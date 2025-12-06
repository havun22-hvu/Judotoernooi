// VerplaatsingUtils.js - Judoka en poule verplaatsings hulpfuncties
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Vindt alle verplaatste judoka's uit de blokbladen
 * @return {Array} Array met verplaatste judoka's
 */
function vindVerplaatsteJudokas() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const verplaatsteJudokas = [];

  for (let blokNr = 1; blokNr <= 6; blokNr++) {
    const blokBlad = ss.getSheetByName(`Blok ${blokNr}`);
    if (!blokBlad) continue;

    const data = blokBlad.getDataRange().getValues();
    const headers = data[0];

    const naamIdx = headers.indexOf("Naam");
    const pouleNrIdx = headers.indexOf("Poule-nr");
    const opmerkingenIdx = headers.indexOf("Opmerkingen");
    const aanwezigIdx = headers.indexOf("Aanwezig");

    if (naamIdx === -1 || pouleNrIdx === -1 || opmerkingenIdx === -1) continue;

    for (let i = 1; i < data.length; i++) {
      const row = data[i];
      const naam = row[naamIdx];
      const pouleNr = row[pouleNrIdx];
      const opmerkingen = row[opmerkingenIdx] || "";
      const aanwezig = aanwezigIdx !== -1 ? row[aanwezigIdx] : "";

      if (!naam || !pouleNr) continue;
      if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

      const verplaatsMatch = opmerkingen.match(/Verplaatst van poule (\d+) naar poule (\d+)/);
      if (verplaatsMatch) {
        verplaatsteJudokas.push({
          naam: naam,
          vanPoule: parseInt(verplaatsMatch[1]),
          naarPoule: parseInt(verplaatsMatch[2]),
          aanwezig: aanwezig === "Ja",
          blokNr: blokNr
        });
      }
    }
  }

  return verplaatsteJudokas;
}

/**
 * Update poule-informatie met verplaatsingen
 * @param {Object} pouleMap - Map van poules
 * @param {Array} verplaatsteJudokas - Array met verplaatste judoka's
 */
function updatePouleMapMetVerplaatsingen(pouleMap, verplaatsteJudokas) {
  for (const verplaatsing of verplaatsteJudokas) {
    // Markeer judoka als verplaatst in de oude poule
    const vanPouleKey = `${verplaatsing.vanPoule}`;
    if (pouleMap[vanPouleKey]) {
      const index = pouleMap[vanPouleKey].judokas.findIndex(j =>
        j.naam === verplaatsing.naam && !j.verplaatst
      );
      if (index !== -1) {
        pouleMap[vanPouleKey].judokas[index].verplaatst = true;
      }
    }

    // Voeg judoka toe aan nieuwe poule
    const naarPouleKey = `${verplaatsing.naarPoule}`;
    if (pouleMap[naarPouleKey]) {
      const bestaatAl = pouleMap[naarPouleKey].judokas.some(j => j.naam === verplaatsing.naam);
      if (!bestaatAl) {
        pouleMap[naarPouleKey].judokas.push({
          naam: verplaatsing.naam,
          aanwezig: verplaatsing.aanwezig,
          verplaatst: false
        });
      }
    }
  }

  // Herbereken wedstrijden voor beide poules
  for (const key in pouleMap) {
    const poule = pouleMap[key];
    const actieveJudokas = poule.judokas.filter(j => !j.verplaatst && j.aanwezig);
    poule.aantalWedstrijden = berekenAantalWedstrijden(actieveJudokas.length);
    poule.actieveJudokas = actieveJudokas.length;
  }
}

/**
 * Update poule-informatie voor afwezige judoka's
 * @param {Object} pouleMap - Map van poules
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

  const aangepastePoulesSet = new Set();

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const naam = row[naamIdx];
    const pouleNr = row[pouleNrIdx];
    const aanwezig = row[aanwezigIdx];

    if (!naam || !pouleNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    if (aanwezig === "Nee") {
      if (pouleMap[pouleNr]) {
        const index = pouleMap[pouleNr].judokas.findIndex(j => j.naam === naam && !j.verplaatst);
        if (index !== -1) {
          pouleMap[pouleNr].judokas[index].aanwezig = false;
          aangepastePoulesSet.add(pouleNr);
        }
      }
    }
  }

  // Herbereken wedstrijden
  for (const pouleNr of aangepastePoulesSet) {
    const poule = pouleMap[pouleNr];
    const actieveJudokas = poule.judokas.filter(j => !j.verplaatst && j.aanwezig);
    poule.aantalWedstrijden = berekenAantalWedstrijden(actieveJudokas.length);
    poule.actieveJudokas = actieveJudokas.length;
  }
}

/**
 * Herberekent de nummering van judoka's binnen elke poule
 * @param {Sheet} sheet - Het sheet om te hernummeren
 */
function hernummerJudokasPerPoule(sheet) {
  const lastRow = sheet.getLastRow();
  const data = sheet.getDataRange().getValues();

  if (lastRow <= 1) return;

  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const nrIdx = headers.indexOf("Nr");
  const pouleNrIdx = headers.indexOf("Poule-nr");

  if (naamIdx === -1 || nrIdx === -1 || pouleNrIdx === -1) return;

  const pouleMap = {};

  for (let i = 1; i < data.length; i++) {
    const naam = data[i][naamIdx];
    const pouleNr = data[i][pouleNrIdx];

    if (!naam || !pouleNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    if (!pouleMap[pouleNr]) {
      pouleMap[pouleNr] = {
        judokas: []
      };
    }

    pouleMap[pouleNr].judokas.push({
      row: i + 1,
      naam: naam
    });
  }

  for (const [pouleNr, poule] of Object.entries(pouleMap)) {
    for (let i = 0; i < poule.judokas.length; i++) {
      const judoka = poule.judokas[i];
      const nieuwNummer = i + 1;
      sheet.getRange(judoka.row, nrIdx + 1).setValue(nieuwNummer);
    }
  }
}

/**
 * Update het matnummer van een poule in het PouleIndeling blad
 * @param {number} pouleId - Het poulenummer
 * @param {number} nieuweMat - Het nieuwe matnummer
 * @return {boolean} True als succesvol
 */
function updatePouleMatInPouleindeling(pouleId, nieuweMat) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName('PouleIndeling');

  if (!poulesSheet) return false;

  const data = poulesSheet.getDataRange().getValues();
  const headers = data[0];
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");

  if (pouleNrIdx === -1 || matIdx === -1) return false;

  let updated = false;
  for (let i = 1; i < data.length; i++) {
    if (data[i][pouleNrIdx] === pouleId) {
      poulesSheet.getRange(i + 1, matIdx + 1).setValue(nieuweMat);
      updated = true;
    }
  }

  return updated;
}

/**
 * Update het matnummer van een poule in een blokblad
 * @param {number} pouleId - Het poulenummer
 * @param {number} nieuweMat - Het nieuwe matnummer
 * @param {number} blokNr - Het bloknummer
 * @return {boolean} True als succesvol
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
  for (let i = 1; i < data.length; i++) {
    if (data[i][pouleNrIdx] === pouleId) {
      blokSheet.getRange(i + 1, matIdx + 1).setValue(nieuweMat);
      updated = true;
    }
  }

  return updated;
}

/**
 * Helper functie om de weeglijst bij te werken met verplaatsingsinformatie
 * @param {Spreadsheet} ss - De actieve spreadsheet
 * @param {Array} selectedJudokas - Array van geselecteerde judokas met naam en huidigePoule
 * @param {number} doelPoule - Het doel poule nummer
 */
function updateWeeglijstMetVerplaatsingsInfo(ss, selectedJudokas, doelPoule) {
  const weeglijstSheet = ss.getSheetByName('Weeglijst');
  if (!weeglijstSheet) return;

  const weegData = weeglijstSheet.getDataRange().getValues();
  const weegHeaders = weegData[0];
  const weegNaamIdx = weegHeaders.indexOf("Naam");
  const weegOpmerkingenIdx = weegHeaders.indexOf("Opmerkingen");

  if (weegNaamIdx === -1 || weegOpmerkingenIdx === -1) return;

  for (const judoka of selectedJudokas) {
    for (let i = 1; i < weegData.length; i++) {
      if (weegData[i][weegNaamIdx] === judoka.naam) {
        let huidigeOpmerking = weegData[i][weegOpmerkingenIdx] || "";
        let nieuweOpmerking = `Verplaatst van poule ${judoka.huidigePoule} naar poule ${doelPoule}`;

        if (huidigeOpmerking) {
          nieuweOpmerking = huidigeOpmerking + "; " + nieuweOpmerking;
        }

        weeglijstSheet.getRange(i + 1, weegOpmerkingenIdx + 1).setValue(nieuweOpmerking);
        break;
      }
    }
  }
}
