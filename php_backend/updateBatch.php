<?php 
require_once "db.php";

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['status'])) {
    $status = $_POST['status'] ?? '';
    $id = $_POST['id'] ?? '';
    $quantity = $_POST['quantityIn'];

    // production
    $stmt = $pdo->prepare("UPDATE production SET status = :status WHERE production_id = :id");
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':id', $id);    

    // to inventory. No need to set status for going in inventory must be Recent
    # Check for duplicate ID
    $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
    $stmt_id = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
    $stmt_id->bindValue(':id', $id_num);
    $stmt_id->execute();
    if($stmt_id->fetchColumn()) {
        // Retry with new ID
        $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
    }

    if($status == "Completed") {
    $stmt_fetch = $pdo->prepare("SELECT item, quantity, unit, status FROM production WHERE production_id = :id");
    $stmt_fetch->execute(["id"=>$id]);
    $row_fetch = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, unit, stock_in, created_at) VALUES (:prod_id, :product, :quantity, :unit, :stock_in, NOW())");
        $stmt2->bindValue(":prod_id", $id_num);
        $stmt2->bindValue(":product", $row_fetch['item']);
        $stmt2->bindValue(":quantity", $quantity); // Stock In
        $stmt2->bindValue(":unit", $row_fetch['unit']);
        $stmt2->bindValue(':stock_in', $quantity);
        $stmt2->execute();
    }

    if($stmt->execute()) {
        header("Location: ../production.php?updated=1");
        exit;
    } else {
        header("Location: ../production.php?error=1");
        exit;
    }
}
?>