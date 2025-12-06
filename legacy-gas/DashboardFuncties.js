// DashboardFuncties.js - Server-side functies voor het HTML Dashboard
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Opent het toernooi dashboard als sidebar
 */
function openDashboard() {
  const html = HtmlService.createHtmlOutputFromFile('Dashboard')
    .setTitle('WestFries Open Dashboard');

  SpreadsheetApp.getUi().showSidebar(html);
}

/**
 * Importeert een deelnemerslijst via bestandsupload
 * @param {string} base64Data - Base64 encoded bestandsdata
 * @param {string} fileName - Naam van het bestand
 * @return {Object} Resultaat object met success status
 */
function importeerDeelnemerslijstViaUpload(base64Data, fileName) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();

    // Decodeer de base64 data
    const blob = Utilities.newBlob(
      Utilities.base64Decode(base64Data),
      'application/octet-stream',
      fileName
    );

    // Bepaal het bestandstype
    const extension = fileName.split('.').pop().toLowerCase();
    let newSheet;

    if (extension === 'csv') {
      // Importeer CSV
      const csvData = blob.getDataAsString();
      newSheet = ss.insertSheet('ImportedData_temp');
      const rows = Utilities.parseCsv(csvData);
      if (rows.length > 0) {
        newSheet.getRange(1, 1, rows.length, rows[0].length).setValues(rows);
      }
    } else if (extension === 'xlsx' || extension === 'xls') {
      // Voor Excel bestanden moeten we een andere aanpak gebruiken
      // Google Sheets kan niet direct Excel bestanden parsen via Apps Script
      // We gebruiken Drive API om te importeren
      const driveFile = DriveApp.createFile(blob);
      const fileId = driveFile.getId();

      // Converteer naar Google Sheets
      const resource = {
        title: 'ImportedData_temp',
        mimeType: MimeType.GOOGLE_SHEETS
      };

      const convertedFile = Drive.Files.copy(resource, fileId);
      const importedSpreadsheet = SpreadsheetApp.openById(convertedFile.id);
      const sourceSheet = importedSpreadsheet.getSheets()[0];

      // Kopieer data naar huidige spreadsheet
      newSheet = ss.insertSheet('ImportedData_temp');
      const sourceData = sourceSheet.getDataRange().getValues();
      if (sourceData.length > 0) {
        newSheet.getRange(1, 1, sourceData.length, sourceData[0].length).setValues(sourceData);
      }

      // Verwijder tijdelijke bestanden
      DriveApp.getFileById(fileId).setTrashed(true);
      DriveApp.getFileById(convertedFile.id).setTrashed(true);
    } else {
      return {
        success: false,
        error: 'Ongeldig bestandstype. Gebruik Excel (.xlsx, .xls) of CSV (.csv)'
      };
    }

    // Controleer of er al een Deelnemerslijst bestaat en verwijder deze
    const bestaandeDeelnemerslijst = ss.getSheetByName('Deelnemerslijst');
    if (bestaandeDeelnemerslijst) {
      ss.deleteSheet(bestaandeDeelnemerslijst);
    }

    // Hernoem het nieuwe sheet
    newSheet.setName('Deelnemerslijst');

    // Verplaats naar positie 2
    ss.setActiveSheet(newSheet);
    ss.moveActiveSheet(2);

    // Voer automatisch de controle uit
    controleerEnGenereerCodes();

    return {
      success: true,
      message: 'Deelnemerslijst succesvol geïmporteerd en gecontroleerd'
    };

  } catch (error) {
    Logger.log('Fout bij importeren: ' + error.toString());
    return {
      success: false,
      error: error.toString()
    };
  }
}

/**
 * Alternatieve import functie voor CSV bestanden (eenvoudiger)
 * @param {string} base64Data - Base64 encoded CSV data
 * @return {Object} Resultaat object
 */
function importeerCSVDeelnemerslijst(base64Data) {
  try {
    const ss = SpreadsheetApp.getActiveSpreadsheet();

    // Decodeer base64
    const csvContent = Utilities.newBlob(
      Utilities.base64Decode(base64Data)
    ).getDataAsString();

    // Parse CSV
    const rows = Utilities.parseCsv(csvContent);

    if (rows.length === 0) {
      return {
        success: false,
        error: 'Het CSV bestand is leeg'
      };
    }

    // Controleer of er al een Deelnemerslijst bestaat en verwijder deze
    const bestaandeDeelnemerslijst = ss.getSheetByName('Deelnemerslijst');
    if (bestaandeDeelnemerslijst) {
      ss.deleteSheet(bestaandeDeelnemerslijst);
    }

    // Maak nieuw sheet
    const newSheet = ss.insertSheet('Deelnemerslijst');

    // Vul data in
    newSheet.getRange(1, 1, rows.length, rows[0].length).setValues(rows);

    // Verplaats naar positie 2
    ss.setActiveSheet(newSheet);
    ss.moveActiveSheet(2);

    // Voer automatisch de controle uit
    controleerEnGenereerCodes();

    return {
      success: true,
      message: 'CSV succesvol geïmporteerd'
    };

  } catch (error) {
    Logger.log('Fout bij CSV import: ' + error.toString());
    return {
      success: false,
      error: error.toString()
    };
  }
}
