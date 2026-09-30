<?php
require_once "session.php";
requireRole(['admin', 'manager']);

// Update/Edit Total Stock: deduct the entered amount (Sacks) from the
// single-row `total` ledger, floored at 0. Inventory rows are fixed
// production-tracking records and are never touched here.
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['quantity'])) {
    $unit = $_POST['unit'];
    $entered = round((float)($_POST['quantity'] ?? 0), 2);
    if($unit == 'KG') $entered = round($entered / 50, 2);
    $receiver = trim($_POST['description'] ?? '');
    if ($entered <= 0) {
        header("Location: ../inventory.php?error=1");
        exit;
    }

    $stmtTotal = $pdo->prepare("SELECT total_stock FROM total LIMIT 1");
    $stmtTotal->execute();
    $current = (float)($stmtTotal->fetchColumn() ?? 0);

    $deduct = round(min($entered, $current), 2);
    if ($deduct <= 0) {
        header("Location: ../inventory.php?error=1");
        exit;
    }

    $updT = $pdo->prepare("UPDATE total SET total_stock = :total");
    $updT->execute([':total' => round($current - $deduct, 2)]);

    $hist = $pdo->prepare("INSERT INTO history (user, action, ref_id, product, quantity, unit, receiver) VALUES (:user, 'Stock Deducted', 'TOTAL', :prod, :quan, :unit, :receiver)");
    $hist->execute([
        ':user' => $_SESSION['user_name'] ?? '',
        ':prod' => 'Vermicast',
        ':quan' => $deduct,
        ':unit' => 'Sacks',
        ':receiver' => $receiver !== '' ? substr($receiver, 0, 50) : null
    ]);

    header("Location: ../inventory.php?success=1");
    exit;
}

header("Location: ../inventory.php?error=1");
exit;
?>
