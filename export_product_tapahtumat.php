<?php
/**
 * export_product_tapahtumat.php
 *
 * Reads a one-column CSV that lists product numbers,
 * finds the latest negative “laskutus” event for each product,
 * and writes the distinct product codes that need resetting
 * to a new CSV file.
 *
 *   php export_product_tapahtumat.php <yhti?>
 */

if (php_sapi_name() !== 'cli') {
  die("This script must be run from the command line.\n");
}

if (!isset($argv[1]) || !$argv[1]) {
  die("Usage: php {$argv[0]} <yhti?>\n");
}

date_default_timezone_set('Europe/Helsinki');

require "inc/connect.inc";
require "inc/functions.inc";

/* ---------- validate company / yhti? ---------- */
$yhtioInput = pupesoft_cleanstring($argv[1]);
$yhtiorow   = hae_yhtion_parametrit($yhtioInput);
if (!$yhtiorow) {
  die("Unknown yhti?: {$argv[1]}\n");
}
$yhtio = $yhtiorow['yhtio'];

cron_log();

/* ---------- file settings ---------- */
$inputCsv   = __DIR__ . '/datain/export_product_tapahtumat/data.csv';
$outputCsv  = __DIR__ . '/datain/export_product_tapahtumat/export_product_codes.csv';
$delimiter  = ';';
$enclosure  = '"';
$escapeChar = '\\';   // only used if PHP version supports it

/* ---------- open input CSV ---------- */
if (!is_readable($inputCsv)) {
  die("Cannot open $inputCsv for reading.\n");
}

$file = new SplFileObject($inputCsv);
$file->setFlags(SplFileObject::READ_CSV);
$file->setCsvControl($delimiter, $enclosure, $escapeChar);

/* ---------- gather products that need reset ---------- */
$exportRows = array();                // holds rows like ['ABC-123']

$exportRows[] = array(
  'tuoteno',
  'laji',
  'kpl',
  'hinta',
  'kplhinta',
  'selite',
  'laatija',
  'laadittu'
);

foreach ($file as $key => $row) {
  // Skip empty or final blank line
  if ($row === [null] || $row === false) {
    continue;
  }

  $tuoteno = pupesoft_cleanstring($row[0]);

  $query = "
    SELECT t.tuoteno,
           t.laji,
           t.kpl,
           t.hinta,
           t.kplhinta,
           t.selite,
           t.laatija,
           t.laadittu
      FROM tapahtuma AS t
      JOIN (
              SELECT tuoteno, MAX(laadittu) AS laadittu
                FROM tapahtuma
               WHERE yhtio   = '$yhtio'
                 AND tuoteno = '$tuoteno'
                 AND laji    = 'laskutus'
                 AND kpl     < 0
            GROUP BY tuoteno
           ) AS m
        ON m.tuoteno  = t.tuoteno
       AND m.laadittu = t.laadittu
     WHERE t.yhtio   = '$yhtio'
       AND t.tuoteno = '$tuoteno'
       AND t.laji    = 'laskutus'
       AND t.kpl     < 0
  ";

  $res = pupe_query($query);

  if (mysql_num_rows($res) > 0) {
    $data = mysql_fetch_assoc($res);
    $exportRows[] = array(
      $data['tuoteno'],
      $data['laji'],
      $data['kpl'],
      $data['hinta'],
      $data['kplhinta'],
      $data['selite'],
      $data['laatija'],
      $data['laadittu']
    );
  }
}

/* ---------- write output CSV ---------- */
if (!$exportRows) {
  echo "No matching product codes to export.\n";
  exit;
}

/* remove duplicates while keeping original order */
$exportRows = array_values(array_unique(array_map('serialize', $exportRows)));
$exportRows = array_map('unserialize', $exportRows);

$out = fopen($outputCsv, 'w');
if (!$out) {
  die("Cannot open $outputCsv for writing.\n");
}

/* PHP < 5.5 accepts only 4 args, ? 5.5 accepts 5 */
$useFiveArgs = version_compare(PHP_VERSION, '5.5.0', '>=');

foreach ($exportRows as $csvRow) {
  if ($useFiveArgs) {
    fputcsv($out, $csvRow, $delimiter, $enclosure, $escapeChar);
  } else {
    fputcsv($out, $csvRow, $delimiter, $enclosure);   // 4-arg version
  }
}

fclose($out);

printf(
  "Exported %d product codes to %s\n",
  count($exportRows),
  $outputCsv
);
?>
