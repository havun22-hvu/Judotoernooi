#!/usr/bin/env node
/**
 * Integrity Check Script — validates critical UI/code elements
 * Supports: must_contain (text) + must_contain_selector (HTML tag/attr)
 * Source: VP-05 audit improvement plan
 */
const fs = require('fs');
const path = require('path');

const integrityFile = path.join(process.cwd(), '.integrity.json');

if (!fs.existsSync(integrityFile)) {
    console.log('No .integrity.json found — skipping');
    process.exit(0);
}

const config = JSON.parse(fs.readFileSync(integrityFile, 'utf8'));
let failures = 0;
let passed = 0;

for (const check of config.checks) {
    const filePath = path.join(process.cwd(), check.file);

    if (!fs.existsSync(filePath)) {
        console.error(`FAIL: ${check.file} — FILE NOT FOUND (${check.description})`);
        failures++;
        continue;
    }

    const content = fs.readFileSync(filePath, 'utf8');
    let checkFailed = false;

    // Text-based checks (must_contain)
    if (check.must_contain) {
        const missing = check.must_contain.filter(term => !content.includes(term));
        if (missing.length > 0) {
            console.error(`FAIL: ${check.file} — Missing text: ${missing.join(', ')}`);
            checkFailed = true;
        }
    }

    // Selector-based checks (must_contain_selector)
    if (check.must_contain_selector) {
        for (const sel of check.must_contain_selector) {
            // Build a simple regex to match <tag ... attr="value" ...>
            let pattern;
            if (sel.value) {
                pattern = new RegExp(
                    `<${sel.tag}[^>]*${sel.attr}\\s*=\\s*["']${sel.value}["'][^>]*>`,
                    'i'
                );
            } else if (sel.contains) {
                pattern = new RegExp(
                    `<${sel.tag}[^>]*${sel.attr}\\s*=\\s*["'][^"']*${sel.contains}[^"']*["'][^>]*>`,
                    'i'
                );
            }

            if (pattern && !pattern.test(content)) {
                console.error(`FAIL: ${check.file} — Missing selector: <${sel.tag} ${sel.attr}="${sel.value || sel.contains}">`);
                checkFailed = true;
            }
        }
    }

    if (checkFailed) {
        failures++;
    } else {
        console.log(`OK: ${check.file} — ${check.description}`);
        passed++;
    }
}

console.log(`\n${passed} passed, ${failures} failed`);
process.exit(failures > 0 ? 1 : 0);
