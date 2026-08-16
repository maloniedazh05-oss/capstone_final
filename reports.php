
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
    <div class="main-sidebar">
        <ul>
            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
                <li><a href='dashboard.php'>Dashboard</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
                <li><a href='inventory.php'>Inventory</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
                <li><a href='production.php'>Production</a></li>
            <?php endif; ?>

            <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                <li><a href='Reports.php'>Reports</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="reportspage">
        <?php 
        require_once "php_backend/db.php";

        // Need html date get month first then puzzle the process on selected month.
        $today = date('Y-m-d');

        $start_month = $today . '00:00:00';

        // Select 1 month. From very first day exact 12:00 or 0:00 
       // $stmt = $pdo->prepare("SELECT date FROM production WHERE production_date >= :start_date AND < :end_date ORDER BY production_date ASC"); 
       // $stmt->execute();
// 2026-08-12 15:07:15 // Y-M-D time
        //$totalQuantity = 0;
        //while($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
        //    $totalQuantity += $row['quantity'];
        //}
        //echo "Monthly Quantity Production: " . $totalQuantity;

        ?>
        <form method="GET">
        <select id="months-select">
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
        <button>View Month Report</button>
        </form>
            <!--View month report-->
        <?php 
        require_once "php_backend/db.php";
        
        $stmt = null;
        // Get the unit first
        $stmt = $pdo->prepare("SELECT unit,quantity FROM production WHERE unit = :unit");
        $stmt->execute([':unit'=>'Sac']); // Sac KG
                // For Sac
                $total_sac = 0;
        while($sac = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total_sac += $sac['quantity'];
        }

        // For KG
        $total_kg = 0;
        $stmt->execute([':unit' => 'KG']);
        while($kg = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $total_kg += $kg['quantity'];
        }

        ?>

        <div class="production-summary">
            <div id="card-1">
                <p>Total Quantity(Sac): <?=$total_sac?></p>
            </div>
            <div id="card-2">
                <p>Total Quantity(KG): <?=$total_kg?></p>
            </div>
        </div>

    </div>
</body>
</html>