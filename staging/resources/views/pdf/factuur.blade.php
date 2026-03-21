<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Factuur {{ $factuurnummer }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }
        .page { padding: 40px; }

        /* Header */
        .header { margin-bottom: 40px; }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .company-details {
            color: #666;
            font-size: 10px;
            line-height: 1.6;
        }

        /* Addresses */
        .addresses {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .address-from, .address-to {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .address-label {
            font-size: 9px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .address-to .org-name {
            font-weight: bold;
            font-size: 13px;
        }

        /* Invoice meta */
        .invoice-meta {
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            background: #f9fafb;
        }
        .invoice-meta table { width: 100%; }
        .invoice-meta td { padding: 3px 0; }
        .invoice-meta td:first-child {
            font-weight: bold;
            width: 180px;
        }

        /* Lines table */
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .lines-table th {
            background: #1e40af;
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .lines-table th:last-child { text-align: right; }
        .lines-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .lines-table td:last-child { text-align: right; }

        /* Totals */
        .totals {
            width: 250px;
            margin-left: auto;
            margin-bottom: 30px;
        }
        .totals table { width: 100%; }
        .totals td { padding: 5px 0; }
        .totals td:last-child { text-align: right; }
        .totals .total-row {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #1e40af;
            color: #1e40af;
        }

        /* KOR notice */
        .kor-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 10px;
            color: #92400e;
            margin-bottom: 30px;
        }

        /* Footer */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            font-size: 9px;
            color: #999;
            text-align: center;
            line-height: 1.8;
        }
        .payment-info {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="page">
        {{-- Header --}}
        <div class="header">
            <div class="company-name">{{ $config['bedrijfsnaam'] }}</div>
            <div class="company-details">
                {{ $config['adres'] }} | {{ $config['postcode'] }} {{ $config['plaats'] }}<br>
                KvK: {{ $config['kvk'] }} | BTW-id: {{ $config['btw_id'] }}<br>
                {{ $config['email'] }} | {{ $config['telefoon'] }}
            </div>
        </div>

        {{-- Addresses --}}
        <div class="addresses">
            <div class="address-to">
                <div class="address-label">Factuur aan</div>
                <div class="org-name">{{ $organisator->organisatie_naam }}</div>
                @if($organisator->straat)
                    <div>{{ $organisator->straat }}</div>
                @endif
                @if($organisator->postcode || $organisator->plaats)
                    <div>{{ $organisator->postcode }} {{ $organisator->plaats }}</div>
                @endif
                @if($organisator->land && $organisator->land !== 'Nederland')
                    <div>{{ $organisator->land }}</div>
                @endif
                @if($organisator->kvk_nummer)
                    <div style="margin-top: 5px; font-size: 10px; color: #666;">KvK: {{ $organisator->kvk_nummer }}</div>
                @endif
                @if($organisator->btw_nummer)
                    <div style="font-size: 10px; color: #666;">BTW: {{ $organisator->btw_nummer }}</div>
                @endif
            </div>
        </div>

        {{-- Invoice meta --}}
        <div class="invoice-meta">
            <table>
                <tr>
                    <td>Factuurnummer:</td>
                    <td>{{ $factuurnummer }}</td>
                </tr>
                <tr>
                    <td>Factuurdatum:</td>
                    <td>{{ $factuurdatum }}</td>
                </tr>
                <tr>
                    <td>Betalingskenmerk:</td>
                    <td>{{ $betaling->stripe_payment_id ?? $betaling->mollie_payment_id ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Toernooi:</td>
                    <td>{{ $toernooi->naam }}</td>
                </tr>
            </table>
        </div>

        {{-- Lines --}}
        <table class="lines-table">
            <thead>
                <tr>
                    <th>Omschrijving</th>
                    <th>Bedrag</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $omschrijving }}</td>
                    <td>&euro; {{ number_format($bedrag, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <table>
                <tr class="total-row">
                    <td>Totaal</td>
                    <td>&euro; {{ number_format($bedrag, 2, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        {{-- KOR notice --}}
        @if($config['kor'])
            <div class="kor-notice">
                {{ $config['kor_tekst'] }}
            </div>
        @endif

        {{-- Payment info --}}
        <div class="payment-info">
            <strong>Betaalstatus:</strong> Voldaan op {{ $factuurdatum }}<br>
            <strong>Betaald via:</strong> {{ $betaling->payment_provider === 'Stripe' ? 'Stripe' : 'Mollie' }}
        </div>

        {{-- Footer --}}
        <div class="footer">
            {{ $config['bedrijfsnaam'] }} | {{ $config['adres'] }}, {{ $config['postcode'] }} {{ $config['plaats'] }}<br>
            KvK: {{ $config['kvk'] }} | BTW-id: {{ $config['btw_id'] }} | IBAN: {{ $config['iban'] }}<br>
            {{ $config['email'] }}
        </div>
    </div>
</body>
</html>
