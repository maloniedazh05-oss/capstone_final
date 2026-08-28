<?php 
require_once "db.php";

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['id']) && isset($_POST['status'])) {
    $status = $_POST['status'] ?? '';
    $id = $_POST['id'] ?? '';

    $stmt = $pdo->prepare("UPDATE production SET status = :status WHERE id = :id");
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':id', $id);

    if($stmt->execute()) {
        header("Location: ../production.php?updated=1");
        exit;
    } else {
        header("Location: ../production.php?error=1");
        exit;
    }
}
?>