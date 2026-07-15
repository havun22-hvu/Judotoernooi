---
title: Server rol configuratie
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Server rol configuratie

> Onderdeel van [Redundantie & offline draaien](../REDUNDANTIE.md).

## 10. Server Rol Configuratie

Bij eerste start van de lokale server-modus moet de gebruiker expliciet kiezen welke rol dit apparaat heeft.

### Opstartscherm

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│   SERVERROL CONFIGUREREN                                    │
│                                                             │
│   Welke rol heeft deze laptop?                              │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ○ PRIMARY SERVER (Laptop A)                        │   │
│   │                                                     │   │
│   │    → Dit is de hoofdserver                          │   │
│   │    → Alle tablets/devices verbinden hiermee         │   │
│   │    → IP wordt: 192.168.1.100                        │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  ○ STANDBY SERVER (Laptop B)                        │   │
│   │                                                     │   │
│   │    → Dit is de backup server                        │   │
│   │    → Neemt automatisch over bij crash Primary       │   │
│   │    → IP wordt: 192.168.1.101                        │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
│   [ Bevestigen ]                                            │
│                                                             │
│   ⚠️  Let op: Kies elke rol maar op 1 laptop!              │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Configuratie Opslag

Na keuze wordt de rol opgeslagen in `config/local-server.php`:

```php
return [
    'role' => 'primary',  // of 'standby'
    'ip' => '192.168.1.100',
    'configured_at' => '2026-01-31 10:00:00',
    'device_name' => 'Laptop A - Jurytafel',
];
```

### Validatie

Bij elke start controleert het systeem:
1. Is er al een config? → Gebruik opgeslagen rol
2. Komt IP overeen? → Waarschuwing als IP veranderd is
3. Is Primary al online? → Standby detecteert dit automatisch

### Rol Wijzigen

Via het instellingen menu kan de rol gewijzigd worden:
- **Noodplan → Server Instellingen → Rol Wijzigen**
- Vereist herstart van de server

---

