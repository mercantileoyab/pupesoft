<?php

/*
Tehty ja validoitu kayttaen spekseja:

URL: http://www.iso20022.org/catalogue_of_unifi_messages.page
Msg ID: pain.001.001.03
Message Name: CustomerCreditTransferInitiationV03
*/

function sanitize_sepa_string($string) {
    $replacements = array(
        'Ä' => 'A', 'ä' => 'a',
        'Ö' => 'O', 'ö' => 'o',
        'Å' => 'A', 'å' => 'a',
        'Ü' => 'U', 'ü' => 'u',
        'Õ' => 'O', 'õ' => 'o',
        'Š' => 'S', 'š' => 's',
        'Ž' => 'Z', 'ž' => 'z',
        // Add other necessary replacements here
    );
    
    $string = strtr($string, $replacements);
    
    // Allow only basic characters: a-z, A-Z, 0-9, spaces, and specific punctuation
    // SEPA allowed characters: a-z A-Z 0-9 / - ? : ( ) . , ' + space
    return preg_replace('/[^a-zA-Z0-9\/\-\?:\(\)\.,\'\+ ]/', '', $string);
}

function sepa_header() {
  global $xml, $pain, $yhtiorow;

  $xmlstr  = '<?xml version="1.0" encoding="UTF-8"?>';
  $xmlstr .= '<Document ';
  $xmlstr .= 'xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03" ';
  $xmlstr .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
  $xmlstr .= 'xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03 pain.001.001.03.xsd">';
  $xmlstr .= '</Document>';

  $xml = new SimpleXMLElement($xmlstr);

  $pain = $xml->addChild('CstmrCdtTrfInitn');
  $GrpHdr = $pain->addChild('GrpHdr');                                                  // GroupHeader
  $GrpHdr->addChild('MsgId', date('Y-m-d')."T".date('H:i:s')."-".mt_rand(100,999));     // MessageIdentification
  $GrpHdr->addChild('CreDtTm', date('Y-m-d')."T".date('H:i:s'));                        // CreationDateTime
  $GrpHdr->addChild('NbOfTxs', 0);                                                      // NumberOfTransactions
  $GrpHdr->addChild('CtrlSum', 0);                                                      // ControlSum

  $InitgPty = $GrpHdr->addChild('InitgPty', '');                                        // InitiatingParty
  $InitgPty->addChild('Nm', sprintf("%-1.70s", $yhtiorow['nimi']));                     // Name
  
  $Id = $InitgPty->addChild('Id');
  $OrgId = $Id->addChild('OrgId');
  $Othr = $OrgId->addChild('Othr');
  $Othr->addChild('Id', str_replace(array(' ', '-'), '', $yhtiorow['ytunnus']));
  $SchmeNm = $Othr->addChild('SchmeNm');
  $SchmeNm->addChild('Cd', 'BANK');
}

function sepa_paymentinfo($laskurow) {
  global $xml, $pain, $PmtInf, $yhtiorow;

  $PmtInf = $pain->addChild('PmtInf');                                                  // PaymentInformation
  $PmtInfId = $PmtInf->addChild('PmtInfId', $laskurow['tunnus']);                       // PaymentInformationIdentification
  $PmtMtd = $PmtInf->addChild('PmtMtd', 'TRF');                                         // PaymentMethod
  $PmtInf->addChild('BtchBookg', 'true');                                               // BatchBooking
  
  $PmtInf->addChild('NbOfTxs', 0);
  $PmtInf->addChild('CtrlSum', 0);

  if ($laskurow["sepa"] === 'SEPA') {
    $PmtTpInf = $PmtInf->addChild('PmtTpInf');
    $SvcLvl = $PmtTpInf->addChild('SvcLvl');
    $SvcLvl->addChild('Cd', 'SEPA');
  }

  $ReqdExctnDt = $PmtInf->addChild('ReqdExctnDt', $laskurow['olmapvm']);                // RequestedExecutionDate

  $Dbtr = $PmtInf->addChild('Dbtr');                                                    // Debtor
  $Dbtr->addChild('Nm', sprintf("%-1.70s", $yhtiorow['nimi']));                         // Name
  
  $PstlAdr = $Dbtr->addChild('PstlAdr');                                                // PostalAddress
  $PstlAdr->addChild('Ctry', $yhtiorow['maa']);
  $PstlAdr->addChild('AdrLine', sprintf("%-1.70s", $yhtiorow['osoite']));               // AddressLine
  $PstlAdr->addChild('AdrLine', sprintf("%-1.70s", $yhtiorow['maa']."-".$yhtiorow['postino']." ".$yhtiorow['postitp']));

  $Id = $Dbtr->addChild('Id');                                                          // Identification
  $OrgId = $Id->addChild('OrgId');                                                      // OrganisationIdentification

  if ($laskurow["yriti_asiakastunnus"] != "0" and $laskurow["yriti_asiakastunnus"] != "") {
    $Othr = $OrgId->addChild('Othr');
    $Othr->addChild('Id', $laskurow["yriti_asiakastunnus"]);
    $SchmeNm = $Othr->addChild('SchmeNm');
    $SchmeNm->addChild('Cd', 'BANK');
  }
  else {
    $Othr = $OrgId->addChild('Othr');
    $Othr->addChild('Id', str_replace(array(' ', '-'), '', $yhtiorow['ytunnus']));
    $SchmeNm = $Othr->addChild('SchmeNm');
    $SchmeNm->addChild('Cd', 'BANK');
  }

  $DbtrAcct = $PmtInf->addChild('DbtrAcct');                                            // DebtorAccount
  $Id = $DbtrAcct->addChild('Id');                                                      // Identification
  $Id->addChild('IBAN', str_replace(' ', '', $laskurow['yriti_iban']));                 // IBAN
  
  $DbtrAgt = $PmtInf->addChild('DbtrAgt');                                              // DebtorAgent
  $FinInstnId  = $DbtrAgt->addChild('FinInstnId');                                      // FinancialInstitutionIdentification
  
  if (!empty($laskurow['yriti_bic'])) {
    $FinInstnId->addChild('BIC', $laskurow['yriti_bic']);
  } else {
    $Othr = $FinInstnId->addChild('Othr');
    $Othr->addChild('Id', 'NOTPROVIDED');
  }

  $ChrgBr = $PmtInf->addChild('ChrgBr', 'SLEV');                                        // ChargeBearer
}

function sepa_credittransfer($laskurow, $popvm_nyt, $netotetut_rivit = '') {
  global $xml, $pain, $PmtInf, $yhtiorow, $kukarow;

  $CdtTrfTxInf = $PmtInf->addChild('CdtTrfTxInf', '');                                  // CreditTransferTransaction Information
  $PmtId = $CdtTrfTxInf->addChild('PmtId', '');                                         // PaymentIdentification
  $InstrId = $PmtId->addChild('InstrId', "{$laskurow['tunnus']}-".preg_replace("/[^0-9]/", "", $popvm_nyt));      // Instruction Id
  $EndToEndId = $PmtId->addChild('EndToEndId', "{$laskurow['tunnus']}-".preg_replace("/[^0-9]/", "", $popvm_nyt));  // EndToEndIdentification

  $Amt = $CdtTrfTxInf->addChild('Amt', '');                                             // Amount

  if ($laskurow['alatila'] != 'K') {
    $InstdAmt = $Amt->addChild('InstdAmt', sprintf("%.02f", round($laskurow['summa'], 2)));              // InstructedAmount
  }
  else {
    $InstdAmt = $Amt->addChild('InstdAmt', sprintf("%.02f", round($laskurow['summa'] - $laskurow['kasumma'], 2)));  // InstructedAmount
  }
  $InstdAmt->addAttribute('Ccy', $laskurow['valkoodi']);                                // Currency

  $CdtrAgt = $CdtTrfTxInf->addChild('CdtrAgt', '');
  $FinInstnId = $CdtrAgt->addChild('FinInstnId', '');
  if (!empty($laskurow['swift'])) {
    $FinInstnId->addChild('BIC', $laskurow['swift']);
  }

  $Cdtr = $CdtTrfTxInf->addChild('Cdtr', '');                                           // Creditor

  $creditorName = (trim($laskurow['pankki_haltija']) != '') 
      ? $laskurow['pankki_haltija'] 
      : trim($laskurow['nimi']." ".$laskurow['nimitark']);

  // Replace & or &amp; with "and" BEFORE sanitization
  $creditorName = str_replace(array('&amp;', '&'), 'and', $creditorName);

  // Sanitize name if not domestic (FI)
  if (strtolower($laskurow['maa']) != 'fi') {
      $creditorName = sanitize_sepa_string($creditorName);
  }

  $Nm = $Cdtr->addChild('Nm', sprintf("%-1.70s", $creditorName)); // Name
  
  $PstlAdr = $Cdtr->addChild('PstlAdr', '');                                            // PostalAddress

  if ($laskurow['yriti_bic'] == 'HELSFIHH') {
    $_osoite  = trim($laskurow['osoite'])  == '' ? ''  : $laskurow['osoite'];
    $_postino = trim($laskurow['postino']) == '' ? ''  : $laskurow['postino'];
    $_postitp = trim($laskurow['postitp']) == '' ? ''  : $laskurow['postitp'];
    $_erotin = "";
  }
  else {
    $_osoite  = trim($laskurow['osoite'])  == '' ? '-'  : $laskurow['osoite'];
    $_postino = trim($laskurow['postino']) == '' ? '-'  : $laskurow['postino'];
    $_postitp = trim($laskurow['postitp']) == '' ? '-'  : $laskurow['postitp'];
    $_erotin = "-";
  }

  $_maa = trim($laskurow['maa']) == '' ? 'FI' : $laskurow['maa'];

  // Sanitize address fields if not domestic
  if (strtolower($laskurow['maa']) != 'fi') {
      $_osoite = sanitize_sepa_string($_osoite);
      $_postitp = sanitize_sepa_string($_postitp);
  }

  $PstlAdr->addChild('StrtNm', sprintf("%-1.70s", $_osoite)); // StreetName
  $PstlAdr->addChild('PstCd', sprintf("%-1.16s", "{$_maa}{$_erotin}{$_postino}")); // PostCode
  $PstlAdr->addChild('TwnNm', sprintf("%-1.35s", $_postitp)); // TownName
  $PstlAdr->addChild('Ctry', sprintf("%-2.2s", $_maa)); // Country
  
  $PstlAdr->addChild('AdrLine', sprintf("%-1.70s", $_osoite)); // AddressLine
  $PstlAdr->addChild('AdrLine', sprintf("%-1.70s", "{$_maa}{$_erotin}{$_postino}{$_erotin}{$_postitp}"));
  
  $CdtrAcct = $CdtTrfTxInf->addChild('CdtrAcct', '');                                   // CreditorAccount
  $Id = $CdtrAcct->addChild('Id', '');                                                  // Identification
  if (tarkista_sepa($laskurow["iban_maa"]) !== FALSE) {
    $Id->addChild('IBAN', str_replace(' ', '', $laskurow['ultilno']));                  // IBAN
  }
  else {
    $Othr = $Id->addChild('Othr');
    $Othr->addChild('Id', $laskurow['ultilno']);                                        // Othr
  }

  $rmtInfNeeded = false;
  
  if ($yhtiorow['maa'] == 'EE') {
    $rmtInfNeeded = true;
  }
  elseif (strlen(trim($laskurow["viite"])) > 0) {
    $rmtInfNeeded = true;
  }
  elseif ($laskurow['viesti'] != "") {
    $rmtInfNeeded = true;
  }
  if ($netotetut_rivit != "") {
    $rmtInfNeeded = true;
  }

  if ($rmtInfNeeded) {
    $RmtInf = $CdtTrfTxInf->addChild('RmtInf', '');                                       // RemittanceInformation

    if ($netotetut_rivit != "") {
      $ustrd_parts = array();
      $query = "SELECT *
                FROM lasku
                WHERE yhtio = '$kukarow[yhtio]'
                AND tunnus  in ($netotetut_rivit)";
      $result = pupe_query($query);

      while ($nettorow = mysql_fetch_assoc($result)) {
        $id_str = "";
        $prefix = ($nettorow["summa"] < 0) ? "Cdt Note" : "Inv";
        
        if (strlen(trim($nettorow["viite"])) > 0) {
          $id_str = "Ref " . trim($nettorow["viite"]);
        } elseif (strlen(trim($nettorow["laskunro"])) > 0) {
          $id_str = "$prefix " . trim($nettorow["laskunro"]);
        } elseif ($nettorow["viesti"] != "") {
          $id_str = "Msg " . trim($nettorow["viesti"]);
        } else {
          $id_str = "Doc " . $nettorow["tunnus"];
        }
        
        $ustrd_parts[] = $id_str;
      }
      
      $full_ustrd = implode(', ', $ustrd_parts);
      $RmtInf->addChild('Ustrd', sprintf("%-1.140s", $full_ustrd));
    }
    else {
      // Logic for EE: If reference exists, use <Strd> (ISO standard), else <Ustrd>
      if ($yhtiorow['maa'] == 'EE') {
        if (strlen(trim($laskurow["viite"])) > 0) {
          $Strd = $RmtInf->addChild('Strd', '');                                            
          $CdtrRefInf = $Strd->addChild('CdtrRefInf', '');                                  
          
          $Tp = $CdtrRefInf->addChild('Tp', '');
          $CdOrPrtry = $Tp->addChild('CdOrPrtry', '');
          $CdOrPrtry->addChild('Cd', 'SCOR');                                               
          
          $CdtrRefInf->addChild('Ref', sprintf("%-1.35s", $laskurow['viite']));             
        } else {
          // Fallback to unstructured if no viite
          if (strlen(trim($laskurow["laskunro"])) > 0) {
            $reference_number_and_message = $laskurow['laskunro'];
            if ($laskurow['viesti'] != "" && $laskurow['viesti'] != $laskurow['viite']) {
                $reference_number_and_message .= " " . $laskurow['viesti'];
            }
          } elseif ($laskurow['viesti'] != "") {
            $reference_number_and_message = $laskurow['viesti'];
          } else {
            $reference_number_and_message = "Invoice " . $laskurow['tunnus'];
          }
          $Ustrd = $RmtInf->addChild('Ustrd', sprintf("%-1.140s", $reference_number_and_message));
        }
      }
      else {
        // Standard/Foreign logic
        if (strlen(trim($laskurow["viite"])) > 0) {
          $Strd = $RmtInf->addChild('Strd', '');                                            // Structured
          $CdtrRefInf = $Strd->addChild('CdtrRefInf', '');                                  // CreditorReferenceInformation
          
          $Tp = $CdtrRefInf->addChild('Tp', '');
          $CdOrPrtry = $Tp->addChild('CdOrPrtry', '');
          $CdOrPrtry->addChild('Cd', 'SCOR');                                               // Code
          
          $CdtrRefInf->addChild('Ref', sprintf("%-1.35s", $laskurow['viite']));             // Reference
        }
        elseif ($laskurow['viesti'] != "") {
          $viesti_content = $laskurow['viesti'];
          if (strtolower($laskurow['maa']) != 'fi') {
             $viesti_content = sanitize_sepa_string($viesti_content);
          }
          $Ustrd = $RmtInf->addChild('Ustrd', sprintf("%-1.140s", $viesti_content));    // Unstructured
        }
      }
    }
  }
}

if (isset($_POST["tee"])) {
  if ($_POST["tee"] == 'lataa_tiedosto') $lataa_tiedosto = 1;
  if ($_POST["kaunisnimi"] != '') $_POST["kaunisnimi"] = str_replace("/", "", $_POST["kaunisnimi"]);
}

require "inc/parametrit.inc";
require "inc/pankkiyhteys_functions.inc";

$tee = empty($tee) ? '' : $tee;

if ($tee == "KIRJOITAKOPIO" || isset($vanhatee) && $vanhatee == "KIRJOITAKOPIO") {
  $pankkitiedostot_polku = "/tmp";
}
elseif (!empty($maksuaineiston_siirto[$kukarow["yhtio"]]["local_dir"])) {
  $pankkitiedostot_polku = trim($maksuaineiston_siirto[$kukarow["yhtio"]]["local_dir"]);
}
elseif (!empty($pankkitiedostot_polku) != "") {
  $pankkitiedostot_polku = trim($pankkitiedostot_polku);
}
else {
  $pankkitiedostot_polku = $pupe_root_polku."/dataout";
}

$pankkitiedostot_polku = rtrim($pankkitiedostot_polku, '/').'/';

if ($tee == "lataa_tiedosto") {
  if (isset($pankkifilenimi)) {
    readfile($pankkitiedostot_polku.basename($pankkifilenimi));
  }
  elseif (isset($tmpfilenimi)) {
    readfile("/tmp/".basename($tmpfilenimi));
  }

  exit;
}

echo "<font class='head'>".t("SEPA-maksuaineisto")."</font><hr>";

if (!is_writable($pankkitiedostot_polku)) {
  virhe("Pankkitiedostopolku virheellinen!");
  $tee = "";
}

if ($tee == "laheta_pankkiin") {
  if (empty($salasana)) {
    virhe("Salasana taytyy antaa!");
    $tee = "virhe";
  }
  elseif (!hae_pankkiyhteys_ja_pura_salaus($pankkiyhteys_tunnus, $salasana)) {
    virhe("Antamasi salasana on vaara!");
    $tee = "virhe";
  }

  if (empty($pankkiyhteys_tunnus)) {
    virhe("Pankkiyhteystunnus katosi!");
    $tee = "virhe";
  }

  $pankkiyhteys_tiedosto_full = "{$pankkitiedostot_polku}{$pankkiyhteys_tiedosto}";

  if (!is_readable($pankkiyhteys_tiedosto_full)) {
    virhe("Maksuaineisto ei ole luettavissa!");
    $tee = "virhe";
  }
}

if ($tee == "laheta_pankkiin") {
  $_xml = file_get_contents($pankkiyhteys_tiedosto_full);
  $_data = base64_encode($_xml);

  $params = array(
    "pankkiyhteys_tunnus"   => $pankkiyhteys_tunnus,
    "pankkiyhteys_salasana" => $salasana,
    "file_type"             => "NDCORPAYS",
    "maksuaineisto"         => $_data,
  );

  $vastaus = sepa_upload_file($params);

  if ($vastaus) {
    viesti("Maksuaineisto lahetetty, vastaus pankista:");

    echo "<br/>";
    echo "<table>";
    echo "<tbody>";
    foreach ($vastaus as $key => $value) {
      echo "<tr>";
      echo "<td>{$key}</td>";
      echo "<td>{$value}</td>";
      echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    echo "<br/><br/>";

    $pankkiyhteys_tiedosto = "";
  }

  $tee = "";
}

$pankkitili_tunnus = empty($pankkitili_tunnus) ? 0 : (int) $pankkitili_tunnus;

if ($yhtiorow["pankkitiedostot"] == "E") {
  echo "<font class='error'>";
  echo t("SEPA-aineston voi luoda ainoastaan per pankki tai kaikki pankit yhteen tiedostoon.");
  echo "<br>";
  echo t("Tarkista pankkitiedostot -yhtion parametrti.");
  echo "</font>";

  $tee = "virhe";
}

if ($tee == "KIRJOITAKOPIO") {
  $lisa = " and lasku.tunnus in ($poimitut_laskut) ";
}
else {
  $lisa = " and lasku.tila = 'P' and lasku.maksaja = '$kukarow[kuka]' ";
}

$sepa_maat_array = tarkista_sepa('', 'K');
$sepamaat        = "'".implode("','", $sepa_maat_array)."'";

$haku_query = "SELECT lasku.*,
               if(lasku.ultilno_maa != '', lasku.ultilno_maa, lasku.maa) iban_maa,
               if((lasku.ultilno_maa != ''
                 AND lasku.ultilno_maa in ($sepamaat))
                 OR lasku.maa          in ($sepamaat), 'SEPA', '') sepa,
               yriti.iban yriti_iban,
               yriti.bic yriti_bic,
               yriti.asiakastunnus yriti_asiakastunnus,
               date_format(lasku.popvm, '%d.%m.%y.%H.%i.%s') popvm_dmy
               FROM lasku
               INNER JOIN valuu ON (valuu.yhtio = lasku.yhtio
                AND valuu.nimi         = lasku.valkoodi)
               INNER JOIN yriti ON (yriti.yhtio = lasku.yhtio
                AND yriti.tunnus       = lasku.maksu_tili
                AND yriti.kaytossa     = '')
               WHERE lasku.yhtio       = '{$kukarow["yhtio"]}'
               {$lisa}
               ORDER BY maksu_tili, olmapvm, sepa, ultilno";
$result = pupe_query($haku_query);

if ($tee == "") {

  $_num = mysql_num_rows($result);

  echo "<br>";
  echo "<font class='message'>".t("Sinulla on")." {$_num} ".t("laskua poimittuna").".</font>";
  echo "<br><br>";

  if (SEPA_PANKKIYHTEYS and $_num > 0) {
    $_temp = mysql_fetch_assoc($result);
    $pankkitili_tunnus = $_temp['yriti_tunnus'];
    mysql_data_seek($result, 0);
  }

  $virheita = 0;

  while ($laskurow = mysql_fetch_assoc($result)) {

    if (tarkista_iban($laskurow["ultilno"]) != $laskurow["ultilno"] and tarkista_sepa($laskurow["iban_maa"]) !== FALSE) {
      echo "<font class='error'>Laskun tilinumero ei ole oikeellinen IBAN tilinumero, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) $laskurow[ultilno]</font><br>";
      $virheita++;
      continue;
    }
    elseif (tarkista_bban($laskurow["ultilno"]) === FALSE) {
      echo "<font class='error'>Laskun tilinumero ei ole oikeellinen BBAN tilinumero, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) $laskurow[ultilno]</font><br>";
      $virheita++;
      continue;
    }

    if ($laskurow["ultilno"] == "") {
      echo "<font class='error'>Laskulta puuttuu tilinumero, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) </font><br>";
      $virheita++;
      continue;
    }

    if (tarkista_iban($laskurow["yriti_iban"]) == "") {
      echo "<font class='error'>Yrityksen pankkitili $laskurow[yriti_iban] ei ole oikeellinen IBAN tilinumero, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) </font><br>";
      $virheita++;
      continue;
    }

    if (tarkista_bic($laskurow["yriti_bic"]) === FALSE) {
      echo "<font class='error'>Yrityksen pankkitilin $laskurow[yriti_iban] BIC on virheellinen, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) </font><br>";
      $virheita++;
      continue;
    }

    if (tarkista_bic($laskurow["swift"]) === FALSE) {
      echo "<font class='error'>Laskun BIC ei ole oikeellinen, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) $laskurow[swift]</font><br>";
      $virheita++;
      continue;
    }

    if ($laskurow["summa"] == 0) {
      echo "<font class='error'>Laskulta puuttuu summa, laskua ei voida lisata aineistoon! $laskurow[nimi] ($laskurow[summa] $laskurow[valkoodi]) </font><br>";
      $virheita++;
      continue;
    }

  }

  if (!is_dir($pankkitiedostot_polku) or !is_writable($pankkitiedostot_polku)) {
    echo "<font class='error'>".t("Kansioissa ongelmia").": $pankkitiedostot_polku</font><br>";
    $virheita++;
  }

  if (mysql_num_rows($result) > 0 and $virheita == 0) {
    echo "<form name = 'valinta' method='post'>";
    echo "<input type = 'hidden' name = 'tee' value = 'KIRJOITA'>";
    echo "<input type = 'hidden' name = 'pankkitili_tunnus' value = '{$pankkitili_tunnus}'>";
    echo "<input type = 'submit' value = '".t("Tee maksuaineistot")."'>";
    echo "</form>";
  }
}

if ($tee == "KIRJOITA" or $tee == "KIRJOITAKOPIO") {

  if (mysql_num_rows($result) > 0) {

    $popvm_row = mysql_fetch_assoc($result);

    if ($tee == "KIRJOITAKOPIO") {
      $popvm_nyt = $popvm_row["popvm"];
      $popvm_dmy = $popvm_row["popvm_dmy"];
    }
    else {
      $popvm_nyt = date("Y-m-d H:i:s");
      $popvm_dmy = date("d.m.y.H.i.s");
    }

    if (strtoupper($yhtiorow['maa']) == 'EE' and substr($popvm_row['yriti_iban'], 0, 2) == "EE") {
      $kaunisnimi = "EESEPA-$kukarow[yhtio]-".$popvm_dmy.".xml";
    }
    else {
      $kaunisnimi = "SEPA-$kukarow[yhtio]-".$popvm_dmy.".xml";
    }

    $toot = fopen($pankkitiedostot_polku.$kaunisnimi, "w+");

    if (!$toot) {
      echo t("En saanut tiedostoa auki! Tarkista polku")." $pankkitiedostot_polku$kaunisnimi !";
      exit;
    }

    echo "<br>";
    echo "<table>";
    echo "<tr>";
    echo "<th>".t("Tiedosto")."</th>";
    echo "<td>$kaunisnimi</td>";
    echo "</tr>";
  }
  else {
    echo "<font class='message'>".t("Sopivia laskuja ei loydy")."</font>";
    exit;
  }

  $tapahtuma_maara    = 0;
  $edpvm              = "0000-00-00";
  $edsepa             = "";
  $edtili             = "";
  $netotettava_laskut = array();
  $netotettava_summa  = array();

  $query = "SELECT maksu_tili, ultilno, olmapvm, valkoodi
            FROM lasku
            WHERE yhtio = '$kukarow[yhtio]'
            {$lisa}
            AND summa   < 0
            GROUP BY maksu_tili, ultilno, olmapvm, valkoodi";
  $result = pupe_query($query);

  while ($laskurow = mysql_fetch_assoc($result)) {

    $query = "SELECT lasku.tunnus laskutunnus,
              if(lasku.alatila = 'K', summa - kasumma, summa) maksettavasumma, yriti.bic
              FROM lasku
              INNER JOIN yriti ON yriti.tunnus = lasku.maksu_tili AND yriti.yhtio = lasku.yhtio
              WHERE lasku.yhtio    = '$kukarow[yhtio]'
              {$lisa}
              AND ultilno    = '$laskurow[ultilno]'
              AND lasku.valkoodi   = '$laskurow[valkoodi]'
              AND maksu_tili = '$laskurow[maksu_tili]'
              AND olmapvm    = '$laskurow[olmapvm]'
              ORDER BY if(summa < 0, 1, 2), summa DESC";
    $nettolaskures = pupe_query($query);

    $nettosumma_yhteensa = 0;
    $nettolaskuja_yhteensa = 0;
    $nordea_hyvityslaskuja_yhteensa = 0;

    $nettolaskujen_tunnukset = "";
    $summa_plussalla = false;

    while ($nettolaskurow = mysql_fetch_assoc($nettolaskures)) {
      if (!$summa_plussalla) {
        $nettosumma_yhteensa += $nettolaskurow["maksettavasumma"];
        $nettolaskujen_tunnukset .= "$nettolaskurow[laskutunnus],";
        $nettolaskuja_yhteensa++;
      }

      if ($nettolaskurow["maksettavasumma"] < 0 and $nettolaskurow['bic'] == 'NDEAFIHH') {
        $nordea_hyvityslaskuja_yhteensa++;
      }

      if ($nettosumma_yhteensa > 0) {
        $summa_plussalla = true;
      }
    }

    $nettolaskujen_tunnukset = substr($nettolaskujen_tunnukset, 0, -1);

    if ($nettosumma_yhteensa < 0) {

      echo "<tr>";
      echo "<th>".t("Virhe")."</th>";
      echo "<td><font class='error'>Hyvityslaskujen netotus jaa miinukselle! Poimi lisaa laskuja tilille $laskurow[valkoodi] $laskurow[ultilno], $laskurow[olmapvm]</font></td>";
      echo "</tr>";
      echo "</table>";

      require "inc/footer.inc";
      exit;
    }

    if ($nettolaskuja_yhteensa > 999) {

      echo "<tr>";
      echo "<th>".t("Virhe")."</th>";
      echo "<td><font class='error'>Hyvityslaskujen netotus koostuu yli 999 tapahtumasta! SEPA aineisto ei tue nain isoja netotuksia! $laskurow[valkoodi] $laskurow[ultilno], $laskurow[olmapvm]</font></td>";
      echo "</tr>";
      echo "</table>";

      require "inc/footer.inc";
      exit;
    }

    if ($nordea_hyvityslaskuja_yhteensa > 9) {

      echo "<tr>";
      echo "<th>".t("Virhe")."</th>";
      echo "<td><font class='error'>" . t("Poimittu aineisto voi sisaltaa vain 9 hyvityslaskua yhdelle toimittajalle") . ".</font></td>";
      echo "</tr>";
      echo "</table>";

      require "inc/footer.inc";
      exit;
    }

    $netotettava_laskut[] = $nettolaskujen_tunnukset;
    $netotettava_summa[]  = $nettosumma_yhteensa;
  }

  sepa_header();

  foreach ($netotettava_laskut as $i => $tunnukset) {
    $query = "SELECT lasku.*, if(lasku.ultilno_maa != '', lasku.ultilno_maa, lasku.maa) iban_maa,
              if((lasku.ultilno_maa != ''
                AND lasku.ultilno_maa in ($sepamaat))
                OR lasku.maa          in ($sepamaat), 'SEPA', '') sepa,
              yriti.iban yriti_iban, yriti.bic yriti_bic, yriti.asiakastunnus yriti_asiakastunnus
              FROM lasku
              JOIN yriti ON (yriti.yhtio = lasku.yhtio
                AND yriti.tunnus      = lasku.maksu_tili
                AND yriti.kaytossa    = '')
              WHERE lasku.yhtio       = '$kukarow[yhtio]'
              AND lasku.tunnus        in ($tunnukset)
              LIMIT 1";
    $result = pupe_query($query);
    $nettorow = mysql_fetch_assoc($result);

    $nettorow["viesti"] = (trim($nettorow["viesti"]) == "") ? $nettorow["laskunro"] : $nettorow["viesti"];
    $nettorow["viesti"] = (trim($nettorow["viesti"]) == "") ? $nettorow["viite"] : $nettorow["viesti"];

    $nettorow["viite"]    = '';
    $nettorow["alatila"]  = '';
    $nettorow["summa"]    = $netotettava_summa[$i];

    sepa_paymentinfo($nettorow);
    sepa_credittransfer($nettorow, $popvm_nyt, $tunnukset);
    $tapahtuma_maara++;

    if ($tee == "KIRJOITA") {
      $query = "UPDATE lasku
                SET tila = 'Q',
                popvm       = '$popvm_nyt'
                WHERE yhtio = '$kukarow[yhtio]'
                AND tunnus  in ($tunnukset)";
      $uresult = pupe_query($query);
    }
  }

  $netotetut_laskut = implode(",", $netotettava_laskut);

  if ($netotetut_laskut != "") {
    $lisa .= " and lasku.tunnus not in ($netotetut_laskut) ";
  }

  $haku_query = "SELECT lasku.*,
                 if(lasku.ultilno_maa != '', lasku.ultilno_maa, lasku.maa) iban_maa,
                 if((lasku.ultilno_maa != ''
                   AND lasku.ultilno_maa in ($sepamaat))
                   OR lasku.maa          in ($sepamaat), 'SEPA', '') sepa,
                 yriti.iban yriti_iban,
                 yriti.bic yriti_bic,
                 yriti.asiakastunnus yriti_asiakastunnus,
                 date_format(lasku.popvm, '%d.%m.%y.%H.%i.%s') popvm_dmy
                 FROM lasku
                 INNER JOIN valuu ON (valuu.yhtio = lasku.yhtio
                  AND valuu.nimi         = lasku.valkoodi)
                 INNER JOIN yriti ON (yriti.yhtio = lasku.yhtio
                  AND yriti.tunnus       = lasku.maksu_tili
                  AND yriti.kaytossa     = '')
                 WHERE lasku.yhtio       = '{$kukarow["yhtio"]}'
                 {$lisa}
                 ORDER BY maksu_tili, olmapvm, sepa, ultilno";
  $result = pupe_query($haku_query);

  while ($laskurow = mysql_fetch_assoc($result)) {

    if ($laskurow['laskunro'] != 0 and $laskurow['laskunro'] != $laskurow['viesti'] and $yhtiorow['maa'] != 'EE') {
      $laskurow['viesti'] = (trim($laskurow['viesti']) == "") ? $laskurow['laskunro'] : $laskurow['viesti']." ".$laskurow['laskunro'];
    }

    if ($kukarow["yhtio"] == "kiko") {
      if ($edpvm != $laskurow['olmapvm'] or $edsepa != $laskurow['sepa']) {
        sepa_paymentinfo($laskurow);
        $edpvm  = $laskurow['olmapvm'];
        $edsepa = $laskurow['sepa'];
      }
    }
    else {
      if ($edpvm != $laskurow['olmapvm'] or $edtili != $laskurow['ultilno']) {
        sepa_paymentinfo($laskurow);
        $edpvm  = $laskurow['olmapvm'];
        $edtili = $laskurow['ultilno'];
      }
    }

    sepa_credittransfer($laskurow, $popvm_nyt);
    $tapahtuma_maara++;

    if ($tee == "KIRJOITA") {
      $query = "UPDATE lasku
                SET tila = 'Q',
                popvm       = '$popvm_nyt'
                WHERE yhtio = '$kukarow[yhtio]'
                AND tunnus  = '$laskurow[tunnus]'";
      $uresult = pupe_query($query);
    }
  }

  $total_txs = 0;
  $total_sum = 0.0;

  foreach ($pain->PmtInf as $pmtInf) {
      $group_txs = 0;
      $group_sum = 0.0;

      foreach ($pmtInf->CdtTrfTxInf as $tx) {
          $group_txs++;
          $val = (float) $tx->Amt->InstdAmt;
          $group_sum += $val;
      }

      $pmtInf->NbOfTxs = $group_txs;
      $pmtInf->CtrlSum = sprintf("%.02f", $group_sum);

      $total_txs += $group_txs;
      $total_sum += $group_sum;
  }

  $pain->GrpHdr->NbOfTxs = $total_txs;
  $pain->GrpHdr->CtrlSum = sprintf("%.02f", $total_sum);

  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  
  $xml_output = $xml->asXML();
  
  if (function_exists('mb_convert_encoding')) {
    $xml_output = mb_convert_encoding($xml_output, 'UTF-8', 'ISO-8859-1');
  } else {
    $xml_output = utf8_encode($xml_output);
  }

  $dom->loadXML($xml_output);
  fwrite($toot, $dom->saveXML());
  
  fclose($toot);

  libxml_use_internal_errors(true);

  $xml_virheet = "";
  $xml_domdoc = new DomDocument;
  $xml_file = $pankkitiedostot_polku.$kaunisnimi;
  $xml_schema = "$pupe_root_polku/datain/pain.001.001.03.xsd";

  $pankkiyhteys_tiedosto = $kaunisnimi;

  $xml_domdoc->Load($xml_file);

  if (!$xml_domdoc->schemaValidate($xml_schema)) {

    echo "<font class='message'>SEPA-aineistosta loytyi viela seuraavat virheet, aineisto saattaa hylkaantya pankissa!</font><br><br>";

    $all_errors = libxml_get_errors();

    foreach ($all_errors as $error) {
      echo "<font class='info'>$error->message</font><br>";
      $xml_virheet .= "$error->message\n";
    }

    echo "<br>";

    mail($yhtiorow['admin_email'], mb_encode_mimeheader($yhtiorow['nimi']." - SEPA Error", "ISO-8859-1", "Q"), $xml_virheet."\n", "From: ".mb_encode_mimeheader($yhtiorow["nimi"], "ISO-8859-1", "Q")." <$yhtiorow[postittaja_email]>\n", "-f $yhtiorow[postittaja_email]");
  }

  echo "<tr><th>".t("Tallenna aineisto")."</th>";
  echo "<form method='post' class='multisubmit'>";
  echo "<input type='hidden' name='tee' value='lataa_tiedosto'>";
  echo "<input type='hidden' name='kaunisnimi' value='$kaunisnimi'>";

  if ($tee == "KIRJOITAKOPIO") {
    echo "<input type='hidden' name='tmpfilenimi' value='".basename($kaunisnimi)."'>";
  }
  else {
    echo "<input type='hidden' name='pankkifilenimi' value='$kaunisnimi'>";
  }

  echo "<td><input type='submit' value='".t("Tallenna")."'></form></td>";
  echo "</tr>";
  echo "</table>";

  $y = $kukarow["yhtio"];
  if (isset(  $maksuaineiston_siirto[$y]["host"],
      $maksuaineiston_siirto[$y]["user"],
      $maksuaineiston_siirto[$y]["pass"],
      $maksuaineiston_siirto[$y]["path"],
      $maksuaineiston_siirto[$y]["type"],
      $maksuaineiston_siirto[$y]["file"],
      $maksuaineiston_siirto[$y]["local_dir"],
      $maksuaineiston_siirto[$y]["local_dir_ok"],
      $maksuaineiston_siirto[$y]["local_dir_error"])) {
    require "maksuaineisto_send.php";
    echo "<br><font class='message'>".t("Maksuaineisto siirretty pankkiyhteysohjelmaan").".</font>";
  }
}

if (SEPA_PANKKIYHTEYS and !empty($pankkiyhteys_tiedosto)) {
  $query = "SELECT pankkiyhteys.tunnus AS pankkiyhteys_tunnus
            FROM yriti
            INNER JOIN pankkiyhteys ON (pankkiyhteys.yhtio = yriti.yhtio
              AND pankkiyhteys.pankki = yriti.bic)
            WHERE yriti.yhtio         = '{$kukarow["yhtio"]}'
            AND yriti.tunnus          = {$pankkitili_tunnus}";
  $result = pupe_query($query);

  if (mysql_num_rows($result) == 1) {
    $row = mysql_fetch_assoc($result);

    echo "<br><br>";
    echo "<font class='message'>";
    echo t("Laheta maksuaineisto pankkiin");
    echo "</font>";
    echo "<hr>";

    echo "<form method='post' action='sepa.php'>";
    echo "<input type='hidden' name='tee' value='laheta_pankkiin'/>";
    echo "<input type='hidden' name='vanhatee' value='{$tee}'/>";
    echo "<input type='hidden' name='pankkitili_tunnus' value='{$pankkitili_tunnus}'/>";
    echo "<input type='hidden' name='pankkiyhteys_tunnus' value='{$row['pankkiyhteys_tunnus']}'/>";
    echo "<input type='hidden' name='pankkiyhteys_tiedosto' value='$pankkiyhteys_tiedosto'/>";

    echo "<table>";
    echo "<tr>";
    echo "<th>" . t("Tiedosto") . "</th>";
    echo "<td>{$pankkiyhteys_tiedosto}</td>";
    echo "</tr>";

    echo "<tr>";
    echo "<th><label for='salasana'>" . t("Syota pankkiyhteyden salasana") . "</label></th>";
    echo "<td><input type='password' name='salasana' id='salasana'/></td>";
    echo "</tr>";
    echo "</table>";

    echo "<br>";
    echo "<input type='submit' value='".t("Laheta aineisto pankkiin")."'>";
    echo "</form>";
  }
}
?>