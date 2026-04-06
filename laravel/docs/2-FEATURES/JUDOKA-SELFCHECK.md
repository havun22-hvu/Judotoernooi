# Judoka Self-Check via Weegkaart

> **Status:** Ontwerp (nog niet geïmplementeerd)
> **Doel:** Judoka's zelf gewicht en geboortejaar laten verifiëren/corrigeren via de weegkaart PWA

## Probleem

Coaches vullen gewicht en geboortedatum vaak slordig in bij inschrijving. Dit leidt tot:
- Verkeerde gewichtsklasse-indeling
- Verkeerde leeftijdscategorie
- Discussie bij de weging op toernooilag
- Extra werk voor de organisator

## Oplossing

De weegkaart (`/weegkaart/{qr_code}`) wordt uitgebreid van read-only naar een self-check formulier. De judoka kan zelf gewicht en geboortejaar wijzigen vóór de gewichtsmutatie deadline.

## Flow

```
Coach schrijft judoka in (+ betaling)
    ↓
Judoka krijgt weegkaart QR (via coach/WhatsApp)
    ↓
Judoka opent weegkaart → ziet vooringevulde data
    ↓
Judoka wijzigt gewicht + geboortejaar indien nodig
    ↓
    ├─ Instelling "Direct" → data gaat naar judoka record
    │                         (judoka.gewicht + judoka.geboortejaar)
    │
    └─ Instelling "Via coach" → judoka.updated_at wijzigt
                                 → coach ziet "niet gesynced"
                                 → coach reviewt + klikt Sync
    ↓
Deadline gewichtsmutatie verstrijkt
    ↓
Weegkaart is actief (als gewicht + geboortejaar ingevuld)
    ↓
Toernooilag: weging via admin interface
```

## Voorwaarde: Coach Portal moet actief zijn

De self-check feature werkt **alleen** als de organisator gebruik maakt van de coach portal.

| Coach portal actief? | Weegkaart | Self-check |
|----------------------|-----------|------------|
| **Ja** | Bewerkbaar (vóór deadline) | ✓ Judoka kan wijzigen |
| **Nee** | Read-only (altijd) | ✗ Organisator regelt zelf inschrijvingen |

Als `portaal_modus` op `uit` staat (of coach portal niet ingeschakeld), dan:
- Weegkaart blijft puur informatief (huidige gedrag)
- Gewichtsmutatie deadline en bestemming-instelling zijn niet relevant
- Instellingen worden verborgen in de UI

## Instelling organisator

Nieuwe instelling bij **Toernooi → Organisatie tab → Coach Portal instellingen** (alleen zichtbaar als coach portal actief):

| Veld | Type | Opties |
|------|------|--------|
| `gewichtsmutatie_bestemming` | enum | `direct` / `via_coach` |

| Optie | Gedrag |
|-------|--------|
| **Direct** | Judoka wijzigt → `judoka.gewicht` + `judoka.geboortejaar` direct bijgewerkt |
| **Via coach** | Judoka wijzigt → `updated_at` verandert → coach moet opnieuw syncen (`isGewijzigdNaSync()`) |

**Bestaand sync-mechanisme werkt voor "via coach":**
- `isGewijzigdNaSync()` checkt `updated_at > synced_at`
- Coach ziet in portal welke judoka's gewijzigd zijn na laatste sync (al gemarkeerd)
- Bij gewijzigde judoka: toon **diff** van wat er veranderd is (bijv. "gewicht: 32 → 35 kg")
- Coach klikt "Sync" → `synced_at = now()`

## Nieuwe deadline: Gewichtsmutatie

Aparte deadline naast de bestaande inschrijving deadline.

### Database

Nieuw veld op `toernooien` tabel:

| Veld | Type | Beschrijving |
|------|------|--------------|
| `gewichtsmutatie_deadline` | date, nullable | Tot wanneer gewicht/geboortejaar gewijzigd mag worden |
| `gewichtsmutatie_bestemming` | string, default `via_coach` | `direct` of `via_coach` |

### Toernooi Instellingen UI

In het "Inschrijving" blok (naast bestaande deadline):

```
Inschrijving Deadline    Gewichtsmutatie Deadline    Maximum Aantal Deelnemers
[10-04-2026]             [17-04-2026]                [200]
```

### Validatie bij opslaan

Gewichtsmutatie deadline moet **op of na** de inschrijving deadline liggen. Toon foutmelding als organisator een eerdere datum invult:

> "Gewichtsmutatie deadline kan niet vóór de inschrijving deadline liggen"

### Logica

| Situatie | Inschrijven | Gewicht/geboortejaar wijzigen |
|----------|-------------|-------------------------------|
| Voor inschrijving deadline | ✓ | ✓ |
| Na inschrijving deadline, voor gewichtsmutatie deadline | ✗ | ✓ |
| Na gewichtsmutatie deadline | ✗ | ✗ |
| Geen gewichtsmutatie deadline ingesteld | ✗ | Volgt inschrijving deadline |

## Weegkaart PWA wijzigingen

### Huidige situatie
- Route: `/weegkaart/{qr_code}` → `WeegkaartController@show`
- Read-only: toont naam, club, categorie, gewichtsklasse, blok, mat

### Nieuwe situatie

**Vóór gewichtsmutatie deadline:**
- Weegkaart toont bewerkbaar formulier voor gewicht + geboortejaar
- Andere velden (naam, club, geslacht, band) blijven read-only
- Submit knop: "Gegevens bevestigen"
- Na bevestiging: vinkje/status "Bevestigd op [datum]"

**Na gewichtsmutatie deadline:**
- Weegkaart is read-only (geen formulier meer)
- Als gewicht + geboortejaar ingevuld → weegkaart is actief (groene status)
- Als gewicht OF geboortejaar ontbreekt → **melding in PWA**: "Gegevens incompleet — neem contact op met je coach"

### Nieuw veld judoka

| Veld | Type | Beschrijving |
|------|------|--------------|
| `selfcheck_at` | timestamp, nullable | Wanneer judoka zelf gegevens bevestigd/gewijzigd heeft |

Dit veld wordt gezet bij elke submit via de weegkaart. Hiermee kan de organisator zien:
- Wie heeft zelf bevestigd
- Wie heeft nog niet gereageerd

## Gewichtsklasse herberekening

Bij wijziging van gewicht of geboortejaar via weegkaart:

1. `gewicht` → herbereken `gewichtsklasse` via `toernooi.bepaalGewichtsklasse()`
2. `geboortejaar` → herbereken `leeftijdsklasse` via `CategorieClassifier::classificeer()`
3. Update alle classificatie-velden: `leeftijdsklasse`, `categorie_key`, `sort_categorie`

**Let op:** Dit is dezelfde logica als `CoachPortalController::updateJudokaCode()`.

### Herclassificatie melding aan organisator

Als een geboortejaar-wijziging leidt tot een **andere leeftijdscategorie** en de judoka zit al in een poule:
- Melding in de judokalijst: vlaggetje/badge "Categorie gewijzigd na self-check"
- Organisator moet handmatig poule-herindeling doen
- Bij "via coach" optie: coach ziet dit ook als onderdeel van de sync-diff

## Betrokken bestanden

| Wat | Bestand |
|-----|---------|
| Weegkaart controller | `app/Http/Controllers/WeegkaartController.php` |
| Weegkaart view | `resources/views/pages/weegkaart/show.blade.php` |
| Coach portal controller | `app/Http/Controllers/CoachPortalController.php` |
| Toernooi model | `app/Models/Toernooi.php` |
| Judoka model | `app/Models/Judoka.php` |
| Toernooi instellingen view | `resources/views/pages/toernooi/organisatie.blade.php` (of vergelijkbaar) |
| Migratie | Nieuw: `gewichtsmutatie_deadline` + `gewichtsmutatie_bestemming` op toernooien |
| Migratie | Nieuw: `selfcheck_at` op judokas |

## Validatie

| Veld | Regels |
|------|--------|
| `gewicht` | numeric, min:10, max:200 |
| `geboortejaar` | integer, min:1950, max:huidig jaar |

## Security

- Weegkaart URL bevat UUID → niet te raden
- Geen authenticatie nodig (publieke route, zoals nu)
- Rate limiting op POST endpoint (max 10 requests/minuut per IP)
- Na deadline: POST endpoint geeft 403

## Toekomstige uitbreidingen (NIET in v1)

- Push notificatie aan coach bij wijziging
- Herinnering aan judoka als deadline nadert en nog niet bevestigd
- Foto-upload voor judoka pas
