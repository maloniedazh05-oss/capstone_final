<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item']) && isset($_POST['quantity'])) {
    require_once "session.php";
    requireRole(['admin', 'staff']);

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
    $stmt = $pdo->prepare("INSERT INTO production (batch_id, quantity,item, unit, receiver) VALUES (:batch_id, :quantity, :item, :unit, :receiver)");
    $stmt->bindValue(':batch_id', $batch_id);
    $stmt->bindValue(':quantity', $quantity);
    $stmt->bindValue(':item', $item);
    $stmt->bindValue(':unit', $unit);
    $stmt->bindValue(':receiver', $receiver);


    if ($stmt->execute()) {
        $hist = $pdo->prepare("INSERT INTO history (user, action, ref_id, product, quantity, unit) VALUES (:user, 'Created Batch', :ref, :prod, :quan, :unit)");
        $hist->execute([':user' => $_SESSION['user_name'] ?? '', ':ref' => $batch_id, ':prod' => $item, ':quan' => $quantity, ':unit' => $unit]);
        header("Location: ../production.php");
        exit;
    } else {echo "Cannot add into production.";}

    echo "Invalid Data";
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['status_id'])) {
    require_once "session.php";
    requireRole(['admin', 'staff']);
    $id = $_GET['id'];
    $batch = $_GET['batch'];
    $date = $_GET['date'];
    $item = $_GET['item'];
    $quantity = $_GET['quantity'];
    $unit = $_GET['unit'];
    $status = $_GET['status_id'];
# Auto insert if completed instead. 
# prod_id	product	quantity	unit	status	description	created_at	updated_at	
    if ($status == 'Completed') {
        $stmt_inventory = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, unit) VALUES (:prod_id, :product, :quantity, :unit)");
        $stmt_inventory->bindValue(':prod_id', $id);
        $stmt_inventory->bindValue(':product', $item);
        $stmt_inventory->bindValue(':quantity', $quantity);
        $stmt_inventory->bindValue(':unit', $unit);
        
        $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
        $stmt_id = $pdo->prepare(
            "SELECT prod_id FROM inventory WHERE prod_id = :id"
        );
        $stmt_id->bindValue(':id', $id_num);
        $stmt_id->execute();
        if ($stmt_id->fetchColumn()) {
            // Retry with new ID
            $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
            $stmt = null;
        }
        if ($stmt_inventory->execute()) {
            echo "Added in inventory successfully";
        }
    }

    $stmt_status = $pdo->prepare("UPDATE production SET status = :status, updated_at = NOW() WHERE batch_id = :batch_id");
    $stmt_status->bindValue(':batch_id', $_GET['batch']);
    $stmt_status->bindValue(':status', $status);
    $stmt_status->execute();

    if ($status == 'Completed') {
        $hist = $pdo->prepare("INSERT INTO history (user, action, ref_id, product, quantity, unit) VALUES (:user, 'Completed Batch', :ref, :prod, :quan, :unit)");
        $hist->execute([':user' => $_SESSION['user_name'] ?? '', ':ref' => $_GET['batch'], ':prod' => $item, ':quan' => $quantity, ':unit' => $unit]);
    }

    header("Location: ../production.php");
}
?>