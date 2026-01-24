#!/usr/bin/env python3
"""
Poule Solver V2 - Simpele Greedy met Slimme Herverdeling
=========================================================

Algoritme:
1. SORTEER: Alle judoka's op prioriteit (band → gewicht → leeftijd)
2. VERDEEL: Loop door gesorteerde lijst, maak poules greedy
3. HERVERDEEL: Fix kleine poules (1-2) als mogelijk
4. ACCEPTEER: Orphans die nergens passen

Input: JSON via stdin
Output: JSON via stdout
"""

import sys
import json
import logging
from dataclasses import dataclass, field
from typing import List, Dict, Set, Optional, Tuple

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

    @property
    def min_band(self) -> int:
        return min(j.band for j in self.judokas) if self.judokas else 0

    @property
    def max_band(self) -> int:
        return max(j.band for j in self.judokas) if self.judokas else 0


def past_in_poule(judoka: Judoka, poule: Poule, max_kg: float, max_lft: int, max_band: int) -> bool:
    """Check of judoka past bij ALLE judoka's in de poule."""
    if not poule.judokas:
        return True

    for pj in poule.judokas:
        if abs(judoka.gewicht - pj.gewicht) > max_kg:
            return False
        if abs(judoka.leeftijd - pj.leeftijd) > max_lft:
            return False
        if max_band > 0 and abs(judoka.band - pj.band) > max_band:
            return False

    return True


def sorteer_judokas(judokas: List[Judoka], prioriteiten: List[str]) -> List[Judoka]:
    """Sorteer judoka's op prioriteiten (laag naar hoog)."""

    def sort_key(j: Judoka):
        keys = []
        for p in prioriteiten:
            if p == 'band':
                keys.append(j.band)  # laag (wit=0) eerst
            elif p == 'gewicht':
                keys.append(j.gewicht)  # licht eerst
            elif p == 'leeftijd':
                keys.append(j.leeftijd)  # jong eerst
        return tuple(keys)

    return sorted(judokas, key=sort_key)


# =============================================================================
# STAP 1: GREEDY VERDELING
# =============================================================================

def verdeel_greedy(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    ideale_grootte: int,
    max_grootte: int
) -> List[Poule]:
    """
    Slimme greedy verdeling:
    - Start met eerste judoka
    - Zoek in ALLE overgebleven judoka's wie het beste past
    - Vul poule tot ideale grootte
    - Herhaal
    """
    if not judokas:
        return []

    poules = []
    overgebleven = list(judokas)

    while overgebleven:
        # Start nieuwe poule met eerste overgebleven judoka
        anchor = overgebleven.pop(0)
        huidige_poule = Poule(judokas=[anchor])

        # Zoek passende judoka's tot poule vol is
        while huidige_poule.size < ideale_grootte and overgebleven:
            beste_match = None
            beste_score = float('inf')

            for kandidaat in overgebleven:
                if not past_in_poule(kandidaat, huidige_poule, max_kg, max_lft, max_band):
                    continue

                # Score: hoe dicht bij huidige poule? (lager = beter)
                # Prioriteer: zelfde band, dicht gewicht
                band_verschil = abs(kandidaat.band - huidige_poule.min_band)
                gewicht_verschil = abs(kandidaat.gewicht - huidige_poule.min_gewicht)
                score = band_verschil * 100 + gewicht_verschil

                if score < beste_score:
                    beste_score = score
                    beste_match = kandidaat

            if beste_match:
                overgebleven.remove(beste_match)
                huidige_poule.judokas.append(beste_match)
            else:
                break  # Niemand past meer, sluit poule af

        poules.append(huidige_poule)

    return poules


# =============================================================================
# STAP 2: SLIMME HERVERDELING
# =============================================================================

def herverdeel_kleine_poules(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Fix kleine poules (< min_voorkeur) door:
    1. Kleine poules samenvoegen als ze compatibel zijn
    2. Judoka's stelen van grote poules (> ideale grootte) om kleine te vullen

    Orphans (poules van 1-2 die nergens passen) blijven bestaan.
    """
    if not poules:
        return poules

    min_size = min(voorkeur) if voorkeur else 3
    max_size = max(voorkeur) if voorkeur else 6
    ideale_size = voorkeur[0] if voorkeur else 5

    max_iteraties = 10
    verplaatste_judokas: Set[int] = set()  # Track wie al verplaatst is

    for iteratie in range(max_iteraties):
        verbeterd = False

        # Identificeer kleine poules
        kleine_poules = [p for p in poules if 0 < p.size < min_size]

        if not kleine_poules:
            logging.debug(f"  Iteratie {iteratie}: geen kleine poules meer")
            break

        logging.debug(f"  Iteratie {iteratie}: {len(kleine_poules)} kleine poules")

        for kleine in kleine_poules[:]:
            if kleine not in poules or kleine.size == 0:
                continue

            # STRATEGIE 1: Merge twee kleine poules
            merged = False
            for andere in kleine_poules:
                if andere is kleine or andere not in poules:
                    continue
                if kleine.size + andere.size > max_size:
                    continue

                # Check compatibiliteit
                alle_compatibel = True
                for j1 in kleine.judokas:
                    for j2 in andere.judokas:
                        if abs(j1.gewicht - j2.gewicht) > max_kg:
                            alle_compatibel = False
                            break
                        if abs(j1.leeftijd - j2.leeftijd) > max_lft:
                            alle_compatibel = False
                            break
                        if max_band > 0 and abs(j1.band - j2.band) > max_band:
                            alle_compatibel = False
                            break
                    if not alle_compatibel:
                        break

                if alle_compatibel:
                    kleine.judokas.extend(andere.judokas)
                    poules.remove(andere)
                    verbeterd = True
                    merged = True
                    logging.debug(f"    Kleine poules samengevoegd → size {kleine.size}")
                    break

            if merged:
                continue

            # STRATEGIE 2: Steel judoka van TE GROTE poule (> ideale grootte)
            # Alleen stelen van poules die groter zijn dan ideaal
            if kleine.size < min_size and kleine in poules:
                grote_poules = [p for p in poules if p.size > ideale_size]

                for grote in grote_poules:
                    # Zoek judoka in grote die past bij kleine EN nog niet verplaatst
                    for judoka in grote.judokas:
                        if judoka.id in verplaatste_judokas:
                            continue

                        if past_in_poule(judoka, kleine, max_kg, max_lft, max_band):
                            grote.judokas.remove(judoka)
                            kleine.judokas.append(judoka)
                            verplaatste_judokas.add(judoka.id)
                            verbeterd = True
                            logging.debug(f"    J{judoka.id} gestolen van grote poule (was {grote.size + 1})")
                            break

                    if kleine.size >= min_size:
                        break  # Kleine is nu groot genoeg

        if not verbeterd:
            logging.debug(f"  Geen verbetering meer na {iteratie + 1} iteraties")
            break

    # Filter lege poules
    return [p for p in poules if p.size > 0]


# =============================================================================
# HOOFDALGORITME
# =============================================================================

def verdeel_judokas(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int],
    prioriteiten: List[str]
) -> Tuple[List[Poule], List[Judoka]]:
    """
    Hoofdalgoritme:
    1. Sorteer op prioriteiten (band → gewicht → leeftijd)
    2. Verdeel greedy
    3. Herverdeel kleine poules
    4. Markeer orphans
    """
    if not judokas:
        return [], []

    ideale_grootte = voorkeur[0] if voorkeur else 5
    max_grootte = max(voorkeur) if voorkeur else 6
    min_grootte = min(voorkeur) if voorkeur else 3

    logging.debug(f"=== VERDEEL START ===")
    logging.debug(f"Judokas: {len(judokas)}, max_kg={max_kg}, max_lft={max_lft}, max_band={max_band}")
    logging.debug(f"Prioriteiten: {prioriteiten}, voorkeur: {voorkeur}")

    # STAP 1: Sorteer
    gesorteerd = sorteer_judokas(judokas, prioriteiten)
    logging.debug(f"Gesorteerd: {[f'J{j.id}(b{j.band},{j.gewicht}kg)' for j in gesorteerd[:10]]}...")

    # STAP 2: Greedy verdeling
    poules = verdeel_greedy(gesorteerd, max_kg, max_lft, max_band, ideale_grootte, max_grootte)
    logging.debug(f"Na greedy: {len(poules)} poules")
    for i, p in enumerate(poules):
        logging.debug(f"  Poule {i}: size={p.size}, range={p.gewicht_range:.1f}kg, band={p.min_band}-{p.max_band}")

    # STAP 3: Herverdeel kleine poules
    kleine_voor = len([p for p in poules if p.size < min_grootte])
    logging.debug(f"Kleine poules voor herverdeling: {kleine_voor}")

    poules = herverdeel_kleine_poules(poules, max_kg, max_lft, max_band, voorkeur)

    kleine_na = len([p for p in poules if p.size < min_grootte])
    logging.debug(f"Kleine poules na herverdeling: {kleine_na}")

    # STAP 4: Identificeer orphans
    orphans = []
    for poule in poules:
        if poule.size < min_grootte:
            orphans.extend(poule.judokas)

    logging.debug(f"=== RESULTAAT ===")
    logging.debug(f"Poules: {len(poules)}, Orphans: {len(orphans)}")

    return poules, orphans


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
    return 70  # Niet in voorkeur (bijv. poule van 2)


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

        # Filter: splits complete en onvolledige judoka's
        complete_judokas = []
        onvolledige_judokas = []

        for j in judokas_data:
            gewicht = j.get('gewicht')
            leeftijd = j.get('leeftijd')

            # Check of essentiële velden aanwezig zijn
            is_onvolledig = (
                gewicht is None or gewicht == 0 or
                leeftijd is None or leeftijd == 0
            )

            if is_onvolledig:
                onvolledige_judokas.append(j['id'])
                logging.debug(f"  Onvolledige judoka J{j['id']}: gewicht={gewicht}, leeftijd={leeftijd}")
            else:
                complete_judokas.append(
                    Judoka(
                        id=j['id'],
                        leeftijd=leeftijd,
                        gewicht=float(gewicht),
                        band=j.get('band', 0),
                        club_id=j.get('club_id', 0)
                    )
                )

        if onvolledige_judokas:
            logging.debug(f"=== {len(onvolledige_judokas)} ONVOLLEDIGE JUDOKA'S UITGESLOTEN ===")

        if not complete_judokas:
            return {
                "success": True,
                "poules": [],
                "onvolledige_judokas": onvolledige_judokas,
                "stats": {
                    "totaal_judokas": len(judokas_data),
                    "complete_judokas": 0,
                    "onvolledige_judokas": len(onvolledige_judokas),
                    "totaal_poules": 0,
                    "score": 0
                }
            }

        poules, orphans = verdeel_judokas(complete_judokas, max_kg, max_lft, max_band, voorkeur, prioriteiten)

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
            "onvolledige_judokas": onvolledige_judokas,
            "stats": {
                "totaal_judokas": len(judokas_data),
                "complete_judokas": len(complete_judokas),
                "onvolledige_judokas": len(onvolledige_judokas),
                "totaal_poules": len(poules),
                "score": score_indeling(poules, voorkeur),
                "grootte_verdeling": grootte_counts,
                "orphans": len(orphans)
            }
        }

    except Exception as e:
        import traceback
        return {"success": False, "error": str(e), "traceback": traceback.format_exc()}


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
