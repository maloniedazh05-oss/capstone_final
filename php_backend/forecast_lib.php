<?php
// Monthly Holt's Double Exponential Smoothing forecaster (pure PHP, no extensions).
// Uses up to 36 months of Completed totals for one product (complete months
// plus the current month-to-date when it already holds sales, pro-rated to
// a full-month equivalent). Predicts next full month's demand: level + trend.
// Shared by forecasting.php, reports.php and report_pdf.php so they can never
// drift. Holt's has no seasonal component, matching intermittent batch sales.
//
// Future methods slot into $FORECAST_METHODS (e.g. 'hw') without
// touching any caller: runForecast($pdo, $product, $selMethod).

// One Holt's pass: level init = first observation, trend init = mean of the
// first up-to-4 one-step differences. Returns [levels, trends, sse].
function fitHolt($data, $alpha, $beta) {
    $n = count($data);
    $level = (float)$data[0];
    $diffs = [];
    for ($i = 1; $i < $n && $i <= 4; $i++) {
        $diffs[] = (float)$data[$i] - (float)$data[$i - 1];
    }
    $trend = $diffs ? array_sum($diffs) / count($diffs) : 0.0;
    $levels = [$level];
    $trends = [$trend];
    $sse = 0.0;
    for ($i = 1; $i < $n; $i++) {
        $obs = (float)$data[$i];
        $err = $obs - ($level + $trend);
        $sse += $err * $err;
        $prevLevel = $level;
        $level = $alpha * $obs + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
        $levels[] = $level;
        $trends[] = $trend;
    }
    return [$levels, $trends, $sse];
}

// Grid-search alpha x beta 0.05-0.95 step 0.05, keep lowest in-sample SSE.
function tuneHolt($data) {
    $bestAlpha = 0.3;
    $bestBeta = 0.1;
    $bestSSE = null;
    for ($a = 5; $a <= 95; $a += 5) {
        for ($b = 5; $b <= 95; $b += 5) {
            list(, , $sse) = fitHolt($data, $a / 100, $b / 100);
            if ($bestSSE === null || $sse < $bestSSE) {
                $bestSSE = $sse;
                $bestAlpha = $a / 100;
                $bestBeta = $b / 100;
            }
        }
    }
    return [$bestAlpha, $bestBeta];
}

function runForecast($pdo, $product, $selMethod = null) {
    $methods = ['holt' => true]; // registry for future methods
    $method = strtolower(trim($selMethod ?? 'holt'));
    if (!isset($methods[$method])) {
        $method = 'holt';
    }

    // Monthly completed totals, oldest -> newest.
    $stmt = $pdo->prepare("SELECT YEAR(updated_at) AS y, MONTH(updated_at) AS m, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND product = :prod GROUP BY YEAR(updated_at), MONTH(updated_at) ORDER BY y, m");
    $stmt->execute([':prod' => $product]);
    $qtyMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $qtyMap[sprintf('%04d-%02d', $row['y'], $row['m'])] = (float)$row['total_qty'];
    }
    if (empty($qtyMap)) {
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'beta' => 0, 'trend' => 0, 'months' => 0, 'complete' => 0, 'mtd' => null, 'nextMonth' => '', 'series' => []];
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
        return ['forecast' => [], 'method' => '', 'note' => '', 'average' => 0, 'thin' => true, 'alpha' => 0, 'beta' => 0, 'trend' => 0, 'months' => count($months), 'complete' => $completeCount, 'mtd' => $mtd, 'nextMonth' => '', 'series' => []];
    }

    $data = array_column($months, 'qty');
    list($alpha, $beta) = tuneHolt($data);
    list($levels, $trends, ) = fitHolt($data, $alpha, $beta);
    $trend = round(end($trends), 2);
    // One step ahead: level + trend. A negative trend on a near-zero level
    // must never produce a negative forecast, so clamp at zero.
    $forecastQty = max(0, round(end($levels) + end($trends), 2));

    // Target is always the next full month (the current partial month can
    // never be the answer). E.g. any day in September -> October.
    $nextMonth = date('Y-m', strtotime('first day of next month'));

    $average = round(array_sum($data) / count($data), 1);
    return [
        'forecast' => [$forecastQty],
        'method' => "Holt's DES (monthly)",
        'note' => '',
        'average' => $average,
        'thin' => false,
        'alpha' => $alpha,
        'beta' => $beta,
        'trend' => $trend,
        'months' => count($months),
        'complete' => $completeCount,
        'mtd' => $mtd,
        'nextMonth' => $nextMonth,
        'series' => $months,
    ];
}
