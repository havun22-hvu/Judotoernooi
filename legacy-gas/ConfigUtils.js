// ConfigUtils.js - Configuratie hulpfuncties
// WestFries Open JudoToernooi - Judoschool Cees Veen

// Globale variabelen voor gecachte configuratie
let LEEFTIJDSKLASSEN_CACHE = {};
let BAND_COMBINATIES_CACHE = {};

/**
 * Constanten voor bandkleuren (kyu-waarden)
 */
const BANDEN = {
  "wit": 6,
  "geel": 5,
  "oranje": 4,
  "groen": 3,
  "blauw": 2,
  "bruin": 1,
  "zwart": 0,
  "onbekend": "X"
};

/**
 * Constanten voor poule-instellingen
 */
const POULE_SETTINGS = {
  MIN_JUDOKAS: 3,
  OPTIMAL_JUDOKAS: 5,
  MAX_JUDOKAS: 6
};

/**
 * Hulpfunctie om het aantal blokken te krijgen uit de configuratie
 * @return {number} Het aantal blokken
 */
function getAantalBlokken() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");
  if (!configSheet) return 6; // Default waarde
  return configSheet.getRange("B14").getValue() || 6;
}

/**
 * Hulpfunctie om het aantal matten te krijgen uit de configuratie
 * @return {number} Het aantal matten
 */
function getAantalMatten() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");
  if (!configSheet) return 7; // Default waarde
  return configSheet.getRange("B11").getValue() || 7;
}

/**
 * Geeft de banden constante terug
 * @return {Object} De banden constante
 */
function getBanden() {
  return BANDEN;
}

/**
 * Geeft de poule settings terug
 * @return {Object} De poule settings
 */
function getPouleSettings() {
  return POULE_SETTINGS;
}

/**
 * Haalt de ingestelde gewichtstoleratiemarge op uit de configuratie
 * @return {number} De tolerantiemarge in kg (standaard 0.5 als niet ingesteld)
 */
function getGewichtsToleratie() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");

  if (configSheet) {
    const toleratie = configSheet.getRange("B28").getValue();
    if (!isNaN(toleratie) && toleratie > 0) {
      return parseFloat(toleratie);
    }
  }

  return 0.5; // Standaardwaarde
}

/**
 * Laadt de leeftijdsklassen en gewichtsklassen uit het ToernooiConfig blad
 * @return {boolean} True als succesvol geladen, anders false
 */
function laadConfiguratie() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const configSheet = ss.getSheetByName("ToernooiConfig");
  if (!configSheet) {
    return false;
  }

  // Standaardwaarden
  const defaultLeeftijdsklassen = {
    "Mini's": {
      code: "M",
      maxLeeftijd: 8,
      gewichtsklassen: [-20, -23, -26, -29, 29]
    },
    "A-pupillen": {
      code: "A",
      maxLeeftijd: 10,
      gewichtsklassen: [-24, -27, -30, -34, -38, 38]
    },
    "B-pupillen": {
      code: "B",
      maxLeeftijd: 12,
      gewichtsklassen: [-27, -30, -34, -38, -42, -46, -50, 50]
    },
    "Dames -15": {
      code: "D",
      maxLeeftijd: 15,
      gewichtsklassen: [-36, -40, -44, -48, -52, -56, -63, 63]
    },
    "Heren -15": {
      code: "H",
      maxLeeftijd: 15,
      gewichtsklassen: [-34, -38, -42, -46, -50, -55, -60, -66, 66]
    }
  };

  const defaultBandCombinaties = {
    "Mini's": [[0, 1, 2, 3, 4, 5, 6]],
    "A-pupillen": [[6], [0, 1, 2, 3, 4, 5]],
    "B-pupillen": [[5, 6], [0, 1, 2, 3, 4]],
    "Dames -15": [[3, 4, 5, 6], [1, 2]],
    "Heren -15": [[3, 4, 5, 6], [1, 2]]
  };

  try {
    const lastRow = configSheet.getLastRow();
    let leeftijdsSectieStart = -1;
    let bandCombinatiesSectieStart = -1;

    for (let i = 1; i <= lastRow; i++) {
      const value = configSheet.getRange(i, 1).getValue();
      if (value === "LEEFTIJDS- EN GEWICHTSKLASSEN") {
        leeftijdsSectieStart = i;
      } else if (value === "BAND COMBINATIEREGELS") {
        bandCombinatiesSectieStart = i;
      }
    }

    if (leeftijdsSectieStart === -1 || bandCombinatiesSectieStart === -1) {
      LEEFTIJDSKLASSEN_CACHE = defaultLeeftijdsklassen;
      BAND_COMBINATIES_CACHE = defaultBandCombinaties;
      return false;
    }

    // Lees leeftijdsklassen
    LEEFTIJDSKLASSEN_CACHE = {};
    for (let i = leeftijdsSectieStart + 1; i < bandCombinatiesSectieStart - 1; i++) {
      const naam = configSheet.getRange(i, 1).getValue();
      if (!naam || naam.trim() === "") continue;

      const leeftijdInfo = configSheet.getRange(i, 2).getValue();
      const maxLeeftijdMatch = leeftijdInfo.match(/jonger dan (\d+) jaar/i);
      const maxLeeftijd = maxLeeftijdMatch ? parseInt(maxLeeftijdMatch[1]) : 0;

      const gewichtsklassenInfo = configSheet.getRange(i, 3).getValue();
      const gewichtsklassenMatch = gewichtsklassenInfo.match(/Gewichtsklassen: (.+)/i);
      const gewichtsklassenStr = gewichtsklassenMatch ? gewichtsklassenMatch[1] : "";

      const gewichtsklassen = [];
      const gewichtenParts = gewichtsklassenStr.split(",");

      for (const part of gewichtenParts) {
        const trimmedPart = part.trim();
        if (trimmedPart.startsWith("+")) {
          const gewicht = parseInt(trimmedPart.match(/\+(\d+) kg/)[1]);
          gewichtsklassen.push(gewicht);
        } else {
          const gewicht = parseInt(trimmedPart.match(/(\d+) kg/)[1]);
          gewichtsklassen.push(-gewicht);
        }
      }

      let code;
      if (naam === "Mini's") code = "M";
      else if (naam === "A-pupillen") code = "A";
      else if (naam === "B-pupillen") code = "B";
      else if (naam === "Dames -15") code = "D";
      else if (naam === "Heren -15") code = "H";
      else code = naam.charAt(0);

      LEEFTIJDSKLASSEN_CACHE[naam] = {
        code: code,
        maxLeeftijd: maxLeeftijd,
        gewichtsklassen: gewichtsklassen
      };
    }

    // Lees band combinaties
    BAND_COMBINATIES_CACHE = {};
    for (const leeftijdsklasse in LEEFTIJDSKLASSEN_CACHE) {
      if (defaultBandCombinaties[leeftijdsklasse]) {
        BAND_COMBINATIES_CACHE[leeftijdsklasse] = defaultBandCombinaties[leeftijdsklasse];
      } else {
        BAND_COMBINATIES_CACHE[leeftijdsklasse] = [[0, 1, 2, 3, 4, 5, 6]];
      }
    }

    for (let i = bandCombinatiesSectieStart + 1; i <= lastRow; i++) {
      const regel = configSheet.getRange(i, 1).getValue();
      if (!regel || regel.trim() === "") break;

      const match = regel.match(/([^:]+): (.+)/);
      if (!match) continue;

      const leeftijdsklasse = match[1].trim();
      const combinatieStr = match[2].trim();
      const combinaties = combinatieStr.split(";");
      const combinatieArray = [];

      for (const combinatie of combinaties) {
        const bandCodes = [];
        const trimmedCombinatie = combinatie.trim().toLowerCase();

        if (trimmedCombinatie.includes("alle banden samen")) {
          combinatieArray.push([0, 1, 2, 3, 4, 5, 6]);
        } else if (trimmedCombinatie.includes("witte banden apart")) {
          bandCodes.push(6);
        } else if (trimmedCombinatie.includes("wit en geel samen")) {
          bandCodes.push(5, 6);
        } else if (trimmedCombinatie.includes("wit t/m groen samen")) {
          bandCodes.push(3, 4, 5, 6);
        } else if (trimmedCombinatie.includes("geel en hoger samen")) {
          bandCodes.push(0, 1, 2, 3, 4, 5);
        } else if (trimmedCombinatie.includes("oranje en hoger samen")) {
          bandCodes.push(0, 1, 2, 3, 4);
        } else if (trimmedCombinatie.includes("blauw en bruin samen")) {
          bandCodes.push(1, 2);
        }

        if (bandCodes.length > 0) {
          combinatieArray.push(bandCodes);
        }
      }

      if (combinatieArray.length > 0 && LEEFTIJDSKLASSEN_CACHE[leeftijdsklasse]) {
        BAND_COMBINATIES_CACHE[leeftijdsklasse] = combinatieArray;
      }
    }

    return true;
  } catch (e) {
    Logger.log("Fout bij het laden van configuratie: " + e);
    LEEFTIJDSKLASSEN_CACHE = defaultLeeftijdsklassen;
    BAND_COMBINATIES_CACHE = defaultBandCombinaties;
    return false;
  }
}

/**
 * Geeft de leeftijdsklassen terug
 * @return {Object} De leeftijdsklassen
 */
function getLeeftijdsklassen() {
  if (Object.keys(LEEFTIJDSKLASSEN_CACHE).length === 0) {
    laadConfiguratie();
  }
  return LEEFTIJDSKLASSEN_CACHE;
}

/**
 * Geeft de band combinaties terug
 * @return {Object} De band combinaties
 */
function getBandCombinaties() {
  if (Object.keys(BAND_COMBINATIES_CACHE).length === 0) {
    laadConfiguratie();
  }
  return BAND_COMBINATIES_CACHE;
}
