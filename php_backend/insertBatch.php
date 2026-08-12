<?php 
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item']) && isset($_POST['quantity'])) {
require_once "db.php";

$item = trim($_POST['item']);
$quantity = $_POST['quantity'] ?? '';
$unit = $_POST['unit'];

$batch_id = substr(str_shuffle("0123456789"), 0, 7);
    # Check for duplicate ID (very unlikely but safety check)
    $stmt = $pdo->prepare("SELECT production_id FROM production WHERE production_id = :id");
    $stmt->bindValue(':id', $batch_id);
    $stmt->execute();
    if($stmt->fetchColumn()) {
        // Retry with new ID
        $batch_id = substr(str_shuffle("0123456789"), 0, 7);
    }

$stmt = $pdo->prepare("INSERT INTO production (batch_id, quantity, unit, item) VALUES (:batch_id, :quantity, :unit, :item)");
$stmt->bindValue(':batch_id', $batch_id);
$stmt->bindValue(':quantity', $quantity);
$stmt->bindValue(':unit', $unit);
$stmt->bindValue(':item', $item);

if($stmt->execute()) {
    header("Location: ../production.php");
    exit;
}

echo "Invalid Data";
}
?>