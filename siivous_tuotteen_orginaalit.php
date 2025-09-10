#!/usr/bin/php
<?php
/**
 * siivous_tuotteen_orginaalit.php
 *
 * Siivoaa tuotteen_orginaalit-taulua kolmella eri logiikalla:
 * 1. Poistaa tuplarivit (duplikaatit).
 * 2. Poistaa turhat rivit, joita ei loydy tuote-taulusta (turhat).
 * 3. Korjaa orig_tuoteno- ja aineisto-sarakkeet (erikoismerkit).
 *
 * Tulostaa lopuksi kasiteltyjen rivien maaran ja tallentaa muutokset
 * CSV-tiedostoon.
 *
 * Kaytto
 * -----
 * Duplikaattien poisto:
 * php siivous_tuotteen_orginaalit.php <yhtio> duplikaatit
 *
 * Turhien rivien poisto:
 * php siivous_tuotteen_orginaalit.php <yhtio> turhat
 *
 * Erikoismerkkien ja aineiston korjaus:
 * php siivous_tuotteen_orginaalit.php <yhtio> erikoismerkit
 */

if (php_sapi_name() !== 'cli') {
  die("Aja komentorivilta.\n");
}
if (empty($argv[1]) || empty($argv[2])) {
  die("Kaytto: php {$argv[0]} <yhtio> [duplikaatit|turhat|erikoismerkit]\n");
}

date_default_timezone_set('Europe/Helsinki');

require_once 'inc/connect.inc';    // avaa $GLOBALS['masterlink']
require_once 'inc/functions.inc';  // Pupesoft-apurit

/* ------------------------------------------------------- Yhtion ja ajon tyypin maaritys */
$yhtioRivi = hae_yhtion_parametrit(pupesoft_cleanstring($argv[1]));
if (!$yhtioRivi) {
  die("Tuntematon yhtio {$argv[1]}\n");
}
$yhtio = $yhtioRivi['yhtio'];

$siivousTila = isset($argv[2]) ? $argv[2] : 'duplikaatit';

if (!in_array($siivousTila, array('duplikaatit', 'turhat', 'erikoismerkit'))) {
  die("Virheellinen siivoustila. Valitse 'duplikaatit', 'turhat' tai 'erikoismerkit'.\n");
}

cron_log(); // normaali Pupesoft-ajon lokimerkinta

/* ------------------------------------------------------ Apufunktiot */
function puhdista_koodi($s) {
  return strtoupper(str_replace(
    array('/', '_', '.', ' ', '-', '(', ')'), '', $s));
}

function rakenna_avain($rivi) {
  return $rivi['tuoteno'] . '|' .
         $rivi['merkki'] . '|' .
         strtoupper($rivi['aineisto']) . '|' .
         puhdista_koodi($rivi['orig_tuoteno']);
}

function suoritaPoisto($tunnukset) {
    if (empty($tunnukset)) {
        return 0;
    }
    
    if (!mysql_ping($GLOBALS['masterlink'])) {
        echo "Tietokantayhteys katkesi, yritetaan yhdistaa uudelleen...\n";
    }

    $poistoSql = "DELETE FROM tuotteen_orginaalit WHERE tunnus IN (" . implode(',', $tunnukset) . ")";
    pupe_query($poistoSql);
    return count($tunnukset);
}

/* ------------------------------------------------------ Duplikaattien siivouslogiikka */

function kasittele_duplikaattiryhma(array $rivit, array &$poistettavatTunnukset, $csvTiedosto) {
  if (!$rivit) return;

  $puhtaat = $likaiset = array();
  foreach ($rivit as $rivi) {
    if ($rivi['orig_tuoteno'] === puhdista_koodi($rivi['orig_tuoteno'])) {
      $puhtaat[] = $rivi;
    } else {
      $likaiset[] = $rivi;
    }
  }

  if ($puhtaat) {
    foreach ($likaiset as $rivi) {
      fputcsv($csvTiedosto, array($rivi['tunnus'], $rivi['tuoteno'], $rivi['orig_tuoteno']), ';');
      $poistettavatTunnukset[] = $rivi['tunnus'];
    }
  }

  if (count($puhtaat) > 1) {
    usort($puhtaat, function ($a, $b) {
      return strcmp($b['luontiaika'], $a['luontiaika']);
    });

    $uusinAika = $puhtaat[0]['luontiaika'];
    foreach (array_slice($puhtaat, 1) as $rivi) {
      if ($rivi['luontiaika'] < $uusinAika) {
        fputcsv($csvTiedosto, array($rivi['tunnus'], $rivi['tuoteno'], $rivi['orig_tuoteno']), ';');
        $poistettavatTunnukset[] = $rivi['tunnus'];
      }
    }
  }
}

function suoritaDuplikaattienSiivous($yhtio, $csvTiedosto) {
  echo "Aloitetaan duplikaattien siivous...\n";
  $yhteensaPoistettu = 0;
  $poistoPalaKoko = 1000;
  $keratytTunnukset = array();
  $yhteys = $GLOBALS['masterlink'];

  $tuotenumeroSql = "SELECT DISTINCT tuoteno FROM tuotteen_orginaalit WHERE yhtio = '$yhtio'";
  $tuotenumeroTulos = pupe_query($tuotenumeroSql);
  if (!$tuotenumeroTulos) {
      die("Ei voitu hakea tuotenumeroita: " . mysql_error($yhteys) . "\n");
  }

  $kaikkiTuotenumerot = array();
  while ($rivi = mysql_fetch_assoc($tuotenumeroTulos)) {
      $kaikkiTuotenumerot[] = $rivi['tuoteno'];
  }
  $tuotenumeroidenMaara = count($kaikkiTuotenumerot);
  mysql_free_result($tuotenumeroTulos);
  echo "Loytyi $tuotenumeroidenMaara eri tuotenumeroa kasiteltavaksi.\n";

  $kasiteltyLaskuri = 0;
  foreach ($kaikkiTuotenumerot as $tuotenumero) {
    $kasiteltyLaskuri++;
    if ($kasiteltyLaskuri % 1000 == 0) {
        echo "Kasitelty $kasiteltyLaskuri / $tuotenumeroidenMaara tuotenumeroa...\n";
    }

    $sql = "
    SELECT tunnus, tuoteno, orig_tuoteno, aineisto, merkki, luontiaika
    FROM tuotteen_orginaalit
    WHERE yhtio = '$yhtio' AND tuoteno = '" . mysql_real_escape_string($tuotenumero) . "'
    ORDER BY tuoteno, UPPER(aineisto),
      UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
      orig_tuoteno, '/', ''), '_', ''), '.', ''), ' ', ''), '-', ''), '(', ''), ')', '')),
      orig_tuoteno, luontiaika";

    $tulos = mysql_unbuffered_query($sql, $yhteys);
    if (!$tulos) {
      echo("Varoitus: Haku epaonnistui tuotenumerolle $tuotenumero: " . mysql_error($yhteys) . "\n");
      continue;
    }

    $ryhmaAvain = null;
    $rivit      = array();

    while ($rivi = mysql_fetch_assoc($tulos)) {
      $avain = rakenna_avain($rivi);
      if ($ryhmaAvain !== null && $avain !== $ryhmaAvain) {
        kasittele_duplikaattiryhma($rivit, $keratytTunnukset, $csvTiedosto);
        $rivit = array();
      }
      $rivit[]    = $rivi;
      $ryhmaAvain = $avain;
    }
    kasittele_duplikaattiryhma($rivit, $keratytTunnukset, $csvTiedosto);
    mysql_free_result($tulos);

    if (count($keratytTunnukset) >= $poistoPalaKoko) {
        $yhteensaPoistettu += suoritaPoisto(array_unique($keratytTunnukset));
        $keratytTunnukset = array();
    }
  }

  if (!empty($keratytTunnukset)) {
      $yhteensaPoistettu += suoritaPoisto(array_unique($keratytTunnukset));
  }

  return $yhteensaPoistettu;
}

/* ------------------------------------------------------ Turhien rivien siivouslogiikka */

function suoritaTurhienSiivous($yhtio, $csvTiedosto) {
  echo "Aloitetaan turhien rivien siivous...\n";
  $yhteensaPoistettu = 0;
  $yhteys = $GLOBALS['masterlink'];
  $palaKoko = 50000;

  $rajaSql = "SELECT MIN(tunnus) AS min_tunnus, MAX(tunnus) AS max_tunnus FROM tuotteen_orginaalit WHERE yhtio = '$yhtio'";
  $rajaTulos = pupe_query($rajaSql);
  $rajat = mysql_fetch_assoc($rajaTulos);
  $minTunnus = $rajat['min_tunnus'];
  $maxTunnus = $rajat['max_tunnus'];

  if (!$minTunnus) {
    echo "Ei riveja kasiteltavaksi.\n";
    return 0;
  }

  for ($i = $minTunnus; $i <= $maxTunnus; $i += $palaKoko) {
    $alku = $i;
    $loppu = $i + $palaKoko - 1;
    echo "Kasitellaan tunnukset valilta $alku - $loppu...\n";
    $palanTunnukset = array();

    $sql = "
      SELECT t_org.tunnus, t_org.tuoteno, t_org.orig_tuoteno
      FROM tuotteen_orginaalit AS t_org
      LEFT JOIN tuote AS t ON t_org.orig_tuoteno = t.tuoteno AND t_org.yhtio = t.yhtio
      WHERE t_org.yhtio = '$yhtio'
        AND t_org.tunnus BETWEEN $alku AND $loppu
        AND t.tuoteno IS NULL";

    $tulos = mysql_unbuffered_query($sql, $yhteys);
    if (!$tulos) {
        echo("Varoitus: Haku epaonnistui: " . mysql_error($yhteys) . "\n");
        continue;
    }

    while ($rivi = mysql_fetch_assoc($tulos)) {
      fputcsv($csvTiedosto, array($rivi['tunnus'], $rivi['tuoteno'], $rivi['orig_tuoteno']), ';');
      $palanTunnukset[] = $rivi['tunnus'];
    }
    mysql_free_result($tulos);
    
    $yhteensaPoistettu += suoritaPoisto($palanTunnukset);
  }

  return $yhteensaPoistettu;
}

/* ------------------------------------------------------ Erikoismerkkien siivouslogiikka */

function suoritaErikoismerkkiSiivous($yhtio, $csvTiedosto) {
  echo "Aloitetaan erikoismerkkien ja aineiston siivous...\n";
  $yhteensaPaivitetty = 0;
  $yhteys = $GLOBALS['masterlink'];
  $palaKoko = 50000;
  
  $puhdistusLauseke = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(orig_tuoteno, '/', ''), '_', ''), '.', ''), ' ', ''), '-', ''), '(', ''), ')', ''))";

  $rajaSql = "SELECT MIN(tunnus) AS min_tunnus, MAX(tunnus) AS max_tunnus FROM tuotteen_orginaalit WHERE yhtio = '$yhtio'";
  $rajaTulos = pupe_query($rajaSql);
  $rajat = mysql_fetch_assoc($rajaTulos);
  $minTunnus = $rajat['min_tunnus'];
  $maxTunnus = $rajat['max_tunnus'];

  if (!$minTunnus) {
    echo "Ei riveja kasiteltavaksi.\n";
    return 0;
  }

  for ($i = $minTunnus; $i <= $maxTunnus; $i += $palaKoko) {
    $alku = $i;
    $loppu = $i + $palaKoko - 1;
    echo "Kasitellaan tunnukset valilta $alku - $loppu...\n";

    // VAIHE 1: Etsi kaikki korjattavat rivit ja kirjaa ne CSV:hen
    $hakuSql = "
      SELECT tunnus, orig_tuoteno, aineisto
      FROM tuotteen_orginaalit
      WHERE yhtio = '$yhtio'
        AND tunnus BETWEEN $alku AND $loppu
        AND (
          (orig_tuoteno != $puhdistusLauseke AND LENGTH($puhdistusLauseke) > 0)
          OR BINARY aineisto != UPPER(aineisto)
        )";

    $tulos = mysql_unbuffered_query($hakuSql, $yhteys);
    if (!$tulos) {
        echo("Varoitus: Haku epaonnistui: " . mysql_error($yhteys) . "\n");
        continue;
    }

    $muutettavienMaara = 0;
    while ($rivi = mysql_fetch_assoc($tulos)) {
        $puhdasKoodi = puhdista_koodi($rivi['orig_tuoteno']);
        $isoAineisto = strtoupper($rivi['aineisto']);
        fputcsv($csvTiedosto, array($rivi['tunnus'], $rivi['orig_tuoteno'], $puhdasKoodi, $rivi['aineisto'], $isoAineisto), ';');
        $muutettavienMaara++;
    }
    mysql_free_result($tulos);

    // VAIHE 2: Suorita yksi massapaivitys kaikille loydetyille riveille
    if ($muutettavienMaara > 0) {
        $paivitysSql = "
            UPDATE tuotteen_orginaalit
            SET
                orig_tuoteno = IF(LENGTH($puhdistusLauseke) > 0, $puhdistusLauseke, orig_tuoteno),
                aineisto = UPPER(aineisto)
            WHERE
                yhtio = '$yhtio'
                AND tunnus BETWEEN $alku AND $loppu
                AND (
                    (orig_tuoteno != $puhdistusLauseke AND LENGTH($puhdistusLauseke) > 0)
                    OR BINARY aineisto != UPPER(aineisto)
                )";
        
        pupe_query($paivitysSql);
        $yhteensaPaivitetty += $muutettavienMaara;
    }
  }

  return $yhteensaPaivitetty;
}


/* -------------------------------------------------- Paaohjelma */

$csvTiedostonNimi = 'siivotut_orginaalit_' . $siivousTila . '_' . date('Ymd_His') . '.csv';
$csvTiedosto = fopen($csvTiedostonNimi, 'w');
if ($csvTiedosto === false) {
  die("Ei voitu avata CSV-tiedostoa kirjoitusta varten: $csvTiedostonNimi\n");
}

$yhteensaKasitelty = 0;
$tulosViesti = "";

if ($siivousTila == 'erikoismerkit') {
  fputcsv($csvTiedosto, array('tunnus', 'vanha_orig_tuoteno', 'uusi_orig_tuoteno', 'vanha_aineisto', 'uusi_aineisto'), ';');
  $yhteensaKasitelty = suoritaErikoismerkkiSiivous($yhtio, $csvTiedosto);
  $tulosViesti = "Paivitettyja riveja yhteensa: $yhteensaKasitelty";
} else {
  fputcsv($csvTiedosto, array('tunnus', 'tuoteno', 'orig_tuoteno'), ';');
  if ($siivousTila == 'duplikaatit') {
    $yhteensaKasitelty = suoritaDuplikaattienSiivous($yhtio, $csvTiedosto);
  } elseif ($siivousTila == 'turhat') {
    $yhteensaKasitelty = suoritaTurhienSiivous($yhtio, $csvTiedosto);
  }
  $tulosViesti = "Poistettuja riveja yhteensa: $yhteensaKasitelty";
}

fclose($csvTiedosto);
echo "Kasiteltyjen rivien tiedot exportattu tiedostoon: $csvTiedostonNimi\n";

/* ---------------------------- Tulos */
echo $tulosViesti . "\n";
?>

