<?php
    include_once "php_backend/session.php";
    requireRole(['admin', 'staff', 'manager']);
?>
<!DOCTYPE html">
<html lang="en">
    <head>
        <title id="home_title">Homepage</title>
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
        <form action="php_backend/logout.php" method="POST"><button type="submit">Logout</button></form>
    <script>

    // Test document load to change title:
    document.addEventListener('DOMContentLoaded', () => {
        const home_t = document.getElementById('home_title')
        home_t.textContent = "Homepage - Dashboard";
    });
</script>
    </body>
</html>