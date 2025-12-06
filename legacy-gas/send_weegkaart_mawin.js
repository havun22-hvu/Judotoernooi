function sendWeegkaartMawin() {
  try {
    // Haal judoka gegevens op
    const judokaGegevens = getJudokaGegevens("Mawin van Unen");
    
    if (!judokaGegevens) {
      Logger.log('ERROR: Judoka niet gevonden: Mawin van Unen');
      return { success: false, error: 'Judoka niet gevonden' };
    }
    
    Logger.log('Judoka gevonden: ' + JSON.stringify(judokaGegevens));
    
    // Verstuur email
    const result = verstuurWeegkaartEmail("henkvu@gmail.com", judokaGegevens);
    
    Logger.log('Email verstuurd! Result: ' + JSON.stringify(result));
    return result;
    
  } catch (error) {
    Logger.log('ERROR: ' + error.toString());
    return { success: false, error: error.toString() };
  }
}
