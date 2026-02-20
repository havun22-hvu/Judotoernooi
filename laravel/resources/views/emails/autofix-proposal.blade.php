<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; }
        .header { background: #dc2626; color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 18px; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; }
        .section { margin-bottom: 20px; }
        .section h2 { font-size: 14px; color: #6b7280; text-transform: uppercase; margin-bottom: 8px; }
        .error-info { background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; }
        .attempt { background: white; border: 1px solid #d1d5db; padding: 15px; border-radius: 6px; margin-bottom: 10px; }
        .attempt-header { font-weight: bold; color: #dc2626; margin-bottom: 8px; font-size: 14px; }
        .attempt-error { background: #fef2f2; border: 1px solid #fecaca; padding: 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #991b1b; margin-top: 8px; }
        .analysis { white-space: pre-wrap; font-size: 13px; }
        .review-link { text-align: center; padding: 15px; }
        .btn { display: inline-block; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; background: #2563eb; color: white; }
        .meta { font-size: 12px; color: #9ca3af; margin-top: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AutoFix MISLUKT - {{ __('JudoToernooi') }}</h1>
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
            <h2>Pogingen ({{ $attempts ? $attempts->count() : 1 }}x mislukt)</h2>

            @if($attempts && $attempts->count() > 0)
                @foreach($attempts as $index => $attempt)
                    <div class="attempt">
                        <div class="attempt-header">Poging {{ $index + 1 }}</div>
                        <div class="analysis">{!! nl2br(e($attempt->claude_analysis)) !!}</div>
                        @if($attempt->apply_error)
                            <div class="attempt-error">
                                <strong>Fout:</strong> {{ $attempt->apply_error }}
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="attempt">
                    <div class="analysis">{!! nl2br(e($proposal->claude_analysis)) !!}</div>
                    @if($proposal->apply_error)
                        <div class="attempt-error">
                            <strong>Fout:</strong> {{ $proposal->apply_error }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="review-link">
            <a href="{{ url('/autofix/' . $proposal->approval_token) }}" class="btn">
                Bekijk Details
            </a>
        </div>

        <div class="meta">
            Claude kon deze error niet automatisch fixen. Handmatige actie vereist.
        </div>
    </div>
</body>
</html>
