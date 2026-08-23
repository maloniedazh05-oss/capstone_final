
<?php 
require_once "php_backend/session.php";

requireRole(['admin']);
?>

<!--
Summary month date of production, in/out stock -Reports.

50KG per bags = 500Pesos
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>

    <div class="reportspage">
        <?php 
        require_once "php_backend/db.php";

        // Need html date get month first then puzzle the process on selected month.
        $today = date('Y-m-d');

        $start_month = $today . '00:00:00';

        ?>
        <form method="GET">
        <select id="months-select" name="month-selected">
            <?php
            // Check current Year , in checking Jan-Dec
            for($month = 1; $month <= 12; $month++) {

            //This yr, fetch the iteration $month var, then append starting date.
                $date = date('Y-') . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
                //Start to finish of month in this yr
                $stmt = $pdo->prepare("SELECT * FROM production WHERE production_date >= :start_date AND production_date < :end_date"); 
                
                $stmt->execute(['start_date' => $date, 'end_date' => date('Y-m-t 23:59:59', strtotime($date))]);
                if($stmt->rowCount() > 0) {
                    echo "<option value='" . date('Y-m-01 00:00:00', strtotime($date)) . "'>" . date('F', strtotime($date)) . "</option>";
                }
            }
            $stmt_count = $pdo->prepare("SELECT COUNT(*) production_date FROM production");
            $stmt_count->execute();
            $count = $stmt_count->fetchAll();
            if(!$count || $count == 0) echo "<option>No Record</option>";
            ?>
        </select>
        <button type="submit">View Month Report</button>
        </form>
            <!--View month report-->
        <?php 
        require_once "php_backend/db.php";
        
        $stmt = null;

        if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['month-selected'])) {
            $month = $_GET['month-selected'];
            $get_month = date('m', strtotime($month));
            //echo $get_month . "<br>";
            //echo date('Y-m-01', strtotime($month));
            
            $date_start = date('Y-m-01', strtotime($month)) . ' 00:00:00';
            $date_end = date('Y-m-01', strtotime($month . '+1 month')) . ' 00:00:00';

            echo 'Start: ' . $date_start;
            echo 'End: '. $date_end;

                //Fetch all from selected mopnth fetched.
            $stmt = $pdo->prepare("SELECT production_date, quantity, unit, status FROM production WHERE production_date >= :date_start AND production_date < :date_end");
            $stmt->execute([':date_start' => $date_start, ':date_end'=> $date_end]);
            // January, Feb.. date Format:
            $readable_date = date('F m, Y', strtotime($date_start));

            //Init UNITS -- Display every unit total production of that month:
            $total_sac = 0;
            $total_kg = 0;
            $stock_in = 0;
            $stock_out = 0;
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                if($row['unit'] == 'Sac') {
                    $total_sac += $row['quantity'];
                }
                if($row['unit'] == 'KG') {
                    $total_kg += $row['quantity'];
                }    
                echo $row['quantity'];    

                // Stock in - Fetch an item with no status completed.
                if($row['status'] != 'Completed') {
                    $stock_in += $row['quantity'];
                }

                // Stock out - fetch item with completed staus:
                if($row['status'] == 'Completed') {
                    $stock_out += $row['quantity'];
                }
            }
        }

        ?>

        <div class="production-summary"><!--Production summary START-->
            <?php 
            if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['month-selected'])):
            ?>
            <h2><?=$readable_date?></h2>
            <div id="card-1">
                <p>Total Production(Sac)<?=$total_sac ?? 0?></p>
            </div>
            <div id="card-2">
                <p>Total Production(KG)<?=$total_kg ?? 0?></p>
            </div>
            <div id="card-3">
                <p>Total Stock In: <?=$stock_in ?? 0?></p>
            </div>
            <div id="card-4">
                <p>Total Stock Out: <?=$stock_out ?? 0?></p>
            </div>

        </div> <!--Production summary END-->
        <?php endif; ?>

    </div>
</body>
</html>