// PouleIndeling.gs - Functies voor poule-indeling
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
* Genereert poule-indeling op basis van judoka-codes
*/
function genereerPouleIndeling() {
 const ss = SpreadsheetApp.getActiveSpreadsheet();
 const deelnemersSheet = ss.getSheetByName("Deelnemerslijst");
 const poulesSheet = ss.getSheetByName("PouleIndeling");
 const pouleSettings = getPouleSettings();
 
 // Lees deelnemersgegevens
 const deelnemersData = leesDeelnemersData(deelnemersSheet);
 
 // Sorteer op judokacode
 deelnemersData.sort((a, b) => a.judokaCode.localeCompare(b.judokaCode));
 
 // Verdeel in poules
 poulesSheet.clear();
 maakPouleHeaders(poulesSheet);
 
 // Deel in op gewichtsklassen en leeftijdsklassen
 const groepen = deelInGroepen(deelnemersData);
 
 // Schrijf poules naar sheet
 schrijfPoules(poulesSheet, groepen, pouleSettings);
 
 SpreadsheetApp.getUi().alert("Poule-indeling voltooid");
}

function leesDeelnemersData(sheet) {
 const lastRow = sheet.getLastRow();
 
 // Haal alle data op van het sheet
 const allData = sheet.getRange(1, 1, lastRow, 10).getValues();
 const deelnemers = [];
 
 // Zoek naar de header-rij (deze bevat "Judoka-code" of soortgelijke tekst)
 let headerRow = -1;
 for (let i = 0; i < allData.length; i++) {
   if (allData[i].some(cell => cell && typeof cell === 'string' && 
                        (cell.toLowerCase().includes('judoka') || 
                         cell.toLowerCase().includes('code')))) {
     headerRow = i;
     break;
   }
 }
 
 // Als we geen header vinden, neem aan dat deze op rij 1 staat
 if (headerRow === -1) headerRow = 0;
 
 // Verzamel alle deelnemers (rijen met judokacode)
 for (let i = headerRow + 1; i < allData.length; i++) {
   // Check of deze rij een judoka bevat (gewichtsklasse en geboortejaar ingevuld)
   if (allData[i][3] && allData[i][5] && allData[i][6]) {
     deelnemers.push({
       naam: allData[i][0] || "",
       band: allData[i][1] || "",
       club: allData[i][2] || "",
       gewicht: allData[i][3] || "",
       geslacht: allData[i][4] || "",
       geboortejaar: allData[i][5] || "",
       judokaCode: allData[i][6] || "",
       leeftijdsklasse: allData[i][7] || "",
       gewichtsklasse: allData[i][8] || "",
       bandcode: allData[i][9] || ""
     });
   }
 }
 
 return deelnemers;
}

/**
* Maakt headers voor het pouleblad
*/
function maakPouleHeaders(sheet) {
 const headers = ["Naam", "Band", "Club", "Gewichtsklasse", "Geslacht", "Geboortejaar",
                  "Leeftijdsklasse", "Opmerking", "Judoka-code", "Blok", "Mat", "Poule-nr", "Pouletitel"];
 
 const headerRange = sheet.getRange(1, 1, 1, headers.length);
 headerRange.setValues([headers])
   .setFontWeight("bold")
   .setBackground("#D9D9D9");
 
 // Zet de bovenste rij vast zodat deze zichtbaar blijft bij scrollen
 sheet.setFrozenRows(1);
}

function deelInGroepen(deelnemers) {
  const groepen = {};
  
  // Deel in volgens leeftijdsklasse, gewichtsklasse en geslacht
  deelnemers.forEach(deelnemer => {
    const leeftijdCode = deelnemer.judokaCode.substr(0, 2);
    const gewichtCode = deelnemer.judokaCode.substr(2, 2);
    const bandCode = deelnemer.judokaCode.substr(4, 1);
    const geslachtCode = deelnemer.judokaCode.substr(5, 1); // M of V
    
    // Bepaal correcte leeftijdsklasse
    let leeftijdsklasse = deelnemer.leeftijdsklasse || "";
    
    // Maak een sleutel die rekening houdt met geslacht ALLEEN bij -15 jaar
    let sleutel;
    
    if (leeftijdCode === "15") {
      // Voor -15 jaar, maak expliciet onderscheid tussen Heren en Dames
      leeftijdsklasse = (geslachtCode === "V") ? "Dames -15" : "Heren -15";
      // Sleutel met geslachtscode voor gescheiden indeling
      sleutel = `${leeftijdCode}-${gewichtCode}-${geslachtCode}`;
    } else {
      // Voor jongere leeftijdsklassen, GEEN onderscheid op geslacht
      sleutel = `${leeftijdCode}-${gewichtCode}`;
    }
    
    // Extract gewichtsklasse en controleer of het +/- is
    let gewichtKlasse = deelnemer.gewichtsklasse || deelnemer.gewicht || "";
    
    // Maak een unieke sleutel die ook rekening houdt met +/- prefix bij gewichtsklasse
    if (gewichtKlasse.includes("+")) {
      // Voor +gewicht: voeg een 'P' toe aan de sleutel (P = plus)
      sleutel += "-P";
    } else if (gewichtKlasse.includes("-")) {
      // Voor -gewicht: voeg een 'M' toe aan de sleutel (M = min)
      sleutel += "-M";
    }
    
    if (!groepen[sleutel]) {
      groepen[sleutel] = {
        leeftijdsklasse: leeftijdsklasse,
        gewichtsklasse: gewichtKlasse,
        deelnemers: []
      };
    }
    
    groepen[sleutel].deelnemers.push(deelnemer);
  });
  
  return groepen;
}

/**
* Verdeelt deelnemers in poules die zo dicht mogelijk bij het optimale aantal judoka's komen (5)
* Zorgt ervoor dat er geen poules met 1 of 2 deelnemers zijn tenzij niet anders mogelijk
*/
function maakOptimalePoules(deelnemers, settings) {
  const aantalDeelnemers = deelnemers.length;
  const optimaal = settings.OPTIMAL_JUDOKAS;
  const minimum = settings.MIN_JUDOKAS;
  const maximum = settings.MAX_JUDOKAS;
  
  // Als minder dan minimum, één poule
  if (aantalDeelnemers <= minimum) {
    return [deelnemers];
  }
  
  // Als tussen minimum en optimaal+1, één poule
  if (aantalDeelnemers <= optimaal + 1) {
    return [deelnemers];
  }
  
  // Zoek beste verdeling met zoveel mogelijk optimale poules
  // Voorkom poules met 1 of 2 deelnemers tenzij er niet anders mogelijk is
  let besteVerdeling = [];
  let besteScore = Number.MAX_SAFE_INTEGER;
  
  // Test verschillende aantallen poules
  for (let aantalPoules = 2; aantalPoules <= Math.floor(aantalDeelnemers / minimum); aantalPoules++) {
    const grootte = Math.floor(aantalDeelnemers / aantalPoules);
    const rest = aantalDeelnemers % aantalPoules;
    
    // Check of alle poules aan minimumgrootte voldoen
    if (grootte < minimum) continue;
    
    // Bereken score (afwijking van optimaal)
    let score = 0;
    let heeftTeKleinePoule = false;
    
    for (let i = 0; i < aantalPoules; i++) {
      const pouleGrootte = grootte + (i < rest ? 1 : 0);
      score += Math.abs(pouleGrootte - optimaal);
      
      // Extra penalty voor te grote poules
      if (pouleGrootte > maximum) {
        score += 10 * (pouleGrootte - maximum);
      }
      
      // Extra hoge penalty voor poules met 1 of 2 deelnemers
      if (pouleGrootte <= 2) {
        score += 100;
        heeftTeKleinePoule = true;
      }
    }
    
    // Als er geen poule met 1-2 deelnemers is, of er is geen andere optie,
    // en deze score is beter dan wat we al hadden
    if ((score < besteScore && !heeftTeKleinePoule) || (besteVerdeling.length === 0)) {
      besteScore = score;
      
      // Maak de verdeling
      besteVerdeling = Array(aantalPoules).fill().map(() => []);
      
      // Sorteer deelnemers om vergelijkbaar niveau samen te plaatsen
      // (bijvoorbeeld op basis van band of judokacode)
      const gesorteerdeDeelnemers = [...deelnemers].sort((a, b) => 
        a.judokaCode.localeCompare(b.judokaCode));
      
      let deelnemerIndex = 0;
      for (let i = 0; i < aantalPoules; i++) {
        const pouleGrootte = grootte + (i < rest ? 1 : 0);
        for (let j = 0; j < pouleGrootte; j++) {
          besteVerdeling[i].push(gesorteerdeDeelnemers[deelnemerIndex++]);
        }
      }
    }
  }
  
  // Als we geen goede verdeling konden vinden, maak één poule
  if (besteVerdeling.length === 0) {
    return [deelnemers];
  }
  
  return besteVerdeling;
}

/**
* Bepaalt de leeftijdsklasse op basis van leeftijd en geslacht
* @param {number} leeftijd - De leeftijd van de judoka
* @param {string} geslacht - Het geslacht van de judoka ('M' of 'V')
* @return {string} De leeftijdsklasse
*/
function bepaalLeeftijdsklasse(leeftijd, geslacht) {
  if (leeftijd < 8) return "Mini's";
  if (leeftijd < 10) return "A-pupillen";
  if (leeftijd < 12) return "B-pupillen";
  if (leeftijd < 15) return (geslacht === "V") ? "Dames -15" : "Heren -15";
  if (leeftijd < 18) return (geslacht === "V") ? "Dames -18" : "Heren -18";
  return (geslacht === "V") ? "Dames" : "Heren";
}


function schrijfPoules(sheet, groepen, settings) {
  // Maak headers
  const headers = ["Naam", "Band", "Club", "Gewichtsklasse", "Geslacht", "Geboortejaar",
                  "Leeftijdsklasse", "Opmerking", "Judoka-code", "Blok", "Mat", "Poule-nr", "Pouletitel"];
  
  sheet.getRange(1, 1, 1, headers.length).setValues([headers])
    .setFontWeight("bold")
    .setBackground("#D9D9D9")
    .setHorizontalAlignment("center");
  
  // Zet de eerste rij vast voor scrollen
  sheet.setFrozenRows(1);
  
  // Start vanaf rij 2
  let row = 2;
  let pouleNr = 1;
  let vorigeLeeftijdsklasse = '';
  
  // Organiseer sleutels per leeftijdsklasse en gewicht
  const leeftijdsGroepen = {};
  
  // Groepeer op leeftijdsklasse
  for (const sleutel in groepen) {
    const groep = groepen[sleutel];
    const deelnemers = groep.deelnemers;
    
    if (deelnemers.length === 0) continue;
    
    // Bepaal leeftijdsklasse uit de eerste deelnemer
    let leeftijdsklasse = "";
    
    if (deelnemers[0].leeftijdsklasse) {
      leeftijdsklasse = deelnemers[0].leeftijdsklasse;
    } else if (deelnemers[0].judokaCode) {
      // Extract uit judokaCode als nodig
      const leeftijdCode = deelnemers[0].judokaCode.substr(0, 2);
      const geslachtCode = deelnemers[0].judokaCode.substr(5, 1);
      
      if (leeftijdCode === "08") leeftijdsklasse = "Mini's";
      else if (leeftijdCode === "10") leeftijdsklasse = "A-pupillen";
      else if (leeftijdCode === "12") leeftijdsklasse = "B-pupillen";
      else if (leeftijdCode === "15") {
        leeftijdsklasse = (geslachtCode === "V") ? "Dames -15" : "Heren -15";
      }
    }
    
    // Als geen leeftijdsklasse gevonden, gebruik standaard
    if (!leeftijdsklasse) leeftijdsklasse = "Onbekend";
    
    // Sla sleutel op in juiste leeftijdsgroep
    if (!leeftijdsGroepen[leeftijdsklasse]) {
      leeftijdsGroepen[leeftijdsklasse] = [];
    }
    
    leeftijdsGroepen[leeftijdsklasse].push(sleutel);
  }
  
  // Bepaal volgorde van leeftijdsklassen
  const volgorde = [
    "Mini's", 
    "A-pupillen", 
    "B-pupillen", 
    "Dames -15", 
    "Heren -15"
  ];
  
  // Voeg alle overige leeftijdsklassen toe aan het einde
  for (const lk in leeftijdsGroepen) {
    if (!volgorde.includes(lk)) {
      volgorde.push(lk);
    }
  }
  
  // Schrijf elke leeftijdsklasse in de gewenste volgorde
  for (const leeftijdsklasse of volgorde) {
    if (!leeftijdsGroepen[leeftijdsklasse] || leeftijdsGroepen[leeftijdsklasse].length === 0) continue;
    
    // Sorteer sleutels binnen de leeftijdsklasse
    leeftijdsGroepen[leeftijdsklasse].sort();
    
    // Schrijf kopregel voor de leeftijdsklasse
    sheet.getRange(row, 1, 1, headers.length).merge();
    sheet.getRange(row, 1)
      .setValue(leeftijdsklasse)
      .setBackground("#EFEFEF")
      .setHorizontalAlignment("center")
      .setFontWeight("bold");
    row++;
    
    // Schrijf alle poules voor deze leeftijdsklasse
    for (const sleutel of leeftijdsGroepen[leeftijdsklasse]) {
      const groep = groepen[sleutel];
      const deelnemers = groep.deelnemers;
      let gewichtsklasse = groep.gewichtsklasse;
      
      // Verdeel in poules
      const poules = maakOptimalePoules(deelnemers, settings);
      
      // Schrijf elke poule
      for (let i = 0; i < poules.length; i++) {
        // Maak de pouletitel
        const pouleTitel = maakPouleTitel(leeftijdsklasse, gewichtsklasse, pouleNr);
        
        // Titelrij voor poule
        sheet.getRange(row, 1, 1, headers.length).merge();
        sheet.getRange(row, 1)
          .setValue(pouleTitel)
          .setBackground("#B6D7A8")
          .setHorizontalAlignment("center")
          .setFontWeight("bold");
        row++;
        
        // Bepaal achtergrondkleur op basis van aantal judoka's in de poule
        let pouleAchtergrond = null;
        let waarschuwingsTekst = "";
        
        if (poules[i].length <= 2) {
          pouleAchtergrond = "#FFFF00"; // Geel voor te kleine poules (1-2 judoka's)
          waarschuwingsTekst = "TE WEINIG DEELNEMERS";
        } else if (poules[i].length >= 7) {
          pouleAchtergrond = "#FFD966"; // Oranje voor grote poules (7+ judoka's)
          waarschuwingsTekst = "LET OP: grote poule";
        }
        
        // Deelnemers in poule
        for (const judoka of poules[i]) {
          const judokaRij = [
            judoka.naam,
            judoka.band,
            judoka.club,
            judoka.gewichtsklasse || judoka.gewicht,
            judoka.geslacht,
            judoka.geboortejaar,
            leeftijdsklasse, // Leeftijdsklasse
            waarschuwingsTekst,
            judoka.judokaCode,
            "", // Blok
            "", // Mat
            pouleNr,
            pouleTitel
          ];
          
          const rowRange = sheet.getRange(row, 1, 1, headers.length);
          rowRange.setValues([judokaRij]);
          rowRange.setHorizontalAlignment("center");
          
          if (pouleAchtergrond) {
            rowRange.setBackground(pouleAchtergrond);
          }
          
          row++;
        }
        
        // Twee lege rijen na elke poule
        row += 2;
        pouleNr++;
      }
    }
  }
  
  // Pas kolombreedtes aan
  sheet.autoResizeColumns(1, 12);
}


/**
* Maakt een gestandaardiseerde pouletitel op basis van leeftijdsklasse, gewichtsklasse en poulenummer
* @param {string} leeftijdsklasse - De leeftijdsklasse (bijv. "Mini's", "A-pupillen")
* @param {string} gewichtsklasse - De gewichtsklasse (bijv. "-24 kg", "+38 kg")
* @param {number} pouleNr - Het nummer van de poule
* @return {string} Een geformatteerde pouletitel
*/
function maakPouleTitel(leeftijdsklasse, gewichtsklasse, pouleNr) {
 // Zorg dat er altijd waarden zijn, zelfs als er iets ontbreekt
 const lk = leeftijdsklasse || "Onbekende leeftijdsklasse";
 const gk = gewichtsklasse || "Onbekend gewicht";
 
 // Maak de titel in het juiste formaat
 return `${lk} ${gk} Poule ${pouleNr}`;
}




function maakOptimalePoules(deelnemers, settings) {
  const aantalDeelnemers = deelnemers.length;
  const optimaal = settings.OPTIMAL_JUDOKAS;
  const minimum = settings.MIN_JUDOKAS;
  const maximum = settings.MAX_JUDOKAS;
  
  // Als er maar weinig deelnemers zijn (≤ minimum), één poule
  if (aantalDeelnemers <= minimum) {
    return [deelnemers];
  }
  
  // Speciale gevallen
  if (aantalDeelnemers === 5) return [deelnemers]; // Perfect aantal
  if (aantalDeelnemers === 6) return [deelnemers]; // Goed aantal
  if (aantalDeelnemers === 4) return [deelnemers]; // Ook acceptabel
  
  // Speciale gevallen voor 7-10 deelnemers
  if (aantalDeelnemers === 7) return [[...deelnemers.slice(0, 3)], [...deelnemers.slice(3)]]; // 3+4
  if (aantalDeelnemers === 8) return [[...deelnemers.slice(0, 4)], [...deelnemers.slice(4)]]; // 4+4
  if (aantalDeelnemers === 9) return [[...deelnemers.slice(0, 4)], [...deelnemers.slice(4)]]; // 4+5
  if (aantalDeelnemers === 10) return [[...deelnemers.slice(0, 5)], [...deelnemers.slice(5)]]; // 5+5
  
  // Ga door met testen van verschillende verdelingen voor grotere aantallen
  let besteVerdeling = [];
  let besteScore = Number.MAX_SAFE_INTEGER;
  
  // Straf factoren
  const STRAF_TE_KLEIN = 1000; // Straf voor poules met 1-2 deelnemers
  const STRAF_KLEINE_POULE = 100; // Straf voor poules met 3 deelnemers  
  const STRAF_GROTE_POULE = 50;  // Straf voor poules met 7+ deelnemers
  const STRAF_AFWIJKING = 10;    // Straf per afwijking van optimale grootte
  
  // Test verschillende aantallen poules
  const maxPoules = Math.floor(aantalDeelnemers / 3); // Max aantal mogelijke poules
  
  for (let aantalPoules = 2; aantalPoules <= maxPoules; aantalPoules++) {
    const basisGrootte = Math.floor(aantalDeelnemers / aantalPoules);
    const rest = aantalDeelnemers % aantalPoules;
    
    // Bereken score voor deze verdeling
    let score = 0;
    
    // Simuleer de poules
    const pouleGroottes = Array(aantalPoules).fill(basisGrootte);
    for (let i = 0; i < rest; i++) {
      pouleGroottes[i]++;
    }
    
    // Evalueer elke poule
    for (const grootte of pouleGroottes) {
      if (grootte <= 2) {
        score += STRAF_TE_KLEIN;
      } else if (grootte === 3) {
        score += STRAF_KLEINE_POULE;
      } else if (grootte >= 7) {
        score += STRAF_GROTE_POULE + (grootte - 7) * 20; // Extra straf per extra deelnemer
      } else {
        // Straf voor afwijking van optimaal (5)
        score += Math.abs(grootte - optimaal) * STRAF_AFWIJKING;
      }
    }
    
    // Als deze verdeling beter is dan wat we al hadden
    if (score < besteScore) {
      besteScore = score;
      
      // Maak de verdeling
      besteVerdeling = Array(aantalPoules).fill().map(() => []);
      
      // Verdeel de deelnemers over de poules
      let deelnemerIndex = 0;
      for (let i = 0; i < aantalPoules; i++) {
        const pouleGrootte = basisGrootte + (i < rest ? 1 : 0);
        for (let j = 0; j < pouleGrootte; j++) {
          besteVerdeling[i].push(deelnemers[deelnemerIndex++]);
        }
      }
    }
  }
  
  // Als er geen geldige verdeling is (zou niet moeten gebeuren), maak één poule
  if (besteVerdeling.length === 0) {
    return [deelnemers];
  }
  
  return besteVerdeling;
}

/**
 * Verplaatst geselecteerde judoka's naar een andere poule
 */
function verplaatsJudoka() {
  const ui = SpreadsheetApp.getUi();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName("PouleIndeling");
  
  // Haal alle geselecteerde ranges op
  const selection = sheet.getSelection();
  const ranges = selection.getActiveRangeList().getRanges();
  
  if (!ranges || ranges.length === 0) {
    ui.alert("Geen selectie", "Selecteer eerst de judoka-rijen die je wilt verplaatsen.", ui.ButtonSet.OK);
    return;
  }
  
  // Verzamel alle geselecteerde judoka's
  const selectedJudokas = [];
  const numColumns = sheet.getLastColumn();
  
  for (let r = 0; r < ranges.length; r++) {
    const range = ranges[r];
    const startRow = range.getRow();
    const numRows = range.getNumRows();
    
    for (let i = 0; i < numRows; i++) {
      const currentRow = startRow + i;
      const rowData = sheet.getRange(currentRow, 1, 1, numColumns).getValues()[0];
      
      // Controleer of dit een judoka is (heeft een naam en een judokacode)
      if (rowData[0] && rowData[0].toString().trim() !== '' && 
          rowData[7] && rowData[7].toString().trim() !== '') {
        selectedJudokas.push({
          row: currentRow,
          data: rowData
        });
      }
    }
  }
  
  if (selectedJudokas.length === 0) {
    ui.alert("Geen judoka's geselecteerd", "Selecteer judoka's in het 'PouleIndeling' tabblad.", ui.ButtonSet.OK);
    return;
  }
  
  // Vraag doelpoule
  const response = ui.prompt(
    `Verplaats ${selectedJudokas.length === 1 ? 'Judoka' : 'Judoka\'s'}`,
    `Naar welk poulenummer wil je ${selectedJudokas.length === 1 ? 'deze judoka' : 'deze ' + selectedJudokas.length + ' judoka\'s'} verplaatsen?`,
    ui.ButtonSet.OK_CANCEL
  );
  
  if (response.getSelectedButton() !== ui.Button.OK) {
    return;
  }
  
  const doelPoule = parseInt(response.getResponseText());
  if (isNaN(doelPoule)) {
    ui.alert("Ongeldig poulenummer", "Voer een geldig poulenummer in.", ui.ButtonSet.OK);
    return;
  }
  
  // Zoek doelpouletitel
  let doelPouleTitel = "";
  let laatsteRijDoelPoule = 0;
  const allData = sheet.getDataRange().getValues();
  
  for (let i = 0; i < allData.length; i++) {
    if (allData[i][10] === doelPoule) { // Kolom K (index 10)
      doelPouleTitel = allData[i][11];  // Kolom L (index 11)
      laatsteRijDoelPoule = i + 1;      // +1 omdat rijen op 1 beginnen
    }
  }
  
  if (!doelPouleTitel) {
    ui.alert("Doelpoule niet gevonden", "Er is geen poule gevonden met poulenummer " + doelPoule, ui.ButtonSet.OK);
    return;
  }
  
  // Sorteer judoka's van hoog naar laag om verwijderproblemen te voorkomen
  selectedJudokas.sort((a, b) => b.row - a.row);
  
  // Verzamel de gegevens voordat we de rijen verwijderen
  const judokasToAdd = [];
  
  for (const judoka of selectedJudokas) {
    const judokaData = judoka.data.slice(); // Kopie maken
    
    // Update poule-info, behoud blok en mat
    judokaData[10] = doelPoule;         // Poule-nr
    judokaData[11] = doelPouleTitel;    // Pouletitel
    
    judokasToAdd.push(judokaData);
    
    // Verwijder originele rij
    sheet.deleteRow(judoka.row);
  }
  
  // Vind de laatste judoka in de doelpoule na de verwijderingen
  const updatedData = sheet.getDataRange().getValues();
  let insertRow = -1;
  
  for (let i = 0; i < updatedData.length; i++) {
    if (updatedData[i][10] === doelPoule) {
      insertRow = i + 1;
    }
  }
  
  // Als we geen laatste judoka vinden, voegen we toe aan het eind
  if (insertRow === -1) {
    // Voeg toe aan het eind
    for (const judokaData of judokasToAdd) {
      sheet.appendRow(judokaData);
    }
  } else {
    // Voeg toe na de laatste judoka
    for (let i = 0; i < judokasToAdd.length; i++) {
      sheet.insertRowAfter(insertRow + i);
      sheet.getRange(insertRow + i + 1, 1, 1, judokasToAdd[i].length).setValues([judokasToAdd[i]]);
      sheet.getRange(insertRow + i + 1, 1, 1, numColumns).setHorizontalAlignment("center");
    }
  }
  
  // Controleer grote poules en update markeringen
  updatePouleMarkeringen(sheet);
  
  // Herbereken wedstrijden
  berekenWedstrijdenPerGewichtsklasse();
  
  ui.alert(`${judokasToAdd.length} judoka('s) verplaatst naar poule ${doelPoule}`);
}

/**
 * Update markeringen voor grote poules
 */
function updatePouleMarkeringen(sheet) {
  const lastRow = sheet.getLastRow();
  const data = sheet.getRange(2, 1, lastRow-1, 12).getValues();
  
  // Tel judoka's per poule
  const pouleAantallen = {};
  
  for (let i = 0; i < data.length; i++) {
    const pouleNr = data[i][10];
    if (!pouleNr) continue;
    
    if (!pouleAantallen[pouleNr]) {
      pouleAantallen[pouleNr] = 0;
    }
    pouleAantallen[pouleNr]++;
  }
  
  // Update markering en waarschuwingen
  for (let i = 0; i < data.length; i++) {
    const pouleNr = data[i][10];
    if (!pouleNr) continue;
    
    const aantalInPoule = pouleAantallen[pouleNr] || 0;
    
    // Update opmerking en achtergrond
    let opmerking = "";
    let achtergrond = null;
    
    if (aantalInPoule <= 2) {
      opmerking = "TE WEINIG DEELNEMERS";
      achtergrond = "#FFFF00"; // Geel voor te kleine poules
    } else if (aantalInPoule >= 7) {
      opmerking = "LET OP: grote poule";
      achtergrond = "#FFD966"; // Oranje voor grote poules
    }
    
    sheet.getRange(i+2, 7).setValue(opmerking);
    
    if (achtergrond) {
      sheet.getRange(i+2, 1, 1, 12).setBackground(achtergrond);
    } else {
      sheet.getRange(i+2, 1, 1, 12).setBackground(null);
    }
  }
}

/**
 * Berekent het aantal wedstrijden voor een poule met een bepaald aantal judoka's
 * @param {number} aantalJudokas - Het aantal judoka's in de poule
 * @return {number} Het aantal wedstrijden in de poule
 */
function berekenAantalWedstrijden(aantalJudokas) {
  // Formule: n*(n-1)/2 voor normale poule waar iedereen 1x tegen elkaar speelt
  // Voor 3 judoka's: iedereen speelt 2x tegen elkaar
  if (aantalJudokas === 3) {
    return 6; // 3 judoka's spelen dubbel tegen elkaar
  } else {
    return (aantalJudokas * (aantalJudokas - 1)) / 2;
  }
}

function berekenWedstrijdenPerGewichtsklasse() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  
  if (!poulesSheet) {
    Logger.log("PouleIndeling tabblad niet gevonden");
    return 0;
  }
  
  // Lees alle data uit het poule-indelingsblad
  const lastRow = poulesSheet.getLastRow();
  const data = poulesSheet.getRange(1, 1, lastRow, 13).getValues();
  Logger.log("Aantal rijen in data: " + data.length);
  
  // Map om te tellen hoeveel judoka's er per poule zijn
  const pouleJudokas = {};
  // Map om poules te groeperen per gewichtsklasse/leeftijdsklasse combinatie
  const gewichtsklassePoules = {};
  
  // Doorloop alle rijen om judoka's per poule te tellen
  for (let i = 1; i < data.length; i++) {
    const row = data[i];
    
    // Alleen rijen met een poulenummer verwerken
    if (row[10] && typeof row[10] === 'number') {
      const poulenr = row[10];
      const pouletitel = row[11];
      Logger.log("Gevonden poule: " + poulenr + ", titel: " + pouletitel);
      
      if (!pouleJudokas[poulenr]) {
        pouleJudokas[poulenr] = {
          aantal: 0,
          titel: pouletitel,
          gewichtsklasse: ""
        };
      }
      
      // Tel deze judoka
      pouleJudokas[poulenr].aantal++;
      
      // Gewichtsklasse uit de pouletitel halen
      if (!pouleJudokas[poulenr].gewichtsklasse && pouletitel) {
        // Extract combinatie van leeftijdsklasse en gewichtsklasse
        const match = pouletitel.match(/(.+?) ([\-\+][0-9]+ kg|[0-9]+ kg)/i);
        if (match && match.length >= 3) {
          const leeftijdsklasse = match[1].trim();
          const gewichtsklasse = match[2].trim();
          const combi = `${leeftijdsklasse} ${gewichtsklasse}`;
          pouleJudokas[poulenr].gewichtsklasse = combi;
          Logger.log("Gewichtsklasse gevonden: " + combi);
          
          // Voeg poule toe aan gewichtsklasse groep
          if (!gewichtsklassePoules[combi]) {
            gewichtsklassePoules[combi] = [];
          }
          gewichtsklassePoules[combi].push(poulenr);
        } else {
          Logger.log("Geen gewichtsklasse gevonden in pouletitel: " + pouletitel);
        }
      }
    }
  }
  
  Logger.log("Aantal poules: " + Object.keys(pouleJudokas).length);
  Logger.log("Aantal gewichtsklassen: " + Object.keys(gewichtsklassePoules).length);

  // Bereken wedstrijden per gewichtsklasse
  const gewichtsklasseWedstrijden = {};
  let totaalWedstrijden = 0;

  for (const combi in gewichtsklassePoules) {
    const pouleNummers = gewichtsklassePoules[combi];
    let wedstrijdenVoorGewichtsklasse = 0;

    for (const poulenr of pouleNummers) {
      if (pouleJudokas[poulenr]) {
        const aantalJudokas = pouleJudokas[poulenr].aantal;
        const wedstrijden = berekenAantalWedstrijden(aantalJudokas);
        wedstrijdenVoorGewichtsklasse += wedstrijden;
      }
    }

    gewichtsklasseWedstrijden[combi] = wedstrijdenVoorGewichtsklasse;
    totaalWedstrijden += wedstrijdenVoorGewichtsklasse;
  }

  // Schrijf overzicht naar het blad
  // Zoek een lege kolom rechts van de poule-indeling
  const overzichtStartKolom = 14; // Kolom N (na de poule data)

  // CLEAR eerst de overzichtkolommen N en O volledig
  // Dit voorkomt dat oude poule-totalen blijven staan na herindeling
  const maxRows = poulesSheet.getMaxRows();
  poulesSheet.getRange(1, overzichtStartKolom, maxRows, 2).clear();

  // Schrijf header voor overzicht
  poulesSheet.getRange(1, overzichtStartKolom).setValue("OVERZICHT WEDSTRIJDEN")
    .setFontWeight("bold")
    .setFontSize(12);

  poulesSheet.getRange(2, overzichtStartKolom).setValue("Leeftijdsklasse/Gewichtsklasse")
    .setFontWeight("bold");
  poulesSheet.getRange(2, overzichtStartKolom + 1).setValue("Aantal Wedstrijden")
    .setFontWeight("bold");

  // Sorteer gewichtsklassen op leeftijd (jong naar oud)
  const leeftijdsVolgorde = ["Mini's", "A-pupillen", "B-pupillen", "Dames -15", "Heren -15", "Dames -18", "Heren -18", "Dames", "Heren"];

  const gesorteerdeCombi = Object.keys(gewichtsklasseWedstrijden).sort((a, b) => {
    // Extract leeftijdsklasse uit de combi string (bijv. "Mini's -20 kg" -> "Mini's")
    const leeftijdA = leeftijdsVolgorde.find(lk => a.startsWith(lk)) || a;
    const leeftijdB = leeftijdsVolgorde.find(lk => b.startsWith(lk)) || b;

    const indexA = leeftijdsVolgorde.indexOf(leeftijdA);
    const indexB = leeftijdsVolgorde.indexOf(leeftijdB);

    // Als beide in de volgorde array staan, sorteer op index
    if (indexA !== -1 && indexB !== -1) {
      if (indexA !== indexB) return indexA - indexB;
    }

    // Anders alfabetisch op de volledige string
    return a.localeCompare(b);
  });

  // Schrijf data
  let rij = 3;
  for (const combi of gesorteerdeCombi) {
    poulesSheet.getRange(rij, overzichtStartKolom).setValue(combi);
    poulesSheet.getRange(rij, overzichtStartKolom + 1).setValue(gewichtsklasseWedstrijden[combi]);
    rij++;
  }

  // Schrijf totaal
  poulesSheet.getRange(rij, overzichtStartKolom).setValue("TOTAAL")
    .setFontWeight("bold");
  poulesSheet.getRange(rij, overzichtStartKolom + 1).setValue(totaalWedstrijden)
    .setFontWeight("bold");

  // Pas kolombreedtes aan
  poulesSheet.setColumnWidth(overzichtStartKolom, 250);
  poulesSheet.setColumnWidth(overzichtStartKolom + 1, 150);

  Logger.log("Totaal aantal wedstrijden: " + totaalWedstrijden);
  return totaalWedstrijden;
}

/**
 * Update de functie genereerPouleIndeling om ook het aantal wedstrijden te berekenen
 */
function genereerPouleIndeling() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const deelnemersSheet = ss.getSheetByName("Deelnemerslijst");
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  const pouleSettings = getPouleSettings();
  
  // Lees deelnemersgegevens
  const deelnemersData = leesDeelnemersData(deelnemersSheet);
  
  // Sorteer op judokacode
  deelnemersData.sort((a, b) => a.judokaCode.localeCompare(b.judokaCode));
  
  // Verdeel in poules
  poulesSheet.clear();
  maakPouleHeaders(poulesSheet);
  
  // Deel in op gewichtsklassen en leeftijdsklassen
  const groepen = deelInGroepen(deelnemersData);
  
  // Schrijf poules naar sheet
  schrijfPoules(poulesSheet, groepen, pouleSettings);
  
  // Bereken het aantal wedstrijden per gewichtsklasse
  const totaalWedstrijden = berekenWedstrijdenPerGewichtsklasse();
  
  SpreadsheetApp.getUi().alert(
    "Poule-indeling voltooid",
    `Poule-indeling is voltooid. Totaal aantal wedstrijden: ${totaalWedstrijden}.`,
    SpreadsheetApp.getUi().ButtonSet.OK
  );
}

/**
 * Functie om enkel de wedstrijden opnieuw te berekenen zonder de poule-indeling te wijzigen
 */
function herberekeningWedstrijden() {
  const totaalWedstrijden = berekenWedstrijdenPerGewichtsklasse();
  
  SpreadsheetApp.getUi().alert(
    "Herberekening voltooid",
    `De wedstrijden zijn opnieuw berekend. Totaal aantal wedstrijden: ${totaalWedstrijden}.`,
    SpreadsheetApp.getUi().ButtonSet.OK
  );
}

// Voeg dit toe aan het einde van de PouleIndeling.gs file of waar relevant

/**
 * Zorgt ervoor dat de meest recente configuratie wordt geladen voor poule-indeling
 */
function laadConfiguratieVoorPouleIndeling() {
  // Laad de meest recente configuratie
  if (typeof laadConfiguratie === 'function') {
    laadConfiguratie();
  }
  
  // Controleer of de leeftijdsklassen zijn geladen
  const leeftijdsklassen = getLeeftijdsklassen();
  const bandCombinaties = getBandCombinaties();
  
  if (Object.keys(leeftijdsklassen).length === 0) {
    SpreadsheetApp.getUi().alert(
      'Waarschuwing',
      'De leeftijdsklassen konden niet worden geladen. Standaardwaarden worden gebruikt. Controleer het configuratieblad.',
      SpreadsheetApp.getUi().ButtonSet.OK
    );
  }
  
  return {
    leeftijdsklassen: leeftijdsklassen,
    bandCombinaties: bandCombinaties
  };
}

// Pas de genereerPouleIndeling functie aan met een check voor de configuratie
function genereerPouleIndeling() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const deelnemersSheet = ss.getSheetByName("Deelnemerslijst");
  const poulesSheet = ss.getSheetByName("PouleIndeling");
  const pouleSettings = getPouleSettings();
  
  // Laad de configuratie voor de indeling
  laadConfiguratieVoorPouleIndeling();
  
  // Rest van de functie blijft hetzelfde
  const deelnemersData = leesDeelnemersData(deelnemersSheet);
  
  // Sorteer op judokacode
  deelnemersData.sort((a, b) => a.judokaCode.localeCompare(b.judokaCode));
  
  // Verdeel in poules
  poulesSheet.clear();
  maakPouleHeaders(poulesSheet);
  
  // Deel in op gewichtsklassen en leeftijdsklassen
  const groepen = deelInGroepen(deelnemersData);
  
  // Schrijf poules naar sheet
  schrijfPoules(poulesSheet, groepen, pouleSettings);
  
  // Bereken het aantal wedstrijden per gewichtsklasse
  const totaalWedstrijden = berekenWedstrijdenPerGewichtsklasse();
  
  SpreadsheetApp.getUi().alert(
    "Poule-indeling voltooid",
    `Poule-indeling is voltooid. Totaal aantal wedstrijden: ${totaalWedstrijden}.`,
    SpreadsheetApp.getUi().ButtonSet.OK
  );
}