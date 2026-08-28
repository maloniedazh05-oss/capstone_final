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
        $total = 0;
        $count = 0;
        $data = [];
        if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['test1'])) {
            $today = date('Y-m-d');
            $end_yr = date('Y-m-d', strtotime($today . '-1095 day'));
            
            //Forecasting prepatring - Fetching every day data starting from prev yeaer to today.
            //Start from right previous year then check Each day to total
           // $stmt = $pdo->prepare("SELECT quantity, updated_at FROM inventory WHERE updated_at ");
           echo "Today: " . $today;
           echo "<br>Previous: " . $end_yr;
           $test1 = 0;
           $test2= 0;
           $current_day = $end_yr;
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
        }
        ?>
        
        <table>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
            </tr>
        <?php
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)):     
        ?>

        <tr>
            <td><?=$row['product']?></td>
            <td><?=$row['quantity']?></td>
        </tr>
        <?php $total += $row['quantity']; $count++; endwhile; ?>
        </table>
            <h3>Total: <?=$total?></h3>
        <h3>Count: <?=$count?></h3>
        <?php 
        $stmt_day = $pdo->prepare("SELECT ")
        ?>
        <!--<h4>Data: <?php foreach($data as $item) {echo $item;}?></h4>-->
    </div> <!--Forecastingpage END-->
</body>
</html>