<?php

if (isset($lukitse_laji) and $lukitse_laji != "") {
  $otsikko     = 'Avainsanan kieliversiot';
  $otsikko_nappi   = 'avainsanan kieliversio';
}
else {
  $otsikko     = 'Avainsanat';
  $otsikko_nappi   = 'avainsana';
}

// HUOM HUOM: eth�n muuta n�it� ilman, ett� korjaat my�s yllapito.php:ss� iframen lukitse_avaimeen muuttujat
$kentat   = 'tunnus, kieli, laji, selite, selitetark, selitetark_2, selitetark_3, jarjestys, perhe HIDDEN_perhe';
$hakukentat = 'tunnus, kieli, laji, selite, selitetark, selitetark_2, selitetark_3, jarjestys, perhe';

$jarjestys = 'kieli, laji, jarjestys, selite, selitetark, selitetark_2';

$mysqlaliasarray = array();

// T�ss� kohtaa aliakset suomeksi, t-funkkari on yllapito.php:ssa
$mysqlaliasarray['SAHKTILTUN']['selite']           = "Toimipaikka";
$mysqlaliasarray['SAHKTILTUN']['selitetark']       = "Toimittaja";
$mysqlaliasarray['SAHKTILTUN']['selitetark_2']     = "Asiakasnumero";
$mysqlaliasarray['SAHKTILTUN']['selitetark_3']     = "K�ytt�j�tunnus";
$mysqlaliasarray['SAHKTILTUN']['selitetark_4']     = "Salasana";
$mysqlaliasarray['SAHKTILTUN']['selitetark_5']     = "Toimittajanumero";

$mysqlaliasarray['VALMISTUSLINJA']['selite']         = "Linjanumero";
$mysqlaliasarray['VALMISTUSLINJA']['selitetark']     = "Linjan nimi";
$mysqlaliasarray['VALMISTUSLINJA']['selitetark_2']   = "P�iv�kapasiteetti sekunneissa";

$mysqlaliasarray['TOIMVAHVISTUS']['selite']         = "Toimitusvahvistuksen tiedoston nimi";
$mysqlaliasarray['TOIMVAHVISTUS']['selitetark']    = "Toimitusvahvistuksen nimi";
$mysqlaliasarray['TOIMVAHVISTUS']['selitetark_2']    = "Toimitusvahvistus tyyppi";

$mysqlaliasarray['OSTOTIL_TILTYYP']['selite']       = "Ostotilauksen tilaustyyppi";
$mysqlaliasarray['OSTOTIL_TILTYYP']['selitetark']   = "Ostotilauksen tilaustyypin nimi";
$mysqlaliasarray['OSTOTIL_TILTYYP']['selitetark_2'] = "Automaattituloutuksen esto";

$mysqlaliasarray['INVEN_LAJI']['selitetark_2']     = "Varastonmuutos tili";
$mysqlaliasarray['INVEN_LAJI']['selitetark_3']     = "Mobiili-inventoinnin oletuslaji";
$mysqlaliasarray['INVEN_LAJI']['selitetark_4']     = "Oletusselite";

$mysqlaliasarray['KERAYSERA_TK']['selite']         = "Ker��j�";
$mysqlaliasarray['KERAYSERA_TK']['selitetark']     = "Varasto";

$mysqlaliasarray['RELEX_TUOTEPAR']['selite']         = "Tuote-kentt�";
$mysqlaliasarray['RELEX_TUOTEPAR']['selitetark']     = "Operaattori";
$mysqlaliasarray['RELEX_TUOTEPAR']['selitetark_2']   = "Arvo";

$mysqlaliasarray['ASIAKASHINNASTO']['selite']         = "K�ytet��nk� myyntihinnan tilalla myym�l�hintaa";
