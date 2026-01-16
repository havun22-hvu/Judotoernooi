#!/usr/bin/env python3
"""
Test script voor poule_solver.py
Run: python test_poule_solver.py
"""

import json
import random
from poule_solver import solve, Judoka, greedy_plus_plus, score_indeling, maak_poules_greedy

def genereer_test_judokas(aantal: int, leeftijd_range=(6, 8), gewicht_range=(20, 35)) -> list:
    """Genereer random test data."""
    judokas = []
    for i in range(aantal):
        leeftijd = random.randint(*leeftijd_range)
        # Gewicht correleert enigszins met leeftijd
        basis = gewicht_range[0] + (leeftijd - leeftijd_range[0]) * 3
        gewicht = round(basis + random.uniform(-3, 5), 1)
        band = random.randint(0, min(leeftijd - 5, 4))

        judokas.append({
            "id": i + 1,
            "leeftijd": leeftijd,
            "gewicht": gewicht,
            "band": max(0, band),
            "club_id": random.randint(1, 10)
        })
    return judokas


def test_basic():
    """Test met kleine dataset."""
    print("=" * 60)
    print("TEST 1: Kleine dataset (10 judoka's)")
    print("=" * 60)

    input_data = {
        "categorie": "U7",
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": [
            {"id": 1, "leeftijd": 6, "gewicht": 22.0, "band": 1, "club_id": 1},
            {"id": 2, "leeftijd": 6, "gewicht": 23.5, "band": 1, "club_id": 2},
            {"id": 3, "leeftijd": 6, "gewicht": 24.0, "band": 2, "club_id": 1},
            {"id": 4, "leeftijd": 6, "gewicht": 24.5, "band": 1, "club_id": 3},
            {"id": 5, "leeftijd": 6, "gewicht": 25.0, "band": 2, "club_id": 2},
            {"id": 6, "leeftijd": 7, "gewicht": 26.0, "band": 2, "club_id": 4},
            {"id": 7, "leeftijd": 7, "gewicht": 27.5, "band": 3, "club_id": 1},
            {"id": 8, "leeftijd": 7, "gewicht": 28.0, "band": 2, "club_id": 5},
            {"id": 9, "leeftijd": 7, "gewicht": 30.0, "band": 3, "club_id": 3},
            {"id": 10, "leeftijd": 7, "gewicht": 31.5, "band": 3, "club_id": 2},
        ]
    }

    result = solve(input_data)

    print(f"Success: {result['success']}")
    print(f"Poules: {result['stats']['totaal_poules']}")
    print(f"Score: {result['stats']['score']}")
    print(f"Grootte verdeling: {result['stats']['grootte_verdeling']}")
    print()

    for i, poule in enumerate(result['poules'], 1):
        print(f"Poule {i}: {poule['size']} judoka's, gewicht {poule['gewicht_range']}kg, leeftijd {poule['leeftijd_range']}j")
        print(f"  IDs: {poule['judoka_ids']}")

    return result['success']


def test_medium():
    """Test met medium dataset."""
    print("\n" + "=" * 60)
    print("TEST 2: Medium dataset (50 judoka's)")
    print("=" * 60)

    random.seed(42)
    judokas = genereer_test_judokas(50, leeftijd_range=(6, 8), gewicht_range=(20, 35))

    input_data = {
        "categorie": "U9",
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": judokas
    }

    result = solve(input_data)

    print(f"Success: {result['success']}")
    print(f"Poules: {result['stats']['totaal_poules']}")
    print(f"Score: {result['stats']['score']}")
    print(f"Grootte verdeling: {result['stats']['grootte_verdeling']}")
    print(f"Orphans: {result['stats']['orphans']}")

    # Vergelijk met pure greedy
    judoka_objs = [Judoka(j['id'], j['leeftijd'], j['gewicht'], j['band'], j['club_id']) for j in judokas]
    greedy_poules = maak_poules_greedy(judoka_objs, 3.0, 2, 5)
    greedy_score = score_indeling(greedy_poules, [5, 4, 6, 3])

    print(f"\nVergelijking:")
    print(f"  Greedy basis score: {greedy_score}")
    print(f"  Greedy++ score:     {result['stats']['score']}")
    print(f"  Verbetering:        {greedy_score - result['stats']['score']}")

    return result['success']


def test_large():
    """Test met grote dataset."""
    print("\n" + "=" * 60)
    print("TEST 3: Grote dataset (200 judoka's)")
    print("=" * 60)

    random.seed(123)
    judokas = genereer_test_judokas(200, leeftijd_range=(7, 12), gewicht_range=(25, 50))

    input_data = {
        "categorie": "Jeugd",
        "max_kg_verschil": 4.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": judokas
    }

    result = solve(input_data)

    print(f"Success: {result['success']}")
    print(f"Poules: {result['stats']['totaal_poules']}")
    print(f"Score: {result['stats']['score']}")
    print(f"Grootte verdeling: {result['stats']['grootte_verdeling']}")
    print(f"Orphans: {result['stats']['orphans']}")

    # Tel ideale poules
    ideaal = result['stats']['grootte_verdeling'].get(5, 0)
    goed = result['stats']['grootte_verdeling'].get(4, 0)
    totaal = result['stats']['totaal_poules']

    print(f"\nKwaliteit:")
    print(f"  Poules van 5: {ideaal} ({100*ideaal/totaal:.0f}%)")
    print(f"  Poules van 4: {goed} ({100*goed/totaal:.0f}%)")

    return result['success']


def test_edge_cases():
    """Test edge cases."""
    print("\n" + "=" * 60)
    print("TEST 4: Edge cases")
    print("=" * 60)

    # Lege input
    result = solve({"judokas": []})
    print(f"Lege input: success={result['success']}, poules={result['stats']['totaal_poules']}")

    # 1 judoka (orphan)
    result = solve({
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": [{"id": 1, "leeftijd": 6, "gewicht": 25.0}]
    })
    print(f"1 judoka: success={result['success']}, orphans={result['stats']['orphans']}")

    # Alle judoka's zelfde gewicht (perfecte match)
    result = solve({
        "max_kg_verschil": 3.0,
        "max_leeftijd_verschil": 2,
        "poule_grootte_voorkeur": [5, 4, 6, 3],
        "judokas": [{"id": i, "leeftijd": 6, "gewicht": 25.0} for i in range(1, 11)]
    })
    print(f"Zelfde gewicht: success={result['success']}, poules={result['stats']['totaal_poules']}, score={result['stats']['score']}")

    return True


def main():
    print("POULE SOLVER TEST SUITE")
    print("=" * 60)

    tests = [
        ("Basic", test_basic),
        ("Medium", test_medium),
        ("Large", test_large),
        ("Edge cases", test_edge_cases),
    ]

    results = []
    for name, test_fn in tests:
        try:
            success = test_fn()
            results.append((name, success))
        except Exception as e:
            print(f"ERROR in {name}: {e}")
            results.append((name, False))

    print("\n" + "=" * 60)
    print("RESULTATEN")
    print("=" * 60)
    for name, success in results:
        status = "PASS" if success else "FAIL"
        print(f"  {name}: {status}")

    all_passed = all(r[1] for r in results)
    print(f"\nTotaal: {'ALL PASSED' if all_passed else 'SOME FAILED'}")

    return 0 if all_passed else 1


if __name__ == "__main__":
    exit(main())
