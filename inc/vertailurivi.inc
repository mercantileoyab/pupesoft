<?php
$ulos = "";
$jatko = 1; // oletetaan normaali k�sittely
$tyyppi = 1; // oletetaan rivin n�kyvyys

//yhti�t� ei n�ytet�
if (mysql_field_name($result, $i) == "yhtio") {
  $tyyppi = 0;
}