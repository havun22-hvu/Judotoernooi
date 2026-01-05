"""
Poule Indeling Solver Experiment
================================
Optimale verdeling met 3 variabelen:
- Leeftijd (max X jaar verschil)
- Gewicht (max X kg verschil)
- Band (zo gelijk mogelijk)

Run: python poule_solver_experiment.py
"""

import random
from dataclasses import dataclass
from typing import List, Dict
from collections import defaultdict

# Band kleuren (0=wit, 7=zwart)
BANDEN = ['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart']

@dataclass
class Judoka:
    id: int
    naam: str
    leeftijd: int      # 6-18 jaar
    gewicht: float     # 20-80 kg
    band: int          # 0-6 (wit-zwart)
    geslacht: str      # M/V

    def __repr__(self):
        return f"{self.naam} ({self.leeftijd}j, {self.gewicht}kg, {BANDEN[self.band]})"

@dataclass
class Poule:
    judokas: List[Judoka]

    @property
    def leeftijd_range(self) -> int:
        if not self.judokas:
            return 0
        leeftijden = [j.leeftijd for j in self.judokas]
        return max(leeftijden) - min(leeftijden)

    @property
    def gewicht_range(self) -> float:
        if not self.judokas:
            return 0
        gewichten = [j.gewicht for j in self.judokas]
        return max(gewichten) - min(gewichten)

    @property
    def band_range(self) -> int:
        if not self.judokas:
            return 0
        banden = [j.band for j in self.judokas]
        return max(banden) - min(banden)

    def score(self, max_leeftijd=3, max_gewicht=3, max_band=2,
              weight_leeftijd=0.4, weight_gewicht=0.4, weight_band=0.2) -> float:
        """
        Lagere score = betere poule
        Penalty als grenzen overschreden worden
        """
        score = 0

        # Leeftijd penalty
        if self.leeftijd_range > max_leeftijd:
            score += (self.leeftijd_range - max_leeftijd) * 10 * weight_leeftijd
        else:
            score += self.leeftijd_range * weight_leeftijd

        # Gewicht penalty
        if self.gewicht_range > max_gewicht:
            score += (self.gewicht_range - max_gewicht) * 10 * weight_gewicht
        else:
            score += self.gewicht_range * weight_gewicht

        # Band penalty
        if self.band_range > max_band:
            score += (self.band_range - max_band) * 5 * weight_band
        else:
            score += self.band_range * weight_band

        return score

    def __repr__(self):
        return f"Poule({len(self.judokas)}): L={self.leeftijd_range}j, G={self.gewicht_range:.1f}kg, B={self.band_range}"


def genereer_test_judokas(aantal: int = 100) -> List[Judoka]:
    """Genereer random test data"""
    judokas = []
    for i in range(aantal):
        leeftijd = random.randint(7, 15)
        # Gewicht correleert enigszins met leeftijd
        basis_gewicht = 20 + (leeftijd - 6) * 4
        gewicht = round(basis_gewicht + random.uniform(-5, 10), 1)
        # Jongere = lagere band (meestal)
        max_band = min(leeftijd - 6, 6)
        band = random.randint(0, max_band)
        geslacht = random.choice(['M', 'V'])

        judokas.append(Judoka(
            id=i+1,
            naam=f"Judoka_{i+1}",
            leeftijd=leeftijd,
            gewicht=gewicht,
            band=band,
            geslacht=geslacht
        ))
    return judokas


def algoritme_gewicht_eerst(judokas: List[Judoka],
                            max_gewicht: float = 3.0,
                            max_leeftijd: int = 3,
                            poule_grootte: int = 5) -> List[Poule]:
    """
    Optie A: Eerst groeperen op gewicht, dan sorteren op band
    """
    # Sorteer op gewicht
    gesorteerd = sorted(judokas, key=lambda j: j.gewicht)

    # Maak gewichtsgroepen (breekpunten)
    groepen = []
    huidige_groep = [gesorteerd[0]]

    for j in gesorteerd[1:]:
        # Check of judoka in huidige groep past
        min_gewicht = min(jj.gewicht for jj in huidige_groep)
        if j.gewicht - min_gewicht <= max_gewicht:
            huidige_groep.append(j)
        else:
            groepen.append(huidige_groep)
            huidige_groep = [j]
    groepen.append(huidige_groep)

    # Per groep: sorteer op band, verdeel in poules
    poules = []
    for groep in groepen:
        # Sorteer op band
        groep_sorted = sorted(groep, key=lambda j: j.band)

        # Verdeel in poules
        for i in range(0, len(groep_sorted), poule_grootte):
            poule_judokas = groep_sorted[i:i+poule_grootte]
            if len(poule_judokas) >= 2:  # Minimaal 2 voor een poule
                poules.append(Poule(judokas=poule_judokas))

    return poules


def algoritme_band_eerst(judokas: List[Judoka],
                         max_gewicht: float = 3.0,
                         poule_grootte: int = 5) -> List[Poule]:
    """
    Optie B: Eerst groeperen op band, dan sorteren op gewicht
    """
    # Groepeer op band
    band_groepen = defaultdict(list)
    for j in judokas:
        band_groepen[j.band].append(j)

    poules = []
    for band, groep in sorted(band_groepen.items()):
        # Sorteer op gewicht
        groep_sorted = sorted(groep, key=lambda j: j.gewicht)

        # Maak poules met max gewicht check
        huidige_poule = []
        for j in groep_sorted:
            if not huidige_poule:
                huidige_poule.append(j)
            elif (j.gewicht - huidige_poule[0].gewicht <= max_gewicht and
                  len(huidige_poule) < poule_grootte):
                huidige_poule.append(j)
            else:
                if len(huidige_poule) >= 2:
                    poules.append(Poule(judokas=huidige_poule))
                huidige_poule = [j]

        if len(huidige_poule) >= 2:
            poules.append(Poule(judokas=huidige_poule))

    return poules


def algoritme_leeftijd_gewicht_band(judokas: List[Judoka],
                                     max_leeftijd: int = 3,
                                     max_gewicht: float = 3.0,
                                     poule_grootte: int = 5) -> List[Poule]:
    """
    Nieuw: Eerst leeftijd, dan gewicht, dan band
    """
    # Stap 1: Groepeer op leeftijd (breekpunten)
    gesorteerd = sorted(judokas, key=lambda j: j.leeftijd)

    leeftijd_groepen = []
    huidige_groep = [gesorteerd[0]]

    for j in gesorteerd[1:]:
        min_leeftijd = min(jj.leeftijd for jj in huidige_groep)
        if j.leeftijd - min_leeftijd <= max_leeftijd:
            huidige_groep.append(j)
        else:
            leeftijd_groepen.append(huidige_groep)
            huidige_groep = [j]
    leeftijd_groepen.append(huidige_groep)

    # Stap 2: Per leeftijdsgroep, groepeer op gewicht
    poules = []
    for lg in leeftijd_groepen:
        # Sorteer op gewicht
        lg_sorted = sorted(lg, key=lambda j: j.gewicht)

        gewicht_groepen = []
        huidige_gg = [lg_sorted[0]]

        for j in lg_sorted[1:]:
            min_gewicht = min(jj.gewicht for jj in huidige_gg)
            if j.gewicht - min_gewicht <= max_gewicht:
                huidige_gg.append(j)
            else:
                gewicht_groepen.append(huidige_gg)
                huidige_gg = [j]
        gewicht_groepen.append(huidige_gg)

        # Stap 3: Per gewichtsgroep, sorteer op band, maak poules
        for gg in gewicht_groepen:
            gg_sorted = sorted(gg, key=lambda j: j.band)

            for i in range(0, len(gg_sorted), poule_grootte):
                poule_judokas = gg_sorted[i:i+poule_grootte]
                if len(poule_judokas) >= 2:
                    poules.append(Poule(judokas=poule_judokas))

    return poules


def evalueer_poules(poules: List[Poule], naam: str):
    """Print statistieken van poule-indeling"""
    print(f"\n{'='*60}")
    print(f"ALGORITME: {naam}")
    print(f"{'='*60}")
    print(f"Aantal poules: {len(poules)}")
    print(f"Totaal judokas: {sum(len(p.judokas) for p in poules)}")

    # Statistieken
    leeftijd_ranges = [p.leeftijd_range for p in poules]
    gewicht_ranges = [p.gewicht_range for p in poules]
    band_ranges = [p.band_range for p in poules]
    scores = [p.score() for p in poules]

    print(f"\nLeeftijd range: gem={sum(leeftijd_ranges)/len(poules):.1f}, max={max(leeftijd_ranges)}")
    print(f"Gewicht range:  gem={sum(gewicht_ranges)/len(poules):.1f}kg, max={max(gewicht_ranges):.1f}kg")
    print(f"Band range:     gem={sum(band_ranges)/len(poules):.1f}, max={max(band_ranges)}")
    print(f"Totale score:   {sum(scores):.1f} (lager = beter)")

    # Overtredingen
    leeftijd_violations = sum(1 for r in leeftijd_ranges if r > 3)
    gewicht_violations = sum(1 for r in gewicht_ranges if r > 3)

    print(f"\nOvertredingen:")
    print(f"  Leeftijd >3 jaar: {leeftijd_violations} poules")
    print(f"  Gewicht >3 kg:    {gewicht_violations} poules")

    # Voorbeeld poules
    print(f"\nVoorbeeld poules:")
    for p in poules[:3]:
        print(f"  {p}")
        for j in p.judokas:
            print(f"    - {j}")


def main():
    print("="*60)
    print("POULE INDELING SOLVER EXPERIMENT")
    print("="*60)

    # Genereer test data
    random.seed(42)  # Voor reproduceerbare resultaten
    judokas = genereer_test_judokas(400)

    print(f"\nTest data: {len(judokas)} judoka's")
    print(f"Leeftijden: {min(j.leeftijd for j in judokas)}-{max(j.leeftijd for j in judokas)} jaar")
    print(f"Gewichten: {min(j.gewicht for j in judokas)}-{max(j.gewicht for j in judokas)} kg")

    # Test verschillende algoritmes
    poules1 = algoritme_gewicht_eerst(judokas, max_gewicht=3.0)
    evalueer_poules(poules1, "GEWICHT > BAND")

    poules2 = algoritme_band_eerst(judokas, max_gewicht=3.0)
    evalueer_poules(poules2, "BAND > GEWICHT")

    poules3 = algoritme_leeftijd_gewicht_band(judokas, max_leeftijd=2, max_gewicht=3.0)
    evalueer_poules(poules3, "LEEFTIJD > GEWICHT > BAND")

    print("\n" + "="*60)
    print("CONCLUSIE")
    print("="*60)
    scores = {
        "GEWICHT > BAND": sum(p.score() for p in poules1),
        "BAND > GEWICHT": sum(p.score() for p in poules2),
        "LEEFTIJD > GEWICHT > BAND": sum(p.score() for p in poules3),
    }
    beste = min(scores, key=scores.get)
    print(f"Beste algoritme: {beste} (score: {scores[beste]:.1f})")


if __name__ == "__main__":
    main()
