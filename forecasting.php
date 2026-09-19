<?php 
require_once "php_backend/session.php";

requireRole(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecasting</title>
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>
    <div class="forecastingpage">
        <div class="page-header">
            <h1>Sales Forecasting</h1>
        </div>

        <!-- Action / Configuration Card -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-chart-line"></i> Demand & Sales Forecasting</h2>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="test1">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Forecast Next 30 Days</button>
                </form>
            </div>
            <div class="card-body">
                <p class="section-desc" style="margin: 0;">Generate statistical sales projections for the next 30 days using historical inventory data with Holt-Winters Exponential Smoothing.</p>
            </div>
        </div>

        <?php 
        // indexed array: [0 => oldest day quantity, 1095 => today quantity] - empty days = 0
        if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['test1'])) {
            $today = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime($today . ' -1095 days'));

            $status = 'Completed';
            $start_datetime = $start_date . ' 00:00:00';
            $end_datetime = $today . ' 23:59:59';

            // Fetch data for each day that has data
            $stmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = :status AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
            $stmt->execute([':status' => $status, ':start' => $start_datetime, ':end' => $end_datetime]);

            // Map: 'Y-m-d' => total_qty
            $qty_map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $qty_map[$row['day']] = (int)$row['total_qty'];
            }

            // Build continuous 1096-day array (1095 days diff + today inclusive)
            // Missing days stay 0 so Holt-Winters sees the true pattern including idle days
            $data = [];
            $start = $start_date;
            while ($start <= $today) {
                $data[] = $qty_map[$start] ?? 0;
                $start = date('Y-m-d', strtotime($start . ' +1 day'));
            }

            // Flask route is @app.route("/forecasting") on port 5000,
            // so POST to /forecasting (NOT /py_backend/forecasting.php)
            $url = "http://127.0.0.1:5000/forecasting";
            $data_json = json_encode($data);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Content-Length: " . strlen($data_json)
            ]);

            // Receive python response:
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $result = curl_exec($ch);
            if ($result === false) {
                ?>
                <div class="feedback-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Forecasting microservice is offline. Please start it with: <code>python py_backend/forecasting.py</code></span>
                </div>
                <?php
            } else {
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $decoded = json_decode($result, true);
                if ($http_code != 200 || !isset($decoded['success']) || !$decoded['success'] || !isset($decoded['result'])) {
                    ?>
                    <div class="feedback-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Forecast calculation failed: <?= htmlspecialchars($decoded['error'] ?? $result) ?></span>
                    </div>
                    <?php
                } else {
                    // Clamp small negatives to 0, round to whole sacs
                    $forecast = array_map(function($v) { return max(0, round($v, 1)); }, $decoded['result']);
                    $total = array_sum($forecast);
                    $avg = $forecast ? round($total / count($forecast), 1) : 0;
                    ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-calendar-days"></i> Next 30 Days Forecast Predictions</h2>
                        </div>
                        <div class="card-body">
                            <div class="forecast-meta-info">
                                <i class="fa-solid fa-circle-info"></i>
                                <span>Historical window: <strong><?= htmlspecialchars($start_date) ?></strong> to <strong><?= htmlspecialchars($today) ?></strong> (<?= count($data) ?> days continuous data evaluated)</span>
                            </div>

                            <div class="forecast-summary-bar">
                                <div class="summary-item">
                                    <span class="summary-label"><i class="fa-solid fa-basket-shopping"></i> Total Predicted Demand:</span>
                                    <span class="summary-value"><strong><?= number_format($total) ?></strong> Sac</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label"><i class="fa-solid fa-chart-simple"></i> Daily Average:</span>
                                    <span class="summary-value"><strong><?= htmlspecialchars($avg) ?></strong> Sac/day</span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Date</th>
                                            <th>Predicted Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($forecast as $i => $qty): ?>
                                        <tr>
                                            <td>Day <?= ($i + 1) ?></td>
                                            <td><?= htmlspecialchars(date('F d, Y (l)', strtotime($today . ' +' . ($i + 1) . ' days'))) ?></td>
                                            <td><strong><?= htmlspecialchars($qty) ?> Sac</strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (isset($_GET['debug'])): ?>
                                <div class="forecast-debug-box">
                                    <strong>JSON sent to Python (<?= count($data) ?> values):</strong>
                                    <pre><?= htmlspecialchars($data_json) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            }
        }
        ?>
    </div> <!--Forecastingpage END-->
</body>
</html>
