# Smallwork — kleine fixes/taken JudoToernooi

> Kleine bugs/typo's die geen /arch of plan nodig hebben. Log + fix + klaar.

## 21-06-2026
- **Tab springt naar Organisatie bij open/intern-toernooi keuze** — bij wijzigen van
  `toernooi_type` herlaadde `toernooi/edit.blade.php` de pagina met een kale
  `window.location.reload()`, die een eventuele `?tab=organisatie` uit de URL behield (veel
  instellingen-redirects landen daarop). Gevolg: je sprong naar de Organisatie-tab i.p.v. op
  de Toernooi-tab te blijven waar de keuze staat. Fix: reload nu expliciet naar `?tab=toernooi`.
