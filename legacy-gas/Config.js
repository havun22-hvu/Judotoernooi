// Config.js - Configuratie en menu voor het WestFries Open JudoToernooi Management Systeem
// Organisatie: Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalBlokken, laadConfiguratie)
 * - Voorbereiding.js (setupConfigSheet)
 */

/**
 * Checkt welke blokken gesloten zijn door het ZaalOverzicht te lezen
 * @return {Object} Object met blok nummers als keys en true/false als values
 */
function checkGeslotenBlokken() {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const schemaSheet = ss.getSheetByName('ZaalOverzicht');
    const geslotenBlokken = {};

    if (!schemaSheet) {
      // Als er geen ZaalOverzicht is, zijn alle blokken nog open
      return geslotenBlokken;
    }

    // Lees alleen kolom A (sneller dan hele range)
    const lastRow = Math.min(schemaSheet.getLastRow(), 100); // Limiteer tot 100 rijen
    if (lastRow < 1) return geslotenBlokken;

    const data = schemaSheet.getRange(1, 1, lastRow, 1).getValues();

    // Zoek naar "Blok X (Weging gesloten)" in kolom A
    for (let i = 0; i < data.length; i++) {
      const cellValue = data[i][0];
      if (cellValue && typeof cellValue === 'string') {
        // Check voor "Blok 1 (Weging gesloten)", "Blok 2 (Weging gesloten)", etc.
        const match = cellValue.match(/Blok (\d+).*Weging gesloten/);
        if (match) {
          const blokNr = parseInt(match[1]);
          geslotenBlokken[blokNr] = true;
        }
      }
    }

    return geslotenBlokken;
  } catch (e) {
    // Bij fout, return lege object (alle blokken open)
    Logger.log('Fout bij checkGeslotenBlokken: ' + e.message);
    return {};
  }
}

/**
 * Maakt het menu voor het WestFries Open JudoToernooi management systeem
 */
function createJudoMenu() {
  const ui = SpreadsheetApp.getUi();

  // Bepaal het aantal blokken (functie in ConfigUtils.js)
  const aantalBlokken = getAantalBlokken();

  // Check welke blokken gesloten zijn
  const geslotenBlokken = checkGeslotenBlokken();

  // Basis Toernooidag submenu
  const toernooidagMenu = ui.createMenu('Toernooidag')
    .addItem('Blokbladen bijwerken (mbt Weeglijst)', 'updateBlokBladen')
    .addItem('Verplaats judoka naar andere poule', 'verplaatsJudokaNaarAnderePoule')
    .addItem('Werk ZaalOverzicht bij', 'genereerZaalOverzicht')
    .addItem('Verplaats poule naar andere mat', 'verplaatsPouleNaarAndereMat')
    .addItem('Nieuwe poule aanmaken', 'maakNieuwePoule')
    .addSeparator()
    .addItem('📋 Genereer wedstrijdschema\'s...', 'genereerWedstrijdschemas')
    .addItem('🖨️ Print backup schema\'s per mat...', 'keuzeMenuPrintBackupSchemas')
    .addSeparator();

  // Voeg blok-specifieke items toe op basis van status
  for (let i = 1; i <= Math.min(aantalBlokken, 6); i++) {
    if (geslotenBlokken[i]) {
      // Blok is gesloten - toon wedstrijdschema knoppen
      toernooidagMenu.addItem(`Wedstrijdschema's Blok ${i}`, `digitaalWedstrijdschemaBlok${i}`);
    } else {
      // Blok is nog open - toon sluit weging knop
      toernooidagMenu.addItem(`Sluit weging Blok ${i}`, `sluitWegingBlok${i}`);
    }
  }

  // Bouw het volledige menu met tijd indicator
  const laatsteUpdate = '22:24';

  ui.createMenu(`WestFries Open (${laatsteUpdate})`)
    .addItem('🔐 Admin Login', 'openAdminLogin')
    .addItem('🥋 Mat Login', 'openMatLogin')
    .addItem('⚖️ Weeglijst Login', 'openWeeglijstLogin')
    .addItem('📺 Presentator Dashboard', 'openPresentatorDashboard')
    .addSeparator()
    .addSubMenu(ui.createMenu('Voorbereiding')
      .addItem('Initialiseer nieuw toernooi', 'initializeToernooi')
      .addItem('Maak Tabbladen', 'maakMatEnTijdsblokBladen')
      .addSeparator()
      .addItem('Importeer Deelnemerslijst', 'importeerDeelnemerslijst')
      .addItem('Voltooi Import', 'voltooiImportDeelnemerslijst')
      .addSeparator()
      .addItem('Controleer Deelnemers', 'controleerEnGenereerCodes')
      .addSeparator()
      .addItem('🔧 Test: Gesloten blokken', 'testCheckGeslotenBlokken')
      .addItem('🔧 Test: Verwijder oud tabblad', 'testVerwijderOudTabblad'))
    .addSubMenu(ui.createMenu('Poule indeling')
      .addItem('Genereer poule-indeling', 'genereerPouleIndeling')
      .addItem('Verplaats judoka naar andere poule', 'verplaatsJudoka')
      .addItem('Herbereken wedstrijden', 'herberekeningWedstrijden')
      .addSeparator()
      .addItem('📧 Verstuur Judoka Pasjes', 'verstuurJudokaPasjes'))
    .addSubMenu(ui.createMenu('Blok/Mat Indeling')
      .addItem('Genereer Blok/Mat Indeling', 'genereerBlokMatIndeling')
      .addItem('Vul poules in blokken', 'vulPoulesInBlokken')
      .addItem('Vul Blok/Mat nummers in bij PouleIndeling', 'updatePouleIndelingVanuitMenu')
      .addItem('Genereer weeglijst Zaaloverzicht en blokbladen', 'genereerWeeglijstEnBlokbladen'))
    .addSubMenu(toernooidagMenu)
    .addSeparator()
    .addItem('ℹ️ Code Versie', 'toonCodeVersie')
    .addToUi();
}

/**
 * Test functie om gesloten blokken te controleren
 */
function testCheckGeslotenBlokken() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const geslotenBlokken = checkGeslotenBlokken();

  let message = "Gesloten blokken detectie:\n\n";

  if (Object.keys(geslotenBlokken).length === 0) {
    message += "Geen gesloten blokken gevonden.\n\n";
  } else {
    message += "Gesloten blokken:\n";
    for (const blokNr in geslotenBlokken) {
      message += `- Blok ${blokNr}: ${geslotenBlokken[blokNr] ? 'GESLOTEN' : 'OPEN'}\n`;
    }
    message += "\n";
  }

  // Lees ook de ruwe data uit ZaalOverzicht
  const schemaSheet = ss.getSheetByName('ZaalOverzicht');
  if (schemaSheet) {
    const data = schemaSheet.getDataRange().getValues();
    message += "\nBlok-titels in ZaalOverzicht (kolom A):\n";
    for (let i = 0; i < Math.min(data.length, 20); i++) {
      const cellValue = data[i][0];
      if (cellValue && typeof cellValue === 'string' && cellValue.includes('Blok')) {
        message += `- Rij ${i+1}: "${cellValue}"\n`;

        // Test de regex match
        const match = cellValue.match(/Blok (\d+).*Weging gesloten/);
        if (match) {
          message += `  ✅ MATCH gevonden! Blok ${match[1]}\n`;
        } else {
          message += `  ❌ Geen match met regex\n`;
        }
      }
    }
  } else {
    message += "\nZaalOverzicht niet gevonden!";
  }

  message += "\n\n💡 TIP: Na het zien van deze info, herlaad de pagina (F5) om het menu te vernieuwen.";

  ui.alert('Debug: Gesloten Blokken', message, ui.ButtonSet.OK);

  // Open het ZaalOverzicht zodat de gebruiker het kan zien
  if (schemaSheet) {
    ss.setActiveSheet(schemaSheet);
  }
}

/**
 * Test functie om te verifiëren dat oude tabbladen correct verwijderd worden
 * Deze functie helpt bij het testen van de deployment
 */
function testVerwijderOudTabblad() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Zoek naar een "Deelnemerslijst (oud)" tabblad
  const oudTabblad = ss.getSheetByName('Deelnemerslijst (oud)');

  if (oudTabblad) {
    const response = ui.alert(
      'Oud tabblad gevonden',
      'Er is een "Deelnemerslijst (oud)" tabblad gevonden.\n\n' +
      'Wil je dit verwijderen?',
      ui.ButtonSet.YES_NO
    );

    if (response === ui.Button.YES) {
      ss.deleteSheet(oudTabblad);
      ui.alert(
        'Verwijderd',
        'Het tabblad "Deelnemerslijst (oud)" is succesvol verwijderd.',
        ui.ButtonSet.OK
      );
    }
  } else {
    ui.alert(
      'Geen oud tabblad',
      'Er is geen "Deelnemerslijst (oud)" tabblad gevonden.\n\n' +
      'Dit betekent dat de nieuwe code werkt! ✅\n\n' +
      'Bij de volgende import wordt het oude tabblad automatisch verwijderd.',
      ui.ButtonSet.OK
    );
  }
}

/**
 * Toont informatie over de huidige code deployment
 * Handig om te verifiëren dat de laatste deployment actief is
 */
function toonCodeVersie() {
  const ui = SpreadsheetApp.getUi();
  const laatsteUpdate = '22:24';

  ui.alert(
    'Code Deployment Informatie',
    'WestFries Open JudoToernooi\n\n' +
    `Laatste update: ${laatsteUpdate}\n\n` +
    'Recente wijzigingen:\n' +
    '✅ Print Backup Functionaliteit\n' +
    '   → Exacte kolombreedte wordt gekopieerd\n' +
    '   → Print tab wordt automatisch verborgen\n' +
    '   → 3 knoppen: Print Poule / Open Print Tab / Wis Print Tab\n' +
    '✅ NIEUW: 📱 Web Applicatie!\n' +
    '   → Standalone web app zonder spreadsheet zichtbaar\n' +
    '   → Login scherm met Admin/Mat/Weeglijst keuze\n' +
    '   → QR scanner + naam zoeken in weeglijst\n' +
    '✅ Admin Login Systeem (DEV modus - geen wachtwoord)\n' +
    '✅ QR Code Weging met camera scanner\n' +
    '✅ Judoka Pasjes via email met QR codes\n\n' +
    'Als je deze tijd (21:54) in het menu ziet, is de deployment succesvol!',
    ui.ButtonSet.OK
  );
}

/**
 * Initialiseert een volledig nieuw toernooi
 * Stap 1: Verwijder oude data en maak config
 */
function initializeToernooi() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Bevestiging vragen - oude gegevens gaan verloren
  const response = ui.alert(
    'Nieuw toernooi starten',
    'ALLE oude gegevens gaan verloren!\n\nAlle tabbladen worden verwijderd.\n\nDoorgaan?',
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    return;
  }

  try {
    // Verwijder ALLE tabbladen behalve het eerste
    const allSheets = ss.getSheets();
    for (let i = allSheets.length - 1; i > 0; i--) {
      ss.deleteSheet(allSheets[i]);
    }

    // Hernoem het eerste tabblad naar ToernooiConfig
    const firstSheet = ss.getSheets()[0];
    if (firstSheet.getName() !== "ToernooiConfig") {
      firstSheet.setName("ToernooiConfig");
    }

    // Setup volledige configuratie
    setupConfigSheet();

    // Maak automatisch alle tabbladen aan
    _createTabsInternal();

    // Klaar - geen extra alert, laat Dashboard de success message tonen
  } catch (e) {
    throw new Error('Fout bij initialiseren: ' + e.message);
  }
}

/**
 * Past de bloktabbladen aan op basis van de configuratie
 * Gebruikt de waarden uit ToernooiConfig (B14 voor blokken)
 */
function pasTabbladeAanOpBasisVanConfig() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");

  if (!configSheet) {
    ui.alert(
      'Configuratie niet gevonden',
      'Het ToernooiConfig tabblad bestaat niet. Start eerst een nieuw toernooi.',
      ui.ButtonSet.OK
    );
    return;
  }

  // Lees aantal blokken uit configuratie
  const nieuwAantalBlokken = configSheet.getRange("B14").getValue() || 6;

  // Bevestiging vragen
  const response = ui.alert(
    'Tabbladen Aanpassen',
    `De configuratie aanpassen naar ${nieuwAantalBlokken} tijdsblokken.\n\nOntbrekende tabbladen worden toegevoegd, overtollige worden verwijderd.\n\nDoorgaan?`,
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    return;
  }

  // Update blok-tabbladen
  for (let i = 1; i <= 10; i++) {
    const blokSheet = ss.getSheetByName(`Blok ${i}`);
    if (i <= nieuwAantalBlokken) {
      // Moet bestaan
      if (!blokSheet) {
        ss.insertSheet(`Blok ${i}`);
      }
    } else {
      // Moet niet bestaan
      if (blokSheet) {
        ss.deleteSheet(blokSheet);
      }
    }
  }

  // Sorteer tabbladen in de juiste volgorde
  const gewensteVolgorde = [
    'ToernooiConfig',
    'Deelnemerslijst',
    'PouleIndeling',
    'Blok/Mat verdeling',
    'ZaalOverzicht'
  ];

  // Voeg Blok tabbladen toe
  for (let i = 1; i <= nieuwAantalBlokken; i++) {
    gewensteVolgorde.push(`Blok ${i}`);
  }

  // Dashboard als laatste
  gewensteVolgorde.push('Dashboard');

  // Verplaats elk tabblad naar de juiste positie
  let positie = 1;
  for (const sheetName of gewensteVolgorde) {
    const sheet = ss.getSheetByName(sheetName);
    if (sheet) {
      ss.setActiveSheet(sheet);
      ss.moveActiveSheet(positie);
      positie++;
    }
  }

  // Herlaad configuratie
  laadConfiguratie();

  ui.alert(
    'Tabbladen Bijgewerkt',
    `De tabbladen zijn aangepast naar ${nieuwAantalBlokken} tijdsblokken.\n\nDe volgorde is hersteld.`,
    ui.ButtonSet.OK
  );
}

/**
 * Functie die uitgevoerd wordt bij openen van het spreadsheet
 * Voegt het menu toe aan de spreadsheet en laadt de configuratie
 */
function onOpen() {
  // Laad de configuratie bij het openen (functie in ConfigUtils.js)
  laadConfiguratie();

  // Maak het menu
  createJudoMenu();
}
