// Voorbereiding.gs - Functies voor toernooi configuratie en deelnemerslijst
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Zet het configuratieblad op met aangepaste leeftijds- en gewichtsklasse sectie
 * en gewichtstoleratie configuratie
 */
function setupConfigSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");
  
  // Reset het tabblad
  configSheet.clear();
  
  // Stel het formaat in
  configSheet.setColumnWidth(1, 200);
  configSheet.setColumnWidth(2, 300);
  configSheet.setColumnWidth(3, 500);
  configSheet.setColumnWidth(4, 150); // Extra kolom voor prioriteit
  
  // Titel
  configSheet.getRange("A1:D1").merge();
  configSheet.getRange("A1").setValue("WestFries Open JudoToernooi - Configuratie")
    .setFontWeight("bold")
    .setFontSize(14)
    .setHorizontalAlignment("center");

  // Toernooi instellingen
  configSheet.getRange("A3").setValue("TOERNOOI INSTELLINGEN").setFontWeight("bold");

  const configItems = [
    ["Toernooinaam", "WestFries Open JudoToernooi", ""],
    ["Datum", new Date().toLocaleDateString(), ""],
    ["Locatie", "", ""],
    ["Organisator", "Judoschool Cees Veen", ""],
    ["Contactpersoon", "", ""],
    ["", "", ""],
    ["MATTEN CONFIGURATIE", "", ""],
    ["Aantal matten", "7", ""],
    ["", "", ""],
    ["TIJDSBLOKKEN CONFIGURATIE", "", ""],
    ["Aantal tijdsblokken", "6", ""],
    ["Tijdsblok 1", "", ""],
    ["Tijdsblok 2", "", ""],
    ["Tijdsblok 3", "", ""],
    ["Tijdsblok 4", "", ""],
    ["Tijdsblok 5", "", ""],
    ["Tijdsblok 6", "", ""],
    ["", "", ""],
    ["POULE INSTELLINGEN", "", ""],
    ["Minimaal aantal judoka's per poule", "3", ""],
    ["Optimaal aantal judoka's per poule", "5", ""],
    ["Maximaal aantal judoka's per poule", "6", ""],
    ["", "", ""],
    ["GEWICHTSTOLERATIE", "", ""],
    ["Toleratiemarge voor gewichtsklassen (kg)", "0.5", "Standaard 0.5 kg (gebruik 0.3 voor strikter beleid)"]
  ];
  
  // Voeg configuratie-items toe
  for (let i = 0; i < configItems.length; i++) {
    configSheet.getRange(i + 4, 1).setValue(configItems[i][0]);
    configSheet.getRange(i + 4, 2).setValue(configItems[i][1]);
    configSheet.getRange(i + 4, 3).setValue(configItems[i][2]);
  }
  
  // Voeg de sectie voor gewichtstoleratie toe met een opvallende kleur
  configSheet.getRange(4 + 24, 1, 1, 3).setBackground("#FFEB3B"); // Gele highlight voor gewichtstoleratie
  
  // Rest van de functie blijft hetzelfde...
  
  // Voeg deelnemerslijst instructies toe
  const instructieRowIndex = configItems.length + 6;
  configSheet.getRange(instructieRowIndex, 1).setValue("DEELNEMERSLIJST IMPORTEREN").setFontWeight("bold");
  
  const importInstructies = [
    "1. Importeer je deelnemerslijst via het Google Sheets menu: Bestand > Importeren",
    "2. Selecteer of upload je bestand met de deelnemersgegevens",
    "3. Kies voor 'Nieuw werkblad maken' of 'Bestaand werkblad vervangen'",
    "4. Noem het werkblad 'Deelnemerslijst'",
    "5. De lijst moet de volgende kolommen bevatten: Naam, Band, Club, Gewichtsklasse, Geslacht, Geboortejaar"
  ];
  
  for (let i = 0; i < importInstructies.length; i++) {
    configSheet.getRange(instructieRowIndex + i + 1, 1, 1, 4).merge();
    configSheet.getRange(instructieRowIndex + i + 1, 1).setValue(importInstructies[i]);
  }
  
  // Voeg leeftijds- en gewichtsklasse informatie toe - nu bewerkbaar
  const infoRowIndex = instructieRowIndex + importInstructies.length + 2;
  configSheet.getRange(infoRowIndex, 1).setValue("LEEFTIJDS- EN GEWICHTSKLASSEN").setFontWeight("bold");
  
  const leeftijdsklassen = [
    ["Mini's", "Jonger dan 8 jaar", "Gewichtsklassen: -20 kg, -23 kg, -26 kg, -29 kg, +29 kg"],
    ["A-pupillen", "Jonger dan 10 jaar", "Gewichtsklassen: -24 kg, -27 kg, -30 kg, -34 kg, -38 kg, +38 kg"],
    ["B-pupillen", "Jonger dan 12 jaar", "Gewichtsklassen: -27 kg, -30 kg, -34 kg, -38 kg, -42 kg, -46 kg, -50 kg, +50 kg"],
    ["Dames -15", "Jonger dan 15 jaar", "Gewichtsklassen: -36 kg, -40 kg, -44 kg, -48 kg, -52 kg, -56 kg, -63 kg, +63 kg"],
    ["Heren -15", "Jonger dan 15 jaar", "Gewichtsklassen: -34 kg, -38 kg, -42 kg, -46 kg, -50 kg, -55 kg, -60 kg, -66 kg, +66 kg"]
  ];
  
  for (let i = 0; i < leeftijdsklassen.length; i++) {
    configSheet.getRange(infoRowIndex + i + 1, 1).setValue(leeftijdsklassen[i][0]);
    configSheet.getRange(infoRowIndex + i + 1, 2).setValue(leeftijdsklassen[i][1]);
    configSheet.getRange(infoRowIndex + i + 1, 3).setValue(leeftijdsklassen[i][2]);
  }
  
  // Voeg band combinatieregels toe
  const bandRowIndex = infoRowIndex + leeftijdsklassen.length + 2;
  configSheet.getRange(bandRowIndex, 1).setValue("BAND COMBINATIEREGELS").setFontWeight("bold");
  
  const bandCombinaties = [
    "Mini's: Alle banden samen",
    "A-pupillen: Witte banden apart; geel en hoger samen",
    "B-pupillen: Wit en geel samen; oranje en hoger samen",
    "Dames -15: Wit t/m groen samen; blauw en bruin samen",
    "Heren -15: Wit t/m groen samen; blauw en bruin samen"
  ];
  
  for (let i = 0; i < bandCombinaties.length; i++) {
    configSheet.getRange(bandRowIndex + i + 1, 1, 1, 4).merge();
    configSheet.getRange(bandRowIndex + i + 1, 1).setValue(bandCombinaties[i]);
  }

  // OPMERKING: Protection en laadConfiguratie kunnen problemen geven bij initializeToernooi
  // Deze worden overgeslagen voor snellere setup

  // Beveilig belangrijke cellen maar houd de configuratiedelen bewerkbaar
  // const protection = configSheet.protect().setDescription('Beschermde toelichting');
  // protection.removeEditors(protection.getEditors());
  // protection.addEditor(Session.getEffectiveUser());

  // Laad de configuratie meteen
  // if (typeof laadConfiguratie === 'function') {
  //   laadConfiguratie();
  // }
}

/**
 * Interne functie om tabbladen aan te maken zonder UI meldingen
 * Wordt gebruikt door initializeToernooi() voor gestroomlijnde setup
 */
function _createTabsInternal() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");

  if (!configSheet) return;

  const aantalMatten = configSheet.getRange("B11").getValue() || 7;
  const aantalBlokken = configSheet.getRange("B14").getValue() || 6;

  const teBehoudenTabs = ["ToernooiConfig"];
  const teMakenTabs = [
    "Deelnemerslijst",
    "PouleIndeling",
    "Blok/Mat verdeling",
    "ZaalOverzicht"
  ];

  const alleSheets = ss.getSheets();

  // Verwijder bestaande tabbladen die we opnieuw willen maken
  for (let i = alleSheets.length - 1; i >= 0; i--) {
    const sheetNaam = alleSheets[i].getName();
    if (teBehoudenTabs.includes(sheetNaam)) continue;

    if (teMakenTabs.includes(sheetNaam) ||
        sheetNaam.match(/^Blok \d+$/) ||
        sheetNaam.match(/^Wedstrijdschema's Blok \d+$/) ||
        sheetNaam === "Dashboard") {
      ss.deleteSheet(alleSheets[i]);
    }
  }

  // Maak alle tabbladen aan
  for (const tabNaam of teMakenTabs) {
    if (!ss.getSheetByName(tabNaam)) {
      ss.insertSheet(tabNaam);
    }
  }

  for (let i = 1; i <= aantalBlokken; i++) {
    if (!ss.getSheetByName(`Blok ${i}`)) {
      ss.insertSheet(`Blok ${i}`);
    }
  }

  if (!ss.getSheetByName("Dashboard")) {
    ss.insertSheet("Dashboard");
  }
}

/**
 * Maakt mat en tijdsblok tabbladen aan (met gebruikersinteractie)
 * Voor handmatig gebruik via het menu
 */
function maakMatEnTijdsblokBladen() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");

  if (!configSheet) {
    ui.alert(
      'Fout',
      'Het tabblad "ToernooiConfig" bestaat niet. Maak eerst het configuratieblad aan.',
      ui.ButtonSet.OK
    );
    return;
  }

  const aantalMatten = configSheet.getRange("B11").getValue() || 7;
  const aantalBlokken = configSheet.getRange("B14").getValue() || 6;

  const response = ui.alert(
    'Tabbladen aanmaken',
    `Wil je alle benodigde tabbladen aanmaken voor ${aantalBlokken} tijdsblokken? Bestaande tabbladen met dezelfde naam worden verwijderd.`,
    ui.ButtonSet.YES_NO
  );

  if (response !== ui.Button.YES) {
    return;
  }

  // Gebruik de interne functie
  _createTabsInternal();

  ui.alert(
    'Tabbladen aangemaakt',
    `Alle benodigde tabbladen zijn aangemaakt voor ${aantalBlokken} tijdsblokken. U kunt nu verder gaan met het importeren van de deelnemerslijst.`,
    ui.ButtonSet.OK
  );
}

function corrigeerNaamHoofdletters(naam) {
  if (!naam) return naam;
  
  // Lijst met Nederlandse tussenvoegsels
  const tussenvoegsels = ["van", "de", "der", "den", "het", "in", "ter", "ten", "te", "la", "le", "les", "op", "'t", "s", "t", "aan", "bij", "onder", "voor", "over", "tot"];
  
  // Splits de naam in woorden
  const woorden = naam.toString().trim().split(/\s+/);
  const aantalWoorden = woorden.length;
  
  // Nieuwe array voor gecorrigeerde woorden
  const gecorrigeerdeWoorden = [];
  
  // Verwerk elk woord
  for (let i = 0; i < aantalWoorden; i++) {
    let woord = woorden[i];
    if (!woord) continue;
    
    // Zet woord om naar lowercase voor controle
    const lowerWoord = woord.toLowerCase();
    
    // Check of het een tussenvoegsel is
    const isTussenvoegsel = tussenvoegsels.includes(lowerWoord);
    
    // Elk woord krijgt een hoofdletter behalve tussenvoegsels in het midden
    let moetkrijgenHoofdletter = true;
    if (isTussenvoegsel && i > 0 && i < aantalWoorden - 1) {
      moetkrijgenHoofdletter = false;
    }
    
    // Zet de eerste letter in hoofdletter of alles in lowercase
    if (moetkrijgenHoofdletter && lowerWoord.length > 0) {
      woord = lowerWoord.charAt(0).toUpperCase() + lowerWoord.slice(1);
    } else {
      woord = lowerWoord;
    }
    
    gecorrigeerdeWoorden.push(woord);
  }
  
  return gecorrigeerdeWoorden.join(' ');
}

/**
 * Functie om judoka-codes te genereren
 * Wordt aangeroepen nadat de gebruiker de deelnemerslijst heeft geïmporteerd
 */
function controleerEnGenereerCodes() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();
  
  // Controleer of het Deelnemerslijst tabblad bestaat
  if (!sheetExists("Deelnemerslijst")) {
    ui.alert(
      'Deelnemerslijst niet gevonden',
      'Er is geen tabblad met de naam "Deelnemerslijst". Importeer eerst je deelnemerslijst en zorg dat het tabblad "Deelnemerslijst" heet.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  const deelnemersSheet = ss.getSheetByName("Deelnemerslijst");
  
  // Controleer of er headers zijn
  const headers = deelnemersSheet.getRange(1, 1, 3, 10).getValues();
  let headerRow = -1;
  
  // Zoek headerrij
  for (let i = 0; i < headers.length; i++) {
    if (headers[i].some(cell => typeof cell === 'string' && cell.toString().toLowerCase().includes('naam'))) {
      headerRow = i + 1; // +1 omdat rij-index 0-based is maar getRange 1-based
      break;
    }
  }
  
  // Als geen headerrij gevonden, neem aan dat het rij 1 is
  if (headerRow === -1) {
    headerRow = 1;
  }
  
  // Controleer of er deelnemers zijn
  const lastRow = getLastRowWithData(deelnemersSheet);
  if (lastRow <= headerRow) {
    ui.alert(
      'Geen deelnemers gevonden',
      'Er zijn geen deelnemers gevonden in het deelnemerslijst tabblad. ' +
      'Importeer eerst je deelnemerslijst.',
      ui.ButtonSet.OK
    );
    return;
  }
  
  // Deelnemersgegevens ophalen - begin vanaf de rij na de headerrij
  const dataRange = deelnemersSheet.getRange(headerRow + 1, 1, lastRow - headerRow, 6);
  const data = dataRange.getValues();
  
  // Controleer gegevens en markeer problemen
  const problemen = controleDeelnemersGegevens(deelnemersSheet, data, headerRow + 1);
  
  // Nieuwe data ophalen na mogelijke correcties
  const updatedData = deelnemersSheet.getRange(headerRow + 1, 1, lastRow - headerRow, 6).getValues();
  
  // Genereer altijd judoka-codes, ook als er problemen waren
  genereerJudokaCodes(deelnemersSheet, updatedData, headerRow + 1);
  
  // Sorteer de deelnemerslijst op leeftijdsklasse, gewicht en naam
  sorterenDeelnemerslijst(deelnemersSheet);
  
  // Automatisch aanpassen van kolombreedtes
  deelnemersSheet.autoResizeColumns(1, 10);
  
  // Toon resultaat aan gebruiker
  if (problemen.length > 0) {
    ui.alert(
      'Deelnemerslijst gecontroleerd met problemen',
      'Er zijn ' + problemen.length + ' problemen gevonden en (indien mogelijk) automatisch gecorrigeerd.\n\n' +
      problemen.slice(0, 10).join('\n') + 
      (problemen.length > 10 ? '\n\n... en ' + (problemen.length - 10) + ' meer problemen.' : '') +
      '\n\nJudoka-codes zijn gegenereerd voor alle deelnemers.',
      ui.ButtonSet.OK
    );
  } else {
    ui.alert(
      'Deelnemerslijst gecontroleerd',
      'De deelnemerslijst is succesvol gecontroleerd en alle judoka-codes zijn gegenereerd.',
      ui.ButtonSet.OK
    );
  }
}

/**
* Controleer de deelnemersgegevens op problemen
* @param {Sheet} sheet - Het deelnemerslijst tabblad
* @param {Array} data - 2D array met deelnemersgegevens
* @param {number} startRow - Rijnummer waar de data begint
* @return {Array} Een array met beschrijvingen van gevonden problemen
*/
function controleDeelnemersGegevens(sheet, data, startRow) {
 const problemen = [];
 const bandenObj = getBanden();
 const banden = Object.keys(bandenObj);
 const huidigJaar = getCurrentYear();
 
 // Reset cellkleuren
 if (data.length > 0) {
   sheet.getRange(startRow, 1, data.length, 6).setBackground(null);
 }
 
 for (let i = 0; i < data.length; i++) {
   const row = i + startRow; // Rijnummer in de sheet
   const [naam, bandInput, club, gewicht, geslacht, geboortejaar] = data[i];
   
   // Overslaan als alle cellen leeg zijn (lege rij)
   if (!naam && !bandInput && !club && !gewicht && !geslacht && !geboortejaar) {
     continue;
   }
   
   // Controleer naam (leeg of niet hoofdletter)
   if (!naam || naam.trim() === '') {
     sheet.getRange(row, 1).setBackground('#F4CCCC'); // Rood
     problemen.push(`Rij ${row}: Naam ontbreekt`);
   } else {
     const gecorrigeerdeNaam = corrigeerNaamHoofdletters(naam);
     
     // Controleer of er daadwerkelijk een correctie nodig is
     if (gecorrigeerdeNaam !== naam) {
       sheet.getRange(row, 1).setValue(gecorrigeerdeNaam);
       sheet.getRange(row, 1).setBackground('#FCE5CD'); // Oranje
       problemen.push(`Rij ${row}: Naam "${naam}" is gecorrigeerd naar "${gecorrigeerdeNaam}"`);
     }
   }
   
   // Controleer band (moet ingevuld zijn met geldige waarde)
   if (!bandInput || bandInput.trim() === '') {
     // Vul automatisch 'onbekend' in als de band ontbreekt
     sheet.getRange(row, 2).setValue('onbekend');
     sheet.getRange(row, 2).setBackground('#FCE5CD'); // Oranje om aan te geven dat er een correctie is gemaakt
     problemen.push(`Rij ${row}: Band ontbreekt - automatisch ingevuld als 'onbekend'`);
   } else {
     // Functie om bandkleur te herkennen uit verschillende notaties
     const herkenBand = (bandStr) => {
       // Maak lowercase en verwijder spaties aan begin/eind
       bandStr = bandStr.toLowerCase().trim();
       
       // Direct match voor eenvoudige gevallen, inclusief "onbekend"
       if (bandStr in bandenObj) {
         return bandStr;
       }
       
       // Check voor formaten als "Groen (3 kyu)" of variaties
       for (const band in bandenObj) {
         if (bandStr.startsWith(band)) {
           return band;
         }
       }
       
       // Als niets matcht, retourneer null
       return null;
     };
     
     // Controleer band en markeer als het ongeldig is
     const herkendeband = herkenBand(bandInput);
     if (herkendeband === null) {
       // Bij ongeldige band, vul 'onbekend' in
       sheet.getRange(row, 2).setValue('onbekend');
       sheet.getRange(row, 2).setBackground('#FCE5CD'); // Oranje
       problemen.push(`Rij ${row}: Ongeldige band "${bandInput}" - automatisch ingevuld als 'onbekend'`);
     }
   }
   
   // Controleer club
   if (!club || club.trim() === '') {
     sheet.getRange(row, 3).setBackground('#F4CCCC');
     problemen.push(`Rij ${row}: Club ontbreekt`);
   }
   
   // Controleer gewicht (moet een getal zijn, gevolgd door 'kg', mogelijk met +/- prefix)
   if (!gewicht || typeof gewicht !== 'string') {
     sheet.getRange(row, 4).setBackground('#F4CCCC');
     problemen.push(`Rij ${row}: Gewicht ontbreekt of is in onjuist formaat`);
   } else {
     // Accepteer zowel "30 kg" als ook "-30 kg" of "+30 kg"
     const gewichtMatch = gewicht.match(/^([+-]?\d+)\s*kg$/i);
     if (!gewichtMatch) {
       sheet.getRange(row, 4).setBackground('#F4CCCC');
       problemen.push(`Rij ${row}: Gewicht (${gewicht}) is in onjuist formaat. Gebruik formaat '30 kg', '-30 kg' of '+30 kg'`);
     }
   }
   
   // Controleer geslacht
   if (!geslacht) {
     sheet.getRange(row, 5).setBackground('#F4CCCC');
     problemen.push(`Rij ${row}: Geslacht ontbreekt`);
   } else {
     // Normaliseer geslacht voor controle
     const geslachtNormalized = geslacht.toString().trim().toLowerCase();
     
     if (!(geslachtNormalized === 'm' || geslachtNormalized === 'v' || 
           geslachtNormalized === 'man' || geslachtNormalized === 'vrouw')) {
       sheet.getRange(row, 5).setBackground('#F4CCCC');
       problemen.push(`Rij ${row}: Ongeldig geslacht (${geslacht}). Moet 'M', 'V', 'Man' of 'Vrouw' zijn`);
     }
   }
   
   // Controleer geboortejaar
   if (!geboortejaar || typeof geboortejaar !== 'number' || isNaN(geboortejaar)) {
     sheet.getRange(row, 6).setBackground('#F4CCCC');
     problemen.push(`Rij ${row}: Geboortejaar ontbreekt of is geen getal`);
   } else {
     // Bereken leeftijd
     const leeftijd = huidigJaar - geboortejaar;
     
     // Alleen controleren op te oud (20+), niet op te jong
     if (geboortejaar < huidigJaar - 20) {
       sheet.getRange(row, 6).setBackground('#F4CCCC');
       problemen.push(`Rij ${row}: Geboortejaar (${geboortejaar}) resulteert in een leeftijd hoger dan 20 jaar`);
     }
   }
 }
 
 return problemen;
}

/**
 * Genereer judoka-codes voor alle deelnemers
 * @param {Sheet} sheet - Het deelnemerslijst tabblad
 * @param {Array} data - 2D array met deelnemersgegevens
 * @param {number} startRow - Rijnummer waar de data begint
 */
function genereerJudokaCodes(sheet, data, startRow) {
  const leeftijdsklassen = getLeeftijdsklassen();
  const bandenObj = getBanden();
  const huidigJaar = getCurrentYear();
  
  // Stel titels in
  const headers = ["Naam", "Band", "Club", "Gewichtsklasse", "Geslacht", "Geboortejaar", "JudokaCode", "Leeftijdsklasse", "Gewichtsklasse (berekend)", "Bandcode"];
  for (let i = 0; i < headers.length; i++) {
    sheet.getRange(1, i + 1).setValue(headers[i])
      .setFontWeight("bold");
  }
  
  // Zet de eerste rij vast zodat deze zichtbaar blijft bij scrollen
  sheet.setFrozenRows(1);
  
  // Functie om bandkleur te herkennen uit verschillende notaties
  const herkenBand = (bandStr) => {
    if (!bandStr || bandStr.trim() === '') {
      return "onbekend";
    }

    bandStr = bandStr.toLowerCase().trim();

    // Direct match voor eenvoudige gevallen, inclusief "onbekend"
    if (bandStr in bandenObj) {
      return bandStr;
    }

    // Check voor formaten als "Groen (3 kyu)" of variaties
    for (const band in bandenObj) {
      if (bandStr.startsWith(band)) {
        return band;
      }
    }

    return "onbekend";
  };
  
  // Voorbereiden voor het bijhouden van volgnummers per categorie
  const volgNummerMap = {};
  
  // Clear bestaande codes
  if (data.length > 0) {
    sheet.getRange(startRow, 7, data.length, 4).clearContent();
  }
  
  // Array voor alle nieuwe waarden
  const updatedValues = [];
  
  for (let i = 0; i < data.length; i++) {
    const row = []; // Array voor deze rij
    const [naam, bandInput, club, gewichtStr, geslachtInput, geboortejaar] = data[i];
    
    // Overslaan als alle cellen leeg zijn (lege rij)
    if (!naam && !bandInput && !club && !gewichtStr && !geslachtInput && !geboortejaar) {
      updatedValues.push(["", "", "", ""]); // Lege waarden voor deze rij
      continue;
    }
    
    // Normaliseer geslacht naar 'M' of 'V'
    let geslachtCode;
    if (geslachtInput) {
      const geslachtNormalized = geslachtInput.toString().trim().toLowerCase();
      if (geslachtNormalized === 'm' || geslachtNormalized === 'man') {
        geslachtCode = 'M';
      } else if (geslachtNormalized === 'v' || geslachtNormalized === 'vrouw') {
        geslachtCode = 'V';
      } else {
        geslachtCode = 'X';
      }
    } else {
      geslachtCode = 'X';
    }
    
    // Herken band en bepaal bandcode
    const bandKey = herkenBand(bandInput);
    const bandcode = bandenObj[bandKey];
    
    // Bepaal leeftijdsklasse en numerieke code (2 cijfers)
    let leeftijdNumeriek = '';
    let leeftijdsklasseNaam = '';
    
    // Veiliger verwerking van geboortejaar
    if (!geboortejaar || typeof geboortejaar !== 'number' || isNaN(geboortejaar)) {
      leeftijdNumeriek = "10"; // Default: A-pupillen
      leeftijdsklasseNaam = "A-pupillen";
    } else {
      const leeftijd = huidigJaar - geboortejaar;
      
      // Bepaal de juiste leeftijdsklasse
      if (leeftijd < 8) {
        leeftijdNumeriek = "08"; // Mini's
        leeftijdsklasseNaam = "Mini's";
      } else if (leeftijd < 10) {
        leeftijdNumeriek = "10"; // A-pupillen
        leeftijdsklasseNaam = "A-pupillen";
      } else if (leeftijd < 12) {
        leeftijdNumeriek = "12"; // B-pupillen
        leeftijdsklasseNaam = "B-pupillen";
      } else {
        leeftijdNumeriek = "15"; // -15 jaar
        leeftijdsklasseNaam = (geslachtCode === 'V') ? "Dames -15" : "Heren -15";
      }
    }
    
    // Gewicht bepalen (2 cijfers)
    let gewichtsklasseCode = '00';
    let gewichtsklasseBerekend = '';

    if (gewichtStr) {
      // Probeer direct een getal uit de gewichtsstring te halen
      const numMatch = gewichtStr.match(/([+-]?)(\d+)/);
      if (numMatch) {
        const gewichtKg = parseInt(numMatch[2]);

        // Zoek de juiste gewichtsklasse op basis van leeftijdsklasse
        let gevondenGewichtsklasse = null;

        // Haal gewichtsklassen op voor deze leeftijdsklasse
        if (leeftijdsklasseNaam && leeftijdsklassen[leeftijdsklasseNaam]) {
          const gewichtsklassenVoorLeeftijd = leeftijdsklassen[leeftijdsklasseNaam].gewichtsklassen || [];

          // Zoek de juiste gewichtsklasse
          for (const klasse of gewichtsklassenVoorLeeftijd) {
            if (klasse < 0) {
              // Negatieve klasse: -XX kg
              if (gewichtKg <= Math.abs(klasse)) {
                gevondenGewichtsklasse = klasse;
                break;
              }
            } else {
              // Positieve klasse: +XX kg (zwaarste klasse)
              gevondenGewichtsklasse = klasse;
              break;
            }
          }
        }

        // Als we een gewichtsklasse hebben gevonden, gebruik die
        if (gevondenGewichtsklasse !== null) {
          const absGewicht = Math.abs(gevondenGewichtsklasse);
          gewichtsklasseCode = absGewicht < 10 ? '0' + absGewicht : '' + absGewicht;
          if (gewichtsklasseCode.length > 2) {
            gewichtsklasseCode = gewichtsklasseCode.substring(0, 2);
          }

          // Maak de gewichtsklasse string
          if (gevondenGewichtsklasse < 0) {
            gewichtsklasseBerekend = '-' + absGewicht + ' kg';
          } else {
            gewichtsklasseBerekend = '+' + absGewicht + ' kg';
          }
        } else {
          // Fallback: gebruik het ingevoerde gewicht
          const prefix = numMatch[1] || '-';
          gewichtsklasseCode = gewichtKg < 10 ? '0' + gewichtKg : '' + gewichtKg;
          if (gewichtsklasseCode.length > 2) {
            gewichtsklasseCode = gewichtsklasseCode.substring(0, 2);
          }
          gewichtsklasseBerekend = prefix + gewichtKg + ' kg';
        }
      }
    }
    
    // Maak categoriesleutel voor volgnummers
    const categorieKey = `${leeftijdNumeriek}${gewichtsklasseCode}${bandcode}${geslachtCode}`;
    if (!volgNummerMap[categorieKey]) {
      volgNummerMap[categorieKey] = 1;
    }
    const volgNummer = volgNummerMap[categorieKey];
    const volgNummerCode = volgNummer < 10 ? '0' + volgNummer : '' + volgNummer;
    volgNummerMap[categorieKey]++;
    
    // Maak judokacode: LLGGBGVV (LL=leeftijd, GG=gewicht, B=bandcode, G=geslacht, VV=volgnummer)
    const judokaCode = leeftijdNumeriek + gewichtsklasseCode + bandcode + geslachtCode + volgNummerCode;
    
    // Voeg waarden toe
    row.push(judokaCode);             // JudokaCode
    row.push(leeftijdsklasseNaam);    // Leeftijdsklasse
    row.push(gewichtsklasseBerekend); // Gewichtsklasse (berekend)
    row.push(bandcode);               // Bandcode
    
    updatedValues.push(row);
  }
  
  // Update het sheet als er waarden zijn
  if (updatedValues.length > 0) {
    sheet.getRange(startRow, 7, updatedValues.length, 4).setValues(updatedValues);
  }
  
  // Automatisch aanpassen van kolombreedtes
  sheet.autoResizeColumns(1, 10);
  
  // Voeg wat extra ruimte toe aan alle kolommen
  for (let i = 1; i <= 10; i++) {
    const huidigeBreeddte = sheet.getColumnWidth(i);
    sheet.setColumnWidth(i, huidigeBreeddte + 20);
  }
}

/**
 * Sorteer de deelnemerslijst op leeftijdsklasse, gewicht en naam
 * @param {Sheet} sheet - Het deelnemerslijst tabblad
 */
function sorterenDeelnemerslijst(sheet) {
  const lastRow = getLastRowWithData(sheet);
  if (lastRow <= 3) return;

  const dataRange = sheet.getRange(4, 1, lastRow - 3, 10);
  const data = dataRange.getValues();
  const backgrounds = dataRange.getBackgrounds();

  // Maak een array van objecten die zowel data als achtergrond bevatten
  const combined = data.map((row, index) => ({
    data: row,
    background: backgrounds[index]
  }));

  // Sorteer op leeftijdsklasse (kolom 8), gewicht (kolom 9) en naam (kolom 1)
  combined.sort((a, b) => {
    // Vergelijk eerst leeftijdsklasse
    if (a.data[7] !== b.data[7]) {
      return a.data[7] < b.data[7] ? -1 : 1;
    }

    // Dan vergelijk gewicht
    // Gewichtsklasse formaat is "-XX kg" of "+XX kg"
    const getGewicht = (gewichtStr) => {
      const match = /([+-])(\d+)\s*kg/.exec(gewichtStr);
      if (!match) return 0;

      const gewicht = parseInt(match[2]);
      return match[1] === '-' ? gewicht : 999 + gewicht; // Zorg dat plus-gewichten altijd achteraan komen
    };

    const gewichtA = getGewicht(a.data[8]);
    const gewichtB = getGewicht(b.data[8]);

    if (gewichtA !== gewichtB) {
      return gewichtA - gewichtB;
    }

    // Ten slotte op naam
    return a.data[0].localeCompare(b.data[0]);
  });

  // Splits de gecombineerde array terug in data en achtergronden
  const sortedData = combined.map(item => item.data);
  const sortedBackgrounds = combined.map(item => item.background);

  // Update het sheet met de gesorteerde data en achtergronden
  dataRange.setValues(sortedData);
  dataRange.setBackgrounds(sortedBackgrounds);
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

/**
 * Start het importproces voor een nieuwe deelnemerslijst
 * Begeleidt de gebruiker stap voor stap
 */
function importeerDeelnemerslijst() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();

  // Stap 1: Toon instructies
  const response = ui.alert(
    'Deelnemerslijst importeren - Stap 1 van 2',
    'Klik op OK en importeer daarna je deelnemerslijst:\n\n' +
    '1. Ga naar: Bestand > Importeren\n' +
    '2. Upload je deelnemerslijst bestand (Excel/CSV)\n' +
    '3. Selecteer "Nieuwe tabbladen invoegen"\n' +
    '4. Klik op "Gegevens importeren"\n' +
    '5. Kom terug naar dit menu en klik: WestFries Open > Voorbereiding > Voltooi Import',
    ui.ButtonSet.OK_CANCEL
  );

  if (response !== ui.Button.OK) {
    return;
  }

  // Sla huidige tabbladnamen op in script properties
  const sheets = ss.getSheets();
  const sheetNames = sheets.map(sheet => sheet.getName());

  PropertiesService.getScriptProperties().setProperty(
    'IMPORT_BESTAANDE_TABS',
    JSON.stringify(sheetNames)
  );

  ui.alert(
    'Klaar voor import',
    'De voorbereiding is compleet. Importeer nu je deelnemerslijst.\n\n' +
    'Wanneer je klaar bent met importeren, klik dan op:\n' +
    'WestFries Open > Voorbereiding > Voltooi Import',
    ui.ButtonSet.OK
  );
}

/**
 * Voltooit het importproces door het nieuwe tabblad te detecteren en te verwerken
 */
function voltooiImportDeelnemerslijst() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const ui = SpreadsheetApp.getUi();

  // Haal opgeslagen tabbladnamen op
  const properties = PropertiesService.getScriptProperties();
  const bestaandeTabsJson = properties.getProperty('IMPORT_BESTAANDE_TABS');

  if (!bestaandeTabsJson) {
    ui.alert(
      'Import niet gestart',
      'Je hebt het importproces nog niet gestart.\n\n' +
      'Klik eerst op: WestFries Open > Voorbereiding > Importeer Deelnemerslijst',
      ui.ButtonSet.OK
    );
    return;
  }

  const bestaandeTabs = JSON.parse(bestaandeTabsJson);
  const huidigeTabs = ss.getSheets();

  // Vind nieuwe tabbladen
  const nieuweTabs = huidigeTabs.filter(sheet =>
    !bestaandeTabs.includes(sheet.getName())
  );

  if (nieuweTabs.length === 0) {
    ui.alert(
      'Geen nieuw tabblad gevonden',
      'Er is geen nieuw tabblad gedetecteerd.\n\n' +
      'Zorg ervoor dat je de import hebt voltooid voordat je op "Voltooi Import" klikt.',
      ui.ButtonSet.OK
    );
    return;
  }

  // Als er meerdere nieuwe tabbladen zijn, neem het laatste
  const nieuwTabblad = nieuweTabs[nieuweTabs.length - 1];
  const oudeNaam = nieuwTabblad.getName();

  // Controleer of er al een "Deelnemerslijst" tabblad bestaat
  const bestaandeDeelnemerslijst = ss.getSheetByName("Deelnemerslijst");
  if (bestaandeDeelnemerslijst) {
    const vervangenResponse = ui.alert(
      'Deelnemerslijst bestaat al',
      'Er bestaat al een tabblad met de naam "Deelnemerslijst".\n\n' +
      'Wil je deze vervangen door het nieuwe tabblad "' + oudeNaam + '"?\n' +
      '(Het oude tabblad wordt verwijderd)',
      ui.ButtonSet.YES_NO
    );

    if (vervangenResponse !== ui.Button.YES) {
      properties.deleteProperty('IMPORT_BESTAANDE_TABS');
      return;
    }

    // Verwijder oude deelnemerslijst
    ss.deleteSheet(bestaandeDeelnemerslijst);
  }

  // Hernoem het nieuwe tabblad
  try {
    nieuwTabblad.setName("Deelnemerslijst");
  } catch (e) {
    ui.alert(
      'Fout bij hernoemen',
      'Er is een fout opgetreden bij het hernoemen van het tabblad:\n' + e.message,
      ui.ButtonSet.OK
    );
    properties.deleteProperty('IMPORT_BESTAANDE_TABS');
    return;
  }

  // Verplaats naar positie 2 (na ToernooiConfig)
  ss.setActiveSheet(nieuwTabblad);
  ss.moveActiveSheet(2);

  // Ruim de opgeslagen property op
  properties.deleteProperty('IMPORT_BESTAANDE_TABS');

  // Vraag of de gebruiker direct wil controleren
  const controleerResponse = ui.alert(
    'Import geslaagd!',
    'Tabblad "' + oudeNaam + '" is hernoemd naar "Deelnemerslijst" en verplaatst naar positie 2.\n\n' +
    'Wil je nu automatisch de deelnemers controleren en codes genereren?',
    ui.ButtonSet.YES_NO
  );

  if (controleerResponse === ui.Button.YES) {
    controleerEnGenereerCodes();
  }
}