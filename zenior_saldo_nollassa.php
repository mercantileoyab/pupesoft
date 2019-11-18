<?php

// Kutsutaanko CLI:st‰
if (php_sapi_name() != 'cli') {
  die ("Ajo ainoastaan cronista / komentorivilt‰!");
}

if (!isset($argv[1])) {
  die ("Anna yhtio parametriksi!");
}

$pupe_root_polku = dirname(__FILE__);

// Otetaan includepath aina rootista
ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.dirname(__FILE__).PATH_SEPARATOR."/usr/share/pear");
error_reporting(E_ALL ^E_WARNING ^E_NOTICE);
ini_set("display_errors", 0);

// Otetaan tietokanta connect
require "inc/connect.inc";
require "inc/functions.inc";

// Logitetaan ajo
cron_log();

// Tehd‰‰n oletukset
$kukarow['yhtio'] = $argv[1];
$kukarow['kuka'] = "admin";
$yhtiorow = hae_yhtion_parametrit($argv[1]);
$kukarow['kieli'] = $yhtiorow['kieli'];

if ($yhtiorow["epakurantoinnin_myyntihintaleikkuri"] != 'Z') {
  die(t("T‰m‰ toiminto on k‰ytett‰viss‰ vain, jos yhtiˆparametri epakurantoinnin_myyntihintaleikkuri on 'Z'"));
}

// hae nollasaldoiset ep‰kurantit, tarvitaan tuoteno ja avainsanalle tallennettu alkuper‰inen hinta
$query  = "SELECT tuote.tuoteno,
           tuotteen_avainsanat.selitetark AS orig_myyntihinta,
           MAX(tuote.myyntihinta)         AS varahinta,
           SUM(tuotepaikat.saldo)         AS saldosumma
           FROM tuote
           LEFT JOIN tuotteen_avainsanat ON (tuote.yhtio = tuotteen_avainsanat.yhtio 
              AND tuotteen_avainsanat.kieli = '{$yhtiorow['kieli']}' 
              AND tuote.tuoteno = tuotteen_avainsanat.tuoteno 
              AND tuotteen_avainsanat.laji = 'zeniorparts' 
              AND tuotteen_avainsanat.selite = 'K')
           JOIN tuotepaikat ON (tuotepaikat.yhtio = tuote.yhtio 
              AND tuotepaikat.tuoteno = tuote.tuoteno)
           WHERE tuote.yhtio           = '{$kukarow["yhtio"]}'
           AND tuote.epakurantti25pvm != '0000-00-00'
           GROUP BY 1, 2
           HAVING saldosumma = 0";
$result = pupe_query($query);

while ($row = mysql_fetch_assoc($result)) {
  if ($yhtiorow['epakurantti_automaattimuutokset'] == 'Z') {
    $puun_tunnus = t_avainsana("ZEPA_OS_PUUNALK", "", "AND selitetark = (SELECT selitetark FROM tuotteen_avainsanat WHERE yhtio = '{$kukarow["yhtio"]}' AND kieli = '{$yhtiorow["kieli"]}' AND tuoteno = '{$row["tuoteno"]}' AND laji = 'zeniorparts' AND selite = 'ALKUP_OSASTO' LIMIT 1)", "", "", "selite");
    if ($puun_tunnus != "") {
      $query = "DELETE FROM puun_alkio WHERE yhtio = '{$kukarow["yhtio"]}' AND liitos = '{$row["tuoteno"]}' AND puun_tunnus = {$puun_tunnus}";
      pupe_query($query);
    }

    $set_lisakentat = "hinnastoon  = 'E', ostoehdotus = 'E', ";

    $alkup_try = executescalar("SELECT selitetark FROM tuotteen_avainsanat WHERE yhtio = '{$kukarow["yhtio"]}' AND kieli = '{$yhtiorow["kieli"]}' AND tuoteno = '{$row["tuoteno"]}' AND laji = 'zeniorparts' AND selite = 'ALKUP_TRY' LIMIT 1");
    if ($alkup_try != null) {
      $set_lisakentat .= "try = '{$alkup_try}', ";
    }

    $alkup_ale = executescalar("SELECT selitetark FROM tuotteen_avainsanat WHERE yhtio = '{$kukarow["yhtio"]}' AND kieli = '{$yhtiorow["kieli"]}' AND tuoteno = '{$row["tuoteno"]}' AND laji = 'zeniorparts' AND selite = 'ALKUP_ALERYHMA' LIMIT 1");
    if ($alkup_ale != null) {
      $set_lisakentat .= "aleryhma = '{$alkup_ale}', ";
    }

    $alkup_ost = executescalar("SELECT selitetark FROM tuotteen_avainsanat WHERE yhtio = '{$kukarow["yhtio"]}' AND kieli = '{$yhtiorow["kieli"]}' AND tuoteno = '{$row["tuoteno"]}' AND laji = 'zeniorparts' AND selite = 'ALKUP_OSASTO' LIMIT 1");
    if ($alkup_ost != null) {
      $set_lisakentat .= "osasto = '{$alkup_ost}', ";
    }
  }
  else {
    $set_lisakentat = "";
  }

  // jos talteenotettu hinta ei ole nollaa isompi, otetaan viimeisin myyntihinta
  $hinta = (floatval($row["orig_myyntihinta"]) > 0) ? floatval($row["orig_myyntihinta"]) : $row["varahinta"];
  $selite = t("Ep‰kuranttimuutos") . ": ".t("Tuote")." {$row["tuoteno"]} ".t("p‰ivitet‰‰n kurantiksi");

  $t_query = "UPDATE tuote
              SET status        = 'T',
              {$set_lisakentat}
              epakurantti25pvm  = '0000-00-00',
              epakurantti50pvm  = '0000-00-00',
              epakurantti75pvm  = '0000-00-00',
              epakurantti100pvm = '0000-00-00',
              myyntihinta       = {$hinta}
              WHERE yhtio       = '{$kukarow["yhtio"]}'
              AND tuoteno       = '{$row["tuoteno"]}';";
  $t_result = pupe_query($t_query);

  if ($yhtiorow['epakurantti_automaattimuutokset'] == 'Z') {
    $t_query = "DELETE FROM tuotteen_avainsanat
                WHERE yhtio   = '{$kukarow["yhtio"]}'
                  AND kieli   = '{$yhtiorow["kieli"]}'
                  AND laji    = 'zeniorparts'
                  AND tuoteno = '{$row["tuoteno"]}';";
    $t_result = pupe_query($t_query);
  }

  $t_query = "INSERT INTO tapahtuma SET
              yhtio    = '{$kukarow["yhtio"]}',
              tuoteno  = '{$row["tuoteno"]}',
              laji     = 'Ep‰kurantti',
              kpl      = '0',
              hinta    = 0,
              kplhinta = 0,
              selite   = '$selite',
              laatija  = '{$kukarow["kuka"]}',
              laadittu = now()";
  $t_result = pupe_query($t_query);
}
