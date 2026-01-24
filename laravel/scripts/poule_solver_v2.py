#!/usr/bin/env python3
"""
Poule Solver V2 - Cascading Band Verdeling
==========================================

Algoritme:
1. INVENTARISEER: Tel per band, combineer aangrenzende
2. BEREKEN: Hoeveel poules + restanten per groep
3. SELECTEER RESTANTEN: Grensgevallen (band↓, gewicht↓, leeftijd↓)
4. VALIDEER: Past restant qua leeftijd in volgende groep? Zo niet: wissel
5. VERDEEL BLIJVERS: Maak poules, groepeer op band/gewicht
6. CASCADE: Restanten → volgende groep, herhaal

Input: JSON via stdin
Output: JSON via stdout
"""

import sys
import json
import logging
from dataclasses import dataclass, field
from typing import List, Dict, Set, Optional, Tuple
from collections import defaultdict

# Debug logging naar stderr (stdout is voor JSON output)
logging.basicConfig(
    level=logging.DEBUG,
    format='[DEBUG] %(message)s',
    stream=sys.stderr
)


@dataclass
class Judoka:
    id: int
    leeftijd: int
    gewicht: float
    band: int = 0
    club_id: int = 0

    def __repr__(self):
        return f"J{self.id}({self.leeftijd}j,{self.gewicht}kg,b{self.band})"


@dataclass
class Poule:
    judokas: List[Judoka] = field(default_factory=list)

    @property
    def size(self) -> int:
        return len(self.judokas)

    @property
    def min_gewicht(self) -> float:
        return min(j.gewicht for j in self.judokas) if self.judokas else 0

    @property
    def max_gewicht(self) -> float:
        return max(j.gewicht for j in self.judokas) if self.judokas else 0

    @property
    def gewicht_range(self) -> float:
        return self.max_gewicht - self.min_gewicht if self.judokas else 0

    @property
    def min_leeftijd(self) -> int:
        return min(j.leeftijd for j in self.judokas) if self.judokas else 0

    @property
    def max_leeftijd(self) -> int:
        return max(j.leeftijd for j in self.judokas) if self.judokas else 0


# =============================================================================
# STAP 0: INVENTARISATIE
# =============================================================================

def inventariseer_per_band(judokas: List[Judoka]) -> Dict[int, List[Judoka]]:
    """Groepeer judoka's per band niveau."""
    per_band = defaultdict(list)
    for j in judokas:
        per_band[j.band].append(j)
    return dict(per_band)


def combineer_aangrenzende_banden(
    per_band: Dict[int, List[Judoka]],
    max_band: int
) -> List[Tuple[List[int], List[Judoka]]]:
    """
    Combineer aangrenzende band niveaus tot groepen.

    Returns: [(band_niveaus, judokas), ...]
    """
    if not per_band:
        return []

    band_niveaus = sorted(per_band.keys())
    groepen = []

    i = 0
    while i < len(band_niveaus):
        start_band = band_niveaus[i]
        groep_banden = [start_band]
        groep_judokas = list(per_band[start_band])

        # Voeg aangrenzende banden toe zolang binnen max_band
        while i + 1 < len(band_niveaus):
            volgende_band = band_niveaus[i + 1]
            if max_band > 0 and (volgende_band - start_band) > max_band:
                break
            groep_banden.append(volgende_band)
            groep_judokas.extend(per_band[volgende_band])
            i += 1

        groepen.append((groep_banden, groep_judokas))
        i += 1

    return groepen


# =============================================================================
# STAP 1-2: BEREKEN EN SELECTEER RESTANTEN
# =============================================================================

def bereken_aantal_restanten(totaal: int, ideale_grootte: int) -> int:
    """Bereken hoeveel restanten er zijn na verdeling."""
    if totaal <= 0:
        return 0
    aantal_poules = totaal // ideale_grootte
    if aantal_poules == 0:
        return totaal  # Alles is restant (te weinig voor 1 poule)
    return totaal - (aantal_poules * ideale_grootte)


def selecteer_restanten(
    judokas: List[Judoka],
    aantal_restanten: int,
    volgende_groep_leeftijden: Optional[Tuple[int, int]],  # (min, max) of None
    max_lft: int
) -> Tuple[List[Judoka], List[Judoka]]:
    """
    Selecteer restanten die doorschuiven naar volgende groep.

    Restanten = grensgevallen: hoogste band, zwaarste, oudste
    MAAR: moeten wel qua leeftijd passen in volgende groep!

    Returns: (blijvers, restanten)
    """
    if aantal_restanten <= 0 or not judokas:
        return judokas, []

    if aantal_restanten >= len(judokas):
        return [], judokas

    # Sorteer op: band↓, gewicht↓, leeftijd↓ (grensgevallen bovenaan)
    gesorteerd = sorted(judokas, key=lambda j: (-j.band, -j.gewicht, -j.leeftijd))

    restanten = []
    kandidaat_idx = 0

    while len(restanten) < aantal_restanten and kandidaat_idx < len(gesorteerd):
        kandidaat = gesorteerd[kandidaat_idx]

        # Check of kandidaat qua leeftijd past in volgende groep
        past_in_volgende = True
        if volgende_groep_leeftijden:
            min_lft_volgende, max_lft_volgende = volgende_groep_leeftijden
            # Kandidaat moet kunnen matchen met iemand in volgende groep
            # Dat betekent: leeftijdsverschil <= max_lft
            if kandidaat.leeftijd < min_lft_volgende - max_lft:
                past_in_volgende = False
            if kandidaat.leeftijd > max_lft_volgende + max_lft:
                past_in_volgende = False

        if past_in_volgende:
            restanten.append(kandidaat)
        else:
            # Zoek alternatief: zelfde band, bovenklasse gewicht, wel passende leeftijd
            alternatief = zoek_alternatief_restant(
                gesorteerd,
                kandidaat,
                restanten,
                volgende_groep_leeftijden,
                max_lft
            )
            if alternatief:
                restanten.append(alternatief)
            # Als geen alternatief: kandidaat blijft hier (wordt geen restant)

        kandidaat_idx += 1

    # Blijvers = alle judoka's die niet in restanten zitten
    restant_ids = {j.id for j in restanten}
    blijvers = [j for j in judokas if j.id not in restant_ids]

    return blijvers, restanten


def zoek_alternatief_restant(
    gesorteerd: List[Judoka],
    origineel: Judoka,
    al_restant: List[Judoka],
    volgende_groep_leeftijden: Optional[Tuple[int, int]],
    max_lft: int
) -> Optional[Judoka]:
    """
    Zoek alternatief voor restant die niet past qua leeftijd.

    Criteria:
    - Zelfde band (of hoogste beschikbare)
    - In bovenklasse qua gewicht (niet de lichtste)
    - WEL passende leeftijd voor volgende groep
    """
    if not volgende_groep_leeftijden:
        return None

    min_lft_volgende, max_lft_volgende = volgende_groep_leeftijden
    al_restant_ids = {j.id for j in al_restant}

    # Zoek in gesorteerde lijst (al gesorteerd op band↓, gewicht↓, leeftijd↓)
    for kandidaat in gesorteerd:
        if kandidaat.id == origineel.id:
            continue
        if kandidaat.id in al_restant_ids:
            continue

        # Check: zelfde of hogere band als origineel
        if kandidaat.band < origineel.band:
            continue

        # Check: niet de allerlichste (bovenste helft qua gewicht)
        # Dit is al gegarandeerd door sortering op gewicht↓

        # Check: past qua leeftijd in volgende groep
        if kandidaat.leeftijd < min_lft_volgende - max_lft:
            continue
        if kandidaat.leeftijd > max_lft_volgende + max_lft:
            continue

        return kandidaat

    return None


# =============================================================================
# STAP 3: VERDEEL BLIJVERS
# =============================================================================

def verdeel_in_poules(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int],
    prioriteiten: List[str] = None
) -> List[Poule]:
    """
    Verdeel judoka's in poules binnen alle constraints.
    Groepeer zoveel mogelijk op band en gewicht volgens prioriteiten.
    """
    if not judokas:
        return []

    ideale_grootte = voorkeur[0] if voorkeur else 5
    max_size = max(voorkeur) if voorkeur else 6

    # Sorteer op prioriteiten (default: band eerst, dan gewicht, dan leeftijd)
    if prioriteiten is None:
        prioriteiten = ['band', 'gewicht', 'leeftijd']

    def sort_key(j: Judoka):
        keys = []
        for p in prioriteiten:
            if p == 'band':
                keys.append(j.band)
            elif p == 'gewicht':
                keys.append(j.gewicht)
            elif p == 'leeftijd':
                keys.append(j.leeftijd)
        return tuple(keys)

    gesorteerd = sorted(judokas, key=sort_key)

    poules = []
    geplaatst: Set[int] = set()

    while len(geplaatst) < len(gesorteerd):
        # Vind eerste niet-geplaatste als anchor
        anchor = None
        for j in gesorteerd:
            if j.id not in geplaatst:
                anchor = j
                break

        if not anchor:
            break

        # Verzamel kandidaten die bij anchor passen
        poule_judokas = [anchor]

        for j in gesorteerd:
            if j.id in geplaatst or j.id == anchor.id:
                continue
            if len(poule_judokas) >= ideale_grootte:
                break

            # Check of j past bij ALLE judoka's in de poule
            past = True
            for pj in poule_judokas:
                if abs(j.gewicht - pj.gewicht) > max_kg:
                    past = False
                    break
                if abs(j.leeftijd - pj.leeftijd) > max_lft:
                    past = False
                    break
                if max_band > 0 and abs(j.band - pj.band) > max_band:
                    past = False
                    break

            if past:
                poule_judokas.append(j)

        poules.append(Poule(judokas=poule_judokas))
        for j in poule_judokas:
            geplaatst.add(j.id)

    return poules


def optimaliseer_kleine_poules(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Optimaliseer verdeling om kleine poules (orphans) te voorkomen.

    Strategie:
    1. Identificeer kleine poules (size < 3)
    2. Probeer judokas uit kleine poule naar grotere poules te verplaatsen
    3. Of: merge twee kleine poules als ze samen passen
    4. Of: herverdeel door anchor-shift (pak judoka van grote poule)
    """
    if not poules:
        return poules

    min_size = min(voorkeur) if voorkeur else 3
    max_size = max(voorkeur) if voorkeur else 6

    # Blijf optimaliseren tot geen verbetering meer
    verbeterd = True
    iteraties = 0
    max_iteraties = 10

    while verbeterd and iteraties < max_iteraties:
        verbeterd = False
        iteraties += 1

        # Vind kleine poules
        kleine_poules = [p for p in poules if p.size < min_size and p.size > 0]
        grote_poules = [p for p in poules if p.size >= min_size]

        if not kleine_poules:
            break

        for kleine in kleine_poules[:]:  # Copy list
            if kleine not in poules:
                continue

            # STRATEGIE 1: Verplaats naar grote poule met ruimte
            for judoka in kleine.judokas[:]:
                for grote in grote_poules:
                    if grote.size >= max_size:
                        continue

                    # Check of judoka past
                    past = True
                    for pj in grote.judokas:
                        if abs(judoka.gewicht - pj.gewicht) > max_kg:
                            past = False
                            break
                        if abs(judoka.leeftijd - pj.leeftijd) > max_lft:
                            past = False
                            break
                        if max_band > 0 and abs(judoka.band - pj.band) > max_band:
                            past = False
                            break

                    if past:
                        kleine.judokas.remove(judoka)
                        grote.judokas.append(judoka)
                        verbeterd = True
                        logging.debug(f"    [OPT] J{judoka.id} verplaatst naar grotere poule")
                        break

            # Verwijder lege poules
            if kleine.size == 0:
                poules.remove(kleine)
                continue

            # STRATEGIE 2: Merge twee kleine poules
            if kleine.size < min_size:
                for andere_kleine in kleine_poules:
                    if andere_kleine is kleine or andere_kleine not in poules:
                        continue
                    if kleine.size + andere_kleine.size > max_size:
                        continue

                    # Check of alle judokas compatibel zijn
                    alle_judokas = kleine.judokas + andere_kleine.judokas
                    compatibel = True
                    for i, j1 in enumerate(alle_judokas):
                        for j2 in alle_judokas[i+1:]:
                            if abs(j1.gewicht - j2.gewicht) > max_kg:
                                compatibel = False
                                break
                            if abs(j1.leeftijd - j2.leeftijd) > max_lft:
                                compatibel = False
                                break
                            if max_band > 0 and abs(j1.band - j2.band) > max_band:
                                compatibel = False
                                break
                        if not compatibel:
                            break

                    if compatibel:
                        kleine.judokas.extend(andere_kleine.judokas)
                        poules.remove(andere_kleine)
                        verbeterd = True
                        logging.debug(f"    [OPT] Kleine poules samengevoegd")
                        break

            # STRATEGIE 3: Steel judoka van grote poule om kleine te vullen
            if kleine.size < min_size and kleine.size > 0:
                for grote in grote_poules:
                    if grote.size <= min_size:
                        continue  # Niet stelen van net-voldoende poules

                    # Zoek judoka in grote die past bij kleine
                    for judoka in grote.judokas:
                        past_in_kleine = True
                        for pj in kleine.judokas:
                            if abs(judoka.gewicht - pj.gewicht) > max_kg:
                                past_in_kleine = False
                                break
                            if abs(judoka.leeftijd - pj.leeftijd) > max_lft:
                                past_in_kleine = False
                                break
                            if max_band > 0 and abs(judoka.band - pj.band) > max_band:
                                past_in_kleine = False
                                break

                        if past_in_kleine:
                            grote.judokas.remove(judoka)
                            kleine.judokas.append(judoka)
                            verbeterd = True
                            logging.debug(f"    [OPT] J{judoka.id} gestolen van grote poule")
                            break
                    if verbeterd:
                        break

    return [p for p in poules if p.size > 0]


# =============================================================================
# STAP 4: PLAATS RESTANTEN IN LICHTSTE POULES
# =============================================================================

def plaats_in_lichtste_poules(
    restanten: List[Judoka],
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    max_band: int,
    max_size: int
) -> List[Judoka]:
    """
    Plaats restanten (hogere band) in lichtste poules.
    Hogere band + lichtere tegenstanders = eerlijker.

    Returns: Judoka's die niet geplaatst konden worden
    """
    if not restanten or not poules:
        return restanten

    # Sorteer poules op gemiddeld gewicht (lichtste eerst)
    poules_sorted = sorted(poules, key=lambda p: p.min_gewicht)

    niet_geplaatst = []

    for judoka in restanten:
        geplaatst = False

        for poule in poules_sorted:
            if poule.size >= max_size:
                continue

            # Check of judoka past
            past = True
            for pj in poule.judokas:
                if abs(judoka.gewicht - pj.gewicht) > max_kg:
                    past = False
                    break
                if abs(judoka.leeftijd - pj.leeftijd) > max_lft:
                    past = False
                    break
                if max_band > 0 and abs(judoka.band - pj.band) > max_band:
                    past = False
                    break

            if past:
                poule.judokas.append(judoka)
                geplaatst = True
                break

        if not geplaatst:
            niet_geplaatst.append(judoka)

    return niet_geplaatst


# =============================================================================
# HOOFDALGORITME: CASCADE VERDELING
# =============================================================================

def cascade_verdeel(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int],
    prioriteiten: List[str] = None
) -> Tuple[List[Poule], List[Judoka]]:
    """
    Hoofdalgoritme: Cascading Band Verdeling.

    1. Inventariseer per band
    2. Combineer aangrenzende banden
    3. Per groep: bepaal restanten vooraf, verdeel blijvers
    4. Cascade restanten naar volgende groep
    """
    if not judokas:
        return [], []

    if prioriteiten is None:
        prioriteiten = ['band', 'gewicht', 'leeftijd']

    ideale_grootte = voorkeur[0] if voorkeur else 5
    max_size = max(voorkeur) if voorkeur else 6
    min_size = min(voorkeur) if voorkeur else 3

    logging.debug(f"=== CASCADE VERDEEL START ===")
    logging.debug(f"Totaal judokas: {len(judokas)}, max_kg={max_kg}, max_lft={max_lft}, max_band={max_band}")
    logging.debug(f"Prioriteiten: {prioriteiten}")

    # STAP 0: Inventariseer per band
    per_band = inventariseer_per_band(judokas)
    logging.debug(f"Per band: {[(b, len(js)) for b, js in sorted(per_band.items())]}")

    # Combineer aangrenzende banden tot groepen
    groepen = combineer_aangrenzende_banden(per_band, max_band)
    logging.debug(f"Aantal groepen na combineren: {len(groepen)}")
    for i, (banden, js) in enumerate(groepen):
        logging.debug(f"  Groep {i}: banden={banden}, judokas={len(js)}")

    alle_poules = []
    cascade_restanten = []  # Restanten van vorige groep
    alle_orphans = []

    for groep_idx, (band_niveaus, groep_judokas) in enumerate(groepen):
        logging.debug(f"--- Verwerk groep {groep_idx}: banden={band_niveaus} ---")

        # Voeg cascade restanten van vorige groep toe
        groep_judokas = groep_judokas + cascade_restanten
        logging.debug(f"  Judokas in groep (incl. cascade): {len(groep_judokas)}")

        if not groep_judokas:
            continue

        # Log alle judokas in deze groep
        for j in sorted(groep_judokas, key=lambda x: (x.band, x.gewicht)):
            logging.debug(f"    J{j.id}: band={j.band}, gewicht={j.gewicht}, lft={j.leeftijd}")

        # Bepaal leeftijdsrange van VOLGENDE groep (voor restant validatie)
        volgende_groep_leeftijden = None
        if groep_idx + 1 < len(groepen):
            _, volgende_judokas = groepen[groep_idx + 1]
            if volgende_judokas:
                volgende_leeftijden = [j.leeftijd for j in volgende_judokas]
                volgende_groep_leeftijden = (min(volgende_leeftijden), max(volgende_leeftijden))

        # STAP 1: Bereken aantal restanten
        aantal_restanten = bereken_aantal_restanten(len(groep_judokas), ideale_grootte)
        logging.debug(f"  Berekende restanten: {aantal_restanten} (totaal={len(groep_judokas)}, ideaal={ideale_grootte})")

        # Geen restanten voor laatste groep
        if groep_idx == len(groepen) - 1:
            aantal_restanten = 0
            logging.debug(f"  Laatste groep - geen restanten")

        # STAP 2: Selecteer restanten (met validatie)
        blijvers, restanten = selecteer_restanten(
            groep_judokas,
            aantal_restanten,
            volgende_groep_leeftijden,
            max_lft
        )
        logging.debug(f"  Blijvers: {len(blijvers)}, Restanten: {len(restanten)}")
        if restanten:
            logging.debug(f"  Restanten: {[f'J{j.id}(b{j.band},{j.gewicht}kg)' for j in restanten]}")

        # STAP 3: Verdeel blijvers in poules
        poules = verdeel_in_poules(blijvers, max_kg, max_lft, max_band, voorkeur, prioriteiten)
        logging.debug(f"  Poules gemaakt: {len(poules)}")
        for pi, p in enumerate(poules):
            logging.debug(f"    Poule {pi}: size={p.size}, judokas={[f'J{j.id}(b{j.band},{j.gewicht}kg)' for j in p.judokas]}")

        # STAP 4: Probeer restanten in lichtste poules te plaatsen
        # (alleen voor huidige groep, niet doorschuiven)
        # Nee, restanten schuiven door naar volgende groep!

        alle_poules.extend(poules)
        cascade_restanten = restanten

    # Laatste cascade_restanten worden orphans
    for orphan in cascade_restanten:
        alle_poules.append(Poule(judokas=[orphan]))
        alle_orphans.append(orphan)

    # STAP 5: Optimaliseer kleine poules
    logging.debug(f"=== OPTIMALISATIE FASE ===")
    logging.debug(f"  Poules voor optimalisatie: {len(alle_poules)}")
    kleine_voor = len([p for p in alle_poules if p.size < min_size])
    logging.debug(f"  Kleine poules (<{min_size}): {kleine_voor}")

    alle_poules = optimaliseer_kleine_poules(alle_poules, max_kg, max_lft, max_band, voorkeur)

    kleine_na = len([p for p in alle_poules if p.size < min_size])
    logging.debug(f"  Poules na optimalisatie: {len(alle_poules)}")
    logging.debug(f"  Kleine poules na: {kleine_na}")

    # Check voor kleine poules (< min_size) en markeer als orphan
    alle_orphans = []
    for poule in alle_poules:
        if poule.size < min_size and poule.size > 0:
            for j in poule.judokas:
                if j not in alle_orphans:
                    alle_orphans.append(j)

    return alle_poules, alle_orphans


# =============================================================================
# SCORING
# =============================================================================

def bereken_grootte_penalty(grootte: int, voorkeur: List[int]) -> int:
    """Score op basis van poule_grootte_voorkeur. Lager = beter."""
    if grootte <= 1:
        return 100
    if grootte in voorkeur:
        index = voorkeur.index(grootte)
        return 0 if index == 0 else (5 if index == 1 else 40)
    return 70


def score_indeling(poules: List[Poule], voorkeur: List[int]) -> int:
    """Bereken totale score. Lager = beter."""
    return sum(bereken_grootte_penalty(p.size, voorkeur) for p in poules)


# =============================================================================
# SOLVE
# =============================================================================

def solve(input_data: dict) -> dict:
    """Verdeel judoka's binnen één categorie in optimale poules."""
    try:
        max_kg = float(input_data.get('max_kg_verschil', 3.0))
        max_lft = int(input_data.get('max_leeftijd_verschil', 2))
        max_band = int(input_data.get('max_band_verschil', 0))
        voorkeur = input_data.get('poule_grootte_voorkeur', [5, 4, 6, 3])
        prioriteiten = input_data.get('verdeling_prioriteiten', ['band', 'gewicht', 'leeftijd'])

        judokas_data = input_data.get('judokas', [])
        judokas = [
            Judoka(
                id=j['id'],
                leeftijd=j.get('leeftijd', 0),
                gewicht=float(j.get('gewicht', 0)),
                band=j.get('band', 0),
                club_id=j.get('club_id', 0)
            )
            for j in judokas_data
        ]

        if not judokas:
            return {
                "success": True,
                "poules": [],
                "stats": {"totaal_judokas": 0, "totaal_poules": 0, "score": 0}
            }

        poules, orphans = cascade_verdeel(judokas, max_kg, max_lft, max_band, voorkeur, prioriteiten)

        poules_output = []
        grootte_counts = {}

        for p in poules:
            g = p.size
            grootte_counts[g] = grootte_counts.get(g, 0) + 1
            poules_output.append({
                "judoka_ids": [j.id for j in p.judokas],
                "gewicht_range": round(p.gewicht_range, 1),
                "size": p.size
            })

        return {
            "success": True,
            "poules": poules_output,
            "stats": {
                "totaal_judokas": len(judokas),
                "totaal_poules": len(poules),
                "score": score_indeling(poules, voorkeur),
                "grootte_verdeling": grootte_counts,
                "orphans": len(orphans)
            }
        }

    except Exception as e:
        return {"success": False, "error": str(e)}


def main():
    try:
        input_data = json.load(sys.stdin)
        result = solve(input_data)
        print(json.dumps(result, indent=2))
    except json.JSONDecodeError as e:
        print(json.dumps({"success": False, "error": f"Invalid JSON: {e}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
