<?php
require_once "php_backend/session.php";

requireRole(['admin']);

// Hybrid forecaster lives in php_backend/forecast_lib.php (shared with reports.php).
require_once "php_backend/forecast_lib.php";

// (fitHolt and fitHW live in forecast_lib.php too.)

// Fertilizer types available for forecasting.
$prodOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);

$selProduct = $_POST['product'] ?? ($prodOpts[0] ?? 'Vermicast');
if (!in_array($selProduct, $prodOpts, true)) {
    $selProduct = $prodOpts[0] ?? 'Vermicast';
}
$selPeriod = $_POST['period'] ?? '14';
if (!in_array($selPeriod, ['7', '14', '30'], true)) {
    $selPeriod = '14';
}
$steps = (int)$selPeriod;

// Input range: how many past days feed the models (default 365).
$selRange = $_POST['range'] ?? '90';
if (!in_array($selRange, ['30', '90', '180', '365'], true)) {
    $selRange = '90';
}
$rangeDays = (int)$selRange;

// Method: auto-select by best MSE, or one arm forced (guarded by eligibility).
$selMethod = $_POST['method'] ?? 'holt';
if (!in_array($selMethod, ['auto', 'reg', 'holt', 'hw'], true)) {
    $selMethod = 'holt';
}

// Available history range label for the selected product.
$rangeStmt = $pdo->prepare("SELECT MIN(updated_at) AS mn, MAX(updated_at) AS mx FROM inventory WHERE status = 'Completed' AND product = :prod");
$rangeStmt->execute([':prod' => $selProduct]);
$rangeRow = $rangeStmt->fetch(PDO::FETCH_ASSOC);
$rangeLabel = ($rangeRow && $rangeRow['mn']) ? date('F Y', strtotime($rangeRow['mn'])) . ' - ' . date('F Y', strtotime($rangeRow['mx'])) : 'No completed data';

// Current inventory of the selected product.
$curStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE status != 'Completed' AND product = :prod");
$curStmt->execute([':prod' => $selProduct]);
$currentStock = (int)$curStmt->fetchColumn();

$forecast = null;
$method = '';
$histWarn = false;
$thinNotice = false;
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['generate'])) {
    $today = date('Y-m-d');

    $alpha = 0.3;
    $res = runForecast($pdo, $selProduct, $rangeDays, $steps, $selMethod);
    $forecast = $res['forecast'];
    $method = $res['method'];
    $downgradeNote = $res['note'];
    $thinNotice = $res['thin'];
    $total = array_sum($forecast);

    // Record the run. The table may not exist on old DBs yet - never fatal a forecast.
    // Older tables lack method/range_days - retry without them so old DBs still save.
    // Thin-history refusals save nothing - there is no forecast to record.
    if (!$thinNotice) {
    try {
        $hist = $pdo->prepare("INSERT INTO forecasting_history (product, period_days, alpha, total_demand, daily_json, method, range_days) VALUES (:prod, :days, :alpha, :total, :daily, :method, :range)");
        $hist->execute([':prod' => $selProduct, ':days' => $steps, ':alpha' => $alpha, ':total' => $total, ':daily' => json_encode($forecast), ':method' => $method, ':range' => $rangeDays]);
    } catch (Exception $e) {
        try {
            $hist = $pdo->prepare("INSERT INTO forecasting_history (product, period_days, alpha, total_demand, daily_json) VALUES (:prod, :days, :alpha, :total, :daily)");
            $hist->execute([':prod' => $selProduct, ':days' => $steps, ':alpha' => $alpha, ':total' => $total, ':daily' => json_encode($forecast)]);
        } catch (Exception $e2) {
            $histWarn = true;
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecasting</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="assets/node_modules/chart.js/dist/chart.umd.js"></script>
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
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="fmethod">Forecasting Method</label>
                        <select id="fmethod" name="method" required>
                            <option value="auto" <?= $selMethod === 'auto' ? 'selected' : '' ?>>Auto-select (Recommended)</option>
                            <option value="reg" <?= $selMethod === 'reg' ? 'selected' : '' ?>>Linear Regression</option>
                            <option value="holt" <?= $selMethod === 'holt' ? 'selected' : '' ?>>Holt's Linear Trend (needs 14+ days)</option>
                            <option value="hw" <?= $selMethod === 'hw' ? 'selected' : '' ?>>Holt-Winters Seasonal, period 7 (needs 56+ days)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <div class="section-desc" id="method-best" style="margin: 0;"></div>
                    </div>
                    </div>
                    <details>
                        <summary>Advanced</summary>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Historical Data</label>
                        <div><strong><?= htmlspecialchars($rangeLabel) ?></strong> (last <?= htmlspecialchars($selRange) ?> days evaluated)</div>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="range">Input Range</label>
                        <select id="range" name="range" required>
                            <option value="30" <?= $selRange === '30' ? 'selected' : '' ?>>30 Days</option>
                            <option value="90" <?= $selRange === '90' ? 'selected' : '' ?>>90 Days</option>
                            <option value="180" <?= $selRange === '180' ? 'selected' : '' ?>>180 Days</option>
                            <option value="365" <?= $selRange === '365' ? 'selected' : '' ?>>365 Days</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="period">Forecast Period</label>
                        <select id="period" name="period" required>
                            <option value="7" <?= $selPeriod === '7' ? 'selected' : '' ?>>7 Days</option>
                            <option value="14" <?= $selPeriod === '14' ? 'selected' : '' ?>>14 Days</option>
                            <option value="30" <?= $selPeriod === '30' ? 'selected' : '' ?>>30 Days</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <div class="section-desc" style="margin: 0;">7 days: reliable · 14 days: fine if steady · 30 days: rough direction only.</div>
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label>Method</label>
                        <div><?= $forecast !== null ? htmlspecialchars($method) . ($downgradeNote !== '' ? ' - ' . htmlspecialchars($downgradeNote) : '') : 'Hybrid: steady trends use Linear Regression, shifting trends use Holt\'s Linear Trend, weekly rhythms use Seasonal Holt-Winters (arm auto-selected by best fit)' ?></div>
                    </div>
                    <input type="hidden" name="generate" value="1">                      
                    </details>

                    <button type="submit" class="btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate Forecast</button>
                </form>
                <script>
                // Method-first UX: each arm shows and applies its best settings.
                // HW-seasonal also needs 56+ days of input - disabled for short ranges.
                // The server re-checks everything; this only guides the picker.
                (function () {
                    var rangeSel = document.getElementById('range');
                    var periodSel = document.getElementById('period');
                    var methodSel = document.getElementById('fmethod');
                    var bestNote = document.getElementById('method-best');
                    var methodBest = {
                        reg: { range: '30', period: '7', text: 'Best for Linear Regression: 30 days input · 7 days forecast.' },
                        holt: { range: '90', period: '14', text: "Best for Holt's Linear Trend: 90 days input · 14 days forecast." },
                        hw: { range: '365', period: '7', text: 'Best for Seasonal Holt-Winters: 365 days input · 7 days forecast.' }
                    };
                    function syncMethodOpts() {
                        var hwOpt = methodSel.querySelector('option[value="hw"]');
                        var ok = parseInt(rangeSel.value, 10) >= 56;
                        hwOpt.disabled = !ok;
                        if (!ok && methodSel.value === 'hw') {
                            methodSel.value = 'auto';
                        }
                    }
                    function syncBest() {
                        var best = methodBest[methodSel.value];
                        if (best) {
                            rangeSel.value = best.range;
                            periodSel.value = best.period;
                            bestNote.textContent = best.text;
                        } else {
                            bestNote.textContent = 'Auto-select fits every eligible arm and runs the best fit.';
                        }
                        syncMethodOpts();
                    }
                    rangeSel.addEventListener('change', syncMethodOpts);
                    methodSel.addEventListener('change', syncBest);
                    syncBest();
                })();
                </script>
            </div>
        </div>

        <?php if ($thinNotice): ?>
        <div class="content-card">
            <div class="card-body">
                <p class="section-desc">Not enough history yet - forecasts unlock after about 15 days of sales for this product.</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($forecast !== null && !$thinNotice): ?>
        <?php
        $total = array_sum($forecast);
        $avg = $forecast ? round($total / count($forecast), 1) : 0;
        $diff = $currentStock - $total;
        // Chart: last 30 history days (solid) + forecast (dashed).
        $tailStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at >= :start GROUP BY DATE(updated_at)");
        $tailStmt->execute([':prod' => $selProduct, ':start' => date('Y-m-d', strtotime($today . ' -29 days')) . ' 00:00:00']);
        $tailMap = [];
        while ($tailRow = $tailStmt->fetch(PDO::FETCH_ASSOC)) {
            $tailMap[$tailRow['day']] = (int)$tailRow['total_qty'];
        }
        $histLabels = [];
        $histVals = [];
        $td = date('Y-m-d', strtotime($today . ' -29 days'));
        while ($td <= $today) {
            $histLabels[] = date('M d', strtotime($td));
            $histVals[] = $tailMap[$td] ?? 0;
            $td = date('Y-m-d', strtotime($td . ' +1 day'));
        }
        $fcLabels = [];
        $fd = $today;
        for ($i = 1; $i <= $steps; $i++) {
            $fd = date('Y-m-d', strtotime($fd . ' +1 day'));
            $fcLabels[] = date('M d', strtotime($fd));
        }
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
                <div class="forecast-summary-bar">
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-basket-shopping"></i> Current Inventory:</span>
                        <span class="summary-value"><strong><?= number_format($currentStock) ?></strong> Sacks</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-chart-simple"></i> Forecasted Demand:</span>
                        <span class="summary-value"><strong><?= number_format($total) ?></strong> Sacks</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label"><i class="fa-solid fa-scale-balanced"></i> Difference:</span>
                        <span class="summary-value"><strong><?= ($diff >= 0 ? '+' : '') . number_format($diff) ?></strong> Sacks</span>
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
        const fcHistLabels = <?= json_encode($histLabels) ?>;
        const fcHistVals = <?= json_encode($histVals) ?>;
        const fcLabels = <?= json_encode($fcLabels) ?>;
        const fcVals = <?= json_encode($forecast) ?>;
        const fcNulls = new Array(fcHistVals.length).fill(null);
        new Chart(document.getElementById('forecastChart'), {
            data: {
                labels: fcHistLabels.concat(fcLabels),
                datasets: [
                    { type: 'line', label: 'Actual', data: fcHistVals.concat(new Array(fcVals.length).fill(null)), borderColor: '#22c55e', tension: 0.3, pointRadius: 2, spanGaps: false },
                    { type: 'line', label: 'Forecast', data: fcNulls.concat(fcVals), borderColor: '#3b82f6', borderDash: [6, 4], tension: 0.3, pointRadius: 2, spanGaps: false }
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
                <h2><i class="fa-solid fa-calendar-days"></i> Forecasted Demand (<?= htmlspecialchars($method) ?>, daily avg <?= htmlspecialchars($avg) ?> Sacks)</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Forecast</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $fd = $today; ?>
                            <?php foreach ($forecast as $qty): ?>
                            <?php $fd = date('Y-m-d', strtotime($fd . ' +1 day')); ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M d', strtotime($fd))) ?></td>
                                <td><strong><?= htmlspecialchars($qty) ?> Sacks</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div> <!--Forecastingpage END-->
</body>
</html>
