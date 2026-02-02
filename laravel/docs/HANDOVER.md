# Handover - 2 februari 2026

## Vandaag gedaan: Noodplan pagina uitgebreid

### Nieuwe secties op tab 3 (Noodplan/Instellingen):

1. **Lokaal netwerk vs Internet uitleg** - met live status metingen
   - Lokaal netwerk: meet latency naar lokale server (als IP ingesteld)
   - Internet: meet latency naar cloud (/ping endpoint)
   - Automatische check elke 30 sec

2. **Netwerk modus keuze** - MET/ZONDER eigen router (Deco)
   - Scenario tabellen per modus
   - Wat te doen bij storingen

3. **IP-adressen configureren** - 3 velden met copy knoppen
   - Primaire laptop IP
   - Standby laptop IP
   - Hotspot IP

4. **Voorbereiding (avond ervoor)** - download knoppen
   - Download noodbackup (JSON)
   - Download poule-indeling (Excel)

5. **Bij storing: overstappen naar lokale server** - vereenvoudigde stappen
   - Geen technische commando's meer
   - Kopieerbare URL voor tablets

---

## Morgen verder: IP configuratie

### Wat moet gebeuren:
- **Primaire server IP** - organisator moet laptop IP kunnen invullen
- **Standby server IP** - tweede laptop als backup
- De IP's worden al opgeslagen in database (`local_server_primary_ip`, `local_server_standby_ip`, `hotspot_ip`)
- UI voor invullen staat er al, moet getest worden

### Database velden (al aangemaakt):
- `toernooien.local_server_primary_ip` (varchar 45)
- `toernooien.local_server_standby_ip` (varchar 45)
- `toernooien.hotspot_ip` (varchar 45)
- `toernooien.heeft_eigen_router` (boolean)
- `toernooien.eigen_router_ssid` (varchar 100)
- `toernooien.hotspot_ssid` (varchar 100)

### Route voor opslaan:
- `PUT /toernooi.local-server-ips` → `ToernooiController@updateLocalServerIps`

---

## Opruimen (niet urgent)

De `/local-server/*` routes en `LocalSyncController` methods zijn nog aanwezig maar views zijn verwijderd. Kan later opgeruimd worden als niet meer nodig.

---

## Test URL
https://staging.judotournament.org/cees-veen/toernooi/wimpeltoernooi-2026-februari/edit → tab 3 (Noodplan)
