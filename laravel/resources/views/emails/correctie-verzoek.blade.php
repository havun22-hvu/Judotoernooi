<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correctie vereist - {{ $toernooi->naam }}</title>
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
            border-bottom: 2px solid #dc2626;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #dc2626;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .warning-box {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box h3 {
            margin: 0 0 10px 0;
            color: #dc2626;
        }
        .judoka-list {
            background: #fff7ed;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .judoka-item {
            padding: 10px;
            border-bottom: 1px solid #fed7aa;
            margin-bottom: 5px;
        }
        .judoka-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .judoka-name {
            font-weight: bold;
            color: #c2410c;
        }
        .judoka-warning {
            font-size: 14px;
            color: #9a3412;
            margin-top: 5px;
        }
        .cta-button {
            display: inline-block;
            background: #dc2626;
            color: white !important;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
        }
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        .pin-box {
            margin-top: 15px;
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
        }
        .pin-box p {
            margin: 0;
            color: #92400e;
            font-weight: bold;
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
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Actie Vereist</h1>
            <p style="color: #666;">{{ $toernooi->naam }} - {{ $toernooi->datum->format('d F Y') }}</p>
        </div>

        <p>Beste {{ $club->contact_naam ?? $club->naam }},</p>

        <div class="warning-box">
            <h3>{{ $judokas->count() }} judoka('s) vereisen correctie</h3>
            <p>Bij het importeren van de judoka-gegevens zijn er problemen gedetecteerd. Wij verzoeken u vriendelijk om de onderstaande gegevens te controleren en aan te vullen.</p>
        </div>

        <div class="judoka-list">
            <h4 style="margin-top: 0; color: #c2410c;">Te corrigeren judoka's:</h4>
            @foreach($judokas as $judoka)
            <div class="judoka-item">
                <div class="judoka-name">{{ $judoka->naam }}</div>
                @if($judoka->import_warnings)
                <div class="judoka-warning">{{ $judoka->import_warnings }}</div>
                @endif
            </div>
            @endforeach
        </div>

        <div class="cta-section">
            <p><strong>Log in op uw club portaal om de gegevens aan te passen:</strong></p>
            <a href="{{ $portalUrl }}" class="cta-button">Naar Club Portaal</a>
            <div class="pin-box">
                <p>PIN code: <span style="font-size: 20px; letter-spacing: 2px;">{{ $pincode }}</span></p>
            </div>
            <p style="color: #666; font-size: 14px; margin-top: 15px;">Link werkt niet? Kopieer deze URL:</p>
            <div class="link-fallback">{{ $portalUrl }}</div>
        </div>

        @if($toernooi->inschrijving_deadline)
        <p style="background: #fef3c7; padding: 10px; border-radius: 4px; text-align: center;">
            <strong>Let op:</strong> Corrigeer de gegevens voor <strong>{{ $toernooi->inschrijving_deadline->format('d-m-Y') }}</strong>
        </p>
        @endif

        <p>Heeft u vragen? Neem dan contact op met de organisatie.</p>

        <p>Met sportieve groet,<br>
        <strong>{{ $toernooi->organisatie ?? 'De Organisatie' }}</strong></p>

        <div class="footer">
            <p>Deze email is verstuurd naar {{ $club->email }}.</p>
            <p>{{ $toernooi->naam }} | {{ $toernooi->datum->format('d-m-Y') }}</p>
        </div>
    </div>
</body>
</html>
