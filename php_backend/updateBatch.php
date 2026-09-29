<?php 
require_once "session.php";
requireRole(['admin', 'staff']);

if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['status'])) {
    $status = $_POST['status'] ?? '';
    $id = $_POST['id'] ?? '';
    $quantity = $_POST['quantityIn'];

    // production
    $stmt = $pdo->prepare("UPDATE production SET status = :status, updated_at = NOW() WHERE production_id = :id");
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':id', $id);    

    // to inventory. No need to set status for going in inventory must be Recent.
    // ID RULE: the inventory record keeps the production batch_id, so the
    // Batch ID (Production History) and Item ID (Inventory) always match.

    if($status == "Completed") {
    $stmt_fetch = $pdo->prepare("SELECT batch_id, item, quantity, unit, status, receiver FROM production WHERE production_id = :id");
    $stmt_fetch->execute(["id"=>$id]);
    $row_fetch = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    // Only append once: skip if already Completed (re-submit would double-count).
    if ($row_fetch && $row_fetch['status'] != 'Completed') {
        // Same ID end to end: inventory prod_id = production batch_id.
        $inv_id = $row_fetch['batch_id'];
        $dup = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
        $dup->bindValue(':id', $inv_id);
        $dup->execute();
        if ($dup->fetchColumn()) {
            // Should not happen (guarded above), but never fatal on a PK clash:
            // keep the batch traceable with a suffixed record id.
            $inv_id = substr($inv_id . substr(str_shuffle("0123456789"), 0, 3), 0, 15);
        }
        // Stamp the produced amount on the batch so Production History shows it
        // (batches are created with quantity 0 until completed).
        $updQ = $pdo->prepare("UPDATE production SET quantity = :q WHERE production_id = :id");
        $updQ->bindValue(':q', $quantity);
        $updQ->bindValue(':id', $id);
        $updQ->execute();

        $stmt2 = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, unit, description, stock_in, created_at) VALUES (:prod_id, :product, :quantity, :unit, :description, :stock_in, NOW())");
        $stmt2->bindValue(":prod_id", $inv_id);
        $stmt2->bindValue(":product", $row_fetch['item']);
        $stmt2->bindValue(":quantity", $quantity); // Stock In
        $stmt2->bindValue(":unit", $row_fetch['unit']);
        $stmt2->bindValue(":description", 'Batch: ' . $row_fetch['batch_id']);
        $stmt2->bindValue(':stock_in', $quantity);
        $stmt2->execute();

        $hist = $pdo->prepare("INSERT INTO history (user, action, ref_id, product, quantity, unit) VALUES (:user, 'Completed Batch', :ref, :prod, :quan, :unit)");
        $hist->execute([':user' => $_SESSION['user_name'] ?? '', ':ref' => $row_fetch['batch_id'], ':prod' => $row_fetch['item'], ':quan' => $quantity, ':unit' => $row_fetch['unit']]);

        // Optional ledger table: skip silently when it does not exist.
        // Live stock is always SUM(quantity) from inventory.
        try {
            $stmtTotal = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
            $stmtTotal->execute();
            $row = $stmtTotal->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $newTotal = round((float)$row['total_stock'] + (float)$quantity, 2);
                $updateTotal = $pdo->prepare("UPDATE total SET total_stock = :total");
                $updateTotal->execute([':total' => $newTotal]);
            } else {
                $insertTotal = $pdo->prepare("INSERT INTO total (total_stock) VALUES (:total)");
                $insertTotal->execute([':total' => round((float)$quantity, 2)]);
            }
        } catch (Exception $e) {
            // no total table — nothing to update
        }
    }
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