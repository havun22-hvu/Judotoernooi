# Planning: Intropage vs PWA Live App

## Overzicht

Er is een duidelijke scheiding nodig tussen:
1. **Intropage** - Publieke website voor het toernooi (marketing/informatie)
2. **PWA Live App** - Real-time app voor op de wedstrijddag

## Huidige situatie (probleem)

```
Instellingen → Paginabuilder → Direct naar PWA app ❌
```

De paginabuilder verwijst nu direct naar de PWA app, maar die moet pas getoond worden NA scannen van QR-code op de intropage.

## Gewenste situatie

```
Instellingen → Paginabuilder → Intropage (publieke website)
                                    ↓
                              QR-code scannen op wedstrijddag
                                    ↓
                              PWA Live App
```

---

## 1. Intropage (Publieke Website)

### URL structuur
```
/toernooi/{slug}              → Intropage (standaard)
/toernooi/{slug}/live         → PWA Live App (via QR of link)
```

### Secties voor Intropage

| Sectie | Inhoud | Paginabuilder |
|--------|--------|---------------|
| **Hero** | Titel, datum, locatie, countdown | ✅ |
| **Over het toernooi** | Beschrijving, missie | ✅ |
| **Toernooiregels** | Reglement, categorieën, judopunten | ✅ |
| **Programma** | Tijdschema per blok | ✅ |
| **Impressie** | Foto's/video vorig toernooi | ✅ |
| **Inschrijvingen** | Aantal clubs, judoka's (live teller) | Automatisch |
| **Deelnemerslijst** | Ingeschreven judoka's per club | Automatisch |
| **Praktische info** | Locatie, parkeren, horeca | ✅ |
| **Contact** | Organisatie, vragen | ✅ |
| **QR-code sectie** | "Scan op de wedstrijddag" | ✅ |

### Automatische content (niet via paginabuilder)
- Aantal ingeschreven judoka's (live)
- Aantal clubs
- Deelnemerslijst per club
- Countdown naar toernooidatum

### Paginabuilder opties
```php
[
    'hero' => [
        'titel' => 'WestFries Open Judotoernooi 2025',
        'subtitel' => 'Het gezelligste judotoernooi van Noord-Holland',
        'datum' => '2025-03-15',
        'achtergrond_afbeelding' => 'uploads/hero.jpg',
    ],
    'secties' => [
        ['type' => 'tekst', 'titel' => 'Over het toernooi', 'inhoud' => '...'],
        ['type' => 'afbeelding_grid', 'titel' => 'Impressie 2024', 'afbeeldingen' => [...]],
        ['type' => 'programma', 'toon_tijdschema' => true],
        ['type' => 'deelnemers', 'toon_clubs' => true, 'toon_judokas' => false],
        ['type' => 'locatie', 'adres' => '...', 'maps_embed' => '...'],
        ['type' => 'qr_code', 'titel' => 'Op de wedstrijddag', 'tekst' => 'Scan voor live uitslagen'],
    ],
]
```

---

## 2. PWA Live App (Wedstrijddag)

### URL structuur
```
/toernooi/{slug}/live         → PWA hoofdpagina
/toernooi/{slug}/live/mat/{n} → Specifieke mat volgen
/toernooi/{slug}/live/judoka/{id} → Judoka volgen
```

### Functionaliteiten

| Functie | Beschrijving |
|---------|--------------|
| **Dashboard** | Overzicht alle matten, actieve poules |
| **Mat volgen** | Real-time wedstrijden per mat |
| **Favorieten** | Volg specifieke judoka's |
| **Notificaties** | "Je judoka is bijna aan de beurt" |
| **Uitslagen** | Alle resultaten per poule |
| **Zoeken** | Zoek judoka op naam/club |

### PWA Features
- Installeerbaar op homescreen
- Offline caching van statische content
- Push notificaties (opt-in)
- Service worker voor snelle updates

---

## 3. Implementatie Plan

### Fase 1: Routes en Controllers
- [ ] Nieuwe route: `/toernooi/{slug}` → IntropageController
- [ ] Route aanpassen: `/toernooi/{slug}/live` → bestaande PWA
- [ ] IntropageController met Blade view

### Fase 2: Intropage Blade Views
- [ ] `resources/views/intropage/show.blade.php`
- [ ] Componenten voor elke sectie type
- [ ] Responsive design (mobile-first)

### Fase 3: Paginabuilder Aanpassen
- [ ] Database veld `pagina_inhoud` op Toernooi model
- [ ] Admin interface voor secties beheren
- [ ] Preview functie

### Fase 4: Automatische Content
- [ ] Deelnemers teller component
- [ ] Club lijst component
- [ ] Countdown component

### Fase 5: QR-code Integratie
- [ ] QR-code genereren naar `/live` URL
- [ ] QR-code component voor intropage
- [ ] Printbare QR-codes voor op locatie

---

## 4. Database Wijzigingen

### Toernooi model uitbreiden
```php
// Nieuwe velden
'intropage_inhoud' => 'json',      // Paginabuilder content
'intropage_actief' => 'boolean',   // Intropage aan/uit
'pwa_actief' => 'boolean',         // PWA alleen actief op wedstrijddag?
'pwa_start_datum' => 'date',       // Wanneer PWA beschikbaar wordt
```

### Migration
```php
Schema::table('toernooien', function (Blueprint $table) {
    $table->json('intropage_inhoud')->nullable();
    $table->boolean('intropage_actief')->default(true);
    $table->boolean('pwa_actief')->default(true);
    $table->date('pwa_start_datum')->nullable();
});
```

---

## 5. URL Overzicht (Nieuw)

| URL | Pagina | Wanneer beschikbaar |
|-----|--------|---------------------|
| `/toernooi/{slug}` | Intropage | Altijd |
| `/toernooi/{slug}/inschrijven` | Inschrijfformulier | Tot deadline |
| `/toernooi/{slug}/deelnemers` | Deelnemerslijst | Altijd |
| `/toernooi/{slug}/live` | PWA Live App | Wedstrijddag (of altijd) |
| `/toernooi/{slug}/live/mat/{n}` | Mat volgen | Wedstrijddag |
| `/toernooi/{slug}/uitslagen` | Eindresultaten | Na toernooi |

---

## 6. Paginabuilder UI (Admin)

### Sectie types

```
┌─────────────────────────────────────────┐
│ INTROPAGE BUILDER                       │
├─────────────────────────────────────────┤
│ [+] Sectie toevoegen                    │
│                                         │
│ ┌─ Hero ─────────────────────────────┐  │
│ │ Titel: [________________]          │  │
│ │ Datum: [____-__-__]               │  │
│ │ Afbeelding: [Upload]              │  │
│ └────────────────────────────────────┘  │
│                                         │
│ ┌─ Tekst ────────────────────────────┐  │
│ │ Titel: [Over het toernooi]        │  │
│ │ Inhoud: [WYSIWYG editor]          │  │
│ └────────────────────────────────────┘  │
│                                         │
│ ┌─ Impressie (foto's) ───────────────┐  │
│ │ Titel: [Impressie 2024]           │  │
│ │ [Foto 1] [Foto 2] [Foto 3] [+]    │  │
│ └────────────────────────────────────┘  │
│                                         │
│ ┌─ Deelnemers (automatisch) ─────────┐  │
│ │ ☑ Toon aantal clubs               │  │
│ │ ☑ Toon aantal judoka's            │  │
│ │ ☐ Toon volledige lijst            │  │
│ └────────────────────────────────────┘  │
│                                         │
│ ┌─ QR-code ──────────────────────────┐  │
│ │ Tekst: [Scan voor live uitslagen] │  │
│ │ [PREVIEW QR]                      │  │
│ └────────────────────────────────────┘  │
│                                         │
│ [Opslaan] [Preview]                     │
└─────────────────────────────────────────┘
```

---

## 7. Prioriteit

1. **Hoog**: Routes scheiden (intropage vs PWA)
2. **Hoog**: Basis intropage met automatische content
3. **Middel**: Paginabuilder voor custom secties
4. **Laag**: Geavanceerde features (countdown, animaties)

---

## 8. Beslissingen (vastgesteld)

### 1. PWA beschikbaarheid ✅
**Besluit:** PWA altijd beschikbaar, maar met beperkingen:
- **Vooraf:** Algemene info, favorieten instellen, deelnemerslijst bekijken
- **Op wedstrijddag:** Live tab + definitieve poules worden pas getoond
- **Reden:** Poules kunnen wijzigen door mutaties (gewicht, afwezigheid)

### 2. Deelnemerslijst ✅
**Besluit:** Volledig publiek (alle namen zichtbaar)

### 3. Paginabuilder ✅
**Besluit:** Flexibel met sjabloon als startpunt
- Mooi standaard sjabloon als basis
- Organisator kan secties aanpassen, verwijderen, toevoegen
- Drag & drop voor volgorde wijzigen
- WYSIWYG editor voor tekst secties

---

## 9. PWA Tabs per Fase

| Tab | Vooraf | Wedstrijddag |
|-----|--------|--------------|
| **Home** | ✅ Toernooi info | ✅ Toernooi info |
| **Deelnemers** | ✅ Ingeschreven judoka's | ✅ Definitieve lijst |
| **Poules** | ⏳ "Nog niet bekend" | ✅ Definitieve indeling |
| **Live** | ⏳ "Nog niet gestart" | ✅ Real-time uitslagen |
| **Favorieten** | ✅ Judoka's selecteren | ✅ Met live updates |
| **Uitslagen** | ❌ Niet zichtbaar | ✅ Na afloop poules |

### Logica voor tab beschikbaarheid
```php
// Poules tab
if ($toernooi->poules_definitief) {
    // Toon definitieve poules
} else {
    // Toon melding "Poule-indeling wordt op de wedstrijddag bekend"
}

// Live tab
if ($toernooi->datum == today() && $toernooi->is_gestart) {
    // Toon live uitslagen
} else {
    // Toon melding "Live uitslagen beschikbaar op wedstrijddag"
}
```

---

## 10. Standaard Intropage Sjabloon

Bij aanmaken nieuw toernooi wordt dit sjabloon automatisch toegepast:

```
┌─────────────────────────────────────────────────────────┐
│                        HERO                              │
│  [Toernooi naam]                                        │
│  [Datum] | [Locatie]                                    │
│  [Countdown: nog X dagen]                               │
│                                                         │
│  [INSCHRIJVEN]  [MEER INFO]                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  OVER HET TOERNOOI                                      │
│  [Standaard tekst die organisator kan aanpassen]        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  PROGRAMMA                                              │
│  Blok 1: 09:00 - Mini's                                │
│  Blok 2: 10:30 - U11                                   │
│  etc.                                                   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  DEELNEMERS                          [Automatisch]      │
│  ┌────────┐  ┌────────┐                                │
│  │  42    │  │  156   │                                │
│  │ clubs  │  │judoka's│                                │
│  └────────┘  └────────┘                                │
│                                                         │
│  [Bekijk alle deelnemers →]                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  IMPRESSIE VORIG JAAR                                   │
│  [Foto] [Foto] [Foto] [Foto]                           │
│  [Optioneel: YouTube embed]                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  LOCATIE & PRAKTISCHE INFO                              │
│  [Google Maps embed]                                    │
│  Adres: ...                                            │
│  Parkeren: ...                                         │
│  Horeca: ...                                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  OP DE WEDSTRIJDDAG                                     │
│  Scan de QR-code voor live uitslagen                   │
│       ┌─────────┐                                      │
│       │ QR CODE │                                      │
│       └─────────┘                                      │
│  of ga naar: judotournament.org/live                   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  CONTACT                                                │
│  Organisatie: [Club naam]                              │
│  Email: [email]                                        │
│  Telefoon: [nummer]                                    │
└─────────────────────────────────────────────────────────┘
```

Organisator kan:
- Secties verwijderen (bijv. geen impressie)
- Volgorde wijzigen (drag & drop)
- Teksten aanpassen
- Eigen secties toevoegen
- **Thema kleur kiezen**

---

## 11. Thema & Kleuren

### Kleurkeuze voor organisator

De organisator kan een **primaire kleur** kiezen die door de hele intropage en PWA wordt gebruikt.

#### Voorgedefinieerde kleurthema's

| Thema | Primair | Accent | Voorbeeld gebruik |
|-------|---------|--------|-------------------|
| **Judo Blauw** | `#1d4ed8` | `#3b82f6` | Standaard, professioneel |
| **Energie Oranje** | `#ea580c` | `#f97316` | Dynamisch, sportief |
| **Natuur Groen** | `#15803d` | `#22c55e` | Fris, jeugdig |
| **Kracht Rood** | `#dc2626` | `#ef4444` | Sterk, opvallend |
| **Elegant Paars** | `#7c3aed` | `#a78bfa` | Modern, uniek |
| **Classic Zwart** | `#1f2937` | `#4b5563` | Strak, minimalistisch |

#### Custom kleur
Organisator kan ook eigen hex-kleur invoeren: `#______`

### Kleur toepassing

```
┌─────────────────────────────────────────────────────────┐
│  HERO                          [Primaire kleur]         │
│  ████████████████████████████████████████████████████  │
│                                                         │
│  Knoppen: [INSCHRIJVEN] ← Primaire kleur               │
│  Links: Accent kleur                                   │
│  Headers: Primaire kleur                               │
└─────────────────────────────────────────────────────────┘
```

### Database veld

```php
// Toernooi model
'thema_kleur' => 'string',  // Hex code: #1d4ed8
'thema_preset' => 'string', // Of preset naam: 'judo-blauw', 'energie-oranje'
```

### CSS Variables implementatie

```css
:root {
  --color-primary: #1d4ed8;
  --color-primary-light: #3b82f6;
  --color-primary-dark: #1e40af;
  --color-accent: #3b82f6;
}

/* Automatisch gegenereerd op basis van organisator keuze */
.hero { background: var(--color-primary); }
.btn-primary { background: var(--color-primary); }
.link { color: var(--color-accent); }
h2, h3 { color: var(--color-primary-dark); }
```

### Paginabuilder UI voor kleuren

```
┌─────────────────────────────────────────────────────────┐
│  THEMA INSTELLINGEN                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Kies een kleurthema:                                  │
│                                                         │
│  ○ [████] Judo Blauw (standaard)                       │
│  ○ [████] Energie Oranje                               │
│  ○ [████] Natuur Groen                                 │
│  ○ [████] Kracht Rood                                  │
│  ○ [████] Elegant Paars                                │
│  ○ [████] Classic Zwart                                │
│  ○ [____] Eigen kleur: #______                         │
│                                                         │
│  Preview:                                              │
│  ┌─────────────────────────────────────────────────┐   │
│  │  [Mini preview van hero met gekozen kleur]      │   │
│  │  [INSCHRIJVEN] [MEER INFO]                      │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 12. Logo & Branding

### Organisator uploads

| Element | Formaat | Gebruik |
|---------|---------|---------|
| **Logo** | PNG/SVG, transparant | Header, favicon |
| **Hero afbeelding** | JPG, 1920x600 | Achtergrond hero sectie |
| **Impressie foto's** | JPG, max 2MB | Foto galerij |

### Fallback

Als geen logo geupload:
- Toon toernooi naam als tekst
- Genereer simpel favicon met eerste letter + primaire kleur

---

## 13. Responsive Design

### Breakpoints

| Device | Breedte | Layout |
|--------|---------|--------|
| Mobile | < 640px | Enkele kolom, compact |
| Tablet | 640-1024px | 2 kolommen |
| Desktop | > 1024px | Volledige layout |

### Mobile-first prioriteiten

1. Hero met countdown - compact
2. Inschrijf knop - prominent
3. Deelnemers teller - zichtbaar
4. QR-code - groot en scanbaar
5. Contact - makkelijk bereikbaar
