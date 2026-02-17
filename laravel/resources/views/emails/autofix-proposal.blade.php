<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #dc2626; color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 18px; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .section { margin-bottom: 20px; }
        .section h2 { font-size: 14px; color: #6b7280; text-transform: uppercase; margin-bottom: 8px; }
        .error-info { background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; }
        .analysis { background: white; border: 1px solid #d1d5db; padding: 15px; border-radius: 6px; white-space: pre-wrap; font-size: 14px; }
        .actions { text-align: center; padding: 20px; background: white; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; margin: 0 8px; }
        .btn-approve { background: #059669; color: white; }
        .btn-reject { background: #6b7280; color: white; }
        .meta { font-size: 12px; color: #9ca3af; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 AutoFix Voorstel - JudoToernooi</h1>
    </div>

    <div class="content">
        <div class="section">
            <h2>Error</h2>
            <div class="error-info">
                <strong>{{ $proposal->exception_class }}</strong><br>
                {{ $proposal->exception_message }}<br><br>
                <strong>File:</strong> {{ $proposal->file }}:{{ $proposal->line }}<br>
                <strong>URL:</strong> {{ $proposal->url ?? 'N/A' }}<br>
                <strong>Tijd:</strong> {{ $proposal->created_at->format('d-m-Y H:i:s') }}
            </div>
        </div>

        <div class="section">
            <h2>Claude's Analyse & Voorgestelde Fix</h2>
            <div class="analysis">{!! nl2br(e($proposal->claude_analysis)) !!}</div>
        </div>
    </div>

    <div class="actions">
        <a href="{{ url('/autofix/' . $proposal->approval_token) }}" class="btn btn-approve">
            Bekijk & Goedkeuren
        </a>
        <a href="{{ url('/autofix/' . $proposal->approval_token . '/reject') }}" class="btn btn-reject">
            Afwijzen
        </a>

        <div class="meta">
            Proposal #{{ $proposal->id }} | Token: {{ \Illuminate\Support\Str::limit($proposal->approval_token, 12) }}...
        </div>
    </div>
</body>
</html>
