<?php
echo "<font class='head'>".t("Maksuvalmius")."</font><hr>";

$sel1 = $sel2 = $sel3 = $sel4 = $sel5 = $sel6 = "";
$kumulat = 0;

if ($aika == 'pv') {
  $sel1 = 'SELECTED';
}

if ($aika == 'vi') {
  $sel2 = "SELECTED";
}

if ($aika == 'kk') {
  $sel3 = "SELECTED";
}

if ($konserni != '') {
  $sel4 = "CHECKED";
}

if ($eilahetettyja != '') {
  $sel5 = "CHECKED";
}

if ($pankkisaldo != '') {
  $sel6 = "CHECKED";
}

echo "<form action = 'raportit.php' method='post'>
    <table><tr>
    <td>
    <input type = 'hidden' name = 'tee' value = '1'>
    <input type = 'hidden' name = 'toim' value = 'maksuvalmius'>
    ".t("Maksuvalmius")."</td><td>
    <select name='aika'>
                   <option value = 'pv' $sel1>".t("P�iv�")."
                   <option value = 'vi' $sel2>".t("Viikko")."
                   <option value = 'kk' $sel3>".t("Kuukausi")."
    </select></td>";

if ($yhtiorow["konserni"] != "") {
  echo "<tr><td>".t("Konserni")."</td>
    <td><input type = 'checkbox' name = 'konserni' $sel4></td></tr>";
}

echo "<tr><td>".t("�l� n�yt� l�hetettyj�")."</td>
    <td><input type = 'checkbox' name = 'eilahetettyja' $sel5></td></tr>";

echo "<tr><td>".t("K�ty� pankkitilin maksulimitti� alkusaldona")."</td>
       <td><input type = 'checkbox' name = 'pankkisaldo' $sel6></td></tr>";

echo "</table><br><input type = 'submit' value = '".t("N�yt�")."'></form><br>";

if ($aika != '') {

  // Tehd��n alkusiivous!
  $query = "SELECT konserni
            FROM yhtio
            WHERE yhtio = '$kukarow[yhtio]'";
  $result = pupe_query($query);

  if (mysql_num_rows($result) == 1) {
    $trow = mysql_fetch_assoc($result);
  }
  else {
    echo t("Yhti�it� l�ytyi monta tai ei lainkaan! Virhe!");
    exit;
  }

  // Poistetaan vanhat tapahtumat
  $query = "DELETE
            FROM maksu
            WHERE (konserni = '$trow[konserni]' or yhtio = '$kukarow[yhtio]')
            AND tyyppi != 'MU'";
  $result = pupe_query($query);

  // Lis�t��n uudet
  // Onko meill� konsernin�k�kulma??
  if ($konserni == 'on') {
    $query = "SELECT yhtio, konserni, nimi
              FROM yhtio
              WHERE konserni  = '$trow[konserni]'
              and konserni   != ''";
    $result = pupe_query($query);

    if (mysql_num_rows($result) < 2) {
      echo t("Pyysit konsernin�k�kulmaa, mutta yritys ei ole konsernin osa").".<br>";
      exit;
    }
    else {
      echo "<table><tr><th>".t("Konserniyritykset").":</th></tr>";

      while ($yrow = mysql_fetch_assoc($result)) {
        echo "<tr><td>$yrow[nimi]</td></tr>";
      }

      echo "</table><br>";

      mysql_data_seek($result, 2);
    }

    while ($yrow = mysql_fetch_assoc($result)) {
      //Myyntireskontra
      $query = "SELECT if(kapvm > now(), kapvm,erpcm) olmapvm, sum(summa-saldo_maksettu) summa, count(*)
                FROM lasku
                WHERE yhtio    = '$yrow[yhtio]'
                and lasku.tila = 'U'
                and mapvm      = '0000-00-00'
                GROUP BY 1";
      $yresult = pupe_query($query);

      while ($trow = mysql_fetch_assoc($yresult)) {
        $query = "INSERT into maksu values (
                  '$yrow[yhtio]',
                  '$yrow[konserni]',
                  '$kukarow[kuka]',
                  '$trow[olmapvm]',
                  'MY',
                  '$trow[summa]',
                  'Myyntilaskuja',
                  '',
                  '')";
        $xresult = pupe_query($query);
      }

      //Ostoreskontra
      //  Ei listata maksuun l�hetettyj� aineistoja, luotetaan ett� ne on maksettu
      if ($eilahetettyja != "") {
        $tilat = "'H', 'M', 'P'";
      }
      else {
        $tilat = "'H', 'M', 'P', 'Q'";
      }

      $query = "SELECT olmapvm, -1 * sum(summa * valuu.kurssi) summa, count(*)
                FROM lasku, valuu, yhtio
                WHERE lasku.yhtio  = '$yrow[yhtio]'
                and valuu.yhtio    = '$yrow[yhtio]'
                and lasku.valkoodi = valuu.nimi
                and lasku.tila     in ($tilat)
                GROUP BY olmapvm";
      $yresult = pupe_query($query);

      while ($trow = mysql_fetch_assoc($yresult)) {
        $query = "INSERT into maksu values (
                  '$yrow[yhtio]',
                  '$yrow[konserni]',
                  '$kukarow[kuka]',
                  '$trow[olmapvm]',
                  'OS',
                  '$trow[summa]',
                  'Ostolaskuja',
                  '',
                  '')";
        $xresult = pupe_query($query);
      }
    }
  }
  else {
    $inyhtio = $kukarow['yhtio'];
    $inkonserni = "";

    //Myyntireskontra
    $query = "SELECT if(kapvm > now(), kapvm, erpcm) olmapvm, sum(summa-saldo_maksettu) summa, count(*)
              FROM lasku
              WHERE lasku.yhtio = '$kukarow[yhtio]'
              AND lasku.tila    = 'U'
              AND mapvm         = '0000-00-00'
              GROUP BY 1";
    $result = pupe_query($query);

    while ($trow = mysql_fetch_assoc($result)) {
      $query = "INSERT into maksu values (
                '$kukarow[yhtio]',
                 '',
                '$kukarow[kuka]',
                '$trow[olmapvm]',
                'MY',
                '$trow[summa]',
                'Myyntilaskuja',
                '',
                '')";
      $xresult = pupe_query($query);
    }

    //Ostoreskontra
    //  Ei listata maksuun l�hetettyj� aineistoja, luotetaan ett� ne on maksettu
    if ($eilahetettyja != "") {
      $tilat = "'H', 'M', 'P'";
    }
    else {
      $tilat = "'H', 'M', 'P', 'Q'";
    }

    $query = "SELECT olmapvm, -1 * sum(summa * valuu.kurssi) summa, count(*)
              FROM lasku
              JOIN valuu ON (valuu.yhtio = lasku.yhtio and lasku.valkoodi = valuu.nimi)
              WHERE lasku.yhtio = '$kukarow[yhtio]'
              AND lasku.tila    in ($tilat)
              GROUP BY olmapvm";
    $result = pupe_query($query);

    while ($trow = mysql_fetch_assoc($result)) {
      $query = "INSERT into maksu values (
                '$kukarow[yhtio]',
                 '',
                '$kukarow[kuka]',
                '$trow[olmapvm]',
                'OS',
                '$trow[summa]',
                'Ostolaskuja',
                '',
                '')";
      $xresult = pupe_query($query);
    }
  }

  if ($aika == 'pv') {
    $tapa = 'tapvm';
  }
  if ($aika == 'vi') {
    $tapa = "YEARWEEK(tapvm, 3)";
  }
  if ($aika == 'kk') {
    $tapa = "left(tapvm,7)";;
  }

  $query = "SELECT $tapa Aika,
            sum(if(tyyppi='MY',summa,0)) Myyntires,
            sum(if(tyyppi='OS',summa,0)) Ostores,
            sum(if(tyyppi='MU',-1 * summa,0)) Muu,
            sum(if(tyyppi='MU',-1 * summa,summa)) Yhteensa
            FROM maksu
            WHERE yhtio  = '$kukarow[yhtio]'
            and maksettu <> 1
            GROUP BY Aika ASC WITH ROLLUP";
  $result = pupe_query($query);

  echo "<table><tr>";
  echo "<th>".t("Jakso")."</th>";
  echo "<th>".t("Aika")."</th>";
  echo "<th>".t("Myyntireskontra")."</th>";
  echo "<th>".t("Ostoreskontra")."</th>";
  echo "<th>".t("Muu")."</th>";
  echo "<th>".t("Yhteens�")."</th>";
  echo "<th>".t("Kumulatiivinen")."</th>";
  echo "</tr>";

  // Pankkitilien saldo
  if (!empty($pankkisaldo)) {
    $query = "SELECT sum(maksulimitti) saldo_nyt
              FROM yriti
              WHERE yhtio  = '$kukarow[yhtio]'
              AND valkoodi = '$yhtiorow[valkoodi]'";
    $yres = pupe_query($query);
    $yrow = mysql_fetch_assoc($yres);

    echo "<tr>";
    echo "<td colspan='6'>".t("Pankkitilin maksulimitti").":</td>";
    echo "<td class='text-right'>{$yrow['saldo_nyt']}</td>";
    echo "</tr>";

    $kumulat = $yrow['saldo_nyt'];
  }

  while ($trow = mysql_fetch_assoc($result)) {
    echo "<tr>";

    if ($trow["Aika"] != "") {
      $class = "";

      if ($aika == 'pv') {
        echo "<td>".t("P�iv�").": ";
        echo "<td class='text-right'>".tv1dateconv($trow["Aika"])."</td>";
      }

      if ($aika == 'vi') {
        echo "<td>".t("Viikko").": ";
        echo "<td>".substr($trow["Aika"], 4, 2)." - ".substr($trow["Aika"], 0, 4)."</td>";
      }

      if ($aika == 'kk') {
        echo "<td>".t("Kuukausi").": ";
        echo "<td>".substr($trow["Aika"], 5, 2)." - ".substr($trow["Aika"], 0, 4)."</td>";
      }
    }
    else {
      $class = " tumma";
      echo "<td class='$class'>".t("Yhteens�").": ";
      echo "<td class='$class'></td>";
    }

    if ($aika == 'pv' and !empty($trow["Aika"])) {
      echo "<td class='text-right{$class}'><a href = '${palvelin2}myyntires/myyntilaskuhaku.php?tee=M&pvm=$trow[Aika]&laskuntyyppi=A&lopetus=$PHP_SELF////tee=$tee//toim=$toim//aika=$aika//konserni=$konserni//eilahetettyja=$eilahetettyja//pankkisaldo=$pankkisaldo'>$trow[Myyntires]</a></td>";
      echo "<td class='text-right{$class}'><a href = 'raportit.php?toim=laskuhaku&tee=M&pvm=$trow[Aika]&lopetus=$PHP_SELF////tee=$tee//toim=$toim//aika=$aika//konserni=$konserni//eilahetettyja=$eilahetettyja//pankkisaldo=$pankkisaldo'>$trow[Ostores]</a></td>";
    }
    else {
      echo "<td class='text-right{$class}'>$trow[Myyntires]</td>";
      echo "<td class='text-right{$class}'>$trow[Ostores]</td>";
    }

    echo "<td class='text-right{$class}'>$trow[Muu]</td>";
    echo "<td class='text-right{$class}'>$trow[Yhteensa]</td>";

    if (!empty($pankkisaldo)) {
      $kumulat += $trow['Yhteensa'];
    }

    $miinus = "";

    if ($kumulat < 0) {
      $miinus = " error";
    }

    echo "<td class='text-right{$class}{$miinus}'>".sprintf("%.2f", $kumulat)."</td>";

    echo "</tr>";

    if (empty($pankkisaldo)) {
      $kumulat += $trow['Yhteensa'];
    }
  }

  echo "</table>";
}
