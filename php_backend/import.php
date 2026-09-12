<?php
require_once "session.php";
require_once "db.php";

requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] != "POST" || !isset($_FILES['historyFile'])) {
    header("Location: ../sales.php?import_error=" . urlencode("No file uploaded."));
    exit;
}

if ($_FILES['historyFile']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../sales.php?import_error=" . urlencode("Upload failed. Try again."));
    exit;
}

$raw = file_get_contents($_FILES['historyFile']['tmp_name']);
$values = json_decode($raw, true);

if (!is_array($values) || empty($values)) {
    header("Location: ../sales.php?import_error=" . urlencode("Invalid file! Must be a JSON array: [23,4,2] or [{\"prod_id\":\"PRD002\",...}]."));
    exit;
}

// Format detect: first element is an object => record mode, else flat-number mode
$first = reset($values);
if (is_array($first)) {
    importRecords($pdo, $values);
} else {
    importFlat($pdo, $values);
}
exit;

// Flat numbers: [23,4,0,26] — index 0 = today, going back one day per value.
// Every value (including 0) becomes one Completed row so Holt-Winters sees idle days.
function importFlat($pdo, $values) {
    if (count($values) > 1096) {
        header("Location: ../sales.php?import_error=" . urlencode("Too many values! Max 1096 (today back to -1095 days). Got " . count($values) . "."));
        exit;
    }

    foreach ($values as $i => $v) {
        if (!is_numeric($v) || (int)$v < 0 || (int)$v != $v + 0) {
            header("Location: ../sales.php?import_error=" . urlencode("Invalid value at index $i! Must be whole numbers 0 or higher."));
            exit;
        }
    }

    $today = date('Y-m-d');
    $count = count($values);
    $oldest = date('Y-m-d', strtotime($today . ' -' . ($count - 1) . ' days'));

    guardRange($pdo, $oldest, $today);

    try {
        $pdo->beginTransaction();

        $stmt_id = $pdo->prepare("SELECT prod_id FROM inventory WHERE prod_id = :id");
        $stmt_ins = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, unit, status, description, created_at, updated_at) VALUES (:id, :prod, :quan, :unit, :status, :desc, :created, :updated)");

        $inserted = 0;
        foreach ($values as $i => $v) {
            $day = date('Y-m-d', strtotime($today . ' -' . $i . ' days'));
            $ts = $day . ' 12:00:00';

            do {
                $id_num = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
                $stmt_id->execute([':id' => $id_num]);
            } while ($stmt_id->fetchColumn());

            $stmt_ins->execute([
                ':id' => $id_num,
                ':prod' => 'Vermicast',
                ':quan' => (int)$v,
                ':unit' => 'Sack',
                ':status' => 'Completed',
                ':desc' => 'Historical import',
                ':created' => $ts,
                ':updated' => $ts
            ]);
            $inserted++;
        }

        $pdo->commit();
        header("Location: ../sales.php?import_success=" . urlencode("Imported $inserted day(s): $oldest to $today."));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../sales.php?import_error=" . urlencode("Import failed: " . $e->getMessage()));
        exit;
    }
}

// Records: [{"prod_id":"PRD002","product":"Vermicast","quantity":34,"unit":"Sack",
// "reference_id":"SALE-2025-001","notes":"...","created_at":"2023-09-06 16:44:37"}]
// Each record is queried in inventory by prod_id then marked Completed:
// missing => INSERT, found but not Completed => UPDATE to Completed,
// already Completed => INSERT new row (keeps daily history, no collapsing).
function importRecords($pdo, $values) {
    if (count($values) > 5000) {
        header("Location: ../sales.php?import_error=" . urlencode("Too many records! Max 5000. Got " . count($values) . "."));
        exit;
    }

    // Validate everything first so a bad row never half-imports
    $rows = [];
    $min_date = null;
    $max_date = null;
    foreach ($values as $i => $r) {
        if (!is_array($r)) {
            header("Location: ../sales.php?import_error=" . urlencode("Invalid record at index $i! Must be an object with prod_id, quantity, created_at."));
            exit;
        }
        $prod_id = trim($r['prod_id'] ?? '');
        $qty = $r['quantity'] ?? null;
        $ts = strtotime($r['created_at'] ?? '');
        if ($prod_id == '' || strlen($prod_id) > 15) {
            header("Location: ../sales.php?import_error=" . urlencode("Invalid prod_id at index $i! Required, max 15 chars."));
            exit;
        }
        if (!is_numeric($qty) || (int)$qty < 0 || (int)$qty != $qty + 0) {
            header("Location: ../sales.php?import_error=" . urlencode("Invalid quantity at index $i! Must be a whole number 0 or higher."));
            exit;
        }
        if ($ts === false) {
            header("Location: ../sales.php?import_error=" . urlencode("Invalid created_at at index $i! Use YYYY-MM-DD HH:MM:SS."));
            exit;
        }
        $date = date('Y-m-d H:i:s', $ts);
        $rows[] = [
            'prod_id' => $prod_id,
            'product' => trim($r['product'] ?? '') ?: 'Vermicast',
            'quantity' => (int)$qty,
            'unit' => trim($r['unit'] ?? '') ?: 'Sack',
            'desc' => trim($r['notes'] ?? '') ?: ('Historical import ' . trim($r['reference_id'] ?? '')),
            'ts' => $date,
            'day' => date('Y-m-d', $ts)
        ];
        if ($min_date === null || $date < $min_date) $min_date = $date;
        if ($max_date === null || $date > $max_date) $max_date = $date;
    }

    guardRange($pdo, date('Y-m-d', strtotime($min_date)), date('Y-m-d', strtotime($max_date)));

    try {
        $pdo->beginTransaction();

        $stmt_find = $pdo->prepare("SELECT prod_id, status FROM inventory WHERE prod_id = :id");
        $stmt_upd = $pdo->prepare("UPDATE inventory SET quantity = :quan, unit = :unit, status = 'Completed', description = :desc, updated_at = :updated WHERE prod_id = :id");
        $stmt_ins = $pdo->prepare("INSERT INTO inventory (prod_id, product, quantity, unit, status, description, created_at, updated_at) VALUES (:id, :prod, :quan, :unit, 'Completed', :desc, :created, :updated)");

        $inserted = 0;
        $marked = 0;
        foreach ($rows as $r) {
            $stmt_find->execute([':id' => $r['prod_id']]);
            $found = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if (!$found) {
                $stmt_ins->execute([
                    ':id' => $r['prod_id'],
                    ':prod' => $r['product'],
                    ':quan' => $r['quantity'],
                    ':unit' => $r['unit'],
                    ':desc' => $r['desc'],
                    ':created' => $r['ts'],
                    ':updated' => $r['ts']
                ]);
                $inserted++;
            } elseif ($found['status'] !== 'Completed') {
                $stmt_upd->execute([
                    ':quan' => $r['quantity'],
                    ':unit' => $r['unit'],
                    ':desc' => $r['desc'],
                    ':updated' => $r['ts'],
                    ':id' => $r['prod_id']
                ]);
                $marked++;
                
            } else {
                // Already Completed: new row so daily history is not collapsed
                $new_id = $r['prod_id'] . '-' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 7);
                $stmt_ins->execute([
                    ':id' => substr($new_id, 0, 15),
                    ':prod' => $r['product'],
                    ':quan' => $r['quantity'],
                    ':unit' => $r['unit'],
                    ':desc' => $r['desc'],
                    ':created' => $r['ts'],
                    ':updated' => $r['ts']
                ]);
                $inserted++;
            }
        }


        $pdo->commit();
        header("Location: ../sales.php?import_success=" . urlencode("Imported " . count($rows) . " record(s): $inserted new, $marked marked Completed."));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../sales.php?import_error=" . urlencode("Import failed: " . $e->getMessage()));
        exit;
    }
}

//Re-import guard: abort if Completed rows already exist in range, unless overwrite ticked.
// Overwrite removes only previously imported rows in range, keeps manual records.
function guardRange($pdo, $oldest, $today) {
    $confirm = $_POST['confirm_overwrite'] ?? '';
    if ($confirm !== 'yes') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE status = :status AND DATE(updated_at) BETWEEN :oldest AND :today");
        $stmt->execute([':status' => 'Completed', ':oldest' => $oldest, ':today' => $today]);
        $existing = (int)$stmt->fetchColumn();
        if ($existing > 0) {
            header("Location: ../sales.php?import_error=" . urlencode("Found $existing Completed record(s) from $oldest to $today. Re-importing would double-count. Tick overwrite to replace Historical import rows in this range."));
            exit;
        }
    } else {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE status = :status AND description LIKE :desc AND DATE(updated_at) BETWEEN :oldest AND :today");
        $stmt->execute([':status' => 'Completed', ':desc' => '%Historical import%', ':oldest' => $oldest, ':today' => $today]);
    }
}
?>
