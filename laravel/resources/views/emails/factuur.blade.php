<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factuur {{ $factuurnummer }}</title>
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
            margin: 0 0 5px 0;
            font-size: 22px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #1e40af;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 5px 0;
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
            <h1>Factuur {{ $factuurnummer }}</h1>
            <div class="subtitle">JudoToernooi Upgrade</div>
        </div>

        <p>Beste {{ $organisator->contactpersoon ?? $organisator->organisatie_naam }},</p>

        <p>Bedankt voor uw upgrade van <strong>{{ $toernooi->naam }}</strong>. In de bijlage vindt u de factuur voor uw administratie.</p>

        <div class="info-box">
            <p><strong>Factuurnummer:</strong> {{ $factuurnummer }}</p>
            <p><strong>Bedrag:</strong> &euro; {{ number_format($betaling->bedrag, 2, ',', '.') }}</p>
            <p><strong>Toernooi:</strong> {{ $toernooi->naam }}</p>
        </div>

        <p>Heeft u vragen over deze factuur? Neem dan contact op via {{ config('factuur.email') }}.</p>

        <p>Met vriendelijke groet,<br>
        <strong>{{ config('factuur.bedrijfsnaam') }}</strong></p>

        <div class="footer">
            <p>{{ config('factuur.bedrijfsnaam') }} | KvK {{ config('factuur.kvk') }}</p>
        </div>
    </div>
</body>
</html>
