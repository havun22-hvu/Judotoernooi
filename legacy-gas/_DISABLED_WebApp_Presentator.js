// WebApp_Presentator.js - Aparte web app voor Presentator Dashboard
// Deploy deze als aparte web app

function doGet(e) {
  try {
    Logger.log('Presentator web app aangeroepen');

    const action = (e && e.parameter && e.parameter.action) ? e.parameter.action : '';

    // Als action=dashboard, toon dashboard
    if (action === 'dashboard') {
      Logger.log('Toon presentator dashboard');
      return HtmlService.createHtmlOutputFromFile('PresentatorDashboard')
        .setTitle('Presentator Dashboard - WestFries Open')
        .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');
    }

    // Anders: toon login
    Logger.log('Toon presentator login');
    return HtmlService.createHtmlOutputFromFile('PresentatorLogin')
      .setTitle('Presentator Login - WestFries Open')
      .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');

  } catch (err) {
    Logger.log('ERROR: ' + err.toString());
    return HtmlService.createHtmlOutput('<h1>Error</h1><p>' + err.toString() + '</p><pre>' + err.stack + '</pre>');
  }
}
