#!/usr/bin/env python3
"""
OR-Tools Constraint Solver for Blok/Mat distribution in Judo Tournament.

KEY INSIGHT: We distribute CATEGORIES (leeftijd+gewicht combinations), not individual poules!
Each category contains multiple poules that must ALL go to the same blok.

Constraints:
1. Each category (leeftijd+gewicht) goes to exactly 1 blok
2. Per leeftijdsklasse, weights must increase over blocks (lighter weights in earlier blocks)
3. Balance total matches across blocks

Input: JSON via stdin
Output: JSON via stdout
"""

import json
import sys
import re
from typing import Dict, List, Tuple
from ortools.sat.python import cp_model


def parse_weight(gewichtsklasse: str) -> float:
    """
    Parse weight class to numeric value.
    -50 = up to 50kg, +50 = over 50kg
    """
    match = re.search(r'([+-]?)(\d+)', gewichtsklasse)
    if match:
        sign = match.group(1)
        num = int(match.group(2))
        # +XX should sort after -XX (e.g., +50 > -50)
        return num + 1000 if sign == '+' else num
    return 999


def solve_blok_mat_distribution(data: Dict) -> Dict:
    """
    Solve the block/mat distribution problem using CP-SAT.

    The key is to group poules by category (leeftijd+gewicht) first,
    then distribute categories (not individual poules) over blocks.
    """
    blokken = data['blokken']
    matten = data['matten']
    poules = data['poules']

    if not blokken or not matten or not poules:
        return {'success': False, 'error': 'Missing blokken, matten, or poules'}

    num_blokken = len(blokken)
    num_matten = len(matten)

    # STEP 1: Group poules into categories (leeftijd+gewicht)
    # Each category = all poules with same leeftijdsklasse + gewichtsklasse
    categories = {}  # (leeftijd, gewicht) -> {'poule_ids': [...], 'total_matches': X}
    for poule in poules:
        key = (poule['leeftijdsklasse'], poule['gewichtsklasse'])
        if key not in categories:
            categories[key] = {'poule_ids': [], 'total_matches': 0}
        categories[key]['poule_ids'].append(poule['id'])
        categories[key]['total_matches'] += poule['aantal_wedstrijden']

    cat_list = list(categories.keys())
    num_categories = len(cat_list)

    # Create model
    model = cp_model.CpModel()

    # Decision variables: x[c][b] = 1 if category c is assigned to blok b
    x = {}
    for c in range(num_categories):
        for b in range(num_blokken):
            x[c, b] = model.NewBoolVar(f'x_{c}_{b}')

    # Constraint 1: Each category is assigned to exactly one blok
    for c in range(num_categories):
        model.AddExactlyOne(x[c, b] for b in range(num_blokken))

    # Group categories by leeftijdsklasse for weight ordering
    leeftijd_categories = {}  # leeftijd -> [(weight_numeric, category_index)]
    for c, (leeftijd, gewicht) in enumerate(cat_list):
        if leeftijd not in leeftijd_categories:
            leeftijd_categories[leeftijd] = []
        leeftijd_categories[leeftijd].append((parse_weight(gewicht), c, gewicht))

    # Sort by weight within each leeftijdsklasse
    for leeftijd in leeftijd_categories:
        leeftijd_categories[leeftijd].sort(key=lambda x: x[0])

    # Constraint 2: Per leeftijdsklasse, lighter weights must be in earlier or same blok
    # Create blok_num variable for each category
    category_blok = {}
    for c in range(num_categories):
        category_blok[c] = model.NewIntVar(0, num_blokken - 1, f'blok_{c}')
        model.Add(category_blok[c] == sum(b * x[c, b] for b in range(num_blokken)))

    # Add ordering constraint: lighter weight category blok <= heavier weight category blok
    # Also track gaps between consecutive weights for minimization
    gaps = []
    for leeftijd, weight_categories in leeftijd_categories.items():
        for i in range(len(weight_categories) - 1):
            _, c_light, _ = weight_categories[i]
            _, c_heavy, _ = weight_categories[i + 1]
            # Lighter weight blok <= heavier weight blok
            model.Add(category_blok[c_light] <= category_blok[c_heavy])

            # Track the gap between consecutive weights
            gap = model.NewIntVar(0, num_blokken - 1, f'gap_{leeftijd}_{i}')
            model.Add(gap == category_blok[c_heavy] - category_blok[c_light])
            gaps.append(gap)

    # Sum of all gaps (we want to minimize this for better continuity)
    total_gaps = model.NewIntVar(0, num_blokken * len(gaps) if gaps else 0, 'total_gaps')
    if gaps:
        model.Add(total_gaps == sum(gaps))

    # Constraint 3: Balance matches across blocks
    # Track total matches per blok
    total_matches = sum(categories[k]['total_matches'] for k in cat_list)
    avg_per_blok = total_matches // num_blokken

    matches_per_blok = {}
    for b in range(num_blokken):
        matches_per_blok[b] = model.NewIntVar(0, total_matches, f'matches_blok_{b}')
        model.Add(matches_per_blok[b] == sum(
            categories[cat_list[c]]['total_matches'] * x[c, b]
            for c in range(num_categories)
        ))

    # Minimize maximum deviation from average per blok
    max_deviation = model.NewIntVar(0, total_matches, 'max_deviation')
    for b in range(num_blokken):
        diff_pos = model.NewIntVar(0, total_matches, f'diff_pos_{b}')
        diff_neg = model.NewIntVar(0, total_matches, f'diff_neg_{b}')
        model.Add(matches_per_blok[b] - avg_per_blok == diff_pos - diff_neg)
        model.Add(diff_pos <= max_deviation)
        model.Add(diff_neg <= max_deviation)

    # Multi-objective: minimize deviation (most important) AND gaps (secondary)
    # Higher weight on deviation ensures better balance across blocks
    # Lower weight on gaps allows more spreading of categories
    model.Minimize(100 * max_deviation + total_gaps)

    # Solve
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 60.0
    status = solver.Solve(model)

    if status == cp_model.OPTIMAL or status == cp_model.FEASIBLE:
        # Build assignments: for each poule, assign the blok of its category
        # Mat assignment is left for later (set to None)
        assignments = []

        for c in range(num_categories):
            # Find which blok this category is assigned to
            assigned_blok = None
            for b in range(num_blokken):
                if solver.Value(x[c, b]) == 1:
                    assigned_blok = b
                    break

            cat_key = cat_list[c]
            poule_ids = categories[cat_key]['poule_ids']

            # All poules in this category get the same blok (no mat assignment yet)
            for poule_id in poule_ids:
                assignments.append({
                    'poule_id': poule_id,
                    'blok_id': blokken[assigned_blok]['id'],
                    'mat_id': None  # Mat wordt later bepaald
                })

        # Calculate statistics
        blok_matches = {}
        for b in range(num_blokken):
            blok_matches[blokken[b]['id']] = solver.Value(matches_per_blok[b])

        stats = {
            'status': 'optimal' if status == cp_model.OPTIMAL else 'feasible',
            'max_deviation': solver.Value(max_deviation),
            'total_gaps': solver.Value(total_gaps),
            'avg_per_blok': avg_per_blok,
            'num_categories': num_categories,
            'blok_matches': blok_matches,
        }

        return {
            'success': True,
            'assignments': assignments,
            'statistics': stats
        }
    else:
        status_names = {
            cp_model.INFEASIBLE: 'infeasible',
            cp_model.MODEL_INVALID: 'model_invalid',
            cp_model.UNKNOWN: 'unknown'
        }
        return {
            'success': False,
            'error': f'Solver failed: {status_names.get(status, status)}'
        }


def main():
    try:
        # Read JSON from stdin
        input_data = json.loads(sys.stdin.read())

        # Solve
        result = solve_blok_mat_distribution(input_data)

        # Output JSON
        print(json.dumps(result, indent=2))

    except Exception as e:
        import traceback
        print(json.dumps({
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()
