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
?>