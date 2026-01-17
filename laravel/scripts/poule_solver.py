#!/usr/bin/env python3
"""
Poule Solver - Optimale poule-indeling voor judotoernooien
==========================================================

Twee modes:
1. solve_all(): Classificeer + verdeel ALLE judoka's (nieuwe modus)
2. solve(): Verdeel judoka's binnen één categorie (legacy modus)

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
        return f"J{self.id}({self.leeftijd}j,{self.gewicht}kg)"


@dataclass
class Poule:
    judokas: List[Judoka] = field(default_factory=list)
    categorie_key: str = ""
    label: str = ""
    gewichtsklasse: str = ""

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


# =============================================================================
# Categorie Classifier
# =============================================================================

class CategorieClassifier:
    """
    Classificeert judoka's naar categorieën op basis van harde criteria:
    - max_leeftijd
    - geslacht (M/V/gemengd)
    - band_filter (tm_oranje, vanaf_groen)
    - gewichtsklassen (bij vaste klassen)
    """

    BAND_NIVEAUS = {
        'wit': 1, 'white': 1,
        'geel': 2, 'yellow': 2,
        'oranje': 3, 'orange': 3,
        'groen': 4, 'green': 4,
        'blauw': 5, 'blue': 5,
        'bruin': 6, 'brown': 6,
        'zwart': 7, 'black': 7,
    }

    def __init__(self, categorieen: Dict, tolerantie: float = 0.5):
        """
        categorieen: dict met categorie configs
        {
            "u7": {"label": "U7", "max_leeftijd": 6, "geslacht": "gemengd", ...},
            "u11_h": {"label": "U11 Jongens", "max_leeftijd": 10, "geslacht": "M", ...}
        }
        """
        self.categorieen = categorieen
        self.tolerantie = tolerantie
        # Sorteer op max_leeftijd (jong → oud)
        self.sorted_keys = sorted(
            categorieen.keys(),
            key=lambda k: categorieen[k].get('max_leeftijd', 99)
        )

    def classificeer(self, judoka: Judoka) -> Optional[Dict]:
        """
        Classificeer judoka naar een categorie.
        Returns: {"key": "u7", "label": "U7", "gewichtsklasse": "-24" of None}
        """
        # Stap 1: Vind eerste max_leeftijd waar judoka in past
        eerste_match_leeftijd = None
        for key in self.sorted_keys:
            config = self.categorieen[key]
            max_leeftijd = config.get('max_leeftijd', 99)
            if judoka.leeftijd <= max_leeftijd:
                eerste_match_leeftijd = max_leeftijd
                break

        if eerste_match_leeftijd is None:
            return None  # Geen match

        # Stap 2: Check alleen categorieën met deze max_leeftijd
        for key in self.sorted_keys:
            config = self.categorieen[key]
            max_leeftijd = config.get('max_leeftijd', 99)

            if max_leeftijd != eerste_match_leeftijd:
                continue

            # Check geslacht
            if not self._geslacht_matcht(judoka.geslacht, config, key):
                continue

            # Check band_filter
            band_filter = config.get('band_filter')
            if band_filter and not self._band_matcht(judoka.band, band_filter):
                continue

            # Match gevonden! Bepaal gewichtsklasse
            gewichtsklasse = self._bepaal_gewichtsklasse(judoka.gewicht, config)

            return {
                "key": key,
                "label": config.get('label', key),
                "gewichtsklasse": gewichtsklasse,
                "is_dynamisch": (config.get('max_kg_verschil', 0) > 0),
                "max_kg_verschil": config.get('max_kg_verschil', 0),
                "max_leeftijd_verschil": config.get('max_leeftijd_verschil', 2),
            }

        return None  # Geen match

    def _geslacht_matcht(self, judoka_geslacht: str, config: Dict, key: str) -> bool:
        """Check of geslacht matcht met categorie config."""
        config_geslacht = config.get('geslacht', 'gemengd').upper()
        judoka_geslacht = judoka_geslacht.upper() if judoka_geslacht else ''
        label = config.get('label', '').lower()

        # Normalize legacy values
        if config_geslacht == 'MEISJES':
            config_geslacht = 'V'
        elif config_geslacht == 'JONGENS':
            config_geslacht = 'M'

        # Auto-detect from label/key if not explicitly gemengd
        original = config.get('geslacht', '').lower()
        is_explicit_gemengd = original == 'gemengd'

        if config_geslacht == 'GEMENGD' and not is_explicit_gemengd:
            if any(x in label for x in ['dames', 'meisjes']) or key.endswith('_d'):
                config_geslacht = 'V'
            elif any(x in label for x in ['heren', 'jongens']) or key.endswith('_h'):
                config_geslacht = 'M'

        # Gemengd matches all
        if config_geslacht == 'GEMENGD':
            return True

        return config_geslacht == judoka_geslacht

    def _band_matcht(self, band_niveau: int, band_filter: str) -> bool:
        """Check of band niveau matcht met filter."""
        if band_filter.startswith('tm_') or band_filter.startswith('t/m '):
            band = band_filter.replace('tm_', '').replace('t/m ', '')
            max_niveau = self.BAND_NIVEAUS.get(band.lower(), 7)
            return band_niveau <= max_niveau

        if band_filter.startswith('vanaf_') or band_filter.startswith('vanaf '):
            band = band_filter.replace('vanaf_', '').replace('vanaf ', '')
            min_niveau = self.BAND_NIVEAUS.get(band.lower(), 1)
            return band_niveau >= min_niveau

        return True

    def _bepaal_gewichtsklasse(self, gewicht: float, config: Dict) -> Optional[str]:
        """Bepaal gewichtsklasse uit config. None voor dynamische categorieën."""
        # Dynamisch = geen vaste klassen
        if config.get('max_kg_verschil', 0) > 0:
            return None

        gewichten = config.get('gewichten', [])
        if not gewichten:
            return None

        for klasse in gewichten:
            klasse_str = str(klasse)
            if klasse_str.startswith('+'):
                # Plus categorie = catch-all
                return klasse_str

            # Minus categorie
            max_gewicht = abs(float(klasse_str.replace('-', '')))
            if gewicht <= max_gewicht + self.tolerantie:
                return klasse_str

        # Fallback: laatste (meestal + categorie)
        return str(gewichten[-1])


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
# Greedy++ Algoritme
# =============================================================================

def identificeer_uitschieters(judokas: List[Judoka], max_kg: float) -> Tuple[List[Judoka], List[Judoka]]:
    """
    Scheid uitschieters van de bulk.
    Uitschieter = judoka die niet binnen max_kg van de mediaan ligt,
    OF judoka met grote gap naar de rest.
    """
    if len(judokas) < 4:
        return judokas, []  # Te weinig voor uitschieters

    gesorteerd = sorted(judokas, key=lambda j: j.gewicht)
    gewichten = [j.gewicht for j in gesorteerd]

    # Bereken mediaan
    n = len(gewichten)
    mediaan = (gewichten[n//2] + gewichten[(n-1)//2]) / 2

    # Vind de "bulk" - judoka's binnen redelijke afstand van mediaan
    # Gebruik 1.5x max_kg als threshold
    threshold = max_kg * 1.5

    bulk = []
    uitschieters = []

    for j in gesorteerd:
        if abs(j.gewicht - mediaan) <= threshold:
            bulk.append(j)
        else:
            uitschieters.append(j)

    # Extra check: kijk naar gaps aan de uiteinden
    if bulk and not uitschieters:
        # Check gap aan onderkant
        if len(gesorteerd) >= 2:
            gap_onder = gesorteerd[1].gewicht - gesorteerd[0].gewicht
            if gap_onder > max_kg * 0.7:  # Grote gap = uitschieter
                uitschieters.append(gesorteerd[0])
                bulk = [j for j in bulk if j.id != gesorteerd[0].id]

        # Check gap aan bovenkant
        if len(gesorteerd) >= 2:
            gap_boven = gesorteerd[-1].gewicht - gesorteerd[-2].gewicht
            if gap_boven > max_kg * 0.7:
                uitschieters.append(gesorteerd[-1])
                bulk = [j for j in bulk if j.id != gesorteerd[-1].id]

    return bulk, uitschieters


def voeg_uitschieters_toe(poules: List[Poule], uitschieters: List[Judoka], max_kg: float, max_lft: int) -> List[Poule]:
    """
    Voeg uitschieters toe aan de best passende poule.
    Sorteer uitschieters op gewicht en voeg ze één voor één toe.
    """
    # Sorteer uitschieters: lichtste eerst bij lichtste poule, etc.
    uitschieters_sorted = sorted(uitschieters, key=lambda j: j.gewicht)

    for uitschieter in uitschieters_sorted:
        beste_poule = None
        beste_range_na = float('inf')

        for poule in poules:
            if not poule.judokas:
                continue

            # Bereken wat de nieuwe range zou zijn
            nieuwe_min = min(poule.min_gewicht, uitschieter.gewicht)
            nieuwe_max = max(poule.max_gewicht, uitschieter.gewicht)
            nieuwe_range = nieuwe_max - nieuwe_min

            # Check leeftijd
            nieuwe_min_lft = min(poule.min_leeftijd, uitschieter.leeftijd)
            nieuwe_max_lft = max(poule.max_leeftijd, uitschieter.leeftijd)
            if nieuwe_max_lft - nieuwe_min_lft > max_lft:
                continue

            # Prefereer poule waar uitschieter het beste past (kleinste resulterende range)
            if nieuwe_range < beste_range_na:
                beste_range_na = nieuwe_range
                beste_poule = poule

        # Voeg ALLEEN toe als het binnen de criteria past - geen overschrijding!
        if beste_poule and beste_range_na <= max_kg:
            beste_poule.voeg_toe(uitschieter)
        else:
            # Uitschieter past nergens - wordt orphan (beter dan criteria overschrijden!)
            nieuwe_poule = Poule(judokas=[uitschieter])
            poules.append(nieuwe_poule)

    return poules


def maak_poules_greedy(
    judokas: List[Judoka],
    max_kg: float,
    max_lft: int,
    max_grootte: int = 5
) -> List[Poule]:
    """Stap 1: Simpel greedy algoritme - sorteer op leeftijd/gewicht/band, maak poules."""
    if not judokas:
        return []

    # Sorteer op leeftijd (primair), dan gewicht, dan band
    gesorteerd = sorted(judokas, key=lambda j: (j.leeftijd, j.gewicht, j.band))
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
    """Stap 2: Probeer orphans toe te voegen aan andere poules."""
    max_grootte = voorkeur[0] if voorkeur else 5
    absolute_max = max(voorkeur) if voorkeur else 6

    # Pass 1: Poules van 3-4
    poules = _fix_orphans_by_target_sizes(poules, max_kg, max_lft, voorkeur, [3, 4])
    # Pass 2: Poules van 5
    poules = _fix_orphans_by_target_sizes(poules, max_kg, max_lft, voorkeur, [5])
    # Pass 3: Split poules van 6+1
    poules = _fix_orphans_by_splitting(poules, max_kg, max_lft, voorkeur, absolute_max)
    # Pass 4: Merge kleine poules door judoka's te stelen
    poules = _fix_orphans_by_stealing(poules, max_kg, max_lft, voorkeur)

    return poules


def _fix_orphans_by_target_sizes(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int],
    target_sizes: List[int]
) -> List[Poule]:
    """Helper: plaats orphans in poules met specifieke groottes."""
    verbeterd = True

    while verbeterd:
        verbeterd = False
        kleine = [p for p in poules if p.size <= 2]

        for kleine_poule in kleine:
            for judoka in kleine_poule.judokas[:]:
                beste_poule = None
                beste_score = float('inf')

                for andere in poules:
                    if andere is kleine_poule:
                        continue
                    if andere.size not in target_sizes:
                        continue
                    if not andere.kan_toevoegen(judoka, max_kg, max_lft):
                        continue

                    nieuwe_score = bereken_grootte_penalty(andere.size + 1, voorkeur)
                    if nieuwe_score < beste_score:
                        beste_score = nieuwe_score
                        beste_poule = andere

                if beste_poule:
                    kleine_poule.verwijder(judoka)
                    beste_poule.voeg_toe(judoka)
                    verbeterd = True

        poules = [p for p in poules if p.size > 0]

    return poules


def _fix_orphans_by_splitting(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int],
    absolute_max: int
) -> List[Poule]:
    """Helper: poule van 6 + orphan → split naar 3+4."""
    verbeterd = True

    while verbeterd:
        verbeterd = False
        orphans = [p for p in poules if p.size == 1]

        for orphan_poule in orphans:
            judoka = orphan_poule.judokas[0]

            for andere in poules:
                if andere is orphan_poule:
                    continue
                if andere.size != absolute_max:
                    continue
                if not andere.kan_toevoegen(judoka, max_kg, max_lft):
                    continue

                alle_judokas = andere.judokas + [judoka]
                poule_3, poule_4 = _split_in_3_en_4(alle_judokas, max_kg, max_lft)

                if poule_3 and poule_4:
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
    """Split 7 judoka's in 3 + 4."""
    if len(judokas) != 7:
        return None, None

    gesorteerd = sorted(judokas, key=lambda j: (j.leeftijd, j.gewicht))
    beste_split = None
    beste_totaal_range = float('inf')

    for indices_3 in combinations(range(7), 3):
        indices_4 = [i for i in range(7) if i not in indices_3]

        groep_3 = [gesorteerd[i] for i in indices_3]
        groep_4 = [gesorteerd[i] for i in indices_4]

        poule_3 = Poule(judokas=groep_3)
        poule_4 = Poule(judokas=groep_4)

        if poule_3.gewicht_range > max_kg or poule_3.leeftijd_range > max_lft:
            continue
        if poule_4.gewicht_range > max_kg or poule_4.leeftijd_range > max_lft:
            continue

        totaal_range = poule_3.gewicht_range + poule_4.gewicht_range
        if totaal_range < beste_totaal_range:
            beste_totaal_range = totaal_range
            beste_split = (poule_3, poule_4)

    return beste_split if beste_split else (None, None)


def _fix_orphans_by_stealing(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """
    Pass 4: Probeer kleine poules groter te maken door judoka's te 'stelen' van andere poules.

    Als we 2 kleine poules hebben die niet samen kunnen (te grote range), probeer dan
    een judoka uit een grotere poule te stelen die wel bij de kleine poule past.
    """
    min_grootte = 3  # Minimum gewenste grootte
    max_iteraties = 20
    iteratie = 0

    while iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False

        # Vind kleine poules (size < 3)
        kleine = [p for p in poules if p.size < min_grootte]
        if not kleine:
            break

        for kleine_poule in kleine:
            # Probeer een judoka te stelen van een grotere poule
            for andere in poules:
                if andere is kleine_poule:
                    continue
                # Alleen stelen van poules die het kunnen missen (size > 3)
                if andere.size <= min_grootte:
                    continue

                # Zoek een judoka in andere die bij kleine_poule past
                for judoka in andere.judokas[:]:
                    # Check of judoka bij kleine_poule kan
                    if kleine_poule.kan_toevoegen(judoka, max_kg, max_lft):
                        # Check of andere nog valid is na verwijdering
                        temp_andere = [j for j in andere.judokas if j.id != judoka.id]
                        if len(temp_andere) < min_grootte:
                            continue  # Zou andere te klein maken

                        # Check of temp_andere nog binnen constraints blijft
                        if temp_andere:
                            gew = [j.gewicht for j in temp_andere]
                            lft = [j.leeftijd for j in temp_andere]
                            if max(gew) - min(gew) > max_kg or max(lft) - min(lft) > max_lft:
                                continue

                        # Steel de judoka!
                        andere.verwijder(judoka)
                        kleine_poule.voeg_toe(judoka)
                        verbeterd = True
                        break

                if verbeterd:
                    break
            if verbeterd:
                break

        if not verbeterd:
            break

    # Verwijder lege poules
    poules = [p for p in poules if p.size > 0]
    return poules


def merge_kleine_poules(
    poules: List[Poule],
    max_kg: float,
    max_lft: int,
    voorkeur: List[int]
) -> List[Poule]:
    """Stap 3: Merge kleine poules."""
    absolute_max = max(voorkeur) if voorkeur else 6
    verbeterd = True

    while verbeterd:
        verbeterd = False
        kleine = [p for p in poules if p.size < 4]

        for p1, p2 in combinations(kleine, 2):
            if p1 not in poules or p2 not in poules:
                continue

            combined_size = p1.size + p2.size
            if combined_size > absolute_max:
                continue

            all_judokas = p1.judokas + p2.judokas
            gewichten = [j.gewicht for j in all_judokas]
            leeftijden = [j.leeftijd for j in all_judokas]

            if max(gewichten) - min(gewichten) > max_kg:
                continue
            if max(leeftijden) - min(leeftijden) > max_lft:
                continue

            oude_score = bereken_grootte_penalty(p1.size, voorkeur) + bereken_grootte_penalty(p2.size, voorkeur)
            nieuwe_score = bereken_grootte_penalty(combined_size, voorkeur)

            if nieuwe_score < oude_score:
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
    """Stap 4: Swap optimalisatie."""
    max_iteraties = 100
    iteratie = 0

    while iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False
        oude_totaal = score_indeling(poules, voorkeur)

        te_groot = [p for p in poules if p.size > voorkeur[0]]
        min_grootte = voorkeur[-1] if voorkeur else 3
        te_klein = [p for p in poules if p.size < min_grootte]

        for grote in te_groot:
            for kleine in te_klein:
                if grote is kleine:
                    continue

                for judoka in grote.judokas[:]:
                    if kleine.kan_toevoegen(judoka, max_kg, max_lft):
                        grote.verwijder(judoka)
                        kleine.voeg_toe(judoka)

                        nieuwe_totaal = score_indeling(poules, voorkeur)

                        if nieuwe_totaal < oude_totaal:
                            verbeterd = True
                            break
                        else:
                            kleine.verwijder(judoka)
                            grote.voeg_toe(judoka)

                if verbeterd:
                    break
            if verbeterd:
                break

        if not verbeterd:
            break

    return poules


def optimaliseer_gewichtsranges(
    poules: List[Poule],
    max_kg: float,
    max_lft: int
) -> List[Poule]:
    """
    Stap 5: Optimaliseer gewichtsranges door judoka's te ruilen tussen poules.
    Zoekt naar poules waar de range te groot is en probeert te swappen.
    """
    max_iteraties = 50
    iteratie = 0

    while iteratie < max_iteraties:
        iteratie += 1
        verbeterd = False

        # Vind poules met te grote range (> 80% van max_kg)
        problematische = [(i, p) for i, p in enumerate(poules)
                          if p.gewicht_range > max_kg * 0.8 and p.size >= 2]

        if not problematische:
            break

        for idx, probl_poule in problematische:
            # Vind de zwaarste en lichtste judoka
            judokas_sorted = sorted(probl_poule.judokas, key=lambda j: j.gewicht)
            lichtste = judokas_sorted[0]
            zwaarste = judokas_sorted[-1]

            # Probeer zwaarste te ruilen met lichtere uit andere poule
            for andere_idx, andere in enumerate(poules):
                if andere_idx == idx or andere.size < 2:
                    continue

                andere_sorted = sorted(andere.judokas, key=lambda j: j.gewicht)

                # Probeer zwaarste → andere, lichtere kandidaat ← terug
                for kandidaat in andere_sorted:
                    # Kandidaat moet lichter zijn dan zwaarste
                    if kandidaat.gewicht >= zwaarste.gewicht:
                        continue

                    # Simuleer swap
                    # 1. Verwijder zwaarste uit probl, voeg kandidaat toe
                    temp_probl = [j for j in probl_poule.judokas if j.id != zwaarste.id]
                    temp_probl.append(kandidaat)

                    # 2. Verwijder kandidaat uit andere, voeg zwaarste toe
                    temp_andere = [j for j in andere.judokas if j.id != kandidaat.id]
                    temp_andere.append(zwaarste)

                    # Check of beide binnen limieten blijven
                    probl_gew = [j.gewicht for j in temp_probl]
                    probl_lft = [j.leeftijd for j in temp_probl]
                    andere_gew = [j.gewicht for j in temp_andere]
                    andere_lft = [j.leeftijd for j in temp_andere]

                    probl_ok = (max(probl_gew) - min(probl_gew) <= max_kg and
                                max(probl_lft) - min(probl_lft) <= max_lft)
                    andere_ok = (max(andere_gew) - min(andere_gew) <= max_kg and
                                 max(andere_lft) - min(andere_lft) <= max_lft)

                    if probl_ok and andere_ok:
                        # Swap verbetert de situatie?
                        oude_range = probl_poule.gewicht_range
                        nieuwe_range = max(probl_gew) - min(probl_gew)

                        if nieuwe_range < oude_range:
                            # Voer swap uit
                            probl_poule.verwijder(zwaarste)
                            probl_poule.voeg_toe(kandidaat)
                            andere.verwijder(kandidaat)
                            andere.voeg_toe(zwaarste)
                            verbeterd = True
                            break

                if verbeterd:
                    break

            # Probeer ook lichtste te ruilen met zwaardere
            if not verbeterd:
                for andere_idx, andere in enumerate(poules):
                    if andere_idx == idx or andere.size < 2:
                        continue

                    andere_sorted = sorted(andere.judokas, key=lambda j: j.gewicht, reverse=True)

                    for kandidaat in andere_sorted:
                        if kandidaat.gewicht <= lichtste.gewicht:
                            continue

                        # Simuleer swap
                        temp_probl = [j for j in probl_poule.judokas if j.id != lichtste.id]
                        temp_probl.append(kandidaat)
                        temp_andere = [j for j in andere.judokas if j.id != kandidaat.id]
                        temp_andere.append(lichtste)

                        probl_gew = [j.gewicht for j in temp_probl]
                        probl_lft = [j.leeftijd for j in temp_probl]
                        andere_gew = [j.gewicht for j in temp_andere]
                        andere_lft = [j.leeftijd for j in temp_andere]

                        probl_ok = (max(probl_gew) - min(probl_gew) <= max_kg and
                                    max(probl_lft) - min(probl_lft) <= max_lft)
                        andere_ok = (max(andere_gew) - min(andere_gew) <= max_kg and
                                     max(andere_lft) - min(andere_lft) <= max_lft)

                        if probl_ok and andere_ok:
                            oude_range = probl_poule.gewicht_range
                            nieuwe_range = max(probl_gew) - min(probl_gew)

                            if nieuwe_range < oude_range:
                                probl_poule.verwijder(lichtste)
                                probl_poule.voeg_toe(kandidaat)
                                andere.verwijder(kandidaat)
                                andere.voeg_toe(lichtste)
                                verbeterd = True
                                break

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
    """Greedy++ algoritme."""
    max_grootte = voorkeur[0] if voorkeur else 5
    poules = maak_poules_greedy(judokas, max_kg, max_lft, max_grootte)
    poules = fix_orphans(poules, max_kg, max_lft, voorkeur)
    poules = merge_kleine_poules(poules, max_kg, max_lft, voorkeur)
    poules = probeer_swaps(poules, max_kg, max_lft, voorkeur)
    # Stap 5: Optimaliseer gewichtsranges door slimme swaps
    poules = optimaliseer_gewichtsranges(poules, max_kg, max_lft)
    return poules


# =============================================================================
# Solve Functions
# =============================================================================

def solve_all(input_data: dict) -> dict:
    """
    NIEUWE MODUS: Classificeer + verdeel ALLE judoka's.

    Input:
    {
        "categorieen": {
            "u7": {"label": "U7", "max_leeftijd": 6, "geslacht": "gemengd", "max_kg_verschil": 3, ...},
            ...
        },
        "judokas": [
            {"id": 1, "leeftijd": 6, "gewicht": 22.5, "geslacht": "M", "band": 2, "club_id": 1},
            ...
        ],
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "gewicht_tolerantie": 0.5
    }

    Output:
    {
        "success": true,
        "poules": [
            {
                "categorie_key": "u7",
                "label": "U7",
                "gewichtsklasse": "22-25kg",
                "judoka_ids": [1, 2, 5],
                "gewicht_range": 2.5,
                "leeftijd_range": 1
            }
        ],
        "classificatie": {
            "1": {"key": "u7", "label": "U7", "gewichtsklasse": null},
            ...
        },
        "statistieken": {...}
    }
    """
    try:
        categorieen = input_data.get('categorieen', {})
        judokas_data = input_data.get('judokas', [])
        voorkeur = input_data.get('poule_grootte_voorkeur', [5, 4, 6, 3])
        tolerantie = input_data.get('gewicht_tolerantie', 0.5)

        if not categorieen:
            return {"success": False, "error": "Geen categorieen geconfigureerd"}

        # Parse judoka's
        judokas = []
        for j in judokas_data:
            judokas.append(Judoka(
                id=j['id'],
                leeftijd=j.get('leeftijd', 0),
                gewicht=float(j.get('gewicht', 0)),
                geslacht=j.get('geslacht', ''),
                band=j.get('band', 0),
                club_id=j.get('club_id', 0)
            ))

        if not judokas:
            return {
                "success": True,
                "poules": [],
                "classificatie": {},
                "statistieken": {"totaal_judokas": 0, "totaal_poules": 0}
            }

        # Classificeer alle judoka's
        classifier = CategorieClassifier(categorieen, tolerantie)
        classificatie = {}
        per_categorie: Dict[str, List[Judoka]] = {}

        for judoka in judokas:
            result = classifier.classificeer(judoka)
            if result:
                classificatie[str(judoka.id)] = result
                key = result['key']
                if key not in per_categorie:
                    per_categorie[key] = []
                per_categorie[key].append(judoka)
            else:
                classificatie[str(judoka.id)] = {"key": None, "label": "Onbekend"}

        # Verdeel per categorie
        alle_poules = []
        stats_per_cat = {}

        for cat_key, cat_judokas in per_categorie.items():
            config = categorieen.get(cat_key, {})
            label = config.get('label', cat_key)
            is_dynamisch = (config.get('max_kg_verschil', 0) > 0)

            if is_dynamisch:
                # Dynamische categorie: gebruik Greedy++
                max_kg = config.get('max_kg_verschil', 3)
                max_lft = config.get('max_leeftijd_verschil', 2)
                poules = greedy_plus_plus(cat_judokas, max_kg, max_lft, voorkeur)

                for poule in poules:
                    # Bepaal gewichtsklasse string
                    if poule.judokas:
                        min_kg = round(poule.min_gewicht, 1)
                        max_kg_val = round(poule.max_gewicht, 1)
                        gk = f"{min_kg}-{max_kg_val}kg" if min_kg != max_kg_val else f"{min_kg}kg"
                    else:
                        gk = ""

                    alle_poules.append({
                        "categorie_key": cat_key,
                        "label": label,
                        "gewichtsklasse": gk,
                        "judoka_ids": [j.id for j in poule.judokas],
                        "gewicht_range": round(poule.gewicht_range, 1),
                        "leeftijd_range": poule.leeftijd_range,
                        "size": poule.size
                    })

                stats_per_cat[cat_key] = {
                    "judokas": len(cat_judokas),
                    "poules": len(poules),
                    "type": "dynamisch"
                }
            else:
                # Vaste gewichtsklassen: groepeer per klasse
                per_klasse: Dict[str, List[Judoka]] = {}
                for judoka in cat_judokas:
                    klasse = classificatie.get(str(judoka.id), {}).get('gewichtsklasse', 'onbekend')
                    if klasse not in per_klasse:
                        per_klasse[klasse] = []
                    per_klasse[klasse].append(judoka)

                for klasse, klasse_judokas in per_klasse.items():
                    # Voor vaste klassen: verdeel in poules van voorkeur grootte
                    # Simpele greedy (geen kg/lft check nodig, klasse is al bepaald)
                    max_grootte = voorkeur[0] if voorkeur else 5
                    gesorteerd = sorted(klasse_judokas, key=lambda j: j.gewicht)

                    poule_judokas = []
                    for judoka in gesorteerd:
                        poule_judokas.append(judoka)
                        if len(poule_judokas) >= max_grootte:
                            alle_poules.append({
                                "categorie_key": cat_key,
                                "label": label,
                                "gewichtsklasse": klasse,
                                "judoka_ids": [j.id for j in poule_judokas],
                                "gewicht_range": round(max(j.gewicht for j in poule_judokas) - min(j.gewicht for j in poule_judokas), 1),
                                "leeftijd_range": max(j.leeftijd for j in poule_judokas) - min(j.leeftijd for j in poule_judokas),
                                "size": len(poule_judokas)
                            })
                            poule_judokas = []

                    # Laatste poule
                    if poule_judokas:
                        alle_poules.append({
                            "categorie_key": cat_key,
                            "label": label,
                            "gewichtsklasse": klasse,
                            "judoka_ids": [j.id for j in poule_judokas],
                            "gewicht_range": round(max(j.gewicht for j in poule_judokas) - min(j.gewicht for j in poule_judokas), 1) if len(poule_judokas) > 1 else 0,
                            "leeftijd_range": (max(j.leeftijd for j in poule_judokas) - min(j.leeftijd for j in poule_judokas)) if len(poule_judokas) > 1 else 0,
                            "size": len(poule_judokas)
                        })

                stats_per_cat[cat_key] = {
                    "judokas": len(cat_judokas),
                    "poules": len([p for p in alle_poules if p['categorie_key'] == cat_key]),
                    "type": "vast"
                }

        # Statistieken
        grootte_counts = {}
        for p in alle_poules:
            g = p['size']
            grootte_counts[g] = grootte_counts.get(g, 0) + 1

        return {
            "success": True,
            "poules": alle_poules,
            "classificatie": classificatie,
            "statistieken": {
                "totaal_judokas": len(judokas),
                "totaal_poules": len(alle_poules),
                "niet_geclassificeerd": len([c for c in classificatie.values() if c.get('key') is None]),
                "grootte_verdeling": grootte_counts,
                "orphans": grootte_counts.get(1, 0),
                "per_categorie": stats_per_cat
            }
        }

    except Exception as e:
        import traceback
        return {
            "success": False,
            "error": str(e),
            "traceback": traceback.format_exc()
        }


def solve(input_data: dict) -> dict:
    """
    LEGACY MODUS: Verdeel judoka's binnen één categorie.
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

        poules = greedy_plus_plus(judokas, max_kg, max_lft, voorkeur)

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

        # Bepaal modus: solve_all als 'categorieen' aanwezig is
        if 'categorieen' in input_data:
            result = solve_all(input_data)
        else:
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
