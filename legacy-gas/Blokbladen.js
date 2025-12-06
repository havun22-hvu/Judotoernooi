// Blokbladen.js - Functies voor blokbladen generatie
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js (getAantalBlokken)
 */

/**
 * Maakt blokbladen op basis van de poule-indeling
 * Genereert voor elk blok een apart tabblad met:
 * - Judoka's georganiseerd per mat en poule
 * - Aanwezigheid tracking
 * - Gewichtsmutaties vanuit weeglijst
 * - Opmerkingen
 */
function maakBlokbladen() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  const weeglijstSheet = ss.getSheetByName('Weeglijst');

  if (!poulesSheet) {
    SpreadsheetApp.getUi().alert(
      'Fout',
      'Het tabblad "PouleIndeling" is niet gevonden. Genereer eerst een poule-indeling.',
      SpreadsheetApp.getUi().ButtonSet.OK
    );
    return;
  }

  if (!weeglijstSheet) {
    SpreadsheetApp.getUi().alert(
      'Fout',
      'Het tabblad "Weeglijst" is niet gevonden. Maak eerst een weeglijst.',
      SpreadsheetApp.getUi().ButtonSet.OK
    );
    return;
  }

  // Lees het aantal blokken uit de configuratie
  const aantalBlokken = getAantalBlokken();

  // Lees alle data uit pouleindeling
  const pouleLastRow = poulesSheet.getLastRow();
  const pouleData = poulesSheet.getRange(1, 1, pouleLastRow, 13).getValues();

  // Lees gewichtsmutaties en aanwezigheid uit weeglijst
  const weegLastRow = weeglijstSheet.getLastRow();
  const weegData = weeglijstSheet.getRange(1, 1, weegLastRow, 15).getValues();

  // Zoek indexen van kolommen in weeglijst
  const weegHeaders = weegData[0];
  const weegNaamIdx = weegHeaders.indexOf("Naam");
  const weegAanwezigIdx = weegHeaders.indexOf("Aanwezig");
  const weegMutatieIdx = weegHeaders.indexOf("Gew.mutatie");
  const weegOpmerkingenIdx = weegHeaders.indexOf("Opmerkingen");

  // Maak een map van naam naar aanwezigheid en gewichtsmutatie
  const judokaInfo = {};
  for (let i = 1; i < weegData.length; i++) {
    const row = weegData[i];
    const naam = row[weegNaamIdx];

    if (naam) {
      judokaInfo[naam] = {
        aanwezig: weegAanwezigIdx !== -1 ? row[weegAanwezigIdx] || "" : "",
        gewichtsmutatie: weegMutatieIdx !== -1 ? row[weegMutatieIdx] || "" : "",
        opmerkingen: weegOpmerkingenIdx !== -1 ? row[weegOpmerkingenIdx] || "" : ""
      };
    }
  }

  // Zoek indexen van belangrijke kolommen in pouleindeling
  const headers = pouleData[0];
  const naamIndex = headers.indexOf("Naam");
  const bandIndex = headers.indexOf("Band");
  const clubIndex = headers.indexOf("Club");
  const gewichtIndex = headers.indexOf("Gewichtsklasse");
  const geslachtIndex = headers.indexOf("Geslacht");
  const geboortejaarIndex = headers.indexOf("Geboortejaar");
  const blokIndex = headers.indexOf("Blok");
  const matIndex = headers.indexOf("Mat");
  const pouleNrIndex = headers.indexOf("Poule-nr");
  const pouleTitelIndex = headers.indexOf("Pouletitel");

  // Controleer of alle benodigde kolommen gevonden zijn
  if ([naamIndex, bandIndex, clubIndex, gewichtIndex, geslachtIndex, geboortejaarIndex,
       blokIndex, matIndex, pouleNrIndex, pouleTitelIndex].some(idx => idx === -1)) {
    SpreadsheetApp.getUi().alert(
      'Fout',
      'Niet alle benodigde kolommen gevonden in PouleIndeling tabblad.',
      SpreadsheetApp.getUi().ButtonSet.OK
    );
    return;
  }

  // Organiseer judoka's per blok, mat en poule
  const blokMatPouleMap = {};

  // Eerst verzamel alle poule-informatie
  const pouleMap = {};

  for (let i = 1; i < pouleData.length; i++) {
    const row = pouleData[i];
    const naam = row[naamIndex];
    const pouleNr = row[pouleNrIndex];
    const pouleTitel = row[pouleTitelIndex];
    const blokNr = row[blokIndex];
    const matNr = row[matIndex];

    // Sla rijen over die geen echte judoka zijn
    if (!naam || !pouleNr || naam === "" || pouleTitel === "") continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("TOTAAL") || naam.includes("Poule"))) continue;

    // Als deze poule nog niet in de map zit, voeg toe
    if (!pouleMap[pouleNr]) {
      pouleMap[pouleNr] = {
        pouleNr: pouleNr,
        titel: pouleTitel,
        blokNr: blokNr,
        matNr: matNr,
        judokas: []
      };
    }

    // Voeg judoka toe aan de poule
    pouleMap[pouleNr].judokas.push({
      naam: naam,
      band: row[bandIndex],
      club: row[clubIndex],
      gewicht: row[gewichtIndex],
      geslacht: row[geslachtIndex],
      geboortejaar: row[geboortejaarIndex],
      pouleNr: pouleNr,
      blokNr: blokNr,
      matNr: matNr
    });
  }

  // Controleer of er blok/mat nummers zijn toegewezen
  let heeftBlokMatToewijzingen = false;
  for (const pouleNr in pouleMap) {
    const poule = pouleMap[pouleNr];
    if (poule.blokNr && poule.matNr) {
      heeftBlokMatToewijzingen = true;
      break;
    }
  }

  if (!heeftBlokMatToewijzingen) {
    SpreadsheetApp.getUi().alert(
      'Geen blok/mat toewijzingen',
      'Er zijn geen blok- en matnummers toegewezen in de PouleIndeling.\n\n' +
      'Volg eerst deze stappen:\n' +
      '1. Genereer Blok/Mat Indeling\n' +
      '2. Vul bloknummers in (kolom C)\n' +
      '3. Vul poules in blokken\n' +
      '4. Vul matnummers in\n' +
      '5. Klik op "Vul Blok/Mat nummers in bij PouleIndeling"\n\n' +
      'Dan kun je de blokbladen genereren.',
      SpreadsheetApp.getUi().ButtonSet.OK
    );
    return;
  }

  // Nu organiseer poules per blok en mat
  for (const pouleNr in pouleMap) {
    const poule = pouleMap[pouleNr];

    // Sla poules over zonder blok/mat toewijzing
    if (!poule.blokNr || !poule.matNr) continue;

    // Initialiseer de blok/mat/poule structuur als nodig
    if (!blokMatPouleMap[poule.blokNr]) {
      blokMatPouleMap[poule.blokNr] = {};
    }

    if (!blokMatPouleMap[poule.blokNr][poule.matNr]) {
      blokMatPouleMap[poule.blokNr][poule.matNr] = [];
    }

    // Voeg poule toe aan de juiste blok/mat
    blokMatPouleMap[poule.blokNr][poule.matNr].push(poule);
  }

  // Nieuwe headers voor blokbladen
  const blokHeaders = [
    "Aanwezig", "Nr", "Naam", "Band", "Club", "Geslacht", "Geboortejaar", "Gew. klasse",
    "Mat", "Poule-nr", "Gew.mutatie", "Opmerkingen", "Alt. poule"
  ];

  // Defineer de kolomindexen voor het blokblad
  const aanwezigBlokIdx = 0;
  const nrBlokIdx = 1;
  const naamBlokIdx = 2;
  const bandBlokIdx = 3;
  const clubBlokIdx = 4;
  const geslachtBlokIdx = 5;
  const geboortejaarBlokIdx = 6;
  const gewichtBlokIdx = 7;
  const matBlokIdx = 8;
  const pouleNrBlokIdx = 9;
  const mutatieBlokIdx = 10;
  const opmerkingenBlokIdx = 11;
  const altPouleBlokIdx = 12;

  // Maak een blad voor elk blok
  for (let blokNr = 1; blokNr <= aantalBlokken; blokNr++) {
    const blokNaam = `Blok ${blokNr}`;
    let blokSheet;

    // Controleer of het blokblad al bestaat
    if (ss.getSheetByName(blokNaam)) {
      // Gebruik bestaand blad, maar wis de inhoud
      blokSheet = ss.getSheetByName(blokNaam);
      blokSheet.clear();

      // Verwijder alle bestaande data validatie regels om problemen te voorkomen
      const totalRows = blokSheet.getMaxRows();
      const totalCols = blokSheet.getMaxColumns();
      if (totalRows > 1 && totalCols > 0) {
        blokSheet.getRange(1, 1, totalRows, totalCols).setDataValidation(null);
      }
    } else {
      // Maak een nieuw blad
      blokSheet = ss.insertSheet(blokNaam);
    }

    // Headers voor het blokblad
    blokSheet.getRange(1, 1, 1, blokHeaders.length).setValues([blokHeaders])
      .setFontWeight("bold")
      .setBackground("#D9D9D9");

    // Bevries de eerste rij
    blokSheet.setFrozenRows(1);

    // Als dit blok geen poules heeft, toon een melding
    if (!blokMatPouleMap[blokNr] || Object.keys(blokMatPouleMap[blokNr]).length === 0) {
      blokSheet.getRange(2, 1, 1, blokHeaders.length).merge();
      blokSheet.getRange(2, 1).setValue(`Geen poules gevonden voor blok ${blokNr}`)
        .setHorizontalAlignment("center")
        .setFontStyle("italic");
      continue;
    }

    // Start vanaf rij 2
    let currentRow = 2;

    // Sorteer matten numeriek
    const matNummers = Object.keys(blokMatPouleMap[blokNr]).map(Number).sort((a, b) => a - b);

    // Loop door elke mat
    for (const matNr of matNummers) {
      // Voeg mat titel toe
      blokSheet.getRange(currentRow, 1, 1, blokHeaders.length).merge();
      blokSheet.getRange(currentRow, 1).setValue(`MAT ${matNr}`)
        .setBackground("#D9EAD3")
        .setHorizontalAlignment("center")
        .setFontWeight("bold");
      currentRow++;

      // Sorteer poules
      const poules = blokMatPouleMap[blokNr][matNr];
      poules.sort((a, b) => a.pouleNr - b.pouleNr);

      // Loop door elke poule op deze mat
      for (const poule of poules) {
        // Voeg poule titel toe
        blokSheet.getRange(currentRow, 1, 1, blokHeaders.length).merge();
        blokSheet.getRange(currentRow, 1).setValue(poule.titel)
          .setBackground("#B6D7A8")
          .setHorizontalAlignment("center")
          .setFontWeight("bold");
        currentRow++;

        // Sorteer judoka's alfabetisch
        poule.judokas.sort((a, b) => a.naam.localeCompare(b.naam));

        // Houdt bij welke rijen judoka's bevatten voor datavalidatie
        const judokaRijen = [];

        // Loop door elke judoka in de poule
        for (let i = 0; i < poule.judokas.length; i++) {
          const judoka = poule.judokas[i];

          // Haal informatie op uit de weeglijst
          const info = judokaInfo[judoka.naam] || {};

          // Judoka rij data
          const judokaRij = Array(blokHeaders.length).fill("");
          judokaRij[aanwezigBlokIdx] = "Nee";                      // Standaard op "Nee" zetten
          judokaRij[nrBlokIdx] = i + 1;                            // Nummer binnen poule
          judokaRij[naamBlokIdx] = judoka.naam;                    // Naam
          judokaRij[bandBlokIdx] = judoka.band;                    // Band
          judokaRij[clubBlokIdx] = judoka.club;                    // Club
          judokaRij[geslachtBlokIdx] = judoka.geslacht;            // Geslacht
          judokaRij[geboortejaarBlokIdx] = judoka.geboortejaar;    // Geboortejaar
          judokaRij[gewichtBlokIdx] = judoka.gewicht;              // Gewichtsklasse
          judokaRij[matBlokIdx] = matNr;                           // Mat
          judokaRij[pouleNrBlokIdx] = poule.pouleNr;               // Poule-nr
          judokaRij[mutatieBlokIdx] = info.gewichtsmutatie || "";  // Gewichtsmutatie
          judokaRij[opmerkingenBlokIdx] = info.opmerkingen || "";  // Opmerkingen

          // Voeg de rij toe
          blokSheet.getRange(currentRow, 1, 1, judokaRij.length).setValues([judokaRij]);

          // Markeer de "Nee" in de aanwezigheidskolom rood
          blokSheet.getRange(currentRow, aanwezigBlokIdx + 1).setBackground("#F4CCCC");

          // Markeer gewichtsmutatie rood indien aanwezig
          if (info.gewichtsmutatie) {
            blokSheet.getRange(currentRow, mutatieBlokIdx + 1).setBackground("#F4CCCC");
          }

          // Centreer de meeste cellen
          blokSheet.getRange(currentRow, 1, 1, blokHeaders.length).setHorizontalAlignment("center");

          // Naam en Club links uitlijnen voor betere leesbaarheid
          blokSheet.getRange(currentRow, naamBlokIdx + 1).setHorizontalAlignment("left");
          blokSheet.getRange(currentRow, clubBlokIdx + 1).setHorizontalAlignment("left");

          // Opmerkingen en Alt. poule links uitlijnen
          blokSheet.getRange(currentRow, opmerkingenBlokIdx + 1).setHorizontalAlignment("left");
          blokSheet.getRange(currentRow, altPouleBlokIdx + 1).setHorizontalAlignment("left");

          // Houd deze rij bij voor datavalidatie
          judokaRijen.push(currentRow);

          currentRow++;
        }

        // Voeg datavalidatie toe voor de Aanwezig kolom
        if (judokaRijen.length > 0) {
          const aanwezigRule = SpreadsheetApp.newDataValidation()
            .requireValueInList(['Ja', 'Nee'], true)
            .build();

          // Voeg datavalidatie toe voor elke judoka rij afzonderlijk
          for (const row of judokaRijen) {
            blokSheet.getRange(row, aanwezigBlokIdx + 1).setDataValidation(aanwezigRule);
          }
        }

        // Voeg een lege rij toe na elke poule voor betere leesbaarheid
        currentRow++;
      }

      // Voeg een extra lege rij toe na elke mat
      currentRow++;
    }

    // Pas kolombreedtes automatisch aan op basis van inhoud
    for (let col = 1; col <= blokHeaders.length; col++) {
      blokSheet.autoResizeColumn(col);
    }

    // Pas maximale/minimale breedtes aan voor specifieke kolommen
    // Opmerkingen kolom - breder maken
    const opmerkingenCol = opmerkingenBlokIdx + 1;
    if (blokSheet.getColumnWidth(opmerkingenCol) < 200) {
      blokSheet.setColumnWidth(opmerkingenCol, 200);
    }
    if (blokSheet.getColumnWidth(opmerkingenCol) > 350) {
      blokSheet.setColumnWidth(opmerkingenCol, 350);
    }

    // Alt. poule kolom - automatisch aanpassen aan inhoud met maximum
    const altPouleCol = altPouleBlokIdx + 1;
    if (blokSheet.getColumnWidth(altPouleCol) > 350) {
      blokSheet.setColumnWidth(altPouleCol, 350);
    }

    // Zet minimale breedtes voor betere leesbaarheid
    const naamCol = naamBlokIdx + 1;
    if (blokSheet.getColumnWidth(naamCol) < 150) {
      blokSheet.setColumnWidth(naamCol, 150);
    }

    const clubCol = clubBlokIdx + 1;
    if (blokSheet.getColumnWidth(clubCol) < 100) {
      blokSheet.setColumnWidth(clubCol, 100);
    }

    const aanwezigCol = aanwezigBlokIdx + 1;
    if (blokSheet.getColumnWidth(aanwezigCol) < 80) {
      blokSheet.setColumnWidth(aanwezigCol, 80);
    }
  }
}

/**
 * Update mat-nummer voor een poule in een blokblad
 * @param {number} pouleId - Het poule nummer
 * @param {number} nieuweMat - Het nieuwe mat nummer
 * @param {number} blokNr - Het blok nummer
 * @return {boolean} True als update succesvol, anders false
 */
function updatePouleMatInBlokblad(pouleId, nieuweMat, blokNr) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const blokSheet = ss.getSheetByName(`Blok ${blokNr}`);

  if (!blokSheet) return false;

  const data = blokSheet.getDataRange().getValues();
  const headers = data[0];
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");

  if (pouleNrIdx === -1 || matIdx === -1) return false;

  let updated = false;

  // Update alle judoka's in deze poule
  for (let i = 1; i < data.length; i++) {
    if (data[i][pouleNrIdx] === pouleId) {
      blokSheet.getRange(i + 1, matIdx + 1).setValue(nieuweMat);
      updated = true;
    }
  }

  return updated;
}
