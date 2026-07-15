---
title: Error Notification Service
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Error Notification Service

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

## 5. Error Notification Service

Stuurt kritieke errors naar HavunCore voor remote monitoring.

### Configuratie

```env
# .env
ERROR_NOTIFICATION_WEBHOOK=https://havuncore.example.com/webhook/errors
```

```php
// config/services.php
'error_notification' => [
    'webhook_url' => env('ERROR_NOTIFICATION_WEBHOOK'),
],
```

### Automatisch (via bootstrap/app.php)

Kritieke exceptions worden automatisch gemeld:

```php
// bootstrap/app.php
$exceptions->report(function (\Throwable $e) {
    if (!app()->environment('local', 'testing')) {
        // Skip common non-critical exceptions
        $ignoredExceptions = [
            TokenMismatchException::class,
            ModelNotFoundException::class,
            NotFoundHttpException::class,
            ValidationException::class,
        ];
        // Send notification for critical errors
        app(ErrorNotificationService::class)->notifyException($e, [...]);
    }
});
```

### Handmatig

```php
use App\Services\ErrorNotificationService;

// Async notification (fire and forget)
app(ErrorNotificationService::class)->notify(
    title: 'Critical: Backup failed',
    message: 'Daily backup could not complete',
    context: ['server' => gethostname()],
    severity: 'critical'
);

// Synchronous (blocking, for critical)
$success = app(ErrorNotificationService::class)->notifyImmediate(
    title: 'Payment webhook failed',
    message: 'Multiple webhook failures detected',
    context: ['last_error' => $error]
);
```

### Payload Format

```json
{
    "app": "JudoToernooi",
    "environment": "production",
    "server": "server-name",
    "timestamp": "2026-02-02T12:00:00+01:00",
    "severity": "critical",
    "title": "Exception: Error message",
    "message": "Full error message",
    "context": {
        "exception_class": "App\\Exceptions\\MollieException",
        "file": "/var/www/app/Services/MollieService.php",
        "line": 145,
        "trace": "..."
    },
    "url": "https://judotournament.org/...",
    "user_id": 123,
    "ip": "1.2.3.4"
}
```

---

