<?php

$otsikko     = 'Yhteensopivuus tuotteen lis�tiedot';
$otsikko_nappi   = 'Yhteensopivuus tuotteen lis�tieto';

// HUOM HUOM: eth�n muuta n�it� ilman, ett� korjaat my�s yllapito.php:ss� iframen lukitse_avaimeen muuttujat
$kentat   = "tunnus, otsikko, arvo, jarjestys, kieli, if(status='','".t("Aktiivinen")."',if(status='L','".t("Aktiivinen")." (".t("lukittu").")','".t("Poistettu")."')) status, if(nakyvyys='','".t("Kyll�")."',if(nakyvyys='K', '".t("Kyll�")." (".t("ei otsikkoa").")', '".t("Ei")."')) nakyvyys";
$hakukentat = "tunnus, otsikko, arvo, jarjestys, kieli, yhteensopivuus_tuote_tunnus";
$jarjestys   = "jarjestys, otsikko, arvo, tunnus";