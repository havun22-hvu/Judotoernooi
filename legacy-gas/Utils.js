// Utils.js - Algemene hulpfuncties
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * BELANGRIJK: Dit bestand is grotendeels leeg omdat de meeste functies
 * direct in andere bestanden worden gebruikt zonder dependencies.
 *
 * Apps Script laadt bestanden in alfabetische volgorde, dus functies
 * die overal nodig zijn worden nu direct inline gebruikt:
 * - SpreadsheetApp.getActiveSpreadsheet() in plaats van getSpreadsheet()
 * - Direct "ToernooiConfig" string in plaats van getTabNames().CONFIG
 */

/**
 * Hulpfunctie om het huidige jaartal te krijgen
 * @return {number} Het huidige jaartal
 */
function getCurrentYear() {
  return new Date().getFullYear();
}

/**
 * Controleert of een tabblad bestaat
 * @param {string} sheetName - Naam van het tabblad
 * @return {boolean} True als het tabblad bestaat, anders false
 */
function sheetExists(sheetName) {
  return SpreadsheetApp.getActiveSpreadsheet().getSheetByName(sheetName) !== null;
}

/**
 * Hulpfunctie om de laatste rij met data te vinden
 * @param {Sheet} sheet - Het tabblad
 * @return {number} Het rijnummer van de laatste rij met data
 */
function getLastRowWithData(sheet) {
  const values = sheet.getDataRange().getValues();
  for (let i = values.length - 1; i >= 0; i--) {
    if (values[i].some(cell => cell !== '')) {
      return i + 1;
    }
  }
  return 0;
}
