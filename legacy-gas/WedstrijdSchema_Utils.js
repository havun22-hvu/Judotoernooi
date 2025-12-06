// WedstrijdSchema_Utils.js - Utilities voor wedstrijdschema generatie
// WestFries Open JudoToernooi - Judoschool Cees Veen

/**
 * Genereert een optimale wedstrijdvolgorde voor een poule
 * Zorgt voor gelijkmatige verdeling en pauzes tussen wedstrijden per judoka
 *
 * @param {number} aantalJudokas - Het aantal judoka's in de poule
 * @returns {Array} Array van wedstrijdparen [{judoka1: nr, judoka2: nr, wedstrijdNr: nr}]
 */
function genereerOptimaleWedstrijdvolgorde(aantalJudokas) {
  // Speciale schema's per aantal judoka's voor optimale verdeling

  if (aantalJudokas === 2) {
    // 2 judoka's: 1 wedstrijd (of dubbele poule = 2 wedstrijden)
    return [
      { judoka1: 1, judoka2: 2, wedstrijdNr: 1 },
      { judoka1: 1, judoka2: 2, wedstrijdNr: 2 }  // Tweede ronde
    ];
  }

  if (aantalJudokas === 3) {
    // 3 judoka's: 3 wedstrijden (dubbele poule = 6 wedstrijden)
    return [
      { judoka1: 1, judoka2: 2, wedstrijdNr: 1 },
      { judoka1: 1, judoka2: 3, wedstrijdNr: 2 },
      { judoka1: 2, judoka2: 3, wedstrijdNr: 3 },
      // Tweede ronde
      { judoka1: 1, judoka2: 2, wedstrijdNr: 4 },
      { judoka1: 1, judoka2: 3, wedstrijdNr: 5 },
      { judoka1: 2, judoka2: 3, wedstrijdNr: 6 }
    ];
  }

  if (aantalJudokas === 4) {
    // 4 judoka's: 6 wedstrijden - optimale volgorde met rust tussen wedstrijden
    return [
      { judoka1: 1, judoka2: 2, wedstrijdNr: 1 },   // 1,2 spelen
      { judoka1: 3, judoka2: 4, wedstrijdNr: 2 },   // 3,4 spelen (1,2 rusten)
      { judoka1: 2, judoka2: 3, wedstrijdNr: 3 },   // 2,3 spelen (1,4 rusten)
      { judoka1: 1, judoka2: 4, wedstrijdNr: 4 },   // 1,4 spelen (2,3 rusten)
      { judoka1: 2, judoka2: 4, wedstrijdNr: 5 },   // 2,4 spelen (1,3 rusten)
      { judoka1: 1, judoka2: 3, wedstrijdNr: 6 }    // 1,3 spelen (2,4 rusten)
    ];
  }

  if (aantalJudokas === 5) {
    // 5 judoka's: 10 wedstrijden - optimale volgorde met rust tussen wedstrijden
    return [
      { judoka1: 1, judoka2: 2, wedstrijdNr: 1 },
      { judoka1: 3, judoka2: 4, wedstrijdNr: 2 },
      { judoka1: 1, judoka2: 5, wedstrijdNr: 3 },
      { judoka1: 2, judoka2: 3, wedstrijdNr: 4 },
      { judoka1: 4, judoka2: 5, wedstrijdNr: 5 },
      { judoka1: 1, judoka2: 3, wedstrijdNr: 6 },
      { judoka1: 2, judoka2: 4, wedstrijdNr: 7 },
      { judoka1: 3, judoka2: 5, wedstrijdNr: 8 },
      { judoka1: 1, judoka2: 4, wedstrijdNr: 9 },
      { judoka1: 2, judoka2: 5, wedstrijdNr: 10 }
    ];
  }

  if (aantalJudokas === 6) {
    // 6 judoka's: 15 wedstrijden - optimale volgorde met rust tussen wedstrijden
    return [
      { judoka1: 1, judoka2: 2, wedstrijdNr: 1 },
      { judoka1: 3, judoka2: 4, wedstrijdNr: 2 },
      { judoka1: 5, judoka2: 6, wedstrijdNr: 3 },
      { judoka1: 1, judoka2: 3, wedstrijdNr: 4 },  // Gewisseld: was 2-5
      { judoka1: 2, judoka2: 5, wedstrijdNr: 5 },  // Gewisseld: was 1-3
      { judoka1: 4, judoka2: 6, wedstrijdNr: 6 },
      { judoka1: 3, judoka2: 5, wedstrijdNr: 7 },   // Gewisseld: was 2-4
      { judoka1: 2, judoka2: 4, wedstrijdNr: 8 },   // Gewisseld: was 3-5
      { judoka1: 1, judoka2: 6, wedstrijdNr: 9 },
      { judoka1: 2, judoka2: 3, wedstrijdNr: 10 },
      { judoka1: 4, judoka2: 5, wedstrijdNr: 11 },
      { judoka1: 3, judoka2: 6, wedstrijdNr: 12 },
      { judoka1: 1, judoka2: 4, wedstrijdNr: 13 },
      { judoka1: 2, judoka2: 6, wedstrijdNr: 14 },  // Gewisseld: was 1-5
      { judoka1: 1, judoka2: 5, wedstrijdNr: 15 }   // Gewisseld: was 2-6
    ];
  }

  if (aantalJudokas === 7) {
    // 7 judoka's: 21 wedstrijden
    return genereerRoundRobinSchema(aantalJudokas);
  }

  if (aantalJudokas === 8) {
    // 8 judoka's: 28 wedstrijden
    return genereerRoundRobinSchema(aantalJudokas);
  }

  if (aantalJudokas === 9) {
    // 9 judoka's: 36 wedstrijden
    return genereerRoundRobinSchema(aantalJudokas);
  }

  if (aantalJudokas === 10) {
    // 10 judoka's: 45 wedstrijden
    return genereerRoundRobinSchema(aantalJudokas);
  }

  // Voor andere aantallen: gebruik generieke round robin
  return genereerRoundRobinSchema(aantalJudokas);
}

/**
 * Generieke Round Robin schema generator
 * Gebruikt het "Circle Method" algoritme voor optimale verdeling
 *
 * @param {number} n - Aantal judoka's
 * @returns {Array} Array van wedstrijdparen
 */
function genereerRoundRobinSchema(n) {
  const wedstrijden = [];
  let wedstrijdNr = 1;

  // Als oneven aantal, voeg dummy toe
  const judokas = [];
  for (let i = 1; i <= n; i++) {
    judokas.push(i);
  }

  if (n % 2 !== 0) {
    judokas.push(null); // Dummy voor bye
  }

  const totaal = judokas.length;

  // Round Robin met circle method
  for (let ronde = 0; ronde < totaal - 1; ronde++) {
    for (let i = 0; i < totaal / 2; i++) {
      const j = totaal - 1 - i;

      const judoka1 = judokas[i];
      const judoka2 = judokas[j];

      // Skip als één van beiden dummy is
      if (judoka1 !== null && judoka2 !== null) {
        wedstrijden.push({
          judoka1: judoka1,
          judoka2: judoka2,
          wedstrijdNr: wedstrijdNr++
        });
      }
    }

    // Roteer (behalve eerste positie blijft vast)
    const laatste = judokas.pop();
    judokas.splice(1, 0, laatste);
  }

  return wedstrijden;
}

/**
 * Converteert wedstrijdvolgorde naar een matrix voor display
 * Elke judoka heeft kolommen voor elke wedstrijd die hij/zij speelt
 *
 * @param {Array} wedstrijdvolgorde - Array van wedstrijdparen
 * @param {number} aantalJudokas - Aantal judoka's
 * @returns {Object} Matrix met wedstrijdposities per judoka
 */
function wedstrijdvolgordeNaarMatrix(wedstrijdvolgorde, aantalJudokas) {
  // Maak een matrix: [judoka][wedstrijdkolom] = {tegen: judokaX, wedstrijdNr: Y}
  const matrix = [];

  for (let i = 0; i < aantalJudokas; i++) {
    matrix[i] = [];
  }

  // Vul matrix op basis van wedstrijdvolgorde
  wedstrijdvolgorde.forEach(wedstrijd => {
    const j1 = wedstrijd.judoka1 - 1; // 0-indexed
    const j2 = wedstrijd.judoka2 - 1;

    matrix[j1].push({
      tegen: wedstrijd.judoka2,
      wedstrijdNr: wedstrijd.wedstrijdNr,
      wp: 0,
      jp: 0
    });

    matrix[j2].push({
      tegen: wedstrijd.judoka1,
      wedstrijdNr: wedstrijd.wedstrijdNr,
      wp: 0,
      jp: 0
    });
  });

  return matrix;
}

/**
 * Berekent het totaal aantal wedstrijden voor een poule
 * @param {number} aantalJudokas - Aantal judoka's
 * @returns {number} Aantal wedstrijden
 */
function berekenTotaalWedstrijden(aantalJudokas) {
  if (aantalJudokas === 3) {
    return 6; // Dubbele poule
  }
  // n * (n-1) / 2
  return (aantalJudokas * (aantalJudokas - 1)) / 2;
}

/**
 * Controleert of een wedstrijdvolgorde valide is
 * (Elke judoka speelt precies 1x tegen elke andere judoka)
 *
 * @param {Array} wedstrijdvolgorde - Array van wedstrijdparen
 * @param {number} aantalJudokas - Aantal judoka's
 * @returns {boolean} True als valide
 */
function valideerWedstrijdvolgorde(wedstrijdvolgorde, aantalJudokas) {
  const verwachtAantal = berekenTotaalWedstrijden(aantalJudokas);

  if (wedstrijdvolgorde.length !== verwachtAantal) {
    return false;
  }

  // Check of elke combinatie precies 1x voorkomt
  const combinaties = new Set();

  for (const wedstrijd of wedstrijdvolgorde) {
    const j1 = Math.min(wedstrijd.judoka1, wedstrijd.judoka2);
    const j2 = Math.max(wedstrijd.judoka1, wedstrijd.judoka2);
    const key = `${j1}-${j2}`;

    if (combinaties.has(key)) {
      return false; // Duplicaat
    }

    combinaties.add(key);
  }

  return true;
}

/**
 * Test functie om wedstrijdschema's te valideren
 */
function testWedstrijdSchemas() {
  const ui = SpreadsheetApp.getUi();

  let resultaat = "TEST RESULTATEN WEDSTRIJDSCHEMA'S\n\n";

  for (let n = 3; n <= 10; n++) {
    const schema = genereerOptimaleWedstrijdvolgorde(n);
    const verwacht = berekenTotaalWedstrijden(n);
    const valide = valideerWedstrijdvolgorde(schema, n);

    resultaat += `${n} judoka's: ${schema.length} wedstrijden (verwacht: ${verwacht}) - ${valide ? '✓ OK' : '✗ FOUT'}\n`;
  }

  ui.alert('Wedstrijdschema Test', resultaat, ui.ButtonSet.OK);
}
