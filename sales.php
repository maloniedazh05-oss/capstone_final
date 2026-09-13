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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require_once "main-sidebar.php";?>
    <?php 
    require_once "php_backend/db.php";

    // Fetch status completed in inventory from selected month.
    ?>
<div class="salespage">
    <?php if (isset($_GET['import_success'])): ?>
        <p class="feedback-success"><?= htmlspecialchars($_GET['import_success']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['import_error'])): ?>
        <p class="feedback-error"><?= htmlspecialchars($_GET['import_error']) ?></p>
    <?php endif; ?>
    <form method="POST" action="php_backend/import.php" enctype="multipart/form-data">
    <input type="file" accept=".json,.txt" name="historyFile" required>
    <label><input type="checkbox" name="confirm_overwrite" value="yes"> Overwrite previously imported rows in this date range</label>
    <!--<p class="section-desc">Two formats: flat [23,4,0,26] (index 0 = today, one day per value, max 1096, zeros kept) or records [{"prod_id":"PRD002","product":"Vermicast","quantity":34,"unit":"Sac","created_at":"2023-09-06 16:44:37"}] (matched by prod_id in inventory, then marked Completed).</p>
    --><br><button type="submit">Import Historical Data</button>
    </form><br>
        <form method="GET">
        <select id="months-select" name="month-selected">
            <?php
            // Months that have Completed inventory (sold) records
            $stmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(updated_at, '%Y-%m-01 00:00:00') AS month_start, DATE_FORMAT(updated_at, '%M %Y') AS month_label FROM inventory WHERE status = :status ORDER BY month_start DESC");
            $stmt->execute([':status' => 'Completed']);
            $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $selected = $_GET['month-selected'] ?? '';
            if (!$months) {
                echo "<option>No Record</option>";
            }
            foreach ($months as $m) {
                $sel = ($selected == $m['month_start']) ? ' selected' : '';
                echo "<option value='" . htmlspecialchars($m['month_start']) . "'$sel>" . htmlspecialchars($m['month_label']) . "</option>";
            }
            ?>
        </select>
        <button type="submit">View Month Sales</button>
        </form>
        <?php
        // Monthly total + daily breakdown of Completed inventory for the selected month
        if (!empty($_GET['month-selected'])) {
            $month_start = $_GET['month-selected'];
            $month_end = date('Y-m-t 23:59:59', strtotime($month_start));

            $stmt_total = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) AS total_qty, COUNT(*) AS rec_count FROM inventory WHERE status = :status AND updated_at BETWEEN :start AND :end");
            $stmt_total->execute([':status' => 'Completed', ':start' => $month_start, ':end' => $month_end]);
            $total_row = $stmt_total->fetch(PDO::FETCH_ASSOC);

            $stmt_days = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS qty, COUNT(*) AS recs FROM inventory WHERE status = :status AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at) ORDER BY day");
            
            $stmt_days->execute([':status' => 'Completed', ':start' => $month_start, ':end' => $month_end]);
            $days = $stmt_days->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Sales for <?= htmlspecialchars(date('F Y', strtotime($month_start))) ?></h2>
            </div>
            <div class="card-body">
                <p><strong>Total sold:</strong> <?= htmlspecialchars($total_row['total_qty']) ?> Sac (<?= htmlspecialchars($total_row['rec_count']) ?> records)</p>
                <?php if (!$days): ?>
                    <p class="section-desc">No Completed records this month.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Quantity Sold</th>
                                <th>Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['day']) ?></td>
                                <td><strong><?= htmlspecialchars($d['qty']) ?></strong></td>
                                <td><?= htmlspecialchars($d['recs']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php } ?>
        </div><!--Salespage END-->
</body>
</html>
