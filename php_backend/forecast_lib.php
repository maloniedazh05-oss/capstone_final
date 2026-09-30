<?php
// Monthly SES forecaster (pure PHP, no extensions).
// Uses up to 36 months of Completed totals for one product (complete months
// plus the current month-to-date when it already holds sales, pro-rated to
// a full-month equivalent). Predicts next full month's demand. Shared by
// forecasting.php, reports.php and report_pdf.php so they can never drift.
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
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'months' => 0, 'complete' => 0, 'mtd' => null, 'nextMonth' => '', 'series' => []];
    }
    // Continuous calendar, oldest -> newest, gaps = 0 demand.
    $keys = array_keys($qtyMap);
    $months = [];
    $k = $keys[0];
    $lastK = end($keys);
    while ($k <= $lastK) {
        $months[] = ['key' => $k, 'qty' => $qtyMap[$k] ?? 0.0, 'partial' => false];
        $k = date('Y-m', strtotime($k . '-01 +1 month'));
    }

    // Month-to-date: the current partial month joins the fit ONLY when it
    // already holds sales, pro-rated to a full-month equivalent (pacing:
    // MTD x days-in-month / days-elapsed). Empty months are skipped so
    // early-month zeros can't crater the level.
    $thisMonth = date('Y-m');
    $mtdRaw = round((float)($qtyMap[$thisMonth] ?? 0), 2);
    $mtd = null;
    if ($mtdRaw > 0) {
        $elapsed = max(1, (int)date('j'));
        $dim = (int)date('t');
        $mtdQty = round($mtdRaw * $dim / $elapsed, 2);
        // Avoid a duplicate key when the calendar already ends this month.
        if (!empty($months) && end($months)['key'] === $thisMonth) {
            array_pop($months);
        }
        $months[] = ['key' => $thisMonth, 'qty' => $mtdQty, 'partial' => true];
        $mtd = ['key' => $thisMonth, 'raw' => $mtdRaw, 'prorated' => $mtdQty];
    } elseif (!empty($months) && end($months)['key'] === $thisMonth) {
        // Empty current month: drop it, fit on complete months only.
        array_pop($months);
    }
    // Cap at the most recent 36 points so old regimes don't drag the level.
    if (count($months) > 36) {
        $months = array_slice($months, -36);
    }
    // Gate: at least 12 points (complete months + MTD when present).
    $completeCount = count($months) - ($mtd !== null ? 1 : 0);
    if (count($months) < 12) {
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'months' => count($months), 'complete' => $completeCount, 'mtd' => $mtd, 'nextMonth' => '', 'series' => []];
    }

    $data = array_column($months, 'qty');
    $alpha = tuneAlpha($data);
    list($levels, ) = fitSES($data, $alpha);
    $forecastQty = round(end($levels), 2);

    // Target is always the next full month (the current partial month can
    // never be the answer). E.g. any day in September -> October.
    $nextMonth = date('Y-m', strtotime('first day of next month'));

    $average = round(array_sum($data) / count($data), 1);
    return [
        'forecast' => [$forecastQty],
        'method' => 'SES (monthly)',
        'note' => '',
        'average' => $average,
        'thin' => false,
        'alpha' => $alpha,
        'months' => count($months),
        'complete' => $completeCount,
        'mtd' => $mtd,
        'nextMonth' => $nextMonth,
        'series' => $months,
    ];
}
