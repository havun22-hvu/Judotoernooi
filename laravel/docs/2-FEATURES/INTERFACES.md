# Interfaces - PWA & Devices

> **Workflow info:** Zie `GEBRUIKERSHANDLEIDING.md` voor voorbereiding vs wedstrijddag

## PWA Apps per Rol

| Interface | PWA Naam | Device | Manifest |
|-----------|----------|--------|----------|
| **Dojo Scanner** | Dojo Scanner | 📱 Smartphone | manifest-dojo.json |
| **Weging** | Weging | 📱 Smartphone / Tablet | manifest-weging.json |
| **Mat** | Mat Interface | 💻 PC / Laptop / Tablet | manifest-mat.json |
| **Spreker** | Spreker | 📋 iPad / Tablet | manifest-spreker.json |

## Installatie

1. Open de interface pagina in browser
2. Klik tandwiel (⚙️) rechtsboven → Instellingen
3. Klik "Installeer [App Naam]"
4. PWA opent direct deze pagina (niet homepage)

## Versie & Updates

- Versie in `config/toernooi.php` → `version`
- Service Worker in `public/sw.js` → `VERSION`
- **Beide verhogen bij release!**
- Forceer Update knop in instellingen modal
