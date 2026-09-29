<?php 
if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['disable_id'])) {
    require_once "db.php";
    $fetch_id = $_POST['disable_id'];
    $find_id = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = :id");
    
    $find_id->execute([':id'=> $fetch_id]);
    if($find_id->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE accounts SET status = :status WHERE id = :id");
        $stmt->execute([':status' => 'disabled', ':id'=> $fetch_id]);
    }
    $find_id = null;
    $stmt = null;
    header("Location: ../users.php");
}

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['enable_id'])) {
    require_once "db.php";
    $fetch_id = $_POST['enable_id'];
    $find_id = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = :id");
    
    $find_id->execute([':id'=> $fetch_id]);
    if($find_id->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE accounts SET status = :status WHERE id = :id");
        $stmt->execute([':status' => 'active', ':id'=> $fetch_id]);
    }
    $find_id = null;
    $stmt = null;
    header("Location: ../users.php");
}

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['reset_id']) && isset($_POST['new_password'])) {
    require_once "db.php";
    $reset_id = $_POST['reset_id'];
    $new_password = trim($_POST['new_password']);
    if ($new_password === '' || strlen($new_password) < 4) {
        header("Location: ../users.php?error=" . urlencode("Password must be at least 4 characters"));
        exit;
    }
    $find_id = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE id = :id");
    $find_id->execute([':id' => $reset_id]);
    if ($find_id->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE accounts SET pass = :pass WHERE id = :id");
        $stmt->execute([':pass' => password_hash($new_password, PASSWORD_ARGON2ID), ':id' => $reset_id]);
        header("Location: ../users.php?success=" . urlencode("Password reset successfully"));
    } else {
        header("Location: ../users.php?error=" . urlencode("User not found"));
    }
    exit;
}
?>