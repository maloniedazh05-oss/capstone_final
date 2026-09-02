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
            <button>View</button>
        </form>

        <?php 
        // indexed array: [0 => oldest day quantity, 1095 => today quantity] - empty days = 0, May simplify system into KG or Sac -- Might be Sac since its fertilizer
        $total = 0;
        $count = 0;
        $data = []; 
        $data_assoc = []; // ['Y-m-d'=> qty] - to align date keys for Python forecasting 
        if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['test1'])) {
            $today = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime($today . ' -1095 days'));
            
            // Forecasting preparng - Fetch each day total quantity from 3 years ago to today
            echo "Today: " . $today;
            echo "<br>Start ( -1095 days ): " . $start_date;

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
            // Append each day's total quantity; render empty day as 0
            $cursor = $start_date;
            while ($cursor <= $today) {
                $qty = $qty_map[$cursor] ?? 0;
                $data[] = $qty;
                $data_assoc[$cursor] = $qty;
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }

            echo "<br>Total days: " . count($data) . " (expected 1096 incl. today)";
            echo "<br><br><strong>Daily totals (oldest -> today):</strong><br>";
            echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
            echo "<pre>" . htmlspecialchars(print_r($data_assoc, true)) . "</pre>";
            // Ready for Python: json_encode the indexed array
            echo "<br><strong>JSON for Python:</strong><br>";
            echo "<pre>" . htmlspecialchars(json_encode($data)) . "</pre>";
        }
            
            
/*
           while($current_day != $today) {
            $previous_day = date('Y-m-d', strtotime($current_day. '-1 day')) . ' 00:00:00';
            $current_day = date('Y-m-d', strtotime($current_day . '+1 day')) . ' 00:00:00';
            $stmt_day = $pdo->prepare("SELECT quantity, status, updated_at FROM inventory WHERE updated_at >= :previous_day AND updated_at <= :current_day AND status = :status");
            $stmt_day->execute([':status' => 'Completed', ':previous_day'=> $previous_day, ':current_day'=> $current_day]);
            if($current_day ==$today) break;
            while($day = $stmt_day->fetch(PDO::FETCH_ASSOC)) {
                $test2 += $day['quantity'];
            }
            $data[] = $test2;
            $test2 = 0;
           }
            if($today && $end_yr) {
                $stmt = $pdo->prepare("SELECT prod_id, product, quantity, status, created_at FROM inventory WHERE status = :status AND created_at >= :start AND created_at <= :end");   
                $stmt->execute([':status' => 'Completed', ':start' => $end_yr, ':end'=> $today]);  
            }
        }*/
        ?>
        
    </div> <!--Forecastingpage END-->
</body>
</html>