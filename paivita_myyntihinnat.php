<?php
// Kutsutaanko CLI:st?
if (php_sapi_name() != 'cli') {
  die("Tata scriptia voi ajaa vain komentorivilta!");
}

if (!isset($argv[1]) or !$argv[1]) {
  echo "Anna yhtio";
  exit;
}

date_default_timezone_set('Europe/Helsinki');

require_once("inc/connect.inc");
require_once("inc/functions.inc");

// ytiorow. Jos ei loydy, lopeta cron
$yhtiorow = hae_yhtion_parametrit(pupesoft_cleanstring($argv[1]));
if (!$yhtiorow) {
  echo "Vaara yhtio";
  exit;
}

// Logitetaan ajo
cron_log();

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
set_time_limit(0);

class PaivitaMyyntihintaKertoimella {

  private $yhtio;

  public function __construct($yhtiorow) {
    $this->yhtio = $yhtiorow['yhtio'];
  }

  public function suorita() {
    $log_tiedosto = dirname(__FILE__) . '/paivita_myyntihinta_kertoimella.log.csv';
    $kahva = fopen($log_tiedosto, 'a');

    if (filesize($log_tiedosto) == 0) {
      fputcsv($kahva, array('tuoteno', 'vanha_myyntihinta', 'uusi_myyntihinta'));
    }

    // Hae tuotteet, joita on muutettu viimeisen 3 paivan aikana ja joilla on mhkerroin maaritelty
    $sql = "SELECT tuoteno, myyntihinta, mhkerroin
            FROM tuote
            WHERE yhtio = '".$this->yhtio."' AND (muutospvm >= DATE_SUB(NOW(), INTERVAL 3 DAY) OR luontiaika >= DATE_SUB(NOW(), INTERVAL 3 DAY))
              AND mhkerroin IS NOT NULL
              AND mhkerroin != 1.00";

    $result = pupe_query($sql);

    while ($tuote = mysql_fetch_assoc($result)) {
      // Hae toimittajan korkein ostohinta jarjestyksella 1
      $sql_toimittaja = "SELECT ostohinta
                         FROM tuotteen_toimittajat
                         WHERE yhtio = '".$this->yhtio."' AND tuoteno = '" . mysql_real_escape_string($tuote['tuoteno']) . "' AND jarjestys = 1
                         ORDER BY ostohinta DESC
                         LIMIT 1";

      $result_toimittaja = pupe_query($sql_toimittaja);
      
      if (mysql_num_rows($result_toimittaja) > 0) {
        $toimittaja = mysql_fetch_assoc($result_toimittaja);
        
        $ostohinta = $toimittaja['ostohinta'];
        $mhkerroin = $tuote['mhkerroin'];
        $vanha_myyntihinta = $tuote['myyntihinta'];

        $uusi_myyntihinta = round($ostohinta * $mhkerroin, 2);

        // Paivita myyntihinta vain jos se on muuttunut
        if (bccomp((string)$uusi_myyntihinta, (string)$vanha_myyntihinta, 2) != 0) {
          $update_sql = "UPDATE tuote SET myyntihinta = '" . $uusi_myyntihinta . "' WHERE yhtio = '".$this->yhtio."' AND tuoteno = '" . mysql_real_escape_string($tuote['tuoteno']) . "'";
          
          if (pupe_query($update_sql)) {
            // Kirjaa muutos lokitiedostoon
            fputcsv($kahva, array($tuote['tuoteno'], $vanha_myyntihinta, $uusi_myyntihinta));
          }
        }
      }
    }

    fclose($kahva);
  }
}

$paivitys = new PaivitaMyyntihintaKertoimella($yhtiorow);
$paivitys->suorita();

?>