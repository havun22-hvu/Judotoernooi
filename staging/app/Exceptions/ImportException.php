<?php

namespace App\Exceptions;

/**
 * Exception for import errors (CSV, Excel, etc).
 *
 * Tracks row-level errors for user feedback.
 */
class ImportException extends JudoToernooiException
{
    public const ERROR_INVALID_FORMAT = 2001;
    public const ERROR_MISSING_COLUMNS = 2002;
    public const ERROR_ROW_VALIDATION = 2003;
    public const ERROR_DUPLICATE_ENTRY = 2004;
    public const ERROR_FILE_READ = 2005;
    public const ERROR_DATABASE = 2006;

    protected array $rowErrors = [];

    public static function invalidFormat(string $expected, string $received, array $context = []): static
    {
        return new static(
            "Invalid format: expected {$expected}, got {$received}",
            "Ongeldig bestandsformaat. Verwacht: {$expected}.",
            $context,
            self::ERROR_INVALID_FORMAT
        );
    }

    public static function missingColumns(array $columns, array $context = []): static
    {
        $columnList = implode(', ', $columns);
        return new static(
            "Missing required columns: {$columnList}",
            "Verplichte kolommen ontbreken: {$columnList}.",
            $context,
            self::ERROR_MISSING_COLUMNS
        );
    }

    public static function rowValidation(int $row, string $error, array $context = []): static
    {
        $exception = new static(
            "Row {$row} validation failed: {$error}",
            "Fout op regel {$row}: {$error}.",
            array_merge($context, ['row' => $row]),
            self::ERROR_ROW_VALIDATION
        );
        $exception->addRowError($row, $error);
        return $exception;
    }

    public static function duplicateEntry(int $row, string $identifier, array $context = []): static
    {
        return new static(
            "Duplicate entry at row {$row}: {$identifier}",
            "Dubbele invoer op regel {$row}: {$identifier}.",
            array_merge($context, ['row' => $row, 'identifier' => $identifier]),
            self::ERROR_DUPLICATE_ENTRY
        );
    }

    public static function fileRead(string $reason, array $context = []): static
    {
        return new static(
            "File read error: {$reason}",
            'Bestand kon niet worden gelezen.',
            $context,
            self::ERROR_FILE_READ
        );
    }

    public static function databaseError(string $message, array $context = []): static
    {
        return new static(
            "Database error during import: {$message}",
            'Er ging iets mis bij het opslaan. Probeer opnieuw.',
            $context,
            self::ERROR_DATABASE
        );
    }

    public function addRowError(int $row, string $error): self
    {
        $this->rowErrors[$row] = $error;
        return $this;
    }

    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    public function hasRowErrors(): bool
    {
        return !empty($this->rowErrors);
    }
}
