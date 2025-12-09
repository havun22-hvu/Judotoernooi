#!/usr/bin/env python3
"""Test script for blok_mat_solver"""

import json
import sys
sys.path.insert(0, '.')
from blok_mat_solver import solve_blok_mat_distribution

# Sample test data: 3 blokken, 2 matten, 12 poules
test_data = {
    'blokken': [
        {'id': 1, 'nummer': 1},
        {'id': 2, 'nummer': 2},
        {'id': 3, 'nummer': 3},
    ],
    'matten': [
        {'id': 1, 'nummer': 1},
        {'id': 2, 'nummer': 2},
    ],
    'poules': [
        # U11 - 3 weight classes
        {'id': 1, 'leeftijdsklasse': 'U11', 'gewichtsklasse': '-30', 'aantal_wedstrijden': 6},
        {'id': 2, 'leeftijdsklasse': 'U11', 'gewichtsklasse': '-35', 'aantal_wedstrijden': 6},
        {'id': 3, 'leeftijdsklasse': 'U11', 'gewichtsklasse': '-40', 'aantal_wedstrijden': 10},
        {'id': 4, 'leeftijdsklasse': 'U11', 'gewichtsklasse': '-40', 'aantal_wedstrijden': 10},  # 2nd poule same weight
        # U13 - 3 weight classes
        {'id': 5, 'leeftijdsklasse': 'U13', 'gewichtsklasse': '-40', 'aantal_wedstrijden': 6},
        {'id': 6, 'leeftijdsklasse': 'U13', 'gewichtsklasse': '-50', 'aantal_wedstrijden': 10},
        {'id': 7, 'leeftijdsklasse': 'U13', 'gewichtsklasse': '+50', 'aantal_wedstrijden': 6},
        # U15 - 3 weight classes
        {'id': 8, 'leeftijdsklasse': 'U15', 'gewichtsklasse': '-50', 'aantal_wedstrijden': 10},
        {'id': 9, 'leeftijdsklasse': 'U15', 'gewichtsklasse': '-60', 'aantal_wedstrijden': 6},
        {'id': 10, 'leeftijdsklasse': 'U15', 'gewichtsklasse': '+60', 'aantal_wedstrijden': 6},
    ]
}

print("Testing blok_mat_solver...")
print(f"Input: {len(test_data['blokken'])} blokken, {len(test_data['matten'])} matten, {len(test_data['poules'])} poules")
print(f"Total matches: {sum(p['aantal_wedstrijden'] for p in test_data['poules'])}")
print()

result = solve_blok_mat_distribution(test_data)

if result['success']:
    print("SUCCESS!")
    print(f"Status: {result['statistics']['status']}")
    print(f"Max deviation: {result['statistics']['max_deviation']}")
    print()

    # Group by blok
    blok_assignments = {}
    for a in result['assignments']:
        blok_id = a['blok_id']
        if blok_id not in blok_assignments:
            blok_assignments[blok_id] = []
        # Find poule info
        poule = next(p for p in test_data['poules'] if p['id'] == a['poule_id'])
        blok_assignments[blok_id].append({
            'poule_id': a['poule_id'],
            'mat_id': a['mat_id'],
            'leeftijd': poule['leeftijdsklasse'],
            'gewicht': poule['gewichtsklasse'],
            'wedstrijden': poule['aantal_wedstrijden'],
        })

    print("Assignments per blok:")
    for blok_id in sorted(blok_assignments.keys()):
        poules = blok_assignments[blok_id]
        total = sum(p['wedstrijden'] for p in poules)
        print(f"\nBlok {blok_id} ({total} wedstrijden):")
        for p in sorted(poules, key=lambda x: (x['leeftijd'], x['gewicht'])):
            print(f"  Poule {p['poule_id']}: {p['leeftijd']} {p['gewicht']} -> Mat {p['mat_id']} ({p['wedstrijden']} matches)")

    # Verify constraints
    print("\n--- Constraint Verification ---")

    # Check: same leeftijd+gewicht in same blok
    categories = {}
    for a in result['assignments']:
        poule = next(p for p in test_data['poules'] if p['id'] == a['poule_id'])
        key = (poule['leeftijdsklasse'], poule['gewichtsklasse'])
        if key not in categories:
            categories[key] = set()
        categories[key].add(a['blok_id'])

    all_same_blok = all(len(bloks) == 1 for bloks in categories.values())
    print(f"Same leeftijd+gewicht in same blok: {'PASS' if all_same_blok else 'FAIL'}")

    # Check: weights increase per leeftijdsklasse
    leeftijd_weights = {}
    for cat, bloks in categories.items():
        leeftijd, gewicht = cat
        blok = list(bloks)[0]
        if leeftijd not in leeftijd_weights:
            leeftijd_weights[leeftijd] = []
        from blok_mat_solver import parse_weight
        leeftijd_weights[leeftijd].append((parse_weight(gewicht), blok, gewicht))

    weights_ok = True
    for leeftijd, weights in leeftijd_weights.items():
        weights.sort(key=lambda x: x[0])  # Sort by weight
        for i in range(len(weights) - 1):
            if weights[i][1] > weights[i+1][1]:  # blok of lighter > blok of heavier
                weights_ok = False
                print(f"  FAIL: {leeftijd} {weights[i][2]} (blok {weights[i][1]}) > {weights[i+1][2]} (blok {weights[i+1][1]})")

    print(f"Weights increase over bloks: {'PASS' if weights_ok else 'FAIL'}")

else:
    print(f"FAILED: {result['error']}")
