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


# Fetch user id then check if status is disabled, if yes then header to login.php
$stmt = $pdo->prepare("SELECT status, name FROM accounts WHERE id = :id");
$stmt->execute(['id'=>$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user['status'] == 'disabled') {
    header("Location: php_backend/logout.php");
    exit;
}

$userFullName = $user['name'];


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


require_once __DIR__ . "/.private/account.php";
$account = PrivateAccount::getInstance();
$myUser = $account->getUserAdmin();
$myPassword = $account->getPasswordAdmin();
$myName = $account->getAdminName();

function adminExists() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE admin = 1");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    return $count > 0;
}

if (!adminExists()) {
    $stmt = $pdo->prepare("INSERT INTO accounts (role, user, pass, admin) VALUES (:role, :user, :pass, :admin)");
    $stmt->execute(['role' => 'admin', 'user' => $myUser, 'pass' => password_hash($myPassword, PASSWORD_ARGON2ID), 'admin' => true]);
}
?>