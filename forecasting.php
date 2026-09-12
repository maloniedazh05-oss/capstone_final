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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>
    <div class="forecastingpage">
        <form method="POST">
            <input type="hidden" name="test1">
            <button>Forecast Next 30 Days</button>
        </form>

        <?php 
        // indexed array: [0 => oldest day quantity, 1095 => today quantity] - empty days = 0
        if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['test1'])) {
            $today = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime($today . ' -1095 days'));

            echo "Today: " . htmlspecialchars($today);
            echo "<br>Start ( -1095 days ): " . htmlspecialchars($start_date);

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

            echo "<br>Total days: " . count($data) . " (expected 1096 including today)";

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
                echo "<br>Is Flask running? Start it with: <code>python py_backend/forecasting.py</code>";
            } else {
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $decoded = json_decode($result, true);
                if ($http_code != 200 || !isset($decoded['success']) || !$decoded['success'] || !isset($decoded['result'])) {
                    echo "<br>Forecast failed: " . htmlspecialchars($decoded['error'] ?? $result);
                } else {
                    // Clamp small negatives to 0, round to whole sacks
                    $forecast = array_map(function($v) { return max(0, (int)round($v)); }, $decoded['result']);
                    $total = array_sum($forecast);
                    $avg = $forecast ? round($total / count($forecast), 1) : 0;
                    echo "<br><br><strong>Next 30 days prediction (Sack(s)/day):</strong>";
                    echo "<br>Total predicted: " . htmlspecialchars($total) . " | Daily average: " . htmlspecialchars($avg);
                ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Predicted Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forecast as $i => $qty): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($today . ' +' . ($i + 1) . ' days'))) ?></td>
                                <td><strong><?= htmlspecialchars($qty) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                    if (isset($_GET['debug'])) {
                        echo "<br><strong>JSON sent to Python:</strong><br>";
                        echo "<pre>" . htmlspecialchars($data_json) . "</pre>";
                    }
                }
            }
        }
        ?>
        
    </div> <!--Forecastingpage END-->
</body>
</html>
