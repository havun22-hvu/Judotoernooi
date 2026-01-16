#!/usr/bin/env python3
"""
Poule Solver - Optimale poule-indeling voor judotoernooien
==========================================================

Greedy++ algoritme:
1. Sorteer op leeftijd -> gewicht
2. Maak poules greedy
3. Fix orphans: zoek in ALLE poules of ze erbij passen
4. Merge kleine poules
5. Swap judoka's tussen poules voor betere verdeling

Input: JSON via stdin
Output: JSON via stdout

Zie: docs/4-PLANNING/PLANNING_DYNAMISCHE_INDELING.md
"""

import sys
import json
from dataclasses import dataclass, field
from typing import List, Dict, Optional, Tuple
from itertools import combinations


@dataclass
class Judoka:
    id: int
    leeftijd: int
    gewicht: float
    band: int = 0
    club_id: int = 0

    def __repr__(self):
        return f"J{self.id}({self.leeftijd}j,{self.gewicht}kg)"


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

    def kan_toevoegen(self, judoka: Judoka, max_kg: float, max_lft: int) -> bool:
        """Check of judoka toegevoegd kan worden binnen limieten."""
        if not self.judokas:
            return True

        # Check gewicht
        new_min = min(self.min_gewicht, judoka.gewicht)
        new_max = max(self.max_gewicht, judoka.gewicht)
        if new_max - new_min > max_kg:
            return False

        # Check leeftijd
        new_min_lft = min(self.min_leeftijd, judoka.leeftijd)
        new_max_lft = max(self.max_leeftijd, judoka.leeftijd)
        if new_max_lft - new_min_lft > max_lft:
            return False

        return True

    def voeg_toe(self, judoka: Judoka):
        self.judokas.append(judoka)

    def verwijder(self, judoka: Judoka):
        self.judokas = [j for j in self.judokas if j.id != judoka.id]

    def __repr__(self):
        return f"Poule({self.size}): {self.judokas}"


def bereken_grootte_penalty(grootte: int, voorkeur: List[int]) -> int:
    """
    Score op basis van poule_grootte_voorkeur uit config.
    Lagere score = beter.
    """
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


def maak_poules_greedy(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_grootte: int = 5
) -> List[Poule]:
    """
    Stap 1: Basis greedy algoritme.
    Loop door gesorteerde judoka's, maak poules.
    """
    if not judokas:
        return []

    # Sorteer op leeftijd, dan gewicht
    gesorteerd = sorted(judokas, key=lambda j: (j.leeftijd, j.gewicht))

    poules = []
    huidige = Poule()

    for judoka in gesorteerd:
        if huidige.size == 0:
            huidige.voeg_toe(judoka)
        elif huidige.size < max_grootte and huidige.kan_toevoegen(judoka, max_kg, max_lft):
            huidige.voeg_toe(judoka)
        else:
            poules.append(huidige)
            huidige = Poule()
            huidige.voeg_toe(judoka)

    if huidige.size > 0:
        poules.append(huidige)

    return poules


def fix_orphans(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Stap 2: Probeer orphans (kleine poules) toe te voegen aan andere poules.

    Plaatsings-attractiviteit:
    - Poule van 3: HOOG (wordt 4 - ideaal)
    - Poule van 4: HOOG (wordt 5 - ideaal)
    - Poule van 5: LAAG (wordt 6 - vermijden)
    - Poule van 6: NIET bijvullen (zou 7 worden) → split naar 3+4

    Passes:
    1. Eerst in poules van 3-4 (hoge attractiviteit)
    2. Dan in poules van 5 (lage attractiviteit, alleen als nodig)
    3. Als orphan bij poule van 6 past → split die poule in 3+4
    """
    max_grootte = voorkeur[0] if voorkeur else 5
    absolute_max = max(voorkeur) if voorkeur else 6

    # Pass 1: Hoge attractiviteit - poules van 3 of 4
    poules = _fix_orphans_by_target_sizes(poules, max_kg, max_lft, voorkeur, [3, 4])

    # Pass 2: Lage attractiviteit - poules van 5 (alleen als pass 1 niet lukte)
    poules = _fix_orphans_by_target_sizes(poules, max_kg, max_lft, voorkeur, [5])

    # Pass 3: Poule van 6 + orphan → split naar 3+4
    poules = _fix_orphans_by_splitting(poules, max_kg, max_lft, voorkeur, absolute_max)

    return poules


def _fix_orphans_by_target_sizes(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int],
    target_sizes: List[int]
) -> List[Poule]:
    """
    Helper: plaats orphans in poules met specifieke groottes.
    """
    verbeterd = True

    while verbeterd:
        verbeterd = False

        # Vind kleine poules (grootte 1-2)
        kleine = [p for p in poules if p.size <= 2]

        for kleine_poule in kleine:
            for judoka in kleine_poule.judokas[:]:
                # Zoek poule met target grootte waar judoka past
                beste_poule = None
                beste_score = float('inf')

                for andere in poules:
                    if andere is kleine_poule:
                        continue
                    if andere.size not in target_sizes:
                        continue
                    if not andere.kan_toevoegen(judoka, max_kg, max_lft):
                        continue

                    # Kies poule met beste score na toevoegen
                    nieuwe_score = bereken_grootte_penalty(andere.size + 1, voorkeur)
                    if nieuwe_score < beste_score:
                        beste_score = nieuwe_score
                        beste_poule = andere

                if beste_poule:
                    kleine_poule.verwijder(judoka)
                    beste_poule.voeg_toe(judoka)
                    verbeterd = True

        # Verwijder lege poules
        poules = [p for p in poules if p.size > 0]

    return poules


def _fix_orphans_by_splitting(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int],
    absolute_max: int
) -> List[Poule]:
    """
    Helper: als orphan bij poule van absolute_max past, voeg toe en split in 3+4.
    Poule van 6 + 1 orphan = 7 → split naar poule van 3 + poule van 4
    """
    verbeterd = True

    while verbeterd:
        verbeterd = False

        # Vind orphans (grootte 1)
        orphans = [p for p in poules if p.size == 1]

        for orphan_poule in orphans:
            judoka = orphan_poule.judokas[0]

            # Zoek poule van absolute_max waar judoka bij past
            for andere in poules:
                if andere is orphan_poule:
                    continue
                if andere.size != absolute_max:
                    continue
                if not andere.kan_toevoegen(judoka, max_kg, max_lft):
                    continue

                # Voeg orphan toe → wordt absolute_max + 1 (bijv. 7)
                alle_judokas = andere.judokas + [judoka]

                # Split in 3 + 4
                poule_3, poule_4 = _split_in_3_en_4(alle_judokas, max_kg, max_lft)

                if poule_3 and poule_4:
                    # Verwijder oude poules, voeg nieuwe toe
                    poules.remove(andere)
                    poules.remove(orphan_poule)
                    poules.append(poule_3)
                    poules.append(poule_4)
                    verbeterd = True
                    break

            if verbeterd:
                break

    return poules


def _split_in_3_en_4(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int
) -> Tuple[Optional[Poule], Optional[Poule]]:
    """
    Split 7 judoka's in een poule van 3 en een poule van 4.
    Probeert beste verdeling te vinden die binnen limieten valt.
    """
    if len(judokas) != 7:
        return None, None

    # Sorteer op gewicht voor betere verdeling
    gesorteerd = sorted(judokas, key=lambda j: (j.leeftijd, j.gewicht))

    # Probeer verschillende splitsingen
    beste_split = None
    beste_totaal_range = float('inf')

    for indices_3 in combinations(range(7), 3):
        indices_4 = [i for i in range(7) if i not in indices_3]

        groep_3 = [gesorteerd[i] for i in indices_3]
        groep_4 = [gesorteerd[i] for i in indices_4]

        poule_3 = Poule(judokas=groep_3)
        poule_4 = Poule(judokas=groep_4)

        # Check of beide binnen limieten vallen
        if poule_3.gewicht_range > max_kg or poule_3.leeftijd_range > max_lft:
            continue
        if poule_4.gewicht_range > max_kg or poule_4.leeftijd_range > max_lft:
            continue

        # Bereken totale range (lager = beter)
        totaal_range = poule_3.gewicht_range + poule_4.gewicht_range
        if totaal_range < beste_totaal_range:
            beste_totaal_range = totaal_range
            beste_split = (poule_3, poule_4)

    if beste_split:
        return beste_split

    return None, None


def merge_kleine_poules(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Stap 3: Merge kleine poules als ze samen binnen limieten vallen.
    """
    max_grootte = voorkeur[0] if voorkeur else 5
    absolute_max = max(voorkeur) if voorkeur else 6  # Harde bovengrens
    verbeterd = True

    while verbeterd:
        verbeterd = False

        # Vind kleine poules (grootte < 4)
        kleine = [p for p in poules if p.size < 4]

        for p1, p2 in combinations(kleine, 2):
            if p1 not in poules or p2 not in poules:
                continue

            # Check of merge mogelijk is (nooit groter dan absolute_max)
            combined_size = p1.size + p2.size
            if combined_size > absolute_max:
                continue

            # Check limieten
            all_judokas = p1.judokas + p2.judokas
            gewichten = [j.gewicht for j in all_judokas]
            leeftijden = [j.leeftijd for j in all_judokas]

            if max(gewichten) - min(gewichten) > max_kg:
                continue
            if max(leeftijden) - min(leeftijden) > max_lft:
                continue

            # Bereken of merge beter is
            oude_score = bereken_grootte_penalty(p1.size, voorkeur) + bereken_grootte_penalty(p2.size, voorkeur)
            nieuwe_score = bereken_grootte_penalty(combined_size, voorkeur)

            if nieuwe_score < oude_score:
                # Merge
                nieuwe_poule = Poule(judokas=all_judokas)
                poules.remove(p1)
                poules.remove(p2)
                poules.append(nieuwe_poule)
                verbeterd = True
                break

    return poules


def probeer_swaps(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Stap 4: Probeer judoka's te swappen tussen poules voor betere verdeling.
    """
    max_iteraties = 100
    iteratie = 0

    while iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False
        oude_totaal = score_indeling(poules, voorkeur)

        # Vind poules die verbeterd kunnen worden
        te_groot = [p for p in poules if p.size > voorkeur[0]]
        min_grootte = voorkeur[-1] if voorkeur else 3
        te_klein = [p for p in poules if p.size < min_grootte]

        # Probeer judoka van grote naar kleine poule te verplaatsen
        for grote in te_groot:
            for kleine in te_klein:
                if grote is kleine:
                    continue

                for judoka in grote.judokas[:]:
                    if kleine.kan_toevoegen(judoka, max_kg, max_lft):
                        # Simuleer verplaatsing
                        grote.verwijder(judoka)
                        kleine.voeg_toe(judoka)

                        nieuwe_totaal = score_indeling(poules, voorkeur)

                        if nieuwe_totaal < oude_totaal:
                            verbeterd = True
                            break
                        else:
                            # Rollback
                            kleine.verwijder(judoka)
                            grote.voeg_toe(judoka)

                if verbeterd:
                    break
            if verbeterd:
                break

        if not verbeterd:
            break

    return poules


def greedy_plus_plus(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Greedy++ algoritme:
    1. Greedy basis
    2. Fix orphans
    3. Merge kleine poules
    4. Swap voor optimalisatie
    """
    # Stap 1: Greedy basis
    max_grootte = voorkeur[0] if voorkeur else 5
    poules = maak_poules_greedy(judokas, max_kg, max_lft, max_grootte)

    # Stap 2: Fix orphans
    poules = fix_orphans(poules, max_kg, max_lft, voorkeur)

    # Stap 3: Merge kleine poules
    poules = merge_kleine_poules(poules, max_kg, max_lft, voorkeur)

    # Stap 4: Swap optimalisatie
    poules = probeer_swaps(poules, max_kg, max_lft, voorkeur)

    return poules


def solve(input_data: dict) -> dict:
    """
    Hoofdfunctie: los poule-indeling op.

    Input:
    {
        "categorie": "U7",
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": [
            {"id": 1, "leeftijd": 6, "gewicht": 25.5, "band": 2, "club_id": 10},
            ...
        ]
    }

    Output:
    {
        "success": true,
        "poules": [...],
        "stats": {...}
    }
    """
    try:
        # Parse input
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
                "stats": {
                    "totaal_judokas": 0,
                    "totaal_poules": 0,
                    "score": 0
                }
            }

        # Run solver
        poules = greedy_plus_plus(judokas, max_kg, max_lft, voorkeur)

        # Build output
        poules_output = []
        stats = {
            "totaal_judokas": len(judokas),
            "totaal_poules": len(poules),
            "score": score_indeling(poules, voorkeur)
        }

        # Count per grootte
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

        stats["grootte_verdeling"] = grootte_counts
        stats["orphans"] = grootte_counts.get(1, 0)

        return {
            "success": True,
            "poules": poules_output,
            "stats": stats
        }

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }


def main():
    """Main entry point: read JSON from stdin, write result to stdout."""
    try:
        input_data = json.load(sys.stdin)
        result = solve(input_data)
        print(json.dumps(result, indent=2))
    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"Invalid JSON input: {e}"
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()
