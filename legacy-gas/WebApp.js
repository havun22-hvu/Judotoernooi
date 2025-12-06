// WebApp.js - Web Applicatie Entry Point
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * BELANGRIJKE OPMERKING:
 * Deze web app moet gepubliceerd worden via:
 * 1. In Google Apps Script: Deploy > New deployment
 * 2. Type: Web app
 * 3. Execute as: Me
 * 4. Who has access: Anyone (of Anyone with Google account)
 * 5. Kopieer de web app URL
 */

/**
 * Helper functie om het spreadsheet te openen
 * Werkt zowel in dialogs als in web apps
 * @return {Spreadsheet} Het spreadsheet object
 */
function getSpreadsheet() {
  try {
    // Probeer eerst de actieve spreadsheet (werkt in bound scripts)
    return SpreadsheetApp.getActiveSpreadsheet();
  } catch (e) {
    // Als dat niet werkt, probeer via openById
    // Je moet hier je spreadsheet ID invullen!
    const SPREADSHEET_ID = ScriptApp.getScriptProperties().getProperty('SPREADSHEET_ID');
    if (SPREADSHEET_ID) {
      return SpreadsheetApp.openById(SPREADSHEET_ID);
    }
    throw new Error('Kan spreadsheet niet openen. Zet SPREADSHEET_ID in script properties.');
  }
}

/**
 * doGet() is de entry point voor de web applicatie
 * Deze functie wordt aangeroepen wanneer iemand de web app URL opent
 * @param {Object} e - Event object met query parameters
 * @return {HtmlOutput} De HTML pagina om te tonen
 */
function doGet(e) {
  try {
    Logger.log('doGet aangeroepen met parameters: ' + JSON.stringify(e ? e.parameter : {}));

    const page = (e && e.parameter && e.parameter.page) ? e.parameter.page : 'login';
    Logger.log('PAGE PARAMETER: ' + page);

    // LOGIN PAGINA (standaard)
    if (page === 'login') {
      return HtmlService.createHtmlOutputFromFile('WebApp_LoginKeuze')
        .setTitle('WestFries Open - Login')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // ADMIN
    if (page === 'admin') {
      return HtmlService.createHtmlOutputFromFile('WebApp_AdminLogin')
        .setTitle('Admin Login - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // MAT BEHEER - selecteer blok/mat
    if (page === 'mat') {
      Logger.log('=== MAT BEHEER ROUTE ===');
      try {
        Logger.log('Step 1: getBeschikbareBlokken...');
        const blokken = getBeschikbareBlokken();
        Logger.log('Blokken opgehaald: ' + JSON.stringify(blokken));

        Logger.log('Step 2: getBeschikbareMatten...');
        const matten = getBeschikbareMatten();
        Logger.log('Matten opgehaald: ' + JSON.stringify(matten));

        Logger.log('Step 3: Template aanmaken...');
        const template = HtmlService.createTemplateFromFile('WebApp_MatInterface');

        Logger.log('Step 4: Data toewijzen...');
        template.blokken = blokken;
        template.matten = matten;

        Logger.log('Step 5: Template evalueren...');
        const result = template.evaluate()
          .setTitle('Mat Beheer - WestFries Open')
          .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');

        Logger.log('Step 6: SUCCESS - returning template');
        return result;

      } catch (err) {
        Logger.log('ERROR in mat route: ' + err.toString());
        Logger.log('Error stack: ' + err.stack);
        return HtmlService.createHtmlOutput(
          '<h1>Error in Mat Beheer</h1>' +
          '<p><strong>Error:</strong> ' + err.toString() + '</p>' +
          '<pre>' + err.stack + '</pre>' +
          '<p><a href="?page=login">Terug naar login</a></p>'
        );
      }
    }

    // MAT DETAIL - wedstrijdschema
    if (page === 'mat_detail') {
      const blok = (e.parameter.blok) ? parseInt(e.parameter.blok) : 1;
      const mat = (e.parameter.mat) ? parseInt(e.parameter.mat) : 1;

      const template = HtmlService.createTemplateFromFile('MatInterface');
      template.blok = blok;
      template.mat = mat;

      return template.evaluate()
        .setTitle(`Mat ${mat} - Blok ${blok} - WestFries Open`)
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // TEST POST
    if (page === 'test_post') {
      return HtmlService.createHtmlOutputFromFile('test_post_simple')
        .setTitle('Test POST');
    }

    // WEEGLIJST - Alle judoka's wegen (GEEN blok filter)
    if (page === 'weging') {
      Logger.log('=== WEGING ROUTE MATCHED ===');
      Logger.log('Loading WebApp_WegingInterface as template...');
      // Use createTemplateFromFile to enable google.script.run
      const template = HtmlService.createTemplateFromFile('WebApp_WegingInterface');
      return template.evaluate()
        .setTitle('Weging - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png')
        .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
    }

    // PRESENTATOR
    if (page === 'presentator') {
      return HtmlService.createHtmlOutputFromFile('PresentatorLogin')
        .setTitle('Presentator Login - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    if (page === 'presentator_dashboard') {
      return HtmlService.createHtmlOutputFromFile('PresentatorDashboard')
        .setTitle('Presentator - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // DASHBOARD
    if (page === 'dashboard') {
      return HtmlService.createHtmlOutputFromFile('WebApp_Dashboard')
        .setTitle('Dashboard - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // FALLBACK - Debug info
    Logger.log('FALLBACK: Onbekende page parameter: ' + page);
    return HtmlService.createHtmlOutput(
      '<h1>Debug Info</h1>' +
      '<p><strong>Page parameter ontvangen:</strong> ' + page + '</p>' +
      '<p><strong>Alle parameters:</strong></p>' +
      '<pre>' + JSON.stringify(e ? e.parameter : {}, null, 2) + '</pre>' +
      '<hr>' +
      '<p><a href="?page=login">Login Keuze</a></p>' +
      '<p><a href="?page=weging">Weging</a></p>' +
      '<p><a href="?page=mat">Mat</a></p>' +
      '<p><a href="?page=presentator">Presentator</a></p>'
    ).setTitle('Debug - WestFries Open');

  } catch (error) {
    Logger.log('ERROR in doGet: ' + error.toString());
    Logger.log('Stack trace: ' + error.stack);

    // Toon een error pagina met details
    return HtmlService.createHtmlOutput(
      '<h1>Error</h1>' +
      '<p>Er is een fout opgetreden:</p>' +
      '<pre>' + error.toString() + '</pre>' +
      '<pre>' + error.stack + '</pre>'
    ).setTitle('Error - WestFries Open');
  }
}

/**
 * Test functie om te verifiëren dat de web app werkt
 */
function testDoGet() {
  const output = doGet({ parameter: {} });
  Logger.log('Test output type: ' + typeof output);
  Logger.log('Test output: ' + output.getContent().substring(0, 100));
  return output;
}

/**
 * Test functie om de web app URL te tonen
 */
function toonWebAppURL() {
  const ui = SpreadsheetApp.getUi();

  ui.alert(
    'Web App Publiceren',
    'Om de web app te gebruiken:\n\n' +
    '1. Ga naar: Deploy > New deployment\n' +
    '2. Selecteer type: Web app\n' +
    '3. Execute as: Me\n' +
    '4. Who has access: Anyone\n' +
    '5. Klik Deploy\n' +
    '6. Kopieer de web app URL\n\n' +
    'Gebruikers kunnen dan de URL openen zonder het spreadsheet te zien!',
    ui.ButtonSet.OK
  );
}

/**
 * Haalt alle blokken op uit de configuratie
 * @return {Array} Array van blok nummers
 */
function getBeschikbareBlokken() {
  const aantalBlokken = getAantalBlokken();
  const blokken = [];

  for (let i = 1; i <= aantalBlokken; i++) {
    blokken.push({
      nummer: i,
      naam: `Blok ${i}`
    });
  }

  return blokken;
}

/**
 * Haalt alle matten op uit de configuratie
 * @return {Array} Array van mat nummers
 */
function getBeschikbareMatten() {
  try {
    const ss = getSpreadsheet();
    const configSheet = ss.getSheetByName('ToernooiConfig');

    if (!configSheet) {
      return [{nummer: 1, naam: 'Mat 1'}, {nummer: 2, naam: 'Mat 2'}];
    }

    const aantalMatten = configSheet.getRange('B13').getValue() || 2;
    const matten = [];

    for (let i = 1; i <= aantalMatten; i++) {
      matten.push({
        nummer: i,
        naam: `Mat ${i}`
      });
    }

    return matten;
  } catch (e) {
    Logger.log('Error in getBeschikbareMatten: ' + e.toString());
    return [{nummer: 1, naam: 'Mat 1'}, {nummer: 2, naam: 'Mat 2'}];
  }
}

/**
 * Zoekt judoka's op naam voor de weeglijst
 * @param {string} zoekterm - De zoekterm (deel van naam)
 * @param {number} blokNr - Het bloknummer (optioneel, 0 = alle blokken)
 * @return {Array} Array van gevonden judoka's
 */
function zoekJudokaOpNaam(zoekterm, blokNr) {
  try {
    const ss = getSpreadsheet();
    const poulesSheet = ss.getSheetByName('PouleIndeling');

    if (!poulesSheet) {
      return [];
    }

    const data = poulesSheet.getDataRange().getValues();
    const headers = data[0];

    const naamIdx = headers.indexOf("Naam");
    const clubIdx = headers.indexOf("Club");
    const blokIdx = headers.indexOf("Blok");
    const leeftijdsklasseIdx = headers.indexOf("Leeftijdsklasse");
    const gewichtsklasseIdx = headers.indexOf("Gewichtsklasse");
    const gewichtIdx = headers.indexOf("Gewicht");

    if (naamIdx === -1 || clubIdx === -1) {
      return [];
    }

    const resultaten = [];
    const zoektermLower = zoekterm.toLowerCase();

    for (let i = 1; i < data.length; i++) {
      const rij = data[i];
      const naam = rij[naamIdx] ? rij[naamIdx].toString() : '';
      const judokaBlok = blokIdx !== -1 ? rij[blokIdx] : 0;

      // Filter op blok als opgegeven
      if (blokNr > 0 && judokaBlok !== blokNr) {
        continue;
      }

      // Check of naam overeenkomt met zoekterm
      if (naam.toLowerCase().includes(zoektermLower)) {
        resultaten.push({
          rijNr: i + 1,
          naam: naam,
          club: clubIdx !== -1 ? rij[clubIdx] : '',
          blok: judokaBlok,
          leeftijdsklasse: leeftijdsklasseIdx !== -1 ? rij[leeftijdsklasseIdx] : '',
          gewichtsklasse: gewichtsklasseIdx !== -1 ? rij[gewichtsklasseIdx] : '',
          gewicht: gewichtIdx !== -1 ? rij[gewichtIdx] : '',
          heeftGewicht: gewichtIdx !== -1 && rij[gewichtIdx] !== '' && rij[gewichtIdx] !== null
        });
      }
    }

    // Sorteer op naam
    resultaten.sort((a, b) => a.naam.localeCompare(b.naam));

    // Limiteer tot 20 resultaten
    return resultaten.slice(0, 20);
  } catch (e) {
    Logger.log('Error in zoekJudokaOpNaam: ' + e.toString());
    return [];
  }
}

/**
 * Haalt de URL van het spreadsheet op
 * @return {string} De spreadsheet URL
 */
function getSpreadsheetUrl() {
  const ss = getSpreadsheet();
  return ss.getUrl();
}

/**
 * Include functie voor HTML templates
 * Voegt andere HTML bestanden in
 */
function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}

/**
 * doPost() handler voor AJAX calls vanuit de web app
 * @param {Object} e - Event object met post data
 * @return {TextOutput} JSON response
 */
function doPost(e) {
  try {
    Logger.log('=== doPost aangeroepen ===');
    Logger.log('Parameters: ' + JSON.stringify(e.parameter));

    const action = e.parameter.action;
    let result = {};

    Logger.log('Action: ' + action);

    if (action === 'zoekJudokaOpNaam') {
      const zoekterm = e.parameter.zoekterm;
      const blokNr = parseInt(e.parameter.blokNr) || 0;
      Logger.log(`zoekJudokaOpNaam: "${zoekterm}", blok ${blokNr}`);
      result = zoekJudokaOpNaam(zoekterm, blokNr);
    }
    else if (action === 'zoekJudokaViaQR') {
      const qrData = e.parameter.qrData;
      Logger.log(`zoekJudokaViaQR: "${qrData}"`);
      result = zoekJudokaViaQR(qrData);
      Logger.log('Result: ' + JSON.stringify(result));
    }
    else if (action === 'slaGewichtOp') {
      const rijNr = parseInt(e.parameter.rijNr);
      const gewicht = parseFloat(e.parameter.gewicht);
      result = slaGewichtOp(rijNr, gewicht);
    }
    else if (action === 'getPoulesVoorMat') {
      const blokNr = parseInt(e.parameter.blokNr);
      const matNr = parseInt(e.parameter.matNr);
      result = getPoulesVoorMat(blokNr, matNr);
    }
    else if (action === 'syncMatData') {
      const blokNr = parseInt(e.parameter.blokNr);
      const matNr = parseInt(e.parameter.matNr);
      const poulesData = JSON.parse(e.parameter.poulesData);
      const changes = JSON.parse(e.parameter.changes || '[]');
      result = syncMatData(blokNr, matNr, poulesData, changes);
    }
    else {
      result = { success: false, error: 'Onbekende actie: ' + action };
    }

    return ContentService.createTextOutput(JSON.stringify(result))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      error: error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}
