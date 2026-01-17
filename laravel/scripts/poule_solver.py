#!/usr/bin/env python3
"""
Poule Solver - Optimale poule-indeling voor judotoernooien
==========================================================

Verdeel judoka's binnen één categorie in optimale poules.
Classificatie gebeurt in PHP via CategorieClassifier.

Sliding Window algoritme:
1. Leeftijdsgroep (jongste + max_lft_verschil)
2. Gewichtsrange (lichtste + max_kg_verschil)
3. Sorteer op band, maak poule(s)
4. Herhaal tot alle judoka's geplaatst

Input: JSON via stdin
Output: JSON via stdout

Zie: docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md
"""

import sys
import json
from dataclasses import dataclass, field
from typing import List, Dict, Set


# =============================================================================
# Data Classes
# =============================================================================

@dataclass
class Judoka:
    id: int
    leeftijd: int
    gewicht: float
    geslacht: str = ""
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
    def gewicht_range(self) -> float:
        if not self.judokas:
            return 0
        gewichten = [j.gewicht for j in self.judokas]
        return max(gewichten) - min(gewichten)

    @property
    def leeftijd_range(self) -> int:
        if not self.judokas:
            return 0
        leeftijden = [j.leeftijd for j in self.judokas]
        return max(leeftijden) - min(leeftijden)

    @property
    def min_gewicht(self) -> float:
        return min(j.gewicht for j in self.judokas) if self.judokas else 0

    @property
    def max_gewicht(self) -> float:
        return max(j.gewicht for j in self.judokas) if self.judokas else 0

    @property
    def min_leeftijd(self) -> int:
        return min(j.leeftijd for j in self.judokas) if self.judokas else 0

    @property
    def max_leeftijd(self) -> int:
        return max(j.leeftijd for j in self.judokas) if self.judokas else 0

    def __repr__(self):
        return f"Poule({self.size}): {self.judokas}"


# =============================================================================
# Scoring Functions
# =============================================================================

def bereken_grootte_penalty(grootte: int, voorkeur: List[int]) -> int:
    """Score op basis van poule_grootte_voorkeur. Lager = beter."""
    if grootte <= 1:
        return 100  # Orphan

    if grootte in voorkeur:
        index = voorkeur.index(grootte)
        if index == 0:
            return 0    # Eerste voorkeur
        elif index == 1:
            return 5    # Tweede voorkeur
        else:
            return 40   # Rest
    else:
        return 70  # Niet in voorkeurlijst


def score_indeling(poules: List[Poule], voorkeur: List[int]) -> int:
    """Bereken totale score. Lager = beter."""
    return sum(bereken_grootte_penalty(p.size, voorkeur) for p in poules)


# =============================================================================
# Sliding Window Algoritme
# =============================================================================

def sliding_window(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Sliding Window algoritme voor poule-indeling.

    1. Leeftijdsgroep (jongste + max_lft_verschil)
    2. Gewichtsrange (lichtste + max_kg_verschil)
    3. Sorteer op band, maak poule(s)
    4. Herhaal
    """
    if not judokas:
        return []

    poules = []
    geplaatst: Set[int] = set()  # IDs van geplaatste judoka's
    overblijvers: List[Judoka] = []  # Overblijvers die mee kunnen naar volgende lft-groep

    # Bepaal ideale en max poulegrootte
    ideale_grootte = voorkeur[0] if voorkeur else 5
    max_grootte = max(voorkeur) if voorkeur else 6
    min_grootte = min(voorkeur) if voorkeur else 3

    # Sorteer alle judoka's op leeftijd
    alle_judokas = sorted(judokas, key=lambda j: j.leeftijd)

    while True:
        # Bepaal beschikbare judoka's (niet geplaatst)
        beschikbaar = [j for j in alle_judokas if j.id not in geplaatst]

        # Voeg overblijvers toe (uit vorige leeftijdsgroep)
        for over in overblijvers:
            if over.id not in geplaatst and over not in beschikbaar:
                beschikbaar.append(over)

        if not beschikbaar:
            break

        # STAP 1: Bepaal leeftijdsgroep
        jongste_leeftijd = min(j.leeftijd for j in beschikbaar)
        max_leeftijd_groep = jongste_leeftijd + max_lft

        # Judoka's in deze leeftijdsgroep
        lft_groep = [j for j in beschikbaar if j.leeftijd <= max_leeftijd_groep]

        if not lft_groep:
            break

        # Verwerk deze leeftijdsgroep
        nieuwe_overblijvers = verwerk_leeftijdsgroep(
            lft_groep, max_kg, voorkeur, ideale_grootte, max_grootte, min_grootte,
            poules, geplaatst
        )

        # Bepaal overblijvers voor volgende leeftijdsgroep
        # Alleen judoka's van de jongste leeftijd die niet geplaatst zijn
        # kunnen NIET mee naar volgende groep (want buiten range)
        overblijvers = []
        for j in lft_groep:
            if j.id not in geplaatst:
                # Check of deze judoka mee kan naar volgende leeftijdsgroep
                # Volgende groep start met leeftijd > jongste_leeftijd
                # Dus alleen judoka's die passen bij (jongste+1, jongste+1+max_lft)
                if j.leeftijd > jongste_leeftijd:
                    overblijvers.append(j)
                # Judoka's van jongste_leeftijd kunnen niet mee → blijven orphan

    # Na-verwerking: probeer kleine poules te mergen
    poules = merge_kleine_poules(poules, max_kg, max_lft, voorkeur)

    return poules


def verwerk_leeftijdsgroep(
    lft_groep: List[Judoka],
    max_kg: float,
    voorkeur: List[int],
    ideale_grootte: int,
    max_grootte: int,
    min_grootte: int,
    poules: List[Poule],
    geplaatst: Set[int]
) -> List[Judoka]:
    """
    Verwerk één leeftijdsgroep: maak poules op basis van gewichtsranges.

    Returns: overblijvers die niet geplaatst konden worden
    """
    # Sorteer op gewicht
    gesorteerd = sorted(lft_groep, key=lambda j: j.gewicht)

    while True:
        # Filter op niet-geplaatste judoka's
        beschikbaar = [j for j in gesorteerd if j.id not in geplaatst]

        if not beschikbaar:
            break

        # STAP 2: Bepaal gewichtsrange
        lichtste_gewicht = beschikbaar[0].gewicht
        max_gewicht_range = lichtste_gewicht + max_kg

        # Judoka's in deze gewichtsrange
        gew_groep = [j for j in beschikbaar if j.gewicht <= max_gewicht_range]

        if not gew_groep:
            break

        # STAP 3: Sorteer op band (laagste eerst) en maak poule(s)
        gew_groep_sorted = sorted(gew_groep, key=lambda j: j.band)

        # Maak poules van deze groep
        maak_poules_van_groep(
            gew_groep_sorted, voorkeur, ideale_grootte, max_grootte,
            poules, geplaatst
        )

    # Return overblijvers
    return [j for j in gesorteerd if j.id not in geplaatst]


def maak_poules_van_groep(
    groep: List[Judoka],
    voorkeur: List[int],
    ideale_grootte: int,
    max_grootte: int,
    poules: List[Poule],
    geplaatst: Set[int]
):
    """
    Maak poule(s) van een groep judoka's (al gesorteerd op band).
    Gebruikt poule_grootte_voorkeur om optimale verdeling te bepalen.
    """
    if not groep:
        return

    n = len(groep)

    if n == 0:
        return

    # Bepaal optimale verdeling
    verdeling = bepaal_verdeling(n, voorkeur, ideale_grootte, max_grootte)

    # Maak poules volgens verdeling
    idx = 0
    for poule_grootte in verdeling:
        if idx >= n:
            break

        poule_judokas = groep[idx:idx + poule_grootte]
        if poule_judokas:
            poule = Poule(judokas=list(poule_judokas))
            poules.append(poule)

            # Markeer als geplaatst
            for j in poule_judokas:
                geplaatst.add(j.id)

        idx += poule_grootte


def bepaal_verdeling(n: int, voorkeur: List[int], ideaal: int, max_size: int) -> List[int]:
    """
    Bepaal optimale verdeling van n judoka's in poules.

    Voorbeelden (voorkeur=[5,4,6,3]):
    - n=5: [5]
    - n=8: [4,4]
    - n=9: [5,4]
    - n=10: [5,5]
    - n=11: [5,6] of [4,4,3]
    - n=12: [4,4,4]
    - n=13: [5,4,4]
    """
    if n <= 0:
        return []

    if n <= max_size:
        return [n]

    # Probeer verdelingen en kies beste score
    beste_verdeling = None
    beste_score = float('inf')

    # Probeer verschillende aantallen poules
    for num_poules in range(2, n + 1):
        if num_poules > n:
            break

        verdeling = verdeel_gelijk(n, num_poules, voorkeur, max_size)
        if verdeling:
            score = sum(bereken_grootte_penalty(g, voorkeur) for g in verdeling)
            if score < beste_score:
                beste_score = score
                beste_verdeling = verdeling

    return beste_verdeling if beste_verdeling else [n]


def verdeel_gelijk(n: int, num_poules: int, voorkeur: List[int], max_size: int) -> List[int]:
    """
    Verdeel n judoka's over num_poules poules, zo gelijk mogelijk.
    Respecteert max_size.
    """
    if num_poules <= 0 or n <= 0:
        return []

    basis = n // num_poules
    rest = n % num_poules

    # Maak verdeling
    verdeling = []
    for i in range(num_poules):
        grootte = basis + (1 if i < rest else 0)
        if grootte > max_size:
            return []  # Niet mogelijk binnen constraints
        verdeling.append(grootte)

    # Sorteer op voorkeur (hoogste voorkeur eerst)
    verdeling.sort(key=lambda g: voorkeur.index(g) if g in voorkeur else 99)

    return verdeling


def merge_kleine_poules(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Na-verwerking: probeer kleine poules (< min_voorkeur) te mergen.
    """
    if not poules:
        return poules

    max_size = max(voorkeur) if voorkeur else 6
    min_size = min(voorkeur) if voorkeur else 3

    verbeterd = True
    max_iteraties = 50
    iteratie = 0

    while verbeterd and iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False

        # Vind kleine poules
        kleine = [p for p in poules if p.size < min_size]

        for kleine_poule in kleine:
            # Probeer te mergen met andere poule
            for andere in poules:
                if andere is kleine_poule:
                    continue

                # Check of merge mogelijk is
                combined_size = kleine_poule.size + andere.size
                if combined_size > max_size:
                    continue

                # Check constraints
                all_judokas = kleine_poule.judokas + andere.judokas
                gewichten = [j.gewicht for j in all_judokas]
                leeftijden = [j.leeftijd for j in all_judokas]

                if max(gewichten) - min(gewichten) > max_kg:
                    continue
                if max(leeftijden) - min(leeftijden) > max_lft:
                    continue

                # Check of merge score verbetert
                oude_score = (bereken_grootte_penalty(kleine_poule.size, voorkeur) +
                              bereken_grootte_penalty(andere.size, voorkeur))
                nieuwe_score = bereken_grootte_penalty(combined_size, voorkeur)

                if nieuwe_score < oude_score:
                    # Merge!
                    nieuwe_poule = Poule(judokas=all_judokas)
                    poules.remove(kleine_poule)
                    poules.remove(andere)
                    poules.append(nieuwe_poule)
                    verbeterd = True
                    break

            if verbeterd:
                break

    return poules


# =============================================================================
# Solve Function
# =============================================================================

def solve(input_data: dict) -> dict:
    """
    Verdeel judoka's binnen één categorie in optimale poules.

    Input:
    {
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": [
            {"id": 1, "leeftijd": 6, "gewicht": 22.5, "band": 2, "club_id": 1},
            ...
        ]
    }

    Output:
    {
        "success": true,
        "poules": [{"judoka_ids": [1, 2, 5], "gewicht_range": 2.5, ...}],
        "stats": {...}
    }
    """
    try:
        max_kg = float(input_data.get('max_kg_verschil', 3.0))
        max_lft = int(input_data.get('max_leeftijd_verschil', 2))
        voorkeur = input_data.get('poule_grootte_voorkeur', [5, 4, 6, 3])

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

        poules = sliding_window(judokas, max_kg, max_lft, voorkeur)

        poules_output = []
        grootte_counts = {}
        for p in poules:
            g = p.size
            grootte_counts[g] = grootte_counts.get(g, 0) + 1
            poules_output.append({
                "judoka_ids": [j.id for j in p.judokas],
                "gewicht_range": round(p.gewicht_range, 1),
                "leeftijd_range": p.leeftijd_range,
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
                "orphans": grootte_counts.get(1, 0)
            }
        }

    except Exception as e:
        return {"success": False, "error": str(e)}


# =============================================================================
# Main
# =============================================================================

def main():
    """Main entry point."""
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
