<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff', 'manager']);

// Monthly Holt's DES forecaster lives in php_backend/forecast_lib.php (shared with reports.php).
require_once "php_backend/forecast_lib.php";

// Fertilizer types available for forecasting.
$prodOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);

$selProduct = $_POST['product'] ?? ($prodOpts[0] ?? 'Vermicast');
if (!in_array($selProduct, $prodOpts, true)) {
    $selProduct = $prodOpts[0] ?? 'Vermicast';
}

// Available history range label for the selected product.
$rangeStmt = $pdo->prepare("SELECT MIN(updated_at) AS mn, MAX(updated_at) AS mx FROM inventory WHERE status = 'Completed' AND product = :prod");
$rangeStmt->execute([':prod' => $selProduct]);
$rangeRow = $rangeStmt->fetch(PDO::FETCH_ASSOC);
$rangeLabel = ($rangeRow && $rangeRow['mn']) ? date('F Y', strtotime($rangeRow['mn'])) . ' to ' . date('F Y', strtotime($rangeRow['mx'])) : 'No completed data';

// Current inventory of the selected product (single-row total ledger).
$curStmt = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
$curStmt->execute();
$currentStock = round((float)($curStmt->fetchColumn() ?? 0), 2);

$forecast = null;
$method = '';
$fcAlpha = 0;
$fcBeta = null;
$fcMonths = 0;
$fcComplete = 0;
$fcMtd = null;
$fcNextMonth = '';
$fcSeries = [];
$fcSavedAt = '';
$histWarn = false;
$thinNotice = false;
// Staff gets a read-only view: latest saved run, never a fresh generate
// (even a forged POST can't trigger one - the generate branch is closed).
$isStaffView = ($_SESSION['user_role'] ?? '') === 'staff';
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['generate']) && !$isStaffView) {
    $res = runForecast($pdo, $selProduct);
    $forecast = $res['forecast'];
    $method = $res['method'];
    $thinNotice = $res['thin'];
    $fcAlpha = $res['alpha'] ?? 0;
    $fcBeta = $res['beta'] ?? 0;
    $fcMonths = $res['months'] ?? 0;
    $fcComplete = $res['complete'] ?? 0;
    $fcMtd = $res['mtd'] ?? null;
    $fcNextMonth = $res['nextMonth'] ?? '';
    $fcSeries = $res['series'] ?? [];

    // Record the run. The table may not exist on old DBs yet - never fatal a forecast.
    // Thin-history refusals save nothing - there is no forecast to record.
    if (!$thinNotice) {
        try {
            $hist = $pdo->prepare("INSERT INTO forecasting_history (product, months_used, alpha, beta, method, forecast_qty, forecast_month, monthly_json) VALUES (:prod, :months, :alpha, :beta, :method, :qty, :fmonth, :monthly)");
            $hist->execute([':prod' => $selProduct, ':months' => $fcMonths, ':alpha' => $fcAlpha, ':beta' => $fcBeta, ':method' => $method, ':qty' => $forecast[0], ':fmonth' => $fcNextMonth, ':monthly' => json_encode($fcSeries)]);
        } catch (Exception $e) {
            $histWarn = true;
        }
    }
}
if ($isStaffView) {
    // Staff view is read-only: no generate branch exists below for them,
    // so fall through to the retained-run loader (even a forged POST with
    // generate=1 lands here, never in a fresh compute).
    $thinNotice = false;
}
if ($forecast === null) {
    // Retained-run loader: show the previous calculation on page load.
    // Display only, never saved.
    try {
        $latest = $pdo->prepare("SELECT forecast_qty, forecast_month, months_used, alpha, beta, method, monthly_json, created_at FROM forecasting_history WHERE product = :prod ORDER BY id DESC LIMIT 1");
        $latest->execute([':prod' => $selProduct]);
        $lastRun = $latest->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lastRun = false;
    }
    if ($lastRun) {
        $forecast = [(float)$lastRun['forecast_qty']];
        $method = $lastRun['method'] ?? "Holt's DES (monthly)";
        $fcAlpha = $lastRun['alpha'];
        $fcBeta = $lastRun['beta'] !== null ? $lastRun['beta'] : null;
        $fcMonths = (int)$lastRun['months_used'];
        $fcNextMonth = $lastRun['forecast_month'];
        $fcSeries = json_decode($lastRun['monthly_json'] ?? '[]', true) ?: [];
        $fcSavedAt = $lastRun['created_at'];
    } else {
        $thinNotice = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecasting</title>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    <?php $NEED_CHART = true; require_once "php_backend/head_assets.php"; ?>
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>
    <div class="forecastingpage">
        <div class="page-header">
            <h1>Demand Forecasting</h1>
        </div>
        <p class="section-desc">Generate a forecast based on historical data.</p>

        <!-- Forecast Settings -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-sliders"></i> Forecast Settings</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="forecasting.php">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="product">Fertilizer Type</label>
                        <select id="product" name="product" required>
                            <?php foreach ($prodOpts as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $selProduct === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Historical Data</label>
                        <div><strong><?= htmlspecialchars($rangeLabel) ?> + Current Month</strong> (12 - 36 Months)</div>
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <!-- For DEBUGGING<label>Method</label>
                        <div>Holt's Double Exponential Smoothing (monthly): next month predicts smoothed level plus trend of monthly demand, with &alpha; and &beta; tuned per run (0.05-0.95). Needs 12 months of sales (complete months + month-to-date).</div>
                    </div>-->
                    <?php if ($isStaffView): ?>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-eye"></i> View Latest Forecast</button>
                    <p class="section-desc" style="margin:10px 0 0;">View-only access: staff can see saved forecasts but cannot generate new ones.</p>
                    <?php else: ?>
                    <input type="hidden" name="generate" value="1">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate Forecast</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($thinNotice): ?>
        <div class="content-card">
            <div class="card-body">
                <?php if ($isStaffView): ?>
                <p class="section-desc">No saved forecast for this product yet - an admin or manager needs to generate one first. (View-only access: staff cannot run new forecasts.)</p>
                <?php else: ?>
                <p class="section-desc">Not enough history yet - forecasts need 12 months of sales (complete months + month-to-date) for this product (found <?= (int)$fcMonths ?>).</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($forecast !== null && !$thinNotice): ?>
        <?php
        $fcQty = round((float)$forecast[0], 2);
        $fcShort = max(0, round($fcQty - $currentStock, 2));
        $fcNextLabel = $fcNextMonth !== '' ? date('M Y', strtotime($fcNextMonth . '-01')) : '';
        $fmtQty = function ($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
        $mLabel = function ($pt) { $l = date('M Y', strtotime($pt['key'] . '-01')); return !empty($pt['partial']) ? $l . ' (to date)' : $l; };
        $chLabels = [];
        $chActual = [];
        foreach ($fcSeries as $pt) {
            $chLabels[] = $mLabel($pt);
            $chActual[] = $pt['qty'];
        }
        // Current-month bar (chart/table layer only - never enters the fit):
        // shows where stock sales stand right now, even at zero. Fresh runs
        // carry the MTD raw value; retained runs need a live query since a
        // zero-MTD month was never saved into monthly_json.
        $curKey = date('Y-m');
        $curLabel = date('M Y', strtotime($curKey . '-01')) . ' (to date)';
        $lastKey = !empty($fcSeries) ? end($fcSeries)['key'] : '';
        $curActual = null;
        if ($lastKey !== $curKey) {
            if ($fcMtd !== null && ($fcMtd['key'] ?? '') === $curKey) {
                $curActual = round((float)$fcMtd['raw'], 2);
            } else {
                $curStmt2 = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status = 'Completed' AND product = :prod AND YEAR(updated_at) = YEAR(CURDATE()) AND MONTH(updated_at) = MONTH(CURDATE())");
                $curStmt2->execute([':prod' => $selProduct]);
                $curActual = round((float)$curStmt2->fetchColumn(), 2);
            }
            $chLabels[] = $curLabel;
        }
        $chLabels[] = $fcNextLabel . ' (fc)';
        // Full-length datasets: actual (green), current-month-to-date
        // (amber, single bar), forecast (blue, single bar).
        $padSeries = array_fill(0, count($fcSeries), null);
        $padMid = $curActual !== null ? [null] : [];
        $dsActual = array_merge($chActual, $padMid, [null]);
        $dsCur = array_merge($padSeries, $curActual !== null ? [$curActual] : [], [null]);
        $dsFc = array_merge($padSeries, $padMid, [$fcQty]);
        ?>
        <!-- Forecast Result -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-square-poll-vertical"></i> Forecast Result</h2>
            </div>
            <div class="card-body">
                <?php if ($histWarn): ?>
                <div class="feedback-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Run computed but not saved (forecasting_history table missing - run its CREATE from database_query).</span>
                </div>
                <?php endif; ?>
                <h3 style="margin: 0 0 4px; font-size: 1.05rem;">Demand Forecast Overview</h3>
                <?php if ($fcSavedAt !== '' && !isset($_POST['generate'])): ?>
                <p class="section-desc" style="margin: 0 0 14px;">Last calculated: <?= htmlspecialchars(date('M d, Y h:i A', strtotime($fcSavedAt))) ?> - retained run, generate a fresh forecast for updated numbers.</p>
                <?php endif; ?>
                <div class="info-cards">
                    <div class="stat-card stat-card-green">
                        <h2>Predicted Demand</h2>
                        <h3 class="conv-val" data-sacks="<?= $fcQty ?>" data-unit="Sacks"><?= $fmtQty($fcQty) ?> Sacks</h3>
                        <div class="stat-sub"><?= htmlspecialchars($fcNextLabel) ?></div>
                    </div>
                    <div class="stat-card stat-card-blue">
                        <h2>Available Stock</h2>
                        <h3 class="conv-val" data-sacks="<?= $currentStock ?>" data-unit="Sacks"><?= $fmtQty($currentStock) ?> Sacks</h3>
                        <div class="stat-sub">Current inventory</div>
                    </div>
                    <div class="stat-card stat-card-amber">
                        <h2>Estimated Shortfall</h2>
                        <h3 class="conv-val" data-sacks="<?= $fcShort ?>" data-unit="Sacks"><?= $fmtQty($fcShort) ?> Sacks</h3>
                        <div class="stat-sub">Illustrative planning estimate</div>
                    </div>
                    <div class="stat-card stat-card-purple">
                        <h2>Forecast Period</h2>
                        <h3>1 Month</h3>
                        <div class="stat-sub"><?= htmlspecialchars($fcNextLabel) ?>, <?= (int)$fcMonths ?> pts<?= $fcMtd !== null ? ' incl. MTD' : '' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical vs Forecast -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-line"></i> Historical vs Forecast</h2>
            </div>
            <div class="card-body">
                <div style="height: 320px;"><canvas id="forecastChart"></canvas></div>
            </div>
        </div>
        <script>
        const fcLabels = <?= json_encode($chLabels) ?>;
        new Chart(document.getElementById('forecastChart'), {
            data: {
                labels: fcLabels,
                datasets: [
                    { type: 'bar', label: 'Actual (monthly)', data: <?= json_encode($dsActual) ?>, backgroundColor: 'rgba(34,197,94,0.6)' },
                    { type: 'bar', label: 'Current month (to date)', data: <?= json_encode($dsCur) ?>, backgroundColor: 'rgba(245,158,11,0.85)' },
                    { type: 'bar', label: 'Forecast', data: <?= json_encode($dsFc) ?>, backgroundColor: 'rgba(59,130,246,0.85)' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true }},
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Sacks' }}}
            }
        });
        </script>

        <!-- Forecasted Demand -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-calendar-days"></i> Forecasted Demand (<?= htmlspecialchars($method) ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Actual</th>
                                <th>Forecast</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fcSeries as $pt): ?>
                            <tr>
                                <td><?= htmlspecialchars($mLabel($pt)) ?></td>
                                <td><strong><?= $fmtQty($pt['qty']) ?> Sacks</strong></td>
                                <td>-</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ($curActual !== null): ?>
                            <tr>
                                <td><?= htmlspecialchars($curLabel) ?></td>
                                <td><strong><?= $fmtQty($curActual) ?> Sacks</strong></td>
                                <td>-</td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($fcNextLabel) ?></strong></td>
                                <td>-</td>
                                <td><strong><?= $fmtQty($fcQty) ?> Sacks</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div> <!--Forecastingpage END-->
    <script>
    // Card value conversion (temporary display only): Sacks x 50 = KG, KG / 50 = Sacks.
    // Desktop (>768px): hover converts, leave restores, taps ignored.
    // Mobile (<=768px): hover disabled, tap toggles original/converted.
    // Crossing the breakpoint resets every card to its original text.
    (function () {
        const isMobile = window.matchMedia('(max-width: 768px)');
        const rate = 50;
        const fmt = v => {
            v = Math.round(v * 100) / 100;
            return Number.isInteger(v) ? String(v) : v.toFixed(2);
        };
        const converted = el => {
            const sacks = parseFloat(el.dataset.sacks || '0');
            const unit = el.dataset.unit || 'Sacks';
            const base = unit === 'KG' ? sacks : sacks * rate;
            const outUnit = unit === 'KG' ? ' Sacks' : ' KG';
            const sign = base < 0 ? '-' : (el.dataset.plus === '1' && base > 0 ? '+' : '');
            return sign + fmt(Math.abs(base)) + outUnit;
        };
        const cards = [];
        document.querySelectorAll('.conv-val').forEach(el => {
            const card = el.closest('.stat-card');
            if (!card) return;
            const state = { el, orig: el.textContent, flipped: false };
            cards.push(state);
            card.addEventListener('mouseenter', () => {
                if (isMobile.matches) return;
                el.textContent = converted(el);
            });
            card.addEventListener('mouseleave', () => {
                if (isMobile.matches) return;
                el.textContent = state.orig;
                state.flipped = false;
            });
            card.addEventListener('click', () => {
                if (!isMobile.matches) return;
                state.flipped = !state.flipped;
                el.textContent = state.flipped ? converted(el) : state.orig;
            });
        });
        const resetAll = () => cards.forEach(s => { s.flipped = false; s.el.textContent = s.orig; });
        if (typeof isMobile.addEventListener === 'function') {
            isMobile.addEventListener('change', resetAll);
        }
    })();
    </script>
</body>
</html>
