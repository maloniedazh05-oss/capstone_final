<?php
    include_once "php_backend/session.php";
    requireRole(['admin', 'staff', 'manager']);
?>
<!DOCTYPE html">
<html lang="en">
    <head>
        <title id="home_title">Homepage</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <?php require_once "main-sidebar.php"; ?>
    <div class="homepage"></div>
    <script>
    // Test document load to change title:
    /*document.addEventListener('DOMContentLoaded', () => {
        const home_t = document.getElementById('home_title')
        home_t.textContent = "Homepage - Dashboard";
    });*/
</script>
    </body>
</html>