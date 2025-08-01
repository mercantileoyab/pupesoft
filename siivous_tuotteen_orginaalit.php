#!/usr/bin/php
<?php
/**
 * cleanup_tuotteen_orginaalit.php
 *
 * Poistaa tuplarivit tuotteen_orginaalit-taulusta ilman self-JOINeja
 * ja puskuroimatta 17 miljoonan rivin tulosjoukkoa. Tulostaa lopuksi
 * poistettujen rivien maaran.
 *
 * Saannot
 * -----
 * 1. Jos orig_tuoteno-koodin *puhdas* versio (erikoismerkit poistettu)
 * on olemassa samalle tuotenumerolle + aineistolle + merkille, kaikki
 * viela "likaiset" versiot poistetaan.
 * 2. Puhtaiden duplikaattien joukosta, joilla on sama tuoteno + aineisto + merkki,
 * sailytetaan uusin luontiaika; vanhemmat poistetaan.
 * 3. Puhtaat "erikoismerkki"-duplikaatit sailyvat, jos puhdasta vastinetta ei ole.
 * 4. Saman luontiajan omaavat rivit sailyvat.
 *
 * Kaytto
 * -----
 * php cleanup_tuotteen_orginaalit.php <yhtio>
 */

if (php_sapi_name() !== 'cli') {
  die("Run from command line.\n");
}
if (empty($argv[1])) {
  die("Usage: php {$argv[0]} <yhtio>\n");
}

date_default_timezone_set('Europe/Helsinki');

require_once 'inc/connect.inc';    // avaa $GLOBALS['masterlink']
require_once 'inc/functions.inc';  // Pupesoft-apurit

$yhtioRivi = hae_yhtion_parametrit(pupesoft_cleanstring($argv[1]));
if (!$yhtioRivi) {
  die("Unknown yhtio {$argv[1]}\n");
}
$yhtio = $yhtioRivi['yhtio'];
cron_log();                        // normaali Pupesoft-ajon lokimerkinta

function puhdista_koodi($s) {
  return strtoupper(str_replace(
    array('/', '_', '.', ' ', '-', '(', ')'), '', $s));
}

/**
 * Tunnistaa poistettavat rivit ryhman sisalta, kirjoittaa ne CSV-tiedostoon ja lisaa niiden tunnukset poistolistaan.
 * @param array $rivit Ryhma riveja, joilla on sama avain.
 * @param array &$poistettavatTunnukset Viittaus taulukkoon, joka sisaltaa kaikki poistettavaksi merkityt tunnukset.
 * @param resource $csvTiedosto Avoin tiedostokahva CSV-tiedostoon.
 */
function kasittele_ryhma(array $rivit, array &$poistettavatTunnukset, $csvTiedosto) {

  if (!$rivit) return;

  $puhtaat = $likaiset = array();
  foreach ($rivit as $rivi) {
    if ($rivi['orig_tuoteno'] === puhdista_koodi($rivi['orig_tuoteno'])) {
      $puhtaat[] = $rivi;
    } else {
      $likaiset[] = $rivi;
    }
  }

  /* saanto 1: Jos puhdas versio on olemassa, kaikki likaiset versiot merkataan poistettavaksi. */
  if ($puhtaat) {
    foreach ($likaiset as $rivi) {
      // Kirjoita rivi heti CSV-tiedostoon ja lisaa vain tunnus poistolistalle.
      fputcsv($csvTiedosto, $rivi, ';');
      $poistettavatTunnukset[] = $rivi['tunnus'];
    }
  }

  /* saannot 2 & 4: Puhtaiden versioiden joukosta sailyta uusin, poista vanhemmat. */
  if (count($puhtaat) > 1) {
    // Jarjesta luontiajan mukaan, uusin ensin.
    usort($puhtaat, function ($a, $b) {
      return strcmp($b['luontiaika'], $a['luontiaika']);
    });

    $uusinAika = $puhtaat[0]['luontiaika'];
    foreach (array_slice($puhtaat, 1) as $rivi) {
      // Poista, jos vanhempi kuin uusin. Saman aikaleiman omaavat rivit sailytetaan.
      if ($rivi['luontiaika'] < $uusinAika) {
        fputcsv($csvTiedosto, $rivi, ';');
        $poistettavatTunnukset[] = $rivi['tunnus'];
      }
    }
  }
}

$sql = "
SELECT
  tunnus,
  tuoteno,
  orig_tuoteno,
  aineisto,
  merkki,
  luontiaika
FROM    tuotteen_orginaalit
WHERE   yhtio = '$yhtio'
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

$yhteys = $GLOBALS['masterlink'];                 // Pupesoft-yhteys
$tulos  = mysql_unbuffered_query($sql, $yhteys);
if (!$tulos) {
  die("SELECT failed: " . mysql_error($yhteys) . "\n");
}

$csvTiedostonNimi = 'deleted_tuotteen_orginaalit_' . date('Ymd_His') . '.csv';
$csvTiedosto = fopen($csvTiedostonNimi, 'w');
if ($csvTiedosto === false) {
  die("Error: Could not open CSV file for writing: $csvTiedostonNimi\n");
}
// Kirjoita CSV-otsikko heti tiedoston luonnin jalkeen.
fputcsv($csvTiedosto, array('tunnus', 'tuoteno', 'orig_tuoteno', 'aineisto', 'merkki', 'luontiaika'), ';');


$ryhmaAvain            = null;
$rivit                 = array();
$poistettavatTunnukset = array(); // Tama tallentaa nyt vain tunnukset, mika saastaa muistia.

/* rakenna uniikki avain duplikaattijoukolle */
function rakenna_avain($rivi) {
  return $rivi['tuoteno'] . '|' .
         $rivi['merkki'] . '|' .
         strtoupper($rivi['aineisto']) . '|' .
         puhdista_koodi($rivi['orig_tuoteno']);
}

// Kasittele tulosjoukko rivi rivilta muistinkayton minimoimiseksi.
while ($rivi = mysql_fetch_assoc($tulos)) {
  $avain = rakenna_avain($rivi);

  // Kun avain vaihtuu, olemme siirtyneet uuteen potentiaalisten duplikaattien ryhmaan.
  // Kasittele valmis ryhma ennen uuden aloittamista.
  if ($ryhmaAvain !== null && $avain !== $ryhmaAvain) {
    kasittele_ryhma($rivit, $poistettavatTunnukset, $csvTiedosto);
    $rivit = array(); // Nollaa seuraavaa ryhmaa varten.
  }

  $rivit[]    = $rivi;
  $ryhmaAvain = $avain;
}
/* Kasittele viimeinen ryhma silmukan paatyttya. */
kasittele_ryhma($rivit, $poistettavatTunnukset, $csvTiedosto);

// Sulje CSV-tiedosto, kun kaikki rivit on kasitelty.
fclose($csvTiedosto);
echo "Exported data of deleted rows to: $csvTiedostonNimi\n";

$yhteensaPoistettu = 0;
$pala              = 1000;

// Varmistetaan, etta tietokantayhteys on elossa ennen poistoja.
// Pitka rivien kasittely saattaa katkaista yhteyden.
if (!mysql_ping($yhteys)) {
  die("Tietokantayhteys katkesi eika sita voitu palauttaa.\n");
}

// Poistetaan duplikaatit $poistettavatTunnukset-taulukon perusteella.
$uniikitPoistettavatTunnukset = array_unique($poistettavatTunnukset);

for ($i = 0, $n = count($uniikitPoistettavatTunnukset); $i < $n; $i += $pala) {
  $tunnukset = array_slice($uniikitPoistettavatTunnukset, $i, $pala);
  $poistoSql = "DELETE FROM tuotteen_orginaalit
    WHERE tunnus IN (" . implode(',', $tunnukset) . ")";
  pupe_query($poistoSql);
  $yhteensaPoistettu += count($tunnukset);
}

echo "Removed rows: $yhteensaPoistettu\n";
?>
