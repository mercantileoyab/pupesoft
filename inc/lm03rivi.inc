<?php

//print "<pre>";

$ulos = "LM03";                        // 01 Aineistotunnus
$ulos .= sprintf('%1.1s', substr($laskutapahtuma, 0, 1));        // 02 Tietuetunnus (1 = maksutapahtuma, 2 = erittelytapahtuma)
$ulos .= sprintf('%1.1s', substr($laskutapahtuma, 1, 1));        // 03 Tapahtumalaji (0 = maksu, 2 = hyvitys)
$ulos .= sprintf('%-14.14s', $lmp_mt_4kentta);        // 04 Sampon speksiss� Maksajan tilinumero / Nordean speksiss� "varalla"
$ulos .= sprintf('%-30.30s', $laskunimi1);          // 05 Saajan tarkenne 1
$ulos .= sprintf('%-20.20s', $laskunimi2);          // 06 Saajan tarkenne 2
$ulos .= sprintf('%-20.20s', $laskunimi3);          // 07 Saajan tarkenne 3
$ulos .= sprintf('%-14.14s', $laskutilno);          // 08 Saajan tilinumero
$ulos .= sprintf('%3.3s', '');                // 09 Sampon speksiss� Maksunaihe / Nordean Speksiss� "varalla"
$ulos .= sprintf('%1.1s', $laskutyyppi);          // 10 Viestityyppi (Viestityypit: 1, 2, 5, 6, 7)
$ulos .= sprintf('%-70.70s', $laskuviesti);          // 11 Viesti
$ulos .= sprintf('%2.2s', '');                // 12 Varalla
$ulos .= sprintf('%02d', substr($luonti['year'], 2, 2));  // 13 Sampon speksiss� Er�p�iv� vv / Nordean Speksiss� "varalla"
$ulos .= sprintf('%02d', $luonti['mon']);          // 13 Sampon speksiss� Er�p�iv� kk / Nordean Speksiss� "varalla"
$ulos .= sprintf('%02d', $luonti['mday']);          // 13 Sampon speksiss� Er�p�iv� pp / Nordean Speksiss� "varalla"
$ulos .= sprintf('%012d', round($laskusumma * 100, 0));    // 14 Rahasumma
$ulos .= sprintf('%1.1s', '');                // 15 Varalla
$ulos .= sprintf('%4.4s', '');                // 16 Varalla
$ulos .= sprintf('%12.12s', '');              // 17 Varalla
$ulos .= sprintf('%-20.20s', $laskusis1);          // 18 Sis�inen viite
$ulos .= sprintf('%-20.20s', $laskusis2);          // 19 Kustannuspaikka
$ulos .= sprintf('%5.5s', '');                // 20 Sampon speksiss� Postinumero (Pakollinen maksuosoituksissa)    HUOM: Kent�n tyyppi N maksutapahtuma-tietueelle (tietuetunnus 1), mutta kentt� on "varalla" sek� tyyppi� AN erittelytapahtuma-tietueessa (tietuetunnus 2) / Nordean Speksiss� "varalla"
$ulos .= sprintf('%10.10s', '');              // 21 Sampon speksiss� Postitoimipaikka (Pakollinen maksuosoituksissa)  HUOM: Kentt� on "varalla" erittelytapahtuma-tietueessa (tietuetunnus 2) / Nordean Speksiss� "varalla"
$ulos .= sprintf('%30.30s', '');              // 22 Sampon speksiss� Saajan jakeluosoite                HUOM: Kentt� on "varalla" erittelytapahtuma-tietueessa (tietuetunnus 2) / Nordean Speksiss� "varalla"

//print strlen($ulos) . "/" . $ulos;
//print "</pre>";

fputs($toot, $ulos."\r\n");
