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
    <?php require_once "main-sidebar.php"; ?>
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