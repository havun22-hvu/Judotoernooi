# Smallwork — kleine fixes/taken JudoToernooi

> Kleine bugs/typo's die geen /arch of plan nodig hebben. Log + fix + klaar.

## 17-07-2026
- **Poules: waarschuwing "X judoka's niet gecategoriseerd" stond er twee keer** (`f414be79`) —
  twee losse blokken in `poule/index.blade.php`, elk met een eigen `countNietGecategoriseerd()`,
  dus ook twee keer dezelfde telling per pageload. De bovenste gehouden: die staat bij de
  overlap-waarschuwing en verdwijnt tijdens lockdown, wat de onderste negeerde. Wel de betere
  tekst en de deeplink (`#categorieen`) van de onderste overgenomen. **Niet weer splitsen.**
- **Upgrade-pagina beloofde "€10 per 50 judoka's"** — klopte al niet sinds de trede 401-500
  (100 breed, +€20). Nu "€0,20 per judoka", wat de werkelijke regel is voor élke trede.

## 15-07-2026
- **Device Toegangen — mat-rij: LCD/Mat omgedraaid + zinloze LCD-QR** —
  `toernooi/partials/device-toegangen.blade.php`. Links stond de code-kolom als LCD-boven /
  Mat-onder, rechts de knop-kolom als Mat-boven ("Interface") / LCD-onder: precies omgekeerd,
  dus je las de regel kriskras. Links omgedraaid naar mat-boven, en "Interface" heet nu overal
  "Mat interface". De QR bij LCD is weg: een TV heeft geen camera, koppelen gaat via de
  4-cijferige code of de korte URL. QR blijft bij Mat (scorebord-app/tablet scant die wél).
  De LCD-code toont nu alleen bij de mat-rol — bij hoofdjury/weging had die geen betekenis.
  Dode code opgeruimd (`toggleQrLcd`, `qrIsLcd`, `labelLcd`, `captionLcd`). Doc:
  `docs/2-FEATURES/SCOREBORD-APP.md` → "Mat-rij in Instellingen → Device Toegangen".

## 28-06-2026
- **QR-codes op coach-weegkaarten zwart i.p.v. blauw** (`625f3f61`) — `coach/weegkaarten.blade.php`
  zette `colorDark: '#1d4ed8'` (blue-700) op de kleine én grote QR. Naar `#000000` voor
  scancontrast. De overige QR's (weegkaart/show, noodplan-weeg/coachkaarten, coach-kaart/show)
  zetten geen kleur → al zwart (default). Runtime qrcodejs-optie, geen Tailwind-build nodig.

## 21-06-2026
- **Tab springt naar Organisatie bij open/intern-toernooi keuze** — bij wijzigen van
  `toernooi_type` herlaadde `toernooi/edit.blade.php` de pagina met een kale
  `window.location.reload()`, die een eventuele `?tab=organisatie` uit de URL behield (veel
  instellingen-redirects landen daarop). Gevolg: je sprong naar de Organisatie-tab i.p.v. op
  de Toernooi-tab te blijven waar de keuze staat. Fix: reload nu expliciet naar `?tab=toernooi`.
- **Huidige tab bewaren bij opslaan (auto-save + Opslaan-knop)** — vervolg op bovenstaande.
  Na de Opslaan-knop redirecte de controller altijd naar de Toernooi-tab (geen tab-param).
  Fix: hidden input `active_tab` (`:value="activeTab"`) in het form; `ToernooiController@update`
  redirect nu met die tab (gevalideerd tegen toernooi/organisatie/noodplan/admin). De
  toernooi_type-reload leest die hidden input → blijft op de huidige tab. Auto-save zelf is
  AJAX (geen navigatie) → tab bleef al staan. 67 ToernooiController-tests groen.
- **Eigen preset-categorieën opslaan faalde (CSP)** — `createCategorieElement` bouwde de
  categorie-rij met `innerHTML` mét inline `onchange="..."` (9×) en inline
  `style="display:none"` → onder strikte CSP geblokkeerd → categorieën renderden niet →
  opslaan lukte niet. Fix: inline attributen weg; één gedelegeerde `change`-listener op het
  rij-element (CSP-safe), begin-zichtbaarheid GS-duur via CSSOM (`style.display`). De toggle-
  functies waren al CSSOM/classList. Blade compileert; durabele oplossing.
- **Volledige CSP-sweep van toernooi/edit.blade.php** — naast de categorie-rijen bleken nog
  meer inline handlers geblokkeerd: preset-modal-knoppen (savePreset/saveNewPreset/etc., via
  innerHTML → opslaan preset faalde), Opslaan-knop, open/intern-radio's, kopieer-URL-knop,
  Mollie-portaal-select, wachtwoord-toggle. Alles gemigreerd: preset-modal + radio's + copy +
  mollie + password → `data-action` + `window.cspActions`-registratie; Opslaan-knop → native
  `type=submit form=toernooi-form`. **Resultaat: 0 inline `on*=` en 0 inline `style=` in het
  hele bestand.** De aangeroepen functies waren al globaal. Blade compileert.
