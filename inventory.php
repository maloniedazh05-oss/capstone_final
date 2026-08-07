
<?php 
require_once "php_backend/session.php";

requireRole(['admin', 'manager']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
        </ul>
    </div>   
</body>
</html>