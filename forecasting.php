<?php
require_once "php_backend/session.php";

requireRole(['admin']);

// Monthly SES forecaster lives in php_backend/forecast_lib.php (shared with reports.php).
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
$rangeLabel = ($rangeRow && $rangeRow['mn']) ? date('F Y', strtotime($rangeRow['mn'])) . ' - ' . date('F Y', strtotime($rangeRow['mx'])) : 'No completed data';

// Current inventory of the selected product (single-row total ledger).
$curStmt = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
$curStmt->execute();
$currentStock = round((float)($curStmt->fetchColumn() ?? 0), 2);

$forecast = null;
$method = '';
$fcAlpha = 0;
$fcMonths = 0;
$fcNextMonth = '';
$fcSeries = [];
$histWarn = false;
$thinNotice = false;
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['generate'])) {
    $res = runForecast($pdo, $selProduct);
    $forecast = $res['forecast'];
    $method = $res['method'];
    $thinNotice = $res['thin'];
    $fcAlpha = $res['alpha'] ?? 0;
    $fcMonths = $res['months'] ?? 0;
    $fcNextMonth = $res['nextMonth'] ?? '';
    $fcSeries = $res['series'] ?? [];

    // Record the run. The table may not exist on old DBs yet - never fatal a forecast.
    // Thin-history refusals save nothing - there is no forecast to record.
    if (!$thinNotice) {
        try {
            $hist = $pdo->prepare("INSERT INTO forecasting_monthly (product, months_used, alpha, forecast_qty, forecast_month, monthly_json) VALUES (:prod, :months, :alpha, :qty, :fmonth, :monthly)");
            $hist->execute([':prod' => $selProduct, ':months' => $fcMonths, ':alpha' => $fcAlpha, ':qty' => $forecast[0], ':fmonth' => $fcNextMonth, ':monthly' => json_encode($fcSeries)]);
        } catch (Exception $e) {
            $histWarn = true;
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
                        <div><strong><?= htmlspecialchars($rangeLabel) ?></strong> (12 - 36 Months)</div>
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <!-- For DEBUGGING<label>Method</label>
                        <div>Simple Exponential Smoothing (monthly): next month predicts the smoothed level of monthly demand, with &alpha; tuned per run (0.05-0.95). Needs 12+ complete months of history.</div>
                    </div>-->
                    <input type="hidden" name="generate" value="1">

                    <button type="submit" class="btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate Forecast</button>
                </form>
            </div>
        </div>

        <?php if ($thinNotice): ?>
        <div class="content-card">
            <div class="card-body">
                <p class="section-desc">Not enough history yet - forecasts need 12+ complete months of sales for this product (found <?= (int)$fcMonths ?>).</p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($forecast !== null && !$thinNotice): ?>
        <?php
        $fcQty = round((float)$forecast[0], 2);
        $fcShort = max(0, round($fcQty - $currentStock, 2));
        $fcNextLabel = $fcNextMonth !== '' ? date('M Y', strtotime($fcNextMonth . '-01')) : '';
        $fmtQty = function ($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
        $chLabels = [];
        $chActual = [];
        foreach ($fcSeries as $pt) {
            $chLabels[] = date('M Y', strtotime($pt['key'] . '-01'));
            $chActual[] = $pt['qty'];
        }
        $chLabels[] = $fcNextLabel . ' (fc)';
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
                    <span>Run computed but not saved (forecasting_monthly table missing - run its CREATE from database_query).</span>
                </div>
                <?php endif; ?>
                <h3 style="margin: 0 0 14px; font-size: 1.05rem;">Demand Forecast Overview</h3>
                <div class="info-cards">
                    <div class="stat-card stat-card-green">
                        <h2>Predicted Demand</h2>
                        <h3><?= $fmtQty($fcQty) ?> Sacks</h3>
                        <div class="stat-sub"><?= htmlspecialchars($fcNextLabel) ?></div>
                    </div>
                    <div class="stat-card stat-card-blue">
                        <h2>Available Stock</h2>
                        <h3><?= $fmtQty($currentStock) ?> Sacks</h3>
                        <div class="stat-sub">Current inventory</div>
                    </div>
                    <div class="stat-card stat-card-amber">
                        <h2>Estimated Shortfall</h2>
                        <h3><?= $fmtQty($fcShort) ?> Sacks</h3>
                        <div class="stat-sub">Illustrative planning estimate</div>
                    </div>
                    <div class="stat-card stat-card-purple">
                        <h2>Forecast Period</h2>
                        <h3>1 Month</h3>
                        <div class="stat-sub"><?= htmlspecialchars($fcNextLabel) ?> (&alpha; = <?= htmlspecialchars($fcAlpha) ?>, <?= (int)$fcMonths ?> months)</div>
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
        const fcActual = <?= json_encode($chActual) ?>;
        const fcQty = <?= json_encode($fcQty) ?>;
        const fcNulls = new Array(fcActual.length).fill(null);
        new Chart(document.getElementById('forecastChart'), {
            data: {
                labels: fcLabels,
                datasets: [
                    { type: 'bar', label: 'Actual (monthly)', data: fcActual.concat([null]), backgroundColor: 'rgba(34,197,94,0.6)' },
                    { type: 'bar', label: 'Forecast', data: fcNulls.concat([fcQty]), backgroundColor: 'rgba(59,130,246,0.85)' }
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
                <h2><i class="fa-solid fa-calendar-days"></i> Forecasted Demand (<?= htmlspecialchars($method) ?>, &alpha; = <?= htmlspecialchars($fcAlpha) ?>)</h2>
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
                                <td><?= htmlspecialchars(date('M Y', strtotime($pt['key'] . '-01'))) ?></td>
                                <td><strong><?= $fmtQty($pt['qty']) ?> Sacks</strong></td>
                                <td>-</td>
                            </tr>
                            <?php endforeach; ?>
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
</body>
</html>
