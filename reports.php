
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
    <link rel="stylesheet" href="assets/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php";?>

    <div class="reportspage">
        <div class="page-header">
            <h1>Production & Inventory Reports</h1>
        </div>

        <?php 
        require_once "php_backend/db.php";

        // Need html date get month first then puzzle the process on selected month.
        $today = date('Y-m-d');
        $start_month = $today . '00:00:00';
        $selectedMonth = $_GET['month-selected'] ?? '';
        ?>

        <!-- Filter Card -->
        <div class="content-card">
            <div class="card-header card-header-flex">
                <h2><i class="fa-solid fa-calendar-days"></i> Monthly Report Selection</h2>
                <div class="card-filter">
                    <form method="GET" style="display: flex; align-items: center; gap: 10px;">
                        <select id="months-select" name="month-selected">
                            <?php
                            // Check current Year , in checking Jan-Dec
                            for($month = 1; $month <= 12; $month++) {
                                //This yr, fetch the iteration $month var, then append starting date.
                                $date = date('Y-') . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00';
                                $val = date('Y-m-01 00:00:00', strtotime($date));
                                //Start to finish of month in this yr
                                $stmt = $pdo->prepare("SELECT * FROM production WHERE production_date >= :start_date AND production_date < :end_date"); 
                                
                                $stmt->execute(['start_date' => $date, 'end_date' => date('Y-m-t 23:59:59', strtotime($date))]);
                                if($stmt->rowCount() > 0) {
                                    $sel = ($selectedMonth == $val) ? ' selected' : '';
                                    echo "<option value='" . $val . "'$sel>" . date('F Y', strtotime($date)) . "</option>";
                                }
                            }
                            $stmt_count = $pdo->prepare("SELECT COUNT(*) production_date FROM production");
                            $stmt_count->execute();
                            $count = $stmt_count->fetchAll();
                            if(!$count || $count == 0) echo "<option>No Record</option>";
                            ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding: 7px 16px;">View Month Report</button>
                    </form>
                </div>
            </div>
        </div>

        <!--View month report-->
        <?php 
        $stmt = null;

        if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['month-selected'])) {
            $month = $_GET['month-selected'];
            $get_month = date('m', strtotime($month));
            
            $date_start = date('Y-m-01', strtotime($month)) . ' 00:00:00';
            $date_end = date('Y-m-01', strtotime($month . '+1 month')) . ' 00:00:00';

            //Fetch all from selected month fetched.
            $stmt = $pdo->prepare("SELECT production_date, quantity, unit, status FROM production WHERE production_date >= :date_start AND production_date < :date_end");
            $stmt->execute([':date_start' => $date_start, ':date_end'=> $date_end]);
            // January, Feb.. date Format:
            $readable_date = date('F Y', strtotime($date_start));

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

                // Stock in - Fetch an item with no status completed.
                if($row['status'] != 'Completed') {
                    $stock_in += $row['quantity'];
                }

                // Stock out - fetch item with completed status:
                if($row['status'] == 'Completed') {
                    $stock_out += $row['quantity'];
                }
            }
        ?>

        <div class="content-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-chart-pie"></i> Production & Stock Summary &mdash; <?= htmlspecialchars($readable_date) ?></h2>
            </div>
            <div class="card-body">
                <div class="report-cards production-summary">
                    <div class="stat-card" id="card-1">
                        <div class="stat-label"><i class="fa-solid fa-cubes"></i> Total Production (Sac)</div>
                        <div class="stat-value"><?= number_format($total_sac ?? 0) ?></div>
                    </div>
                    <div class="stat-card" id="card-2">
                        <div class="stat-label"><i class="fa-solid fa-weight-scale"></i> Total Production (KG)</div>
                        <div class="stat-value"><?= number_format($total_kg ?? 0) ?></div>
                    </div>
                    <div class="stat-card" id="card-3">
                        <div class="stat-label"><i class="fa-solid fa-arrow-down" style="color: var(--color-success);"></i> Total Stock In</div>
                        <div class="stat-value"><?= number_format($stock_in ?? 0) ?></div>
                    </div>
                    <div class="stat-card" id="card-4">
                        <div class="stat-label"><i class="fa-solid fa-arrow-up" style="color: var(--color-primary);"></i> Total Stock Out</div>
                        <div class="stat-value"><?= number_format($stock_out ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</body>
</html>