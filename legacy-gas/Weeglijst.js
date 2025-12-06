// Weeglijst.js - Functies voor weeglijst generatie en beheer
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - Blokbladen.js (maakBlokbladen)
 * - ZaalOverzicht.js (genereerZaalOverzicht)
 */

/**
 * Genereert een weeglijst, blokbladen en zaaloverzicht
 * Coördinerende functie die alle componenten aanroept
 */
function genereerWeeglijstEnBlokbladen() {
  const ui = SpreadsheetApp.getUi();

  try {
    // Controleer eerst of de poule-indeling bestaat
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const poulesSheet = ss.getSheetByName('PouleIndeling');

    if (!poulesSheet) {
      ui.alert(
        'Fout',
        'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.',
        ui.ButtonSet.OK
      );
      return;
    }

    // Controleer of er blok- en matnummers zijn toegewezen
    const lastRow = poulesSheet.getLastRow();
    const headers = poulesSheet.getRange(1, 1, 1, 13).getValues()[0];
    const blokIndex = headers.indexOf("Blok");
    const matIndex = headers.indexOf("Mat");

    if (blokIndex === -1 || matIndex === -1) {
      ui.alert(
        'Fout',
        'De kolommen "Blok" en/of "Mat" zijn niet gevonden in de poule-indeling. Genereer eerst een blok/mat indeling.',
        ui.ButtonSet.OK
      );
      return;
    }

    // Controleer of er blok- en matnummers zijn toegewezen
    const pouleData = poulesSheet.getRange(2, blokIndex + 1, lastRow - 1, 2).getValues();
    let heeftBlokMatNummers = false;

    for (let i = 0; i < pouleData.length; i++) {
      if (pouleData[i][0] && pouleData[i][1]) {
        heeftBlokMatNummers = true;
        break;
      }
    }

    if (!heeftBlokMatNummers) {
      ui.alert(
        'Waarschuwing',
        'Er zijn geen blok- en matnummers toegewezen in de poule-indeling. De blokbladen zullen mogelijk leeg zijn.',
        ui.ButtonSet.OK_CANCEL
      );

      if (ui.Button.CANCEL) {
        return;
      }
    }

    // Maak eerst het ZaalOverzicht (functie in ZaalOverzicht.js)
    genereerZaalOverzicht();

    // Maak dan de weeglijst
    maakWeeglijst();

    // Maak daarna de blokbladen (functie in Blokbladen.js)
    maakBlokbladen();

    ui.alert(
      'Genereren voltooid',
      'Het ZaalOverzicht, de weeglijst en blokbladen zijn succesvol gegenereerd.',
      ui.ButtonSet.OK
    );
  } catch (e) {
    Logger.log("Fout: " + e.toString());
    ui.alert(
      'Fout bij genereren',
      'Er is een fout opgetreden: ' + e.toString(),
      ui.ButtonSet.OK
    );
  }
}

/**
 * Maakt een weeglijst op basis van de deelnemerslijst
 * Geoptimaliseerd voor gebruik op zowel desktop als tablets
 * Met verbeterde gewichtsmutatie-validatie en automatische aanwezigheidsmarkering
 */
function maakWeeglijst() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const deelnemersSheet = ss.getSheetByName('Deelnemerslijst');
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  // Als er al een weeglijst bestaat, verwijder deze
  if (ss.getSheetByName('Weeglijst')) {
    ss.deleteSheet(ss.getSheetByName('Weeglijst'));
  }

  // Bepaal positie: na het ZaalOverzicht
  let positie = 0;
  const sheets = ss.getSheets();
  for (let i = 0; i < sheets.length; i++) {
    if (sheets[i].getName() === 'ZaalOverzicht') {
      positie = i + 1;
      break;
    }
  }

  // Maak nieuw tabblad voor de weeglijst
  const weeglijstSheet = ss.insertSheet('Weeglijst', positie);

  // Lees alle data uit deelnemerslijst (inclusief Leeftijdsklasse kolom)
  const lastRow = deelnemersSheet.getLastRow();
  const data = deelnemersSheet.getRange(1, 1, lastRow, 10).getValues();

  // Zoek header rij en kolom indexen
  let headerRow = 0;
  for (let i = 0; i < Math.min(5, data.length); i++) {
    if (data[i].some(cell => cell && typeof cell === 'string' && cell.toLowerCase().includes('naam'))) {
      headerRow = i;
      break;
    }
  }

  // Bepaal kolom indexen in deelnemerslijst
  const deelnemersHeaders = data[headerRow];
  const deelnemersLeeftijdsklasseIdx = deelnemersHeaders.indexOf("Leeftijdsklasse");

  // Haal blok, mat, poule en gewichtsklasse informatie op uit poule-indeling
  const bloknrMap = {};
  const matnrMap = {};
  const pouleNrMap = {};
  const gewichtsklasseMap = {};
  const leeftijdsklasseMap = {};

  if (poulesSheet) {
    const pouleLastRow = poulesSheet.getLastRow();
    const pouleData = poulesSheet.getRange(1, 1, pouleLastRow, 13).getValues();
    const pouleHeaders = pouleData[0];
    const pouleNaamIdx = pouleHeaders.indexOf("Naam");
    const pouleBlokIdx = pouleHeaders.indexOf("Blok");
    const pouleMatIdx = pouleHeaders.indexOf("Mat");
    const pouleNrIdx = pouleHeaders.indexOf("Poule-nr");
    const pouleGewichtsklasseIdx = pouleHeaders.indexOf("Gewichtsklasse");
    const pouleLeeftijdsklasseIdx = pouleHeaders.indexOf("Leeftijdsklasse");

    if (pouleNaamIdx !== -1 && pouleBlokIdx !== -1 && pouleMatIdx !== -1 && pouleNrIdx !== -1) {
      for (let i = 1; i < pouleData.length; i++) {
        const row = pouleData[i];
        const naam = row[pouleNaamIdx];
        const blokNr = row[pouleBlokIdx];
        const matNr = row[pouleMatIdx];
        const pouleNr = row[pouleNrIdx];
        const gewichtsklasse = pouleGewichtsklasseIdx !== -1 ? row[pouleGewichtsklasseIdx] : "";
        const leeftijdsklasse = pouleLeeftijdsklasseIdx !== -1 ? row[pouleLeeftijdsklasseIdx] : "";

        if (naam && blokNr && matNr && pouleNr) {
          bloknrMap[naam] = blokNr;
          matnrMap[naam] = matNr;
          pouleNrMap[naam] = pouleNr;
          gewichtsklasseMap[naam] = gewichtsklasse;
          leeftijdsklasseMap[naam] = leeftijdsklasse;
        }
      }
    }
  }

  // Kolomvolgorde: Naam, Club, Lft-klasse, Blok, Mat, Poule, Gew. klasse, Aanwezig, Gew.mutatie, Opmerkingen
  const headers = ["Naam", "Club", "Lft-klasse", "Blok", "Mat", "Poule", "Gew. klasse", "Aanwezig", "Gew.mutatie", "Opmerkingen"];

  weeglijstSheet.getRange(1, 1, 1, headers.length).setValues([headers])
    .setFontWeight("bold")
    .setBackground("#D9D9D9");

  // Zet de eerste rij vast
  weeglijstSheet.setFrozenRows(1);

  // Verzamel deelnemers (sla header over)
  const deelnemers = [];
  for (let i = headerRow + 1; i < data.length; i++) {
    // Skip lege rijen
    if (!data[i][0]) continue;

    const naam = data[i][0];
    const club = data[i][2];
    const blokNr = bloknrMap[naam] || "";
    const matNr = matnrMap[naam] || "";
    const pouleNr = pouleNrMap[naam] || "";
    const gewichtsklasse = gewichtsklasseMap[naam] || data[i][3] || "";
    // Haal leeftijdsklasse uit deelnemerslijst (of PouleIndeling als fallback)
    const leeftijdsklasse = (deelnemersLeeftijdsklasseIdx !== -1 ? data[i][deelnemersLeeftijdsklasseIdx] : "") || leeftijdsklasseMap[naam] || "";

    // Alleen opnemen als deze judoka aan een poule, blok of mat is toegewezen
    if (blokNr || matNr || pouleNr) {
      const deelnemer = [
        naam,               // Naam
        club,               // Club
        leeftijdsklasse,    // Leeftijdsklasse
        blokNr,             // Blok
        matNr,              // Mat
        pouleNr,            // Poule
        gewichtsklasse,     // Gewichtsklasse
        "Nee",              // Aanwezig - standaard op Nee
        "",                 // Gewichtsmutatie
        ""                  // Opmerkingen
      ];

      deelnemers.push(deelnemer);
    }
  }

  // Schrijf deelnemers naar weeglijst
  if (deelnemers.length > 0) {
    weeglijstSheet.getRange(2, 1, deelnemers.length, headers.length).setValues(deelnemers);

    // Sorteer eerst op Blok (kolom 4), dan op Naam (kolom 1)
    weeglijstSheet.getRange(2, 1, deelnemers.length, headers.length).sort([
      {column: 4, ascending: true},  // Blok
      {column: 1, ascending: true}   // Naam
    ]);
  }

  // Voeg datavalidatie toe aan de Aanwezig kolom
  const aanwezigRange = weeglijstSheet.getRange(2, 8, deelnemers.length, 1);
  const aanwezigRule = SpreadsheetApp.newDataValidation()
    .requireValueInList(['Ja', 'Nee'], true)
    .setAllowInvalid(false)
    .build();
  aanwezigRange.setDataValidation(aanwezigRule);

  // Eenvoudigere aanpak voor gewichtsmutatie
  const gewichtRange = weeglijstSheet.getRange(2, 9, deelnemers.length, 1);
  gewichtRange.setNote("Vul een gewicht in tussen 15 en 101 kg. Komma of punt is beiden toegestaan.");

  // Kleuren voor betere zichtbaarheid
  if (deelnemers.length > 0) {
    weeglijstSheet.getRange(2, 3, deelnemers.length, 1).setBackground("#FFF2CC").setFontWeight("bold");
    weeglijstSheet.getRange(2, 4, deelnemers.length, 3).setBackground("#E6F4EA").setFontWeight("bold");
    weeglijstSheet.getRange(2, 7, deelnemers.length, 1).setBackground("#FFE599").setFontWeight("bold");
  }

  // Kleuren voor headers
  weeglijstSheet.getRange(1, 3, 1, 1).setBackground("#F1C232");
  weeglijstSheet.getRange(1, 4, 1, 3).setBackground("#B6D7A8");
  weeglijstSheet.getRange(1, 7, 1, 1).setBackground("#F1C232");

  // Pas kolombreedtes aan - eerst auto-resize voor alle kolommen
  for (let col = 1; col <= headers.length; col++) {
    weeglijstSheet.autoResizeColumn(col);
  }

  // Zet minimale breedtes voor betere leesbaarheid
  if (weeglijstSheet.getColumnWidth(1) < 140) weeglijstSheet.setColumnWidth(1, 140); // Naam
  if (weeglijstSheet.getColumnWidth(2) < 110) weeglijstSheet.setColumnWidth(2, 110); // Club
  if (weeglijstSheet.getColumnWidth(3) < 90) weeglijstSheet.setColumnWidth(3, 90);   // Lft-klasse
  if (weeglijstSheet.getColumnWidth(4) < 60) weeglijstSheet.setColumnWidth(4, 60);   // Blok
  if (weeglijstSheet.getColumnWidth(5) < 55) weeglijstSheet.setColumnWidth(5, 55);   // Mat
  if (weeglijstSheet.getColumnWidth(6) < 65) weeglijstSheet.setColumnWidth(6, 65);   // Poule
  if (weeglijstSheet.getColumnWidth(7) < 95) weeglijstSheet.setColumnWidth(7, 95);   // Gew. klasse
  if (weeglijstSheet.getColumnWidth(8) < 80) weeglijstSheet.setColumnWidth(8, 80);   // Aanwezig
  if (weeglijstSheet.getColumnWidth(9) < 95) weeglijstSheet.setColumnWidth(9, 95);   // Gew.mutatie
  if (weeglijstSheet.getColumnWidth(10) < 120) weeglijstSheet.setColumnWidth(10, 120); // Opmerkingen

  // Beperk maximale breedtes voor betere overzichtelijkheid
  for (let col = 1; col <= headers.length; col++) {
    const currentWidth = weeglijstSheet.getColumnWidth(col);
    if (currentWidth > 300) {
      weeglijstSheet.setColumnWidth(col, 300);
    }
  }

  // Centreer alle cellen
  weeglijstSheet.getRange(1, 1, deelnemers.length + 1, headers.length).setHorizontalAlignment("center");
  // Opmerkingen kolom (10) links uitlijnen
  weeglijstSheet.getRange(2, 10, deelnemers.length, 1).setHorizontalAlignment("left");

  // Voeg een gegevensfilter toe
  if (deelnemers.length > 0) {
    const filterRange = weeglijstSheet.getRange(1, 1, deelnemers.length + 1, headers.length);
    filterRange.createFilter();
    weeglijstSheet.setActiveRange(weeglijstSheet.getRange(1, 1));
  }

  // Zet de eerste kolom vast
  weeglijstSheet.setFrozenColumns(1);

  // Maak tabelranden
  if (deelnemers.length > 0) {
    weeglijstSheet.getRange(1, 1, deelnemers.length + 1, headers.length)
      .setBorder(true, true, true, true, true, true, '#D3D3D3', SpreadsheetApp.BorderStyle.SOLID);

    weeglijstSheet.getRange(1, 1, 1, headers.length)
      .setBorder(true, true, true, true, null, null, '#000000', SpreadsheetApp.BorderStyle.SOLID_MEDIUM);
  }

  // Voeg instructies toe
  const instructiesRij = weeglijstSheet.getLastRow() + 2;
  const instructies = [
    ["INSTRUCTIES VOOR WEEGLIJST:", "bold"],
    ["1. Zoeken: Klik op filtericoontje ▼ bij 'Naam' om te zoeken", "italic"],
    ["2. Aanwezigheid op tablet: Tik op het vakje ✓ om aanwezigheid te markeren", "italic"],
    ["3. Aanwezigheid op desktop: Klik op Ja/Nee in de 'Aanwezig' kolom", "italic"],
    ["4. Gew.mutatie: Vul afwijkend gewicht in (15-101 kg, automatisch aanwezig)", "italic"],
    ["5. Tip: zowel komma als punt als decimaalscheidingsteken werkt", "italic"],
    ["6. BELANGRIJK: Controleer gewichtsklasse (geel gemarkeerd) bij elke gewichtsmutatie!", "italic"]
  ];

  for (let i = 0; i < instructies.length; i++) {
    weeglijstSheet.getRange(instructiesRij + i, 1)
      .setValue(instructies[i][0])
      .setFontStyle(instructies[i][1])
      .setBackground("#F3F3F3");
  }

  weeglijstSheet.getRange(instructiesRij, 1, instructies.length, 1).setWrap(true);

  // Installeer de onEdit trigger voor automatische aanwezigheid bij gewichtsmutatie
  installeerWeeglijstTrigger();
}

/**
 * Installeer een onEdit trigger voor de weeglijst
 * Deze zorgt ervoor dat bij het invullen van een gewichtsmutatie automatisch Aanwezig op Ja wordt gezet
 */
function installeerWeeglijstTrigger() {
  const triggers = ScriptApp.getProjectTriggers();

  // Controleer of de trigger al bestaat
  let heeftWeeglijstTrigger = false;
  for (const trigger of triggers) {
    if (trigger.getHandlerFunction() === "onWeeglijstEdit") {
      heeftWeeglijstTrigger = true;
      break;
    }
  }

  // Maak de trigger aan als deze nog niet bestaat
  if (!heeftWeeglijstTrigger) {
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    ScriptApp.newTrigger("onWeeglijstEdit")
      .forSpreadsheet(ss)
      .onEdit()
      .create();
  }
}

/**
 * Event handler voor wijzigingen in de weeglijst
 * Wordt automatisch aangeroepen door de onEdit trigger
 * Zorgt ervoor dat bij het invullen van een gewichtsmutatie automatisch Aanwezig op Ja wordt gezet
 */
function onWeeglijstEdit(e) {
  // Controleer of dit een geldige bewerking is in de Weeglijst
  if (!e || !e.range) return;

  const sheet = e.range.getSheet();
  const sheetNaam = sheet.getName();

  // Alleen in Weeglijst
  if (sheetNaam !== "Weeglijst") return;

  // Controleer of er een waarde is ingevuld
  const nieuweWaarde = e.value;
  if (!nieuweWaarde) return;

  // Haal de headers op
  const headers = sheet.getRange(1, 1, 1, 15).getValues()[0];
  const gewichtsMutatieIdx = headers.indexOf("Gew.mutatie");
  const aanwezigIdx = headers.indexOf("Aanwezig");

  if (gewichtsMutatieIdx === -1 || aanwezigIdx === -1) return;

  // Controleer of de bewerking in de Gewichtsmutatie kolom is
  const editedCol = e.range.getColumn();
  const editedRow = e.range.getRow();

  if (editedCol !== (gewichtsMutatieIdx + 1)) return;
  if (editedRow <= 1) return; // Skip header rij

  // Controleer of de ingevulde waarde een geldig gewicht is
  // Accepteer getallen met komma of punt, eventueel met "kg" erachter
  const gewichtMatch = nieuweWaarde.toString().match(/([0-9]*[.,])?[0-9]+/);
  if (!gewichtMatch) return;

  const gewicht = parseFloat(gewichtMatch[0].replace(',', '.'));

  // Controleer of het gewicht realistisch is (tussen 15 en 150 kg)
  if (gewicht < 15 || gewicht > 150) return;

  // Zet automatisch Aanwezig op Ja
  const aanwezigCell = sheet.getRange(editedRow, aanwezigIdx + 1);
  aanwezigCell.setValue("Ja");

  // Zet groene achtergrondkleur voor aanwezigheid
  aanwezigCell.setBackground("#D9EAD3");

  // Check of judoka te zwaar/licht is en update Opmerking kolom
  const gewKlasseIdx = headers.indexOf("Gew. klasse");
  const opmerkingIdx = headers.indexOf("Opmerkingen");

  if (gewKlasseIdx !== -1 && opmerkingIdx !== -1) {
    const gewKlasse = sheet.getRange(editedRow, gewKlasseIdx + 1).getValue();

    // Parse gewichtsklasse (bijv. "-36kg" of "+70kg")
    const gewKlasseMatch = String(gewKlasse).match(/([+-])?(\d+)/);
    if (gewKlasseMatch) {
      const isPlus = gewKlasseMatch[1] === '+';
      const limiet = parseFloat(gewKlasseMatch[2]);

      let opmerking = "";

      if (isPlus) {
        // +70kg betekent minimaal 70kg
        if (gewicht < limiet) {
          opmerking = `Te licht! Minimaal ${limiet}kg. Alternatief: -${limiet}kg`;
        }
      } else {
        // -36kg betekent maximaal 36kg
        if (gewicht > limiet) {
          // Bereken volgende gewichtsklasse
          const volgendeKlasse = limiet + 4; // Meestal 4kg stappen
          opmerking = `Te zwaar! Maximaal ${limiet}kg. Alternatief: -${volgendeKlasse}kg`;
        }
      }

      // Update opmerking kolom
      if (opmerking) {
        const opmerkingCell = sheet.getRange(editedRow, opmerkingIdx + 1);
        opmerkingCell.setValue(opmerking);
        opmerkingCell.setBackground("#F4CCCC"); // Rode achtergrond voor waarschuwing
      } else {
        // Gewicht is OK - clear opmerking
        const opmerkingCell = sheet.getRange(editedRow, opmerkingIdx + 1);
        const huidigeOpmerking = opmerkingCell.getValue();
        // Alleen clearen als het een gewicht-gerelateerde opmerking was
        if (String(huidigeOpmerking).includes("Te zwaar") || String(huidigeOpmerking).includes("Te licht")) {
          opmerkingCell.setValue("");
          opmerkingCell.setBackground("#D9EAD3"); // Groene achtergrond
        }
      }
    }
  }
}
