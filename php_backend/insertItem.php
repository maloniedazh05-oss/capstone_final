<?php 
require_once "db.php";

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['product_name']) && isset($_POST['quantity'])) {
    $product = trim($_POST['product_name']);
    $quantity = (int)$_POST['quantity'];
    $metric = trim($_POST['metrics']);
    $description = trim($_POST['description'] ?? '');

    $date = date('Y-m-d');
    
    # Generate random ID
    $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);

    # Check status
    $status = "Recent";
    $statusSelection = $_POST['status'] ?? 'Recent';
    if ($statusSelection == 'custom' && isset($_POST['status-custom'])) {
        $status = trim($_POST['status-custom']);
    } elseif ($statusSelection == 'Processing') {
        $status = 'Processing';
    }

    # Check for duplicate ID (very unlikely but safety check)
    $stmt = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
    $stmt->bindValue(':id', $id_num);
    $stmt->execute();
    if($stmt->fetchColumn()) {
        // Retry with new ID
        $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
    }

    $stmt = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, metric, status, description, order_date) VALUES (:id, :prod, :quan, :metric, :status, :description, :order)");
    $stmt->bindValue(':id', $id_num);
    $stmt->bindValue(':prod', $product);
    $stmt->bindValue(':quan', $quantity);
    $stmt->bindValue(':metric', $metric);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':order', $date);

    if($stmt->execute()) {
        header("Location: ../inventory.php?success=1");
        exit;
    } else {
        header("Location: ../inventory.php?error=1");
        exit;
    }
}
?>