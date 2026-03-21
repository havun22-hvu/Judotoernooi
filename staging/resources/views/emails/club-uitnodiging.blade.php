<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Uitnodiging :naam', ['naam' => $toernooi->naam]) }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e40af;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .header .date {
            color: #666;
            font-size: 16px;
        }
        .club-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #1e40af;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
        }
        .info-box p {
            margin: 5px 0;
        }
        .cta-button {
            display: inline-block;
            background: #1e40af;
            color: white !important;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
        }
        .cta-button:hover {
            background: #1e3a8a;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .link-fallback {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            margin-top: 10px;
        }
        .steps {
            margin: 20px 0;
        }
        .steps li {
            margin: 10px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .highlight {
            background: #fef3c7;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $toernooi->naam }}</h1>
            <div class="date">{{ $toernooi->datum->format('l d F Y') }}</div>
        </div>

        <p class="club-name">{{ __('Beste :naam', ['naam' => $club->contact_naam ?? $club->naam]) }},</p>

        <p>{!! __('Namens :organisatie nodigen wij uw club uit voor deelname aan het <strong>:toernooi</strong>.', ['organisatie' => $toernooi->organisatie ?? __('de organisatie'), 'toernooi' => $toernooi->naam]) !!}</p>

        <div class="info-box">
            <h3>{{ __('Toernooi Informatie') }}</h3>
            <p><strong>{{ __('Datum:') }}</strong> {{ $toernooi->datum->format('d-m-Y') }}</p>
            @if($toernooi->locatie)
            <p><strong>{{ __('Locatie:') }}</strong> {{ $toernooi->locatie }}</p>
            @endif
            @if($toernooi->inschrijving_deadline)
            <p><strong>{{ __('Inschrijven tot:') }}</strong> <span class="highlight">{{ $toernooi->inschrijving_deadline->format('d-m-Y') }}</span></p>
            @endif
            @if($toernooi->max_judokas)
            <p><strong>{{ __('Max. deelnemers:') }}</strong> {{ $toernooi->max_judokas }}</p>
            @endif
        </div>

        <h3>{{ __('Hoe werkt het?') }}</h3>
        <ol class="steps">
            <li>{!! __('<strong>Ga naar de onderstaande link</strong> om naar uw club-pagina te gaan') !!}</li>
            <li>{!! __('<strong>Log in met de PIN code</strong> (zie hieronder)') !!}</li>
            <li>{!! __('<strong>Voeg uw judoka\'s toe</strong> met naam, geboortejaar, geslacht, band en gewichtsklasse') !!}</li>
            <li>{!! __('<strong>Ontvang de weegkaarten</strong> voor uw judoka\'s zodra de indeling klaar is') !!}</li>
        </ol>

        <div class="cta-section">
            <a href="{{ $portalUrl }}" class="cta-button">{{ __('Naar Club Portaal') }}</a>
            <div style="margin-top: 15px; background: #fef3c7; padding: 15px; border-radius: 8px; display: inline-block;">
                <p style="margin: 0; color: #92400e; font-weight: bold;">{{ __('PIN code:') }} <span style="font-size: 20px; letter-spacing: 2px;">{{ $pincode }}</span></p>
            </div>
            <p style="color: #666; font-size: 14px; margin-top: 15px;">{{ __('Link werkt niet? Kopieer deze URL:') }}</p>
            <div class="link-fallback">{{ $portalUrl }}</div>
        </div>

        <div class="info-box">
            <h3>{{ __('Weegkaarten') }}</h3>
            <p>{{ __('Na de poule-indeling ontvangt u in uw coach portaal de weegkaarten voor al uw judoka\'s. U kunt deze eenvoudig doorsturen via WhatsApp of e-mail naar de ouders/judoka\'s.') }}</p>
        </div>

        <p>{{ __('Heeft u vragen? Neem dan contact op met de organisatie.') }}</p>

        <p>{{ __('Met sportieve groet,') }}<br>
        <strong>{{ $toernooi->organisatie ?? __('De Organisatie') }}</strong></p>

        <div class="footer">
            <p>{{ __('Deze uitnodiging is verstuurd naar :email.', ['email' => $club->email]) }}</p>
            <p>{{ $toernooi->naam }} | {{ $toernooi->datum->format('d-m-Y') }}</p>
        </div>
    </div>
</body>
</html>
