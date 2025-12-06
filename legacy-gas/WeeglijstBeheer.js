// WeeglijstBeheer.js - QR Code Weging Systeem & Admin Login
// WestFries Open JudoToernooi - Judoschool Cees Veen

// ============================================================================
// ADMIN LOGIN SYSTEEM
// ============================================================================

/**
 * Opent de Admin Login interface
 */
function openAdminLogin() {
  const html = HtmlService.createHtmlOutputFromFile('AdminLogin')
    .setWidth(500)
    .setHeight(600)
    .setTitle('🔐 Admin Login');

  SpreadsheetApp.getUi().showModalDialog(html, 'Admin Login');
}

/**
 * Verifieert het admin wachtwoord
 * @param {string} password - Het ingevoerde wachtwoord
 * @return {Object} Object met success status
 */
function verifyAdminPassword(password) {
  // TIJDELIJK: Wachtwoord verificatie uitgeschakeld voor development
  // Voor live versie: activeer wachtwoord controle
  return {
    success: true,
    message: 'Welkom, administrator! (Dev modus - geen wachtwoord)'
  };

  /* VOOR LIVE VERSIE - UNCOMMENT ONDERSTAANDE CODE:
  try {
    const scriptProperties = PropertiesService.getScriptProperties();
    let adminPassword = scriptProperties.getProperty('ADMIN_PASSWORD');

    if (!adminPassword) {
      adminPassword = 'WestFries2026'; // Standaard wachtwoord
      scriptProperties.setProperty('ADMIN_PASSWORD', adminPassword);
    }

    if (password === adminPassword) {
      return {
        success: true,
        message: 'Welkom, administrator!'
      };
    } else {
      return {
        success: false,
        error: 'Onjuist wachtwoord'
      };
    }
  } catch (error) {
    Logger.log('Fout bij wachtwoord verificatie: ' + error.toString());
    return {
      success: false,
      error: 'Systeemfout bij verificatie'
    };
  }
  */
}

/**
 * Opent het Admin Dashboard (volledige toegang)
 */
function openAdminDashboard() {
  // Open het standaard dashboard
  openDashboard();
}

/**
 * Wijzigt het admin wachtwoord
 * @param {string} oudWachtwoord - Het huidige wachtwoord
 * @param {string} nieuwWachtwoord - Het nieuwe wachtwoord
 * @return {Object} Object met success status
 */
function wijzigAdminWachtwoord(oudWachtwoord, nieuwWachtwoord) {
  try {
    // Verificeer eerst het oude wachtwoord
    const verificatie = verifyAdminPassword(oudWachtwoord);
    if (!verificatie.success) {
      return {
        success: false,
        error: 'Huidig wachtwoord is onjuist'
      };
    }

    // Valideer nieuw wachtwoord
    if (!nieuwWachtwoord || nieuwWachtwoord.length < 6) {
      return {
        success: false,
        error: 'Nieuw wachtwoord moet minimaal 6 tekens bevatten'
      };
    }

    // Sla nieuw wachtwoord op
    const scriptProperties = PropertiesService.getScriptProperties();
    scriptProperties.setProperty('ADMIN_PASSWORD', nieuwWachtwoord);

    return {
      success: true,
      message: 'Wachtwoord succesvol gewijzigd'
    };
  } catch (error) {
    Logger.log('Fout bij wijzigen wachtwoord: ' + error.toString());
    return {
      success: false,
      error: 'Systeemfout bij wijzigen wachtwoord'
    };
  }
}

// ============================================================================
// WEEGLIJST LOGIN SYSTEEM
// ============================================================================

/**
 * Opent de Weeglijst Login interface
 */
function openWeeglijstLogin() {
  const html = HtmlService.createHtmlOutputFromFile('WeeglijstLogin')
    .setWidth(600)
    .setHeight(700)
    .setTitle('⚖️ Weeglijst Login');

  SpreadsheetApp.getUi().showModalDialog(html, 'Weeglijst Login');
}

/**
 * Opent de Weging Interface voor een specifiek blok
 * @param {number} blokNr - Het bloknummer
 */
function openWegingInterface(blokNr) {
  const template = HtmlService.createTemplateFromFile('WegingInterface');
  template.blokNr = blokNr;

  const html = template.evaluate()
    .setWidth(800)
    .setHeight(900)
    .setTitle(`⚖️ Weging Blok ${blokNr}`);

  SpreadsheetApp.getUi().showModalDialog(html, `Weging Blok ${blokNr}`);
}

/**
 * Genereert QR codes voor alle judoka's in een blok
 * @param {number} blokNr - Het bloknummer
 * @return {Array} Array van judoka's met QR code URLs
 */
function genereerQRCodesBlok(blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!poulesSheet) {
    throw new Error('PouleIndeling sheet niet gevonden');
  }

  const data = poulesSheet.getDataRange().getValues();
  const headers = data[0];

  const blokIdx = headers.indexOf("Blok");
  const naamIdx = headers.indexOf("Naam");
  const clubIdx = headers.indexOf("Club");
  const leeftijdsKlasseIdx = headers.indexOf("Leeftijdsklasse");
  const gewichtsKlasseIdx = headers.indexOf("Gewichtsklasse");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");

  const judokas = [];

  for (let i = 1; i < data.length; i++) {
    const row = data[i];

    if (row[blokIdx] !== blokNr) continue;
    if (!row[naamIdx]) continue;

    // Creëer unieke ID voor deze judoka
    const judokaId = `${blokNr}-${row[pouleNrIdx]}-${i}`;

    // QR code data: JSON met alle info
    const qrData = {
      id: judokaId,
      naam: row[naamIdx],
      club: row[clubIdx] || '',
      leeftijdsklasse: row[leeftijdsKlasseIdx] || '',
      gewichtsklasse: row[gewichtsKlasseIdx] || '',
      poule: row[pouleNrIdx],
      mat: row[matIdx],
      blok: blokNr,
      rijNr: i + 1 // Voor het opslaan van gewicht
    };

    // Genereer QR code URL via externe API
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(JSON.stringify(qrData))}`;

    judokas.push({
      id: judokaId,
      naam: row[naamIdx],
      club: row[clubIdx] || '',
      leeftijdsklasse: row[leeftijdsKlasseIdx] || '',
      gewichtsklasse: row[gewichtsKlasseIdx] || '',
      poule: row[pouleNrIdx],
      mat: row[matIdx],
      qrCodeUrl: qrCodeUrl,
      qrData: JSON.stringify(qrData)
    });
  }

  return judokas;
}

/**
 * Zoekt judoka op basis van gescande QR code data
 * QR code bevat alleen blok en mat nummer (format: B5M3)
 * Admin ziet de info en selecteert zelf de judoka uit de lijst
 * @param {string} qrDataString - De gescande QR data (format: B5M3 = Blok 5, Mat 3)
 * @return {Object} Blok en Mat info - geen judoka data
 */
function zoekJudokaViaQR(qrDataString) {
  try {
    Logger.log('zoekJudokaViaQR aangeroepen met: ' + qrDataString);

    // Probeer eerst JSON format te parsen (judoka pasje QR)
    // Format: {"naam":"Mawin van Unen","gewichtsklasse":"-36kg","blok":5}
    try {
      const qrData = JSON.parse(qrDataString);
      if (qrData.naam && qrData.gewichtsklasse && qrData.blok) {
        Logger.log(`QR code bevat judoka data: ${qrData.naam}, ${qrData.gewichtsklasse}, Blok ${qrData.blok}`);

        // Zoek de judoka in PouleIndeling
        const ss = SpreadsheetApp.getActiveSpreadsheet();
        const poulesSheet = ss.getSheetByName('PouleIndeling');

        if (!poulesSheet) {
          return {
            success: false,
            error: 'PouleIndeling sheet niet gevonden'
          };
        }

        const data = poulesSheet.getDataRange().getValues();
        const headers = data[0];

        const naamIdx = headers.indexOf('Naam');
        const gewichtsklasseIdx = headers.indexOf('Gewichtsklasse');
        const blokIdx = headers.indexOf('Blok');
        const geslachtIdx = headers.indexOf('Geslacht');
        const geboortejaarIdx = headers.indexOf('Geboortejaar');
        const clubIdx = headers.indexOf('Club');
        const leeftijdsklasseIdx = headers.indexOf('Leeftijdsklasse');
        const pouleNrIdx = headers.indexOf('Poule-nr');
        const matIdx = headers.indexOf('Mat');

        // Zoek exacte match op naam, gewichtsklasse en blok
        for (let i = 1; i < data.length; i++) {
          const row = data[i];
          if (String(row[naamIdx]).trim() === String(qrData.naam).trim() &&
              String(row[gewichtsklasseIdx]).trim() === String(qrData.gewichtsklasse).trim() &&
              String(row[blokIdx]) == String(qrData.blok)) {

            // Judoka gevonden! Return alle data
            return {
              success: true,
              judoka: {
                rijNr: i + 1,
                naam: row[naamIdx] || '',
                geslacht: row[geslachtIdx] || '',
                geboortejaar: row[geboortejaarIdx] || '',
                club: row[clubIdx] || '',
                leeftijdsklasse: row[leeftijdsklasseIdx] || '',
                gewichtsklasse: row[gewichtsklasseIdx] || '',
                blok: row[blokIdx] || '',
                mat: row[matIdx] || '',
                pouleNr: row[pouleNrIdx] || ''
              },
              message: `Judoka gevonden: ${qrData.naam}`
            };
          }
        }

        // Niet gevonden
        return {
          success: false,
          error: `Judoka ${qrData.naam} niet gevonden in Blok ${qrData.blok}`
        };
      }
    } catch (jsonError) {
      // Geen JSON, probeer oude B5M3 format
      Logger.log('Geen JSON format, probeer B5M3 format');
    }

    // Parse QR data: B5M3 (Blok 5, Mat 3) - oude weegkaart format
    const match = qrDataString.match(/^B(\d+)M(\d+)$/);

    if (!match) {
      return {
        success: false,
        error: 'Ongeldige QR code. Verwacht format: {"naam":"...","gewichtsklasse":"...","blok":5} of B5M3'
      };
    }

    const blokNr = parseInt(match[1]);
    const matNr = parseInt(match[2]);

    Logger.log(`QR code bevat: Blok ${blokNr}, Mat ${matNr}`);

    // Oude format - alleen blok/mat info
    return {
      success: true,
      blok: blokNr,
      mat: matNr,
      message: `Blok ${blokNr}, Mat ${matNr} - Zoek judoka op naam`
    };

  } catch (error) {
    Logger.log('ERROR in zoekJudokaViaQR: ' + error.toString());
    return {
      success: false,
      error: 'Fout bij verwerken QR code: ' + error.message
    };
  }
}

/**
 * Slaat het gewicht op voor een judoka in de Weeglijst sheet
 * @param {number} rijNr - Het rijnummer in PouleIndeling sheet (1-based)
 * @param {number} gewicht - Het gewicht in kg
 * @return {Object} Resultaat van opslaan
 */
function slaGewichtOp(rijNr, gewicht) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  const weeglijstSheet = ss.getSheetByName('Weeglijst');

  if (!poulesSheet) {
    return {
      success: false,
      error: 'PouleIndeling sheet niet gevonden'
    };
  }

  if (!weeglijstSheet) {
    return {
      success: false,
      error: 'Weeglijst sheet niet gevonden'
    };
  }

  try {
    // Format gewicht met 1 decimaal (bijv. 36.5)
    const gewichtFormatted = Math.round(gewicht * 10) / 10;

    // Haal naam op uit PouleIndeling
    const poulesHeaders = poulesSheet.getRange(1, 1, 1, poulesSheet.getLastColumn()).getValues()[0];
    const naamIdx = poulesHeaders.indexOf("Naam");
    const naam = poulesSheet.getRange(rijNr, naamIdx + 1).getValue();

    // Zoek de judoka in de Weeglijst sheet
    const weeglijstData = weeglijstSheet.getDataRange().getValues();
    const weeglijstHeaders = weeglijstData[0];
    const weeglijstNaamIdx = weeglijstHeaders.indexOf("Naam");
    const gewichtMutatieIdx = weeglijstHeaders.indexOf("Gew.mutatie");

    if (gewichtMutatieIdx === -1) {
      return {
        success: false,
        error: 'Gew.mutatie kolom niet gevonden in Weeglijst. Kolommen: ' + weeglijstHeaders.join(', ')
      };
    }

    // Zoek de judoka in Weeglijst
    let weeglijstRij = -1;
    for (let i = 1; i < weeglijstData.length; i++) {
      if (String(weeglijstData[i][weeglijstNaamIdx]).trim() === String(naam).trim()) {
        weeglijstRij = i + 1; // 1-based
        break;
      }
    }

    if (weeglijstRij === -1) {
      return {
        success: false,
        error: `Judoka ${naam} niet gevonden in Weeglijst`
      };
    }

    // Sla gewicht op in Weeglijst (kolom I: Gew.mutatie)
    weeglijstSheet.getRange(weeglijstRij, gewichtMutatieIdx + 1).setValue(gewichtFormatted);

    // Zet Aanwezig op Ja
    const aanwezigIdx = weeglijstHeaders.indexOf("Aanwezig");
    if (aanwezigIdx !== -1) {
      const aanwezigCell = weeglijstSheet.getRange(weeglijstRij, aanwezigIdx + 1);
      aanwezigCell.setValue("Ja");
      aanwezigCell.setBackground("#D9EAD3");
    }

    // Check gewichtsklasse en update Opmerking kolom
    const gewKlasseIdx = weeglijstHeaders.indexOf("Gew. klasse");
    const opmerkingIdx = weeglijstHeaders.indexOf("Opmerkingen");

    if (gewKlasseIdx !== -1 && opmerkingIdx !== -1) {
      const gewKlasse = weeglijstData[weeglijstRij - 1][gewKlasseIdx];
      const gewKlasseMatch = String(gewKlasse).match(/([+-])?(\d+)/);

      if (gewKlasseMatch) {
        const isPlus = gewKlasseMatch[1] === '+';
        const limiet = parseFloat(gewKlasseMatch[2]);
        let opmerking = "";

        if (isPlus) {
          // +70kg betekent minimaal 70kg
          if (gewichtFormatted < limiet) {
            opmerking = `Te licht! Minimaal ${limiet}kg. Alternatief: -${limiet}kg`;
          }
        } else {
          // -36kg betekent maximaal 36kg
          if (gewichtFormatted > limiet) {
            const volgendeKlasse = limiet + 4; // Meestal 4kg stappen
            opmerking = `Te zwaar! Maximaal ${limiet}kg. Alternatief: -${volgendeKlasse}kg`;
          }
        }

        // Update opmerking
        const opmerkingCell = weeglijstSheet.getRange(weeglijstRij, opmerkingIdx + 1);
        if (opmerking) {
          opmerkingCell.setValue(opmerking);
          opmerkingCell.setBackground("#F4CCCC"); // Rode achtergrond
        } else {
          // Gewicht OK - clear oude gewicht-opmerking
          const huidigeOpmerking = weeglijstData[weeglijstRij - 1][opmerkingIdx];
          if (String(huidigeOpmerking).includes("Te zwaar") || String(huidigeOpmerking).includes("Te licht")) {
            opmerkingCell.setValue("");
            opmerkingCell.setBackground("#D9EAD3");
          }
        }
      }
    }

    Logger.log(`Gewicht opgeslagen voor ${naam}: ${gewichtFormatted} kg (Weeglijst rij ${weeglijstRij})`);

    return {
      success: true,
      naam: naam,
      gewicht: gewichtFormatted
    };
  } catch (error) {
    Logger.log(`FOUT bij opslaan gewicht voor rij ${rijNr}: ${error.toString()}`);
    return {
      success: false,
      error: 'Fout bij opslaan gewicht: ' + error.message
    };
  }
}

/**
 * Haalt alle judoka's op voor een blok (zonder QR codes, alleen data)
 * @param {number} blokNr - Het bloknummer
 * @return {Array} Array van judoka's
 */
function getJudokasBlok(blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!poulesSheet) {
    throw new Error('PouleIndeling sheet niet gevonden');
  }

  const data = poulesSheet.getDataRange().getValues();
  const headers = data[0];

  const blokIdx = headers.indexOf("Blok");
  const naamIdx = headers.indexOf("Naam");
  const clubIdx = headers.indexOf("Club");
  const gewichtIdx = headers.indexOf("Gewicht");
  const leeftijdsKlasseIdx = headers.indexOf("Leeftijdsklasse");
  const gewichtsKlasseIdx = headers.indexOf("Gewichtsklasse");
  const pouleNrIdx = headers.indexOf("Poule-nr");

  const judokas = [];

  for (let i = 1; i < data.length; i++) {
    const row = data[i];

    if (row[blokIdx] !== blokNr) continue;
    if (!row[naamIdx]) continue;

    judokas.push({
      rijNr: i + 1,
      naam: row[naamIdx],
      club: row[clubIdx] || '',
      leeftijdsklasse: row[leeftijdsKlasseIdx] || '',
      gewichtsklasse: row[gewichtsKlasseIdx] || '',
      poule: row[pouleNrIdx],
      gewicht: row[gewichtIdx] || ''
    });
  }

  return judokas;
}

/**
 * Genereert HTML voor een judoka pasje
 * @param {Object} judoka - Judoka gegevens
 * @param {string} qrCodeUrl - URL van QR code
 * @param {string} blokTijd - Tijdstip van het blok
 * @return {string} HTML van het pasje
 */
function genereerJudokaPasje(judoka, qrCodeUrl, blokTijd) {
  return `
    <div style="
      width: 360px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 20px;
      padding: 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      font-family: 'Arial', sans-serif;
      color: white;
      margin: 20px auto;
    ">
      <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 24px; font-weight: bold;">🥋 WestFries Open</h2>
        <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">Judoka Toegangspas</p>
      </div>
      <div style="background: white; border-radius: 15px; padding: 20px; color: #333;">
        <div style="text-align: center; margin-bottom: 15px;">
          <h1 style="margin: 0; font-size: 28px; color: #667eea; font-weight: bold;">${judoka.naam}</h1>
        </div>
        <div style="text-align: center; background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
          <p style="margin: 0; font-size: 16px; color: #666;"><strong style="color: #667eea;">🏫 Judoschool:</strong></p>
          <p style="margin: 5px 0 0 0; font-size: 18px; font-weight: 600; color: #333;">${judoka.club}</p>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
          <div style="background: #fff3cd; padding: 12px; border-radius: 8px; text-align: center;">
            <p style="margin: 0; font-size: 12px; color: #856404;"><strong>Leeftijdsklasse</strong></p>
            <p style="margin: 5px 0 0 0; font-size: 16px; font-weight: bold; color: #333;">${judoka.leeftijdsklasse}</p>
          </div>
          <div style="background: #d1ecf1; padding: 12px; border-radius: 8px; text-align: center;">
            <p style="margin: 0; font-size: 12px; color: #0c5460;"><strong>Gewichtsklasse</strong></p>
            <p style="margin: 5px 0 0 0; font-size: 16px; font-weight: bold; color: #333;">${judoka.gewichtsklasse}</p>
          </div>
        </div>
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 10px; text-align: center; margin-bottom: 15px;">
          <p style="margin: 0; font-size: 14px; opacity: 0.9;">📅 Ingedeeld in</p>
          <p style="margin: 5px 0 0 0; font-size: 22px; font-weight: bold;">Blok ${judoka.blok} - Mat ${judoka.mat}</p>
          <p style="margin: 5px 0 0 0; font-size: 16px;">⏰ ${blokTijd}</p>
        </div>
        <div style="text-align: center; margin-bottom: 10px;">
          <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;"><strong>⚖️ Weging QR Code</strong></p>
          <img src="${qrCodeUrl}" style="width: 150px; height: 150px; border: 3px solid #667eea; border-radius: 10px;" alt="QR Code">
          <p style="margin: 10px 0 0 0; font-size: 12px; color: #999;">Scan deze code bij de weging</p>
        </div>
      </div>
      <div style="text-align: center; margin-top: 15px;">
        <p style="margin: 0; font-size: 12px; opacity: 0.8;">💪 Veel succes!</p>
        <p style="margin: 5px 0 0 0; font-size: 11px; opacity: 0.7;">Bewaar deze pas op je telefoon</p>
      </div>
    </div>
  `;
}

/**
 * Verstuurt judoka pasjes per judoschool via email
 */
function verstuurJudokaPasjes() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();

  const result = ui.alert(
    'Judoka Pasjes Versturen',
    'Wil je de judoka pasjes per email versturen naar alle judoscholen?\n\nElke judoschool ontvangt een email met de pasjes van hun judoka\'s inclusief QR codes.',
    ui.ButtonSet.YES_NO
  );

  if (result !== ui.Button.YES) {
    return;
  }

  const poulesSheet = ss.getSheetByName('PouleIndeling');
  const configSheet = ss.getSheetByName('Config');

  if (!poulesSheet) {
    ui.alert('Fout', 'PouleIndeling sheet niet gevonden', ui.ButtonSet.OK);
    return;
  }

  // Haal blok tijden op
  const blokTijden = {};
  if (configSheet) {
    const configData = configSheet.getDataRange().getValues();
    for (let i = 1; i < configData.length; i++) {
      if (configData[i][0] && configData[i][0].toString().startsWith('Blok')) {
        const blokNum = parseInt(configData[i][0].match(/\d+/)[0]);
        blokTijden[blokNum] = configData[i][1] || 'Tijd TBD';
      }
    }
  }

  // Haal alle judoka's op
  const data = poulesSheet.getDataRange().getValues();
  const headers = data[0];

  const blokIdx = headers.indexOf("Blok");
  const naamIdx = headers.indexOf("Naam");
  const clubIdx = headers.indexOf("Club");
  const emailIdx = headers.indexOf("Email");
  const leeftijdsKlasseIdx = headers.indexOf("Leeftijdsklasse");
  const gewichtsKlasseIdx = headers.indexOf("Gewichtsklasse");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");

  // Groepeer per judoschool
  const perJudoschool = {};

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    if (!row[naamIdx]) continue;

    const club = row[clubIdx] || 'Onbekend';
    const email = row[emailIdx] || '';

    if (!perJudoschool[club]) {
      perJudoschool[club] = { email: email, judokas: [] };
    }

    // QR code bevat alleen: naam, gewichtsklasse, blok nummer (zoals gevraagd)
    const qrData = {
      naam: row[naamIdx],
      gewichtsklasse: row[gewichtsKlasseIdx] || '',
      blok: row[blokIdx]
    };

    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(JSON.stringify(qrData))}`;

    perJudoschool[club].judokas.push({
      naam: row[naamIdx],
      club: club,
      leeftijdsklasse: row[leeftijdsKlasseIdx] || '',
      gewichtsklasse: row[gewichtsKlasseIdx] || '',
      poule: row[pouleNrIdx],
      mat: row[matIdx],
      blok: row[blokIdx],
      qrCodeUrl: qrCodeUrl,
      blokTijd: blokTijden[row[blokIdx]] || 'Tijd TBD'
    });
  }

  // Verstuur emails
  let aantalVerstuurd = 0;
  let aantalFouten = 0;

  for (const [club, clubData] of Object.entries(perJudoschool)) {
    if (!clubData.email) {
      Logger.log(`Geen email voor: ${club}`);
      aantalFouten++;
      continue;
    }

    try {
      let pasjesHtml = '';
      clubData.judokas.forEach(judoka => {
        pasjesHtml += genereerJudokaPasje(judoka, judoka.qrCodeUrl, judoka.blokTijd);
      });

      const emailBody = `<html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body style="margin: 0; padding: 20px; background: #f5f5f5;"><div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;"><h1 style="color: #667eea; text-align: center;">WestFries Open - Judoka Pasjes</h1><p style="text-align: center; color: #666;">Beste ${club},<br><br>Hieronder vind je de toegangspasjes voor jullie judoka's. Bewaar deze op je telefoon en scan de QR code bij de weging!</p>${pasjesHtml}<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; text-align: center;"><p style="margin: 0; color: #666;"><strong>📱 Tip:</strong> Bewaar deze email op je telefoon!</p></div></div></body></html>`;

      MailApp.sendEmail({
        to: clubData.email,
        subject: `🥋 WestFries Open - Judoka Pasjes voor ${club}`,
        htmlBody: emailBody
      });

      aantalVerstuurd++;
    } catch (error) {
      Logger.log(`Fout bij ${club}: ${error.message}`);
      aantalFouten++;
    }
  }

  ui.alert(
    'Judoka Pasjes Verstuurd',
    `✅ Emails verstuurd: ${aantalVerstuurd}\n⚠️ Fouten: ${aantalFouten}`,
    ui.ButtonSet.OK
  );
}
