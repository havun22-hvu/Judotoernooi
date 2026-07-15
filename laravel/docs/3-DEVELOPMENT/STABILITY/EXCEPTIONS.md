---
title: Custom Exception Classes
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Custom Exception Classes

> Onderdeel van [Stabiliteitspatronen](../STABILITY.md).

## 1. Custom Exception Classes

### Hiërarchie

```
Exception
└── JudoToernooiException (base)
    ├── MollieException        # Betalingen
    ├── ImportException        # CSV/Excel import
    └── ExternalServiceException # Python solver, HTTP clients
```

### JudoToernooiException (Base)

```php
use App\Exceptions\JudoToernooiException;

// Aanmaken
throw new JudoToernooiException(
    message: 'Technische foutmelding',
    userMessage: 'Gebruikersvriendelijke melding',
    context: ['toernooi_id' => 123]
);

// Gebruiken in controller
try {
    $this->doSomething();
} catch (JudoToernooiException $e) {
    $e->log(); // Logt automatisch met juiste level
    return back()->with('error', $e->getUserMessage());
}
```

### MollieException

```php
use App\Exceptions\MollieException;

// Factory methods
throw MollieException::apiError($endpoint, $errorMessage);
throw MollieException::timeout($endpoint);
throw MollieException::tokenExpired($organisatorId);
throw MollieException::paymentCreationFailed($details);

// Error codes
MollieException::ERROR_API          // 1001
MollieException::ERROR_TIMEOUT      // 1002
MollieException::ERROR_OAUTH        // 1003
MollieException::ERROR_TOKEN_EXPIRED // 1004
MollieException::ERROR_PAYMENT      // 1005
```

### ImportException

```php
use App\Exceptions\ImportException;

// Factory methods
throw ImportException::fileReadError($filename, $error);
throw ImportException::invalidFormat($expected, $got);
throw ImportException::missingColumns($columns);
throw ImportException::rowError($rowNumber, $error);
throw ImportException::databaseError($error);
throw ImportException::partialImport($imported, $failed, $errors);

// Row-level tracking
$errors = [];
foreach ($rows as $index => $row) {
    try {
        $this->processRow($row);
    } catch (\Exception $e) {
        $errors[] = ['row' => $index + 1, 'error' => $e->getMessage()];
    }
}
if (!empty($errors)) {
    throw ImportException::partialImport(count($rows) - count($errors), count($errors), $errors);
}
```

### ExternalServiceException

```php
use App\Exceptions\ExternalServiceException;

// Factory methods
throw ExternalServiceException::timeout($service, $timeoutSeconds);
throw ExternalServiceException::connectionFailed($service, $error);
throw ExternalServiceException::processError($service, $exitCode, $output);
throw ExternalServiceException::pythonSolverError($error, $exitCode);
```

---

