// Test script om een weegkaart te versturen
function testVerstuurWeegkaart() {
  const naam = "Mawin van Unen";
  const emailAdres = "henkvu@gmail.com";
  
  Logger.log('Versturen weegkaart voor: ' + naam);
  Logger.log('Naar email: ' + emailAdres);
  
  const result = verstuurWeegkaart(naam, emailAdres);
  
  Logger.log('Result: ' + JSON.stringify(result));
  return result;
}
