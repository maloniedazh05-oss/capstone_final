<?php
require_once "php_backend/session.php";

requireRole(['admin', 'staff']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href='style.css'>
</head>
<body>
        <div class="main-sidebar">
        <ul>
            <?php if(in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li><a href='dashboard.php'>Dashboard</a></li>
            <?php endif; ?>

            <?php if(in_array($_SESSION['user_role'], ['admin', 'manager'])): ?>
            <li><a href='inventory.php'>Inventory</a></li>
            <?php endif; ?> 

            <?php if(in_array($_SESSION['user_role'], ['admin', 'staff'])): ?>
            <li><a href='production.php'>Production</a></li>
            <?php endif; ?> 

            <?php if (in_array($_SESSION['user_role'], ['admin'])): ?>
                <li><a href='Reports.php'>Reports</a></li>
            <?php endif; ?>
        </ul>
        </div>

    <div class="dashboardpage">
        <p id="dashboard-notif"></p>
        <div class="info-cards">
            <div class="stat-card">Vermicast Stock</div>
            <div class="stat-card">Next Period Stockout Prediction</div>
            <div class="stat-card">Monthly sales Goal</div>
        </div><!-- info-cards END-->
    </div> <!-- dashboardpage END-->

</body>
</html>