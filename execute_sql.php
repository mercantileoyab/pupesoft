<?php

/*
  Katso asetukset alla
*/

// Kutsutaanko CLI:stה
if (php_sapi_name() != 'cli') {
  die("Tהtה scriptiה voi ajaa vain komentoriviltה!");
}

if (!isset($argv[1]) or !$argv[1]) {
  echo "Anna yhtio";
  exit;
}

date_default_timezone_set('Europe/Helsinki');

require "inc/connect.inc";
require "inc/functions.inc";

// ytiorow. Jos ei lצydy, lopeta cron
$yhtiorow = hae_yhtion_parametrit(pupesoft_cleanstring($argv[1]));
if (!$yhtiorow) {
  echo "Vaara yhtio";
  exit;
}

// Logitetaan ajo
cron_log();

ini_set('memory_limit', '8000M');
ini_set('max_execution_time', 30000);

$sql_tiedostot = glob('datain/sql/*.{sql}', GLOB_BRACE);
foreach($sql_tiedostot as $sql_komento) {
  $query = file_get_contents($sql_komento);
  pupe_query($query);
}