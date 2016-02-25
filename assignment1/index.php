<?php

$rustart = getrusage();

date_default_timezone_set('Europe/Amsterdam');

// Load database connection, helpers, etc.
require_once(__DIR__ . '/errors.php');
require_once(__DIR__ . '/include.php');
require_once(__DIR__ . '/classes/CodeAndSqlLTVReport.php');
require_once(__DIR__ . '/classes/SqlLTVReport.php');

function rutime($ru, $rus, $index)
{
    return ($ru["ru_$index.tv_sec"] * 1000 + intval($ru["ru_$index.tv_usec"] / 1000)) - ($rus["ru_$index.tv_sec"] * 1000 + intval($rus["ru_$index.tv_usec"] / 1000));
}

// Vars
$period = 12;
$commission = 0.1;
$reportClass = 'CodeAndSqlLTVReport';

$periods = [3, 12, 18];
$reporters = ['CodeAndSqlLTVReport'];

if (isset($_REQUEST["period"])) {
    $period = filter_input(INPUT_GET, "period", FILTER_SANITIZE_NUMBER_INT);
}

if (isset($_REQUEST["commission"])) {
    $commission = filter_input(INPUT_GET, "commission", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

if (isset($_REQUEST["report"])) {
    $_reportClass = filter_input(INPUT_GET, "report");
    if (in_array($_reportClass, $reporters)) {
        $reportClass = $_reportClass;
    }
}

$report = new $reportClass();
$report->setCommission($commission);
$report->setPeriod($period);

$results = $report->generate();

?>
<!doctype html>
<html>
<head>
    <title>Assignment 1: Create a Report (SQL)</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <style type="text/css">

        form > div {
            clear: both;
            display: block;
            padding: 5px;
        }

        .report-table {
            width: 100%;
            border: 1px solid #000000;
        }

        .report-table td,
        .report-table th {
            text-align: left;
            border: 1px solid #000000;
            padding: 5px;
        }

        .report-table .right {
            text-align: right;
        }
    </style>
</head>
<body>
<h1>Settings</h1>

<form method="get">
    <div>
        Period: <select name="period">
            <?php
            echo implode('', array_map(function ($p) use ($period) {
                return sprintf('<option value="%d" %s>%d months</option>', $p, ($p == $period ? 'selected' : ''), $p);
            }, $periods));
            ?></select>
    </div>
    <div>
        Reporter: <select name="report">
            <?php
            echo implode('', array_map(function ($p) use ($reportClass) {
                return sprintf('<option value="%s" %s>%s</option>', $p, ($p == $reportClass ? 'selected' : ''), $p);
            }, $reporters));
            ?></select>
    </div>
    <div>
        Commission (percentage fraction): <input type="text" name="commission" value="<?= $commission ?>">
    </div>
    <input type="submit" value="Generate report">
</form>
<h1>Report:</h1>
<table class="report-table">
    <thead>
    <tr>
        <th>Year-Month</th>
        <th>Bookers</th>
        <th># of bookings (avg)</th>
        <th>Turnover (avg)</th>
        <th>LTV</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $index => $row): ?>
        <tr>
            <td><?php echo $row->getFormattedDate(); ?></td>
            <td><?php echo $row->getBookers(); ?></td>
            <td><?php echo number_format($row->getAverageBookings(), 2); ?></td>
            <td><?php echo number_format($row->getAverageTurnover(), 2); ?></td>
            <td><?php echo number_format($row->getLTV(), 2); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr>
        <td colspan="4" class="right"><strong>Total rows:</strong></td>
        <td><?= count($results) ?></td>
    </tr>
    <tr>
        <td colspan="5">
            <?php
            $ru = getrusage();
            echo "<p>Computation time: " . rutime($ru, $rustart, "utime") . " ms</p>";
            echo "<p>System calls: " . rutime($ru, $rustart, "stime") . " ms</p>";
            ?>
        </td>
    </tr>
    </tfoot>
</table>
</body>
</html>