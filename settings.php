<?php 
require_once "php_backend/session.php";

requireRole(["admin", "staff", "manager"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php 
    include_once "main-sidebar.php";
    ?>
    
</body>
</html>