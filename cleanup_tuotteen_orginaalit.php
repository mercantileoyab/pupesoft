#!/usr/bin/php
<?php
/**
 * cleanup_tuotteen_orginaalit.php
 *
 * Removes duplicate rows from tuotteen_orginaalit without self-JOINs
 * and without buffering the 17 M-row result set. Prints how many
 * rows were deleted when finished.
 *
 * Rules
 * -----
 * 1. If a *clean* version of orig_tuoteno (special chars stripped)
 *    exists for the same tuoteno + aineisto + merkki, every still-dirty
 *    variant is deleted.
 * 2. Among clean duplicates with identical tuoteno + aineisto + merkki
 *    keep the newest luontiaika; delete older ones.
 * 3. Pure “special-char” duplicates survive if no clean twin exists.
 * 4. Rows with identical luontiaika survive.
 *
 * Usage
 * -----
 *     php cleanup_tuotteen_orginaalit.php <yhtio>
 */

if (php_sapi_name() !== 'cli') {
    die("Run from command line.\n");
}
if (empty($argv[1])) {
    die("Usage: php {$argv[0]} <yhtio>\n");
}

date_default_timezone_set('Europe/Helsinki');

require_once 'inc/connect.inc';    // opens $GLOBALS['masterlink']
require_once 'inc/functions.inc';  // Pupesoft helpers

/* ------------------------------------------------------- company */
$companyRow = hae_yhtion_parametrit(pupesoft_cleanstring($argv[1]));
if (!$companyRow) {
    die("Unknown yhtio {$argv[1]}\n");
}
$company = $companyRow['yhtio'];
cron_log();                        // normal Pupesoft job log entry

/* ------------------------------------------------------ helpers */
function clean_code($s) {
    return strtoupper(str_replace(
        array('/', '_', '.', ' ', '-', '(', ')'), '', $s));
}

function handle_group(array $rows, array &$delIds) {

    if (!$rows) return;

    $clean = $dirty = array();
    foreach ($rows as $r) {
        if ($r['orig_tuoteno'] === clean_code($r['orig_tuoteno'])) {
            $clean[] = $r;
        } else {
            $dirty[] = $r;
        }
    }

    /* rule 1 */
    if ($clean) {
        foreach ($dirty as $r) $delIds[] = $r['tunnus'];
    }

    /* rules 2 & 4 */
    if (count($clean) > 1) {
        usort($clean, function ($a, $b) {
            return strcmp($b['luontiaika'], $a['luontiaika']); // newest first
        });
        $newestTime = $clean[0]['luontiaika'];
        foreach (array_slice($clean, 1) as $r) {
            if ($r['luontiaika'] < $newestTime) {              // identical ts stays
                $delIds[] = $r['tunnus'];
            }
        }
    }
}

/* -------------------------------------------------- main SELECT */
$sql = "
SELECT
        tunnus,
        tuoteno,
        orig_tuoteno,
        aineisto,
        merkki, 
        luontiaika
FROM    tuotteen_orginaalit
WHERE   yhtio = '$company'
ORDER BY
        tuoteno,
        UPPER(aineisto),
        UPPER(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(orig_tuoteno, '/',  ''), '_', ''),
                                '.', ''),
                            ' ', ''),
                        '-', ''),
                    '(', ''),
                ')', '')
        ),
        orig_tuoteno,
        luontiaika
";

$link = $GLOBALS['masterlink'];                 // Pupesoft connection
$res  = mysql_unbuffered_query($sql, $link);
if (!$res) {
    die("SELECT failed: " . mysql_error($link) . "\n");
}

/* -------------------------------------------------- stream rows */
$groupKey  = null;
$rows      = array();
$deleteIds = array();

/* build unique key per duplicate set */
function build_key($row) {
    return $row['tuoteno'] . '|' .
           $row['merkki'] . '|' .
           strtoupper($row['aineisto']) . '|' .
           clean_code($row['orig_tuoteno']);
}

while ($row = mysql_fetch_assoc($res)) {
    $key = build_key($row);

    if ($groupKey !== null && $key !== $groupKey) {
        handle_group($rows, $deleteIds);
        $rows = array();
    }

    $rows[]   = $row;
    $groupKey = $key;
}
/* final group */
handle_group($rows, $deleteIds);

/* -------------------------------------------------- perform deletes */
$totalDeleted = 0;
$chunk        = 1000;

for ($i = 0, $n = count($deleteIds); $i < $n; $i += $chunk) {
    $ids = array_slice($deleteIds, $i, $chunk);
    $delSql = "DELETE FROM tuotteen_orginaalit
               WHERE tunnus IN (" . implode(',', $ids) . ")";
    pupe_query($delSql);
    $totalDeleted += count($ids);
}

/* ---------------------------- result */
echo "Removed rows: $totalDeleted\n";
?>
