// PresentatorBeheer.js - Backend voor Presentator Dashboard

/**
 * DEBUG: Test functie om header rij van eerste poule in Blok 1 te inspecteren
 */
function debugWedstrijdschemaHeader() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Log alle sheet namen eerst
  Logger.log('=== ALLE SHEETS ===');
  const allSheets = ss.getSheets();
  allSheets.forEach(s => Logger.log(`  - ${s.getName()}`));

  // Zoek sheet die begint met "Wedstrijdschema"
  let sheet = null;
  for (const s of allSheets) {
    if (s.getName().includes('Wedstrijdschema') && s.getName().includes('Blok')) {
      sheet = s;
      Logger.log(`\n=== GEVONDEN SHEET: ${s.getName()} ===`);
      break;
    }
  }

  if (!sheet) {
    Logger.log('ERROR: Geen Wedstrijdschema sheet gevonden!');
    return;
  }

  const data = sheet.getDataRange().getValues();
  Logger.log(`Sheet heeft ${data.length} rijen`);

  // Zoek eerste poule header
  for (let rij = 0; rij < data.length; rij++) {
    const celData = data[rij];

    if (celData[0] && typeof celData[0] === 'string' && celData[0].startsWith('Poule ')) {
      Logger.log(`\n=== POULE HEADER GEVONDEN OP RIJ ${rij + 1} ===`);
      Logger.log(`Header tekst: "${celData[0]}"`);
      Logger.log(`\nAlle cellen in deze rij (totaal ${celData.length}):`);

      for (let col = 0; col < celData.length; col++) {
        const waarde = celData[col];
        if (waarde !== "" && waarde !== null && waarde !== undefined) {
          const charCodes = [];
          for (let i = 0; i < waarde.toString().length; i++) {
            charCodes.push(waarde.toString().charCodeAt(i));
          }
          Logger.log(`  Col ${col}: "${waarde}"`);
          Logger.log(`    - Type: ${typeof waarde}`);
          Logger.log(`    - Length: ${waarde.toString().length}`);
          Logger.log(`    - Char codes: [${charCodes.join(', ')}]`);
          Logger.log(`    - Equals "✓": ${waarde === "✓"}`);
          Logger.log(`    - Includes "✓": ${waarde.toString().includes("✓")}`);
        }
      }

      Logger.log('\n✓ character info:');
      Logger.log(`  - Char code: ${("✓").charCodeAt(0)}`);

      break; // Stop na eerste poule
    }
  }

  Logger.log('\n=== DEBUG KLAAR ===');
}

/**
 * Open Presentator Login dialoog
 */
function openPresentatorLogin() {
  const html = HtmlService.createHtmlOutputFromFile('PresentatorLogin')
    .setWidth(500)
    .setHeight(600);
  SpreadsheetApp.getUi().showModalDialog(html, 'Presentator Login');
}

/**
 * Open Presentator Dashboard
 */
function openPresentatorDashboard() {
  const html = HtmlService.createHtmlOutputFromFile('PresentatorDashboard')
    .setWidth(1000)
    .setHeight(700);
  SpreadsheetApp.getUi().showModalDialog(html, 'Presentator Dashboard - Uitslagen');
}

/**
 * Lees poule details (titel + judokas met WP/JP/Plaats) uit wedstrijdschema
 * @param {Sheet} sheet - Het wedstrijdschema sheet
 * @param {number} pouleNr - Het poulenummer
 * @param {number} blokNr - Het bloknummer
 * @return {Object} Object met pouleTitel en judokas array
 */
function leesPouleDetailsUitSchema(sheet, pouleNr, blokNr) {
  try {
    Logger.log(`leesPouleDetailsUitSchema: Poule ${pouleNr}, Blok ${blokNr}`);

    // Zoek de poule header in het sheet
    // Headers beginnen met "Poule X - " in een gemerged cel
    const data = sheet.getDataRange().getValues();
    let pouleHeaderRij = -1;

    Logger.log(`  Zoek in ${data.length} rijen naar poule header...`);

    for (let r = 0; r < data.length; r++) {
      // Check ALLE kolommen in de rij (gemerged cel kan overal staan)
      for (let c = 0; c < data[r].length; c++) {
        const val = String(data[r][c] || '');
        // Zoek naar cel met "Poule X" EN "Blok Y" (gemerged cel bevat volledige tekst)
        if (val.includes(`Poule ${pouleNr}`) && val.includes(`Blok ${blokNr}`)) {
          Logger.log(`  Gevonden op rij ${r + 1}, kolom ${c + 1}: "${val}"`);
          pouleHeaderRij = r;
          break;
        }
      }
      if (pouleHeaderRij !== -1) break;
    }

    if (pouleHeaderRij === -1) {
      Logger.log(`  FOUT: Poule ${pouleNr} niet gevonden in schema`);
      // Log eerste 30 rijen om te debuggen
      for (let r = 0; r < Math.min(30, data.length); r++) {
        for (let c = 0; c < data[r].length; c++) {
          const val = String(data[r][c] || '');
          if (val.includes('Poule')) {
            Logger.log(`  Rij ${r + 1}, Kolom ${c + 1}: "${val}"`);
          }
        }
      }
      return { pouleTitel: `Poule ${pouleNr}`, judokas: [] };
    }

    // Parse de titel uit de header cel
    // Formaat: "Poule X - Leeftijdsklasse Gewichtsklasse | Blok Y - Mat Z"
    const headerTekst = String(data[pouleHeaderRij].find(v => v && String(v).includes(`Poule ${pouleNr}`)) || '');
    let pouleTitel = `Poule ${pouleNr}`;
    const titelMatch = headerTekst.match(/Poule \d+ - (.+?)\s+\|/);
    if (titelMatch) {
      pouleTitel = titelMatch[1].trim(); // "Leeftijdsklasse Gewichtsklasse"
    }

    Logger.log(`  Poule ${pouleNr} gevonden op rij ${pouleHeaderRij + 1}, titel: ${pouleTitel}`);

    // Judoka tabel begint 3 rijen na header:
    // Header rij (gemerged)
    // Kolom headers (Nr, Naam, Wed 1, ...)
    // Sub headers (WP, JP, ...)
    // Dan judoka rijen
    const judokaTabelStartRij = pouleHeaderRij + 3;

    // Zoek de kolommen voor WP totaal, JP totaal, Plaats
    // Deze staan na alle wedstrijdkolommen
    const headerRij = data[pouleHeaderRij + 1]; // Kolom headers
    let wpKolom = -1;
    let jpKolom = -1;
    let plaatsKolom = -1;

    // Zoek van rechts naar links voor WP, JP, Plts (laatste kolommen)
    for (let c = headerRij.length - 1; c >= 0; c--) {
      const val = String(headerRij[c] || '').trim();
      if (val === 'Plts' && plaatsKolom === -1) plaatsKolom = c;
      if (val === 'JP' && jpKolom === -1) jpKolom = c;
      if (val === 'WP' && wpKolom === -1) wpKolom = c;
      if (wpKolom !== -1 && jpKolom !== -1 && plaatsKolom !== -1) break;
    }

    if (wpKolom === -1 || jpKolom === -1 || plaatsKolom === -1) {
      Logger.log(`  WAARSCHUWING: Kolommen niet gevonden (WP: ${wpKolom}, JP: ${jpKolom}, Plts: ${plaatsKolom})`);
      return { pouleTitel: pouleTitel, judokas: [] };
    }

    Logger.log(`  Kolommen: Naam=1 (B), WP=${wpKolom + 1}, JP=${jpKolom + 1}, Plts=${plaatsKolom + 1}`);

    // Lees judoka rijen
    const judokas = [];
    for (let r = judokaTabelStartRij; r < data.length; r++) {
      const naam = String(data[r][1] || '').trim(); // Kolom B (index 1) = Naam
      if (!naam) break; // Stop bij lege rij

      const wp = data[r][wpKolom] || 0;
      const jp = data[r][jpKolom] || 0;
      const plaats = data[r][plaatsKolom] || 0;

      judokas.push({
        naam: naam,
        wp: Number(wp),
        jp: Number(jp),
        plaats: Number(plaats)
      });
    }

    // Sorteer judokas op plaats (1 eerst)
    judokas.sort((a, b) => a.plaats - b.plaats);

    Logger.log(`  ${judokas.length} judoka's gelezen en gesorteerd op plaats`);

    return {
      pouleTitel: pouleTitel,
      judokas: judokas
    };

  } catch (error) {
    Logger.log(`  FOUT bij lezen poule details: ${error.message}`);
    return { pouleTitel: `Poule ${pouleNr}`, judokas: [] };
  }
}

/**
 * Haal voltooide poules op uit controle tabel van een specifiek blok
 * @param {number} blokNr - Het bloknummer (1-6)
 * @return {Array} Array van poule objecten met status
 */
function getUitslagenVoorBlok(blokNr) {
  Logger.log(`getUitslagenVoorBlok(${blokNr}) - START`);

  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
    const sheet = ss.getSheetByName(sheetNaam);

    if (!sheet) {
      Logger.log(`  ${sheetNaam} niet gevonden`);
      return [];
    }

    // Lees horizontale controle tabel (6 rijen voor matten, 40 kolommen breed)
    Logger.log(`  Lees horizontale controle tabel van ${sheetNaam}`);
    const controleTabelStartRij = 4; // Na header "CONTROLE"
    const controleTabelData = sheet.getRange(controleTabelStartRij, 1, 6, 40).getValues();

    const voltooidePoules = [];

    // Loop door alle mat-rijen
    for (let rijIndex = 0; rijIndex < controleTabelData.length; rijIndex++) {
      const rij = controleTabelData[rijIndex];
      const matLabel = rij[0]; // Kolom A: "Mat X"

      if (!matLabel) continue; // Skip lege rijen

      Logger.log(`  ${matLabel}:`);

      // Loop door poules in deze rij (vanaf kolom 2 = index 2, elke 4 kolommen)
      for (let col = 2; col < rij.length; col += 4) {
        const pouleNr = rij[col];       // Kolom C, G, K, O, ... (index 2, 6, 10, 14, ...)
        const afgerond = rij[col + 1];  // Kolom D, H, L, P, ... (Afgerond checkmark)
        const prijs = rij[col + 2];     // Kolom E, I, M, Q, ... (Prijs checkmark)

        // Stop bij lege poule
        if (!pouleNr) break;

        // Filter: alleen afgeronde poules die GEEN prijsuitreiking hebben gehad
        // Als prijsuitreiking al gedaan is, hoeft de poule niet meer getoond te worden
        if (afgerond === "✓" && prijs !== "✓") {
          // Lees judoka details uit wedstrijdschema
          const pouleDetails = leesPouleDetailsUitSchema(sheet, pouleNr, blokNr);

          voltooidePoules.push({
            pouleNr: pouleNr,
            blokNr: blokNr,
            pouleTitel: pouleDetails.pouleTitel,
            prijsuitreiking: false, // Altijd false omdat we gefilterd hebben op prijs !== "✓"
            judokas: pouleDetails.judokas
          });

          Logger.log(`    Poule ${pouleNr} - Afgerond: ✓, Wacht op prijsuitreiking, ${pouleDetails.judokas.length} judoka's`);
        } else if (afgerond === "✓" && prijs === "✓") {
          Logger.log(`    Poule ${pouleNr} - Afgerond: ✓, Prijs: ✓ (verborgen, cyclus compleet)`);
        }
      }
    }

    Logger.log(`getUitslagenVoorBlok(${blokNr}) - EINDE - ${voltooidePoules.length} voltooide poules`);
    return voltooidePoules;

  } catch (error) {
    Logger.log(`ERROR in getUitslagenVoorBlok(${blokNr}): ${error.toString()}`);
    Logger.log('Stack: ' + error.stack);
    throw new Error(`Fout bij ophalen uitslagen voor Blok ${blokNr}: ` + error.message);
  }
}

/**
 * Open een specifieke uitslag poule in de Uitslagen tab
 * @param {number} pouleNr - Het poulenummer
 */
function openUitslagPoule(pouleNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const uitslagenSheet = ss.getSheetByName("Uitslagen");

  if (!uitslagenSheet) {
    throw new Error('Uitslagen tab niet gevonden');
  }

  // Zoek de poule
  const data = uitslagenSheet.getDataRange().getValues();
  for (let i = 0; i < data.length; i++) {
    const rowText = data[i].join(' ');
    const match = rowText.match(/Poule (\d+)/);
    if (match && parseInt(match[1]) === pouleNr) {
      // Ga naar deze rij
      const headerRij = i + 1;
      uitslagenSheet.getRange(headerRij, 1).activate();
      ss.setActiveSheet(uitslagenSheet);
      return;
    }
  }

  throw new Error(`Poule ${pouleNr} niet gevonden in Uitslagen`);
}

/**
 * Verwijder een uitslag poule (afvinken)
 * @param {number} pouleNr - Het poulenummer
 */
function verwijderUitslagPoule(pouleNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const uitslagenSheet = ss.getSheetByName("Uitslagen");

  if (!uitslagenSheet) {
    throw new Error('Uitslagen tab niet gevonden');
  }

  // Zoek de poule
  const data = uitslagenSheet.getDataRange().getValues();
  for (let i = 0; i < data.length; i++) {
    const rowText = data[i].join(' ');
    const match = rowText.match(/Poule (\d+)/);
    if (match && parseInt(match[1]) === pouleNr) {
      // Verwijder deze poule (ongeveer 15 rijen)
      const startRij = i + 1;
      const aantalRijen = 15;

      uitslagenSheet.getRange(startRij, 1, aantalRijen, 50).clear().clearFormat();

      // Compacteer de sheet door lege rijen te verwijderen
      compacteerUitslagenSheet();

      return;
    }
  }

  throw new Error(`Poule ${pouleNr} niet gevonden in Uitslagen`);
}

/**
 * Compacteer de Uitslagen sheet door lege rijen te verwijderen
 */
function compacteerUitslagenSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const uitslagenSheet = ss.getSheetByName("Uitslagen");

  if (!uitslagenSheet) return;

  const data = uitslagenSheet.getDataRange().getValues();
  const nieuweData = [];

  // Behoud alleen niet-lege rijen
  for (let i = 0; i < data.length; i++) {
    const row = data[i];
    const isLeeg = row.every(cell => cell === '' || cell === null);
    if (!isLeeg) {
      nieuweData.push(row);
    }
  }

  // Wis de hele sheet en schrijf compacte data terug
  uitslagenSheet.clear().clearFormat();

  if (nieuweData.length > 0) {
    uitslagenSheet.getRange(1, 1, nieuweData.length, nieuweData[0].length).setValues(nieuweData);

    // Herstel de header opmaak
    uitslagenSheet.getRange(1, 1, 1, 20).merge();
    uitslagenSheet.getRange(1, 1)
      .setValue('UITSLAGEN - VOLTOOIDE POULES')
      .setFontWeight("bold")
      .setFontSize(16)
      .setBackground("#434343")
      .setFontColor("#FFFFFF")
      .setHorizontalAlignment("center");
  }
}

/**
 * Toggle prijsuitreiking status voor een poule
 * @param {number} pouleNr - Het poulenummer
 * @param {number} blokNr - Het bloknummer
 * @return {boolean} Nieuwe status van prijsuitreiking
 */
function togglePrijsuitreiking(pouleNr, blokNr) {
  Logger.log(`togglePrijsuitreiking() - Poule ${pouleNr}, Blok ${blokNr}`);

  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  const sheet = ss.getSheetByName(sheetNaam);

  if (!sheet) {
    throw new Error(`${sheetNaam} niet gevonden`);
  }

  // Zoek de poule header in het sheet
  const data = sheet.getDataRange().getValues();
  let pouleRij = -1;

  for (let i = 0; i < data.length; i++) {
    const cellValue = data[i].join(' ');
    if (cellValue.includes(`Poule ${pouleNr} -`)) {
      pouleRij = i + 1; // 1-indexed
      break;
    }
  }

  if (pouleRij === -1) {
    throw new Error(`Poule ${pouleNr} niet gevonden in ${sheetNaam}`);
  }

  Logger.log(`  Poule gevonden op rij ${pouleRij}`);

  // Bepaal de kolommen (moet consistent zijn met Wedstrijdschemas.js en MatBeheer.js)
  // We moeten het aantal wedstrijden weten om de juiste kolom te berekenen
  // Eenvoudigste manier: zoek de eerste ✓ (afgerond) en neem de volgende kolom voor prijsuitreiking

  let afgerondKolom = -1;
  for (let col = 0; col < data[pouleRij - 1].length; col++) {
    if (data[pouleRij - 1][col] === "✓") {
      afgerondKolom = col + 1; // 1-indexed
      break;
    }
  }

  if (afgerondKolom === -1) {
    throw new Error(`Poule ${pouleNr} is nog niet afgerond (geen afgerond vinkje gevonden)`);
  }

  const prijsKolom = afgerondKolom + 1; // Prijsuitreiking staat rechts van afgerond

  Logger.log(`  Afgerond kolom: ${afgerondKolom}, Prijs kolom: ${prijsKolom}`);

  // Lees huidige status
  const huidigeWaarde = sheet.getRange(pouleRij, prijsKolom).getValue();
  const nieuweStatus = huidigeWaarde !== "✓";

  Logger.log(`  Huidige waarde: "${huidigeWaarde}", Nieuwe status: ${nieuweStatus}`);

  // Update de cel
  const prijsCell = sheet.getRange(pouleRij, prijsKolom);
  prijsCell
    .setValue(nieuweStatus ? "✓" : "")
    .setBackground(nieuweStatus ? "#10b981" : "#10b981") // Groen behouden want poule is al afgerond
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(16)
    .setHorizontalAlignment("center");

  Logger.log(`  Prijsuitreiking status bijgewerkt naar: ${nieuweStatus}`);

  // Update ook de horizontale controle tabel
  updateControleTabelPrijsuitreiking(sheet, pouleNr, nieuweStatus);

  return nieuweStatus;
}

/**
 * Update de prijsuitreiking checkmark in de horizontale controle tabel
 * @param {Sheet} sheet - De wedstrijdschema sheet
 * @param {number} pouleNr - Het poule nummer
 * @param {boolean} prijsuitreiking - Of de prijsuitreiking gedaan is
 */
function updateControleTabelPrijsuitreiking(sheet, pouleNr, prijsuitreiking) {
  try {
    const controleTabelStartRij = 4;
    const controleTabelData = sheet.getRange(controleTabelStartRij, 1, 6, 40).getValues();

    // Zoek door alle mat-rijen
    for (let rijIndex = 0; rijIndex < controleTabelData.length; rijIndex++) {
      const rij = controleTabelData[rijIndex];

      // Loop door poules in deze rij (vanaf kolom 2 = index 2, elke 4 kolommen)
      for (let col = 2; col < rij.length; col += 4) {
        const tabelPouleNr = rij[col];

        if (tabelPouleNr === pouleNr) {
          const sheetRij = controleTabelStartRij + rijIndex;
          const sheetKol = col + 1; // +1 omdat array 0-indexed, sheet 1-indexed
          const prijsKol = sheetKol + 2; // Prijs kolom is 2 na poule nummer (poule | afg | prijs)

          // Update Prijsuitreiking kolom met checkmark
          sheet.getRange(sheetRij, prijsKol).setValue(prijsuitreiking ? "✓" : "");

          Logger.log(`  Controle tabel prijsuitreiking updated: Poule ${pouleNr} op rij ${sheetRij}, kolom ${prijsKol}, prijs = ${prijsuitreiking}`);
          return;
        }
      }
    }

    Logger.log(`  WAARSCHUWING: Poule ${pouleNr} niet gevonden in controle tabel voor prijsuitreiking update`);
  } catch (error) {
    Logger.log(`  FOUT bij update controle tabel prijsuitreiking voor poule ${pouleNr}: ${error.message}`);
  }
}
