<?php 
require_once "db.php";

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['product_name']) && isset($_POST['quantity'])) {
    $product = trim($_POST['product_name']) ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $metric = trim($_POST['metrics']);

    $date = date('Y-m-d');
    #Generate random chars
    $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);

    $status = "Recent";

    # Check randomchar duplicates
    $stmt = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
    $stmt->bindValue(':id', $id_num);
    $result = $stmt->fetchColumn();
    if($result) {
        echo "Duplicates";
        $stmt = null;
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, metric, status, order_date) VALUES (:id, :prod, :quan, :metric, :status, :order)");
    $stmt->bindValue(':id', $id_num);
    $stmt->bindValue(':prod', $product);
    $stmt->bindValue(':quan', $quantity);
    $stmt->bindValue(':metric', $metric);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':order', $date);

    if($stmt->execute()) {
        echo "Item added successfully";
    }
    header("Location: ../inventory.php");
    echo $date;
    echo $product;
    echo $quantity;
    echo $metric;
    echo "<p></p>";
    echo $id_num;
}
?>