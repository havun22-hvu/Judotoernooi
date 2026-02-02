<?php

namespace App\Exceptions;

/**
 * Exception for import errors (CSV, Excel).
 *
 * Handles:
 * - File parsing errors
 * - Data validation errors
 * - Row-level errors with tracking
 */
class ImportException extends JudoToernooiException
{
    public const ERROR_FILE_READ = 2001;
    public const ERROR_INVALID_FORMAT = 2002;
    public const ERROR_MISSING_COLUMNS = 2003;
    public const ERROR_ROW_VALIDATION = 2004;
    public const ERROR_DATABASE = 2005;
    public const ERROR_PARTIAL_IMPORT = 2006;

    protected string $logLevel = 'warning';
    protected array $rowErrors = [];

    /**
     * File could not be read.
     */
    public static function fileReadError(string $filename, string $error): static
    {
        return new static(
            "Could not read file {$filename}: {$error}",
            "Bestand kon niet worden gelezen. Controleer of het een geldig CSV of Excel bestand is.",
            [
                'filename' => $filename,
                'error' => $error,
            ],
            self::ERROR_FILE_READ
        );
    }

    /**
     * Invalid file format.
     */
    public static function invalidFormat(string $filename, string $expectedType): static
    {
        return new static(
            "Invalid file format for {$filename}, expected {$expectedType}",
            "Ongeldig bestandsformaat. Gebruik een CSV of Excel (.xlsx) bestand.",
            [
                'filename' => $filename,
                'expected_type' => $expectedType,
            ],
            self::ERROR_INVALID_FORMAT
        );
    }

    /**
     * Required columns missing.
     */
    public static function missingColumns(array $missing): static
    {
        $columns = implode(', ', $missing);
        return new static(
            "Missing required columns: {$columns}",
            "Verplichte kolommen ontbreken: {$columns}",
            ['missing_columns' => $missing],
            self::ERROR_MISSING_COLUMNS
        );
    }

    /**
     * Row validation error.
     */
    public static function rowError(int $rowNumber, string $field, string $error, mixed $value = null): static
    {
        return new static(
            "Row {$rowNumber}: {$field} - {$error}",
            "Rij {$rowNumber}: {$error}",
            [
                'row_number' => $rowNumber,
                'field' => $field,
                'value' => $value,
            ],
            self::ERROR_ROW_VALIDATION
        );
    }

    /**
     * Database error during import.
     */
    public static function databaseError(string $error, int $rowNumber = 0): static
    {
        $exception = new static(
            "Database error during import at row {$rowNumber}: {$error}",
            "Database fout bij importeren. Neem contact op met support.",
            [
                'row_number' => $rowNumber,
                'error' => $error,
            ],
            self::ERROR_DATABASE
        );
        $exception->logLevel = 'error';
        return $exception;
    }

    /**
     * Partial import completed with errors.
     */
    public static function partialImport(int $imported, int $failed, array $errors): static
    {
        $exception = new static(
            "Partial import: {$imported} imported, {$failed} failed",
            "{$imported} judoka's geïmporteerd, {$failed} mislukt.",
            [
                'imported' => $imported,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 10), // Limit stored errors
            ],
            self::ERROR_PARTIAL_IMPORT
        );
        $exception->rowErrors = $errors;
        return $exception;
    }

    /**
     * Get row-level errors for display.
     */
    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }
}
