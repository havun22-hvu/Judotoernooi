// MatBeheer.js - Server-side functies voor mat-gebaseerd wedstrijdschema systeem
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalBlokken, getAantalMatten)
 */

/**
 * Opent het mat login scherm
 */
function openMatLogin() {
  const html = HtmlService.createHtmlOutputFromFile('MatLogin')
    .setWidth(700)
    .setHeight(780)
    .setTitle('Mat Login - WestFries Open');

  SpreadsheetApp.getUi().showModalDialog(html, 'Mat Login');
}

/**
 * Login functie voor een mat
 * Opent direct de MatInterface na succesvolle login
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 */
function loginMatEnOpenInterface(blokNr, matNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Valideer blok en mat
  const aantalBlokken = getAantalBlokken();
  const aantalMatten = getAantalMatten();

  if (blokNr < 1 || blokNr > aantalBlokken) {
    throw new Error(`Ongeldig bloknummer: ${blokNr}`);
  }

  if (matNr < 1 || matNr > aantalMatten) {
    throw new Error(`Ongeldig matnummer: ${matNr}`);
  }

  // Check of PouleIndeling bestaat
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    throw new Error('Het tabblad "PouleIndeling" is niet gevonden.');
  }

  // Open direct de MatInterface
  openMatInterface(blokNr, matNr);
}

/**
 * Haalt alle poules op voor een specifieke mat
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 * @returns {Array} Array met poule informatie
 */
function getPoulesVoorMat(blokNr, matNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!poulesSheet) {
    throw new Error('Het tabblad "PouleIndeling" is niet gevonden.');
  }

  // Converteer naar nummers voor veilige vergelijking
  blokNr = parseInt(blokNr);
  matNr = parseInt(matNr);

  Logger.log(`getPoulesVoorMat aangeroepen voor Blok ${blokNr}, Mat ${matNr}`);

  const data = poulesSheet.getDataRange().getValues();
  const headers = data[0];

  // Vind kolom indices
  const blokIdx = headers.indexOf("Blok");
  const matIdx = headers.indexOf("Mat");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const pouleTitelIdx = headers.indexOf("Pouletitel");
  const naamIdx = headers.indexOf("Naam");
  const aanwezigIdx = headers.indexOf("Aanwezig");
  const leeftijdsKlasseIdx = headers.indexOf("Leeftijdsklasse");
  const gewichtsKlasseIdx = headers.indexOf("Gewichtsklasse");
  const clubIdx = headers.indexOf("Club");

  // Verzamel poules
  const poulesMap = {};

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const pouleBlok = parseInt(row[blokIdx]) || 0;
    const mat = parseInt(row[matIdx]) || 0;
    const pouleNr = row[pouleNrIdx];
    const pouleTitel = row[pouleTitelIdx];
    const naam = row[naamIdx];
    const aanwezig = aanwezigIdx !== -1 ? row[aanwezigIdx] : "";

    // Filter op blok en mat
    if (pouleBlok !== blokNr || mat !== matNr) continue;
    if (!naam || aanwezig === "Nee") continue;

    // Initialiseer poule als deze nog niet bestaat
    if (!poulesMap[pouleNr]) {
      poulesMap[pouleNr] = {
        pouleNr: pouleNr,
        pouleTitel: pouleTitel,
        leeftijdsklasse: row[leeftijdsKlasseIdx] || '',
        gewichtsklasse: row[gewichtsKlasseIdx] || '',
        blok: pouleBlok,
        mat: mat,
        judokas: [],
        voltooid: false,
        prijsuitreiking: false
      };
    }

    // Voeg judoka toe
    poulesMap[pouleNr].judokas.push({
      naam: naam,
      club: row[clubIdx] || "",
      wedstrijden: [],
      totaalWP: 0,
      totaalJP: 0,
      plaats: null
    });
  }

  // Converteer naar array en sorteer op poulenummer
  const poules = Object.values(poulesMap).sort((a, b) => a.pouleNr - b.pouleNr);

  // Laad bestaande resultaten indien beschikbaar
  poules.forEach(poule => {
    loadBestaandeResultaten(blokNr, matNr, poule);
  });

  return poules;
}

/**
 * Laadt bestaande wedstrijdresultaten voor een poule
 * Leest direct uit het Wedstrijdschema's Blok X tabblad
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 * @param {Object} poule - Poule object
 */
function loadBestaandeResultaten(blokNr, matNr, poule) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const schemaSheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  const schemaSheet = ss.getSheetByName(schemaSheetNaam);

  if (!schemaSheet) {
    // Schema bestaat nog niet
    return;
  }

  const aantalJudokas = poule.judokas.length;
  const wedstrijdvolgorde = genereerOptimaleWedstrijdvolgorde(aantalJudokas);
  const aantalWedstrijden = wedstrijdvolgorde.length;

  // Zoek de poule in het sheet
  const data = schemaSheet.getDataRange().getDisplayValues();
  let pouleRij = -1;

  for (let i = 0; i < data.length; i++) {
    const cellValue = data[i].join(' ');
    if (cellValue.includes(`Poule ${poule.pouleNr} -`)) {
      pouleRij = i + 1; // 1-indexed
      break;
    }
  }

  if (pouleRij === -1) {
    // Poule niet gevonden in schema
    return;
  }

  // Check of de poule voltooid is door de afgerond en prijs kolommen te lezen
  const aantalJudokasTmp = poule.judokas.length;
  const wedstrijdvolgordeTmp = genereerOptimaleWedstrijdvolgorde(aantalJudokasTmp);
  const aantalWedstrijdenTmp = wedstrijdvolgordeTmp.length;
  const dataKolommenTmp = 2 + aantalWedstrijdenTmp * 2 + 3; // Nr, Naam, Wedstrijden, WP, JP, Plts
  const afgerondKolomTmp = dataKolommenTmp; // Laatste cel IN de balk
  const prijsKolomTmp = dataKolommenTmp + 1; // BUITEN de balk

  const afgerondWaarde = data[pouleRij - 1][afgerondKolomTmp - 1]; // -1 omdat data 0-indexed is
  const prijsWaarde = data[pouleRij - 1][prijsKolomTmp - 1]; // -1 omdat data 0-indexed is

  poule.voltooid = (afgerondWaarde === "✓");
  poule.prijsuitreiking = (prijsWaarde === "✓");

  Logger.log(`  Poule ${poule.pouleNr} afgerond: ${poule.voltooid} (waarde: "${afgerondWaarde}"), prijsuitreiking: ${poule.prijsuitreiking} (waarde: "${prijsWaarde}")`);

  const startJudokaRij = pouleRij + 3;

  // Lees resultaten per judoka
  for (let i = 0; i < aantalJudokas; i++) {
    const judoka = poule.judokas[i];
    const judokaNummer = i + 1;
    const judokaRij = startJudokaRij + i;

    judoka.wedstrijden = [];

    let col = 3; // Start kolom voor wedstrijden

    // Loop door alle wedstrijden
    for (let w = 0; w < aantalWedstrijden; w++) {
      const wedstrijd = wedstrijdvolgorde[w];
      const speeltInDezeWedstrijd = (wedstrijd.judoka1 === judokaNummer || wedstrijd.judoka2 === judokaNummer);

      if (speeltInDezeWedstrijd) {
        // Bepaal tegenstander index (moet consistent zijn met MatInterface.html)
        const tegenJudoka = wedstrijd.judoka1 === judokaNummer ? wedstrijd.judoka2 : wedstrijd.judoka1;
        const tegenIndex = tegenJudoka - 1;

        // Lees WP en JP waarden
        const wpValue = data[judokaRij - 1][col - 1]; // -1 omdat data 0-indexed is
        const jpValue = data[judokaRij - 1][col]; // col+1-1

        const wp = wpValue !== '' ? parseInt(wpValue) : null;
        const jp = jpValue !== '' ? parseInt(jpValue) : null;

        // Gebruik tegenIndex als key (consistent met MatInterface.html)
        // BELANGRIJK: Gebruik niet || 0, want dan kunnen we geen onderscheid maken tussen
        // "niet gespeeld" (null) en "verloren met 0 punten" (0)
        judoka.wedstrijden[tegenIndex] = { wp: wp, jp: jp };
      }

      col += 2;
    }
  }

  // Bereken totalen
  poule.judokas.forEach((judoka, idx) => {
    let totaalWP = 0;
    let totaalJP = 0;

    if (judoka.wedstrijden) {
      judoka.wedstrijden.forEach(w => {
        if (w) {
          totaalWP += w.wp || 0;
          totaalJP += w.jp || 0;
        }
      });
    }

    judoka.totaalWP = totaalWP;
    judoka.totaalJP = totaalJP;
  });

  // Bereken rangschikking (WP primair, JP secundair, head-to-head tertiair)
  const judokasMetIndex = poule.judokas.map((j, idx) => ({ ...j, origIndex: idx }));
  judokasMetIndex.sort((a, b) => {
    if (b.totaalWP !== a.totaalWP) return b.totaalWP - a.totaalWP; // WP eerst
    if (b.totaalJP !== a.totaalJP) return b.totaalJP - a.totaalJP; // Bij gelijk WP → JP

    // Bij gelijk WP en JP → kijk naar onderlinge wedstrijd (head-to-head)
    // a.origIndex is de judoka index, b.origIndex is de tegenstander index
    // a.wedstrijden[b.origIndex] bevat het resultaat van a tegen b
    if (a.wedstrijden && b.wedstrijden &&
        a.wedstrijden[b.origIndex] && b.wedstrijden[a.origIndex]) {
      const aTegenB_WP = a.wedstrijden[b.origIndex].wp || 0;
      const bTegenA_WP = b.wedstrijden[a.origIndex].wp || 0;

      if (bTegenA_WP !== aTegenB_WP) {
        return bTegenA_WP - aTegenB_WP; // Winnaar van onderlinge wedstrijd eerst
      }
    }

    return 0; // Helemaal gelijk
  });

  // Wijs plaatsen toe
  judokasMetIndex.forEach((judoka, plaats) => {
    poule.judokas[judoka.origIndex].plaats = plaats + 1;
  });
}

/**
 * Schrijft resultaten van een poule naar het digitale wedstrijdschema
 * @param {Sheet} sheet - Het wedstrijdschema sheet
 * @param {Object} poule - Poule object met judokas en wedstrijden
 * @param {number} blokNr - Het bloknummer
 */
function schrijfResultatenNaarSchema(sheet, poule, blokNr) {
  Logger.log(`schrijfResultatenNaarSchema() - Poule ${poule.pouleNr}`);

  const aantalJudokas = poule.judokas.length;
  const wedstrijdvolgorde = genereerOptimaleWedstrijdvolgorde(aantalJudokas);
  const aantalWedstrijden = wedstrijdvolgorde.length;

  Logger.log(`  Aantal judokas: ${aantalJudokas}, Aantal wedstrijden: ${aantalWedstrijden}`);

  // Zoek de poule in het sheet (zoek naar de header met poule nummer)
  const data = sheet.getDataRange().getDisplayValues();
  let pouleRij = -1;

  for (let i = 0; i < data.length; i++) {
    // Zoek naar de header rij met "Poule X -"
    const cellValue = data[i].join(' ');
    if (cellValue.includes(`Poule ${poule.pouleNr} -`)) {
      pouleRij = i + 1; // 1-indexed
      break;
    }
  }

  if (pouleRij === -1) {
    Logger.log(`  ERROR: Poule ${poule.pouleNr} niet gevonden in schema`);
    return;
  }

  Logger.log(`  Poule gevonden op rij ${pouleRij}`);

  // De judoka rijen beginnen 3 rijen onder de header
  // Rij 1: Header balk met titel
  // Rij 2: Wed 1 | Wed 2 | ... headers
  // Rij 3: WP | JP sub-headers
  // Rij 4+: Judoka's
  const startJudokaRij = pouleRij + 3;

  // Schrijf WP en JP waarden per judoka per wedstrijd
  for (let i = 0; i < aantalJudokas; i++) {
    const judoka = poule.judokas[i];
    const judokaNummer = i + 1;
    const judokaRij = startJudokaRij + i;

    Logger.log(`  Judoka ${judokaNummer} (${judoka.naam}), rij ${judokaRij}`);

    let col = 3; // Start kolom voor wedstrijden

    // Loop door alle wedstrijden
    for (let w = 0; w < aantalWedstrijden; w++) {
      const wedstrijd = wedstrijdvolgorde[w];

      // Check of deze judoka in deze wedstrijd speelt
      const speeltInDezeWedstrijd = (wedstrijd.judoka1 === judokaNummer || wedstrijd.judoka2 === judokaNummer);

      if (speeltInDezeWedstrijd && judoka.wedstrijden) {
        // Bepaal tegenstander index
        const tegenIndex = wedstrijd.judoka1 === judokaNummer ? wedstrijd.judoka2 - 1 : wedstrijd.judoka1 - 1;

        // Data is opgeslagen per tegenstander (judoka.wedstrijden[tegenIndex])
        const resultaat = judoka.wedstrijden[tegenIndex];

        if (resultaat) {
          Logger.log(`    Wed ${w+1} vs Judoka ${tegenIndex+1}: WP=${resultaat.wp}, JP=${resultaat.jp}, Col=${col}`);

          // Schrijf WP
          if (resultaat.wp !== undefined && resultaat.wp !== null && resultaat.wp !== '') {
            sheet.getRange(judokaRij, col).setValue(resultaat.wp);
          }

          // Schrijf JP
          if (resultaat.jp !== undefined && resultaat.jp !== null && resultaat.jp !== '') {
            sheet.getRange(judokaRij, col + 1).setValue(resultaat.jp);
          }
        } else {
          Logger.log(`    Wed ${w+1} vs Judoka ${tegenIndex+1}: Geen resultaat gevonden`);
        }
      }

      col += 2; // Volgende wedstrijd (WP + JP kolommen)
    }

    // Schrijf totalen
    const totaalWP = judoka.totaalWP || 0;
    const totaalJP = judoka.totaalJP || 0;

    Logger.log(`    Totalen: WP=${totaalWP}, JP=${totaalJP}, Plaats=${judoka.plaats || '-'}, Col=${col}`);

    sheet.getRange(judokaRij, col).setValue(totaalWP); // WP totaal
    sheet.getRange(judokaRij, col + 1).setValue(totaalJP); // JP totaal

    // Schrijf plaats (als deze berekend is)
    if (judoka.plaats) {
      const plaatsCell = sheet.getRange(judokaRij, col + 2);
      plaatsCell.setValue(judoka.plaats);

      // Kleuren voor top 3 ALLEEN als poule voltooid is
      if (poule.voltooid) {
        if (judoka.plaats === 1) {
          plaatsCell.setBackground("#FFD700").setFontWeight("bold"); // Goud
        } else if (judoka.plaats === 2) {
          plaatsCell.setBackground("#C0C0C0").setFontWeight("bold"); // Zilver
        } else if (judoka.plaats === 3) {
          plaatsCell.setBackground("#CD7F32").setFontWeight("bold").setFontColor("#FFFFFF"); // Brons
        } else {
          plaatsCell.setBackground("#dbeafe").setFontColor("#1e40af");
        }
      } else {
        // Nog niet voltooid: gewoon blauwe achtergrond
        plaatsCell.setBackground("#dbeafe").setFontColor("#1e40af");
      }
    }
  }

  // Markeer de poule header als VOLTOOID (groen) als deze is afgerond
  const dataKolommen = 2 + aantalWedstrijden * 2 + 3; // Nr, Naam, Wedstrijden, WP, JP, Plts
  const afgerondKolom = dataKolommen; // Laatste cel IN de balk (boven Plts)
  const prijsKolom = dataKolommen + 1; // BUITEN de balk, rechts ernaast

  // Kleur de hele header groen als poule voltooid is
  if (poule.voltooid) {
    sheet.getRange(pouleRij, 1, 1, dataKolommen)
      .setBackground("#10b981") // Groen (voltooid)
      .setFontColor("#FFFFFF");
  } else {
    // Blauw als niet voltooid
    sheet.getRange(pouleRij, 1, 1, dataKolommen)
      .setBackground("#4A86E8")
      .setFontColor("#FFFFFF");
  }

  // Schrijf vinkje in de afgerond kolom (IN de balk, laatste cel)
  sheet.getRange(pouleRij, afgerondKolom)
    .setValue(poule.voltooid ? "✓" : "")
    .setBackground(poule.voltooid ? "#10b981" : "#4A86E8")
    .setFontColor("#FFFFFF")
    .setFontWeight("bold")
    .setFontSize(16)
    .setHorizontalAlignment("center");

  // NIET de prijskolom overschrijven! Die wordt alleen door de Presentator Dashboard aangepast
  // De mat leest alleen de prijsuitreiking status om te tonen in de interface

  // Update CONTROLE TABEL bovenaan sheet (voor Presentator Dashboard)
  updateControleTabel(sheet, poule.pouleNr, poule.voltooid);
}

/**
 * Controleert of alle wedstrijden in een poule gespeeld zijn
 * @param {Object} poule - Poule object
 * @param {number} aantalWedstrijden - Verwacht aantal wedstrijden
 * @returns {boolean} True als poule compleet is
 */
function checkPouleCompleet(poule, aantalWedstrijden) {
  const aantalJudokas = poule.judokas.length;

  for (let i = 0; i < aantalJudokas; i++) {
    const judoka = poule.judokas[i];
    if (!judoka.wedstrijden) return false;

    // Tel hoeveel wedstrijden deze judoka gespeeld heeft
    let gespeeldeWedstrijden = 0;
    for (let w = 0; w < aantalWedstrijden; w++) {
      if (judoka.wedstrijden[w] &&
          (judoka.wedstrijden[w].wp !== undefined || judoka.wedstrijden[w].jp !== undefined)) {
        gespeeldeWedstrijden++;
      }
    }

    // Elke judoka moet (aantalJudokas - 1) wedstrijden spelen
    // Bij 5 judoka's = 4 wedstrijden per judoka
    const verwachtAantalWedstrijden = aantalJudokas - 1;
    if (gespeeldeWedstrijden < verwachtAantalWedstrijden) {
      return false;
    }
  }

  return true;
}

/**
 * Update de controle tabel bovenaan de sheet met de afgerond status
 * Horizontale layout: 1 rij per mat, poules vanaf kolom C
 * Format: [Mat X] [lege] [Poule] [Afg] [Prj] [lege] [Poule] [Afg] [Prj] ...
 * @param {Sheet} sheet - De wedstrijdschema sheet
 * @param {number} pouleNr - Het poule nummer
 * @param {boolean} afgerond - Of de poule afgerond is
 */
function updateControleTabel(sheet, pouleNr, afgerond) {
  try {
    // Controle tabel: 6 rijen (max 6 matten), horizontaal vanaf rij 4
    const controleTabelStartRij = 4; // Na header "CONTROLE"
    const controleTabelData = sheet.getRange(controleTabelStartRij, 1, 6, 40).getValues(); // 6 rijen, 40 kolommen

    // Zoek door alle rijen (matten)
    for (let rijIndex = 0; rijIndex < controleTabelData.length; rijIndex++) {
      const rij = controleTabelData[rijIndex];

      // Loop door poules in deze rij (vanaf kolom 2 = index 2, elke 4 kolommen)
      for (let col = 2; col < rij.length; col += 4) {
        const tabelPouleNr = rij[col]; // Poule nummer

        if (tabelPouleNr === pouleNr) {
          const sheetRij = controleTabelStartRij + rijIndex;
          const sheetKol = col + 1; // +1 omdat array 0-indexed, sheet 1-indexed
          const afgerondKol = sheetKol + 1; // Afgerond kolom is direct na poule nummer

          // Update Afgerond kolom met checkmark
          sheet.getRange(sheetRij, afgerondKol).setValue(afgerond ? "✓" : "");

          Logger.log(`Controle tabel updated: Poule ${pouleNr} op rij ${sheetRij}, kolom ${afgerondKol}, afgerond = ${afgerond}`);
          return;
        }
      }
    }

    Logger.log(`WAARSCHUWING: Poule ${pouleNr} niet gevonden in controle tabel`);
  } catch (error) {
    Logger.log(`FOUT bij update controle tabel voor poule ${pouleNr}: ${error.message}`);
  }
}

/**
 * Synchroniseert mat data naar de spreadsheet
 * Schrijft direct naar het Wedstrijdschema's Blok X tabblad
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 * @param {Array} poulesData - Array met poule data
 * @param {Array} changes - Array met wijzigingen
 * @returns {Object} Sync resultaat
 */
function syncMatData(blokNr, matNr, poulesData, changes) {
  Logger.log('syncMatData() gestart');
  Logger.log('Blok: ' + blokNr + ', Mat: ' + matNr);
  Logger.log('Aantal poules: ' + poulesData.length);

  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const schemaSheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  const schemaSheet = ss.getSheetByName(schemaSheetNaam);

  if (!schemaSheet) {
    Logger.log('ERROR: Sheet niet gevonden: ' + schemaSheetNaam);
    throw new Error(`Het tabblad "${schemaSheetNaam}" is niet gevonden. Genereer eerst de wedstrijdschema's.`);
  }

  Logger.log('Sheet gevonden: ' + schemaSheetNaam);

  // Totalen en rangschikking zijn al berekend in MatInterface.html (client-side)
  // We hoeven dit hier niet opnieuw te doen

  // Schrijf alle data direct naar het schema (inclusief plaatsen)
  poulesData.forEach((poule, idx) => {
    Logger.log('Schrijf resultaten voor poule ' + (idx + 1) + ' (Poule nr ' + poule.pouleNr + ')');
    schrijfResultatenNaarSchema(schemaSheet, poule, blokNr);

    // Log of poule voltooid is (presentator leest direct uit Wedstrijdschema sheet o.b.v. groene header)
    if (poule.voltooid) {
      Logger.log('  Poule ' + poule.pouleNr + ' is voltooid - groen gemarkeerd voor presentator');
    } else {
      Logger.log('  Poule ' + poule.pouleNr + ' is nog niet voltooid');
    }
  });

  Logger.log('Sync succesvol afgerond');

  return {
    success: true,
    synced: poulesData.length,
    timestamp: Date.now()
  };
}


/**
 * Genereert printbare wedstrijdschema's voor een mat (offline fallback)
 * Gebruikt de bestaande digitale wedstrijdschema's
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 */
function genereerPrintSchemaVoorMat(blokNr, matNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Gebruik de bestaande digitale wedstrijdschema's
  const schemaSheetNaam = `Wedstrijdschema's Blok ${blokNr}`;
  const schemaSheet = ss.getSheetByName(schemaSheetNaam);

  if (!schemaSheet) {
    ui.alert(
      'Geen wedstrijdschema\'s gevonden',
      `Er zijn nog geen wedstrijdschema's voor Blok ${blokNr}.\n\n` +
      `Genereer eerst de digitale wedstrijdschema's via:\n` +
      `Toernooidag > 📋 Genereer wedstrijdschema's`,
      ui.ButtonSet.OK
    );
    return;
  }

  // Activeer het schema sheet zodat het geprint kan worden
  schemaSheet.activate();

  ui.alert(
    'Wedstrijdschema\'s Blok ' + blokNr,
    `Het tabblad "Wedstrijdschema's Blok ${blokNr}" is geopend.\n\n` +
    `Je kunt nu filteren op Mat ${matNr} en printen:\n\n` +
    `1. Zoek de sectie "═══ MAT ${matNr} ═══"\n` +
    `2. Selecteer alle poules van deze mat\n` +
    `3. Ga naar: Bestand > Afdrukken > Selectie\n\n` +
    `Of print alle matten: Bestand > Afdrukken > Hele werkblad`,
    ui.ButtonSet.OK
  );
}

/**
 * Opent de mat interface voor een specifiek blok en mat
 * @param {number} blokNr - Het bloknummer
 * @param {number} matNr - Het matnummer
 */
function openMatInterface(blokNr, matNr) {
  const html = HtmlService.createTemplateFromFile('MatInterface');
  html.blok = blokNr;
  html.mat = matNr;

  const htmlOutput = html.evaluate()
    .setWidth(1400)
    .setHeight(900)
    .setTitle(`Mat ${matNr} - Blok ${blokNr}`);

  SpreadsheetApp.getUi().showModalDialog(htmlOutput, `Mat ${matNr} - Blok ${blokNr}`);
}

/**
 * Keuzefunctie voor het printen van backup schema's per mat
 */
function keuzeMenuPrintBackupSchemas() {
  const ui = SpreadsheetApp.getUi();
  const aantalBlokken = getAantalBlokken();
  const aantalMatten = getAantalMatten();

  const resultBlok = ui.prompt(
    'Print Backup Schema\'s',
    `Voor welk blok wil je backup schema's genereren? (1-${aantalBlokken}):`,
    ui.ButtonSet.OK_CANCEL
  );

  if (resultBlok.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  const blokNr = parseInt(resultBlok.getResponseText());
  if (isNaN(blokNr) || blokNr < 1 || blokNr > aantalBlokken) {
    ui.alert('Ongeldig bloknummer', `Voer een geldig bloknummer in tussen 1 en ${aantalBlokken}.`, ui.ButtonSet.OK);
    return;
  }

  const resultMat = ui.prompt(
    'Print Backup Schema\'s',
    `Voor welke mat wil je backup schema's genereren? (1-${aantalMatten}):`,
    ui.ButtonSet.OK_CANCEL
  );

  if (resultMat.getSelectedButton() !== ui.Button.OK) {
    return;
  }

  const matNr = parseInt(resultMat.getResponseText());
  if (isNaN(matNr) || matNr < 1 || matNr > aantalMatten) {
    ui.alert('Ongeldig matnummer', `Voer een geldig matnummer in tussen 1 en ${aantalMatten}.`, ui.ButtonSet.OK);
    return;
  }

  genereerPrintSchemaVoorMat(blokNr, matNr);
}
