// PouleUtils.js - Poule hulpfuncties
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Berekent het aantal wedstrijden voor een poule met een bepaald aantal judoka's
 * @param {number} aantalJudokas - Het aantal judoka's in de poule
 * @return {number} Het aantal wedstrijden
 */
function berekenAantalWedstrijden(aantalJudokas) {
  if (aantalJudokas === 3) {
    return 6; // 3 judoka's spelen dubbel tegen elkaar
  } else if (aantalJudokas >= 2) {
    return Math.floor(aantalJudokas * (aantalJudokas - 1) / 2);
  }
  return 0;
}

/**
 * Alias voor berekenAantalWedstrijden (voor backward compatibility)
 * @param {number} aantalJudokas - Het aantal judoka's
 * @return {number} Het aantal wedstrijden
 */
function berekenAantalWedstrijdenVoorPoule(aantalJudokas) {
  return berekenAantalWedstrijden(aantalJudokas);
}

/**
 * Leest alle actieve poules uit een blokblad
 * @param {Sheet} blokBlad - Het blokblad
 * @return {Array} Array met poule-informatie
 */
function leesActievePoules(blokBlad) {
  const data = blokBlad.getDataRange().getValues();
  if (data.length <= 1) return [];

  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");
  const aanwezigIdx = headers.indexOf("Aanwezig");

  if (naamIdx === -1 || pouleNrIdx === -1 || matIdx === -1 || aanwezigIdx === -1) {
    return [];
  }

  const pouleInfo = {};
  const pouleJudokas = {};

  for (let i = 1; i < data.length; i++) {
    const naam = data[i][naamIdx];
    const pouleNr = data[i][pouleNrIdx];
    const matNr = data[i][matIdx];
    const aanwezig = data[i][aanwezigIdx];

    if (!naam || !pouleNr || !matNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    const pouleKey = `${pouleNr}`;

    if (!pouleInfo[pouleKey]) {
      let pouleTitel = "";
      for (let j = 1; j < i; j++) {
        if (data[j][naamIdx] && typeof data[j][naamIdx] === 'string' &&
            data[j][naamIdx].includes("Poule") && data[j][naamIdx].includes(`${pouleNr}`)) {
          pouleTitel = data[j][naamIdx];
          break;
        }
      }

      pouleInfo[pouleKey] = {
        pouleNr: pouleNr,
        matNr: matNr,
        titel: pouleTitel || `Poule ${pouleNr}`,
        aantalJudokas: 0,
        aanwezigJudokas: 0,
        aantalWedstrijden: 0
      };

      pouleJudokas[pouleKey] = [];
    }

    pouleInfo[pouleKey].aantalJudokas++;
    if (aanwezig === "Ja") {
      pouleInfo[pouleKey].aanwezigJudokas++;
    }

    pouleJudokas[pouleKey].push({
      naam: naam,
      aanwezig: aanwezig === "Ja"
    });
  }

  for (const key in pouleInfo) {
    const poule = pouleInfo[key];
    const aanwezigCount = poule.aanwezigJudokas;
    poule.aantalWedstrijden = berekenAantalWedstrijden(aanwezigCount);
  }

  return Object.values(pouleInfo);
}

/**
 * Leest poule-informatie uit een sheet
 * @param {Sheet} sheet - Het sheet met poule-informatie
 * @return {Object} Map van poules met hun informatie
 */
function leesPouleInformatie(sheet) {
  const data = sheet.getDataRange().getValues();
  if (data.length <= 1) return {};

  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const matIdx = headers.indexOf("Mat");
  const blokIdx = headers.indexOf("Blok");
  const aanwezigIdx = headers.indexOf("Aanwezig");
  const pouleTitelIdx = headers.indexOf("Pouletitel");

  if (naamIdx === -1 || pouleNrIdx === -1) {
    return {};
  }

  const pouleMap = {};

  for (let i = 1; i < data.length; i++) {
    const naam = data[i][naamIdx];
    const pouleNr = data[i][pouleNrIdx];
    const matNr = matIdx !== -1 ? data[i][matIdx] : null;
    const blokNr = blokIdx !== -1 ? data[i][blokIdx] : null;
    const aanwezig = aanwezigIdx !== -1 ? data[i][aanwezigIdx] : "Ja";
    const pouleTitel = pouleTitelIdx !== -1 ? data[i][pouleTitelIdx] : "";

    if (!naam || !pouleNr) continue;
    if (typeof naam === 'string' && (naam.startsWith("MAT ") || naam.includes("Poule"))) continue;

    const pouleKey = `${pouleNr}`;

    if (!pouleMap[pouleKey]) {
      // Extraheer leeftijdsklasse en gewichtsklasse uit de pouleTitel
      let leeftijdsklasse = "";
      let gewichtsklasse = "";

      if (pouleTitel) {
        const match = pouleTitel.match(/(.+?)\s+([+-]?\d+\s+kg)/i);
        if (match) {
          leeftijdsklasse = match[1].trim();
          gewichtsklasse = match[2].trim();
        }
      }

      pouleMap[pouleKey] = {
        pouleNr: pouleNr,
        blokNr: blokNr,
        matNr: matNr,
        titel: pouleTitel || `Poule ${pouleNr}`,
        leeftijdsklasse: leeftijdsklasse,
        gewichtsklasse: gewichtsklasse,
        judokas: [],
        aantalWedstrijden: 0
      };
    }

    pouleMap[pouleKey].judokas.push({
      naam: naam,
      aanwezig: aanwezig === "Ja",
      verplaatst: false
    });
  }

  // Bereken wedstrijden per poule
  for (const key in pouleMap) {
    const poule = pouleMap[key];
    const actieveJudokas = poule.judokas.filter(j => j.aanwezig && !j.verplaatst);
    poule.aantalWedstrijden = berekenAantalWedstrijden(actieveJudokas.length);
    poule.actieveJudokas = actieveJudokas.length;
  }

  return pouleMap;
}

/**
 * Leest alle poules en details uit het PouleIndeling tabblad
 * @param {Sheet} sheet - Het PouleIndeling tabblad
 * @return {Array} Array met poule-informatie
 */
function leesPouleDetails(sheet) {
  const lastRow = sheet.getLastRow();
  const data = sheet.getRange(1, 1, lastRow, 13).getValues();

  const headers = data[0];
  const naamIdx = headers.indexOf("Naam");
  const pouleNrIdx = headers.indexOf("Poule-nr");
  const pouleTitelIdx = headers.indexOf("Pouletitel");

  if (naamIdx === -1 || pouleNrIdx === -1 || pouleTitelIdx === -1) return [];

  const poules = {};

  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    const pouleNr = row[pouleNrIdx];
    const pouleTitel = row[pouleTitelIdx];

    if (!pouleNr || !pouleTitel) continue;

    if (!poules[pouleNr]) {
      let gewichtsklasse = "";
      const match = pouleTitel.match(/(.+?) ([\-\+][0-9]+ kg|[0-9]+ kg)/i);
      if (match && match.length >= 3) {
        const leeftijdsklasse = match[1].trim();
        const gewichtStr = match[2].trim();
        gewichtsklasse = `${leeftijdsklasse} ${gewichtStr}`;
      } else {
        gewichtsklasse = "Onbekend";
      }

      poules[pouleNr] = {
        pouleNr: pouleNr,
        titel: pouleTitel,
        gewichtsklasse: gewichtsklasse,
        judokaCount: 0
      };
    }

    poules[pouleNr].judokaCount++;
  }

  const result = [];
  for (const pouleNr in poules) {
    const poule = poules[pouleNr];
    const wedstrijden = berekenAantalWedstrijden(poule.judokaCount);

    result.push({
      pouleNr: parseInt(pouleNr),
      titel: poule.titel,
      gewichtsklasse: poule.gewichtsklasse,
      judokaCount: poule.judokaCount,
      wedstrijden: wedstrijden
    });
  }

  return result;
}

/**
 * Leest gewichtsklassen en aantal wedstrijden uit het PouleIndeling tabblad
 * @param {Sheet} sheet - Het PouleIndeling tabblad
 * @return {Array} Array met gewichtsklasse-informatie
 */
function leesGewichtsklassenEnWedstrijden(sheet) {
  const lastRow = sheet.getLastRow();
  const lastCol = sheet.getLastColumn();

  if (lastCol < 15) return [];

  const data = sheet.getRange(2, 14, lastRow - 1, 2).getValues();
  const result = [];

  for (let i = 0; i < data.length; i++) {
    const [gewichtsklasse, wedstrijden] = data[i];

    // Filter lege regels, TOTAAL en header regels
    if (!gewichtsklasse || gewichtsklasse === "" || gewichtsklasse === "TOTAAL") continue;

    // Filter de header regel "Leeftijdsklasse/Gewichtsklasse"
    if (typeof gewichtsklasse === 'string' &&
        (gewichtsklasse.includes("Leeftijdsklasse") || gewichtsklasse === "Gewichtsklasse")) continue;

    result.push({
      gewichtsklasse: gewichtsklasse,
      wedstrijden: parseInt(wedstrijden) || 0
    });
  }

  return result;
}

/**
 * Zoekt het bloknummer waarin een poule zich bevindt
 * @param {number} pouleId - Het poulenummer
 * @return {number|null} Het bloknummer of null
 */
function vindBloknummerVoorPoule(pouleId) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Controleer eerst in de PouleIndeling
  const poulesSheet = ss.getSheetByName('PouleIndeling');
  if (poulesSheet) {
    const data = poulesSheet.getDataRange().getValues();
    const headers = data[0];
    const pouleNrIdx = headers.indexOf("Poule-nr");
    const blokIdx = headers.indexOf("Blok");

    if (pouleNrIdx !== -1 && blokIdx !== -1) {
      for (let i = 1; i < data.length; i++) {
        if (data[i][pouleNrIdx] === pouleId && data[i][blokIdx]) {
          return data[i][blokIdx];
        }
      }
    }
  }

  // Anders zoek in blokbladen
  for (let blokNr = 1; blokNr <= 6; blokNr++) {
    const blokSheet = ss.getSheetByName(`Blok ${blokNr}`);
    if (!blokSheet) continue;

    const data = blokSheet.getDataRange().getValues();
    const headers = data[0];
    const pouleNrIdx = headers.indexOf("Poule-nr");

    if (pouleNrIdx !== -1) {
      for (let i = 1; i < data.length; i++) {
        if (data[i][pouleNrIdx] === pouleId) {
          return blokNr;
        }
      }
    }
  }

  return null;
}
