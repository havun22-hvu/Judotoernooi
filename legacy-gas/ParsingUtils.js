// ParsingUtils.js - String parsing hulpfuncties
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Extraheert de leeftijdsklasse uit een pouletitel
 * @param {string} titel - De pouletitel
 * @return {string} De leeftijdsklasse
 */
function extractLeeftijdsklasseFromTitel(titel) {
  if (!titel) return "";

  // Probeer eerst exacte matches
  if (titel.includes("Mini's")) return "Mini's";
  if (titel.includes("A-pupillen")) return "A-pupillen";
  if (titel.includes("B-pupillen")) return "B-pupillen";
  if (titel.includes("Dames -15")) return "Dames -15";
  if (titel.includes("Heren -15")) return "Heren -15";

  return "";
}

/**
 * Extraheert de gewichtsklasse uit een pouletitel
 * @param {string} titel - De pouletitel
 * @return {string} De gewichtsklasse string (bijv. "-30 kg" of "+38 kg")
 */
function extractGewichtsklasseFromTitel(titel) {
  if (!titel) return "";

  const match = titel.match(/([+-]?\d+)\s*kg/i);
  if (match) {
    const gewicht = match[1];
    // Voeg + toe als er geen teken is
    if (!gewicht.startsWith('+') && !gewicht.startsWith('-')) {
      return `-${gewicht} kg`;
    }
    return `${gewicht} kg`;
  }

  return "";
}

/**
 * Extraheert de numerieke gewichtswaarde uit een pouletitel
 * @param {string} titel - De pouletitel
 * @return {number|null} Het gewicht in kg, of null als niet gevonden
 */
function extractGewichtswaarde(titel) {
  if (!titel) return null;

  const match = titel.match(/([+-]?)(\d+)\s*kg/i);
  if (match) {
    const prefix = match[1] || '-';
    const waarde = parseInt(match[2]);
    return prefix === '+' ? waarde : -waarde;
  }

  return null;
}

/**
 * Extraheert de gewichtslimiet (in kg) uit een gewichtsklasse string
 * @param {string} gewichtsklasseStr - De gewichtsklasse (bijv. "-30 kg" of "+38 kg")
 * @return {Object|null} Object met type ('min'/'plus') en waarde (in kg), of null indien ongeldig
 */
function extractGewichtLimiet(gewichtsklasseStr) {
  if (!gewichtsklasseStr) return null;

  const match = gewichtsklasseStr.match(/([+-]?)(\d+)\s*kg/i);
  if (!match) return null;

  const prefix = match[1] || "-";
  const waarde = parseInt(match[2]);

  return {
    type: prefix === "+" ? "plus" : "min",
    waarde: waarde
  };
}

/**
 * Parseert een gewichtsklasse string naar een numerieke waarde
 * @param {string} gewichtsklasse - Gewichtsklasse string (bijv. "-30 kg")
 * @return {number} Het gewicht als nummer (negatief voor -XX kg, positief voor +XX kg)
 */
function parseGewicht(gewichtsklasse) {
  if (!gewichtsklasse) return 0;

  const match = gewichtsklasse.match(/([+-]?)(\d+)/);
  if (!match) return 0;

  const prefix = match[1] || '-';
  const waarde = parseInt(match[2]);

  return prefix === '+' ? waarde : -waarde;
}

/**
 * Corrigeert naam naar juiste hoofdletters met Nederlandse tussenvoegsels
 * @param {string} naam - De naam om te corrigeren
 * @return {string} De gecorrigeerde naam
 */
function corrigeerNaamHoofdletters(naam) {
  if (!naam) return naam;

  const tussenvoegsels = ["van", "de", "der", "den", "het", "in", "ter", "ten", "te", "la", "le", "les", "op", "'t", "s", "t", "aan", "bij", "onder", "voor", "over", "tot"];
  const woorden = naam.toString().trim().split(/\s+/);
  const aantalWoorden = woorden.length;
  const gecorrigeerdeWoorden = [];

  for (let i = 0; i < aantalWoorden; i++) {
    let woord = woorden[i];
    if (!woord) continue;

    const lowerWoord = woord.toLowerCase();
    const isTussenvoegsel = tussenvoegsels.includes(lowerWoord);

    let moetkrijgenHoofdletter = true;
    if (isTussenvoegsel && i > 0 && i < aantalWoorden - 1) {
      moetkrijgenHoofdletter = false;
    }

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
 * Maakt een gestandaardiseerde pouletitel
 * @param {string} leeftijdsklasse - De leeftijdsklasse
 * @param {string} gewichtsklasse - De gewichtsklasse
 * @param {number} pouleNr - Het poulenummer
 * @return {string} De pouletitel
 */
function maakPouleTitel(leeftijdsklasse, gewichtsklasse, pouleNr) {
  const lk = leeftijdsklasse || "Onbekende leeftijdsklasse";
  const gk = gewichtsklasse || "Onbekend gewicht";
  return `${lk} ${gk} Poule ${pouleNr}`;
}

/**
 * Bepaalt de leeftijdsklasse op basis van leeftijd en geslacht
 * @param {number} leeftijd - De leeftijd van de judoka
 * @param {string} geslacht - Het geslacht ('M' of 'V')
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
