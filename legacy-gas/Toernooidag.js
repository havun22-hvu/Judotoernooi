// Toernooidag.gs - Functies voor de toernooidag (weging, aanwezigheid, verplaatsingen)
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalBlokken, getAantalMatten, getGewichtsToleratie)
 * - PouleUtils.js (leesActievePoules, vindBloknummerVoorPoule, berekenAantalWedstrijden)
 * - VerplaatsingUtils.js (vindVerplaatsteJudokas, updateAfwezigeJudokas, hernummerJudokasPerPoule)
 * - ParsingUtils.js (extractGewichtLimiet, corrigeerNaamHoofdletters)
 */

/**
 * Update de blokbladen met gegevens uit de weeglijst en markeer judoka's
 * met gewicht buiten de tolerantiemarge voor overplaatsing
 */
function updateBlokBladen() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const weeglijstSheet = ss.getSheetByName('Weeglijst');
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  
  if (!weeglijstSheet) {
    ui.alert(
      'Fout',
      'Het tabblad "Weeglijst" is niet gevonden.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  if (!poulesSheet) {
    ui.alert(
      'Fout',
      'Het tabblad "PouleIndeling" is niet gevonden.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Haal de ingestelde tolerantiemarge op
  const tolerantieMarge = getGewichtsToleratie();
  
  // Lees alle data uit de weeglijst
  const lastRow = weeglijstSheet.getLastRow();
  const data = weeglijstSheet.getRange(1, 1, lastRow, 15).getValues();
  
  // Lees alle data uit de poule-indeling
  const pouleData = poulesSheet.getDataRange().getValues();
  const pouleHeaders = pouleData[0];
  const pouleTitelIdx = pouleHeaders.indexOf("Pouletitel");
  const pouleNrIdx = pouleHeaders.indexOf("Poule-nr");
  const blokIdx = pouleHeaders.indexOf("Blok");
  const matIdx = pouleHeaders.indexOf("Mat");
  const naamPouleIdx = pouleHeaders.indexOf("Naam");
  const gewichtsKlasseIdx = pouleHeaders.indexOf("Gewichtsklasse");
  
  // Zoek de juiste kolommen in weeglijst
  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const leeftijdsklasseIdx = headers.indexOf("Lft-klasse");
  const aanwezigIdx = headers.indexOf("Aanwezig");
  const mutatieIdx = headers.indexOf("Gew.mutatie");
  const opmerkingenIdx = headers.indexOf("Opmerkingen");
  
  if (naamIdx === -1 || aanwezigIdx === -1) {
    ui.alert(
      'Fout',
      'De kolommen "Naam" en/of "Aanwezig" zijn niet gevonden in de weeglijst.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Maak een map van naam naar aanwezigheid en gewichtsmutatie
  const judokaInfo = {};
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const naam = row[naamIdx];
    const leeftijdsklasse = leeftijdsklasseIdx !== -1 ? row[leeftijdsklasseIdx] || "" : "";
    
    if (naam) {
      judokaInfo[naam] = {
        aanwezig: aanwezigIdx !== -1 ? row[aanwezigIdx] || "" : "",
        gewichtsmutatie: mutatieIdx !== -1 ? row[mutatieIdx] || "" : "",
        opmerkingen: opmerkingenIdx !== -1 ? row[opmerkingenIdx] || "" : "",
        leeftijdsklasse: leeftijdsklasse
      };
    }
  }
  
  // Verzamel alle beschikbare poules per leeftijdsklasse en gewichtsklasse
  const beschikbarePoules = {};
  let aantalBeschikbarePoules = 0;

  for (let i = 1; i < pouleData.length; i++) {
    const row = pouleData[i];
    const pouleTitel = row[pouleTitelIdx];
    const pouleNr = row[pouleNrIdx];
    const blokNr = row[blokIdx];
    const matNr = row[matIdx];
    const gewichtsklasse = row[gewichtsKlasseIdx];

    // Sla rijen over die geen geldige poule zijn
    if (!pouleTitel || !pouleNr) continue;

    // Extract leeftijdsklasse en gewichtsklasse uit de pouletitel
    // Formaat is meestal: "Leeftijdsklasse Gewichtsklasse Poule X"
    const match = pouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg|^\d+\s+kg)/i);
    if (!match) continue;

    const leeftijdsklasse = match[1].trim();
    const gewichtsklasseStr = match[2].trim();

    // Maak sleutel om poules te groeperen op leeftijdsklasse en gewichtsklasse
    const sleutel = `${leeftijdsklasse}-${gewichtsklasseStr}`;

    if (!beschikbarePoules[sleutel]) {
      beschikbarePoules[sleutel] = [];
    }

    // Controleer of deze poule al in de lijst staat (om duplicaten te voorkomen)
    const bestaandePoulesNrs = beschikbarePoules[sleutel].map(p => p.pouleNr);
    if (bestaandePoulesNrs.includes(pouleNr)) continue;

    // Tel het aantal judoka's in deze poule
    let aantalJudokas = 0;

    // Tellen van judoka's in deze poule
    for (let j = 1; j < pouleData.length; j++) {
      if (pouleData[j][pouleNrIdx] === pouleNr) {
        // Alleen tellen als het een judoka is (geen titel)
        if (pouleData[j][naamPouleIdx] && typeof pouleData[j][naamPouleIdx] !== 'string' ||
            (typeof pouleData[j][naamPouleIdx] === 'string' &&
             !pouleData[j][naamPouleIdx].includes("TOTAAL") &&
             !pouleData[j][naamPouleIdx].includes("Poule"))) {
          aantalJudokas++;
        }
      }
    }

    // Bereken aantal wedstrijden
    let aantalWedstrijden = 0;
    if (aantalJudokas === 3) {
      aantalWedstrijden = 6; // 3 judoka's spelen dubbel tegen elkaar
    } else if (aantalJudokas > 1) {
      aantalWedstrijden = Math.floor(aantalJudokas * (aantalJudokas - 1) / 2);
    }

    // Extract gewichtslimiet voor gewichtsklasse validatie
    const gewichtLimiet = extractGewichtLimiet(gewichtsklasseStr);

    // Voeg poule-info toe
    beschikbarePoules[sleutel].push({
      pouleNr: pouleNr,
      pouleTitel: pouleTitel,
      blokNr: blokNr,
      matNr: matNr,
      aantalJudokas: aantalJudokas,
      aantalWedstrijden: aantalWedstrijden,
      gewichtLimiet: gewichtLimiet,
      gewichtsklasseStr: gewichtsklasseStr
    });
  }

  // Zoek alle blokbladen
  const allSheets = ss.getSheets();
  let blokSheets = [];
  let updatedCount = 0;
  
  for (const sheet of allSheets) {
    const sheetName = sheet.getName();
    if (sheetName.match(/^Blok \d+$/)) {
      blokSheets.push(sheet);
    }
  }
  
  // Doorloop alle blokbladen en update de gegevens
  for (const blokSheet of blokSheets) {
    const lastBlokRow = blokSheet.getLastRow();
    if (lastBlokRow <= 1) continue; // Skip lege bladen
    
    // Lees de headers om de juiste kolommen te vinden
    const blokHeaders = blokSheet.getRange(1, 1, 1, 20).getValues()[0];
    const blokNaamIdx = blokHeaders.indexOf("Naam");
    const blokNrIdx = blokHeaders.indexOf("Nr");
    const blokAanwezigIdx = blokHeaders.indexOf("Aanwezig");
    const blokMutatieIdx = blokHeaders.indexOf("Gew.mutatie");
    const blokPouleNrIdx = blokHeaders.indexOf("Poule-nr");
    const blokOpmerkingenIdx = blokHeaders.indexOf("Opmerkingen");
    const blokGewichtsklasseIdx = blokHeaders.indexOf("Gew. klasse");
    const blokAltPouleIdx = blokHeaders.indexOf("Alt. poule");
    
    if (blokNaamIdx === -1 || blokAanwezigIdx === -1) continue; // Skip bladen zonder de juiste headers
    
    // Extra check: als pouleNrIdx niet gevonden is, gebruik kolom J (index 9)
    const pouleNrIdx = blokPouleNrIdx !== -1 ? blokPouleNrIdx : 9;
    
    // Lees alle data van het blokblad
    const blokData = blokSheet.getRange(1, 1, lastBlokRow, Math.max(blokHeaders.length, 15)).getValues();
    
    // Verzamel poules en judoka's per poule
    const pouleMap = {};
    
    // Eerste doorloop: verzamel poule informatie
    for (let i = 1; i < blokData.length; i++) {
      const naam = blokData[i][blokNaamIdx];
      const pouleNr = blokData[i][pouleNrIdx];
      
      // Sla lege rijen of titelrijen over
      if (!naam || naam.startsWith("MAT ") || naam.includes("Poule")) continue;
      if (!pouleNr) continue;
      
      // Maak entry voor deze poule als die nog niet bestaat
      if (!pouleMap[pouleNr]) {
        // Zoek de pouleTitel en gewichtsklasse
        let pouleTitel = "";
        let gewichtsklasse = "";
        
        // Zoek in de pouleindeling
        for (let j = 1; j < pouleData.length; j++) {
          if (pouleData[j][pouleNrIdx] === pouleNr) {
            pouleTitel = pouleData[j][pouleTitelIdx] || "";
            gewichtsklasse = pouleData[j][gewichtsKlasseIdx] || "";
            break;
          }
        }
        
        // Extract gewichtslimiet uit de pouleTitel
        let gewichtLimiet = null;
        if (pouleTitel) {
          const match = pouleTitel.match(/([+-]?\d+)\s*kg/);
          if (match) {
            const gewichtsklasseStr = match[0];
            gewichtLimiet = extractGewichtLimiet(gewichtsklasseStr);
          }
        }
        
        pouleMap[pouleNr] = {
          judokas: [],
          pouleTitel: pouleTitel,
          gewichtsklasse: gewichtsklasse,
          gewichtLimiet: gewichtLimiet
        };
      }
      
      // Voeg judoka toe aan de poule
      pouleMap[pouleNr].judokas.push({
        row: i + 1,
        naam: naam
      });
    }

    // Bepaal ondergrens voor elke poule
    // Groepeer poules per leeftijdsklasse
    const poulesPerLeeftijdsklasse = {};
    for (const [pouleNr, poule] of Object.entries(pouleMap)) {
      if (poule.pouleTitel) {
        const match = poule.pouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg)/i);
        if (match) {
          const leeftijdsklasse = match[1].trim();
          if (!poulesPerLeeftijdsklasse[leeftijdsklasse]) {
            poulesPerLeeftijdsklasse[leeftijdsklasse] = [];
          }
          poulesPerLeeftijdsklasse[leeftijdsklasse].push({
            pouleNr: pouleNr,
            gewichtLimiet: poule.gewichtLimiet
          });
        }
      }
    }

    // Sorteer en bepaal ondergrenzen
    for (const leeftijdsklasse in poulesPerLeeftijdsklasse) {
      const poules = poulesPerLeeftijdsklasse[leeftijdsklasse];
      // Sorteer op gewicht (alleen -XX kg klassen)
      const minKlassen = poules.filter(p => p.gewichtLimiet && p.gewichtLimiet.type === "min")
                               .sort((a, b) => a.gewichtLimiet.waarde - b.gewichtLimiet.waarde);

      // Zet ondergrenzen
      for (let i = 0; i < minKlassen.length; i++) {
        if (i > 0) {
          // De ondergrens is de vorige gewichtsklasse
          pouleMap[minKlassen[i].pouleNr].gewichtLimietOnder = minKlassen[i - 1].gewichtLimiet.waarde;
        }
      }
    }

    // Tweede doorloop: update informatie en nummering
    for (const [pouleNr, poule] of Object.entries(pouleMap)) {
      // Herbereken nummering binnen elke poule
      for (let i = 0; i < poule.judokas.length; i++) {
        const judoka = poule.judokas[i];
        const nieuwNummer = i + 1;
        
        // Update nummer
        if (blokNrIdx !== -1) {
          blokSheet.getRange(judoka.row, blokNrIdx + 1).setValue(nieuwNummer);
        }
        
        // Update aanwezigheid en gewichtsmutatie
        const info = judokaInfo[judoka.naam];
        if (info) {
          // Update aanwezigheid
          if (info.aanwezig) {
            blokSheet.getRange(judoka.row, blokAanwezigIdx + 1).setValue(info.aanwezig);
            
            // Aanwezigheid kleuren
            if (info.aanwezig === "Ja") {
              blokSheet.getRange(judoka.row, blokAanwezigIdx + 1).setBackground("#D9EAD3"); // Groen voor aanwezig
            } else {
              blokSheet.getRange(judoka.row, blokAanwezigIdx + 1).setBackground("#F4CCCC"); // Rood voor niet aanwezig
            }
            
            updatedCount++;
          }
          
          // Update gewichtsmutatie als de kolom bestaat
          if (blokMutatieIdx !== -1) {
            // Sla het huidige gewicht op
            const huidigeGewichtsmutatie = blokData[judoka.row - 1][blokMutatieIdx];
            const nieuweGewichtsmutatie = info.gewichtsmutatie || "";

            // Update altijd (ook als leeg), tenzij exact hetzelfde
            if (nieuweGewichtsmutatie !== huidigeGewichtsmutatie) {
              blokSheet.getRange(judoka.row, blokMutatieIdx + 1).setValue(nieuweGewichtsmutatie);
              updatedCount++;
            }

            // Controleer of het gewicht buiten de tolerantiemarge valt (alleen bij ingevuld gewicht)
            if (nieuweGewichtsmutatie && poule.gewichtLimiet !== null) {
              // Extract het numerieke gewicht uit de gewichtsmutatie
              const gewichtMatch = nieuweGewichtsmutatie.toString().match(/([0-9]*[.])?[0-9]+/);

              if (gewichtMatch) {
                const gemeten_gewicht = parseFloat(gewichtMatch[0]);

                // Bepaal of dit gewicht buiten de toegestane marge valt
                if (isGewichtBuitenMarge(gemeten_gewicht, poule.gewichtLimiet, tolerantieMarge, poule.gewichtLimietOnder)) {
                  // Markeer met rode achtergrond
                  blokSheet.getRange(judoka.row, blokMutatieIdx + 1).setBackground("#F4CCCC");

                  // Zoek en toon mogelijke alternatieve poules
                  const mogelijkePoules = vindMogelijkePoules(gemeten_gewicht, info.leeftijdsklasse, poule.pouleTitel, beschikbarePoules);

                  // Toon mogelijke poules in de "Alt. poule" kolom
                  if (blokAltPouleIdx !== -1 && mogelijkePoules) {
                    blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setValue(mogelijkePoules);
                    blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setBackground("#FFF3CD"); // Licht oranje achtergrond
                  }
                } else {
                  // Gewicht binnen marge - groene achtergrond en leeg Alt. poule
                  blokSheet.getRange(judoka.row, blokMutatieIdx + 1).setBackground("#D9EAD3");

                  // Maak Alt. poule kolom leeg (gewicht is goed)
                  if (blokAltPouleIdx !== -1) {
                    blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setValue("");
                    blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setBackground(null);
                  }
                }
              }
            } else if (!nieuweGewichtsmutatie) {
              // Geen gewichtsmutatie - verwijder eventuele achtergrondkleur en Alt. poule
              blokSheet.getRange(judoka.row, blokMutatieIdx + 1).setBackground(null);

              // Maak Alt. poule kolom ook leeg
              if (blokAltPouleIdx !== -1) {
                blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setValue("");
                blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setBackground(null);
              }
            }
          }
          
          // Update opmerkingen als de kolom bestaat
          if (blokOpmerkingenIdx !== -1 && info.opmerkingen) {
            // Sla de huidige opmerking op
            const huidigeOpmerking = blokData[judoka.row - 1][blokOpmerkingenIdx];
            
            // Alleen updaten als de waarde gewijzigd is
            if (info.opmerkingen !== huidigeOpmerking) {
              blokSheet.getRange(judoka.row, blokOpmerkingenIdx + 1).setValue(info.opmerkingen);
              updatedCount++;
            }
          }
        }
      }
    }
  }
  
  ui.alert(
    'Blokbladen bijgewerkt',
    `Bijgewerkt: ${updatedCount} judoka-entries in de blokbladen. ` + 
    `Nummering per poule is opnieuw berekend. ` +
    `Gewichtsmutaties buiten de tolerantiemarge (${tolerantieMarge} kg) zijn rood gemarkeerd.`,
    ui.ButtonSet.OK
  );
}

// Functie extractGewichtLimiet is nu in ParsingUtils.js

/**
 * Controleert of een gemeten gewicht buiten de toegestane marge valt
 * @param {number} gemeten_gewicht - Het gemeten gewicht in kg
 * @param {Object} gewichtLimiet - Object met type ('min'/'plus') en waarde (in kg)
 * @param {number} tolerantieMarge - De toegestane tolerantiemarge in kg
 * @return {boolean} True als het gewicht buiten de marge valt, anders false
 */
function isGewichtBuitenMarge(gemeten_gewicht, gewichtLimiet, tolerantieMarge, gewichtLimietOnder) {
  if (!gewichtLimiet) return false;

  if (gewichtLimiet.type === "min") {
    // -XX kg: gewicht moet <= XX + tolerantie zijn EN >= ondergrens + tolerantie
    const teLicht = gewichtLimietOnder ? gemeten_gewicht < (gewichtLimietOnder + tolerantieMarge) : false;
    const teZwaar = gemeten_gewicht > (gewichtLimiet.waarde + tolerantieMarge);
    return teLicht || teZwaar;
  } else {
    // +XX kg: gewicht moet > XX - tolerantie zijn
    return gemeten_gewicht <= (gewichtLimiet.waarde - tolerantieMarge);
  }
}

/**
 * Vindt mogelijke poules voor een judoka met een bepaald gewicht
 * @param {number} gemeten_gewicht - Het gemeten gewicht in kg
 * @param {string} huidigePouleTitel - De huidige pouletitel
 * @param {Object} beschikbarePoules - Map van alle beschikbare poules per gewichtsklasse
 * @return {string} Informatie over mogelijke poules of leeg indien geen gevonden
 */
function vindMogelijkePoules(gemeten_gewicht, leeftijdsklasse, huidigePouleTitel, beschikbarePoules) {
  // Als leeftijdsklasse niet meegegeven is, haal uit pouleTitel
  if (!leeftijdsklasse) {
    if (!huidigePouleTitel) return "";
    const match = huidigePouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg)/i);
    if (!match) return "";
    leeftijdsklasse = match[1].trim();
  }

  if (!leeftijdsklasse || !gemeten_gewicht) return "";

  // Zoek alle poules voor deze leeftijdsklasse waar het gewicht PAS binnen de grenzen
  const mogelijkePoules = [];

  for (const [sleutel, poules] of Object.entries(beschikbarePoules)) {
    // Controleer of dit dezelfde leeftijdsklasse is
    if (sleutel.startsWith(leeftijdsklasse)) {
      for (const poule of poules) {
        if (poule.gewichtLimiet) {
          // Voor "-XX kg" klassen: judoka moet LICHTER zijn dan XX kg
          // Dus als gemeten_gewicht <= XX, dan past het
          if (poule.gewichtLimiet.type === "min") {
            if (gemeten_gewicht <= poule.gewichtLimiet.waarde) {
              mogelijkePoules.push({
                pouleNr: poule.pouleNr,
                blokNr: poule.blokNr,
                matNr: poule.matNr,
                gewichtsklasseStr: poule.gewichtsklasseStr,
                leeftijdsklasse: leeftijdsklasse,
                gewichtLimiet: poule.gewichtLimiet.waarde
              });
            }
          }
          // Voor "+XX kg" klassen: judoka moet ZWAARDER zijn dan XX kg
          // Dus als gemeten_gewicht > XX, dan past het
          else if (poule.gewichtLimiet.type === "plus") {
            if (gemeten_gewicht > poule.gewichtLimiet.waarde) {
              mogelijkePoules.push({
                pouleNr: poule.pouleNr,
                blokNr: poule.blokNr,
                matNr: poule.matNr,
                gewichtsklasseStr: poule.gewichtsklasseStr,
                leeftijdsklasse: leeftijdsklasse,
                gewichtLimiet: poule.gewichtLimiet.waarde
              });
            }
          }
        }
      }
    }
  }

  // Als er geen mogelijke poules zijn gevonden
  if (mogelijkePoules.length === 0) return "";

  // Sorteer: voor min-klassen de hoogste limiet eerst (dichtsbijzijnde zwaardere klasse)
  // Voor plus-klassen de laagste limiet eerst (dichtsbijzijnde lichtere klasse)
  mogelijkePoules.sort((a, b) => {
    // Sorteer eerst op gewichtLimiet (dichtsbijzijnde klasse krijgt voorrang)
    const verschilA = Math.abs(gemeten_gewicht - a.gewichtLimiet);
    const verschilB = Math.abs(gemeten_gewicht - b.gewichtLimiet);
    if (verschilA !== verschilB) return verschilA - verschilB;

    // Dan op blok
    if (a.blokNr !== b.blokNr) return a.blokNr - b.blokNr;

    // Dan op mat
    return a.matNr - b.matNr;
  });

  // Verzamel alle unieke matten voor de beste suggestie
  const eerste = mogelijkePoules[0];
  const mattenVoorDezeKlasse = new Set();

  // Zoek alle poules met dezelfde leeftijdsklasse en gewichtsklasse
  for (const poule of mogelijkePoules) {
    if (poule.gewichtsklasseStr === eerste.gewichtsklasseStr &&
        poule.blokNr === eerste.blokNr &&
        poule.matNr) {
      mattenVoorDezeKlasse.add(poule.matNr);
    }
  }

  // Sorteer matten en maak een string
  const mattenArray = Array.from(mattenVoorDezeKlasse).sort((a, b) => a - b);
  const mattenStr = mattenArray.length > 0 ? ` (Mat ${mattenArray.join(', ')})` : '';

  // Toon de suggestie met matten
  const resultaat = `${eerste.leeftijdsklasse}, ${eerste.gewichtsklasseStr}, Blok ${eerste.blokNr}${mattenStr}`;
  return resultaat;
}

// Functie getGewichtsToleratie is nu in ConfigUtils.js

 /**
 * Verplaatst een judoka naar een andere poule
 * Aangepaste implementatie die ook gewichtsmutatie verwijdert na verplaatsing
 */
function verplaatsJudokaNaarAnderePoule() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // Controleer of er een blokblad geselecteerd is
  const activeSheet = ss.getActiveSheet();
  const sheetName = activeSheet.getName();
  
  if (!sheetName.match(/^Blok \d+$/)) {
    ui.alert('Geen blokblad geselecteerd', 'Selecteer een blokblad (Blok X) en probeer het opnieuw.', ui.ButtonSet.OK);
    return;
  }
  
  // Haal geselecteerde rijen op
  const selection = activeSheet.getSelection();
  const ranges = selection.getActiveRangeList().getRanges();
  
  if (!ranges || ranges.length === 0) {
    ui.alert("Geen selectie", "Selecteer eerst de judoka-rijen die je wilt verplaatsen.", ui.ButtonSet.OK);
    return;
  }
  
  // Lees de kolommen in het actieve blad
  const numColumns = activeSheet.getLastColumn();
  const headerRow = activeSheet.getRange(1, 1, 1, numColumns).getValues()[0];
  const bronNaamIdx = headerRow.indexOf("Naam");
  const bronPouleNrIdx = headerRow.indexOf("Poule-nr");
  const bronAanwezigIdx = headerRow.indexOf("Aanwezig");
  const bronMutatieIdx = headerRow.indexOf("Gew.mutatie");
  const bronOpmerkingenIdx = headerRow.indexOf("Opmerkingen");
  
  if (bronNaamIdx === -1 || bronPouleNrIdx === -1) {
    ui.alert('Fout', 'Kon de benodigde kolommen niet vinden in het blokblad.', ui.ButtonSet.OK);
    return;
  }
  
  // Verzamel alle geselecteerde judoka's - Gebruik techniek uit PouleIndeling.gs
  const selectedJudokas = [];
  
  for (let r = 0; r < ranges.length; r++) {
    const range = ranges[r];
    const startRow = range.getRow();
    const numRows = range.getNumRows();
    
    for (let i = 0; i < numRows; i++) {
      const currentRow = startRow + i;
      const rowData = activeSheet.getRange(currentRow, 1, 1, numColumns).getValues()[0];
      const naam = rowData[bronNaamIdx];
      
      // Controleer of dit een judoka is (heeft een naam)
      if (naam && naam.toString().trim() !== '') {
        // Skip rijen met mat of poule headers
        if (naam.startsWith("MAT ") || naam.includes("Poule")) {
          continue;
        }
        
        selectedJudokas.push({
          row: currentRow,
          data: rowData,
          naam: naam,
          huidigePoule: rowData[bronPouleNrIdx],
          aanwezig: bronAanwezigIdx !== -1 ? rowData[bronAanwezigIdx] : "",
          gewichtsmutatie: bronMutatieIdx !== -1 ? rowData[bronMutatieIdx] : "",
          opmerkingen: bronOpmerkingenIdx !== -1 ? rowData[bronOpmerkingenIdx] : ""
        });
      }
    }
  }
  
  if (selectedJudokas.length === 0) {
    ui.alert("Geen judoka's geselecteerd", "Selecteer judoka's in het blokblad.", ui.ButtonSet.OK);
    return;
  }
  
  // Vraag doelpoule
  const response = ui.prompt(
    `Verplaats ${selectedJudokas.length === 1 ? 'Judoka' : 'Judoka\'s'}`,
    `Naar welk poulenummer wil je ${selectedJudokas.length === 1 ? 'deze judoka' : 'deze ' + selectedJudokas.length + ' judoka\'s'} verplaatsen?`,
    ui.ButtonSet.OK_CANCEL
  );
  
  if (response.getSelectedButton() !== ui.Button.OK) {
    return;
  }
  
  const doelPoule = parseInt(response.getResponseText());
  if (isNaN(doelPoule)) {
    ui.alert("Ongeldig poulenummer", "Voer een geldig poulenummer in.", ui.ButtonSet.OK);
    return;
  }
  
  // Zoek de doelpoule info in PouleIndeling
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" is niet gevonden.', ui.ButtonSet.OK);
    return;
  }
  
  const pouleData = poulesSheet.getDataRange().getValues();
  const pouleHeaders = pouleData[0];
  const indNaamIdx = pouleHeaders.indexOf("Naam");
  const indPouleNrIdx = pouleHeaders.indexOf("Poule-nr");
  const indBlokIdx = pouleHeaders.indexOf("Blok");
  const indMatIdx = pouleHeaders.indexOf("Mat");
  const indTitelIdx = pouleHeaders.indexOf("Pouletitel");
  
  // Zoek de doelpoule
  let doelBlok = null;
  let doelPouleMat = null;
  let doelPouleTitel = null;
  
  for (let i = 1; i < pouleData.length; i++) {
    if (pouleData[i][indPouleNrIdx] === doelPoule) {
      doelBlok = pouleData[i][indBlokIdx];
      doelPouleMat = pouleData[i][indMatIdx];
      doelPouleTitel = pouleData[i][indTitelIdx];
      break;
    }
  }
  
  if (doelBlok === null) {
    ui.alert('Doelpoule niet gevonden', 'Geen poule gevonden met nummer ' + doelPoule, ui.ButtonSet.OK);
    return;
  }
  
  // Bepaal het doelblad
  const doelBladNaam = `Blok ${doelBlok}`;
  const doelSheet = ss.getSheetByName(doelBladNaam);
  
  if (!doelSheet) {
    ui.alert('Doelblad niet gevonden', `Het blad "${doelBladNaam}" bestaat niet.`, ui.ButtonSet.OK);
    return;
  }
  
  // Lees headers van doelblad
  const doelHeaders = doelSheet.getRange(1, 1, 1, doelSheet.getLastColumn()).getValues()[0];
  const doelNaamIdx = doelHeaders.indexOf("Naam");
  const doelPouleNrIdx = doelHeaders.indexOf("Poule-nr");
  const doelAanwezigIdx = doelHeaders.indexOf("Aanwezig");
  const doelMutatieIdx = doelHeaders.indexOf("Gew.mutatie");
  const doelOpmerkingenIdx = doelHeaders.indexOf("Opmerkingen");
  const doelGewichtsklasseIdx = doelHeaders.indexOf("Gew. klasse");
  const doelAltPouleIdx = doelHeaders.indexOf("Alt. poule");

  // Extract gewichtsklasse uit doelPouleTitel
  let doelGewichtsklasse = "";
  if (doelPouleTitel) {
    const match = doelPouleTitel.match(/([+-]?\d+\s+kg)/i);
    if (match) {
      doelGewichtsklasse = match[1].trim();
    }
  }
  
  // Update weeglijst met opmerkingen over de verplaatsing - één opmerking
  const weeglijstSheet = ss.getSheetByName('Weeglijst');
  if (weeglijstSheet) {
    const weegData = weeglijstSheet.getDataRange().getValues();
    const weegHeaders = weegData[0];
    const weegNaamIdx = weegHeaders.indexOf("Naam");
    const weegOpmerkingenIdx = weegHeaders.indexOf("Opmerkingen");
    const weegAanwezigIdx = weegHeaders.indexOf("Aanwezig");
    const weegMutatieIdx = weegHeaders.indexOf("Gew.mutatie");
    const weegGewichtsklasseIdx = weegHeaders.indexOf("Gew. klasse");
    
    if (weegNaamIdx !== -1 && weegOpmerkingenIdx !== -1) {
      for (const judoka of selectedJudokas) {
        for (let i = 1; i < weegData.length; i++) {
          if (weegData[i][weegNaamIdx] === judoka.naam) {
            let huidigeOpmerking = weegData[i][weegOpmerkingenIdx] || "";
            let nieuweOpmerking = `Verplaatst van poule ${judoka.huidigePoule} naar poule ${doelPoule}`;
            
            // Controleer of deze opmerking al bestaat voordat we toevoegen
            if (!huidigeOpmerking.includes(nieuweOpmerking)) {
              huidigeOpmerking = huidigeOpmerking ? huidigeOpmerking + "; " + nieuweOpmerking : nieuweOpmerking;
              weeglijstSheet.getRange(i + 1, weegOpmerkingenIdx + 1).setValue(huidigeOpmerking);
            }
            
            // Update de achtergrondkleur voor aanwezigheid
            if (weegAanwezigIdx !== -1 && judoka.aanwezig === "Ja") {
              weeglijstSheet.getRange(i + 1, weegAanwezigIdx + 1).setBackground("#D9EAD3"); // Groen voor aanwezig
            }

            // Verwijder gewichtsmutatie uit weeglijst na verplaatsing
            // Zo wordt deze niet opnieuw doorgegeven bij volgende "Blokbladen bijwerken"
            if (weegMutatieIdx !== -1) {
              weeglijstSheet.getRange(i + 1, weegMutatieIdx + 1).setValue("");
              weeglijstSheet.getRange(i + 1, weegMutatieIdx + 1).setBackground(null);
            }

            // Update gewichtsklasse in weeglijst naar de nieuwe poule
            if (weegGewichtsklasseIdx !== -1 && doelGewichtsklasse) {
              weeglijstSheet.getRange(i + 1, weegGewichtsklasseIdx + 1).setValue(doelGewichtsklasse);
            }

            break;
          }
        }
      }
    }
  }
  
  // Update poule-indeling voor alle geselecteerde judoka's
  for (const judoka of selectedJudokas) {
    for (let i = 1; i < pouleData.length; i++) {
      if (pouleData[i][indNaamIdx] === judoka.naam) {
        poulesSheet.getRange(i + 1, indBlokIdx + 1).setValue(doelBlok);
        poulesSheet.getRange(i + 1, indMatIdx + 1).setValue(doelPouleMat);
        poulesSheet.getRange(i + 1, indPouleNrIdx + 1).setValue(doelPoule);
        poulesSheet.getRange(i + 1, indTitelIdx + 1).setValue(doelPouleTitel);
        break;
      }
    }
  }
  
  // Zoek de doelpoule in het doelblad
  const doelData = doelSheet.getDataRange().getValues();
  let laatsteRij = -1;
  let doelPouleHeader = -1;
  
  // Zoek eerst de doelpoule titel
  for (let i = 0; i < doelData.length; i++) {
    if (doelData[i][doelNaamIdx] && 
        typeof doelData[i][doelNaamIdx] === 'string' && 
        doelData[i][doelNaamIdx].includes("Poule") && 
        doelData[i][doelNaamIdx].includes(doelPoule.toString())) {
      doelPouleHeader = i;
      break;
    }
  }
  
  // Als de poule titel is gevonden, zoek de laatste judoka in die poule
  if (doelPouleHeader !== -1) {
    for (let i = doelPouleHeader + 1; i < doelData.length; i++) {
      // Als we een nieuwe poule of mat header tegenkomen, stoppen we met zoeken
      if (doelData[i][doelNaamIdx] && 
          typeof doelData[i][doelNaamIdx] === 'string' && 
          (doelData[i][doelNaamIdx].includes("Poule") || doelData[i][doelNaamIdx].startsWith("MAT "))) {
        break;
      }
      
      // Als deze rij een judoka bevat (met poulenummer)
      if (doelData[i][doelPouleNrIdx] === doelPoule) {
        laatsteRij = i;
      }
    }
  }
  
  // Als we geen poule header vonden, zoek dan op poulenummer
  if (doelPouleHeader === -1) {
    for (let i = 0; i < doelData.length; i++) {
      if (doelData[i][doelPouleNrIdx] === doelPoule) {
        laatsteRij = i;
      }
    }
  }
  
  // Bepaal invoegpunt ALTIJD NA de laatste judoka in de poule, niet erboven
  let invoegRij = laatsteRij !== -1 ? laatsteRij : (doelPouleHeader !== -1 ? doelPouleHeader : doelSheet.getLastRow());
  
  // Sorteer judoka's op rijnummer van hoog naar laag om verwijderproblemen te voorkomen
  // Gebruik hier de techniek van PouleIndeling.gs
  selectedJudokas.sort((a, b) => b.row - a.row);
  
  // Maak kopieën van judoka's data voordat we rijen verwijderen
  const judokasToAdd = [];
  
  for (const judoka of selectedJudokas) {
    // Kopieer de rij data en pas aan voor doelpoule
    const updatedData = [];
    
    // Opstellen van rij data voor het doelblad
    for (let i = 0; i < doelHeaders.length; i++) {
      if (i === doelAanwezigIdx) {
        updatedData[i] = judoka.aanwezig || "Nee";
      } else if (i === doelNaamIdx) {
        updatedData[i] = judoka.naam;
      } else if (i === doelPouleNrIdx) {
        updatedData[i] = doelPoule;
      } else if (i === doelGewichtsklasseIdx) {
        // Update gewichtsklasse naar nieuwe poule
        updatedData[i] = doelGewichtsklasse;
      } else if (i === doelMutatieIdx) {
       // Behoud de gewichtsmutatie, alleen verwijder later de markering
        updatedData[i] = judoka.gewichtsmutatie;
      } else if (i === doelAltPouleIdx) {
        // Leeg de Alt. poule kolom bij verplaatsing
        updatedData[i] = "";
      } else if (i === doelOpmerkingenIdx) {
        let huidigeOpmerking = judoka.opmerkingen || "";
        let nieuweOpmerking = `Verplaatst van poule ${judoka.huidigePoule} naar poule ${doelPoule}`;

        // Controleer of deze opmerking al bestaat voordat we toevoegen
        updatedData[i] = huidigeOpmerking.includes(nieuweOpmerking) ?
                         huidigeOpmerking :
                         (huidigeOpmerking ? huidigeOpmerking + "; " + nieuweOpmerking : nieuweOpmerking);
      } else if (doelHeaders[i] === "Mat") {
        updatedData[i] = doelPouleMat;
      } else {
        // Behoud overige waarden
        updatedData[i] = i < judoka.data.length ? judoka.data[i] : "";
      }
    }
    
    judokasToAdd.push(updatedData);
    
    // Verwijder de originele rij uit het actieve blad
    activeSheet.deleteRow(judoka.row);
  }
  
  // Voeg judoka's toe aan doelblad
  if (invoegRij === -1) {
    // Voeg toe aan het eind
    for (const judokaData of judokasToAdd) {
      doelSheet.appendRow(judokaData);
    }
  } else {
    // Voeg toe NA de laatste judoka van de poule
    for (let i = 0; i < judokasToAdd.length; i++) {
      doelSheet.insertRowAfter(invoegRij + i);
      doelSheet.getRange(invoegRij + i + 1, 1, 1, judokasToAdd[i].length).setValues([judokasToAdd[i]]);
      
      // Pas opmaak toe
      doelSheet.getRange(invoegRij + i + 1, 1, 1, judokasToAdd[i].length).setHorizontalAlignment("center");
      
      // Naam en Club links uitlijnen voor betere leesbaarheid
      if (doelNaamIdx !== -1) {
        doelSheet.getRange(invoegRij + i + 1, doelNaamIdx + 1).setHorizontalAlignment("left");
      }
      if (doelHeaders.indexOf("Club") !== -1) {
        doelSheet.getRange(invoegRij + i + 1, doelHeaders.indexOf("Club") + 1).setHorizontalAlignment("left");
      }
      
      // Opmerkingen links uitlijnen
      if (doelOpmerkingenIdx !== -1) {
        doelSheet.getRange(invoegRij + i + 1, doelOpmerkingenIdx + 1).setHorizontalAlignment("left");
      }
      
      // NIEUW: Zet achtergrondkleur voor aanwezigheid
      if (doelAanwezigIdx !== -1) {
        const aanwezig = judokasToAdd[i][doelAanwezigIdx];
        if (aanwezig === "Ja") {
          doelSheet.getRange(invoegRij + i + 1, doelAanwezigIdx + 1).setBackground("#D9EAD3"); // Groen voor aanwezig
        } else if (aanwezig === "Nee") {
          doelSheet.getRange(invoegRij + i + 1, doelAanwezigIdx + 1).setBackground("#F4CCCC"); // Rood voor niet aanwezig
        }
      }

      // Gewichtsmutatie heeft geen rode achtergrond meer na verplaatsing
      if (doelMutatieIdx !== -1) {
        doelSheet.getRange(invoegRij + i + 1, doelMutatieIdx + 1).setBackground(null);
      }

      // Verwijder Alt. poule informatie en achtergrond na verplaatsing
      if (doelAltPouleIdx !== -1) {
        doelSheet.getRange(invoegRij + i + 1, doelAltPouleIdx + 1).setValue("");
        doelSheet.getRange(invoegRij + i + 1, doelAltPouleIdx + 1).setBackground(null);
      }
    }
  }
  
  // Hernummer judoka's in beide bladen
  hernummerJudokasPerPoule(activeSheet);
  if (doelSheet !== activeSheet) {
    hernummerJudokasPerPoule(doelSheet);
  }

  // Pas kolombreedtes aan in beide blokbladen
  pasKolombreedtesAan(activeSheet);
  if (doelSheet !== activeSheet) {
    pasKolombreedtesAan(doelSheet);
  }

  ui.alert(
    'Judoka(s) verplaatst',
    `${selectedJudokas.length} judoka(s) verplaatst naar poule ${doelPoule} in ${doelBladNaam}.`,
    ui.ButtonSet.OK
  );
}

/**
 * Past kolombreedtes aan voor Opmerkingen en Alt. poule kolommen
 * @param {Sheet} sheet - Het blokblad
 */
function pasKolombreedtesAan(sheet) {
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  const opmerkingenIdx = headers.indexOf("Opmerkingen");
  const altPouleIdx = headers.indexOf("Alt. poule");

  // Pas eerst alle kolommen automatisch aan
  for (let col = 1; col <= headers.length; col++) {
    sheet.autoResizeColumn(col);
  }

  // Pas Opmerkingen kolom aan
  if (opmerkingenIdx !== -1) {
    const opmerkingenCol = opmerkingenIdx + 1;
    const huidigeBreedteOpm = sheet.getColumnWidth(opmerkingenCol);

    // Minimum 200px, maximum 350px
    if (huidigeBreedteOpm < 200) {
      sheet.setColumnWidth(opmerkingenCol, 200);
    } else if (huidigeBreedteOpm > 350) {
      sheet.setColumnWidth(opmerkingenCol, 350);
    }
  }

  // Pas Alt. poule kolom aan
  if (altPouleIdx !== -1) {
    const altPouleCol = altPouleIdx + 1;
    const huidigeBreedteAlt = sheet.getColumnWidth(altPouleCol);

    // Maximum 350px
    if (huidigeBreedteAlt > 350) {
      sheet.setColumnWidth(altPouleCol, 350);
    }
  }
}

// Functies vindVerplaatsteJudokas, updateAfwezigeJudokas, markerenWegingGesloten zijn nu in VerplaatsingUtils.js
// (Merk op: markerenWegingGesloten wordt gebruikt in MaakBlokken.js, mogelijk verplaatsen naar aparte file)

// Functie leesActievePoules is nu in PouleUtils.js
// Hieronder staat een LOKALE kopie die ALLEEN in dit bestand wordt gebruikt
// TODO: Refactor om gebruik te maken van PouleUtils versie
function leesActievePoules(blokBlad) {
  const data = blokBlad.getDataRange().getValues();
  if (data.length <= 1) return []; // Alleen header
  
  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");
  const aanwezigIdx = headers.indexOf("Aanwezig");
  const gewichtsklasseIdx = headers.indexOf("Gewichtsklasse");
  
  if (naamIdx === -1 || pouleNrIdx === -1 || matIdx === -1 || aanwezigIdx === -1) {
    return []; // Ontbrekende kolommen
  }
  
  // Zoek poule-informatie
  const pouleInfo = {};
  const pouleJudokas = {};
  
  // Doorloop alle rijen en verzamel poule-informatie
  for (let i = 1; i < data.length; i++) {
    const naam = data[i][naamIdx];
    const pouleNr = data[i][pouleNrIdx];
    const matNr = data[i][matIdx];
    const aanwezig = data[i][aanwezigIdx];
    
    // Skip als het geen judoka is (of mat-titel of poule-titel)
    if (!naam || !pouleNr || !matNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;
    
    // Maak sleutel voor deze poule
    const pouleKey = `${pouleNr}`;
    
    // Initialiseer poule-informatie als die nog niet bestaat
    if (!pouleInfo[pouleKey]) {
      // Zoek de pouletitel
      let pouleTitel = "";
      for (let j = 1; j < i; j++) {
        // Zoek de dichtstbijzijnde pouletitel boven deze judoka
        if (data[j][naamIdx] && typeof data[j][naamIdx] === 'string' && 
            data[j][naamIdx].includes("Poule") && data[j][naamIdx].includes(`${pouleNr}`)) {
          pouleTitel = data[j][naamIdx];
          break;
        }
      }
      
      pouleInfo[pouleKey] = {
        pouleNr: pouleNr,
        matNr: matNr,
        titel: pouleTitel || `Poule ${pouleNr}`, // Fallback titel
        aantalJudokas: 0,
        aanwezigJudokas: 0,
        aantalWedstrijden: 0
      };
      
      pouleJudokas[pouleKey] = [];
    }
    
    // Houd bij voor deze poule
    pouleInfo[pouleKey].aantalJudokas++;
    if (aanwezig === "Ja") {
      pouleInfo[pouleKey].aanwezigJudokas++;
    }
    
    // Voeg judoka toe aan de poule
    pouleJudokas[pouleKey].push({
      naam: naam,
      aanwezig: aanwezig === "Ja"
    });
  }
  
  // Bereken aantal wedstrijden per poule op basis van aanwezige judoka's
  for (const key in pouleInfo) {
    const poule = pouleInfo[key];
    const aanwezigCount = poule.aanwezigJudokas;
    
    // Bereken aantal wedstrijden
    if (aanwezigCount === 3) {
      poule.aantalWedstrijden = 6; // 3 judoka's spelen dubbel tegen elkaar
    } else if (aanwezigCount >= 2) {
      poule.aantalWedstrijden = Math.floor(aanwezigCount * (aanwezigCount - 1) / 2);
    } else {
      poule.aantalWedstrijden = 0; // 0 of 1 judoka's = geen wedstrijden
    }
  }
  
  // Converteer naar array
  return Object.values(pouleInfo);
}

// Functie hernummerJudokasPerPoule is nu in VerplaatsingUtils.js
// Functies getAantalBlokken en getAantalMatten zijn nu in ConfigUtils.js

/**
 * Verplaatst een poule naar een andere mat binnen hetzelfde blok
 */
function verplaatsPouleNaarAndereMat() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const activeSheet = ss.getActiveSheet();
  
  // Check of we in het ActueelZaalOverzicht zijn
  if (activeSheet.getName() !== "ActueelZaalOverzicht") {
    ui.alert('Verkeerd blad geselecteerd', 'Deze functie werkt alleen in het ActueelZaalOverzicht. Selecteer eerst een poule in het ActueelZaalOverzicht.', ui.ButtonSet.OK);
    return;
  }
  
  const activeCell = activeSheet.getActiveCell();
  const cellValue = activeCell.getValue();
  
  // Probeer poulenummer te extraheren uit cel
  if (!cellValue || typeof cellValue !== 'string' || 
     (!cellValue.includes("P.") && !cellValue.includes("Poule"))) {
    ui.alert('Geen poule geselecteerd', 'Selecteer een cel met een poule in het ActueelZaalOverzicht.', ui.ButtonSet.OK);
    return;
  }
  
  // Extract poulenummer uit "... P. 3 (4 w.)" of "... Poule 3 (4 w.)"
  let pouleMatch = cellValue.match(/P\. (\d+)/);
  if (!pouleMatch) {
    pouleMatch = cellValue.match(/Poule (\d+)/);
  }
  
  if (!pouleMatch) {
    ui.alert('Fout', 'Kon geen poulenummer vinden in de geselecteerde cel.', ui.ButtonSet.OK);
    return;
  }
  
  const pouleId = parseInt(pouleMatch[1]);
  
  // Bepaal huidige mat (kolom - 1)
  const huidigeMatNr = activeCell.getColumn() - 1;
  
  // Zoek het huidige blok
  let blokNr = null;
  for (let i = 1; i <= activeSheet.getLastRow(); i++) {
    const value = activeSheet.getRange(i, 1).getValue();
    if (value && typeof value === 'string' && value.startsWith("Blok ")) {
      const blokMatch = value.match(/Blok (\d+)/);
      if (blokMatch && i < activeCell.getRow()) {
        blokNr = parseInt(blokMatch[1]);
      } else if (i > activeCell.getRow()) {
        // Als we voorbij de actieve rij zijn, stop
        break;
      }
    }
  }
  
  if (!blokNr) {
    ui.alert('Fout', 'Kon het huidige bloknummer niet bepalen.', ui.ButtonSet.OK);
    return;
  }
  
  // Vraag om nieuwe mat
  const matResponse = ui.prompt(
    'Poule verplaatsen',
    `Poule ${pouleId} van mat ${huidigeMatNr} naar welke mat verplaatsen? (1-${getAantalMatten()}):`,
    ui.ButtonSet.OK_CANCEL
  );
  
  if (matResponse.getSelectedButton() !== ui.Button.OK) return;
  
  const nieuweMat = parseInt(matResponse.getResponseText());
  if (isNaN(nieuweMat) || nieuweMat < 1 || nieuweMat > getAantalMatten()) {
    ui.alert('Fout', `Voer een geldig matnummer in (1-${getAantalMatten()}).`, ui.ButtonSet.OK);
    return;
  }
  
  // Update alleen pouleindeling, niet het blokblad
  const pouleUpdated = updatePouleMatInPouleindeling(pouleId, nieuweMat);
  
  if (pouleUpdated) {
    // Werk schema bij
    genereerActueelZaalOverzicht();
    ui.alert('Poule verplaatst', `Poule ${pouleId} is verplaatst naar mat ${nieuweMat} in blok ${blokNr}.`, ui.ButtonSet.OK);
  } else {
    ui.alert('Fout', `Kon poule ${pouleId} niet vinden of verplaatsen.`, ui.ButtonSet.OK);
  }
}

// Functie vindBloknummerVoorPoule is nu in PouleUtils.js

/**
 * Maakt een nieuwe poule aan voor judoka's die verplaatst moeten worden
 */
function maakNieuwePoule() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  
  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.', ui.ButtonSet.OK);
    return;
  }
  
  // Zorg ervoor dat de configuratie is geladen
  laadConfiguratie();
  
  // Haal de leeftijdsklassen op uit de configuratie
  const leeftijdsklassen = getLeeftijdsklassen();
  
  // Zoek het hoogste bestaande poulenummer
  const pouleData = poulesSheet.getDataRange().getValues();
  const headers = pouleData[0];
  const pouleNrIdx = headers.indexOf("Poule-nr");
  
  let hoogstePouleNr = 0;
  for (let i = 1; i < pouleData.length; i++) {
    const pouleNr = pouleData[i][pouleNrIdx];
    if (pouleNr && typeof pouleNr === 'number' && pouleNr > hoogstePouleNr) {
      hoogstePouleNr = pouleNr;
    }
  }
  
  const nieuwePouleNr = hoogstePouleNr + 1;
  
  // Vraag om de leeftijdsklasse
  const leeftijdsklasseNamen = Object.keys(leeftijdsklassen);
  const leeftijdResponse = ui.prompt(
    'Nieuwe poule aanmaken',
    `Selecteer de leeftijdsklasse (nummer 1-${leeftijdsklasseNamen.length}):\n` +
    leeftijdsklasseNamen.map((lk, idx) => `${idx + 1}. ${lk}`).join('\n'),
    ui.ButtonSet.OK_CANCEL
  );
  
  if (leeftijdResponse.getSelectedButton() !== ui.Button.OK) return;
  
  const leeftijdIdx = parseInt(leeftijdResponse.getResponseText()) - 1;
  if (isNaN(leeftijdIdx) || leeftijdIdx < 0 || leeftijdIdx >= leeftijdsklasseNamen.length) {
    ui.alert('Fout', 'Ongeldige leeftijdsklasse.', ui.ButtonSet.OK);
    return;
  }
  
  const gekozenLeeftijdsklasse = leeftijdsklasseNamen[leeftijdIdx];
  
  // Haal de gewichtsklassen voor deze leeftijdsklasse op
  const gewichtsklassen = [];
  const leeftijdsklasseObj = leeftijdsklassen[gekozenLeeftijdsklasse];
  
  if (leeftijdsklasseObj && leeftijdsklasseObj.gewichtsklassen) {
    for (const gewicht of leeftijdsklasseObj.gewichtsklassen) {
      if (gewicht > 0) {
        gewichtsklassen.push(`+${gewicht} kg`);
      } else {
        gewichtsklassen.push(`${gewicht} kg`);
      }
    }
  }
  
  if (gewichtsklassen.length === 0) {
    ui.alert('Fout', 'Geen gewichtsklassen gevonden voor deze leeftijdsklasse.', ui.ButtonSet.OK);
    return;
  }
  
  const gewichtResponse = ui.prompt(
    'Nieuwe poule aanmaken',
    `Selecteer de gewichtsklasse voor ${gekozenLeeftijdsklasse} (nummer 1-${gewichtsklassen.length}):\n` +
    gewichtsklassen.map((gk, idx) => `${idx + 1}. ${gk}`).join('\n'),
    ui.ButtonSet.OK_CANCEL
  );
  
  if (gewichtResponse.getSelectedButton() !== ui.Button.OK) return;
  
  const gewichtIdx = parseInt(gewichtResponse.getResponseText()) - 1;
  if (isNaN(gewichtIdx) || gewichtIdx < 0 || gewichtIdx >= gewichtsklassen.length) {
    ui.alert('Fout', 'Ongeldige gewichtsklasse.', ui.ButtonSet.OK);
    return;
  }
  
  const gekozenGewichtsklasse = gewichtsklassen[gewichtIdx];
  
  // Vraag om het bloknummer
  const aantalBlokken = getAantalBlokken();
  const blokResponse = ui.prompt(
    'Nieuwe poule aanmaken',
    `In welk tijdsblok moet de poule worden ingepland? (1-${aantalBlokken}):`,
    ui.ButtonSet.OK_CANCEL
  );
  
  if (blokResponse.getSelectedButton() !== ui.Button.OK) return;
  
  const blokNr = parseInt(blokResponse.getResponseText());
  if (isNaN(blokNr) || blokNr < 1 || blokNr > aantalBlokken) {
    ui.alert('Fout', `Voer een geldig bloknummer in tussen 1 en ${aantalBlokken}.`, ui.ButtonSet.OK);
    return;
  }
  
  // Vraag om het matnummer
  const aantalMatten = getAantalMatten();
  const matResponse = ui.prompt(
    'Nieuwe poule aanmaken',
    `Op welke mat moet de poule worden ingepland? (1-${aantalMatten}):`,
    ui.ButtonSet.OK_CANCEL
  );
  
  if (matResponse.getSelectedButton() !== ui.Button.OK) return;
  
  const matNr = parseInt(matResponse.getResponseText());
  if (isNaN(matNr) || matNr < 1 || matNr > aantalMatten) {
    ui.alert('Fout', `Voer een geldig matnummer in tussen 1 en ${aantalMatten}.`, ui.ButtonSet.OK);
    return;
  }
  
  // Maak de pouletitel
  const pouleTitel = `${gekozenLeeftijdsklasse} ${gekozenGewichtsklasse} Poule ${nieuwePouleNr}`;
  
  // Maak een nieuwe lege poule aan in PouleIndeling
  const lastRow = poulesSheet.getLastRow();

  // Titel rij toevoegen
  poulesSheet.getRange(lastRow + 2, 1, 1, 12).merge();
  poulesSheet.getRange(lastRow + 2, 1).setValue(pouleTitel)
    .setBackground("#B6D7A8")
    .setHorizontalAlignment("center")
    .setFontWeight("bold");

  // Dummy judoka rij direct onder de titel toevoegen
  const blokIdx = headers.indexOf("Blok");
  const matIdx = headers.indexOf("Mat");
  const pouleIdx = headers.indexOf("Poule-nr");
  const pouleTitelIdx = headers.indexOf("Pouletitel");

  if (blokIdx !== -1 && matIdx !== -1 && pouleIdx !== -1 && pouleTitelIdx !== -1) {
    const dummyRow = Array(12).fill("");
    dummyRow[blokIdx] = blokNr;
    dummyRow[matIdx] = matNr;
    dummyRow[pouleIdx] = nieuwePouleNr;
    dummyRow[pouleTitelIdx] = pouleTitel;
    
    poulesSheet.getRange(lastRow + 3, 1, 1, 12).setValues([dummyRow]);
    poulesSheet.getRange(lastRow + 3, 7).setValue("Dummy rij - verwijderen na toevoegen judoka's");
  }
  
  // Maak entry ook in bijbehorend blokblad
  const blokBlad = ss.getSheetByName(`Blok ${blokNr}`);
  if (blokBlad) {
    const blokLastRow = blokBlad.getLastRow();
    
    // Vind de plek om de poule in te voegen (na de laatste poule op dezelfde mat)
    let invoegPunt = blokLastRow + 1;
    let matHeader = null;
    
    const blokData = blokBlad.getDataRange().getValues();
    for (let i = 0; i < blokData.length; i++) {
      // Zoek naar mat header die overeenkomt met de gekozen mat
      if (blokData[i][0] === `MAT ${matNr}`) {
        matHeader = i + 1;
      }
      
      // Als we eenmaal de juiste mat header hebben, zoek de positie na de laatste poule
      if (matHeader !== null) {
        // Als we een nieuwe mat header tegenkomen, stoppen we
        if (i > matHeader && blokData[i][0] && blokData[i][0].toString().startsWith("MAT ")) {
          invoegPunt = i + 1;
          break;
        }
        
        // Anders updaten we het invoegpunt na elke rij die bij deze mat hoort
        invoegPunt = i + 2;
      }
    }
    
    // Poule titel invoegen in het blokblad
    blokBlad.insertRowBefore(invoegPunt);
    blokBlad.getRange(invoegPunt, 1, 1, blokBlad.getLastColumn()).merge();
    blokBlad.getRange(invoegPunt, 1).setValue(pouleTitel)
      .setBackground("#B6D7A8")
      .setHorizontalAlignment("center")
      .setFontWeight("bold");

    // Dummy judoka direct onder de titel toevoegen
    const blokHeaders = blokBlad.getRange(1, 1, 1, blokBlad.getLastColumn()).getValues()[0];
    const aanwezigIdx = blokHeaders.indexOf("Aanwezig");
    const blokNrIdx = blokHeaders.indexOf("Nr");
    const blokNaamIdx = blokHeaders.indexOf("Naam");
    const blokPouleNrIdx = blokHeaders.indexOf("Poule-nr");
    const blokMatIdx = blokHeaders.indexOf("Mat");

    if (aanwezigIdx !== -1 && blokPouleNrIdx !== -1 && blokMatIdx !== -1) {
      blokBlad.insertRowAfter(invoegPunt);
      
      const dummyRow = Array(blokBlad.getLastColumn()).fill("");
      dummyRow[aanwezigIdx] = "Nee";
      dummyRow[blokNrIdx] = 1;
      dummyRow[blokNaamIdx] = "Dummy - verwijderen na toevoegen judoka's";
      dummyRow[blokPouleNrIdx] = nieuwePouleNr;
      dummyRow[blokMatIdx] = matNr;
      
      blokBlad.getRange(invoegPunt + 1, 1, 1, blokBlad.getLastColumn()).setValues([dummyRow]);
      
      // Datavalidatie toepassen
      const aanwezigRule = SpreadsheetApp.newDataValidation()
        .requireValueInList(['Ja', 'Nee'], true)
        .build();
      blokBlad.getRange(invoegPunt + 1, aanwezigIdx + 1).setDataValidation(aanwezigRule);
      blokBlad.getRange(invoegPunt + 1, aanwezigIdx + 1).setBackground("#F4CCCC");
    }
  }
  
  // Update ZaalOverzicht
  werkZaalOverzichtBij();
  
  // Bevestiging aan gebruiker
  ui.alert(
    'Nieuwe poule aangemaakt',
    `Er is een nieuwe lege poule aangemaakt:\n\n` +
    `Poule: ${nieuwePouleNr}\n` +
    `Titel: ${pouleTitel}\n` +
    `Blok: ${blokNr}\n` +
    `Mat: ${matNr}\n\n` +
    `Gebruik de functie "Verplaats judoka naar andere poule" om judoka's naar deze poule te verplaatsen.`,
    ui.ButtonSet.OK
  );
}

// ============================================================================
// WEGING SLUITEN FUNCTIES
// ============================================================================

/**
 * Update afwezige judoka's in de poule map op basis van blokblad aanwezigheid
 * @param {Object} pouleMap - Map met poule informatie
 * @param {number} blokNr - Het bloknummer
 */
function updateAfwezigeJudokas(pouleMap, blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const blokBlad = ss.getSheetByName(`Blok ${blokNr}`);

  if (!blokBlad) return;

  const data = blokBlad.getDataRange().getValues();
  const headers = data[0];

  const naamIdx = headers.indexOf("Naam");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const aanwezigIdx = headers.indexOf("Aanwezig");

  if (naamIdx === -1 || pouleNrIdx === -1 || aanwezigIdx === -1) return;

  // Bijhouden welke poules zijn aangepast
  const aangepastePoulesSet = new Set();

  // Zoek afwezige judoka's
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const naam = row[naamIdx];
    const pouleNr = row[pouleNrIdx];
    const aanwezig = row[aanwezigIdx];

    if (!naam || !pouleNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    // Als judoka niet aanwezig is
    if (aanwezig === "Nee") {
      if (pouleMap[pouleNr]) {
        // Vind de judoka in de poule en markeer als niet aanwezig
        const index = pouleMap[pouleNr].judokas.findIndex(j => j.naam === naam && !j.verplaatst);
        if (index !== -1) {
          pouleMap[pouleNr].judokas[index].aanwezig = false;
          aangepastePoulesSet.add(pouleNr);
        }
      }
    }
  }

  // Herbereken aantal wedstrijden voor aangepaste poules
  for (const pouleNr of aangepastePoulesSet) {
    const poule = pouleMap[pouleNr];
    // Filter zowel verplaatste als afwezige judoka's
    const actieveJudokas = poule.judokas.filter(j => !j.verplaatst && j.aanwezig);
    poule.aantalWedstrijden = berekenAantalWedstrijdenVoorPoule(actieveJudokas.length);
    poule.actieveJudokas = actieveJudokas.length;
  }
}

/**
 * Synchroniseert aanwezigheid, gewichtsmutaties en opmerkingen vanuit weeglijst naar een specifiek blokblad
 * @param {number} blokNr - Het bloknummer
 */
function syncAanwezigheidNaarBlokblad(blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const weeglijstSheet = ss.getSheetByName('Weeglijst');
  const blokSheet = ss.getSheetByName(`Blok ${blokNr}`);

  if (!weeglijstSheet || !blokSheet) return;

  // Lees weeglijst data
  const weegData = weeglijstSheet.getDataRange().getValues();
  const weegHeaders = weegData[0];
  const weegNaamIdx = weegHeaders.indexOf("Naam");
  const weegAanwezigIdx = weegHeaders.indexOf("Aanwezig");
  const weegBlokIdx = weegHeaders.indexOf("Blok");
  const weegMutatieIdx = weegHeaders.indexOf("Gew.mutatie");
  const weegOpmerkingenIdx = weegHeaders.indexOf("Opmerkingen");

  if (weegNaamIdx === -1 || weegAanwezigIdx === -1 || weegBlokIdx === -1) return;

  // Maak een map van naam naar gegevens voor judoka's in dit blok
  const judokaMap = {};
  for (let i = 1; i < weegData.length; i++) {
    const naam = weegData[i][weegNaamIdx];
    const aanwezig = weegData[i][weegAanwezigIdx];
    const blok = weegData[i][weegBlokIdx];
    const mutatie = weegMutatieIdx !== -1 ? weegData[i][weegMutatieIdx] : "";
    const opmerkingen = weegOpmerkingenIdx !== -1 ? weegData[i][weegOpmerkingenIdx] : "";

    if (naam && blok === blokNr) {
      judokaMap[naam] = {
        aanwezig: aanwezig,
        gewichtsmutatie: mutatie,
        opmerkingen: opmerkingen
      };
    }
  }

  // Lees blokblad data
  const blokData = blokSheet.getDataRange().getValues();
  const blokHeaders = blokData[0];
  const blokNaamIdx = blokHeaders.indexOf("Naam");
  const blokAanwezigIdx = blokHeaders.indexOf("Aanwezig");
  const blokMutatieIdx = blokHeaders.indexOf("Gew.mutatie");
  const blokOpmerkingenIdx = blokHeaders.indexOf("Opmerkingen");

  if (blokNaamIdx === -1 || blokAanwezigIdx === -1) return;

  // Update gegevens in blokblad
  for (let i = 1; i < blokData.length; i++) {
    const naam = blokData[i][blokNaamIdx];

    if (!naam || typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    if (judokaMap.hasOwnProperty(naam)) {
      const info = judokaMap[naam];

      // Update aanwezigheid
      const huidigeAanwezigheid = blokData[i][blokAanwezigIdx];
      if (huidigeAanwezigheid !== info.aanwezig) {
        blokSheet.getRange(i + 1, blokAanwezigIdx + 1).setValue(info.aanwezig);

        // Achtergrondkleur voor aanwezigheid
        if (info.aanwezig === "Ja") {
          blokSheet.getRange(i + 1, blokAanwezigIdx + 1).setBackground("#D9EAD3");
        } else {
          blokSheet.getRange(i + 1, blokAanwezigIdx + 1).setBackground(null);
        }
      }

      // Update gewichtsmutatie
      if (blokMutatieIdx !== -1) {
        const huidigeGewichtsmutatie = blokData[i][blokMutatieIdx];
        if (huidigeGewichtsmutatie !== info.gewichtsmutatie) {
          blokSheet.getRange(i + 1, blokMutatieIdx + 1).setValue(info.gewichtsmutatie);
        }
      }

      // Update opmerkingen
      if (blokOpmerkingenIdx !== -1 && info.opmerkingen) {
        const huidigeOpmerkingen = blokData[i][blokOpmerkingenIdx];
        if (huidigeOpmerkingen !== info.opmerkingen) {
          blokSheet.getRange(i + 1, blokOpmerkingenIdx + 1).setValue(info.opmerkingen);
        }
      }
    }
  }
}

/**
 * Markeer in het schema dat de weging voor een blok gesloten is
 * @param {Sheet} schemaSheet - Het ZaalOverzicht sheet
 * @param {number} blokNr - Het bloknummer
 */
function markerenWegingGesloten(schemaSheet, blokNr) {
  // Zoek de rij met de bloktitel
  const lastRow = schemaSheet.getLastRow();

  for (let i = 1; i <= lastRow; i++) {
    const value = schemaSheet.getRange(i, 1).getValue();

    // Check of dit de juiste blok-rij is (met of zonder "(Weging gesloten)")
    if (value === `Blok ${blokNr}` || value === `Blok ${blokNr} (Weging gesloten)`) {
      // Alleen markeren als het nog niet gesloten is
      if (!value.includes("(Weging gesloten)")) {
        schemaSheet.getRange(i, 1).setValue(`Blok ${blokNr} (Weging gesloten)`);
      }

      // Pas achtergrondkleur aan (altijd, ook als het al gesloten was)
      schemaSheet.getRange(i, 1).setBackground("#D9EAD3"); // Groen
      break;
    }
  }
}

/**
 * Controleert gewichtsmutaties en vult Alt. poule kolom voor een specifiek blok
 * @param {number} blokNr - Het bloknummer
 */
function checkEnVulAltPouleVoorBlok(blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const blokSheet = ss.getSheetByName(`Blok ${blokNr}`);
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  const weeglijstSheet = ss.getSheetByName('Weeglijst');

  if (!blokSheet || !poulesSheet || !weeglijstSheet) return;

  const tolerantieMarge = getGewichtsToleratie();

  // Lees weeglijst voor leeftijdsklasse informatie
  const weeglijstData = weeglijstSheet.getDataRange().getValues();
  const weeglijstHeaders = weeglijstData[0];
  const naamWeegIdx = weeglijstHeaders.indexOf("Naam");
  const leeftijdsklasseWeegIdx = weeglijstHeaders.indexOf("Lft-klasse");

  const judokaLeeftijdsklasseMap = {};
  for (let i = 1; i < weeglijstData.length; i++) {
    const naam = weeglijstData[i][naamWeegIdx];
    const leeftijdsklasse = leeftijdsklasseWeegIdx !== -1 ? weeglijstData[i][leeftijdsklasseWeegIdx] : "";
    if (naam) {
      judokaLeeftijdsklasseMap[naam] = leeftijdsklasse;
    }
  }

  // Lees PouleIndeling voor beschikbare poules
  const pouleData = poulesSheet.getDataRange().getValues();
  const pouleHeaders = pouleData[0];
  const pouleTitelIdx = pouleHeaders.indexOf("Pouletitel");
  const pouleNrIdx = pouleHeaders.indexOf("Poule-nr");
  const blokIdx = pouleHeaders.indexOf("Blok");
  const matIdx = pouleHeaders.indexOf("Mat");
  const naamPouleIdx = pouleHeaders.indexOf("Naam");

  // Verzamel beschikbare poules
  const beschikbarePoules = {};
  for (let i = 1; i < pouleData.length; i++) {
    const row = pouleData[i];
    const pouleTitel = row[pouleTitelIdx];
    const pouleNr = row[pouleNrIdx];
    const blokNummer = row[blokIdx];
    const matNr = row[matIdx];

    if (!pouleTitel || !pouleNr) continue;

    const match = pouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg|^\d+\s+kg)/i);
    if (!match) continue;

    const leeftijdsklasse = match[1].trim();
    const gewichtsklasseStr = match[2].trim();
    const sleutel = `${leeftijdsklasse}-${gewichtsklasseStr}`;

    if (!beschikbarePoules[sleutel]) {
      beschikbarePoules[sleutel] = [];
    }

    const bestaandePoulesNrs = beschikbarePoules[sleutel].map(p => p.pouleNr);
    if (bestaandePoulesNrs.includes(pouleNr)) continue;

    let aantalJudokas = 0;
    for (let j = 1; j < pouleData.length; j++) {
      if (pouleData[j][pouleNrIdx] === pouleNr) {
        if (pouleData[j][naamPouleIdx] && typeof pouleData[j][naamPouleIdx] !== 'string' ||
            (typeof pouleData[j][naamPouleIdx] === 'string' &&
             !pouleData[j][naamPouleIdx].includes("TOTAAL") &&
             !pouleData[j][naamPouleIdx].includes("Poule"))) {
          aantalJudokas++;
        }
      }
    }

    const gewichtLimiet = extractGewichtLimiet(gewichtsklasseStr);

    beschikbarePoules[sleutel].push({
      pouleNr: pouleNr,
      pouleTitel: pouleTitel,
      blokNr: blokNummer,
      matNr: matNr,
      aantalJudokas: aantalJudokas,
      gewichtLimiet: gewichtLimiet,
      gewichtsklasseStr: gewichtsklasseStr
    });
  }

  // Lees blokblad
  const lastBlokRow = blokSheet.getLastRow();
  if (lastBlokRow <= 1) return;

  const blokHeaders = blokSheet.getRange(1, 1, 1, 20).getValues()[0];
  const blokNaamIdx = blokHeaders.indexOf("Naam");
  const blokMutatieIdx = blokHeaders.indexOf("Gew.mutatie");
  const blokPouleNrIdx = blokHeaders.indexOf("Poule-nr");
  const blokAltPouleIdx = blokHeaders.indexOf("Alt. poule");

  if (blokNaamIdx === -1 || blokMutatieIdx === -1) return;

  const blokPouleColIdx = blokPouleNrIdx !== -1 ? blokPouleNrIdx : 9;
  const blokData = blokSheet.getRange(1, 1, lastBlokRow, Math.max(blokHeaders.length, 15)).getValues();

  // Verzamel poules
  const pouleMap = {};
  for (let i = 1; i < blokData.length; i++) {
    const naam = blokData[i][blokNaamIdx];
    const pouleNr = blokData[i][blokPouleColIdx];

    if (!naam || naam.startsWith("MAT ") || naam.includes("Poule")) continue;
    if (!pouleNr) continue;

    if (!pouleMap[pouleNr]) {
      let pouleTitel = "";
      let gewichtLimiet = null;

      for (let j = 1; j < pouleData.length; j++) {
        if (pouleData[j][pouleNrIdx] === pouleNr) {
          pouleTitel = pouleData[j][pouleTitelIdx] || "";
          break;
        }
      }

      if (pouleTitel) {
        const match = pouleTitel.match(/([+-]?\d+)\s*kg/);
        if (match) {
          gewichtLimiet = extractGewichtLimiet(match[0]);
        }
      }

      pouleMap[pouleNr] = {
        judokas: [],
        pouleTitel: pouleTitel,
        gewichtLimiet: gewichtLimiet
      };
    }

    pouleMap[pouleNr].judokas.push({
      row: i + 1,
      naam: naam
    });
  }

  // Bepaal ondergrens voor elke poule
  const poulesPerLeeftijdsklasse = {};
  for (const [pouleNr, poule] of Object.entries(pouleMap)) {
    if (poule.pouleTitel) {
      const match = poule.pouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg)/i);
      if (match) {
        const leeftijdsklasse = match[1].trim();
        if (!poulesPerLeeftijdsklasse[leeftijdsklasse]) {
          poulesPerLeeftijdsklasse[leeftijdsklasse] = [];
        }
        poulesPerLeeftijdsklasse[leeftijdsklasse].push({
          pouleNr: pouleNr,
          gewichtLimiet: poule.gewichtLimiet
        });
      }
    }
  }

  for (const leeftijdsklasse in poulesPerLeeftijdsklasse) {
    const poules = poulesPerLeeftijdsklasse[leeftijdsklasse];
    const minKlassen = poules.filter(p => p.gewichtLimiet && p.gewichtLimiet.type === "min")
                             .sort((a, b) => a.gewichtLimiet.waarde - b.gewichtLimiet.waarde);

    for (let i = 0; i < minKlassen.length; i++) {
      if (i > 0) {
        pouleMap[minKlassen[i].pouleNr].gewichtLimietOnder = minKlassen[i - 1].gewichtLimiet.waarde;
      }
    }
  }

  // Controleer elke judoka
  for (const [pouleNr, poule] of Object.entries(pouleMap)) {
    for (const judoka of poule.judokas) {
      const gewichtsmutatie = blokData[judoka.row - 1][blokMutatieIdx];

      if (gewichtsmutatie && poule.gewichtLimiet !== null) {
        const gewichtMatch = gewichtsmutatie.toString().match(/([0-9]*[.])?[0-9]+/);

        if (gewichtMatch) {
          const gemeten_gewicht = parseFloat(gewichtMatch[0]);
          const leeftijdsklasse = judokaLeeftijdsklasseMap[judoka.naam] || "";

          if (isGewichtBuitenMarge(gemeten_gewicht, poule.gewichtLimiet, tolerantieMarge, poule.gewichtLimietOnder)) {
            // Zoek alternatieve poules (alleen als pouleTitel bekend is)
            if (poule.pouleTitel) {
              const mogelijkePoules = vindMogelijkePoules(gemeten_gewicht, leeftijdsklasse, poule.pouleTitel, beschikbarePoules);

              // Vul Alt. poule kolom
              if (blokAltPouleIdx !== -1 && mogelijkePoules) {
                blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setValue(mogelijkePoules);
                blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setBackground("#FFF3CD");
              }
            }
          } else {
            // Gewicht binnen marge - maak Alt. poule kolom leeg
            if (blokAltPouleIdx !== -1) {
              blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setValue("");
              blokSheet.getRange(judoka.row, blokAltPouleIdx + 1).setBackground(null);
            }
          }
        }
      }
    }
  }
}

/**
 * Sluit de weging voor een specifiek blok
 * @param {number} blokNr - Het bloknummer (1-6)
 */
function sluitWegingBlok(blokNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  const blokSheet = ss.getSheetByName(`Blok ${blokNr}`);
  if (!blokSheet) {
    ui.alert('Fout', `Blokblad "Blok ${blokNr}" bestaat niet.`, ui.ButtonSet.OK);
    return;
  }

  // Vraag bevestiging
  const response = ui.alert(
    `Sluit weging Blok ${blokNr}`,
    `Dit zal alle niet-aanwezige judoka's markeren als afwezig en poules reorganiseren indien nodig. Doorgaan?`,
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    return;
  }

  // Controleer of er een ZaalOverzicht bestaat
  const schemaSheet = ss.getSheetByName('ZaalOverzicht');
  if (!schemaSheet) {
    ui.alert(
      'ZaalOverzicht niet gevonden',
      'Genereer eerst het ZaalOverzicht voordat je de weging sluit.',
      ui.ButtonSet.OK
    );
    return;
  }

  // Haal de huidige poule-informatie op
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    ui.alert('Fout', 'PouleIndeling tabblad niet gevonden.', ui.ButtonSet.OK);
    return;
  }

  // EERST: Synchroniseer aanwezigheid vanuit weeglijst naar blokblad
  syncAanwezigheidNaarBlokblad(blokNr);

  // Controleer gewichtsmutaties en vul Alt. poule kolom
  checkEnVulAltPouleVoorBlok(blokNr);

  // Lees poule-informatie, verzamel verplaatste judoka's, en update
  const pouleMap = leesPouleInformatie(poulesSheet);
  const verplaatsteJudokas = vindVerplaatsteJudokas();
  updatePouleMapMetVerplaatsingen(pouleMap, verplaatsteJudokas);

  // Update afwezige judoka's voor dit specifieke blok
  updateAfwezigeJudokas(pouleMap, blokNr);

  // Update het ZaalOverzicht
  updateZaalOverzichtMetActueleInfo(schemaSheet, pouleMap);

  // Markeer het blok als gesloten
  markerenWegingGesloten(schemaSheet, blokNr);

  // Update het gewichtsklassenoverzicht
  toevoegenGewichtsklassenOverzicht(schemaSheet, pouleMap);

  ui.alert(
    'Weging gesloten',
    `De weging voor Blok ${blokNr} is gesloten. Het ZaalOverzicht is bijgewerkt met de aanwezigheidsgegevens.\n\n` +
    `⚠️ BELANGRIJK: Herlaad de pagina (F5 of Ctrl+F5) om het menu te vernieuwen.\n\n` +
    `Na herladen kun je in het menu "Toernooidag" de wedstrijdschema's printen voor Blok ${blokNr}.`,
    ui.ButtonSet.OK
  );
}

/**
 * Sluit de weging voor Blok 1
 */
function sluitWegingBlok1() {
  sluitWegingBlok(1);
}

/**
 * Sluit de weging voor Blok 2
 */
function sluitWegingBlok2() {
  sluitWegingBlok(2);
}

/**
 * Sluit de weging voor Blok 3
 */
function sluitWegingBlok3() {
  sluitWegingBlok(3);
}

/**
 * Sluit de weging voor Blok 4
 */
function sluitWegingBlok4() {
  sluitWegingBlok(4);
}

/**
 * Sluit de weging voor Blok 5
 */
function sluitWegingBlok5() {
  sluitWegingBlok(5);
}

/**
 * Sluit de weging voor Blok 6
 */
function sluitWegingBlok6() {
  sluitWegingBlok(6);
}

/**
 * Algemene functie voor het sluiten van de weging voor een specifiek blok
 * @param {number} blokNr - Het bloknummer
 */
function sluitWegingVoorBlok(blokNr) {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer eerst of het blok bestaat
  const aantalBlokken = getAantalBlokken();
  if (blokNr > aantalBlokken) {
    ui.alert(
      'Ongeldig bloknummer',
      `Blok ${blokNr} bestaat niet. Er zijn slechts ${aantalBlokken} blokken in dit toernooi.`,
      ui.ButtonSet.OK
    );
    return;
  }

  // Controleer of het bijbehorende blokblad bestaat
  const blokBlad = ss.getSheetByName(`Blok ${blokNr}`);
  if (!blokBlad) {
    ui.alert(
      'Blokblad ontbreekt',
      `Het blad "Blok ${blokNr}" is niet gevonden. Genereer eerst de blokbladen.`,
      ui.ButtonSet.OK
    );
    return;
  }

  // Vraag om bevestiging
  const response = ui.alert(
    `Weging sluiten voor Blok ${blokNr}`,
    `Wil je de weging voor Blok ${blokNr} sluiten? Dit zal het ActueelZaalOverzicht bijwerken met de aanwezigheid van judoka's.`,
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    return;
  }

  // Controleer of er een ActueelZaalOverzicht bestaat
  const schemaSheet = ss.getSheetByName('ActueelZaalOverzicht');
  if (!schemaSheet) {
    ui.alert(
      'ActueelZaalOverzicht niet gevonden',
      'Genereer eerst het ActueelZaalOverzicht voordat je de weging sluit.',
      ui.ButtonSet.OK
    );
    return;
  }

  // Haal de huidige poule-informatie op
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (!poulesSheet) {
    ui.alert('Fout', 'PouleIndeling tabblad niet gevonden.', ui.ButtonSet.OK);
    return;
  }

  // Lees poule-informatie, verzamel verplaatste judoka's, en update
  const pouleMap = leesPouleInformatie(poulesSheet);
  const verplaatsteJudokas = vindVerplaatsteJudokas();
  updatePouleMapMetVerplaatsingen(pouleMap, verplaatsteJudokas);

  // Update afwezige judoka's voor dit specifieke blok
  updateAfwezigeJudokas(pouleMap, blokNr);

  // Update het ActueelZaalOverzicht
  updateZaalOverzichtMetActueleInfo(schemaSheet, pouleMap);

  // Markeer het blok als gesloten
  markerenWegingGesloten(schemaSheet, blokNr);

  // Update het gewichtsklassenoverzicht
  toevoegenGewichtsklassenOverzicht(schemaSheet, pouleMap);

  // Ververs het menu om de knop te veranderen
  createJudoMenu();

  ui.alert(
    'Weging gesloten',
    `De weging voor Blok ${blokNr} is gesloten. Het ActueelZaalOverzicht is bijgewerkt met de aanwezigheidsgegevens.\n\n` +
    `In het menu "Toernooidag" kun je nu de wedstrijdschema's printen voor Blok ${blokNr}.`,
    ui.ButtonSet.OK
  );
}

/**
 * Bepaalt de nieuwe gewichtsklasse op basis van het gemeten gewicht en de leeftijdsklasse
 * Haalt de gewichtsklassen dynamisch op uit het configuratieblad
 * @param {number|string} gewicht - Het gemeten gewicht
 * @param {string} leeftijdsklasse - De leeftijdsklasse van de judoka
 * @return {string} De nieuwe gewichtsklasse in het formaat "-XX kg" of "+XX kg"
 */
function BepaalNieuweGewichtsklasse(gewicht, leeftijdsklasse) {
  const gewichtGetal = parseFloat(gewicht);

  // Haal de leeftijdsklassen en gewichtsklassen op uit de configuratie
  const leeftijdsklassen = getLeeftijdsklassen();

  // Zoek de juiste leeftijdsklasse configuratie
  let leeftijdsklasseObj = null;
  for (const [naam, klasse] of Object.entries(leeftijdsklassen)) {
    if (leeftijdsklasse.includes(naam)) {
      leeftijdsklasseObj = klasse;
      break;
    }
  }

  // Als de leeftijdsklasse niet gevonden is, return null
  if (!leeftijdsklasseObj) return null;

  // Pak de gewichtsklassen voor deze leeftijdsklasse
  const klasseGewichten = leeftijdsklasseObj.gewichtsklassen || [];

  // Geen gewichtsklassen? Return null
  if (klasseGewichten.length === 0) return null;

  // Vind de passende gewichtsklasse
  for (const grens of klasseGewichten) {
    if (grens > 0) { // Dit is een plus-categorie (alles boven de vorige grens)
      const vorigeGrens = klasseGewichten[klasseGewichten.indexOf(grens) - 1];
      if (gewichtGetal > Math.abs(vorigeGrens)) {
        return `+${grens} kg`;
      }
    } else { // Dit is een min-categorie
      if (gewichtGetal <= Math.abs(grens)) {
        return `${grens} kg`;
      }
    }
  }

  // Als geen gewichtsklasse gevonden is, neem de hoogste gewichtsklasse
  const hoogsteGrens = klasseGewichten[klasseGewichten.length - 1];
  if (hoogsteGrens > 0) {
    return `+${hoogsteGrens} kg`;
  } else {
    return `${hoogsteGrens} kg`;
  }
}