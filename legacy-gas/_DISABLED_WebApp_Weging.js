// WebApp_Weging.js - Aparte web app voor Weeglijst
// Deploy deze als aparte web app

function doGet(e) {
  try {
    Logger.log('Weging web app aangeroepen');

    // Toon direct de weging interface (GEEN blok filtering!)
    return HtmlService.createHtmlOutputFromFile('WebApp_WegingInterface')
      .setTitle('Weging - WestFries Open')
      .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');

  } catch (err) {
    Logger.log('ERROR: ' + err.toString());
    return HtmlService.createHtmlOutput('<h1>Error</h1><p>' + err.toString() + '</p><pre>' + err.stack + '</pre>');
  }
}
