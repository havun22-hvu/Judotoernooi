// Wedstrijdschemas.js - Genereer printbare wedstrijdschema's per poule
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalBlokken)
 * - PouleUtils.js (berekenAantalWedstrijden)
 */

/**
 * onEdit trigger voor wedstrijdschema's
 * (Momenteel geen functionaliteit - alle acties via sidebar)
 */
function onEdit(e) {
  // Alle wedstrijdschema functies zijn verplaatst naar de Print Sidebar
  // voor een betere gebruikerservaring
}

/**
 * Berekent de plaatsen voor de geselecteerde poule (aangeroepen vanuit sidebar)
 */
function berekenPlaatsenGeselecteerdePoule() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();

  // Check of we in een wedstrijdschema sheet zijn
  if (!sheet.getName().startsWith("Wedstrijdschema's Blok")) {
    throw new Error('Ga naar een "Wedstrijdschema\'s Blok X" tabblad en klik in een poule.');
  }

  const activeCel = sheet.getActiveCell();
  const actieveRij = activeCel.getRow();

  // Zoek de poule header
  let headerRij = actieveRij;
  for (let r = actieveRij; r >= 1; r--) {
    const celData = sheet.getRange(r, 1, 1, 10).getValues()[0];
    const hasNrHeader = celData[0] === "Nr";

    if (hasNrHeader) {
      headerRij = r;
      break;
    }

    if (r < actieveRij - 50) {
      throw new Error('Kon geen poule header vinden. Klik in een poule.');
    }
  }

  // Zoek de poule data
  const eersteJudokaRij = headerRij + 2; // Na headers en sub-headers

  // Zoek hoeveel judoka's er zijn
  let laatsteJudokaRij = eersteJudokaRij;
  while (sheet.getRange(laatsteJudokaRij, 1).getValue() !== "") {
    laatsteJudokaRij++;
  }
  laatsteJudokaRij--;

  const aantalJudokas = laatsteJudokaRij - eersteJudokaRij + 1;

  // Zoek de WP, JP en Plts kolommen
  const data = sheet.getRange(headerRij, 1, 1, sheet.getLastColumn()).getValues()[0];
  let wpCol, jpCol, pltsCol;

  for (let i = data.length - 1; i >= 0; i--) {
    if (data[i] && data[i].toString().includes("Plts")) pltsCol = i + 1;
    if (data[i] === "JP") jpCol = i + 1;
    if (data[i] === "WP") wpCol = i + 1;
  }

  if (!wpCol || !jpCol || !pltsCol) {
    throw new Error("Kon WP, JP of Plts kolommen niet vinden!");
  }

  // Lees WP en JP totalen
  const judokas = [];
  for (let r = eersteJudokaRij; r <= laatsteJudokaRij; r++) {
    const wp = sheet.getRange(r, wpCol).getValue() || 0;
    const jp = sheet.getRange(r, jpCol).getValue() || 0;
    judokas.push({ rij: r, wp: wp, jp: jp });
  }

  // Sorteer: hoogste WP eerst, bij gelijk WP -> hoogste JP eerst
  judokas.sort((a, b) => {
    if (b.wp !== a.wp) return b.wp - a.wp;
    return b.jp - a.jp;
  });

  // Schrijf plaatsen
  for (let i = 0; i < judokas.length; i++) {
    const plaats = i + 1;
    sheet.getRange(judokas[i].rij, pltsCol).setValue(plaats);
  }

  return { success: true, message: `Plaatsen berekend voor ${aantalJudokas} judoka's!` };
}

/**
 * Print de geselecteerde poule
 * Vereenvoudigde versie - gebruikt de selectie van de admin
 */
function printGeselecteerdePouleSimple() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();
  const selection = sheet.getActiveRange();

  if (!selection) {
    throw new Error('Selecteer eerst een poule (de volledige poule inclusief headers)');
  }

  // Onthoud de originele sheet naam om later terug te keren
  const origineleSheetNaam = sheet.getName();

  // Verwijder oude Print tab volledig als deze bestaat
  let printSheet = ss.getSheetByName("Print");
  if (printSheet) {
    ss.deleteSheet(printSheet);
  }

  // Maak een verse nieuwe Print tab
  printSheet = ss.insertSheet("Print");

  // Reset alle opmaak naar standaard
  printSheet.clear();
  printSheet.clearFormats();

  // Kopieer de geselecteerde data naar A1 in de Print tab (printer maakt eigen marges)
  const doelRange = printSheet.getRange(1, 1, selection.getNumRows(), selection.getNumColumns());

  // Kopieer waarden en opmaak
  selection.copyTo(doelRange, SpreadsheetApp.CopyPasteType.PASTE_NORMAL, false);

  // Kopieer de EXACTE kolombreedte van de originele kolommen
  const bronStartCol = selection.getColumn();
  const doelStartCol = 1;
  const numCols = selection.getNumColumns();

  for (let i = 0; i < numCols; i++) {
    const bronKolomBreedte = sheet.getColumnWidth(bronStartCol + i);
    printSheet.setColumnWidth(doelStartCol + i, bronKolomBreedte);
  }

  // Stel print instellingen in
  printSheet.setHiddenGridlines(true);

  // Verberg de Print tab (wordt alleen zichtbaar bij printen)
  printSheet.hideSheet();

  // Ga terug naar de originele sheet
  ss.setActiveSheet(sheet);

  return {
    success: true,
    message: 'Poule gekopieerd naar Print tab (verborgen, klik Open Print Tab om te printen)',
    origineleSheet: origineleSheetNaam
  };
}

/**
 * Maak Print tab zichtbaar en open het
 */
function openPrintTab() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const printSheet = ss.getSheetByName("Print");

  if (!printSheet) {
    throw new Error('Print tab bestaat niet. Selecteer eerst een poule en klik "Print Poule".');
  }

  // Maak zichtbaar en activeer
  printSheet.showSheet();
  printSheet.activate();

  return { success: true, message: 'Print tab geopend - gebruik Ctrl+P om te printen' };
}

/**
 * Stuurt een voltooide poule naar de Uitslagen tab
 * Overschrijft oude versie als deze al bestaat
 */
function stuurNaarUitslagen() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();
  const selection = sheet.getActiveRange();

  if (!selection) {
    throw new Error('Selecteer eerst een poule (de volledige poule inclusief header)');
  }

  // Check of we in een wedstrijdschema sheet zijn
  if (!sheet.getName().startsWith("Wedstrijdschema's Blok")) {
    throw new Error('Ga naar een "Wedstrijdschema\'s Blok X" tabblad en selecteer een poule.');
  }

  // Probeer de poule header te vinden in de selectie
  const selectionValues = selection.getValues();
  let pouleNr = null;
  let headerRij = null;

  // Zoek naar de blauwe header met "Poule X"
  for (let i = 0; i < selectionValues.length; i++) {
    const rowText = selectionValues[i].join(' ');
    const match = rowText.match(/Poule (\d+)/);
    if (match) {
      pouleNr = parseInt(match[1]);
      headerRij = selection.getRow() + i;
      break;
    }
  }

  if (!pouleNr) {
    throw new Error('Kon geen "Poule X" header vinden in de selectie. Selecteer de hele poule inclusief header.');
  }

  // Maak of haal Uitslagen tab op
  let uitslagenSheet = ss.getSheetByName("Uitslagen");
  if (!uitslagenSheet) {
    // Maak nieuwe Uitslagen tab als laatste
    const aantalSheets = ss.getSheets().length;
    uitslagenSheet = ss.insertSheet("Uitslagen", aantalSheets);

    // Header voor Uitslagen tab
    uitslagenSheet.getRange(1, 1, 1, 20).merge();
    uitslagenSheet.getRange(1, 1)
      .setValue('UITSLAGEN - VOLTOOIDE POULES')
      .setFontWeight("bold")
      .setFontSize(16)
      .setBackground("#434343")
      .setFontColor("#FFFFFF")
      .setHorizontalAlignment("center");
  }

  // Zoek of deze poule al bestaat in Uitslagen
  const uitslagenData = uitslagenSheet.getDataRange().getValues();
  let bestaandePouleRij = null;

  for (let i = 0; i < uitslagenData.length; i++) {
    const rowText = uitslagenData[i].join(' ');
    const match = rowText.match(/Poule (\d+)/);
    if (match && parseInt(match[1]) === pouleNr) {
      bestaandePouleRij = i + 1; // Google Sheets is 1-indexed
      break;
    }
  }

  let doelRij;
  if (bestaandePouleRij) {
    // Overschrijf bestaande poule - verwijder oude versie eerst
    const aantalRijenOud = 20; // Ruim genoeg om oude poule te wissen
    uitslagenSheet.getRange(bestaandePouleRij, 1, aantalRijenOud, 50).clear().clearFormat();
    doelRij = bestaandePouleRij;
  } else {
    // Voeg toe onderaan
    doelRij = uitslagenSheet.getLastRow() + 2; // 2 rijen ruimte
  }

  // Kopieer de poule naar Uitslagen
  const doelRange = uitslagenSheet.getRange(doelRij, 1, selection.getNumRows(), selection.getNumColumns());
  selection.copyTo(doelRange, SpreadsheetApp.CopyPasteType.PASTE_NORMAL, false);

  // Kopieer kolombreedte
  const bronStartCol = selection.getColumn();
  for (let i = 0; i < selection.getNumColumns(); i++) {
    const bronKolomBreedte = sheet.getColumnWidth(bronStartCol + i);
    uitslagenSheet.setColumnWidth(1 + i, bronKolomBreedte);
  }

  // Markeer de originele poule als "verstuurd" door ✅ toe te voegen aan header
  const headerCel = sheet.getRange(headerRij, Math.floor(selection.getNumColumns() / 2) + 1);
  const huidigeWaarde = headerCel.getValue();
  if (!huidigeWaarde.includes('✅')) {
    headerCel.setValue(huidigeWaarde + ' ✅');
  }

  // Timestamp toevoegen in Uitslagen
  const timestampCel = uitslagenSheet.getRange(doelRij, selection.getNumColumns() + 2);
  timestampCel.setValue('Verstuurd: ' + new Date().toLocaleString('nl-NL'))
    .setFontSize(9)
    .setFontColor('#666666');

  return {
    success: true,
    message: `Poule ${pouleNr} verstuurd naar Uitslagen tab ${bestaandePouleRij ? '(overschreven)' : '(nieuw)'}`
  };
}

/**
 * Open de print sidebar
 */
function openPrintSidebar() {
  const html = HtmlService.createHtmlOutputFromFile('PrintSidebar')
    .setTitle('Print Poule')
    .setWidth(300);
  SpreadsheetApp.getUi().showSidebar(html);
}

/**
 * Print de geselecteerde poule (aangeroepen vanuit sidebar)
 * Detecteert automatisch in welke poule de actieve cel staat
 */
function printGeselecteerdePoule() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();

  // Check of we in een wedstrijdschema sheet zijn
  if (!sheet.getName().startsWith("Wedstrijdschema's Blok")) {
    throw new Error('Ga naar een "Wedstrijdschema\'s Blok X" tabblad en klik in een poule.');
  }

  const activeCel = sheet.getActiveCell();
  const actieveRij = activeCel.getRow();

  // Zoek naar boven tot we de poule header vinden (blauwe balk met "Poule X")
  let pouleHeaderRij = actieveRij;
  for (let r = actieveRij; r >= 1; r--) {
    const celData = sheet.getRange(r, 1, 1, 20).getValues()[0];
    const celText = celData.join(' ');

    // Check of deze rij een poule header is (bevat "Poule" en heeft blauwe achtergrond)
    if (celText.includes('Poule') && celText.includes('Blok')) {
      const bgColor = sheet.getRange(r, 1).getBackground();
      // Blauwe achtergrond: #4A86E8 of variant
      if (bgColor && (bgColor.toLowerCase().includes('#4a86e8') || bgColor.toLowerCase().includes('blue'))) {
        pouleHeaderRij = r;
        break;
      }
    }

    // Stop als we te ver teruggaan
    if (r < actieveRij - 50) {
      throw new Error('Kon geen poule header vinden. Klik in de titel balk van een poule.');
    }
  }

  // Nu hebben we de poule header, roep printEnkelePoule aan
  Logger.log(`Geselecteerde poule gevonden op rij ${pouleHeaderRij}`);
  printEnkelePoule(sheet, pouleHeaderRij);

  return { success: true, message: 'Poule gekopieerd naar Print tab!' };
}

/**
 * Wist de Print tab na het printen
 * Verwijdert het tabblad volledig en keert terug naar wedstrijdschema
 */
function wisPrintTab() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const printSheet = ss.getSheetByName("Print");

  if (!printSheet) {
    return { success: true, message: 'Print tab bestaat niet (al verwijderd)' };
  }

  // Zoek een Wedstrijdschema tabblad om naar terug te keren
  const sheets = ss.getSheets();
  let wedstrijdschemaSheet = null;
  for (const sheet of sheets) {
    if (sheet.getName().startsWith("Wedstrijdschema's Blok")) {
      wedstrijdschemaSheet = sheet;
      break;
    }
  }

  // Verwijder het tabblad volledig
  ss.deleteSheet(printSheet);

  // Ga terug naar het wedstrijdschema (als gevonden)
  if (wedstrijdschemaSheet) {
    ss.setActiveSheet(wedstrijdschemaSheet);
  }

  return { success: true, message: 'Print tab verwijderd - terug naar wedstrijdschema' };
}

/**
 * Genereer wedstrijdschema's voor een specifiek blok (PRINT VERSIE)
 * Maakt IDENTIEK schema als digitale versie, maar dan voor printen
 * @param {number} blokNr - Het bloknummer (1-6)
 */
function genereerWedstrijdschemasBlok(blokNr) {
  // Deze functie is nu identiek aan digitaalWedstrijdschemaBlok
  // Voor print: gebruik gewoon de digitale versie
  genereerDigitaleWedstrijdschemasBlok(blokNr);
}

/**
 * OUDE genereerWedstrijdschemasBlok - Nu vervangen door unified versie
 * Keeping for reference only
 */
function genereerWedstrijdschemasBlok_OUD(blokNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer of het blok bestaat
  const aantalBlokken = getAantalBlokken();
  if (blokNr < 1 || blokNr > aantalBlokken) {
    ui.alert('Ongeldig bloknummer', `Blok ${blokNr} bestaat niet.`, ui.ButtonSet.OK);
    return;
  }

  // Haal poule-informatie op uit PouleIndeling
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" is niet gevonden.', ui.ButtonSet.OK);
    return;
  }

  // Verzamel alle poules voor dit blok
  // Gebruik getDisplayValues() om problemen met hyperlinks/formules te voorkomen
  const pouleData = poulesSheet.getDataRange().getDisplayValues();
  const headers = pouleData[0];

  const blokIdx = headers.indexOf("Blok");
  const matIdx = headers.indexOf("Mat");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const pouleTitelIdx = headers.indexOf("Pouletitel");
  const naamIdx = headers.indexOf("Naam");
  const aanwezigIdx = headers.indexOf("Aanwezig");

  if (blokIdx === -1 || matIdx === -1 || pouleNrIdx === -1) {
    ui.alert('Fout', 'Kon de benodigde kolommen niet vinden in PouleIndeling.', ui.ButtonSet.OK);
    return;
  }

  // Verzamel poules per mat
  const poulesPerMat = {};

  for (let i = 1; i < pouleData.length; i++) {
    const row = pouleData[i];
    // Converteer numerieke waarden (getDisplayValues geeft alles als string)
    const pouleBlok = row[blokIdx] ? parseInt(row[blokIdx]) : 0;
    const mat = row[matIdx] ? parseInt(row[matIdx]) : 0;
    const pouleNr = row[pouleNrIdx] ? parseInt(row[pouleNrIdx]) : 0;
    const pouleTitel = row[pouleTitelIdx];
    const naam = row[naamIdx];
    const aanwezig = aanwezigIdx !== -1 ? row[aanwezigIdx] : "";

    // Skip als niet het juiste blok
    if (pouleBlok !== blokNr) continue;

    // Skip als geen mat of poule nummer
    if (!mat || !pouleNr) continue;

    // Skip titel rijen
    if (!naam || (typeof naam === 'string' && naam.includes("Poule"))) continue;

    // Skip afwezige judoka's (alleen judoka's met "Ja" of lege aanwezigheid meenemen)
    if (aanwezig === "Nee") continue;

    // Initialiseer mat als deze nog niet bestaat
    if (!poulesPerMat[mat]) {
      poulesPerMat[mat] = {};
    }

    // Initialiseer poule als deze nog niet bestaat
    if (!poulesPerMat[mat][pouleNr]) {
      poulesPerMat[mat][pouleNr] = {
        pouleNr: pouleNr,
        titel: pouleTitel,
        mat: mat,
        judokas: []
      };
    }

    // Voeg judoka toe
    poulesPerMat[mat][pouleNr].judokas.push({
      naam: naam,
      club: row[headers.indexOf("Club")] || ""
    });
  }

  // Controleer of er poules zijn voor dit blok
  if (Object.keys(poulesPerMat).length === 0) {
    ui.alert(
      'Geen poules gevonden',
      `Er zijn geen poules gevonden voor Blok ${blokNr}. Zorg dat de blok/mat indeling is gemaakt.`,
      ui.ButtonSet.OK
    );
    return;
  }

  // Maak of verwijder bestaand tabblad
  const sheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  let schemaSheet = ss.getSheetByName(sheetNaam);
  if (schemaSheet) {
    try {
      // Probeer eerst alle formules te verwijderen om externe referenties te voorkomen
      schemaSheet.clearContents();
      SpreadsheetApp.flush(); // Forceer update
      ss.deleteSheet(schemaSheet);
      schemaSheet = null;
    } catch (e) {
      Logger.log(`Waarschuwing: kon oud tabblad niet verwijderen: ${e.message}`);
      schemaSheet = null; // Reset zodat we een nieuw maken
    }
  }

  // Maak nieuw tabblad achteraan
  if (!schemaSheet) {
    const aantalSheets = ss.getSheets().length;
    schemaSheet = ss.insertSheet(sheetNaam, aantalSheets);
  }

  // Sorteer matten op nummer
  const matNummers = Object.keys(poulesPerMat).map(m => parseInt(m)).sort((a, b) => a - b);

  let huidigeRij = 1;

  // Voeg header toe met print knop
  maakSchemaHeader(schemaSheet, huidigeRij, blokNr);
  huidigeRij += 4;

  // Genereer schema's per mat
  for (const matNr of matNummers) {
    const poules = poulesPerMat[matNr];

    // Sorteer poules op poulenummer
    const pouleNummers = Object.keys(poules).map(p => parseInt(p)).sort((a, b) => a - b);

    // Mat header
    schemaSheet.getRange(huidigeRij, 1, 1, 10).merge();
    schemaSheet.getRange(huidigeRij, 1)
      .setValue(`═══ MAT ${matNr} ═══`)
      .setFontWeight("bold")
      .setFontSize(14)
      .setBackground("#4A86E8")
      .setFontColor("#FFFFFF")
      .setHorizontalAlignment("center");
    huidigeRij += 2;

    // Genereer schema voor elke poule
    for (const pouleNr of pouleNummers) {
      const poule = poules[pouleNr];
      huidigeRij = maakPouleSchema(schemaSheet, huidigeRij, poule, blokNr);
    }
  }

  // Pas kolombreedtes aan
  schemaSheet.setColumnWidth(1, 50);   // W#
  schemaSheet.setColumnWidth(2, 60);   // Wit
  schemaSheet.setColumnWidth(3, 200);  // Naam Wit
  schemaSheet.setColumnWidth(4, 200);  // Naam Blauw
  schemaSheet.setColumnWidth(5, 60);   // Blauw
  schemaSheet.setColumnWidth(6, 150);  // Uitslag

  ui.alert(
    'Wedstrijdschema\'s gegenereerd',
    `Wedstrijdschema's voor Blok ${blokNr} zijn aangemaakt in tabblad "${sheetNaam}".\n\n` +
    `Gebruik "Print alle schema's" om alle schema's te printen, of selecteer een specifiek schema en print de selectie.`,
    ui.ButtonSet.OK
  );
}

/**
 * Maakt de header van het schema-tabblad met print knop
 * @param {Sheet} sheet - Het sheet
 * @param {number} startRij - De startrij
 * @param {number} blokNr - Het bloknummer
 */
function maakSchemaHeader(sheet, startRij, blokNr) {
  // Titel
  sheet.getRange(startRij, 1, 1, 6).merge();
  sheet.getRange(startRij, 1)
    .setValue(`WEDSTRIJDSCHEMA'S BLOK ${blokNr}`)
    .setFontWeight("bold")
    .setFontSize(16)
    .setBackground("#434343")
    .setFontColor("#FFFFFF")
    .setHorizontalAlignment("center");

  // Print instructie
  sheet.getRange(startRij + 2, 1, 1, 6).merge();
  sheet.getRange(startRij + 2, 1)
    .setValue(`📄 Print alle schema's: Bestand > Afdrukken > Hele werkblad`)
    .setFontStyle("italic")
    .setBackground("#F3F3F3")
    .setHorizontalAlignment("center");
}

/**
 * Maakt een wedstrijdschema voor één poule
 * @param {Sheet} sheet - Het sheet
 * @param {number} startRij - De startrij
 * @param {Object} poule - Poule informatie met judokas
 * @param {number} blokNr - Het bloknummer
 * @return {number} De volgende beschikbare rij
 */
function maakPouleSchema(sheet, startRij, poule, blokNr) {
  let rij = startRij;

  // Poule header
  sheet.getRange(rij, 1, 1, 6).merge();
  sheet.getRange(rij, 1)
    .setValue(`BLOK ${blokNr} - MAT ${poule.mat} - ${poule.titel}`)
    .setFontWeight("bold")
    .setFontSize(12)
    .setBackground("#D9D9D9")
    .setHorizontalAlignment("center");
  rij++;

  // Judoka lijst header
  sheet.getRange(rij, 1).setValue("Nr").setFontWeight("bold").setBackground("#E6E6E6");
  sheet.getRange(rij, 2, 1, 2).merge();
  sheet.getRange(rij, 2).setValue("Naam").setFontWeight("bold").setBackground("#E6E6E6");
  sheet.getRange(rij, 4, 1, 2).merge();
  sheet.getRange(rij, 4).setValue("Club").setFontWeight("bold").setBackground("#E6E6E6");
  rij++;

  // Judoka's
  for (let i = 0; i < poule.judokas.length; i++) {
    const judoka = poule.judokas[i];
    sheet.getRange(rij, 1).setValue(i + 1).setHorizontalAlignment("center");
    sheet.getRange(rij, 2, 1, 2).merge();
    sheet.getRange(rij, 2).setValue(judoka.naam);
    sheet.getRange(rij, 4, 1, 2).merge();
    sheet.getRange(rij, 4).setValue(judoka.club);
    rij++;
  }

  rij++; // Lege rij

  // Wedstrijden header
  const wedstrijdHeaders = ["W#", "Wit", "Naam", "Naam", "Blauw", "Uitslag"];
  for (let col = 0; col < wedstrijdHeaders.length; col++) {
    sheet.getRange(rij, col + 1)
      .setValue(wedstrijdHeaders[col])
      .setFontWeight("bold")
      .setBackground("#4A86E8")
      .setFontColor("#FFFFFF")
      .setHorizontalAlignment("center");
  }
  rij++;

  // Genereer wedstrijdparen
  const wedstrijden = genereerWedstrijdparen(poule.judokas.length);

  for (let w = 0; w < wedstrijden.length; w++) {
    const wedstrijd = wedstrijden[w];
    const witJudoka = poule.judokas[wedstrijd.wit - 1];
    const blauwJudoka = poule.judokas[wedstrijd.blauw - 1];

    // Wedstrijdnummer
    sheet.getRange(rij, 1).setValue(w + 1).setHorizontalAlignment("center");

    // Wit nummer
    sheet.getRange(rij, 2).setValue(wedstrijd.wit).setHorizontalAlignment("center");

    // Wit naam (korter weergeven)
    const witNaamKort = maakNaamKort(witJudoka.naam, witJudoka.club);
    sheet.getRange(rij, 3).setValue(witNaamKort);

    // Blauw naam (korter weergeven)
    const blauwNaamKort = maakNaamKort(blauwJudoka.naam, blauwJudoka.club);
    sheet.getRange(rij, 4).setValue(blauwNaamKort);

    // Blauw nummer
    sheet.getRange(rij, 5).setValue(wedstrijd.blauw).setHorizontalAlignment("center");

    // Uitslag (leeg voor invullen)
    sheet.getRange(rij, 6).setValue("");

    rij++;
  }

  rij++; // Lege rij

  // Print instructie voor dit schema
  sheet.getRange(rij, 1, 1, 6).merge();
  sheet.getRange(rij, 1)
    .setValue("📄 Print alleen dit schema: Selecteer deze poule en ga naar Bestand > Afdrukken > Selectie")
    .setFontStyle("italic")
    .setFontSize(10)
    .setBackground("#FFF3CD")
    .setHorizontalAlignment("center")
    .setFontColor("#856404");
  rij++;

  rij++; // Lege rij

  // Opmerkingen
  sheet.getRange(rij, 1, 1, 6).merge();
  sheet.getRange(rij, 1)
    .setValue("Opmerkingen: _______________________________________________________________")
    .setBackground("#F3F3F3");
  rij++;

  rij += 2; // Extra ruimte voor page break

  // Voeg page break toe
  sheet.setRowBreak(rij - 1);

  return rij;
}

/**
 * Genereer wedstrijdparen voor een poule
 * @param {number} aantalJudokas - Het aantal judoka's in de poule
 * @return {Array} Array met wedstrijdparen {wit: nummer, blauw: nummer}
 */
function genereerWedstrijdparen(aantalJudokas) {
  const wedstrijden = [];

  if (aantalJudokas === 3) {
    // Dubbele poule: iedereen 2x tegen elkaar
    const combinaties = [[1, 2], [1, 3], [2, 3]];
    for (const combi of combinaties) {
      wedstrijden.push({ wit: combi[0], blauw: combi[1] });
    }
    // Tweede ronde
    for (const combi of combinaties) {
      wedstrijden.push({ wit: combi[0], blauw: combi[1] });
    }
  } else if (aantalJudokas === 4) {
    // Standaard volgorde voor 4 judoka's
    wedstrijden.push({ wit: 1, blauw: 2 });
    wedstrijden.push({ wit: 3, blauw: 4 });
    wedstrijden.push({ wit: 1, blauw: 3 });
    wedstrijden.push({ wit: 2, blauw: 4 });
    wedstrijden.push({ wit: 1, blauw: 4 });
    wedstrijden.push({ wit: 2, blauw: 3 });
  } else {
    // Voor andere aantallen: alle combinaties
    for (let i = 1; i <= aantalJudokas; i++) {
      for (let j = i + 1; j <= aantalJudokas; j++) {
        wedstrijden.push({ wit: i, blauw: j });
      }
    }
  }

  return wedstrijden;
}

/**
 * Maakt een naam kort voor in de wedstrijdtabel
 * @param {string} naam - Volledige naam
 * @param {string} club - Club naam
 * @return {string} Korte weergave: "Achternaam, V. (Club kort)"
 */
function maakNaamKort(naam, club) {
  if (!naam) return "";

  const delen = naam.split(' ');
  if (delen.length === 1) {
    // Alleen achternaam
    return `${naam} (${maakClubKort(club)})`;
  }

  // Laatste deel is achternaam, eerste deel(en) zijn voornaam
  const achternaam = delen[delen.length - 1];
  const voornaam = delen[0];
  const voorletter = voornaam.charAt(0).toUpperCase();

  return `${achternaam}, ${voorletter}. (${maakClubKort(club)})`;
}

/**
 * Maakt een club naam kort
 * @param {string} club - Volledige club naam
 * @return {string} Korte weergave (max 15 karakters)
 */
function maakClubKort(club) {
  if (!club) return "";
  if (club.length <= 15) return club;

  // Probeer afkorten op spaties
  const woorden = club.split(' ');
  if (woorden.length > 1) {
    // Neem eerste letters van elk woord
    return woorden.map(w => w.charAt(0).toUpperCase()).join('');
  }

  // Anders gewoon afkappen
  return club.substring(0, 12) + "...";
}

/**
 * Genereer wedstrijdschema's voor alle blokken
 * Menu functie die vraagt voor welk blok
 */
function genereerWedstrijdschemas() {
  const ui = SpreadsheetApp.getUi();
  const aantalBlokken = getAantalBlokken();

  const response = ui.prompt(
    'Wedstrijdschema\'s genereren',
    `Voor welk blok wil je wedstrijdschema's genereren? (1-${aantalBlokken}):`,
    ui.ButtonSet.OK_CANCEL
  );

  if (response.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  const blokNr = parseInt(response.getResponseText());
  if (isNaN(blokNr) || blokNr < 1 || blokNr > aantalBlokken) {
    ui.alert('Ongeldig bloknummer', `Voer een geldig bloknummer in tussen 1 en ${aantalBlokken}.`, ui.ButtonSet.OK);
    return;
  }

  genereerWedstrijdschemasBlok(blokNr);
}

/**
 * Genereer wedstrijdschema's voor Blok 1
 */
function genereerWedstrijdschemasBlok1() {
  genereerWedstrijdschemasBlok(1);
}

/**
 * Genereer wedstrijdschema's voor Blok 2
 */
function genereerWedstrijdschemasBlok2() {
  genereerWedstrijdschemasBlok(2);
}

/**
 * Genereer wedstrijdschema's voor Blok 3
 */
function genereerWedstrijdschemasBlok3() {
  genereerWedstrijdschemasBlok(3);
}

/**
 * Genereer wedstrijdschema's voor Blok 4
 */
function genereerWedstrijdschemasBlok4() {
  genereerWedstrijdschemasBlok(4);
}

/**
 * Genereer wedstrijdschema's voor Blok 5
 */
function genereerWedstrijdschemasBlok5() {
  genereerWedstrijdschemasBlok(5);
}

/**
 * Genereer wedstrijdschema's voor Blok 6
 */
function genereerWedstrijdschemasBlok6() {
  genereerWedstrijdschemasBlok(6);
}

/**
 * Print wedstrijdschema's voor Blok 1
 */
function printWedstrijdschemasBlok1() {
  printWedstrijdschemasVoorBlok(1);
}

/**
 * Print wedstrijdschema's voor Blok 2
 */
function printWedstrijdschemasBlok2() {
  printWedstrijdschemasVoorBlok(2);
}

/**
 * Print wedstrijdschema's voor Blok 3
 */
function printWedstrijdschemasBlok3() {
  printWedstrijdschemasVoorBlok(3);
}

/**
 * Print wedstrijdschema's voor Blok 4
 */
function printWedstrijdschemasBlok4() {
  printWedstrijdschemasVoorBlok(4);
}

/**
 * Print wedstrijdschema's voor Blok 5
 */
function printWedstrijdschemasBlok5() {
  printWedstrijdschemasVoorBlok(5);
}

/**
 * Print wedstrijdschema's voor Blok 6
 */
function printWedstrijdschemasBlok6() {
  printWedstrijdschemasVoorBlok(6);
}

/**
 * Genereer (indien nodig) en open wedstrijdschema's voor een blok om te printen
 * @param {number} blokNr - Het bloknummer
 */
function printWedstrijdschemasVoorBlok(blokNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  const sheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  let schemaSheet = ss.getSheetByName(sheetNaam);

  // Genereer schema's als ze nog niet bestaan
  if (!schemaSheet) {
    genereerWedstrijdschemasBlok(blokNr);
    schemaSheet = ss.getSheetByName(sheetNaam);
  }

  // Activeer het tabblad
  if (schemaSheet) {
    schemaSheet.activate();

    ui.alert(
      'Wedstrijdschema\'s Blok ' + blokNr,
      `Het tabblad met wedstrijdschema's voor Blok ${blokNr} is geopend.\n\n` +
      `Ga naar: Bestand > Afdrukken > Hele werkblad\n\n` +
      `Of selecteer een specifieke poule en print alleen die selectie.`,
      ui.ButtonSet.OK
    );
  }
}

// ==================== DIGITALE WEDSTRIJDSCHEMA'S ====================

/**
 * Genereert digitale wedstrijdschema's met schaakbordpatroon voor een specifiek blok
 * @param {number} blokNr - Het bloknummer (1-6)
 */
function genereerDigitaleWedstrijdschemasBlok(blokNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer of het blok bestaat
  const aantalBlokken = getAantalBlokken();
  if (blokNr < 1 || blokNr > aantalBlokken) {
    ui.alert('Ongeldig bloknummer', `Blok ${blokNr} bestaat niet.`, ui.ButtonSet.OK);
    return;
  }

  // Haal poule-informatie op
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" is niet gevonden.', ui.ButtonSet.OK);
    return;
  }

  // Verzamel alle poules voor dit blok
  // Gebruik getDisplayValues() om problemen met hyperlinks/formules te voorkomen
  const pouleData = poulesSheet.getDataRange().getDisplayValues();
  const headers = pouleData[0];

  const blokIdx = headers.indexOf("Blok");
  const matIdx = headers.indexOf("Mat");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const pouleTitelIdx = headers.indexOf("Pouletitel");
  const naamIdx = headers.indexOf("Naam");
  const aanwezigIdx = headers.indexOf("Aanwezig");
  const leeftijdsKlasseIdx = headers.indexOf("Leeftijdsklasse");
  const gewichtsKlasseIdx = headers.indexOf("Gewichtsklasse");

  // Debug logging
  Logger.log(`Headers gevonden: Leeftijdsklasse idx=${leeftijdsKlasseIdx}, Gewichtsklasse idx=${gewichtsKlasseIdx}`);
  if (leeftijdsKlasseIdx === -1 || gewichtsKlasseIdx === -1) {
    Logger.log(`WAARSCHUWING: Leeftijdsklasse of Gewichtsklasse kolom niet gevonden in headers!`);
    Logger.log(`Beschikbare headers: ${headers.join(', ')}`);
  }

  // Tel judoka's per poule
  const poulesInfo = {};

  for (let i = 1; i < pouleData.length; i++) {
    const row = pouleData[i];
    // Converteer numerieke waarden (getDisplayValues geeft alles als string)
    const pouleBlok = row[blokIdx] ? parseInt(row[blokIdx]) : 0;
    const mat = row[matIdx] ? parseInt(row[matIdx]) : 0;
    const pouleNr = row[pouleNrIdx] ? parseInt(row[pouleNrIdx]) : 0;
    const pouleTitel = row[pouleTitelIdx];
    const naam = row[naamIdx];
    const aanwezig = aanwezigIdx !== -1 ? row[aanwezigIdx] : "";

    // Skip als niet het juiste blok
    if (pouleBlok !== blokNr) continue;
    if (!mat || !pouleNr || !naam) continue;
    if (aanwezig === "Nee") continue;

    // Initialiseer poule
    if (!poulesInfo[pouleNr]) {
      // Haal leeftijdsklasse en gewichtsklasse uit pouletitel als kolommen leeg zijn
      // Pouletitel format: "Mini's -20 kg Poule 1" of "Mini's -20 kg"
      let leeftijdsklasse = String(row[leeftijdsKlasseIdx] || '');
      let gewichtsklasse = String(row[gewichtsKlasseIdx] || '');

      // Als leeg, probeer te parsen uit pouleTitel
      if (!leeftijdsklasse || !gewichtsklasse) {
        const titelMatch = String(pouleTitel).match(/^(.+?)\s+([\+\-]?\d+\s+kg)/i);
        if (titelMatch) {
          if (!leeftijdsklasse) leeftijdsklasse = titelMatch[1].trim();
          if (!gewichtsklasse) gewichtsklasse = titelMatch[2].trim();
        }
      }

      // Converteer alle waarden naar string om problemen met formulas/hyperlinks te voorkomen
      poulesInfo[pouleNr] = {
        pouleNr: pouleNr,
        pouleTitel: String(pouleTitel || ''),
        leeftijdsklasse: leeftijdsklasse,
        gewichtsklasse: gewichtsklasse,
        mat: mat,
        judokas: []
      };

      Logger.log(`  Poule ${pouleNr} geïnitialiseerd: "${leeftijdsklasse}" "${gewichtsklasse}"`);
    } else {
      // Update leeftijdsklasse en gewichtsklasse als deze nog leeg zijn (van eerste judoka)
      if (!poulesInfo[pouleNr].leeftijdsklasse && row[leeftijdsKlasseIdx]) {
        poulesInfo[pouleNr].leeftijdsklasse = String(row[leeftijdsKlasseIdx]);
      }
      if (!poulesInfo[pouleNr].gewichtsklasse && row[gewichtsKlasseIdx]) {
        poulesInfo[pouleNr].gewichtsklasse = String(row[gewichtsKlasseIdx]);
      }
    }

    // Converteer naar string om problemen met formulas/hyperlinks te voorkomen
    const clubIdx = headers.indexOf("Club");
    const naamStr = String(naam || "");
    const clubStr = clubIdx !== -1 ? String(row[clubIdx] || "") : "";

    // Debug logging voor poule 11
    if (pouleNr === 11) {
      Logger.log(`Poule 11 - Judoka: ${naamStr}, Club: ${clubStr}, Type naam: ${typeof naam}, Type club: ${typeof row[clubIdx]}`);
    }

    poulesInfo[pouleNr].judokas.push({
      naam: naamStr,
      club: clubStr
    });
  }

  // Maak of verwijder bestaand tabblad
  const sheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  let schemaSheet = ss.getSheetByName(sheetNaam);
  if (schemaSheet) {
    try {
      // Probeer eerst alle formules te verwijderen om externe referenties te voorkomen
      schemaSheet.clearContents();
      SpreadsheetApp.flush(); // Forceer update
      ss.deleteSheet(schemaSheet);
      schemaSheet = null;
    } catch (e) {
      Logger.log(`Waarschuwing: kon oud tabblad niet verwijderen: ${e.message}`);
      schemaSheet = null; // Reset zodat we een nieuw maken
    }
  }

  // Maak nieuw tabblad achteraan
  if (!schemaSheet) {
    const aantalSheets = ss.getSheets().length;
    schemaSheet = ss.insertSheet(sheetNaam, aantalSheets);
  }

  // Groepeer poules per mat
  const poulesPerMat = {};
  for (const pouleNr in poulesInfo) {
    const poule = poulesInfo[pouleNr];
    const matNr = poule.mat;
    if (!poulesPerMat[matNr]) {
      poulesPerMat[matNr] = [];
    }
    poulesPerMat[matNr].push(poule);
  }

  // Sorteer matten op nummer
  const matNummers = Object.keys(poulesPerMat).map(m => parseInt(m)).sort((a, b) => a - b);

  let huidigeRij = 1;

  // Header
  schemaSheet.getRange(huidigeRij, 1, 1, 20).merge();
  schemaSheet.getRange(huidigeRij, 1)
    .setValue(`WEDSTRIJDSCHEMA'S BLOK ${blokNr}`)
    .setFontWeight("bold")
    .setFontSize(16)
    .setBackground("#434343")
    .setFontColor("#FFFFFF")
    .setHorizontalAlignment("center");
  huidigeRij += 2;

  // ===== CONTROLE TABEL BOVENAAN (voor Presentator Dashboard) =====
  // Horizontale layout: 1 rij per mat, poules naast elkaar
  // Format: [Mat X] [Poule] [Afg] [Prj] [lege] [Poule] [Afg] [Prj] [lege] ...
  const controleTabelStartRij = huidigeRij;

  // Header rij
  schemaSheet.getRange(huidigeRij, 1).setValue("CONTROLE").setFontWeight("bold").setBackground("#4A86E8").setFontColor("#FFFFFF");
  huidigeRij++;

  // Reserveer 6 rijen (voor max 6 matten)
  const controleTabelDataStartRij = huidigeRij;
  huidigeRij += 6; // 6 matten
  huidigeRij += 2; // Extra buffer

  // Map om bij te houden welke poules op welke mat staan (voor later invullen)
  const controleTabelPoules = {}; // { matNr: [poule1, poule2, ...] }

  // Genereer schema's per mat
  let aantalSuccesvol = 0;
  for (const matNr of matNummers) {
    const poules = poulesPerMat[matNr];

    // Mat header
    schemaSheet.getRange(huidigeRij, 1, 1, 20).merge();
    schemaSheet.getRange(huidigeRij, 1)
      .setValue(`═══ MAT ${matNr} ═══`)
      .setFontWeight("bold")
      .setFontSize(14)
      .setBackground("#6FA8DC")
      .setFontColor("#FFFFFF")
      .setHorizontalAlignment("center");
    huidigeRij += 2;

    // Sorteer poules binnen deze mat op poulenummer
    poules.sort((a, b) => a.pouleNr - b.pouleNr);

    // Genereer schema voor elke poule op deze mat
    for (const poule of poules) {

      // Valideer poule data
      if (!poule || !poule.judokas || poule.judokas.length < 2) {
        Logger.log(`Poule ${poule.pouleNr} overgeslagen: te weinig judoka's (${poule ? poule.judokas.length : 0})`);
        continue;
      }

      // Check of poule te groot is voor digitaal schema (>10 judoka's = 45 wedstrijden = te breed)
      if (poule.judokas.length > 10) {
        Logger.log(`Poule ${poule.pouleNr} overgeslagen: te veel judoka's (${poule.judokas.length}) voor digitaal schema`);
        ui.alert('⚠️ Poule ' + poule.pouleNr + ' te groot voor digitaal schema',
          `Poule ${poule.pouleNr} heeft ${poule.judokas.length} judoka's.\n\n` +
          `Digitale schema's werken tot max. 10 judoka's.\n` +
          `Gebruik de Print-versie voor deze poule.`,
          ui.ButtonSet.OK);
        huidigeRij += 5;
        continue;
      }

      try {
        Logger.log(`Start genereren poule ${poule.pouleNr} met ${poule.judokas.length} judoka's`);
        const oudRij = huidigeRij; // Bewaar oude rij voor als het misgaat

        huidigeRij = maakDigitaalPouleSchema(schemaSheet, huidigeRij, poule, blokNr);
        Logger.log(`Poule ${poule.pouleNr} succesvol gegenereerd`);
        aantalSuccesvol++;

        // Voeg poule toe aan controle tabel map (per mat)
        if (!controleTabelPoules[matNr]) {
          controleTabelPoules[matNr] = [];
        }
        controleTabelPoules[matNr].push(poule.pouleNr);
      } catch (error) {
        Logger.log(`Fout bij genereren schema voor poule ${poule.pouleNr}: ${error.message}\n${error.stack}`);

        // Forceer flush om te voorkomen dat het spreadsheet beschadigd raakt
        try {
          SpreadsheetApp.flush();
        } catch (e) {
          Logger.log(`Flush failed: ${e.message}`);
        }

        // Probeer te achterhalen welke judoka het probleem veroorzaakt
        let judokaInfo = '';
        if (poule && poule.judokas) {
          judokaInfo = `\n\nJudoka's in deze poule:\n`;
          for (let i = 0; i < poule.judokas.length; i++) {
            const j = poule.judokas[i];
            judokaInfo += `${i+1}. ${j.naam || 'Geen naam'} (${j.club || 'Geen club'})\n`;
          }
        }

        // Toon error maar ga door met volgende poule
        ui.alert('⚠️ Fout bij poule ' + poule.pouleNr,
          'Poule ' + poule.pouleNr + ' kon niet gegenereerd worden:\n' +
          error.message + judokaInfo +
          '\n\nControleer of er geen hyperlinks of formules in de naam/club staan.\n' +
          'De andere poules worden wel gegenereerd.',
          ui.ButtonSet.OK);
        huidigeRij += 5; // Skip wat ruimte voor deze poule
      }
    }

    huidigeRij += 2; // Extra ruimte tussen matten
  }

  // Schrijf controle tabel horizontaal: 1 rij per mat
  let controleTabelRij = controleTabelDataStartRij;
  for (const matNr of matNummers) {
    const poules = controleTabelPoules[matNr] || [];
    if (poules.length === 0) continue;

    // Kolom A: Mat label
    schemaSheet.getRange(controleTabelRij, 1).setValue(`Mat ${matNr}`).setFontWeight("bold");

    // Schrijf poules horizontaal vanaf kolom C
    let col = 3; // Start kolom C
    for (const pouleNr of poules) {
      // Poule nummer
      schemaSheet.getRange(controleTabelRij, col).setValue(pouleNr).setHorizontalAlignment("center");
      // Afgerond (leeg, wordt later gevuld door MatBeheer)
      schemaSheet.getRange(controleTabelRij, col + 1).setValue("").setHorizontalAlignment("center").setFontSize(16);
      // Prijsuitreiking (leeg, wordt later gevuld door Presentator)
      schemaSheet.getRange(controleTabelRij, col + 2).setValue("").setHorizontalAlignment("center").setFontSize(16);

      col += 4; // Volgende poule: 3 kolommen + 1 lege cel = 4
    }

    controleTabelRij++;
  }

  Logger.log(`Controle tabel gegenereerd: ${matNummers.length} matten met totaal ${Object.values(controleTabelPoules).flat().length} poules`);

  // Tel totaal aantal poules
  const totaalPoules = Object.keys(poulesInfo).length;
  Logger.log(`${aantalSuccesvol} van ${totaalPoules} poules succesvol gegenereerd`);

  schemaSheet.activate();

  const succesmelding = aantalSuccesvol === totaalPoules
    ? `Alle ${aantalSuccesvol} poules succesvol gegenereerd!`
    : `${aantalSuccesvol} van ${totaalPoules} poules gegenereerd. Sommige poules hadden fouten.`;

  ui.alert(
    'Digitale wedstrijdschema\'s gegenereerd',
    `${succesmelding}\n\n` +
    `Tabblad: "${sheetNaam}"\n\n` +
    `Vul de wedstrijdpunten (WP) en judopunten (JP) in. De totalen en plaatsen worden automatisch berekend.`,
    ui.ButtonSet.OK
  );
}

/**
 * Maakt een digitaal wedstrijdschema met schaakbordpatroon voor één poule
 * NIEUWE VERSIE: Kolommen = chronologische wedstrijden (Wed 1, Wed 2, Wed 3...)
 * Bij 5 judoka's: 10 wedstrijden totaal als kolommen
 * Per wedstrijd: alleen de 2 judoka's die spelen hebben open vakjes
 * @param {Sheet} sheet - Het sheet
 * @param {number} startRij - De startrij
 * @param {Object} poule - Poule informatie met judokas
 * @param {number} blokNr - Het bloknummer
 * @return {number} De volgende beschikbare rij
 */
function maakDigitaalPouleSchema(sheet, startRij, poule, blokNr) {
  let rij = startRij;
  const aantalJudokas = poule.judokas.length;

  // Genereer optimale wedstrijdvolgorde
  const wedstrijdvolgorde = genereerOptimaleWedstrijdvolgorde(aantalJudokas);
  const aantalWedstrijden = wedstrijdvolgorde.length; // Totaal aantal wedstrijden

  // Poule header
  // Nr + Naam + (wedstrijden * 2) + WP + JP + Plaats + Afgerond + Prijs = 2 + (wedstrijden * 2) + 5
  const dataKolommen = 2 + aantalWedstrijden * 2 + 3; // Nr, Naam, Wedstrijden, WP, JP, Plts
  const totaalKolommen = dataKolommen + 2; // + Afgerond + Prijsuitreiking

  // Debug logging
  Logger.log(`Poule ${poule.pouleNr}: ${aantalJudokas} judoka's, ${aantalWedstrijden} wedstrijden, ${dataKolommen} data kolommen, ${totaalKolommen} totaal kolommen`);

  // Veiligheidscheck: zorg dat we niet te veel kolommen gebruiken
  if (totaalKolommen > 50) {
    throw new Error(`Poule te groot voor digitaal schema: ${aantalJudokas} judoka's = ${aantalWedstrijden} wedstrijden = ${totaalKolommen} kolommen (max 50)`);
  }

  // EERST alle oude opmaak wissen van deze rij (voorkomen dat oude kleuren blijven hangen)
  const maxKolommen = 50; // Wis tot kolom 50 om zeker te zijn
  sheet.getRange(rij, 1, 1, maxKolommen).setBackground(null).setFontColor(null);

  // Zet header balk - alleen over de breedte van het schema (tot en met Plts)
  const headerRange = sheet.getRange(rij, 1, 1, dataKolommen);
  headerRange.setBackground("#4A86E8").setFontColor("#FFFFFF");

  // Zet titel in het midden van de balk
  const middenKolom = Math.floor(dataKolommen / 2) + 1;
  sheet.getRange(rij, middenKolom)
    .setValue(`Poule ${poule.pouleNr} - ${poule.leeftijdsklasse} ${poule.gewichtsklasse} | Blok ${blokNr} - Mat ${poule.mat}`)
    .setFontWeight("bold")
    .setFontSize(12)
    .setHorizontalAlignment("center");

  // Afgerond vinkje: IN de titelbalk, laatste cel (boven Plts)
  const afgerondKolom = dataKolommen; // Laatste kolom van de balk
  sheet.getRange(rij, afgerondKolom)
    .setValue("") // Leeg, komt vinkje als afgerond
    .setBackground("#4A86E8")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(16)
    .setHorizontalAlignment("center");

  // Prijs vinkje: BUITEN de titelbalk, rechts ernaast
  const prijsKolom = dataKolommen + 1; // Direct na de balk
  sheet.getRange(rij, prijsKolom)
    .setValue("") // Leeg, komt vinkje als prijsuitreiking gedaan
    .setBackground("#FFFFFF") // Wit, niet onderdeel van balk
    .setFontColor("#10b981") // Groen vinkje als aanwezig
    .setFontWeight("bold")
    .setFontSize(16)
    .setHorizontalAlignment("center")
    .setBorder(true, true, true, true, null, null, "#000000", SpreadsheetApp.BorderStyle.SOLID);

  rij++;

  // Headers: Nr | Naam | Wed 1 | Wed 2 | Wed 3 | ... | WP | JP | Plaats
  sheet.getRange(rij, 1).setValue("Nr").setFontWeight("bold").setBackground("#D9D9D9").setHorizontalAlignment("center");
  sheet.getRange(rij, 2).setValue("Naam").setFontWeight("bold").setBackground("#D9D9D9").setHorizontalAlignment("center");

  let col = 3;
  for (let w = 1; w <= aantalWedstrijden; w++) {
    sheet.getRange(rij, col, 1, 2).merge();
    sheet.getRange(rij, col)
      .setValue(`Wed ${w}`)
      .setFontWeight("bold")
      .setBackground("#D9D9D9")
      .setHorizontalAlignment("center");
    col += 2;
  }

  sheet.getRange(rij, col).setValue("WP").setFontWeight("bold").setBackground("#FFE599").setHorizontalAlignment("center");
  sheet.getRange(rij, col + 1).setValue("JP").setFontWeight("bold").setBackground("#FFE599").setHorizontalAlignment("center");
  sheet.getRange(rij, col + 2).setValue("Plts").setFontWeight("bold").setBackground("#dbeafe").setHorizontalAlignment("center");
  rij++;

  // Sub-headers voor WP/JP
  sheet.getRange(rij, 1).setBackground("#D9D9D9");
  sheet.getRange(rij, 2).setBackground("#D9D9D9");

  col = 3;
  for (let w = 1; w <= aantalWedstrijden; w++) {
    sheet.getRange(rij, col).setValue("WP").setFontSize(9).setBackground("#E6E6E6").setHorizontalAlignment("center");
    sheet.getRange(rij, col + 1).setValue("JP").setFontSize(9).setBackground("#E6E6E6").setHorizontalAlignment("center");
    col += 2;
  }
  sheet.getRange(rij, col, 1, 3).setBackground("#E6E6E6");
  rij++;

  // Judoka rijen
  for (let i = 0; i < aantalJudokas; i++) {
    const judoka = poule.judokas[i];
    const judokaNummer = i + 1;

    // Nummer
    sheet.getRange(rij, 1).setValue(judokaNummer).setBackground("#F3F3F3").setHorizontalAlignment("center").setFontWeight("bold");

    // Naam
    sheet.getRange(rij, 2).setValue(judoka.naam).setBackground("#F3F3F3");

    // Wedstrijdcellen
    col = 3;
    const wpCelAdressen = []; // Verzamel alle WP cel adressen voor deze judoka
    const jpCelAdressen = []; // Verzamel alle JP cel adressen voor deze judoka

    for (let w = 0; w < aantalWedstrijden; w++) {
      const wedstrijd = wedstrijdvolgorde[w];

      // Check of deze judoka in deze wedstrijd speelt
      const speeltInDezeWedstrijd = (wedstrijd.judoka1 === judokaNummer || wedstrijd.judoka2 === judokaNummer);

      // Kleuren:
      // - Spelende judoka's: ALTIJD WIT (#FFFFFF) met zwarte tekst
      // - Niet-spelende judoka's: donkergrijs (#666666)
      const witKleur = "#FFFFFF";
      const zwarteTekst = "#000000";
      const donkerGrijs = "#666666";

      // WP cel
      const wpCel = sheet.getRange(rij, col);
      if (speeltInDezeWedstrijd) {
        wpCel.setBackground(witKleur)
          .setFontColor(zwarteTekst)
          .setHorizontalAlignment("center")
          .setBorder(true, true, true, true, false, false);
        // Voeg WP cel adres toe aan lijst
        wpCelAdressen.push(columnToLetter(col) + rij);
      } else {
        // Donker grijs vakje voor judoka's die niet spelen - GEEN waarde zetten
        wpCel.setBackground(donkerGrijs)
          .setHorizontalAlignment("center")
          .setBorder(true, true, true, true, false, false);
      }

      // JP cel
      const jpCel = sheet.getRange(rij, col + 1);
      if (speeltInDezeWedstrijd) {
        jpCel.setBackground(witKleur)
          .setFontColor(zwarteTekst)
          .setHorizontalAlignment("center")
          .setBorder(true, true, true, true, false, false);

        // TIJDELIJK UITGESCHAKELD - data validation te traag
        // Voeg dropdown toe met JP waarden (0, 5, 7, 10)
        // const rule = SpreadsheetApp.newDataValidation()
        //   .requireValueInList([0, 5, 7, 10], true)
        //   .setAllowInvalid(false)
        //   .build();
        // jpCel.setDataValidation(rule);

        // Voeg JP cel adres toe aan lijst
        jpCelAdressen.push(columnToLetter(col + 1) + rij);
      } else {
        // Donker grijs vakje voor judoka's die niet spelen - GEEN waarde zetten
        jpCel.setBackground(donkerGrijs)
          .setHorizontalAlignment("center")
          .setBorder(true, true, true, true, false, false);
      }

      col += 2;
    }

    // Totaal WP - som van alleen de WP cellen (waar deze judoka speelt)
    let wpFormule = "=0";
    if (wpCelAdressen.length > 0) {
      wpFormule = `=${wpCelAdressen.join("+")}`;
    }

    sheet.getRange(rij, col)
      .setFormula(wpFormule)
      .setBackground("#FFE599")
      .setHorizontalAlignment("center")
      .setFontWeight("bold");

    // Totaal JP - som van alleen de JP cellen (waar deze judoka speelt)
    let jpFormule = "=0";
    if (jpCelAdressen.length > 0) {
      jpFormule = `=${jpCelAdressen.join("+")}`;
    }

    sheet.getRange(rij, col + 1)
      .setFormula(jpFormule)
      .setBackground("#FFE599")
      .setHorizontalAlignment("center")
      .setFontWeight("bold");

    // Plaats - laat leeg, gebruiker vult handmatig in op basis van WP en JP totalen
    // Of laat het voorlopig leeg totdat we een werkende formule hebben
    sheet.getRange(rij, col + 2)
      .setValue("")
      .setBackground("#A4C2F4")
      .setHorizontalAlignment("center")
      .setFontWeight("bold");

    rij++;
  }

  // Kolombreedte aanpassen
  sheet.setColumnWidth(1, 40);   // Nr

  // Naam kolom - auto resize (past zich automatisch aan aan breedste naam)
  sheet.autoResizeColumn(2);

  for (let w = 0; w < aantalWedstrijden; w++) {
    sheet.setColumnWidth(3 + w * 2, 35);     // WP - smaller
    sheet.setColumnWidth(3 + w * 2 + 1, 35); // JP - smaller
  }

  const totaalCol = 3 + aantalWedstrijden * 2;
  sheet.setColumnWidth(totaalCol, 45);     // WP totaal - smaller
  sheet.setColumnWidth(totaalCol + 1, 45); // JP totaal - smaller
  sheet.setColumnWidth(totaalCol + 2, 45); // Plts - smaller

  rij += 3; // Extra ruimte tussen poules

  return rij;
}

/**
 * Hulpfunctie: converteer kolomnummer naar letter (1 = A, 2 = B, etc.)
 */
function columnToLetter(column) {
  let temp, letter = '';
  while (column > 0) {
    temp = (column - 1) % 26;
    letter = String.fromCharCode(temp + 65) + letter;
    column = (column - temp - 1) / 26;
  }
  return letter;
}

/**
 * Digitale wedstrijdschema's voor Blok 1
 */
function digitaalWedstrijdschemaBlok1() {
  genereerDigitaleWedstrijdschemasBlok(1);
}

/**
 * Digitale wedstrijdschema's voor Blok 2
 */
function digitaalWedstrijdschemaBlok2() {
  genereerDigitaleWedstrijdschemasBlok(2);
}

/**
 * Digitale wedstrijdschema's voor Blok 3
 */
function digitaalWedstrijdschemaBlok3() {
  genereerDigitaleWedstrijdschemasBlok(3);
}

/**
 * Digitale wedstrijdschema's voor Blok 4
 */
function digitaalWedstrijdschemaBlok4() {
  genereerDigitaleWedstrijdschemasBlok(4);
}

/**
 * Digitale wedstrijdschema's voor Blok 5
 */
function digitaalWedstrijdschemaBlok5() {
  genereerDigitaleWedstrijdschemasBlok(5);
}

/**
 * Digitale wedstrijdschema's voor Blok 6
 */
function digitaalWedstrijdschemaBlok6() {
  genereerDigitaleWedstrijdschemasBlok(6);
}
