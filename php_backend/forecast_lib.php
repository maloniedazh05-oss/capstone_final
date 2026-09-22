<?php
// Shared hybrid forecaster (pure PHP, no extensions).
// Arms: Linear Regression (steady line) vs Holt's linear trend vs
// weekly-seasonal Holt-Winters (additive, period 7). Eligibility by
// history length (<15 days: refuse; 15-55: Regression+Holt; 56+ with
// range 56+: all three). Lowest in-sample MSE wins (ties go simpler:
// Regression, Holt, HW). Used by forecasting.php and reports.php so
// both pages can never drift apart.

// Closed-form least squares: slope = Cov(t,y) / Var(t).
function fitReg($data) {
    $n = count($data);
    $sumT = 0;
    $sumY = 0;
    foreach ($data as $i => $y) {
        $sumT += $i;
        $sumY += $y;
    }
    $meanT = $sumT / $n;
    $meanY = $sumY / $n;
    $num = 0;
    $den = 0;
    foreach ($data as $i => $y) {
        $num += ($i - $meanT) * ($y - $meanY);
        $den += ($i - $meanT) * ($i - $meanT);
    }
    $slope = $den != 0 ? $num / $den : 0;
    $intercept = $meanY - $slope * $meanT;
    $sse = 0;
    foreach ($data as $i => $y) {
        $err = $y - ($intercept + $slope * $i);
        $sse += $err * $err;
    }
    return ['slope' => $slope, 'intercept' => $intercept, 'sse' => $sse];
}

function fitHolt($data, $alpha, $beta) {
    $level = $data[0];
    $trend = $data[1] - $data[0];
    $sse = 0;
    $n = count($data);
    for ($i = 1; $i < $n; $i++) {
        $err = $data[$i] - ($level + $trend);
        $sse += $err * $err;
        $prev = $level;
        $level = $alpha * $data[$i] + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $prev) + (1 - $beta) * $trend;
    }
    return ['level' => $level, 'trend' => $trend, 'sse' => $sse];
}

// Weekly-seasonal Holt-Winters (additive, period 7). Needs 14+ points:
// the first two weeks initialize level, trend and the 7 seasonal indices.
function fitHW($data, $alpha, $beta, $gamma) {
    $n = count($data);
    $week1 = array_sum(array_slice($data, 0, 7)) / 7;
    $week2 = array_sum(array_slice($data, 7, 7)) / 7;
    $level = $week1;
    $trend = ($week2 - $week1) / 7;
    $season = [];
    for ($i = 0; $i < 7; $i++) {
        $season[$i] = $data[$i] - $week1;
    }
    $sse = 0;
    for ($i = 0; $i < $n; $i++) {
        $sIdx = $i % 7;
        if ($i >= 7) {
            $err = $data[$i] - ($level + $trend + $season[$sIdx]);
            $sse += $err * $err;
        }
        $prev = $level;
        $level = $alpha * ($data[$i] - $season[$sIdx]) + (1 - $alpha) * ($level + $trend);
        $trend = $beta * ($level - $prev) + (1 - $beta) * $trend;
        $season[$sIdx] = $gamma * ($data[$i] - $level) + (1 - $gamma) * $season[$sIdx];
    }
    return ['level' => $level, 'trend' => $trend, 'season' => $season, 'sse' => $sse, 'lastIdx' => ($n - 1) % 7];
}

// Run one forecast. $selMethod: 'auto' or a forced 'reg'/'holt'/'hw'.
// Returns ['forecast' => [...], 'method' => '...', 'note' => '...', 'thin' => bool].
// 'thin' means history is too young for any arm: no forecast, explain instead.
function runForecast($pdo, $product, $rangeDays, $steps, $selMethod) {
    $alpha = 0.3;
    $beta = 0.3;
    $gamma = 0.3;
    $today = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime($today . ' -' . ($rangeDays - 1) . ' days'));

    // Daily completed totals, oldest -> today, missing days = 0.
    $stmt = $pdo->prepare("SELECT DATE(updated_at) AS day, SUM(quantity) AS total_qty FROM inventory WHERE status = 'Completed' AND product = :prod AND updated_at BETWEEN :start AND :end GROUP BY DATE(updated_at)");
    $stmt->execute([':prod' => $product, ':start' => $start_date . ' 00:00:00', ':end' => $today . ' 23:59:59']);
    $qty_map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $qty_map[$row['day']] = (int)$row['total_qty'];
    }
    $data = [];
    $day = $start_date;
    while ($day <= $today) {
        $data[] = $qty_map[$day] ?? 0;
        $day = date('Y-m-d', strtotime($day . ' +1 day'));
    }

    $forecast = [];
    $method = '';
    $note = '';
    $allZero = true;
    foreach ($data as $v) {
        if ($v != 0) {
            $allZero = false;
            break;
        }
    }
    if ($allZero) {
        // Nothing ever sold: flat zeros, nothing to learn from.
        $method = 'Linear Regression';
        $forecast = array_fill(0, $steps, 0.0);
        return ['forecast' => $forecast, 'method' => $method, 'note' => $note, 'thin' => false];
    }
    // History length = span from first sale to today. Gates the arms.
    $firstNz = null;
    foreach ($data as $di => $dv) {
        if ($dv != 0) {
            $firstNz = $di;
            break;
        }
    }
    $histDays = $firstNz === null ? 0 : count($data) - $firstNz;
    $regEligible = $histDays >= 15;
    $holtEligible = $histDays >= 14;
    $hwEligible = $histDays >= 56 && $rangeDays >= 56;
    if (!$regEligible && !$holtEligible && !$hwEligible) {
        // Too young: one spike would tilt any line, so refuse instead.
        return ['forecast' => [], 'method' => '', 'note' => '', 'thin' => true];
    }
    $wantArm = $selMethod === 'auto' ? 'auto' : $selMethod;
    if ($wantArm === 'reg' && !$regEligible) {
        $note = 'Linear Regression needs 15+ days of history - ran Auto instead.';
        $wantArm = 'auto';
    }
    if ($wantArm === 'holt' && !$holtEligible) {
        $note = "Holt's Linear Trend needs 14+ days of history - ran Auto instead.";
        $wantArm = 'auto';
    }
    if ($wantArm === 'hw' && !$hwEligible) {
        $note = 'Seasonal Holt-Winters needs 56+ days of history and range - ran Auto instead.';
        $wantArm = 'auto';
    }
    $bestArm = null;
    $bestFit = null;
    $bestMSE = null;
    if (($wantArm === 'auto' || $wantArm === 'reg') && $regEligible) {
        $reg = fitReg($data);
        $bestArm = 'reg';
        $bestFit = $reg;
        $bestMSE = $reg['sse'] / count($data);
    }
    if (($wantArm === 'auto' || $wantArm === 'holt') && $holtEligible) {
        $holt = fitHolt($data, $alpha, $beta);
        $holtMSE = $holt['sse'] / (count($data) - 1);
        if ($wantArm === 'holt' || $bestArm === null || $holtMSE < $bestMSE) {
            $bestArm = 'holt';
            $bestFit = $holt;
            $bestMSE = $holtMSE;
        }
    }
    if (($wantArm === 'auto' || $wantArm === 'hw') && $hwEligible) {
        $hw = fitHW($data, $alpha, $beta, $gamma);
        $hwMSE = $hw['sse'] / (count($data) - 7);
        if ($wantArm === 'hw' || $bestArm === null || $hwMSE < $bestMSE) {
            $bestArm = 'hw';
            $bestFit = $hw;
            $bestMSE = $hwMSE;
        }
    }
    $src = ($selMethod === 'auto' || $note !== '') ? 'auto-selected' : 'manual';
    $n = count($data);
    if ($bestArm === 'hw') {
        $method = 'Weekly-Seasonal Holt-Winters (' . $src . ')';
        for ($h = 1; $h <= $steps; $h++) {
            $sIdx = ($bestFit['lastIdx'] + $h) % 7;
            $forecast[] = max(0, round($bestFit['level'] + $h * $bestFit['trend'] + $bestFit['season'][$sIdx], 1));
        }
    } elseif ($bestArm === 'holt') {
        $method = "Holt's Linear Trend (" . $src . ')';
        for ($h = 1; $h <= $steps; $h++) {
            $forecast[] = max(0, round($bestFit['level'] + $h * $bestFit['trend'], 1));
        }
    } else {
        $method = $src === 'manual' ? 'Linear Regression (manual)' : 'Linear Regression';
        for ($h = 1; $h <= $steps; $h++) {
            $forecast[] = max(0, round($bestFit['intercept'] + $bestFit['slope'] * ($n - 1 + $h), 1));
        }
    }
    return ['forecast' => $forecast, 'method' => $method, 'note' => $note, 'thin' => false];
}
