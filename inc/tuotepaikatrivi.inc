<?php

$ulos = "";
$jatko = 1; // oletetaan normaali k�sittely
$tyyppi = 1; // oletetaan rivin n�kyvyys


if (mysql_field_name($result, $i) == "saldo" or
  mysql_field_name($result, $i) == "saldoaika" or
  mysql_field_name($result, $i) == "inventointiaika" or
  mysql_field_name($result, $i) == "inventointipoikkeama" or
  mysql_field_name($result, $i) == "oletus" or
  mysql_field_name($result, $i) == "tuoteno") {
  $tyyppi = 2;
}



if (mysql_field_name($result, $i) == "tunnus" or
  mysql_field_name($result, $i) == "yhtio") {
  $tyyppi = 0;
}

if (mysql_field_name($result, $i) == "tuoteno" and $uusi != 0) {
  $tyyppi = 1;
}

if (mysql_field_name($result, $i) == "tyyppi") {
  $ulos = "<td><select name='$nimi' ".js_alasvetoMaxWidth($nimi, 400).">";

  $sel = array();
  $sel[$trow[$i]] = " selected";

  $ulos .= "<option value = ''>".t("Normaali")."</option>";
  $ulos .= "<option value = 'L'{$sel['L']}>".t("Lava")."</option>";
  $ulos .= "<option value = 'S'{$sel['S']}>".t("Suuntalava")."</option>";
  $ulos .= "</select></td>";

  $jatko = 0;
}
