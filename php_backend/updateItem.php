<?php 
require_once "db.php";

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['edit_id']) && isset($_POST['product_name']) && isset($_POST['quantity'])) {
    $id = trim($_POST['edit_id']);
    $product = trim($_POST['product_name']);
    $quantity = (int)$_POST['quantity'];
    $metric = trim($_POST['metrics']);
    $description = trim($_POST['description'] ?? '');

    # Update to Check status
    $status = "Recent";
    $statusSelection = $_POST['status'] ?? 'Recent';
    //if ($statusSelection == 'custom' && isset($_POST['status-custom'])) {
     //   $status = trim($_POST['status-custom']);
    //} else
    if ($statusSelection == 'Processing') {
        $status = 'Processing';
    } elseif ($statusSelection == 'Sorted') {
        $status = 'Sorted';
    } elseif ($statusSelection == 'Completed') {
        $status = 'Completed';
    }

    $stmt = $pdo->prepare("UPDATE inventory SET product = :prod, quantity = :quan, unit = :unit, status = :status, description = :description, updated_at = NOW() WHERE prod_id = :id");
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':prod', $product);
    $stmt->bindValue(':quan', $quantity);
    $stmt->bindValue(':unit', $metric);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':description', $description);

    if($stmt->execute()) {
        header("Location: ../inventory.php?success=1");
        exit;
    } else {
        header("Location: ../inventory.php?error=1");
        exit;
    }
}
?>