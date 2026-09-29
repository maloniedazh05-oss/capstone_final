<?php
// Monthly SES forecaster (pure PHP, no extensions).
// Uses last 12-36 COMPLETE months of Completed totals for one product:
// each month predicts next month's demand. Shared by forecasting.php,
// reports.php and report_pdf.php so they can never drift apart.
//
// Future methods slot into $FORECAST_METHODS (e.g. 'holt', 'hw') without
// touching any caller: runForecast($pdo, $product, $selMethod).

// One SES pass: level init = first observation. Returns [levels, sse].
function fitSES($data, $alpha) {
    $levels = [];
    $level = (float)$data[0];
    $levels[] = $level;
    $sse = 0.0;
    for ($i = 1, $n = count($data); $i < $n; $i++) {
        $err = (float)$data[$i] - $level;
        $sse += $err * $err;
        $level = $alpha * (float)$data[$i] + (1 - $alpha) * $level;
        $levels[] = $level;
    }
    return [$levels, $sse];
}

// Grid-search alpha 0.05-0.95 step 0.05, keep lowest in-sample SSE.
function tuneAlpha($data) {
    $bestAlpha = 0.3;
    $bestSSE = null;
    for ($a = 5; $a <= 95; $a += 5) {
        $alpha = $a / 100;
        list(, $sse) = fitSES($data, $alpha);
        if ($bestSSE === null || $sse < $bestSSE) {
            $bestSSE = $sse;
            $bestAlpha = $alpha;
        }
    }
    return $bestAlpha;
}

function runForecast($pdo, $product, $selMethod = null) {
    $methods = ['ses' => true]; // registry for future methods
    $method = strtolower(trim($selMethod ?? 'ses'));
    if (!isset($methods[$method])) {
        $method = 'ses';
    }

    // Monthly completed totals, oldest -> newest.
    $stmt = $pdo->prepare("SELECT YEAR(updated_at) AS y, MONTH(updated_at) AS m, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND product = :prod GROUP BY YEAR(updated_at), MONTH(updated_at) ORDER BY y, m");
    $stmt->execute([':prod' => $product]);
    $qtyMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $qtyMap[sprintf('%04d-%02d', $row['y'], $row['m'])] = (float)$row['total_qty'];
    }
    if (empty($qtyMap)) {
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'months' => 0, 'nextMonth' => '', 'series' => []];
    }
    // Continuous calendar, oldest -> newest, gaps = 0 demand.
    $keys = array_keys($qtyMap);
    $months = [];
    $k = $keys[0];
    $lastK = end($keys);
    while ($k <= $lastK) {
        $months[] = ['key' => $k, 'qty' => $qtyMap[$k] ?? 0.0];
        $k = date('Y-m', strtotime($k . '-01 +1 month'));
    }

    // Drop the partial current month: never fit on an incomplete bucket.
    $thisMonth = date('Y-m');
    if (!empty($months) && end($months)['key'] === $thisMonth) {
        array_pop($months);
    }
    // Cap at the most recent 36 so old regimes don't drag the level.
    if (count($months) > 36) {
        $months = array_slice($months, -36);
    }
    // Gate: SES needs at least 12 complete months.
    if (count($months) < 12) {
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'months' => count($months), 'nextMonth' => '', 'series' => []];
    }

    $data = array_column($months, 'qty');
    $alpha = tuneAlpha($data);
    list($levels, ) = fitSES($data, $alpha);
    $forecastQty = round(end($levels), 2);

    // Next calendar month after the last fitted month.
    $lastKey = end($months)['key'];
    $nextMonth = date('Y-m', strtotime($lastKey . '-01 +1 month'));

    $average = round(array_sum($data) / count($data), 1);
    return [
        'forecast' => [$forecastQty],
        'method' => 'SES (monthly)',
        'note' => '',
        'average' => $average,
        'thin' => false,
        'alpha' => $alpha,
        'months' => count($months),
        'nextMonth' => $nextMonth,
        'series' => $months,
    ];
}
