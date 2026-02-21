"""
Tests voor poule_solver.py — beschermt tegen regressie.

Run: python -m pytest scripts/test_poule_solver.py -v
Of:  python scripts/test_poule_solver.py  (zonder pytest)
"""

import sys
import os
sys.path.insert(0, os.path.dirname(__file__))

from poule_solver import Judoka, verdeel_judokas


# =============================================================================
# HELPERS
# =============================================================================

def maak_judokas(gewichten, leeftijd=8, band=0):
    """Maak judoka's met opgegeven gewichten."""
    return [
        Judoka(id=i+1, leeftijd=leeftijd, gewicht=g, band=band, club_id=(i % 4) + 1)
        for i, g in enumerate(gewichten)
    ]


def verdeel(judokas, voorkeur=None, max_kg=3.0, max_lft=0, max_band=0, tolerantie=0.5):
    """Wrapper voor verdeel_judokas met defaults."""
    if voorkeur is None:
        voorkeur = [5, 4, 6, 3]
    return verdeel_judokas(
        judokas, max_kg, max_lft, max_band, voorkeur,
        ['band', 'gewicht', 'leeftijd'], tolerantie
    )


def poule_groottes(poules):
    """Return gesorteerde lijst van poule groottes."""
    return sorted([p.size for p in poules], reverse=True)


# =============================================================================
# REGEL 1: Poule grootte mag NOOIT boven max(voorkeur) komen
# =============================================================================

def test_max_grootte_wordt_gerespecteerd():
    """Geen enkele poule mag groter zijn dan max(voorkeur)."""
    voorkeur = [5, 4, 6, 3]
    max_grootte = max(voorkeur)

    # 12 judoka's met identiek gewicht — alles past bij elkaar
    judokas = maak_judokas([30.0 + i*0.1 for i in range(12)])
    poules, orphans = verdeel(judokas, voorkeur)

    for poule in poules:
        assert poule.size <= max_grootte, \
            f"Poule te groot: {poule.size} > max {max_grootte}"


def test_max_grootte_met_veel_judokas():
    """Ook met 20+ judoka's in zelfde range: max grootte respecteren."""
    voorkeur = [5, 4, 6, 3]
    max_grootte = max(voorkeur)

    judokas = maak_judokas([30.0 + i*0.1 for i in range(20)])
    poules, orphans = verdeel(judokas, voorkeur)

    for poule in poules:
        assert poule.size <= max_grootte, \
            f"Poule te groot: {poule.size} > max {max_grootte}"


def test_max_grootte_kleine_voorkeur():
    """Met voorkeur [4, 3] mag max 4."""
    voorkeur = [4, 3]
    judokas = maak_judokas([30.0 + i*0.1 for i in range(10)])
    poules, orphans = verdeel(judokas, voorkeur)

    for poule in poules:
        assert poule.size <= 4, f"Poule te groot: {poule.size} > 4"


# =============================================================================
# REGEL 2: Gewichtsverschil binnen poule mag NOOIT boven max_kg komen
# =============================================================================

def test_gewichtsverschil_binnen_limiet():
    """Binnen elke poule: max gewichtsverschil <= max_kg."""
    max_kg = 3.0
    judokas = maak_judokas([20, 21, 22, 25, 26, 27, 30, 31, 32, 35, 36])
    poules, orphans = verdeel(judokas, max_kg=max_kg)

    for poule in poules:
        verschil = poule.max_gewicht - poule.min_gewicht
        assert verschil <= max_kg + 0.5, \
            f"Gewichtsverschil {verschil:.1f}kg > max {max_kg}kg (+ 0.5 tolerantie)"


def test_gewichtsverschil_strikte_limiet():
    """Zonder tolerantie: gewichtsverschil strikt <= max_kg."""
    max_kg = 3.0
    judokas = maak_judokas([20, 21, 22, 25, 26, 27, 30, 31, 32])
    poules, orphans = verdeel(judokas, max_kg=max_kg, tolerantie=0)

    for poule in poules:
        verschil = poule.max_gewicht - poule.min_gewicht
        assert verschil <= max_kg, \
            f"Gewichtsverschil {verschil:.1f}kg > max {max_kg}kg"


# =============================================================================
# REGEL 3: Alle judoka's moeten ingedeeld worden (geen orphans als het kan)
# =============================================================================

def test_geen_orphans_bij_gelijke_gewichten():
    """Als alle judoka's compatible zijn, geen orphans."""
    judokas = maak_judokas([30.0 + i*0.1 for i in range(10)])
    poules, orphans = verdeel(judokas)

    totaal_ingedeeld = sum(p.size for p in poules)
    assert totaal_ingedeeld == 10, f"Verwacht 10 ingedeeld, got {totaal_ingedeeld}"
    assert len(orphans) == 0, f"Onverwachte orphans: {len(orphans)}"


def test_geen_orphans_bij_clusters():
    """Clusters van 7 (> max_grootte 6): orphan moet gesplitst worden."""
    # 7 judoka's in zelfde range — greedy maakt poule van 5, 1 orphan van 2
    # Split-strategie moet dat oplossen
    judokas = maak_judokas([34.0, 34.0, 34.2, 34.2, 34.5, 34.5, 34.8])
    poules, orphans = verdeel(judokas, voorkeur=[5, 4, 6, 3])

    assert len(orphans) == 0, \
        f"Orphans bij cluster van 7: {len(orphans)} (groottes: {poule_groottes(poules)})"


def test_alle_judokas_ingedeeld_staging_scenario():
    """Exact de staging data die het probleem veroorzaakte: 38 U9 judoka's."""
    judokas = maak_judokas([
        20.5, 20.8, 20.2, 21.3,           # ~20kg groep
        23.5, 24.0, 23.0, 23.3,           # ~23kg groep
        27.0, 27.3, 27.5,                  # ~27kg groep
        30.0, 30.5, 30.8,                  # ~30kg groep
        34.0, 34.2, 34.5, 34.8,           # ~34kg groep
        20.0, 19.8, 20.2, 20.8,           # ~20kg groep (V)
        23.5, 23.2, 23.8, 23.0,           # ~23kg groep (V)
        27.0, 27.5, 27.8,                  # ~27kg groep (V)
        30.0, 30.5, 30.8,                  # ~30kg groep (V)
        34.0, 34.5, 34.2,                  # ~34kg groep (V)
        38.0, 38.5, 38.8,                  # ~38kg groep (V)
    ])
    poules, orphans = verdeel(judokas, voorkeur=[5, 4, 6, 3])

    totaal = sum(p.size for p in poules)
    assert totaal == 38, f"Niet alle judoka's ingedeeld: {totaal}/38"
    assert len(orphans) == 0, \
        f"Orphans: {len(orphans)} (groottes: {poule_groottes(poules)})"


# =============================================================================
# REGEL 4: Voorkeursvolgorde wordt gerespecteerd
# =============================================================================

def test_voorkeur_5_meest_voorkomend():
    """Bij voorkeur [5,...] moeten poules van 5 het vaakst voorkomen."""
    judokas = maak_judokas([30.0 + i*0.1 for i in range(15)])
    poules, orphans = verdeel(judokas, voorkeur=[5, 4, 6, 3])

    groottes = poule_groottes(poules)
    count_5 = groottes.count(5)
    assert count_5 >= 2, f"Verwacht minstens 2 poules van 5, got {count_5} (groottes: {groottes})"


# =============================================================================
# REGEL 5: Leeftijdsverschil wordt gerespecteerd
# =============================================================================

def test_leeftijdsverschil_limiet():
    """max_lft=1: geen judoka's met >1 jaar verschil in poule."""
    judokas = [
        Judoka(id=1, leeftijd=7, gewicht=25, band=0, club_id=1),
        Judoka(id=2, leeftijd=8, gewicht=25, band=0, club_id=2),
        Judoka(id=3, leeftijd=9, gewicht=25, band=0, club_id=3),
        Judoka(id=4, leeftijd=10, gewicht=25, band=0, club_id=4),
        Judoka(id=5, leeftijd=7, gewicht=25.5, band=0, club_id=1),
        Judoka(id=6, leeftijd=8, gewicht=25.5, band=0, club_id=2),
        Judoka(id=7, leeftijd=9, gewicht=25.5, band=0, club_id=3),
        Judoka(id=8, leeftijd=10, gewicht=25.5, band=0, club_id=4),
    ]
    poules, orphans = verdeel(judokas, max_lft=1)

    for poule in poules:
        verschil = poule.max_leeftijd - poule.min_leeftijd
        assert verschil <= 1, \
            f"Leeftijdsverschil {verschil} > max 1 in poule"


# =============================================================================
# REGEL 6: Band verschil wordt gerespecteerd
# =============================================================================

def test_band_verschil_limiet():
    """max_band=1: geen wit+oranje in zelfde poule."""
    judokas = [
        Judoka(id=1, leeftijd=8, gewicht=25, band=0, club_id=1),  # wit
        Judoka(id=2, leeftijd=8, gewicht=25, band=1, club_id=2),  # geel
        Judoka(id=3, leeftijd=8, gewicht=25, band=2, club_id=3),  # oranje
        Judoka(id=4, leeftijd=8, gewicht=25, band=0, club_id=4),  # wit
        Judoka(id=5, leeftijd=8, gewicht=25.5, band=1, club_id=1),  # geel
        Judoka(id=6, leeftijd=8, gewicht=25.5, band=2, club_id=2),  # oranje
    ]
    poules, orphans = verdeel(judokas, max_band=1)

    for poule in poules:
        verschil = poule.max_band - poule.min_band
        assert verschil <= 1, \
            f"Bandverschil {verschil} > max 1 in poule"


# =============================================================================
# EDGE CASES
# =============================================================================

def test_lege_input():
    """Geen judoka's → geen poules, geen errors."""
    poules, orphans = verdeel([])
    assert len(poules) == 0
    assert len(orphans) == 0


def test_exact_voorkeurs_grootte():
    """Precies 5 judoka's → 1 poule van 5."""
    judokas = maak_judokas([30.0, 30.5, 31.0, 31.5, 32.0])
    poules, orphans = verdeel(judokas, voorkeur=[5, 4, 6, 3])

    assert len(poules) == 1
    assert poules[0].size == 5


def test_twee_gescheiden_groepen():
    """Twee gewichtsgroepen ver uit elkaar → twee aparte poules."""
    judokas = maak_judokas([20, 20.5, 21, 21.5, 22,  # groep 1
                            40, 40.5, 41, 41.5, 42])  # groep 2
    poules, orphans = verdeel(judokas, max_kg=3.0)

    assert len(poules) == 2, f"Verwacht 2 poules, got {len(poules)}"
    for poule in poules:
        assert poule.gewicht_range <= 3.0


# =============================================================================
# RUN ZONDER PYTEST
# =============================================================================

if __name__ == "__main__":
    tests = [v for k, v in globals().items() if k.startswith('test_')]
    geslaagd = 0
    gefaald = 0

    for test in tests:
        try:
            test()
            print(f"  OK  {test.__name__}")
            geslaagd += 1
        except AssertionError as e:
            print(f"  FAIL {test.__name__}: {e}")
            gefaald += 1
        except Exception as e:
            print(f"  ERROR {test.__name__}: {e}")
            gefaald += 1

    print(f"\n{'='*50}")
    print(f"Resultaat: {geslaagd} geslaagd, {gefaald} gefaald")
    if gefaald > 0:
        sys.exit(1)
