---
title: View-updates en migratie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# View-updates en migratie

> Onderdeel van [Mat Wedstrijd Selectie](../MAT-WEDSTRIJD-SELECTIE.md).

## View Updates

### Mat Interface (_content.blade.php)

```javascript
// Kleurbepaling
const isGroen = wedstrijd.id === this.matSelectie.actieve_wedstrijd_id;
const isGeel = wedstrijd.id === this.matSelectie.volgende_wedstrijd_id;
const isBlauw = wedstrijd.id === this.matSelectie.gereedmaken_wedstrijd_id;

// CSS classes
let kleurClass = '';
if (isGroen) kleurClass = 'bg-green-100 border-green-500';
else if (isGeel) kleurClass = 'bg-yellow-100 border-yellow-500';
else if (isBlauw) kleurClass = 'bg-blue-100 border-blue-500';
```

### Selectie logica

```javascript
toggleWedstrijd(wedstrijdId) {
    const isGroen = wedstrijdId === this.matSelectie.actieve_wedstrijd_id;
    const isGeel = wedstrijdId === this.matSelectie.volgende_wedstrijd_id;
    const isBlauw = wedstrijdId === this.matSelectie.gereedmaken_wedstrijd_id;

    if (isGroen) {
        // Deselecteer groen met bevestiging
        if (confirm('Wedstrijd stoppen?')) {
            this.deselecteerGroen();
        }
    } else if (isGeel) {
        // Deselecteer geel
        this.deselecteerGeel();
    } else if (isBlauw) {
        // Deselecteer blauw
        this.deselecteerBlauw();
    } else {
        // Selecteer nieuw
        this.selecteerWedstrijd(wedstrijdId);
    }
}

selecteerWedstrijd(wedstrijdId) {
    if (!this.matSelectie.actieve_wedstrijd_id) {
        // Geen groen → wordt groen
        this.matSelectie.actieve_wedstrijd_id = wedstrijdId;
    } else if (!this.matSelectie.volgende_wedstrijd_id) {
        // Wel groen, geen geel → wordt geel
        this.matSelectie.volgende_wedstrijd_id = wedstrijdId;
    } else if (!this.matSelectie.gereedmaken_wedstrijd_id) {
        // Wel groen + geel, geen blauw → wordt blauw
        this.matSelectie.gereedmaken_wedstrijd_id = wedstrijdId;
    } else {
        alert('Deselecteer eerst een wedstrijd');
        return;
    }
    this.saveMatSelectie();
}

deselecteerGroen() {
    // Doorschuiven: geel → groen, blauw → geel
    this.matSelectie.actieve_wedstrijd_id = this.matSelectie.volgende_wedstrijd_id;
    this.matSelectie.volgende_wedstrijd_id = this.matSelectie.gereedmaken_wedstrijd_id;
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}

deselecteerGeel() {
    // Doorschuiven: blauw → geel
    this.matSelectie.volgende_wedstrijd_id = this.matSelectie.gereedmaken_wedstrijd_id;
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}

deselecteerBlauw() {
    this.matSelectie.gereedmaken_wedstrijd_id = null;
    this.saveMatSelectie();
}
```

---

## Migratie

### Database migratie

```php
Schema::table('matten', function (Blueprint $table) {
    $table->foreignId('gereedmaken_wedstrijd_id')->nullable()->constrained('wedstrijden')->nullOnDelete();
});
```

---

