# Smallwork — kleine fixes/taken JudoToernooi

> Kleine bugs/typo's die geen /arch of plan nodig hebben. Log + fix + klaar.

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
