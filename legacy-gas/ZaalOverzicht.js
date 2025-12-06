// ZaalOverzicht.js - Functies voor zaaloverzicht generatie en beheer
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * AFHANKELIJKHEDEN:
 * Dit bestand gebruikt functies uit:
 * - ConfigUtils.js: getAantalMatten(), getAantalBlokken()
 * - PouleUtils.js: leesPouleInformatie(), berekenAantalWedstrijdenVoorPoule()
 * - ParsingUtils.js: extractLeeftijdsklasseFromTitel(), extractGewichtswaarde()
 * - VerplaatsingUtils.js: vindVerplaatsteJudokas(), updatePouleMapMetVerplaatsingen()
 */

/**
 * Genereert een ZaalOverzicht met alle blokken, matten en poules
 * Toont een overzicht van het schema links en een poule-overzicht rechts
 */
function genereerZaalOverzicht() {
 const ui = SpreadsheetApp.getUi();
 const ss = SpreadsheetApp.getActiveSpreadsheet();

 // Controleer of benodigde tabbladen bestaan
 const poulesSheet = ss.getSheetByName("PouleIndeling");
 if (!poulesSheet) {
   ui.alert('Fout', 'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.', ui.ButtonSet.OK);
   return;
 }

 // Controleer of ZaalOverzicht bestaat
 let zaalOverzichtSheet;
 if (ss.getSheetByName("ZaalOverzicht")) {
   zaalOverzichtSheet = ss.getSheetByName("ZaalOverzicht");
   zaalOverzichtSheet.clear();
 } else {
   zaalOverzichtSheet = ss.insertSheet("ZaalOverzicht");
 }

 // Haal configuratiegegevens op
 const aantalMatten = getAantalMatten();
 const aantalBlokken = getAantalBlokken();

 // Pas kolom A aan op de grootste inhoud (TOTAAL TOERNOOI)
 zaalOverzichtSheet.setColumnWidth(1, 150); // Bredere eerste kolom

 // Titel per kolom apart zetten
 zaalOverzichtSheet.getRange(1, 2).setValue("ZAALOVERZICHT")
   .setFontWeight("bold")
   .setFontSize(14)
   .setHorizontalAlignment("center")
   .setBackground("#D9D9D9");

 zaalOverzichtSheet.getRange(1, 3).setValue("-")
   .setFontWeight("bold")
   .setFontSize(14)
   .setHorizontalAlignment("center")
   .setBackground("#D9D9D9");

 zaalOverzichtSheet.getRange(1, 4).setValue("WestFries Open JudoToernooi")
   .setFontWeight("bold")
   .setFontSize(14)
   .setHorizontalAlignment("center")
   .setBackground("#D9D9D9");

 // Nu pas rijen bevriezen
 zaalOverzichtSheet.setFrozenRows(2);

 // Mat headers
 zaalOverzichtSheet.getRange(2, 1).setValue("Blok/Mat")
   .setFontWeight("bold")
   .setBackground("#D9D9D9")
   .setHorizontalAlignment("center");

 for (let mat = 1; mat <= aantalMatten; mat++) {
   zaalOverzichtSheet.getRange(2, mat + 1).setValue(`Mat ${mat}`)
     .setFontWeight("bold")
     .setBackground("#D9D9D9")
     .setHorizontalAlignment("center");
   zaalOverzichtSheet.setColumnWidth(mat + 1, 180);
 }

 // Kolom voor blok-totaal
 zaalOverzichtSheet.getRange(2, aantalMatten + 2).setValue("Blok Totaal")
   .setFontWeight("bold")
   .setBackground("#D9D9D9")
   .setHorizontalAlignment("center");
 zaalOverzichtSheet.setColumnWidth(aantalMatten + 2, 100);

 // Verzamel poules per blok/mat
 const poulesData = poulesSheet.getDataRange().getValues();
 const headers = poulesData[0];
 const pouleNrIdx = headers.indexOf("Poule-nr");
 const pouleTitelIdx = headers.indexOf("Pouletitel");
 const blokIdx = headers.indexOf("Blok");
 const matIdx = headers.indexOf("Mat");

 // Controleer benodigde kolommen
 if (pouleNrIdx === -1 || pouleTitelIdx === -1 || blokIdx === -1 || matIdx === -1) {
   ui.alert('Fout', 'Benodigde kolommen ontbreken in PouleIndeling.', ui.ButtonSet.OK);
   return;
 }

 // Verzamel poules per blok/mat
 const blokMatPoules = {};
 const allePoules = [];

 for (let i = 1; i < poulesData.length; i++) {
   const pouleNr = poulesData[i][pouleNrIdx];
   const pouleTitel = poulesData[i][pouleTitelIdx];
   const blok = poulesData[i][blokIdx];
   const mat = poulesData[i][matIdx];

   // Skip rijen zonder poule of zonder blok/mat toewijzing
   if (!pouleNr || !pouleTitel || !blok || !mat) continue;

   // Sla titelrijen over
   if (typeof poulesData[i][0] === 'string' &&
       (poulesData[i][0].includes("TOTAAL") || poulesData[i][0].includes("Poule"))) continue;

   // Maak key voor deze blok/mat combinatie
   const key = `${blok}-${mat}`;

   if (!blokMatPoules[key]) {
     blokMatPoules[key] = [];
   }

   // Voeg poule toe als deze nog niet bestaat in de lijst
   const bestaandePoule = allePoules.find(p => p.pouleNr === pouleNr);
   if (!bestaandePoule) {
     // Verkort de titel voor betere weergave
     let verkortePoule = pouleTitel
       .replace("Poule", "P.")
       .replace("A-pupillen", "A-pup.")
       .replace("B-pupillen", "B-pup.");

     // Tel het aantal judoka's en wedstrijden voor deze poule
     let aantalJudokas = 0;

     // Tellen van judoka's in deze poule
     for (let j = 1; j < poulesData.length; j++) {
       if (poulesData[j][pouleNrIdx] === pouleNr) {
         // Alleen tellen als het een judoka is (geen titel)
         if (poulesData[j][0] && typeof poulesData[j][0] !== 'string' ||
             (typeof poulesData[j][0] === 'string' &&
              !poulesData[j][0].includes("TOTAAL") &&
              !poulesData[j][0].includes("Poule"))) {
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

     const pouleInfo = {
       pouleNr: pouleNr,
       titel: verkortePoule,
       volledeTitel: pouleTitel,
       blok: blok,
       mat: mat,
       aantalJudokas: aantalJudokas,
       aantalWedstrijden: aantalWedstrijden
     };

     allePoules.push(pouleInfo);

     // Voeg toe aan blok/mat combinatie
     if (!blokMatPoules[key].some(p => p.pouleNr === pouleNr)) {
       blokMatPoules[key].push(pouleInfo);
     }
   } else {
     // Als de poule al bestaat in allePoules maar nog niet in deze blok/mat combinatie
     if (!blokMatPoules[key].some(p => p.pouleNr === pouleNr)) {
       blokMatPoules[key].push(bestaandePoule);
     }
   }
 }

 // Vul het zaaloverzicht - efficiënter ruimtegebruik
 let currentRow = 3;
 const matTotalen = Array(aantalMatten + 1).fill(0); // Totalen per mat over alle blokken
 const blokTotalen = Array(aantalBlokken + 1).fill(0); // Totalen per blok
 let toernooiTotaal = 0; // Totaal aantal wedstrijden in toernooi

 for (let blok = 1; blok <= aantalBlokken; blok++) {
   // Blok header, meteen eerste poule er direct achter
   zaalOverzichtSheet.getRange(currentRow, 1).setValue(`Blok ${blok}`)
     .setFontWeight("bold")
     .setBackground("#F3F3F3");

   // Houdt wedstrijden per mat bij voor dit blok
   const blokMatWedstrijden = Array(aantalMatten + 1).fill(0);

   // Bepaal aantal rijen voor dit blok
   let maxPoulesPerMat = 0;
   for (let mat = 1; mat <= aantalMatten; mat++) {
     const key = `${blok}-${mat}`;
     const poules = blokMatPoules[key] || [];
     maxPoulesPerMat = Math.max(maxPoulesPerMat, poules.length);
   }

   // Voor elke rij poules
   for (let pouleRij = 0; pouleRij < maxPoulesPerMat; pouleRij++) {
     const rowValues = Array(aantalMatten + 2).fill("");

     // Vul poules in voor elke mat
     for (let mat = 1; mat <= aantalMatten; mat++) {
       const key = `${blok}-${mat}`;
       const poules = blokMatPoules[key] || [];

       if (pouleRij < poules.length) {
         const poule = poules[pouleRij];
         rowValues[mat] = `${poule.titel} (${poule.aantalWedstrijden} w.)`;
         blokMatWedstrijden[mat] += poule.aantalWedstrijden;
       }
     }

     // Als het de eerste rij is, zet het op dezelfde regel als het bloknummer
     if (pouleRij === 0) {
       zaalOverzichtSheet.getRange(currentRow, 2, 1, aantalMatten + 1).setValues([rowValues.slice(1)]);
     } else {
       // Anders op een nieuwe regel
       currentRow++;
       zaalOverzichtSheet.getRange(currentRow, 1, 1, aantalMatten + 2).setValues([rowValues]);
     }
   }

   // Totalen per mat voor dit blok
   currentRow++;
   const totaalRij = ["Totaal mat"];
   let blokTotaal = 0;

   for (let mat = 1; mat <= aantalMatten; mat++) {
     totaalRij.push(blokMatWedstrijden[mat]);
     blokTotaal += blokMatWedstrijden[mat];
     matTotalen[mat] += blokMatWedstrijden[mat];
   }

   // Bijhouden in blokTotalen
   blokTotalen[blok] = blokTotaal;
   toernooiTotaal += blokTotaal;

   // Toevoegen van bloktotaal aan de totaalrij
   totaalRij.push(blokTotaal);

   zaalOverzichtSheet.getRange(currentRow, 1, 1, totaalRij.length).setValues([totaalRij])
     .setFontWeight("bold")
     .setBackground("#E6F4EA");

   // Maak het bloktotaal dikgedrukt en geef het een duidelijkere achtergrond
   zaalOverzichtSheet.getRange(currentRow, aantalMatten + 2)
     .setFontWeight("bold")
     .setBackground("#B6D7A8");

   currentRow += 1; // Slechts één lege rij tussen blokken
 }

 // Voeg eindtotalen toe (slechts één lege rij tussen laatste blok en totaal)
 currentRow++;

 // Eindtotaal alle blokken
 const eindRij = ["TOTAAL TOERNOOI"];

 // Lege cellen voor de mat kolommen (deze totalen zijn niet nuttig)
 for (let mat = 1; mat <= aantalMatten; mat++) {
   eindRij.push("");
 }

 // Alleen het toernooi totaal (totaal aantal wedstrijden)
 eindRij.push(toernooiTotaal);

 zaalOverzichtSheet.getRange(currentRow, 1, 1, eindRij.length).setValues([eindRij])
   .setFontWeight("bold")
   .setBackground("#D9EAD3");

 // RECHTER OVERZICHT: Poule-overzicht toevoegen
 // Begin het overzicht rechts van de matten
 const startKolom = aantalMatten + 3;

 // Hoofdtitel (op rij 2 voor consistentie met hoofdtabel)
 zaalOverzichtSheet.getRange(2, startKolom).setValue("Poule");
 zaalOverzichtSheet.getRange(2, startKolom + 1).setValue("Blok");
 zaalOverzichtSheet.getRange(2, startKolom + 2).setValue("Mat");
 zaalOverzichtSheet.getRange(2, startKolom + 3).setValue("Judoka's");
 zaalOverzichtSheet.getRange(2, startKolom + 4).setValue("Wedstrijden");
 zaalOverzichtSheet.getRange(2, startKolom, 1, 5).setFontWeight("bold").setBackground("#D9D9D9");

 // Sorteer poules van jong naar oud en van licht naar zwaar
 allePoules.sort((a, b) => {
   // Extract leeftijdsklasse - gebruik functie uit ParsingUtils.js
   const leeftijdsklasseA = extractLeeftijdsklasseFromTitel(a.volledeTitel);
   const leeftijdsklasseB = extractLeeftijdsklasseFromTitel(b.volledeTitel);

   // Bepaal volgorde leeftijdsklassen
   const leeftijdsVolgorde = {
     "Mini's": 1,
     "A-pupillen": 2,
     "B-pupillen": 3,
     "Dames -15": 4,
     "Heren -15": 5
   };

   const volgordeA = leeftijdsVolgorde[leeftijdsklasseA] || 99;
   const volgordeB = leeftijdsVolgorde[leeftijdsklasseB] || 99;

   // Sorteer eerst op leeftijdsklasse
   if (volgordeA !== volgordeB) {
     return volgordeA - volgordeB;
   }

   // Daarna op gewicht - van licht naar zwaar
   // extractGewichtswaarde geeft negatieve waarden voor lichte gewichten (bijv. -30)
   // en positieve voor zware (bijv. +38), dus we moeten omgekeerd sorteren
   const gewichtA = extractGewichtswaarde(a.volledeTitel);
   const gewichtB = extractGewichtswaarde(b.volledeTitel);

   return gewichtB - gewichtA;
 });

 // Vul poules in
 for (let i = 0; i < allePoules.length; i++) {
   const row = i + 3;
   const poule = allePoules[i];

   zaalOverzichtSheet.getRange(row, startKolom).setValue(poule.titel);
   zaalOverzichtSheet.getRange(row, startKolom + 1).setValue(poule.blok);
   zaalOverzichtSheet.getRange(row, startKolom + 2).setValue(poule.mat);
   zaalOverzichtSheet.getRange(row, startKolom + 3).setValue(poule.aantalJudokas);
   zaalOverzichtSheet.getRange(row, startKolom + 4).setValue(poule.aantalWedstrijden);
 }

 // Stel kolombreedtes in voor rechter overzicht
 zaalOverzichtSheet.setColumnWidth(startKolom, 180);
 zaalOverzichtSheet.setColumnWidth(startKolom + 1, 60);
 zaalOverzichtSheet.setColumnWidth(startKolom + 2, 60);
 zaalOverzichtSheet.setColumnWidth(startKolom + 3, 80);
 zaalOverzichtSheet.setColumnWidth(startKolom + 4, 100);

 // Centreer alle cellen in rechter overzicht
 zaalOverzichtSheet.getRange(3, startKolom, allePoules.length, 5).setHorizontalAlignment("center");

 // Maak schakelknoppen
 zaalOverzichtSheet.getRange(1, aantalMatten + 2).setValue("Overzicht →")
   .setBackground("#FCE5CD")  // Lichtorange
   .setFontWeight("bold")
   .setHorizontalAlignment("center")
   .setBorder(true, true, true, true, null, null);

 zaalOverzichtSheet.getRange(1, startKolom).setValue("← Schema")
   .setBackground("#D9EAD3")  // Lichtgroen
   .setFontWeight("bold")
   .setHorizontalAlignment("center")
   .setBorder(true, true, true, true, null, null);

 // Alle kolommen tonen
 zaalOverzichtSheet.showColumns(1, startKolom + 5);

 ui.alert('Succes', 'Het ZaalOverzicht is gegenereerd met poules aan de rechterzijde.', ui.ButtonSet.OK);
}

/**
 * Werkt het bestaande ZaalOverzicht bij met actuele informatie
 * Verwerkt verplaatste judoka's en actualiseert het aantal wedstrijden
 */
function werkZaalOverzichtBij() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer benodigde tabbladen
  const ZaalOverzichtSheet = ss.getSheetByName('ZaalOverzicht');
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!ZaalOverzichtSheet) {
    ui.alert('Fout', 'Het tabblad "ZaalOverzicht" bestaat niet. Genereer eerst een ZaalOverzicht.', ui.ButtonSet.OK);
    return;
  }

  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.', ui.ButtonSet.OK);
    return;
  }

  // Lees alle huidige poule-informatie uit de PouleIndeling
  const pouleMap = leesPouleInformatie(poulesSheet);

  // Verzamel informatie over verplaatste judoka's uit de blokbladen
  const verplaatsteJudokas = vindVerplaatsteJudokas();

  // Update poule-informatie met verplaatste judoka's
  updatePouleMapMetVerplaatsingen(pouleMap, verplaatsteJudokas);

  // Nu bijwerken van het ZaalOverzicht met actuele informatie
  updateZaalOverzichtMetActueleInfo(ZaalOverzichtSheet, pouleMap);

  // Overzicht van leeftijds- en gewichtsklassen rechts toevoegen
  toevoegenGewichtsklassenOverzicht(ZaalOverzichtSheet, pouleMap);

  // Pas kolombreedtes aan
  ZaalOverzichtSheet.autoResizeColumns(1, ZaalOverzichtSheet.getLastColumn());
  voegSchakelknoppen();

  ui.alert(
    'ZaalOverzicht bijgewerkt',
    'Het ZaalOverzicht is bijgewerkt. Verplaatste judoka\'s en aanwezigheidsgegevens zijn verwerkt in het overzicht.',
    ui.ButtonSet.OK
  );
}

/**
 * Genereert een actueel ZaalOverzicht in een nieuw tabblad
 * Kopieert de structuur van het originele ZaalOverzicht en vult deze met actuele informatie
 */
function genereerActueelZaalOverzicht() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer benodigde tabbladen
  const ZaalOverzichtSheet = ss.getSheetByName('ZaalOverzicht');
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!ZaalOverzichtSheet) {
    ui.alert('Fout', 'Het tabblad "ZaalOverzicht" bestaat niet. Genereer eerst een ZaalOverzicht.', ui.ButtonSet.OK);
    return;
  }

  if (!poulesSheet) {
    ui.alert('Fout', 'Het tabblad "PouleIndeling" bestaat niet. Genereer eerst de poule-indeling.', ui.ButtonSet.OK);
    return;
  }

  // Maak nieuw tabblad voor actueel ZaalOverzicht (of overschrijf bestaand)
  let schemaSheet;
  if (ss.getSheetByName('ActueelZaalOverzicht')) {
    schemaSheet = ss.getSheetByName('ActueelZaalOverzicht');
    schemaSheet.clear();
  } else {
    schemaSheet = ss.insertSheet('ActueelZaalOverzicht');
  }

  // Kopieër alleen de formattering en structuur (kolommen/rijen) van het oorspronkelijke ZaalOverzicht
  const lastRow = ZaalOverzichtSheet.getLastRow();
  const dataRange = ZaalOverzichtSheet.getRange(1, 1, lastRow, 9); // A t/m I = 9 kolommen
  dataRange.copyTo(schemaSheet.getRange(1, 1));

  // Lees alle huidige poule-informatie uit de PouleIndeling
  const pouleMap = leesPouleInformatie(poulesSheet);

  // Verzamel informatie over verplaatste judoka's uit de blokbladen
  const verplaatsteJudokas = vindVerplaatsteJudokas();

  // Update poule-informatie met verplaatste judoka's
  updatePouleMapMetVerplaatsingen(pouleMap, verplaatsteJudokas);

  // Nu bijwerken van het ZaalOverzicht met actuele informatie
  updateZaalOverzichtMetActueleInfo(schemaSheet, pouleMap);

  // Overzicht van leeftijds- en gewichtsklassen rechts toevoegen
  toevoegenGewichtsklassenOverzicht(schemaSheet, pouleMap);

  // Pas kolombreedtes aan
  schemaSheet.autoResizeColumns(1, schemaSheet.getLastColumn());
  voegSchakelknoppen();
  ui.alert(
    'Actueel ZaalOverzicht gegenereerd',
    'Het actuele ZaalOverzicht is gegenereerd. Verplaatste judoka\'s zijn verwerkt in het overzicht.',
    ui.ButtonSet.OK
  );
}

/**
 * Update het ZaalOverzicht met actuele informatie
 * Verwerkt verplaatste judoka's en actualiseert het aantal wedstrijden per poule
 */
function updateZaalOverzichtMetActueleInfo(schemaSheet, pouleMap) {
  // Maak een map om poules op te zoeken op basis van bloknummer en matnummer
  const bloknrMatPouleMap = {};

  for (const pouleNr in pouleMap) {
    const poule = pouleMap[pouleNr];
    const bloknr = poule.blokNr;
    const matnr = poule.matNr;

    if (!bloknrMatPouleMap[bloknr]) {
      bloknrMatPouleMap[bloknr] = {};
    }

    if (!bloknrMatPouleMap[bloknr][matnr]) {
      bloknrMatPouleMap[bloknr][matnr] = [];
    }

    bloknrMatPouleMap[bloknr][matnr].push(poule);
  }

  // Doorloop het schema en update de poules met actuele informatie
  const lastRow = schemaSheet.getLastRow();
  const data = schemaSheet.getRange(1, 1, lastRow, 9).getValues();
  let currentBlok = null;

  for (let row = 0; row < data.length; row++) {
    const waarde = data[row][0]; // Kolom A

    // Detecteer bloknummer
    if (waarde && typeof waarde === 'string' && waarde.startsWith('Blok ')) {
      currentBlok = parseInt(waarde.replace('Blok ', ''));
      continue;
    }

    // Als we geen geldig bloknummer hebben, ga door naar de volgende rij
    if (!currentBlok || !bloknrMatPouleMap[currentBlok]) continue;

    // Als dit een 'Totaal mat' rij is, update met actuele informatie
    if (waarde === 'Totaal mat') {
      const totalenRij = row + 1; // 1-based
      const matTotalen = [];
      matTotalen.push('Totaal mat');

      let bloktotaal = 0;

      // Bereken actuele totalen per mat ALLEEN BINNEN DIT BLOK
      for (let matnr = 1; matnr <= Object.keys(bloknrMatPouleMap[currentBlok]).length; matnr++) {
        const matpoules = bloknrMatPouleMap[currentBlok][matnr] || [];
        const mattotaal = matpoules.reduce((sum, p) => sum + p.aantalWedstrijden, 0);
        matTotalen.push(mattotaal);
        bloktotaal += mattotaal;
      }

      // Vul de rest aan met lege cellen
      for (let i = matTotalen.length; i < 9; i++) {
        matTotalen.push('');
      }

      // Update de rij
      schemaSheet.getRange(totalenRij, 1, 1, matTotalen.length).setValues([matTotalen]);
      continue;
    }

    // Alleen doorgaan als dit een poule-rij is
    let isPouleRij = false;
    for (let mat = 1; mat <= 8; mat++) {
      if (data[row][mat] && typeof data[row][mat] === 'string' &&
         (data[row][mat].includes('Poule') || data[row][mat].includes('P.'))) {
        isPouleRij = true;
        break;
      }
    }

    if (!isPouleRij) continue;

    // Update de poule-informatie per mat
    for (let mat = 1; mat <= 8; mat++) {
      const celWaarde = data[row][mat];
      if (!celWaarde || typeof celWaarde !== 'string') continue;

      // Extract poule informatie uit "P. X" of "Poule X" (voor backward compatibility)
      let pouleMatch = celWaarde.match(/P\. (\d+)/);
      if (!pouleMatch) {
        pouleMatch = celWaarde.match(/Poule (\d+)/);
      }

      if (!pouleMatch) continue;

      const pouleNr = parseInt(pouleMatch[1]);
      if (!pouleMap[pouleNr]) continue;

      // Haal actuele informatie op
      const poule = pouleMap[pouleNr];

      // Gebruik "P." in plaats van "Poule", "w." i.p.v. "wedstrijden",
      // en "pup." i.p.v. "pupillen"
      let titel = poule.titel
        .replace("Poule", "P.")
        .replace("A-pupillen", "A-pup.")
        .replace("B-pupillen", "B-pup.");

      const nieuweWaarde = `${titel} (${poule.aantalWedstrijden} w.)`;

      // Update de cel met actuele informatie
      schemaSheet.getRange(row + 1, mat + 1).setValue(nieuweWaarde);
    }
  }
}

/**
 * Voegt een gewichtsklassen overzicht toe rechts van het ZaalOverzicht
 * Toont leeftijdsklasse, gewichtsklasse, poule, blok, mat, wedstrijden en aantal judoka's
 */
function toevoegenGewichtsklassenOverzicht(schemaSheet, pouleMap) {
  // Bepaal de startkolom voor het overzicht
  const kolomOffset = 2; // Ruimte tussen schema en overzicht
  let startKolom = 0;

  // Zoek de laatste kolom van het bestaande schema
  const lastCol = schemaSheet.getLastColumn();
  const lastRow = schemaSheet.getLastRow();

  // Zoek de eerste lege kolom
  for (let i = 1; i <= lastCol; i++) {
    const range = schemaSheet.getRange(1, i, lastRow, 1);
    if (range.getValues().flat().every(cell => !cell)) {
      startKolom = i;
      break;
    }
  }

  if (startKolom === 0) {
    startKolom = lastCol + 1;
  }

  // Overzicht-kolommen
  startKolom += kolomOffset;

  // Headers voor het overzicht
  schemaSheet.getRange(1, startKolom, 1, 7).setValues([[
    "Leeftijdsklasse", "Gewichtsklasse", "Poule", "Blok", "Mat", "Wedstrijden", "Judoka's"
  ]]);
  schemaSheet.getRange(1, startKolom, 1, 7).setFontWeight("bold").setBackground("#D9D9D9");

  // Verzamel alle poules voor het overzicht
  const poules = Object.values(pouleMap);

  // Sorteer poules op leeftijdsklasse en gewichtsklasse
  poules.sort((a, b) => {
    // Volgorde van leeftijdsklassen
    const leeftijdsVolgorde = {
      "Mini's": 1,
      "A-pupillen": 2,
      "B-pupillen": 3,
      "Dames -15": 4,
      "Heren -15": 5
    };

    // Vergelijk eerst op leeftijdsklasse
    const leeftijdA = leeftijdsVolgorde[a.leeftijdsklasse] || 99;
    const leeftijdB = leeftijdsVolgorde[b.leeftijdsklasse] || 99;

    if (leeftijdA !== leeftijdB) {
      return leeftijdA - leeftijdB;
    }

    // Dan op gewicht
    const gewichtA = parseGewicht(a.gewichtsklasse);
    const gewichtB = parseGewicht(b.gewichtsklasse);

    return gewichtA - gewichtB;
  });

  // Schrijf alle poules
  let row = 2;
  let huidigeLeeftijdsklasse = null;

  for (const poule of poules) {
    // Voeg een header toe voor elke nieuwe leeftijdsklasse
    if (poule.leeftijdsklasse !== huidigeLeeftijdsklasse) {
      // Voeg een lege rij toe tussen leeftijdsklassen (behalve voor de eerste)
      if (huidigeLeeftijdsklasse !== null) {
        schemaSheet.getRange(row, startKolom, 1, 7).setValues([[
          "", "", "", "", "", "", ""
        ]]);
        row++;
      }

      // Voeg header toe
      schemaSheet.getRange(row, startKolom, 1, 7).merge();
      schemaSheet.getRange(row, startKolom).setValue(poule.leeftijdsklasse)
        .setBackground("#EFEFEF")
        .setFontWeight("bold")
        .setHorizontalAlignment("center");
      row++;

      huidigeLeeftijdsklasse = poule.leeftijdsklasse;
    }

    // Bepaal achtergrondkleur op basis van judoka-count
    // Rood als er minder dan 3 actieve judoka's zijn
    let bgColor = null;
    if (poule.actieveJudokas < 3) {
      bgColor = "#F4CCCC"; // Rood
    }

    // Aantal actieve judoka's bepalen
    const aantalJudokas = poule.actieveJudokas || poule.judokas.filter(j => !j.verplaatst).length;

    // Schrijf poule gegevens
    schemaSheet.getRange(row, startKolom, 1, 7).setValues([[
      poule.leeftijdsklasse,
      poule.gewichtsklasse,
      poule.pouleNr,
      poule.blokNr,
      poule.matNr,
      poule.aantalWedstrijden,
      aantalJudokas
    ]]);

    // Zet alle cellen in het midden uitgelijnd
    schemaSheet.getRange(row, startKolom, 1, 7).setHorizontalAlignment("center");

    // Pas achtergrondkleur toe indien nodig
    if (bgColor) {
      schemaSheet.getRange(row, startKolom, 1, 7).setBackground(bgColor);
    }

    row++;
  }
}

/**
 * Voegt een poule-overzicht toe aan het ZaalOverzicht
 * Toont poules gesorteerd op leeftijdsklasse en gewicht
 */
function voegPouleOverzichtToe(sheet, aantalMatten, allePoules) {
  // Begin het overzicht rechts van de matten
  const startKolom = aantalMatten + 3;

  // Hoofdtitel
  sheet.getRange(2, startKolom).setValue("Poule");
  sheet.getRange(2, startKolom + 1).setValue("Blok");
  sheet.getRange(2, startKolom + 2).setValue("Mat");
  sheet.getRange(2, startKolom + 3).setValue("Judoka's");
  sheet.getRange(2, startKolom + 4).setValue("Wedstrijden");
  sheet.getRange(2, startKolom, 1, 5).setFontWeight("bold").setBackground("#D9D9D9");

  // Sorteer poules van jong naar oud en van licht naar zwaar
  allePoules.sort((a, b) => {
    // Extract leeftijdsklasse - gebruik functie uit ParsingUtils.js
    const leeftijdsklasseA = extractLeeftijdsklasseFromTitel(a.volledeTitel);
    const leeftijdsklasseB = extractLeeftijdsklasseFromTitel(b.volledeTitel);

    // Bepaal volgorde leeftijdsklassen
    const leeftijdsVolgorde = {
      "Mini's": 1,
      "A-pupillen": 2,
      "B-pupillen": 3,
      "Dames -15": 4,
      "Heren -15": 5
    };

    const volgordeA = leeftijdsVolgorde[leeftijdsklasseA] || 99;
    const volgordeB = leeftijdsVolgorde[leeftijdsklasseB] || 99;

    // Sorteer eerst op leeftijdsklasse
    if (volgordeA !== volgordeB) {
      return volgordeA - volgordeB;
    }

    // Daarna op gewicht - van licht naar zwaar
    // extractGewichtswaarde geeft negatieve waarden voor lichte gewichten (bijv. -30)
    // en positieve voor zware (bijv. +38), dus we moeten omgekeerd sorteren
    const gewichtA = extractGewichtswaarde(a.volledeTitel);
    const gewichtB = extractGewichtswaarde(b.volledeTitel);

    return gewichtB - gewichtA;
  });

  // Vul poules in
  for (let i = 0; i < allePoules.length; i++) {
    const row = i + 3;
    const poule = allePoules[i];

    sheet.getRange(row, startKolom).setValue(poule.titel);
    sheet.getRange(row, startKolom + 1).setValue(poule.blok);
    sheet.getRange(row, startKolom + 2).setValue(poule.mat);
    sheet.getRange(row, startKolom + 3).setValue(poule.aantalJudokas);
    sheet.getRange(row, startKolom + 4).setValue(poule.aantalWedstrijden);
  }

  // Stel kolombreedtes in
  sheet.setColumnWidth(startKolom, 180);
  sheet.setColumnWidth(startKolom + 1, 60);
  sheet.setColumnWidth(startKolom + 2, 60);
  sheet.setColumnWidth(startKolom + 3, 80);
  sheet.setColumnWidth(startKolom + 4, 100);

  // Centreer alle cellen
  sheet.getRange(3, startKolom, allePoules.length, 5).setHorizontalAlignment("center");

  // Verberg het overzicht initieel (tenzij de schakelknop is ingedrukt)
  sheet.hideColumns(startKolom, 5);
}

/**
 * Voegt schakelknoppen toe aan het ZaalOverzicht
 * Knoppen om te schakelen tussen schema en overzicht weergave
 */
function voegSchakelknoppen() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Verwerk beide schema tabbladen
  const tabbladen = ["ZaalOverzicht"];

  for (const tabNaam of tabbladen) {
    const sheet = ss.getSheetByName(tabNaam);
    if (!sheet) continue;

    // Bepaal de positie van het gewichtsklassen overzicht
    const aantalMatten = getAantalMatten();
    const schemaKolomBreedte = aantalMatten + 1;
    const overzichtKolom = schemaKolomBreedte + 2;

    // Maak een knop voor "Toon Schema" en "Toon Overzicht"
    sheet.getRange(1, overzichtKolom).setValue("← Schema")
      .setBackground("#D9EAD3")  // Lichtgroen
      .setFontWeight("bold")
      .setHorizontalAlignment("center")
      .setBorder(true, true, true, true, null, null)
      .setComment("Klik hier om het schema te tonen en het overzicht te verbergen");

    sheet.getRange(1, schemaKolomBreedte).setValue("Overzicht →")
      .setBackground("#FCE5CD")  // Lichtorange
      .setFontWeight("bold")
      .setHorizontalAlignment("center")
      .setBorder(true, true, true, true, null, null)
      .setComment("Klik hier om het gewichtsklassen overzicht te tonen en het schema te verbergen");
  }

  // Maak de onEdit trigger indien deze nog niet bestaat
  const triggers = ScriptApp.getProjectTriggers();
  let heeftOnEditTrigger = false;

  for (const trigger of triggers) {
    if (trigger.getHandlerFunction() === "onSchemaKnopKlik") {
      heeftOnEditTrigger = true;
      break;
    }
  }

  if (!heeftOnEditTrigger) {
    ScriptApp.newTrigger("onSchemaKnopKlik")
      .forSpreadsheet(ss)
      .onEdit()
      .create();
  }
}

/**
 * Event handler voor het klikken op schema knoppen
 * Wordt automatisch aangeroepen door de onEdit trigger
 */
function onSchemaKnopKlik(e) {
  // Controleer of dit een geldige bewerking is in een schema tabblad
  const sheet = e.range.getSheet();
  const sheetNaam = sheet.getName();

  if (sheetNaam !== "ZaalOverzicht") {
    return; // Alleen reageren op het schema tabblad
  }

  // Bepaal de kolommen voor schema en overzicht
  const aantalMatten = getAantalMatten();
  const schemaKolomBreedte = aantalMatten + 1;
  const overzichtKolom = schemaKolomBreedte + 2;

  // Controleer of een van de knoppen is geklikt
  const klikRij = e.range.getRow();
  const klikKolom = e.range.getColumn();
  const klikWaarde = e.value;

  // Als het niet de eerste rij is (waar de knoppen staan), stop
  if (klikRij !== 1) return;

  // Check of dit de "← Schema" knop is (in overzichtsgedeelte)
  if (klikKolom === overzichtKolom && klikWaarde === "← Schema") {
    // Verberg het overzicht, toon het schema
    sheet.hideColumns(overzichtKolom, sheet.getMaxColumns() - overzichtKolom + 1);
    sheet.showColumns(1, schemaKolomBreedte);
  }
  // Check of dit de "Overzicht →" knop is (in schema gedeelte)
  else if (klikKolom === schemaKolomBreedte && klikWaarde === "Overzicht →") {
    // Verberg het schema, toon het overzicht
    sheet.hideColumns(1, schemaKolomBreedte);
    sheet.showColumns(overzichtKolom, sheet.getMaxColumns() - overzichtKolom + 1);
  }
}

/**
 * Voegt een gewichtsklassen overzicht toe aan het ZaalOverzicht
 * @param {Sheet} sheet - Het ZaalOverzicht tabblad
 * @param {number} aantalMatten - Het aantal matten in gebruik
 */
function voegGewichtsklassenOverzichtToe(sheet, aantalMatten) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName("PouleIndeling");

  if (!poulesSheet) return;

  // Lees gewichtsklassen en wedstrijden uit PouleIndeling
  const gewichtsklassenData = leesGewichtsklassenEnWedstrijden(poulesSheet);

  if (gewichtsklassenData.length === 0) return;

  // Begin het overzicht rechts van de matten
  const startKolom = aantalMatten + 3;

  // Hoofdtitel
  sheet.getRange(2, startKolom).setValue("Gewichtsklasse");
  sheet.getRange(2, startKolom + 1).setValue("Wedstrijden");
  sheet.getRange(2, startKolom + 2).setValue("Blok");
  sheet.getRange(2, startKolom, 1, 3).setFontWeight("bold").setBackground("#D9D9D9");

  // Vul gewichtsklassen in
  for (let i = 0; i < gewichtsklassenData.length; i++) {
    const row = i + 3;
    sheet.getRange(row, startKolom).setValue(gewichtsklassenData[i].gewichtsklasse);
    sheet.getRange(row, startKolom + 1).setValue(gewichtsklassenData[i].wedstrijden);

    // Haal het blok op waarin deze gewichtsklasse is ingedeeld
    const blok = vindBlokVoorGewichtsklasse(gewichtsklassenData[i].gewichtsklasse);
    if (blok) {
      sheet.getRange(row, startKolom + 2).setValue(blok);
    }
  }

  // Stel kolombreedtes in
  sheet.setColumnWidth(startKolom, 200);
  sheet.setColumnWidth(startKolom + 1, 100);
  sheet.setColumnWidth(startKolom + 2, 60);

  // Centreer alle cellen
  sheet.getRange(3, startKolom, gewichtsklassenData.length, 3).setHorizontalAlignment("center");

  // Verberg het overzicht initieel
  sheet.hideColumns(startKolom, 3);
}

/**
 * Vindt het blok waarin een gewichtsklasse is ingedeeld
 * @param {string} gewichtsklasse - De gewichtsklasse
 * @return {number|null} Het bloknummer of null als niet gevonden
 */
function vindBlokVoorGewichtsklasse(gewichtsklasse) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const verdelingSheet = ss.getSheetByName("Blok/Mat verdeling");

  if (!verdelingSheet) return null;

  // Zoek de gewichtsklasse in de linkerkolom
  const data = verdelingSheet.getDataRange().getValues();

  for (let i = 3; i < data.length; i++) {
    if (data[i][0] === gewichtsklasse) {
      const blok = data[i][2];
      if (blok && !isNaN(blok)) {
        return blok;
      }
      break;
    }
  }

  return null;
}

/**
 * Helper functie: Parse gewichtsklasse voor sortering
 * Converteert gewichtsklasse naar numerieke waarde voor correcte sortering
 * @param {string} gewichtsklasse - De gewichtsklasse (bijv. "-30 kg" of "+66 kg")
 * @return {number} Numerieke waarde voor sortering
 */
function parseGewicht(gewichtsklasse) {
  if (!gewichtsklasse) return 0;

  const match = gewichtsklasse.match(/([+-]?)(\d+)/);
  if (!match) return 0;

  const prefix = match[1];
  const value = parseInt(match[2]);

  // Plus-gewichten komen na min-gewichten
  if (prefix === '+') {
    return value + 1000; // Maak veel groter dan normale gewichten
  } else {
    return value;
  }
}
