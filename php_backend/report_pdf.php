<?php
// Real PDF downloads for the reports branches (FPDF, core Helvetica only).
require_once "session.php";
requireRole(['admin']);
require_once "db.php";
require_once "forecast_lib.php";
require_once "fpdf/fpdf.php";

$branch = $_GET['branch'] ?? '';
if (!in_array($branch, ['production', 'stockout', 'forecast'], true)) {
    exit('Unknown report.');
}

function pdf_table($title, $filters, $headers, $widths, $rows, $filename) {
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 14);
    $pdf->Cell(0, 10, $title, 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 8, $filters, 0, 1);
    $pdf->Ln(2);
    $pdf->SetFillColor(34, 197, 94);
    $pdf->SetTextColor(255, 255, 255);
    foreach ($headers as $i => $h) {
        $pdf->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
    if (empty($rows)) {
        $pdf->Cell(array_sum($widths), 8, 'No records match the filters.', 1, 1);
    } else {
        foreach ($rows as $r) {
            foreach ($r as $i => $c) {
                $pdf->Cell($widths[$i], 7, $c, 1);
            }
            $pdf->Ln();
        }
    }
    $pdf->Output('D', $filename);
}

if ($branch === 'production') {
    $repFrom = $_GET['date-from'] ?? date('Y-m-d', strtotime('today -29 days'));
    $repTo = $_GET['date-to'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repFrom)) {
        $repFrom = date('Y-m-d', strtotime($repTo . ' -29 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $repTo) || $repTo > date('Y-m-d')) {
        $repTo = date('Y-m-d');
    }
    if ($repFrom > $repTo) {
        $repTmp = $repFrom;
        $repFrom = $repTo;
        $repTo = $repTmp;
    }
    $repStatus = $_GET['status'] ?? 'all';
    if (!in_array($repStatus, ['all', 'Recent', 'Ongoing', 'Completed'], true)) {
        $repStatus = 'all';
    }
    $searchRec = trim($_GET['search-rec'] ?? '');
    $recSort = $_GET['rec-sort'] ?? 'newest';
    if (!in_array($recSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
        $recSort = 'newest';
    }
    $recSortMap = ['newest' => 'production_date DESC', 'oldest' => 'production_date ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
    $sql = "SELECT batch_id, production_date, item, quantity, unit, status FROM production WHERE production_date BETWEEN :start AND :end"
        . ($repStatus !== 'all' ? " AND status = :status" : "")
        . ($searchRec !== '' ? " AND (CAST(batch_id AS CHAR) LIKE :search OR CAST(production_id AS CHAR) LIKE :search OR item LIKE :search OR receiver LIKE :search)" : "")
        . " ORDER BY " . $recSortMap[$recSort];
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $repFrom . ' 00:00:00');
    $stmt->bindValue(':end', $repTo . ' 23:59:59');
    if ($repStatus !== 'all') {
        $stmt->bindValue(':status', $repStatus);
    }
    if ($searchRec !== '') {
        $stmt->bindValue(':search', "%" . $searchRec . "%");
    }
    $stmt->execute();
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [$r['batch_id'], date('M d', strtotime($r['production_date'])), $r['item'], $r['quantity'] . ' ' . $r['unit'], $r['status']];
    }
    pdf_table('Production Report', "From $repFrom to $repTo | Status: $repStatus" . ($searchRec !== '' ? " | Search: $searchRec" : ''), ['Batch', 'Date', 'Type', 'Qty.', 'Status'], [45, 40, 70, 45, 45], $rows, "production-report-$repFrom-$repTo.pdf");
} elseif ($branch === 'stockout') {
    $soFrom = $_GET['so-from'] ?? date('Y-m-d', strtotime('today -29 days'));
    $soTo = $_GET['so-to'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $soFrom)) {
        $soFrom = date('Y-m-d', strtotime($soTo . ' -29 days'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $soTo) || $soTo > date('Y-m-d')) {
        $soTo = date('Y-m-d');
    }
    if ($soFrom > $soTo) {
        $soTmp = $soFrom;
        $soFrom = $soTo;
        $soTo = $soTmp;
    }
    if ((strtotime($soTo) - strtotime($soFrom)) / 86400 > 92) {
        $soFrom = date('Y-m-d', strtotime($soTo . ' -92 days'));
    }
    $soProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
    $soProd = $_GET['so-product'] ?? 'all';
    if ($soProd !== 'all' && !in_array($soProd, $soProdOpts, true)) {
        $soProd = 'all';
    }
    $searchSo = trim($_GET['search-so'] ?? '');
    $soRecSort = $_GET['so-sort'] ?? 'newest';
    if (!in_array($soRecSort, ['newest', 'oldest', 'highest', 'lowest'], true)) {
        $soRecSort = 'newest';
    }
    $soRecSortMap = ['newest' => 'updated_at DESC', 'oldest' => 'updated_at ASC', 'highest' => 'quantity DESC', 'lowest' => 'quantity ASC'];
    $sql = "SELECT DATE(updated_at) AS day, product, quantity, description FROM inventory WHERE status = 'Completed' AND DATE(updated_at) BETWEEN :start AND :end"
        . ($soProd !== 'all' ? " AND product = :prod" : "")
        . ($searchSo !== '' ? " AND (CAST(prod_id AS CHAR) LIKE :search OR product LIKE :search OR description LIKE :search OR unit LIKE :search)" : "")
        . " ORDER BY " . $soRecSortMap[$soRecSort];
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $soFrom);
    $stmt->bindValue(':end', $soTo);
    if ($soProd !== 'all') {
        $stmt->bindValue(':prod', $soProd);
    }
    if ($searchSo !== '') {
        $stmt->bindValue(':search', "%" . $searchSo . "%");
    }
    $stmt->execute();
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [date('M d', strtotime($r['day'])), $r['product'], $r['quantity'], $r['description'] ?: 'None'];
    }
    pdf_table('Stock-Out Report', "From $soFrom to $soTo | Product: $soProd" . ($searchSo !== '' ? " | Search: $searchSo" : ''), ['Date', 'Type', 'Qty.', 'Desc/Receiver'], [45, 70, 45, 117], $rows, "stockout-report-$soFrom-$soTo.pdf");
} else {
    $fcProdOpts = $pdo->query("SELECT DISTINCT product FROM inventory ORDER BY product")->fetchAll(PDO::FETCH_COLUMN);
    $fcProd = $_GET['fc-product'] ?? ($fcProdOpts[0] ?? 'Vermicast');
    if (!in_array($fcProd, $fcProdOpts, true)) {
        $fcProd = $fcProdOpts[0] ?? 'Vermicast';
    }
    $fcPeriod = $_GET['fc-period'] ?? '30';
    if (!in_array($fcPeriod, ['7', '14', '30'], true)) {
        $fcPeriod = '30';
    }
    $fcToday = date('Y-m-d');
    $fcTailStmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS q FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at >= :start GROUP BY DATE(updated_at)");
    $fcTailStmt->execute([':prod' => $fcProd, ':start' => date('Y-m-d', strtotime($fcToday . ' -6 days')) . ' 00:00:00']);
    $fcTailMap = [];
    while ($fcTailRow = $fcTailStmt->fetch(PDO::FETCH_ASSOC)) {
        $fcTailMap[$fcTailRow['day']] = (int)$fcTailRow['q'];
    }
    $fcTailDays = [];
    $ctd = date('Y-m-d', strtotime($fcToday . ' -6 days'));
    while ($ctd <= $fcToday) {
        $fcTailDays[] = $ctd;
        $ctd = date('Y-m-d', strtotime($ctd . ' +1 day'));
    }
    $fcRes = runForecast($pdo, $fcProd, 365, (int)$fcPeriod, 'auto');
    $rows = [];
    foreach ($fcTailDays as $cd) {
        $rows[] = [date('M d', strtotime($cd)), (string)($fcTailMap[$cd] ?? 0), '-'];
    }
    if (!$fcRes['thin']) {
        $cd = $fcToday;
        foreach ($fcRes['forecast'] as $cq) {
            $cd = date('Y-m-d', strtotime($cd . ' +1 day'));
            $rows[] = [date('M d', strtotime($cd)), '-', (string)$cq];
        }
    }
    pdf_table('Forecast Report (' . $fcRes['method'] . ')', "Product: $fcProd | Next $fcPeriod days", ['Date', 'Actual', 'Forecast'], [60, 60, 60], $rows, "forecast-report-$fcProd-$fcPeriod" . "d.pdf");
}
