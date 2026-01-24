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


def verfijn_poule(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    band_grens: int = -1,
    band_verschil_beginners: int = 1
) -> List[Judoka]:
    """
    Verfijn een lijst judoka's zodat ze allemaal onderling binnen constraints vallen.
    Verwijdert judoka's die niet passen tot de groep consistent is.
    """
    if len(judokas) <= 1:
        return judokas

    # Check of alle judoka's onderling passen
    def alle_passen(groep: List[Judoka]) -> bool:
        if len(groep) <= 1:
            return True

        gewichten = [j.gewicht for j in groep]
        leeftijden = [j.leeftijd for j in groep]
        banden = [j.band for j in groep]

        if max(gewichten) - min(gewichten) > max_kg:
            return False
        if max(leeftijden) - min(leeftijden) > max_lft:
            return False

        if max_band > 0:
            effectieve_max = max_band
            if band_grens >= 0 and min(banden) <= band_grens:
                effectieve_max = band_verschil_beginners
            if max(banden) - min(banden) > effectieve_max:
                return False

        return True

    # Als ze al passen, return
    if alle_passen(judokas):
        return judokas

    # Anders: verwijder van achteren tot ze passen
    result = list(judokas)
    while len(result) > 1 and not alle_passen(result):
        result.pop()

    return result


# =============================================================================
# Sliding Window Algoritme
# =============================================================================

def sliding_window(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int],
    band_grens: int = -1, band_verschil_beginners: int = 1,
    prioriteiten: List[str] = None
) -> List[Poule]:
    """
    Sliding Window algoritme voor poule-indeling.

    Sorteert op basis van prioriteiten (default: band, gewicht, leeftijd).
    Behandelt alle constraints (kg, leeftijd, band) gelijk bij poule vorming.
    """
    if not judokas:
        return []

    poules = []
    geplaatst: Set[int] = set()  # IDs van geplaatste judoka's

    # Bepaal ideale poulegrootte
    ideale_grootte = voorkeur[0] if voorkeur else 5

    # Sorteer op basis van prioriteiten (default: band eerst, dan gewicht, dan leeftijd)
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

    alle_judokas = sorted(judokas, key=sort_key)

    while True:
        # Bepaal beschikbare judoka's (niet geplaatst)
        beschikbaar = [j for j in alle_judokas if j.id not in geplaatst]

        if not beschikbaar:
            break

        # Pak eerste beschikbare judoka (al gesorteerd op prioriteiten)
        anchor = beschikbaar[0]

        # Vind alle judoka's die binnen ALLE constraints vallen t.o.v. anchor
        kandidaten = [anchor]
        for j in beschikbaar[1:]:
            # Check alle constraints
            kg_ok = abs(j.gewicht - anchor.gewicht) <= max_kg
            lft_ok = abs(j.leeftijd - anchor.leeftijd) <= max_lft

            # Band check met beginner regel
            if max_band > 0:
                effectieve_max = max_band
                if band_grens >= 0 and min(anchor.band, j.band) <= band_grens:
                    effectieve_max = band_verschil_beginners
                band_ok = abs(j.band - anchor.band) <= effectieve_max
            else:
                band_ok = True

            if kg_ok and lft_ok and band_ok:
                kandidaten.append(j)

        # Verfijn kandidaten: check of ze ook onderling passen
        # Sorteer kandidaten opnieuw op prioriteiten voor consistentie
        kandidaten = sorted(kandidaten, key=sort_key)

        # Maak poule van kandidaten (max ideale_grootte)
        poule_judokas = kandidaten[:ideale_grootte]

        # Valideer dat alle judoka's in poule onderling passen
        if len(poule_judokas) > 1:
            poule_judokas = verfijn_poule(poule_judokas, max_kg, max_lft, max_band, band_grens, band_verschil_beginners)

        if poule_judokas:
            poules.append(Poule(judokas=poule_judokas))
            for j in poule_judokas:
                geplaatst.add(j.id)
        else:
            # Anchor als orphan
            poules.append(Poule(judokas=[anchor]))
            geplaatst.add(anchor.id)

    # Na-verwerking: probeer kleine poules te mergen
    poules = merge_kleine_poules(poules, max_kg, max_lft, max_band, voorkeur, band_grens, band_verschil_beginners)

    return poules


def maak_een_poule(
    lft_groep: List[Judoka],
    max_kg: float,
    max_band: int,
    ideale_grootte: int,
    poules: List[Poule],
    geplaatst: Set[int],
    band_grens: int = -1, band_verschil_beginners: int = 1
) -> bool:
    """
    Maak 1 poule binnen de leeftijdsgroep.

    1. Sorteer op gewicht
    2. Bepaal gewichtsrange (lichtste + max_kg)
    3. Sorteer op band binnen gewichtsrange
    4. Filter op bandrange (laagste + max_band) als max_band > 0
    5. Pak max ideale_grootte judoka's
    6. Markeer als geplaatst

    Returns: True als poule gemaakt, False als niet mogelijk
    """
    # Filter op niet-geplaatste judoka's, sorteer op gewicht
    beschikbaar = sorted([j for j in lft_groep if j.id not in geplaatst], key=lambda j: j.gewicht)

    if not beschikbaar:
        return False

    # Bepaal gewichtsrange
    lichtste_gewicht = beschikbaar[0].gewicht
    max_gewicht = lichtste_gewicht + max_kg

    # Judoka's in deze gewichtsrange
    in_range = [j for j in beschikbaar if j.gewicht <= max_gewicht]

    if not in_range:
        return False

    # Sorteer op band (laagste eerst: 0=wit, 6=zwart)
    in_range_sorted = sorted(in_range, key=lambda j: j.band)

    # Als max_band > 0: filter ook op bandrange
    if max_band > 0 and in_range_sorted:
        laagste_band = in_range_sorted[0].band
        # Bepaal effectieve max_band (strenger voor beginners)
        effectieve_max_band = max_band
        if band_grens >= 0 and laagste_band <= band_grens:
            # Poule bevat beginner (band <= grens), gebruik beginners verschil
            effectieve_max_band = band_verschil_beginners
        max_toegestane_band = laagste_band + effectieve_max_band
        in_band_range = [j for j in in_range_sorted if j.band <= max_toegestane_band]
        poule_judokas = in_band_range[:ideale_grootte]
    else:
        poule_judokas = in_range_sorted[:ideale_grootte]

    if not poule_judokas:
        return False

    # Maak poule
    poule = Poule(judokas=list(poule_judokas))
    poules.append(poule)

    # Markeer als geplaatst
    for j in poule_judokas:
        geplaatst.add(j.id)

    return True


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
    max_band: int,
    voorkeur: List[int],
    band_grens: int = -1, band_verschil_beginners: int = 1
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
                if not check_poule_constraints(all_judokas, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
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
# Greedy++ Optimalisatie
# =============================================================================

def check_poule_constraints(judokas: List[Judoka], max_kg: float, max_lft: int, max_band: int, band_grens: int = -1, band_verschil_beginners: int = 1) -> bool:
    """Check of lijst judoka's binnen constraints valt."""
    if not judokas:
        return True

    gewichten = [j.gewicht for j in judokas]
    leeftijden = [j.leeftijd for j in judokas]
    banden = [j.band for j in judokas]

    if max(gewichten) - min(gewichten) > max_kg:
        return False
    if max(leeftijden) - min(leeftijden) > max_lft:
        return False

    # Band check met strenger voor beginners
    if max_band > 0:
        effectieve_max_band = max_band
        if band_grens >= 0 and min(banden) <= band_grens:
            # Poule bevat beginner (band <= grens), gebruik beginners verschil
            effectieve_max_band = band_verschil_beginners
        if max(banden) - min(banden) > effectieve_max_band:
            return False

    return True


def kan_toevoegen(judoka: Judoka, poule: Poule, max_kg: float, max_lft: int, max_band: int, band_grens: int = -1, band_verschil_beginners: int = 1) -> bool:
    """Check of judoka aan poule kan worden toegevoegd binnen constraints."""
    if not poule.judokas:
        return True

    alle_judokas = poule.judokas + [judoka]
    return check_poule_constraints(alle_judokas, max_kg, max_lft, max_band, band_grens, band_verschil_beginners)


def club_penalty(judokas: List[Judoka]) -> int:
    """
    Bereken club penalty voor een lijst judoka's.
    Hoger = slechter (meer judoka's van zelfde club).
    """
    from collections import Counter
    clubs = [j.club_id for j in judokas if j.club_id]
    if not clubs:
        return 0
    counts = Counter(clubs)
    return sum(count - 1 for count in counts.values())


def vind_band_verbeterende_swap(
    p1: Poule,
    p2: Poule,
    max_kg: float,
    max_lft: int,
    max_band: int,
    band_grens: int = -1, band_verschil_beginners: int = 1
) -> tuple:
    """
    Vind een swap die de totale band spreiding vermindert.
    Returns (judoka_uit_p1, judoka_uit_p2) of None.
    """
    if not p1.judokas or not p2.judokas:
        return None

    oude_band_spread = (
        max(j.band for j in p1.judokas) - min(j.band for j in p1.judokas) +
        max(j.band for j in p2.judokas) - min(j.band for j in p2.judokas)
    )

    for j1 in p1.judokas:
        for j2 in p2.judokas:
            # Simuleer swap
            p1_na = [j for j in p1.judokas if j is not j1] + [j2]
            p2_na = [j for j in p2.judokas if j is not j2] + [j1]

            # Check harde constraints
            if not check_poule_constraints(p1_na, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                continue
            if not check_poule_constraints(p2_na, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                continue

            # Check verbetering
            nieuwe_band_spread = (
                max(j.band for j in p1_na) - min(j.band for j in p1_na) +
                max(j.band for j in p2_na) - min(j.band for j in p2_na)
            )

            if nieuwe_band_spread < oude_band_spread:
                return (j1, j2)

    return None


def vind_club_verbeterende_swap(
    p1: Poule,
    p2: Poule,
    max_kg: float,
    max_lft: int,
    max_band: int,
    band_grens: int = -1, band_verschil_beginners: int = 1
) -> tuple:
    """
    Vind een swap die de totale club spreiding verbetert.
    Returns (judoka_uit_p1, judoka_uit_p2) of None.
    """
    if not p1.judokas or not p2.judokas:
        return None

    oude_club_penalty = club_penalty(p1.judokas) + club_penalty(p2.judokas)

    # Geen penalty = geen verbetering mogelijk
    if oude_club_penalty == 0:
        return None

    for j1 in p1.judokas:
        for j2 in p2.judokas:
            # Skip als zelfde club (swap helpt niet)
            if j1.club_id == j2.club_id:
                continue

            # Simuleer swap
            p1_na = [j for j in p1.judokas if j is not j1] + [j2]
            p2_na = [j for j in p2.judokas if j is not j2] + [j1]

            # Check harde constraints
            if not check_poule_constraints(p1_na, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                continue
            if not check_poule_constraints(p2_na, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                continue

            # Check verbetering
            nieuwe_club_penalty = club_penalty(p1_na) + club_penalty(p2_na)

            if nieuwe_club_penalty < oude_club_penalty:
                return (j1, j2)

    return None


def greedy_plus_plus(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    max_band: int,
    voorkeur: List[int],
    band_grens: int = -1, band_verschil_beginners: int = 1
) -> List[Poule]:
    """
    Greedy++ optimalisatie na sliding window.

    Prioriteit (hoog → laag):
    1. Orphan rescue - plaats orphans in bestaande poules
    2. Rebalance naar 5 - verplaats judoka van 6 naar 4 om 5+5 te krijgen
    3. Band swap - verbeter band spreiding
    4. Club swap - verbeter club spreiding (alleen als rest gelijk)
    """
    if not poules:
        return poules

    max_size = max(voorkeur) if voorkeur else 6
    min_size = min(voorkeur) if voorkeur else 3

    verbeterd = True
    max_iteraties = 100
    iteratie = 0

    while verbeterd and iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False

        # === STAP 1: Orphan rescue ===
        orphans = [p for p in poules if len(p.judokas) == 1]
        for orphan_poule in orphans:
            orphan = orphan_poule.judokas[0]

            beste_poule = None
            beste_score_verbetering = 0

            for poule in poules:
                if poule is orphan_poule:
                    continue
                if len(poule.judokas) >= max_size:
                    continue

                if not kan_toevoegen(orphan, poule, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                    continue

                oude_score = (bereken_grootte_penalty(1, voorkeur) +
                              bereken_grootte_penalty(len(poule.judokas), voorkeur))
                nieuwe_score = bereken_grootte_penalty(len(poule.judokas) + 1, voorkeur)
                verbetering = oude_score - nieuwe_score

                if verbetering > beste_score_verbetering:
                    beste_score_verbetering = verbetering
                    beste_poule = poule

            if beste_poule:
                beste_poule.judokas.append(orphan)
                poules.remove(orphan_poule)
                verbeterd = True
                break

        if verbeterd:
            continue

        # === STAP 2: Rebalance naar 5 ===
        # Zoek poule van 6 + poule van 4 → verplaats 1 judoka → 2x poule van 5
        for p_groot in [p for p in poules if len(p.judokas) == 6]:
            if verbeterd:
                break
            for p_klein in [p for p in poules if len(p.judokas) == 4]:
                if p_groot is p_klein:
                    continue
                # Zoek judoka uit grote poule die naar kleine kan
                for judoka in p_groot.judokas:
                    if kan_toevoegen(judoka, p_klein, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                        # Check of grote poule nog valid is zonder deze judoka
                        p_groot_na = [j for j in p_groot.judokas if j is not judoka]
                        if check_poule_constraints(p_groot_na, max_kg, max_lft, max_band, band_grens, band_verschil_beginners):
                            p_groot.judokas.remove(judoka)
                            p_klein.judokas.append(judoka)
                            verbeterd = True
                            break
                if verbeterd:
                    break

        if verbeterd:
            continue

        # === STAP 3: Band swap ===
        for i, p1 in enumerate(poules):
            if verbeterd:
                break
            for p2 in poules[i+1:]:
                swap = vind_band_verbeterende_swap(p1, p2, max_kg, max_lft, max_band, band_grens, band_verschil_beginners)
                if swap:
                    j1, j2 = swap
                    p1.judokas.remove(j1)
                    p2.judokas.remove(j2)
                    p1.judokas.append(j2)
                    p2.judokas.append(j1)
                    verbeterd = True
                    break

        if verbeterd:
            continue

        # === STAP 4: Club spreiding ===
        for i, p1 in enumerate(poules):
            if verbeterd:
                break
            for p2 in poules[i+1:]:
                swap = vind_club_verbeterende_swap(p1, p2, max_kg, max_lft, max_band, band_grens, band_verschil_beginners)
                if swap:
                    j1, j2 = swap
                    p1.judokas.remove(j1)
                    p2.judokas.remove(j2)
                    p1.judokas.append(j2)
                    p2.judokas.append(j1)
                    verbeterd = True
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
        "max_band_verschil": 0,  // 0 = geen limiet
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
        max_band = int(input_data.get('max_band_verschil', 0))  # 0 = geen limiet
        band_grens = int(input_data.get('band_grens', -1))  # -1 = geen grens
        band_verschil_beginners = int(input_data.get('band_verschil_beginners', 1))
        voorkeur = input_data.get('poule_grootte_voorkeur', [5, 4, 6, 3])
        prioriteiten = input_data.get('verdeling_prioriteiten', ['leeftijd', 'gewicht', 'band'])

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

        # Sliding window basis indeling
        poules = sliding_window(judokas, max_kg, max_lft, max_band, voorkeur, band_grens, band_verschil_beginners, prioriteiten)

        # Greedy++ optimalisatie
        poules = greedy_plus_plus(poules, max_kg, max_lft, max_band, voorkeur, band_grens, band_verschil_beginners)

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
