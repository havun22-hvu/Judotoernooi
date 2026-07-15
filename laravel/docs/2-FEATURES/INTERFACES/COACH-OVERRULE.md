---
title: Coach check-in: overrule & implementatie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Coach check-in: overrule & implementatie

> Onderdeel van [Interfaces per rol](../INTERFACES.md).

#### Overrule door Hoofdjury (Organisator Portal)

**Probleem:** Coach vergeet uit te checken, nieuwe coach wil kaart overnemen.

**Oplossing:** Hoofdjury kan uitcheck forceren via de **Organisator Portal** (niet via dojo scanner PWA).

**Let op:** Deze functie is NIET zichtbaar in:
- Dojo Scanner (PWA voor vrijwilligers)
- Club Portal (voor budoscholen)

**Organisator Portal - Coach Kaarten beheer:**
```
┌─────────────────────────────────────────────────┐
│  COACH KAARTEN BEHEER                           │
├─────────────────────────────────────────────────┤
│  🔍 Zoek club of coach...                       │
├─────────────────────────────────────────────────┤
│  ⚠️ GEBLOKKEERDE OVERDRACHTEN (1)               │
│  ┌──────────────────────────────────────────┐   │
│  │ Judo Hoorn - Kaart 1                     │   │
│  │ Jan de Vries nog ingecheckt sinds 09:00  │   │
│  │ Nieuwe coach wacht op overdracht         │   │
│  │                                          │   │
│  │ [🔓 Forceer uitcheck]                    │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

**Na klik "Forceer uitcheck":**
- Bevestiging: "Weet u zeker dat u Jan de Vries wilt uitchecken?"
- Coach wordt automatisch uitgecheckt
- Wordt gelogd: "Geforceerd door hoofdjury om [tijd]"
- Kaart kan nu worden overgedragen door nieuwe coach

**Database logging:**
```sql
coach_checkins:
- actie: 'uit_geforceerd'
- geforceerd_door: 'hoofdjury'
```

#### Implementatie

**Routes:**
- `POST /coach-kaart/{qrCode}/checkin` - Check coach in
- `POST /coach-kaart/{qrCode}/checkout` - Check coach uit
- `POST /coach-kaart/{qrCode}/forceer-checkout` - Geforceerde uitcheck (hoofdjury pincode vereist)
- `GET /dojo/{toernooi}/overzicht` - Overzicht alle clubs + check-in status (JSON)
- `GET /dojo/{toernooi}/overzicht/{club}` - Detail voor 1 club (JSON)
- `GET /coach-kaart/{qrCode}/geschiedenis` - Alle coaches met foto's + in/uit tijden

**Model:** `CoachCheckin`
```php
- belongsTo CoachKaart
- belongsTo Toernooi
- scopeVandaag() // Filter op vandaag
- scopeVoorClub($clubId) // Filter op club
```

**Controller:** `DojoController`
```php
public function checkin(CoachKaart $coachKaart) {
    $coachKaart->update(['ingecheckt_op' => now()]);
    return back()->with('success', 'Coach ingecheckt');
}

public function checkout(CoachKaart $coachKaart) {
    $coachKaart->update(['ingecheckt_op' => null]);
    return back()->with('success', 'Coach uitgecheckt');
}
```

**Validatie in CoachKaartController@activeer (overdracht):**
```php
if ($toernooi->coach_incheck_actief && $coachKaart->ingecheckt_op) {
    // HARD BLOKKEREN - geen overdracht mogelijk
    // Huidige coach moet eerst uitchecken bij dojo scanner
    return back()->with('error', 'Overdracht niet mogelijk. Huidige coach moet eerst uitchecken bij de dojo scanner.');
}
```

**View logica (show.blade.php):**
```php
@if($toernooi->coach_incheck_actief && $coachKaart->ingecheckt_op)
    @if($isHuidigeCoach)
        // Toon: "Ga naar dojo scanner om uit te checken"
    @else
        // Toon: "Kaart nog in gebruik, vraag coach om uit te checken"
    @endif
@endif
```

---

