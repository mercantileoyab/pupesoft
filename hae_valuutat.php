<?php

require "inc/parametrit.inc";

echo "<font class='head'>".t("Valuuttakurssien p�ivitys")."<hr></font>";

$ch  = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
$xml = curl_exec($ch);

$xml = @simplexml_load_string($xml);

if ($xml !== FALSE) {

  echo t("Kurssien l�hde").": <a href='http://www.ecb.europa.eu/stats/exchange/eurofxref/html/index.en.html'>Reference rates European Central Bank</a><br><br>";

  $pvm = tv1dateconv($xml->Cube->Cube->attributes()->time);
  $pvm_mysql = $xml->Cube->Cube->attributes()->time;

  echo "<table>";
  echo "<tr><th>".t("Valuutta")."</th><th>".t("Kurssi")." $pvm</th><th>".t("Kurssikerroin")."</th>";

  foreach ($xml->Cube->Cube->Cube as $valuutta) {

    $valkoodi = (string) $valuutta->attributes()->currency;
    $kurssi   = (float)  $valuutta->attributes()->rate;

    echo "<tr><td>$valkoodi</td><td align='right'>$kurssi</td><td align='right'>".sprintf("%.9f", (1/$kurssi))."</td>";

    if ($tee == "PAIVITA") {
      $query = "UPDATE valuu SET
                kurssi      = round(1 / $kurssi, 9),
                muutospvm   = now(),
                muuttaja    = '$kukarow[kuka]'
                WHERE yhtio = '$kukarow[yhtio]'
                AND nimi    = '$valkoodi'";
      $result = pupe_query($query);

      if (mysql_affected_rows() != 0) {
        echo "<td class='back'>".t("Kurssi p�ivitetty").".</td>";
      }

      $query = "INSERT INTO valuu_historia (kotivaluutta, valuutta, kurssi, kurssipvm)
                VALUES ('EUR', '$valkoodi', round(1 / $kurssi, 9), '$pvm_mysql')
                  ON DUPLICATE KEY UPDATE kurssi = round(1 / $kurssi, 9)";
      $result = pupe_query($query);
    }

    echo "</tr>";
  }

  echo "</table>";

  if ($yhtiorow["valkoodi"] == "EUR") {
    echo "<br><form method='post'>
        <input type='hidden' name='tee' value='PAIVITA'>
        <input type='submit' value='".t("P�ivit� kurssit")."'>
        </form>";
  }
  else {
    echo "<font class='error'>".t("Vain EUR kotivaluutta")."!</font><br>";
  }
}
else {
  echo "<font class='error'>".t("Valuuttakurssien haku ep�onnistui")."!</font><br>";
}

require "inc/footer.inc";
