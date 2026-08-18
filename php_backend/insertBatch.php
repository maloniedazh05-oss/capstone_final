<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item']) && isset($_POST['quantity'])) {
    require_once "db.php";

    $item = trim($_POST['item']);
    $quantity = $_POST['quantity'] ?? '';
    $unit = $_POST['unit'];
    $receiver = $_POST['company'] ?? '';

    $batch_id = substr(str_shuffle("0123456789"), 0, 7);
    # Check for duplicate ID (very unlikely but safety check)
    $stmt = $pdo->prepare("SELECT production_id FROM production WHERE production_id = :id");
    $stmt->bindValue(':id', $batch_id);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        // Retry with new ID
        $batch_id = substr(str_shuffle("0123456789"), 0, 7);
    }

    # INsert into Production db
    $stmt = $pdo->prepare("INSERT INTO production (batch_id, quantity, unit, item, receiver) VALUES (:batch_id, :quantity, :unit, :item, :receiver)");
    $stmt->bindValue(':batch_id', $batch_id);
    $stmt->bindValue(':quantity', $quantity);
    $stmt->bindValue(':unit', $unit);
    $stmt->bindValue(':item', $item);
    $stmt->bindValue(':receiver', $receiver);


    # Auto insert in inventory for every production:
    $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
    $stmt_id = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
    $stmt_id->bindValue(':id', $id_num);
    $stmt_id->execute();
    if ($stmt_id->fetchColumn()) {
        // Retry with new ID
        $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
        $stmt = null;
    }

    $stmt_inventory = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, metric, stock_in) VALUES (:prod_id, :product, :quantity, :unit, :stock_in)");
            $stmt_inventory->bindValue(':prod_id', $id_num);
    $stmt_inventory->bindValue(':product', $item);
    $stmt_inventory->bindValue(':quantity', $quantity);
    $stmt_inventory->bindValue(':unit', $unit);
     $stmt_inventory->bindValue(':stock_in', $quantity);

    if($stmt_inventory->execute()) {
        echo "Added in inventory successfully";
    }

    if ($stmt->execute()) {
        header("Location: ../production.php");
        exit;
    } else {echo "Cannot add into production.";}

    echo "Invalid Data";
}
?>