<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Chat kanalen voor communicatie tussen hoofdjury en PWA's
|
*/

// Hoofdjury kanaal - ontvangt alle berichten van PWA's
Broadcast::channel('hoofdjury.{toernooiId}', function ($user, $toernooiId) {
    // Alleen ingelogde organisatoren/admins
    return $user && $user->isOrganisator($toernooiId);
});

// Mat kanaal - per mat
Broadcast::channel('mat.{toernooiId}.{matId}', function ($user, $toernooiId, $matId) {
    // PWA's gebruiken sessie-gebaseerde auth, niet user model
    // Autorisatie gebeurt via middleware
    return true;
});

// Weging kanaal
Broadcast::channel('weging.{toernooiId}', function ($user, $toernooiId) {
    return true;
});

// Spreker kanaal
Broadcast::channel('spreker.{toernooiId}', function ($user, $toernooiId) {
    return true;
});

// Dojo kanaal
Broadcast::channel('dojo.{toernooiId}', function ($user, $toernooiId) {
    return true;
});

// Broadcast naar alle PWA's van een toernooi
Broadcast::channel('toernooi.{toernooiId}', function ($user, $toernooiId) {
    return true;
});
