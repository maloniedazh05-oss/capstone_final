<?php 
require_once "db.php";
if(session_status() == PHP_SESSION_NONE) session_start(); # To fetch the session.
# Check if session if theres none then null.
$id = $_SESSION['user_id'] ?? '';
$user = $_SESSION['user_name'] ?? '';
$role =  $_SESSION['user_role'] ?? '';

if(!$id && !$user) {
    header("Location: login.php");
    exit;
}

# Called on every/most page. Check if role is empty, and invalid
function requireRole($allowed_roles) {
    if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        header("Location: login.php");
        exit;
    }

    if(!in_array($_SESSION['user_role'], $allowed_roles)) {
    http_response_code(403);
    die("Access denied: You do not have permission to view this page!" . "<br><a href='php_backend/logout.php'>Continue</a>");
    }
}
?>