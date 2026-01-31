# Handover: 31 januari 2026

## Wat is gedaan

### Device-bound PWA fixes
- Mat, spreker, dojo interfaces werken nu op iPad/tablet
- Nieuwe device-bound API routes toegevoegd die `device.binding` middleware gebruiken
- Oplost: "missing parameters" errors en "doctype is not valid json" errors

### Best of three wedstrijdschema fix (GROOT)
Meerdere bugs opgelost die ervoor zorgden dat "best of three" bij 2 judoka's niet werkte:

1. **Form saving** - Alpine.js `:value` binding werkte niet → `x-ref` + `x-watch`
2. **Service caching** - Toernooi relatie was gecached → `->toernooi()->first()`
3. **JSON string keys** - "2" vs 2 mismatch → check beide keys
4. **Custom schema override** - Formulier updatet nu automatisch `wedstrijd_schemas[2]`
5. **View count** - Poule-card toont nu echte wedstrijd count

### Database fix
- `positie => 999` was te groot voor `tinyint` (max 255)
- Nu: berekent echte volgende positie

## Openstaande items

- [ ] Chat widget gebruikt admin routes - zal falen op device-bound interfaces (niet kritiek)
- [ ] Poule #2 (Mini's 20-21kg) heeft 5 judokas maar 0 wedstrijden (niet doorgestuurd)

## Belangrijke context voor volgende keer

### Best of three flow
1. Gebruiker selecteert "Best of 3" in toernooi edit
2. Controller update `best_of_three_bij_2 = true` EN `wedstrijd_schemas[2] = [[1,2],[2,1],[1,2]]`
3. Bij doorsturen: `WedstrijdSchemaService` leest custom schema en maakt 3 wedstrijden
4. Views tonen echte wedstrijd count, niet formule

### Device-bound routes
- Admin routes: `/organisator/toernooi/mat/...` - vereisen `auth:organisator`
- Device routes: `/organisator/toernooi/toegang/mat/...` - gebruiken `device.binding` middleware
- Views checken `isset($toegang)` om juiste URL te gebruiken

## Git status
- Laatste commit: `99123a4` - fix: Use proper positie value instead of 999
- Alle omgevingen (local, staging, production) zijn gesynchroniseerd

## Bekende issues/bugs
- Geen kritieke bugs bekend
