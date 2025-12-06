// MatPlanning.gs - Blok/Mat verdeling en planning
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalMatten, getAantalBlokken)
 * - PouleUtils.js (leesGewichtsklassenEnWedstrijden, leesPouleDetails)
 */

/**
 * Maakt een Blok/Mat verdelingsschema voor handmatige indeling
 */
function genereerBlokMatIndeling() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // Controleer of benodigde tabbladen bestaan
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  
  if (!poulesSheet) {
    ui.alert(
      'Fout',
      'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Controleer of Blok/Mat verdeling bestaat
  let sheet;
  if (ss.getSheetByName("Blok/Mat verdeling")) {
    sheet = ss.getSheetByName("Blok/Mat verdeling");
    // Wis de inhoud
    sheet.clear();
    // Wis alle datavalidaties
    const totalRows = sheet.getMaxRows();
    const totalCols = sheet.getMaxColumns();
    if (totalRows > 0 && totalCols > 0) {
      sheet.getRange(1, 1, totalRows, totalCols).setDataValidation(null);
    }
  } else {
    // Maak een nieuw blad aan
    sheet = ss.insertSheet("Blok/Mat verdeling");
  }
  
  // Haal configuratiegegevens op
  const aantalMatten = getAantalMatten();
  const aantalBlokken = getAantalBlokken();
  
  // Stel basiskolombreedtes in
  sheet.setColumnWidth(1, 200); // Gewichtsklasse kolom
  sheet.setColumnWidth(2, 100); // Wedstrijden kolom
  sheet.setColumnWidth(3, 80);  // Blok kolom
  
  // Vul de bovenste tabel links in (gewichtsklassen en bloktoewijzing)
  sheet.getRange(1, 1, 1, 8).merge();
  sheet.getRange(1, 1).setValue("BLOK/MAT VERDELING - HANDMATIGE INDELING")
    .setFontWeight("bold")
    .setFontSize(14)
    .setHorizontalAlignment("center")
    .setBackground("#D9D9D9");
  
  // Rechtertabel opzetten met blokken naast elkaar - Headers voor blokken op rij 2
  const startRechts = 5;
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = startRechts + (blok - 1) * 3;
    
    // Blok header
    sheet.getRange(2, blokKolom, 1, 3).merge();
    sheet.getRange(2, blokKolom).setValue(`Blok ${blok}`)
      .setFontWeight("bold")
      .setBackground("#D9D9D9")
      .setHorizontalAlignment("center");
    
    // Kolom headers voor elke blok
    sheet.getRange(3, blokKolom).setValue("Poule");
    sheet.getRange(3, blokKolom + 1).setValue("Wedstr");
    sheet.getRange(3, blokKolom + 2).setValue("Mat");
    sheet.getRange(3, blokKolom, 1, 3).setFontWeight("bold").setBackground("#F3F3F3");
    
    // Kolombreedte instellen
    sheet.setColumnWidth(blokKolom, 90);     // Poule kolom, iets breder voor lft klassen
    sheet.setColumnWidth(blokKolom + 1, 60); // Wedstrijden kolom
    sheet.setColumnWidth(blokKolom + 2, 50); // Mat kolom
  }
  
  // Gewichtsklassetabel één rij lager, op rij 3
  sheet.getRange(3, 1).setValue("Gewichtsklasse");
  sheet.getRange(3, 2).setValue("Wedstrijden");
  sheet.getRange(3, 3).setValue("Blok");
  sheet.getRange(3, 1, 1, 3).setFontWeight("bold").setBackground("#D9D9D9");
  
  // Haal gewichtsklassen en wedstrijden op uit PouleIndeling
  const gewichtsklassenData = leesGewichtsklassenEnWedstrijden(poulesSheet);
  
  if (gewichtsklassenData.length === 0) {
    ui.alert(
      'Geen gewichtsklassen gevonden',
      'Er zijn geen gewichtsklassen gevonden in het PouleIndeling tabblad.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Vul gewichtsklassen in (vanaf rij 4)
  for (let i = 0; i < gewichtsklassenData.length; i++) {
    const row = i + 4;
    sheet.getRange(row, 1).setValue(gewichtsklassenData[i].gewichtsklasse);
    sheet.getRange(row, 2).setValue(gewichtsklassenData[i].wedstrijden);
  }
  
  // Bepaal de laatste rij van de gewichtsklassentabel
  const laatsteRijLinks = gewichtsklassenData.length + 3;
  
  // Datavalidatie voor blok-kolom links
  const blokValidatie = SpreadsheetApp.newDataValidation()
    .requireNumberBetween(1, aantalBlokken)
    .setAllowInvalid(false)
    .setHelpText(`Vul een bloknummer in tussen 1 en ${aantalBlokken}`)
    .build();
  
  // Voeg validatie toe aan de blok-kolom
  sheet.getRange(4, 3, gewichtsklassenData.length, 1).setDataValidation(blokValidatie);
  
  // Gebruik een vaste startpositie voor onderste tabellen, om ervoor te zorgen dat ze zichtbaar zijn
  const startOnderLinks = laatsteRijLinks + 2; // Extra ruimte toevoegen
  
  // Maak onderste tabel links (totalen per blok) - Zorg ervoor dat deze zichtbaar is
  sheet.getRange(startOnderLinks, 1).setValue("Blok");
  sheet.getRange(startOnderLinks, 2).setValue("Totaal wedstrijden");
  sheet.getRange(startOnderLinks, 1, 1, 2).setFontWeight("bold").setBackground("#D9D9D9");

  // Maak zeker dat deze rij zichtbaar is
  sheet.setRowHeight(startOnderLinks, 25);

  // In de genereerBlokMatIndeling functie, bij het opzetten van de linkertabel onder:

for (let i = 1; i <= aantalBlokken; i++) {
  const blokRij = startOnderLinks + i;
  sheet.getRange(blokRij, 1).setValue(i);

  // Centreer het bloknummer (kolom A)
  sheet.getRange(blokRij, 1).setHorizontalAlignment("center");
  sheet.getRange(blokRij, 1).setVerticalAlignment("middle");

  // SUMIF formule met ABSOLUTE referenties ($ tekens) en puntkomma
  sheet.getRange(blokRij, 2).setFormula(`=SUMIF($C$4:$C$${laatsteRijLinks};${i};$B$4:$B$${laatsteRijLinks})`);

  // Centreer de getallen in de blok-totalen (kolom B)
  sheet.getRange(blokRij, 2).setHorizontalAlignment("center");
  sheet.getRange(blokRij, 2).setVerticalAlignment("middle");

  // Maak zeker dat deze rij zichtbaar is
  sheet.setRowHeight(blokRij, 25);
}

  // Totaalrij onderaan
  const totaalRij = startOnderLinks + aantalBlokken + 1;

// Ook het woord "TOTAAL" in de linkertabel centreren
sheet.getRange(totaalRij, 1).setHorizontalAlignment("center");
sheet.getRange(totaalRij, 1).setVerticalAlignment("middle");
  sheet.getRange(totaalRij, 1).setValue("TOTAAL");
  sheet.setRowHeight(totaalRij, 25);

  // Simpelere SUM formule
  sheet.getRange(totaalRij, 2).setFormula(`=SUM(B${startOnderLinks + 1}:B${totaalRij-1})`);
  
  // Centreer en maak vetgedrukt het eindtotaal in de linkertabel
  sheet.getRange(totaalRij, 2).setHorizontalAlignment("center");
  sheet.getRange(totaalRij, 2).setVerticalAlignment("middle");
  sheet.getRange(totaalRij, 2).setFontWeight("bold");

  sheet.getRange(totaalRij, 1, 1, 2)
    .setFontWeight("bold")
    .setBackground("#D9EAD3");
  
  // Maak onderste tabel rechts (blok/mat matrix)
  const startOnderRechts = startOnderLinks;
  const matrixStart = startRechts;
  
  // Headers - Zorg ervoor dat deze zichtbaar zijn
  sheet.getRange(startOnderRechts, matrixStart).setValue("Blok/Mat");
  sheet.getRange(startOnderRechts, matrixStart).setFontWeight("bold").setBackground("#D9D9D9");
  
  for (let i = 1; i <= aantalMatten; i++) {
    sheet.getRange(startOnderRechts, matrixStart + i).setValue(i);
    sheet.getRange(startOnderRechts, matrixStart + i).setFontWeight("bold").setBackground("#D9D9D9");
    sheet.setColumnWidth(matrixStart + i, 60);
  }
  
  // Extra kolom voor rijtotalen
  sheet.getRange(startOnderRechts, matrixStart + aantalMatten + 1).setValue("Blok totaal");
  sheet.getRange(startOnderRechts, matrixStart + aantalMatten + 1).setFontWeight("bold").setBackground("#D9D9D9");
  sheet.setColumnWidth(matrixStart + aantalMatten + 1, 90);
  
  // Blok rijen
  for (let i = 1; i <= aantalBlokken; i++) {
    const blokRij = startOnderRechts + i;
    // Bloknummer
    sheet.getRange(blokRij, matrixStart).setValue(i);
    sheet.getRange(blokRij, matrixStart).setFontWeight("bold").setBackground("#D9D9D9");
    
    // Lege cellen voor elke mat - NIET initialiseren met nullen
    for (let j = 1; j <= aantalMatten; j++) {
      sheet.getRange(blokRij, matrixStart + j).setValue("");
      sheet.getRange(blokRij, matrixStart + j).setHorizontalAlignment("center");
      sheet.getRange(blokRij, matrixStart + j).setVerticalAlignment("middle");
    }
    
    // Lege cel voor rijtotaal - GEEN formule
    sheet.getRange(blokRij, matrixStart + aantalMatten + 1).setValue("");
    sheet.getRange(blokRij, matrixStart + aantalMatten + 1).setHorizontalAlignment("center");
    sheet.getRange(blokRij, matrixStart + aantalMatten + 1).setVerticalAlignment("middle");
    sheet.getRange(blokRij, matrixStart + aantalMatten + 1).setFontWeight("bold");
    sheet.getRange(blokRij, matrixStart + aantalMatten + 1).setBackground("#F3F3F3");
  }
  
  // Totaalrij onderaan
  sheet.getRange(totaalRij, matrixStart).setValue("TOTAAL");
  sheet.getRange(totaalRij, matrixStart).setFontWeight("bold").setBackground("#D9EAD3");
  
  // BELANGRIJK: LAAT DE KOLOMTOTALEN (MAT-TOTALEN) LEEG
  for (let j = 1; j <= aantalMatten; j++) {
    sheet.getRange(totaalRij, matrixStart + j).setValue("");
  }
  
  // Eindtotaal kolom in matrix - GEEN formule
  sheet.getRange(totaalRij, matrixStart + aantalMatten + 1).setValue("");
  sheet.getRange(totaalRij, matrixStart + aantalMatten + 1).setHorizontalAlignment("center");
  sheet.getRange(totaalRij, matrixStart + aantalMatten + 1).setVerticalAlignment("middle");
  sheet.getRange(totaalRij, matrixStart + aantalMatten + 1).setFontWeight("bold");
  sheet.getRange(totaalRij, matrixStart + aantalMatten + 1).setBackground("#D9EAD3");
  
  // Bereken het totaal aantal wedstrijden uit de PouleIndeling
  let totaalWedstrijden = 0;
  for (const gewichtsklasse of gewichtsklassenData) {
    totaalWedstrijden += gewichtsklasse.wedstrijden;
  }
  
  // Voeg extra statistieken toe rechts van de matrix
  const statKolom = matrixStart + aantalMatten + 3; // 1 kolom ruimte tussen matrix en stats
  sheet.setColumnWidth(statKolom, 150);     // O - eerste deel van label
  sheet.setColumnWidth(statKolom + 1, 150); // P - tweede deel van label
  sheet.setColumnWidth(statKolom + 2, 100); // Q - waarden

  // Totaal wedstrijden in het toernooi - Header
  sheet.getRange(startOnderRechts, statKolom, 1, 2).merge();
  sheet.getRange(startOnderRechts, statKolom).setValue("Statistieken");
  sheet.getRange(startOnderRechts, statKolom).setFontWeight("bold").setBackground("#D9D9D9");

  // Totaal wedstrijden
  sheet.getRange(startOnderRechts + 1, statKolom, 1, 2).merge();
  sheet.getRange(startOnderRechts + 1, statKolom).setValue("Totaal wedstrijden:");
  sheet.getRange(startOnderRechts + 1, statKolom + 2).setValue(totaalWedstrijden);

  // Gemiddeld aantal wedstrijden per blok (Totaal/6)
  const wedstrijdenPerBlok = Math.round(totaalWedstrijden / aantalBlokken);
  sheet.getRange(startOnderRechts + 2, statKolom, 1, 2).merge();
  sheet.getRange(startOnderRechts + 2, statKolom).setValue(`Per blok (${totaalWedstrijden}/${aantalBlokken}):`);
  sheet.getRange(startOnderRechts + 2, statKolom + 2).setValue(wedstrijdenPerBlok);

  // Gemiddeld aantal wedstrijden per mat per blok (Totaal/42)
  const wedstrijdenPerMatPerBlok = Math.round(totaalWedstrijden / (aantalBlokken * aantalMatten));
  const totaalMats = aantalBlokken * aantalMatten;
  sheet.getRange(startOnderRechts + 3, statKolom, 1, 2).merge();
  sheet.getRange(startOnderRechts + 3, statKolom).setValue(`Per mat/blok (${totaalWedstrijden}/${totaalMats}):`);
  sheet.getRange(startOnderRechts + 3, statKolom + 2).setValue(wedstrijdenPerMatPerBlok);

  // Opmaak voor de statistieken
  sheet.getRange(startOnderRechts + 1, statKolom, 3, 2).setBackground("#F3F3F3");
  sheet.getRange(startOnderRechts + 1, statKolom + 2, 3, 1).setHorizontalAlignment("center").setFontWeight("bold").setBackground("#F3F3F3");
  
  // Opmaak en kleuren
  sheet.getRange(4, 1, gewichtsklassenData.length, 3).setHorizontalAlignment("center");
  
  // Bevries de bovenste rijen
  sheet.setFrozenRows(3);
  
  // Scroll naar boven om alles zichtbaar te maken
  sheet.setActiveRange(sheet.getRange(1, 1));
  
  // Sla belangrijke locaties op voor gebruik in onEdit trigger
  PropertiesService.getScriptProperties().setProperty('matrixStartRij', startOnderRechts.toString());
  PropertiesService.getScriptProperties().setProperty('matrixStartKolom', matrixStart.toString());
  PropertiesService.getScriptProperties().setProperty('aantalMatten', aantalMatten.toString());
  
  // Installeer de triggers voor het verwerken van mat toewijzingen en blok toewijzingen
  const triggers = ScriptApp.getProjectTriggers();
  let hasBlokMatEditTrigger = false;
  let hasBlokToewijzingTrigger = false;
  
  for (const trigger of triggers) {
    if (trigger.getHandlerFunction() === "onBlokMatEditVerbeterd") {
      hasBlokMatEditTrigger = true;
    }
    if (trigger.getHandlerFunction() === "onBlokToewijzing") {
      hasBlokToewijzingTrigger = true;
    }
  }
  
  if (!hasBlokMatEditTrigger) {
    ScriptApp.newTrigger("onBlokMatEditVerbeterd")
      .forSpreadsheet(ss)
      .onEdit()
      .create();
  }
  
  if (!hasBlokToewijzingTrigger) {
    ScriptApp.newTrigger("onBlokToewijzing")
      .forSpreadsheet(ss)
      .onEdit()
      .create();
  }
  
  ui.alert(
    'Blok/Mat verdeling aangemaakt',
    'De Blok/Mat verdelingstabel is aangemaakt. U kunt nu gewichtsklassen toewijzen aan blokken. Gebruik daarna het menu-item "Vul poules in blokken" om de poules in te delen.',
    ui.ButtonSet.OK
  );
}

/**
 * Vult poules in de toegewezen blokken op basis van de gewichtsklassen
 */
function vulPoulesInBlokken() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName("Blok/Mat verdeling");
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  
  if (!sheet || !poulesSheet) {
    ui.alert('Fout', 'De nodige tabbladen werden niet gevonden.', ui.ButtonSet.OK);
    return;
  }
  
  // Lees gewichtsklasse toewijzingen
  const gewichtsklasseToewijzingen = [];
  let laatsteGewichtsklasseRij = 0;
  
  // Zoek gewichtsklassen en hun blok-toewijzingen
  for (let i = 4; i <= 100; i++) {
    const gewichtsklasse = sheet.getRange(i, 1).getValue();
    const wedstrijden = sheet.getRange(i, 2).getValue();
    const blokNr = sheet.getRange(i, 3).getValue();
    
    if (gewichtsklasse && wedstrijden) {
      laatsteGewichtsklasseRij = i;
      
      if (blokNr && !isNaN(blokNr)) {
        gewichtsklasseToewijzingen.push({
          gewichtsklasse: gewichtsklasse,
          wedstrijden: wedstrijden,
          blokNr: blokNr
        });
      }
    } else if (i > 10 && !gewichtsklasse) {
      break; // Stop zoeken na eerste lege rij
    }
  }
  
  if (gewichtsklasseToewijzingen.length === 0) {
    ui.alert('Geen toewijzingen', 'Wijs eerst gewichtsklassen toe aan blokken.', ui.ButtonSet.OK);
    return;
  }
  
  const aantalMatten = getAantalMatten();
  const aantalBlokken = getAantalBlokken();
  
  // Lees de matrix locatie uit de properties om te weten waar de onderste tabel begint
  const props = PropertiesService.getScriptProperties();
  const matrixStartRij = parseInt(props.getProperty('matrixStartRij')) || 0;
  
  // Wis alleen de poule-gedeelten, NIET de onderste tabel of de headers
  // Bereken maximum rij om te wissen (niet verder dan de laatste gewichtsklasse en niet in de onderste tabel)
  const maxRowToWipe = Math.min(laatsteGewichtsklasseRij, matrixStartRij - 2);
  
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    // Wis alleen de inhoud tussen rij 4 en maxRowToWipe, behoud de headers!
    if (maxRowToWipe > 4) {
      sheet.getRange(4, blokKolom, maxRowToWipe - 3, 3).clearContent();
      sheet.getRange(4, blokKolom + 2, maxRowToWipe - 3, 1).setDataValidation(null);
    }
  }
  
  // Vul poules in blokken
  const pouleData = leesPouleDetails(poulesSheet);
  const poulesByGewicht = {};
  
  for (const poule of pouleData) {
    if (!poulesByGewicht[poule.gewichtsklasse]) poulesByGewicht[poule.gewichtsklasse] = [];
    poulesByGewicht[poule.gewichtsklasse].push(poule);
  }
  
  const eersteVrijeRijPerBlok = {};
  for (let blok = 1; blok <= aantalBlokken; blok++) eersteVrijeRijPerBlok[blok] = 4;
  
  const matValidatie = SpreadsheetApp.newDataValidation()
    .requireNumberBetween(1, aantalMatten)
    .setAllowInvalid(false)
    .build();
  
  for (const {gewichtsklasse, blokNr} of gewichtsklasseToewijzingen) {
    const poules = poulesByGewicht[gewichtsklasse] || [];
    poules.sort((a, b) => a.pouleNr - b.pouleNr);
    
    if (poules.length === 0) continue;
    
    const blokKolom = 5 + (blokNr - 1) * 3;
    let rij = eersteVrijeRijPerBlok[blokNr];
    
    // Bepaal leeftijdsklasse code
    let leeftijdsklasseCode = "";
    if (gewichtsklasse.includes("Mini")) leeftijdsklasseCode = "M";
    else if (gewichtsklasse.includes("A-pup")) leeftijdsklasseCode = "A";
    else if (gewichtsklasse.includes("B-pup")) leeftijdsklasseCode = "B";
    else if (gewichtsklasse.includes("Dames")) leeftijdsklasseCode = "D";
    else if (gewichtsklasse.includes("Heren")) leeftijdsklasseCode = "H";
    
    // Extract gewichtscode
    let gewichtsCode = "";
    const gewichtsMatch = gewichtsklasse.match(/([+-]?\d+) kg/);
    if (gewichtsMatch) gewichtsCode = gewichtsMatch[1];
    
    // Vul poules in
    for (const poule of poules) {
      // Controleer of we niet te ver naar beneden gaan (bescherm de onderste tabel)
      if (rij >= matrixStartRij - 2) break;

      const pouleTitel = `${poule.pouleNr},${leeftijdsklasseCode}${gewichtsCode}`;

      sheet.getRange(rij, blokKolom).setValue(pouleTitel);
      sheet.getRange(rij, blokKolom + 1).setValue(poule.wedstrijden);
      
      sheet.getRange(rij, blokKolom + 2).setDataValidation(matValidatie);
      sheet.getRange(rij, blokKolom + 2).setBackground("#F3F3F3");
      
      rij++;
    }
    
    eersteVrijeRijPerBlok[blokNr] = rij;
  }
  
  // Centreer cellen
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    const laatsteRij = eersteVrijeRijPerBlok[blok];
    
    if (laatsteRij > 4) {
      sheet.getRange(4, blokKolom, laatsteRij - 4, 3).setHorizontalAlignment("center");
    }
  }
  
  ui.alert('Poules in blokken gevuld', 'U kunt nu poules toewijzen aan matten.', ui.ButtonSet.OK);
}

/**
 * Event handler voor het bewerken van de Blok/Mat verdeling
 * Deze functie start een herberekening van de matrix (rechterkant)
 * wanneer een matnummer wordt gewijzigd
 */
function onBlokMatEditVerbeterd(e) {
  const sheet = e.range.getSheet();
  
  if (sheet.getName() !== "Blok/Mat verdeling") {
    return;
  }
  
  const range = e.range;
  const row = range.getRow();
  const col = range.getColumn();
  
  // Controleer of dit een cel in een matkolom is (elke 3e kolom vanaf kolom 7)
  const isMatColumn = (col >= 7) && ((col - 7) % 3 === 0);
  if (!isMatColumn) {
    return;
  }
  
  // Toon een statusmelding
  SpreadsheetApp.getActive().toast("Mat wijziging gedetecteerd, herberekening wordt gestart...", "Even geduld", 3);
  
  // Voer een herberekening uit van alleen de matrix (rechterkant)
  herberekeningMatrixAlleen(sheet, false); // false = geen UI-dialoog tonen
  
  // Toon een bevestigingsmelding
  SpreadsheetApp.getActive().toast("Mat wijziging verwerkt en mattotalen bijgewerkt", "Voltooid", 2);
}

/**
 * Herberekent alleen de matrix op basis van alle poule/mat toewijzingen
 * De totalen in de linkertabel (berekend op basis van gewichtsklasse/blok toewijzingen) 
 * worden NIET gewijzigd.
 * 
 * @param {Sheet} sheet - Het werkblad (optioneel, wordt anders automatisch opgehaald)
 * @param {boolean} toonUI - Of UI-meldingen moeten worden getoond (default: true)
 */
function herberekeningMatrixAlleen(sheet, toonUI = true) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // Als geen sheet is meegegeven, haal het dan op
  if (!sheet) {
    sheet = ss.getSheetByName("Blok/Mat verdeling");
  }
  
  if (!sheet) {
    if (toonUI) ui.alert('Fout', 'Het Blok/Mat verdeling tabblad werd niet gevonden.', ui.ButtonSet.OK);
    return;
  }
  
  // Toon een statusmelding indien gewenst
  if (toonUI) {
    SpreadsheetApp.getActive().toast("Bezig met herberekenen van alle mattotalen...", "Herberekenen", 5);
  }
  
  // Haal de nodige parameters op
  const props = PropertiesService.getScriptProperties();
  const matrixStartRij = parseInt(props.getProperty('matrixStartRij')) || 0;
  const matrixStartKolom = parseInt(props.getProperty('matrixStartKolom')) || 0;
  const aantalMatten = getAantalMatten();
  const aantalBlokken = getAantalBlokken();
  
  if (matrixStartRij === 0 || matrixStartKolom === 0) {
    if (toonUI) ui.alert('Fout', 'Kon matrix-parameters niet ophalen.', ui.ButtonSet.OK);
    return;
  }
  
  // STAP 1: Bereid een matrix voor om de wedstrijden bij te houden
  const wedstrijdenMatrix = Array(aantalBlokken).fill().map(() => Array(aantalMatten).fill(0));
  
  // Scan alle blokken en poules om wedstrijden te tellen
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    const matKolom = blokKolom + 2;
    
    // Lees alle data voor dit blok in één keer
    const maxRijen = matrixStartRij - 4;
    if (maxRijen <= 0) continue;
    
    const blokData = sheet.getRange(4, blokKolom, maxRijen, 3).getValues();
    
    // Verwerk alle rijen
    for (let i = 0; i < blokData.length; i++) {
      const [poule, wedstrijden, mat] = blokData[i];
      
      // Alleen verwerken als er een geldige poule, wedstrijden en mat zijn
      if (poule && wedstrijden && !isNaN(wedstrijden) && mat && !isNaN(mat)) {
        const wedstrijdenAantal = parseInt(wedstrijden);
        const matNr = parseInt(mat);
        
        if (matNr > 0 && matNr <= aantalMatten) {
          // Voeg de wedstrijden toe aan onze matrix
          wedstrijdenMatrix[blok - 1][matNr - 1] += wedstrijdenAantal;
        }
      }
    }
  }
  
  // STAP 2: Reset en update de matrix met de wedstrijden
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    // Reset alle matcellen voor dit blok
    for (let mat = 1; mat <= aantalMatten; mat++) {
      const aantalWedstrijden = wedstrijdenMatrix[blok - 1][mat - 1];
      sheet.getRange(matrixStartRij + blok, matrixStartKolom + mat).setValue(aantalWedstrijden > 0 ? aantalWedstrijden : "");
    }
  }
  
  // STAP 3: Bereken rijtotalen in de matrix (gebaseerd op de matwaarden)
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    let rijtotaal = 0;
    
    // Tel alle waarden in deze rij op
    for (let mat = 1; mat <= aantalMatten; mat++) {
      const waarde = wedstrijdenMatrix[blok - 1][mat - 1];
      rijtotaal += waarde;
    }
    
    // Update rijtotaal in matrix
    sheet.getRange(matrixStartRij + blok, matrixStartKolom + aantalMatten + 1).setValue(rijtotaal > 0 ? rijtotaal : "");
  }
  
  // STAP 4: Bereken eindtotaal van de matrix
  let matrixEindTotaal = 0;
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    for (let mat = 1; mat <= aantalMatten; mat++) {
      matrixEindTotaal += wedstrijdenMatrix[blok - 1][mat - 1];
    }
  }
  
  // Update eindtotaal in matrix
  sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + aantalMatten + 1).setValue(matrixEindTotaal > 0 ? matrixEindTotaal : "");
  
  // STAP 5: Zorg ervoor dat de MAT-TOTALEN leeg blijven
  for (let mat = 1; mat <= aantalMatten; mat++) {
    sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + mat).setValue("");
  }
  
  // STAP 6: Verzorg de opmaak van de matrix
  // Format voor de getallen: centreren en een duidelijke opmaak
  const getallenOpmaak = sheet.getRange(matrixStartRij + 1, matrixStartKolom + 1, aantalBlokken, aantalMatten);
  getallenOpmaak.setHorizontalAlignment("center");
  getallenOpmaak.setVerticalAlignment("middle");
  getallenOpmaak.setFontWeight("normal");
  
  // Format voor de rijtotalen: vetgedrukt en gecentreerd
  const rijtotalenOpmaak = sheet.getRange(matrixStartRij + 1, matrixStartKolom + aantalMatten + 1, aantalBlokken, 1);
  rijtotalenOpmaak.setHorizontalAlignment("center");
  rijtotalenOpmaak.setVerticalAlignment("middle");
  rijtotalenOpmaak.setFontWeight("bold");
  rijtotalenOpmaak.setBackground("#F3F3F3");
  
  // Format voor de totaalrij: vetgedrukt en gecentreerd
  const totaalrijOpmaak = sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + 1, 1, aantalMatten + 1);
  totaalrijOpmaak.setHorizontalAlignment("center");
  totaalrijOpmaak.setVerticalAlignment("middle");
  totaalrijOpmaak.setFontWeight("bold");
  totaalrijOpmaak.setBackground("#D9EAD3");
  
  // STAP 7: Controleer of er discrepanties zijn tussen de linkertabel en de matrix
  const linkerTotalen = [];
  
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokRij = matrixStartRij + blok;
    const totaal = sheet.getRange(blokRij, 2).getValue();
    linkerTotalen.push(totaal || 0);
  }
  
  // Bereken het totaal van alle blokken in de linkertabel
  const linkerEindTotaal = linkerTotalen.reduce((sum, val) => sum + val, 0);
  
  // Vergelijk de totalen per blok
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const linkerTotaal = linkerTotalen[blok - 1];
    let matrixTotaal = 0;
    
    for (let mat = 1; mat <= aantalMatten; mat++) {
      matrixTotaal += wedstrijdenMatrix[blok - 1][mat - 1];
    }
    
    // Als er een discrepantie is, markeer de rijtotaal cel
    if (matrixTotaal !== linkerTotaal) {
      // Markeer de cel met de rijtotaal als er een discrepantie is
      const discrepantieRij = matrixStartRij + blok;
      const discrepantieKolom = matrixStartKolom + aantalMatten + 1;
      
      // Markeer in het rood als er meer wedstrijden zouden moeten zijn
      if (matrixTotaal < linkerTotaal) {
        sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#F4CCCC"); // Licht rood
        // Voeg een opmerking toe
        sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
          `Let op: Er ontbreken ${linkerTotaal - matrixTotaal} wedstrijden. ` +
          `Toegewezen: ${linkerTotaal}, Verdeeld over matten: ${matrixTotaal}`
        );
      }
      // Markeer in het oranje als er te veel wedstrijden zijn (zou niet moeten gebeuren)
      else if (matrixTotaal > linkerTotaal) {
        sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#FCE5CD"); // Licht oranje
        // Voeg een opmerking toe
        sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
          `Let op: Er zijn ${matrixTotaal - linkerTotaal} te veel wedstrijden verdeeld. ` +
          `Toegewezen: ${linkerTotaal}, Verdeeld over matten: ${matrixTotaal}`
        );
      }
    } else {
      // Alles klopt, verwijder eventuele opmerking en reset de achtergrond
      sheet.getRange(matrixStartRij + blok, matrixStartKolom + aantalMatten + 1).clearNote();
      sheet.getRange(matrixStartRij + blok, matrixStartKolom + aantalMatten + 1).setBackground("#F3F3F3");
    }
  }
  
  // Markeer ook eventuele discrepantie in het eindtotaal
  if (matrixEindTotaal !== linkerEindTotaal) {
    // Markeer de cel met het eindtotaal als er een discrepantie is
    const discrepantieRij = matrixStartRij + aantalBlokken + 1;
    const discrepantieKolom = matrixStartKolom + aantalMatten + 1;
    
    if (matrixEindTotaal < linkerEindTotaal) {
      sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#F4CCCC"); // Licht rood
      sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
        `Let op: Er ontbreken in totaal ${linkerEindTotaal - matrixEindTotaal} wedstrijden. ` +
        `Toegewezen: ${linkerEindTotaal}, Verdeeld over matten: ${matrixEindTotaal}`
      );
    } else {
      sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#FCE5CD"); // Licht oranje
      sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
        `Let op: Er zijn in totaal ${matrixEindTotaal - linkerEindTotaal} te veel wedstrijden verdeeld. ` +
        `Toegewezen: ${linkerEindTotaal}, Verdeeld over matten: ${matrixEindTotaal}`
      );
    }
  } else {
    // Alles klopt, verwijder eventuele opmerking
    sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + aantalMatten + 1).clearNote();
    sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + aantalMatten + 1).setBackground("#D9EAD3");
  }
  
  // STAP 8: Toon een bevestigingsmelding indien gewenst
  if (toonUI) {
    if (matrixEindTotaal < linkerEindTotaal) {
      ui.alert(
        'Herberekening voltooid - Waarschuwing',
        `Alle mattotalen zijn opnieuw berekend. Let op: Er zijn ${linkerEindTotaal} wedstrijden toegewezen aan blokken, maar slechts ${matrixEindTotaal} zijn verdeeld over matten.`,
        ui.ButtonSet.OK
      );
    } else if (matrixEindTotaal > linkerEindTotaal) {
      ui.alert(
        'Herberekening voltooid - Waarschuwing',
        `Alle mattotalen zijn opnieuw berekend. Let op: Er zijn ${matrixEindTotaal} wedstrijden verdeeld over matten, maar slechts ${linkerEindTotaal} toegewezen aan blokken.`,
        ui.ButtonSet.OK
      );
    } else {
      ui.alert(
        'Herberekening voltooid',
        `Alle mattotalen zijn opnieuw berekend. Totaal aantal wedstrijden: ${matrixEindTotaal}.`,
        ui.ButtonSet.OK
      );
    }
  }
  
  return matrixEindTotaal;
}

/**
 * Beschermt of deblokkeerd alle mat-kolommen om invoer tijdens berekening te voorkomen
 * @param {Sheet} sheet - Het werkblad
 * @param {boolean} beschermen - true om te beschermen, false om te deblokkeren
 */
function beschermMatKolommen(sheet, beschermen) {
  const props = PropertiesService.getScriptProperties();
  const matrixStartRij = parseInt(props.getProperty('matrixStartRij')) || 0;
  const aantalBlokken = getAantalBlokken();
  
  if (matrixStartRij === 0) return;
  
  // Voor elk blok, vind en bescherm/deblokkeer de mat-kolom
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    const matKolom = blokKolom + 2;
    
    // Bepaal het bereik van cellen (van rij 4 tot matrixStartRij)
    const matRange = sheet.getRange(4, matKolom, matrixStartRij - 4, 1);
    
    if (beschermen) {
      // Maak de cellen tijdelijk alleen-lezen
      matRange.setBackground("#EFEFEF"); // Lichtgrijs om aan te geven dat ze zijn uitgeschakeld
      
      // Voeg een opmerking toe aan de eerste cel om gebruikers te informeren
      sheet.getRange(4, matKolom).setNote("Invoer tijdelijk uitgeschakeld tijdens herberekening");
    } else {
      // Herstel de normale achtergrond en verwijder opmerkingen
      matRange.setBackground("#F3F3F3"); // Herstel naar originele kleur
      sheet.getRange(4, matKolom).clearNote();
    }
  }
}

/**
 * Herberekent de volledige matrix op basis van alle poule/mat toewijzingen
 * en zorgt ervoor dat de totalen in de rechtertabel overeenkomen met die in de linkertabel
 * 
 * @param {Sheet} sheet - Het werkblad (optioneel, wordt anders automatisch opgehaald)
 * @param {boolean} toonUI - Of UI-meldingen moeten worden getoond (default: true)
 */
function herberekeningMatrixTotalen(sheet, toonUI = true) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // Als geen sheet is meegegeven, haal het dan op
  if (!sheet) {
    sheet = ss.getSheetByName("Blok/Mat verdeling");
  }
  
  if (!sheet) {
    if (toonUI) ui.alert('Fout', 'Het Blok/Mat verdeling tabblad werd niet gevonden.', ui.ButtonSet.OK);
    return;
  }
  
  // Toon een statusmelding indien gewenst
  if (toonUI) {
    SpreadsheetApp.getActive().toast("Bezig met herberekenen van alle mattotalen...", "Herberekenen", 5);
  }
  
  // Haal de nodige parameters op
  const props = PropertiesService.getScriptProperties();
  const matrixStartRij = parseInt(props.getProperty('matrixStartRij')) || 0;
  const matrixStartKolom = parseInt(props.getProperty('matrixStartKolom')) || 0;
  const aantalMatten = getAantalMatten();
  const aantalBlokken = getAantalBlokken();
  
  if (matrixStartRij === 0 || matrixStartKolom === 0) {
    if (toonUI) ui.alert('Fout', 'Kon matrix-parameters niet ophalen.', ui.ButtonSet.OK);
    return;
  }
  
  // STAP 1: Lees eerst de totalen uit de linkertabel (gebaseerd op gewichtsklasse/blok toewijzingen)
  const linkerTotalen = [];
  
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokRij = matrixStartRij + blok;
    const totaal = sheet.getRange(blokRij, 2).getValue();
    linkerTotalen.push(totaal || 0);
  }
  
  // Bereken het totaal van alle blokken in de linkertabel
  const linkerEindTotaal = linkerTotalen.reduce((sum, val) => sum + val, 0);
  
  // STAP 2: Bereid een matrix voor om de wedstrijden bij te houden
  const wedstrijdenMatrix = Array(aantalBlokken).fill().map(() => Array(aantalMatten).fill(0));
  
  // Scan alle blokken en poules om wedstrijden te tellen
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    const matKolom = blokKolom + 2;
    
    // Lees alle data voor dit blok in één keer
    const maxRijen = matrixStartRij - 4;
    if (maxRijen <= 0) continue;
    
    const blokData = sheet.getRange(4, blokKolom, maxRijen, 3).getValues();
    
    // Verwerk alle rijen
    for (let i = 0; i < blokData.length; i++) {
      const [poule, wedstrijden, mat] = blokData[i];
      
      // Alleen verwerken als er een geldige poule, wedstrijden en mat zijn
      if (poule && wedstrijden && !isNaN(wedstrijden) && mat && !isNaN(mat)) {
        const wedstrijdenAantal = parseInt(wedstrijden);
        const matNr = parseInt(mat);
        
        if (matNr > 0 && matNr <= aantalMatten) {
          // Voeg de wedstrijden toe aan onze matrix
          wedstrijdenMatrix[blok - 1][matNr - 1] += wedstrijdenAantal;
        }
      }
    }
  }
  
  // STAP 3: Creëer een tweedimensionale array voor alle cel-updates in de matrix
  const matrixUpdates = [];
  
  // Updates voor mat-waarden
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const rij = [];
    
    for (let mat = 1; mat <= aantalMatten; mat++) {
      const aantalWedstrijden = wedstrijdenMatrix[blok - 1][mat - 1];
      rij.push(aantalWedstrijden > 0 ? aantalWedstrijden : "");
    }
    
    // Voeg rijtotaal toe (uit de linkertabel)
    const linkerTotaal = linkerTotalen[blok - 1];
    rij.push(linkerTotaal > 0 ? linkerTotaal : "");
    
    matrixUpdates.push(rij);
  }
  
  // STAP 4: Maak een extra rij voor de onderste totaalrij
  const totaalRij = [];
  
  // Mat-totalen moeten leeg blijven
  for (let mat = 1; mat <= aantalMatten; mat++) {
    totaalRij.push("");
  }
  
  // Eindtotaal
  totaalRij.push(linkerEindTotaal > 0 ? linkerEindTotaal : "");
  matrixUpdates.push(totaalRij);
  
  // STAP 5: Update de matrix in één keer voor betere prestaties
  sheet.getRange(matrixStartRij + 1, matrixStartKolom + 1, aantalBlokken + 1, aantalMatten + 1).setValues(matrixUpdates);
  
  // STAP 6: Verzorg de opmaak van de matrix
  // Format voor de getallen: centreren en een duidelijke opmaak
  const getallenOpmaak = sheet.getRange(matrixStartRij + 1, matrixStartKolom + 1, aantalBlokken, aantalMatten);
  getallenOpmaak.setHorizontalAlignment("center");
  getallenOpmaak.setVerticalAlignment("middle");
  getallenOpmaak.setFontWeight("normal");
  
  // Format voor de rijtotalen: vetgedrukt en gecentreerd
  const rijtotalenOpmaak = sheet.getRange(matrixStartRij + 1, matrixStartKolom + aantalMatten + 1, aantalBlokken, 1);
  rijtotalenOpmaak.setHorizontalAlignment("center");
  rijtotalenOpmaak.setVerticalAlignment("middle");
  rijtotalenOpmaak.setFontWeight("bold");
  rijtotalenOpmaak.setBackground("#F3F3F3");
  
  // Format voor de totaalrij: vetgedrukt en gecentreerd
  const totaalrijOpmaak = sheet.getRange(matrixStartRij + aantalBlokken + 1, matrixStartKolom + 1, 1, aantalMatten + 1);
  totaalrijOpmaak.setHorizontalAlignment("center");
  totaalrijOpmaak.setVerticalAlignment("middle");
  totaalrijOpmaak.setFontWeight("bold");
  totaalrijOpmaak.setBackground("#D9EAD3");
  
  // STAP 7: Controleer of alle wedstrijden zijn verdeeld
  // Bereken hoeveel wedstrijden er werkelijk zijn toegewezen aan matten
  let matWedstrijden = 0;
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    let blokMatTotaal = 0;
    for (let mat = 1; mat <= aantalMatten; mat++) {
      blokMatTotaal += wedstrijdenMatrix[blok - 1][mat - 1];
    }
    matWedstrijden += blokMatTotaal;
    
    // Als er een discrepantie is, markeer de rijtotaal cel
    if (blokMatTotaal !== linkerTotalen[blok - 1]) {
      // Markeer de cel met de rijtotaal als er een discrepantie is
      const discrepantieRij = matrixStartRij + blok;
      const discrepantieKolom = matrixStartKolom + aantalMatten + 1;
      
      // Markeer in het rood als er meer wedstrijden zouden moeten zijn
      if (blokMatTotaal < linkerTotalen[blok - 1]) {
        sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#F4CCCC"); // Licht rood
        // Voeg een opmerking toe
        sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
          `Let op: Er ontbreken ${linkerTotalen[blok - 1] - blokMatTotaal} wedstrijden. ` +
          `Toegewezen: ${linkerTotalen[blok - 1]}, Verdeeld over matten: ${blokMatTotaal}`
        );
      }
      // Markeer in het oranje als er te veel wedstrijden zijn (zou niet moeten gebeuren)
      else if (blokMatTotaal > linkerTotalen[blok - 1]) {
        sheet.getRange(discrepantieRij, discrepantieKolom).setBackground("#FCE5CD"); // Licht oranje
        // Voeg een opmerking toe
        sheet.getRange(discrepantieRij, discrepantieKolom).setNote(
          `Let op: Er zijn ${blokMatTotaal - linkerTotalen[blok - 1]} te veel wedstrijden verdeeld. ` +
          `Toegewezen: ${linkerTotalen[blok - 1]}, Verdeeld over matten: ${blokMatTotaal}`
        );
      }
    } else {
      // Alles klopt, verwijder eventuele opmerking
      sheet.getRange(matrixStartRij + blok, matrixStartKolom + aantalMatten + 1).clearNote();
    }
  }
  
  // STAP 8: Toon een bevestigingsmelding indien gewenst
  if (toonUI) {
    if (matWedstrijden < linkerEindTotaal) {
      ui.alert(
        'Herberekening voltooid - Waarschuwing',
        `Alle mattotalen zijn opnieuw berekend. Let op: Er zijn ${linkerEindTotaal} wedstrijden toegewezen aan blokken, maar slechts ${matWedstrijden} zijn verdeeld over de matten.`,
        ui.ButtonSet.OK
      );
    } else {
      ui.alert(
        'Herberekening voltooid',
        `Alle mattotalen zijn opnieuw berekend. Totaal aantal wedstrijden: ${linkerEindTotaal}.`,
        ui.ButtonSet.OK
      );
    }
  }
  
  return linkerEindTotaal;
}


/**
 * Werkt alleen de matrix rechtsonder bij, NIET de linkertabel
 * Deze geoptimaliseerde versie leest en schrijft data in batches voor betere prestaties
 * @param {Sheet} sheet - Het werkblad
 * @param {number} startRij - De startrij van de matrix
 * @param {number} startKolom - De startkolom van de matrix
 * @param {number} aantalMatten - Het aantal matten
 * @param {number} aantalBlokken - Het aantal blokken
 */
function updateMatrixTotalen(sheet, startRij, startKolom, aantalMatten, aantalBlokken) {
  // Haal alle gegevens in één keer op voor betere prestaties
  const matrixData = sheet.getRange(startRij + 1, startKolom + 1, aantalBlokken, aantalMatten).getValues();
  
  // Bereken rijtotalen
  const rijtotalen = [];
  let totaalAlleBlokken = 0;
  
  for (let i = 0; i < aantalBlokken; i++) {
    let rijtotaal = 0;
    
    // Tel alle geldige getallen in deze rij op
    for (let j = 0; j < aantalMatten; j++) {
      const waarde = matrixData[i][j];
      if (waarde !== null && waarde !== undefined && waarde !== "" && !isNaN(waarde)) {
        rijtotaal += parseInt(waarde);
      }
    }
    
    rijtotalen.push(rijtotaal);
    totaalAlleBlokken += rijtotaal;
  }
  
  // Update alle rijtotalen in één keer
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const rij = startRij + blok;
    const rijtotaal = rijtotalen[blok - 1];
    
    // Update rijtotaal in matrix
    sheet.getRange(rij, startKolom + aantalMatten + 1).setValue(rijtotaal > 0 ? rijtotaal : "");
  }
  
  // Update het eindtotaal
  const totaalRij = startRij + aantalBlokken + 1;
  sheet.getRange(totaalRij, startKolom + aantalMatten + 1).setValue(totaalAlleBlokken > 0 ? totaalAlleBlokken : "");
  
  // Zorg ervoor dat de MAT-TOTALEN leeg blijven
  const legeWaarden = Array(aantalMatten).fill("");
  sheet.getRange(totaalRij, startKolom + 1, 1, aantalMatten).setValues([legeWaarden]);
}

/**
 * Event handler voor het toewijzen van een blok aan een gewichtsklasse
 * Deze functie werkt alleen de linkertabel bij, niet de mattentabel
 */
function onBlokToewijzing(e) {
  const sheet = e.range.getSheet();
  
  if (sheet.getName() !== "Blok/Mat verdeling") {
    return;
  }
  
  const range = e.range;
  const row = range.getRow();
  const col = range.getColumn();
  
  // Controleer of dit een cel in de blok-kolom is
  if (col !== 3 || row < 4) {
    return;
  }
  
  // Update de linkertabel met bloktotalen
  updateLinkerTabelTotalen(sheet);
  
  // Toon een melding dat de gebruiker 'Vul poules in blokken' moet gebruiken
  SpreadsheetApp.getActive().toast(
    'Gebruik de menufunctie "Vul poules in blokken" om de poules in de mattentabel te plaatsen.',
    'Blok toegewezen',
    5
  );
}



/**
 * Werkt de linkertabel met bloktotalen bij op basis van de gewichtsklasse/blok toewijzingen
 * @param {Sheet} sheet - Het werkblad
 */
function updateLinkerTabelTotalen(sheet) {
  // Lees de matrix locatie uit de properties
  const props = PropertiesService.getScriptProperties();
  const matrixStartRij = parseInt(props.getProperty('matrixStartRij')) || 0;
  
  if (matrixStartRij === 0) {
    return;
  }
  
  const aantalBlokken = getAantalBlokken();
  
  // Bepaal de laatste rij met gewichtsklassen
  const laatsteRij = sheet.getLastRow();
  let laatsteRijLinks = 0;
  
  for (let i = 4; i <= laatsteRij && i < matrixStartRij; i++) {
    if (sheet.getRange(i, 1).getValue()) {
      laatsteRijLinks = i;
    }
  }
  
  if (laatsteRijLinks === 0) {
    return; // Geen gewichtsklassen gevonden
  }
  
  // Update de linkertabel
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokRij = matrixStartRij + blok;

    // SUMIF formule met ABSOLUTE referenties en puntkomma
    // Deze formule telt alleen wedstrijden op basis van de gewichtsklasse/blok toewijzingen
    sheet.getRange(blokRij, 2).setFormula(`=SUMIF($C$4:$C$${laatsteRijLinks};${blok};$B$4:$B$${laatsteRijLinks})`);
  }

  // Update het eindtotaal in de linkertabel met ABSOLUTE referenties
  const totaalRij = matrixStartRij + aantalBlokken + 1;
  sheet.getRange(totaalRij, 2).setFormula(`=SUM($B$${matrixStartRij + 1}:$B$${totaalRij - 1})`);
}

// Functies getAantalMatten, getAantalBlokken, leesGewichtsklassenEnWedstrijden en leesPouleDetails
// zijn nu verplaatst naar ConfigUtils.js en PouleUtils.js

/**
 * Werkt de poule-indeling bij met blok- en matnummers uit de Blok/Mat verdeling
 * Deze functie neemt de handmatige blok/mat toewijzingen en zet ze in het PouleIndeling tabblad
 */
function updatePouleIndelingVanuitMenu() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // Controleer of de benodigde tabbladen bestaan
  const verdelingSheet = ss.getSheetByName("Blok/Mat verdeling");
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  
  if (!verdelingSheet || !poulesSheet) {
    ui.alert(
      'Fout',
      'Een of meer benodigde tabbladen ontbreken. Zorg dat "Blok/Mat verdeling" en "PouleIndeling" bestaan.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Lees de poule-gegevens uit de Blok/Mat verdeling
  const aantalBlokken = getAantalBlokken();
  const pouleToewijzingen = [];
  
  // Voor elk blok
  for (let blok = 1; blok <= aantalBlokken; blok++) {
    const blokKolom = 5 + (blok - 1) * 3;
    const matKolom = blokKolom + 2;
    
    // Lees alle poules in dit blok
    for (let rij = 4; rij <= 100; rij++) {
      const pouleWaarde = verdelingSheet.getRange(rij, blokKolom).getValue();
      const matWaarde = verdelingSheet.getRange(rij, matKolom).getValue();
      
      // Als er een poule is en een mat-toewijzing, bewaar deze informatie
      if (pouleWaarde && matWaarde) {
        // Extract poule-informatie uit de pouleWaarde
        let pouleNr;

        // Probeer verschillende patronen te herkennen
        // Patroon 1: "12,M-24" (pouleNr + komma + leeftijdsklasse + gewichtsklasse)
        let match = /^(\d+),/.exec(pouleWaarde);
        if (match) {
          pouleNr = parseInt(match[1]);
        }
        // Patroon 2: "12M-24" (oude format zonder komma, voor backwards compatibility)
        else {
          match = /^(\d+)[A-Z]/.exec(pouleWaarde);
          if (match) {
            pouleNr = parseInt(match[1]);
          }
        }

        // Als een pouleNr gevonden is, bewaar de toewijzing
        if (pouleNr) {
          pouleToewijzingen.push({
            pouleNr: pouleNr,
            blok: blok,
            mat: matWaarde
          });
        }
      }
    }
  }
  
  if (pouleToewijzingen.length === 0) {
    ui.alert(
      'Geen toewijzingen gevonden',
      'Er zijn geen poule-toewijzingen gevonden in de Blok/Mat verdeling.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Update de PouleIndeling met de gevonden toewijzingen
  const poulesData = poulesSheet.getDataRange().getValues();
  const pouleNrIdx = poulesData[0].indexOf("Poule-nr");
  const blokIdx = poulesData[0].indexOf("Blok");
  const matIdx = poulesData[0].indexOf("Mat");
  
  if (pouleNrIdx === -1 || blokIdx === -1 || matIdx === -1) {
    ui.alert(
      'Fout in PouleIndeling',
      'Het PouleIndeling tabblad mist een of meer benodigde kolommen (Poule-nr, Blok, Mat).',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Houd bij hoeveel poules we geüpdatet hebben
  let updatedCount = 0;
  
  // Loop door alle rijen in de PouleIndeling
  for (let i = 1; i < poulesData.length; i++) {
    const pouleNr = poulesData[i][pouleNrIdx];
    
    // Als deze rij een pouleNr heeft, controleer of we een toewijzing hebben
    if (pouleNr) {
      const toewijzing = pouleToewijzingen.find(t => t.pouleNr === pouleNr);
      
      if (toewijzing) {
        // Update de blok- en matkolommen
        poulesSheet.getRange(i + 1, blokIdx + 1).setValue(toewijzing.blok);
        poulesSheet.getRange(i + 1, matIdx + 1).setValue(toewijzing.mat);
        updatedCount++;
      }
    }
  }
  
  // Toon een bevestiging
  ui.alert(
    'Poule-indeling bijgewerkt',
    `Er zijn ${updatedCount} poule-toewijzingen bijgewerkt in het PouleIndeling tabblad.`,
    ui.ButtonSet.OK
  );
}