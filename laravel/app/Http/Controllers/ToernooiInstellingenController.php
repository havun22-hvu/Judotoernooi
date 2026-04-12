<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ToernooiInstellingenController extends Controller
{
    public function updateWachtwoorden(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $rollen = ['admin', 'jury', 'weging', 'mat', 'spreker'];
        $updated = [];

        foreach ($rollen as $rol) {
            $wachtwoord = $request->input("wachtwoord_{$rol}");
            if ($wachtwoord && strlen($wachtwoord) > 0) {
                $toernooi->setWachtwoord($rol, $wachtwoord);
                $updated[] = ucfirst($rol);
            }
        }

        if (empty($updated)) {
            return redirect()
                ->route('toernooi.edit', $toernooi->routeParams())
                ->with('info', 'Geen wachtwoorden gewijzigd');
        }

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParams())
            ->with('success', 'Wachtwoorden bijgewerkt voor: ' . implode(', ', $updated));
    }

    public function updateBloktijden(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $bloktijden = $request->input('blokken', []);

        foreach ($bloktijden as $blokId => $tijden) {
            $blok = $toernooi->blokken()->find($blokId);
            if ($blok) {
                $blok->update([
                    'weging_start' => $tijden['weging_start'] ?: null,
                    'weging_einde' => $tijden['weging_einde'] ?: null,
                    'starttijd' => $tijden['starttijd'] ?: null,
                ]);
            }
        }

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Bloktijden bijgewerkt');
    }

    public function updateBetalingInstellingen(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'betaling_actief' => 'boolean',
            'inschrijfgeld' => 'nullable|numeric|min:0|max:999.99',
            'payment_provider' => 'nullable|in:mollie,stripe',
        ]);

        $toernooi->update([
            'betaling_actief' => $validated['betaling_actief'] ?? false,
            'inschrijfgeld' => $validated['inschrijfgeld'] ?? null,
            'payment_provider' => $validated['payment_provider'] ?? 'mollie',
        ]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Betalingsinstellingen bijgewerkt');
    }

    public function updatePortaalInstellingen(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'portaal_modus' => 'required|in:uit,mutaties,volledig',
            'weegkaarten_publiek' => 'boolean',
        ]);

        $toernooi->update([
            'portaal_modus' => $validated['portaal_modus'],
            'weegkaarten_publiek' => $request->boolean('weegkaarten_publiek'),
        ]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', 'Portaalinstellingen bijgewerkt');
    }

    /**
     * Update local server and network settings
     */
    public function updateLocalServerIps(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'local_server_primary_ip' => 'nullable|ip',
            'local_server_standby_ip' => 'nullable|ip',
            'heeft_eigen_router' => 'boolean',
            'eigen_router_ssid' => 'nullable|string|max:100',
            'eigen_router_wachtwoord' => 'nullable|string|max:100',
            'hotspot_ssid' => 'nullable|string|max:100',
            'hotspot_wachtwoord' => 'nullable|string|max:100',
            'hotspot_ip' => 'nullable|ip',
        ]);

        // Ensure boolean is set correctly
        $validated['heeft_eigen_router'] = $request->boolean('heeft_eigen_router');

        $toernooi->update($validated);

        return redirect()
            ->route('toernooi.noodplan.index', $toernooi->routeParams())
            ->with('success', 'Netwerkinstellingen opgeslagen');
    }

    /**
     * Detect the server's local IP address and optionally save it
     */
    public function detectMyIp(Organisator $organisator, Request $request, Toernooi $toernooi): JsonResponse
    {
        // Get the server's local IP address
        $localIp = null;

        // Method 1: Try to get from gethostbyname
        $hostname = gethostname();
        $hostIp = gethostbyname($hostname);
        if ($hostIp !== $hostname && filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $localIp = $hostIp;
        }

        // Method 2: Try socket connection to detect outgoing IP
        if (!$localIp || str_starts_with($localIp, '127.')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                @socket_connect($sock, '8.8.8.8', 53);
                @socket_getsockname($sock, $localIp);
                @socket_close($sock);
            }
        }

        // Method 3: Check SERVER_ADDR (may be the local IP on some setups)
        if (!$localIp || str_starts_with($localIp, '127.')) {
            $serverAddr = $_SERVER['SERVER_ADDR'] ?? null;
            if ($serverAddr && !str_starts_with($serverAddr, '127.')) {
                $localIp = $serverAddr;
            }
        }

        // If save=primary or save=standby, also update the toernooi
        $saveAs = $request->query('save');
        if ($localIp && $saveAs === 'primary') {
            $toernooi->update(['local_server_primary_ip' => $localIp]);
        } elseif ($localIp && $saveAs === 'standby') {
            $toernooi->update(['local_server_standby_ip' => $localIp]);
        }

        return response()->json([
            'ip' => $localIp,
            'hostname' => $hostname,
            'saved_as' => $saveAs,
        ]);
    }

    /**
     * Emergency: Reopen preparation phase (reset weegkaarten_gemaakt_op)
     */
    public function heropenVoorbereiding(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $request->validate([
            'wachtwoord' => 'required|string',
        ]);

        // Verify password against logged-in organisator's password
        $loggedIn = auth('organisator')->user();
        if (!$loggedIn || !Hash::check($request->wachtwoord, $loggedIn->password)) {
            return redirect()
                ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
                ->with('error', 'Onjuist wachtwoord. Voorbereiding niet heropend.');
        }

        // Reset weegkaarten_gemaakt_op
        $toernooi->update(['weegkaarten_gemaakt_op' => null]);

        return redirect()
            ->route('toernooi.edit', $toernooi->routeParamsWith(['tab' => 'organisatie']))
            ->with('success', '⚠️ Voorbereiding heropend! Vergeet niet om "Maak weegkaarten" opnieuw te klikken na wijzigingen.');
    }
}
