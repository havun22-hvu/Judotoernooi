// WebApp_Mat.js - Aparte web app voor Mat Beheer
// Deploy deze als aparte web app

function doGet(e) {
  try {
    Logger.log('Mat web app aangeroepen');

    const blokken = getBeschikbareBlokken();
    const matten = getBeschikbareMatten();

    const template = HtmlService.createTemplateFromFile('WebApp_MatInterface');
    template.blokken = blokken;
    template.matten = matten;

    return template.evaluate()
      .setTitle('Mat Beheer - WestFries Open')
      .setFaviconUrl('https://www.gstatic.com/images/branding/product/1x/sheets_48dp.png');

  } catch (err) {
    Logger.log('ERROR: ' + err.toString());
    return HtmlService.createHtmlOutput('<h1>Error</h1><p>' + err.toString() + '</p><pre>' + err.stack + '</pre>');
  }
}
