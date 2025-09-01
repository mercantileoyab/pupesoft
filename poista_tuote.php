<?php

// Kutsutaanko CLI:stä
$php_cli = (php_sapi_name() == 'cli') ? true : false;

if ($php_cli) {
  // Pupesoft root include_pathiin
  ini_set("include_path", ini_get("include_path").PATH_SEPARATOR.dirname(__FILE__));

  // Otetaan tietokanta connect
  require "inc/connect.inc";
  require "inc/functions.inc";

  // Logitetaan ajo
  cron_log();

  if (!isset($argv[1])) {
    echo "Anna yhtio!\n";
    die;
  }

  if (!isset($argv[2])) {
    echo "Anna tiedostonimi\n";
    die;
  }

  // Tehdään parametrit
  $tee = "file";

  // Haetaan yhtiörow ja kukarow
  $yhtio    = pupesoft_cleanstring($argv[1]);
  $yhtiorow = hae_yhtion_parametrit($yhtio);
  $kukarow  = hae_kukarow('admin', $yhtiorow['yhtio']);
}
else {
  require "inc/parametrit.inc";
}

// Tämä vaatii paljon muistia
error_reporting(E_ALL);
ini_set("memory_limit", "5G");
ini_set("display_errors", 1);
unset($pupe_query_debug);

function is_log($str) {
  global $php_cli;

  if ($php_cli) {
    echo date("d.m.Y @ G:i:s") . ": {$str}\n";
  }
  else {
    echo "<font class='message'>{$str}</font><br>";
  }
}

function kasittele_tuote_tiedosto($file_name, $real_name = '') {
  global $kukarow, $yhtiorow;

  $path_parts = ($real_name == '') ? pathinfo($file_name) : pathinfo($real_name);
  $name = strtoupper($path_parts['filename']);
  $ext  = strtoupper($path_parts['extension']);

  if ($ext != "TXT" and $ext != "CSV") {
    die ("<font class='error'><br>".t("Ainoastaan .txt ja .csv tiedostot sallittuja")."!</font>");
  }

  $file  = fopen($file_name , "r") or die (t("Tiedoston avaus epäonnistui")."!");
  $error = 0;
  $count = 0;

  while ($rivi = fgets($file)) {

    // luetaan rivi tiedostosta..
    $rivi = explode("\t", pupesoft_cleanstring($rivi));
    $count++;

    $tuoteno = trim($rivi[0]);

    if ($tuoteno != '') {
      // Etsitään tuote
      $query = "SELECT tunnus
                FROM tuote
                WHERE yhtio = '$kukarow[yhtio]'
                AND tuoteno = '$tuoteno'";
      $tuoteresult = pupe_query($query);

      if (mysql_num_rows($tuoteresult) == 0) {
        $error++;
        echo "<font class='message'>".t("TUOTENUMEROA EI LÖYDY").": $tuoteno</font><br>";
      }
    }
    else {
        $error++;
        echo "<font class='message'>".t("Tuotenumero puuttuu tiedostosta")."</font><br>";
    }
  }

  fclose($file);

  if ($count == 0) {
    die ("<font class='error'><br>".t("Tiedosto on tyhjä")."!</font>");
  }

  return $error;
}

if ($php_cli) {
  echo "\n";
  is_log("Tuotteiden poisto");
}
else {
  echo "<font class='head'>".t("Tuotteiden poisto")."</font><hr>";
  flush();
}

$vikaa          = 0;
$tarkea         = 0;
$kielletty      = 0;
$lask           = 0;
$postoiminto    = "X";
$tyhjatok       = "";
$chekatut       = 0;
$taulut         = "";
$error          = 0;
$failista       = "";
$tee            = (isset($tee)) ? $tee : "";
$postit         = (isset($postit)) ? $postit : array();

if (!isset($muistutus)) $muistutus = "";

if ($php_cli) {
  $uploaded_filename = $argv[2];
  $error = kasittele_tuote_tiedosto($uploaded_filename);
  $failista = "JOO";
}
elseif (isset($_FILES['userfile']) and is_uploaded_file($_FILES['userfile']['tmp_name']) === TRUE and $tee == "file") {
  echo "<font class='message'>".t("Tutkaillaan mitä olet lähettänyt").".<br></font>";
  flush();

  $uploaded_filename = $_FILES['userfile']['tmp_name'];
  $error = kasittele_tuote_tiedosto($uploaded_filename, $_FILES['userfile']['name']);
  $failista = "JOO";
}
elseif (isset($_FILES['userfile']) and is_uploaded_file($_FILES['userfile']['tmp_name']) !== TRUE and $tee == "file") {

  $tuoteno = strtoupper(trim($tuoteno));

  //Tuotenumero tulee käyttöliittymästä
  $query1  = "SELECT tunnus from tuote where yhtio = '$kukarow[yhtio]' and tuoteno = '$tuoteno'";
  $tuoteresult = pupe_query($query1);

  if (mysql_num_rows($tuoteresult) == 0) {
      $error++;
      echo "<font class='message'>".t("TUOTENUMEROA EI LÖYDY").": $tuoteno</font><br>";
  }

  $failista = "EI";
}

if ($error == 0 and $tee == "file") {

  is_log(t("Syötetyt tiedot ovat ok"));
  flush();

  is_log(t("Aloitellaan poisto, tämä voi kestää hetken"));
  flush();

  $tulos = array();

  $locktables = array();
  $locktables['liitetiedostot'] = "liitetiedostot";

  //$dbkanta --> tulee salasanat.php:stä
  $query  = "SHOW TABLES FROM $dbkanta";
  $tabresult = pupe_query($query);

  while ($tables = mysql_fetch_array($tabresult)) {
    $query  = "describe $tables[0]";
    $fieldresult = pupe_query($query);

    while ($fields = mysql_fetch_array($fieldresult)) {
      if ((strpos($fields[0], "tuotenumero") !== false or strpos($fields[0], "tuoteno") !== false) and $fields[0] != 'toim_tuoteno') {
        $locktables[$tables[0]] = $tables[0];
        $tulos[] = $tables[0]."##".$fields[0];
      }
    }
    if ($tables[0] == 'puun_alkio') {
      $locktables[$tables[0]] = $tables[0];
      $tulos[] = $tables[0]."##liitos";
    }
  }

  foreach ($locktables as $ltable) {
    $taulut .= $ltable.' WRITE,';
  }

  $taulut = substr($taulut, 0, -1);

  $montako = count($tulos);

  if ($montako > 0) {
    is_log(t("Löydettiin paikat joista pitää poistaa").": $montako kappaletta.");
    flush();
  }
  else {
    die ("<font class='error'><br>".t("Ei löydetty poistettavia paikkoja, ei uskalleta tehdä mitään")."!</font>");
  }

  is_log(t("Nyt ollan kerätty tietokannasta kaikki tarpeellinen")."<br>".t("Aloitellaan poisto")."...");
  flush();

  if ($failista == "JOO") {
    $file = fopen($uploaded_filename, "r") or die (t("Tiedoston avaus epäonnistui")."!");
  }
  else {
    $tmpfname = tempnam("/tmp", "Poistatuote");
    file_put_contents($tmpfname, "$tuoteno");
    $file = fopen($tmpfname, "r") or die (t("Tiedoston avaus epäonnistui")."!");
  }

  while ($rivi = fgets($file)) {
    // luetaan rivi tiedostosta..
    $rivi = explode("\t", pupesoft_cleanstring($rivi));

    if (trim($rivi[0]) != '') {

      $lokki = "LOCK TABLES $taulut";
      pupe_query($lokki);

      $tuoteno = strtoupper(trim($rivi[0]));

      $query  = "SELECT tunnus
                 FROM tuote
                 WHERE yhtio = '$kukarow[yhtio]'
                 AND tuoteno = '$tuoteno'";
      $tuoteresult = pupe_query($query);

      if (mysql_num_rows($tuoteresult) == 1) {

        $tuoterow = mysql_fetch_assoc($tuoteresult);
        $tuote_tunnus = $tuoterow['tunnus'];

        is_log(t("Poistetaan tuotenumero").": $tuoteno.");
        flush();

        // Poistetaan liitetiedostot
        if ($tuote_tunnus > 0) {
            $query = "DELETE FROM liitetiedostot
                      WHERE yhtio = '$kukarow[yhtio]'
                      AND liitos = 'tuote'
                      AND liitostunnus = '$tuote_tunnus'";
            pupe_query($query);
        }

        foreach ($tulos as $saraketaulu) {

          list($taulu, $sarake) = explode("##", $saraketaulu);

          if ($taulu == 'puun_alkio') {
            $query = "DELETE FROM puun_alkio
                      WHERE yhtio = '$kukarow[yhtio]'
                      AND laji    = 'Tuote'
                      AND liitos  = '$tuoteno'";
             pupe_query($query);
          }
          else {
            $query = "DELETE FROM $taulu
                      WHERE yhtio = '$kukarow[yhtio]'
                      AND $sarake = '$tuoteno'";
            pupe_query($query);
          }
        }
        $lask++;
      }
      else {
        is_log(t("TUOTENUMEROA EI LÖYDY")." $tuoteno");
      }
    }

    $unlokki = "UNLOCK TABLES";
    $res     = pupe_query($unlokki);
  }

  fclose($file);

  is_log(t("Valmis, poistettiin")." $lask ".t("tuotetta")."!");
  $tee = "";
}
elseif ($tee == "file") {
  echo "<font class='error'>".t("Edellämainitut viat pitää korjata ennenkuin voidaan jatkaa")."!!!<br>".t("Mitään ei päivitetty")."!!!<br><br>";
  $tee = "";
}


if ($tee == "" and $php_cli === false) {

  echo "<form method='post' name='sendfile' enctype='multipart/form-data'>

      <table>

      <tr>
        <td class='back' colspan='2'><br><font class='message'>".t("Sisäänlue tiedostosta")."</font><hr></td>
      </tr>

      <tr>
        <th colspan='2'>".t("Tabulaattorilla eroteltu tekstitiedosto").". ".t("Tiedoston sarakkeet").":</th>
      </tr>

      <tr>
        <td>".t("Tuotenumero")."</td>
      </tr>

      <tr>
        <th>".t("Valitse tiedosto").":</th>
        <td><input name='userfile' type='file'></td>
      </tr>

      <tr>
        <td class='back' colspan='2'><br><font class='message'>".t("Tai syötä tuotenumero")."</font><hr></td>
      </tr>

      <tr>
        <th>".t("Tuotenumero").":</th>
        <td><input type='text' name='tuoteno' size='25'></td>
      </tr>
      </table>
      <br>
      <input type='hidden' name='tee' value='file'>
      <input type='submit' value='".t("Poista tuotteet")."'>
      </form>";
}

require "inc/footer.inc";
