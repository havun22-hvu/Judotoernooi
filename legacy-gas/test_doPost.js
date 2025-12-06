/**
 * Test functie om doPost te testen vanuit de Script Editor
 */
function testDoPost() {
  // Simuleer een POST request
  const e = {
    parameter: {
      action: 'zoekJudokaViaQR',
      qrData: '{"naam":"Test Judoka","gewichtsklasse":"-36kg","blok":5}'
    }
  };

  const result = doPost(e);
  const content = result.getContent();

  Logger.log('=== TEST doPost ===');
  Logger.log('Response Content-Type: ' + result.getMimeType());
  Logger.log('Response Body:');
  Logger.log(content);

  try {
    const parsed = JSON.parse(content);
    Logger.log('Parsed JSON:');
    Logger.log(JSON.stringify(parsed, null, 2));
  } catch (error) {
    Logger.log('ERROR: Kan JSON niet parsen: ' + error.message);
  }
}

/**
 * Test zoekJudokaViaQR direct
 */
function testZoekJudokaViaQR() {
  Logger.log('=== TEST zoekJudokaViaQR ===');

  // Test met JSON format
  const qrData = '{"naam":"Mawin van Unen","gewichtsklasse":"-36kg","blok":5}';
  const result = zoekJudokaViaQR(qrData);

  Logger.log('Input: ' + qrData);
  Logger.log('Result:');
  Logger.log(JSON.stringify(result, null, 2));
}
