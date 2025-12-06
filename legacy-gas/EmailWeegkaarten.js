// EmailWeegkaarten.js - Email functionaliteit voor weegkaarten
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Genereert een QR code URL voor een judoka
 * @param {Object} judoka - Judoka object
 * @return {string} URL voor QR code image
 */
function genereerQRCodeURL(judoka) {
  // Data voor QR code: Alleen Blok en Mat nummer (simpel!)
  // Format: B5M3 betekent Blok 5, Mat 3
  const qrData = `B${judoka.blok}M${judoka.mat}`;
  // QR Server API (betrouwbaarder dan Google Charts)
  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrData)}`;
  return qrUrl;
}

/**
 * Debug functie - toon alle kolom headers van PouleIndeling
 */
function debugPouleIndelingHeaders() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  const headers = poulesSheet.getRange(1, 1, 1, poulesSheet.getLastColumn()).getValues()[0];

  Logger.log('PouleIndeling headers:');
  headers.forEach((header, index) => {
    const kolom = String.fromCharCode(65 + index); // A, B, C, etc.
    Logger.log(`Kolom ${kolom} (index ${index}): "${header}"`);
  });
}

/**
 * Debug functie - log QR code data
 */
function testQRCode() {
  const judoka = getJudokaGegevens('Mawin van Unen');
  const qrData = `${judoka.naam}|P${judoka.pouleNr}|B${judoka.blok}|M${judoka.mat}`;
  const qrUrl = genereerQRCodeURL(judoka);

  Logger.log('Judoka data:');
  Logger.log(JSON.stringify(judoka, null, 2));
  Logger.log('QR Data: ' + qrData);
  Logger.log('QR URL: ' + qrUrl);
  Logger.log('Open deze URL in je browser om de QR code te testen: ' + qrUrl);
}

/**
 * Genereert HTML voor een weegkaart van een judoka
 * @param {Object} judoka - Judoka object met alle gegevens
 * @return {string} HTML string voor de weegkaart
 */
function genereerWeegkaartHTML(judoka) {
  const qrCodeUrl = genereerQRCodeURL(judoka);

  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <style>
        body {
          font-family: Arial, sans-serif;
          max-width: 400px;
          margin: 0 auto;
          padding: 10px;
          background: #f5f5f5;
        }
        .weegkaart {
          background: white;
          border: 3px solid #2563eb;
          border-radius: 10px;
          padding: 15px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
          background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
          color: white;
          padding: 12px;
          border-radius: 8px;
          margin-bottom: 15px;
          text-align: center;
        }
        .header h1 {
          margin: 0;
          font-size: 20px;
        }
        .header p {
          margin: 5px 0 0 0;
          font-size: 13px;
          opacity: 0.9;
        }
        .qr-section {
          text-align: center;
          margin: 15px 0;
          padding: 15px;
          background: #f9fafb;
          border-radius: 8px;
        }
        .qr-section img {
          width: 200px;
          height: 200px;
          border: 2px solid #e5e7eb;
          border-radius: 8px;
        }
        .qr-label {
          font-size: 12px;
          color: #6b7280;
          margin-top: 8px;
          font-weight: 600;
        }
        .info-grid {
          display: grid;
          grid-template-columns: 110px 1fr;
          gap: 10px;
          margin-bottom: 15px;
        }
        .label {
          font-weight: bold;
          color: #374151;
          font-size: 14px;
        }
        .value {
          color: #1f2937;
          font-size: 14px;
        }
        .highlight {
          background: #fef3c7;
          padding: 12px;
          border-radius: 8px;
          border-left: 4px solid #f59e0b;
          margin: 15px 0;
        }
        .highlight h3 {
          margin: 0 0 8px 0;
          color: #92400e;
          font-size: 15px;
        }
        .poule-info {
          font-size: 16px;
          font-weight: bold;
          color: #1e40af;
          margin-bottom: 5px;
        }
        .poule-details {
          font-size: 13px;
          color: #6b7280;
        }
        .footer {
          margin-top: 15px;
          padding-top: 12px;
          border-top: 2px solid #e5e7eb;
          text-align: center;
          color: #6b7280;
          font-size: 11px;
        }
        @media print {
          body {
            background: white;
            max-width: 100%;
          }
          .weegkaart {
            border: 2px solid #000;
            page-break-inside: avoid;
          }
        }
      </style>
    </head>
    <body>
      <div class="weegkaart">
        <div class="header">
          <h1>⚖️ Weegkaart</h1>
          <p>WestFries Open Judotoernooi</p>
        </div>

        <div class="qr-section">
          <img src="${qrCodeUrl}" alt="QR Code">
          <div class="qr-label">Scan voor check-in</div>
        </div>

        <div class="info-grid">
          <div class="label">Naam:</div>
          <div class="value">${judoka.naam}</div>

          <div class="label">Geslacht:</div>
          <div class="value">${judoka.geslacht}</div>

          <div class="label">Geboortejaar:</div>
          <div class="value">${judoka.geboortejaar}</div>

          ${judoka.gradatie ? `
          <div class="label">Gradatie:</div>
          <div class="value">${judoka.gradatie}</div>
          ` : ''}

          <div class="label">Judoschool:</div>
          <div class="value">${judoka.judoschool}</div>

          <div class="label">Gewichtsklasse:</div>
          <div class="value"><strong>${judoka.gewichtsklasse}</strong></div>
        </div>

        <div class="highlight">
          <h3>📋 Poule Indeling</h3>
          ${judoka.pouleTitel ? `<div class="poule-info">${judoka.pouleTitel}</div>` : ''}
          <div class="poule-details">
            Blok ${judoka.blok} | Mat ${judoka.mat}
          </div>
        </div>

        <div class="footer">
          <p>Veel succes met het toernooi! 🥋</p>
          <p style="margin-top: 8px;">Judoschool Cees Veen - WestFries Open ${new Date().getFullYear()}</p>
        </div>
      </div>
    </body>
    </html>
  `;

  return html;
}

/**
 * Verstuurt een weegkaart per email naar een specifiek adres
 * @param {string} emailAdres - Het email adres van de ontvanger
 * @param {Object} judoka - Judoka object met alle gegevens
 * @return {Object} Object met success status
 */
function verstuurWeegkaartEmail(emailAdres, judoka) {
  try {
    const htmlBody = genereerWeegkaartHTML(judoka);
    const onderwerp = `Weegkaart ${judoka.naam} - WestFries Open Judotoernooi`;

    // Plain text versie als fallback
    const plainBody = `
WEEGKAART - WestFries Open Judotoernooi

Naam: ${judoka.naam}
Geslacht: ${judoka.geslacht}
Geboortejaar: ${judoka.geboortejaar}
${judoka.gradatie ? `Gradatie: ${judoka.gradatie}` : ''}
Judoschool: ${judoka.judoschool}
Gewichtsklasse: ${judoka.gewichtsklasse}

POULE INDELING:
${judoka.pouleTitel}
Poule ${judoka.pouleNr} | Blok ${judoka.blok} | Mat ${judoka.mat}

Veel succes met het toernooi!

Judoschool Cees Veen - WestFries Open ${new Date().getFullYear()}
    `;

    MailApp.sendEmail({
      to: emailAdres,
      subject: onderwerp,
      body: plainBody,
      htmlBody: htmlBody,
      name: 'WestFries Open Judotoernooi'
    });

    Logger.log(`Weegkaart verstuurd naar ${emailAdres} voor ${judoka.naam}`);

    return {
      success: true,
      message: `Weegkaart verstuurd naar ${emailAdres}`
    };

  } catch (error) {
    Logger.log(`FOUT bij versturen email naar ${emailAdres}: ${error.toString()}`);
    return {
      success: false,
      error: `Fout bij versturen: ${error.message}`
    };
  }
}

/**
 * Haalt judoka gegevens op uit de PouleIndeling sheet
 * @param {string} naam - Naam van de judoka
 * @return {Object} Judoka object met alle gegevens
 */
function getJudokaGegevens(naam) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const poulesSheet = ss.getSheetByName('PouleIndeling');

    if (!poulesSheet) {
      throw new Error('PouleIndeling sheet niet gevonden');
    }

    const data = poulesSheet.getDataRange().getValues();
    const headers = data[0];

    // Vind kolom indexen
    const naamIdx = headers.indexOf('Naam');
    const geslachtIdx = headers.indexOf('Geslacht');
    const geboortejaarIdx = headers.indexOf('Geboortejaar');
    const gradatieIdx = headers.indexOf('Gradatie');
    const clubIdx = headers.indexOf('Club');
    const bondsnummerIdx = headers.indexOf('Bondsnummer');
    const gewichtsklasseIdx = headers.indexOf('Gewichtsklasse');
    const pouleTitelIdx = headers.indexOf('Poule titel');
    const pouleNrIdx = headers.indexOf('Poule-nr');
    const blokIdx = headers.indexOf('Blok');
    const matIdx = headers.indexOf('Mat');

    // Zoek de judoka
    for (let i = 1; i < data.length; i++) {
      if (String(data[i][naamIdx]).trim() === String(naam).trim()) {
        return {
          naam: data[i][naamIdx] || '',
          geslacht: data[i][geslachtIdx] || '',
          geboortejaar: data[i][geboortejaarIdx] || '',
          gradatie: data[i][gradatieIdx] || '',
          judoschool: data[i][clubIdx] || '',
          bondsnummer: data[i][bondsnummerIdx] || '',
          gewichtsklasse: data[i][gewichtsklasseIdx] || '',
          pouleTitel: data[i][pouleTitelIdx] || '',
          pouleNr: data[i][pouleNrIdx] || '',
          blok: data[i][blokIdx] || '',
          mat: data[i][matIdx] || ''
        };
      }
    }

    throw new Error(`Judoka ${naam} niet gevonden in PouleIndeling`);

  } catch (error) {
    Logger.log(`FOUT bij ophalen judoka gegevens: ${error.toString()}`);
    throw error;
  }
}

/**
 * Groepeert alle judoka's per judoschool met hun email adressen
 * @return {Object} Object met judoschool als key en array van judoka's als value
 */
function groepeerJudokasPerSchool() {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const poulesSheet = ss.getSheetByName('PouleIndeling');

    if (!poulesSheet) {
      throw new Error('PouleIndeling sheet niet gevonden');
    }

    const data = poulesSheet.getDataRange().getValues();
    const headers = data[0];

    // Vind kolom indexen
    const clubIdx = headers.indexOf('Club');
    const naamIdx = headers.indexOf('Naam');

    const schoolGroepen = {};

    // Groepeer judoka's per club/school
    for (let i = 1; i < data.length; i++) {
      const club = String(data[i][clubIdx] || '').trim();
      const naam = String(data[i][naamIdx] || '').trim();

      if (!club || !naam) continue;

      if (!schoolGroepen[club]) {
        schoolGroepen[club] = [];
      }

      schoolGroepen[club].push(naam);
    }

    Logger.log(`Gegroepeerd: ${Object.keys(schoolGroepen).length} judoscholen`);
    return schoolGroepen;

  } catch (error) {
    Logger.log(`FOUT bij groeperen judoka's: ${error.toString()}`);
    throw error;
  }
}

/**
 * Verstuurt alle weegkaarten van een judoschool naar één email adres
 * @param {string} judoschool - Naam van de judoschool
 * @param {string} emailAdres - Email adres van de coach
 * @return {Object} Object met success status en aantal verstuurde kaarten
 */
function verstuurWeegkaartenPerSchool(judoschool, emailAdres) {
  try {
    const schoolGroepen = groepeerJudokasPerSchool();
    const judokaNames = schoolGroepen[judoschool];

    if (!judokaNames || judokaNames.length === 0) {
      return {
        success: false,
        error: `Geen judoka's gevonden voor ${judoschool}`
      };
    }

    // Maak één email met alle weegkaarten
    let htmlBody = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
          }
          .intro {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
          }
          .intro h1 {
            color: #2563eb;
            margin: 0 0 10px 0;
          }
        </style>
      </head>
      <body>
        <div class="intro">
          <h1>🥋 Weegkaarten ${judoschool}</h1>
          <p>WestFries Open Judotoernooi</p>
          <p>Hieronder vind je de weegkaarten van alle deelnemers van ${judoschool}.</p>
          <p><strong>${judokaNames.length} judoka's</strong></p>
        </div>
    `;

    // Voeg alle weegkaarten toe
    for (const naam of judokaNames) {
      try {
        const judoka = getJudokaGegevens(naam);
        htmlBody += genereerWeegkaartHTML(judoka);
        htmlBody += '<div style="margin: 30px 0;"></div>'; // Spacer tussen kaarten
      } catch (error) {
        Logger.log(`Fout bij genereren weegkaart voor ${naam}: ${error.message}`);
      }
    }

    htmlBody += `
      </body>
      </html>
    `;

    // Verstuur email
    const onderwerp = `Weegkaarten ${judoschool} - WestFries Open Judotoernooi`;

    MailApp.sendEmail({
      to: emailAdres,
      subject: onderwerp,
      htmlBody: htmlBody
    });

    Logger.log(`${judokaNames.length} weegkaarten verstuurd naar ${emailAdres} voor ${judoschool}`);

    return {
      success: true,
      message: `${judokaNames.length} weegkaarten verstuurd naar ${emailAdres}`,
      aantalKaarten: judokaNames.length
    };

  } catch (error) {
    Logger.log(`FOUT bij versturen weegkaarten voor ${judoschool}: ${error.toString()}`);
    return {
      success: false,
      error: `Fout bij versturen: ${error.message}`
    };
  }
}

/**
 * Test functie - verstuurt een enkele weegkaart
 */
function testVerstuurWeegkaart() {
  const judoka = getJudokaGegevens('Mawin van Unen');
  const result = verstuurWeegkaartEmail('henkvu@gmail.com', judoka);
  Logger.log(result);
}

/**
 * Opent de Email Weegkaarten UI
 */
function openEmailWeegkaartenUI() {
  const html = HtmlService.createHtmlOutputFromFile('EmailWeegkaartenUI')
    .setWidth(700)
    .setHeight(600);
  SpreadsheetApp.getUi().showModalDialog(html, 'Email Weegkaarten');
}

/**
 * Haalt een lijst van alle judoka namen op voor de UI
 * @return {Array} Array van judoka namen
 */
function getJudokaNamenLijst() {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const poulesSheet = ss.getSheetByName('PouleIndeling');

    if (!poulesSheet) {
      return [];
    }

    const data = poulesSheet.getDataRange().getValues();
    const headers = data[0];
    const naamIdx = headers.indexOf('Naam');
    const clubIdx = headers.indexOf('Club');

    const namen = [];
    for (let i = 1; i < data.length; i++) {
      const naam = String(data[i][naamIdx] || '').trim();
      const club = clubIdx !== -1 ? String(data[i][clubIdx] || '').trim() : '';

      // Alleen echte judoka's toevoegen:
      // - Moet een naam hebben
      // - Moet een club hebben (judoka's hebben altijd een club)
      // - Naam mag geen "Blok", "Leeftijdsklasse" bevatten (headers)
      if (naam && club &&
          !naam.toLowerCase().includes('blok') &&
          !naam.toLowerCase().includes('leeftijdsklasse') &&
          !naam.toLowerCase().includes('poule')) {
        namen.push(naam);
      }
    }

    // Verwijder duplicaten en sorteer
    const uniqueNamen = [...new Set(namen)];
    uniqueNamen.sort();
    return uniqueNamen;

  } catch (error) {
    Logger.log(`FOUT bij ophalen judoka namen: ${error.toString()}`);
    return [];
  }
}

/**
 * UI wrapper functie om een enkele weegkaart te versturen
 * @param {string} naam - Naam van de judoka
 * @param {string} emailAdres - Email adres
 * @return {Object} Result object
 */
function verstuurEnkeleWeegkaartUI(naam, emailAdres) {
  try {
    const judoka = getJudokaGegevens(naam);
    return verstuurWeegkaartEmail(emailAdres, judoka);
  } catch (error) {
    return {
      success: false,
      error: error.message
    };
  }
}
