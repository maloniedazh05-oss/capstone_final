<?php
require_once "session.php";

requireRole(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['sales_goal'])) {
    $goal = (int)$_POST['sales_goal'];
    if ($goal < 0 || $goal > 1000000) {
        header("Location: ../index.php?error=1");
        exit;
    }
    $saved = file_put_contents(__DIR__ . '/sales_goal.php', "<?php\n// Target minimum stock (Sacks). Written by setGoal.php - do not edit by hand.\nreturn " . $goal . ";\n");
    if ($saved === false) {
        header("Location: ../index.php?error=1");
        exit;
    }
    header("Location: ../index.php?success=1");
    exit;
}
header("Location: ../index.php?error=1");
