<?php 
require_once "php_backend/session.php";

requireRole(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales</title>
</head>
<body>
    <?php require_once "main-sidebar.php"; ?>
    <?php 
    require_once "php_backend/db.php";

    // Fetch status completed in inventory from selected month.
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
        <button type="submit">View Month Sales</button>
        </form>
</body>
</html>