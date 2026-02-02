<?php

namespace App\Support;

/**
 * Result Object Pattern - represents success or failure of an operation.
 *
 * Use instead of throwing exceptions for expected failures.
 * Allows cleaner control flow without try-catch blocks.
 *
 * @template T The type of the success value
 */
class Result
{
    private bool $success;
    private mixed $value;
    private ?string $error;
    private array $context;

    private function __construct(bool $success, mixed $value = null, ?string $error = null, array $context = [])
    {
        $this->success = $success;
        $this->value = $value;
        $this->error = $error;
        $this->context = $context;
    }

    /**
     * Create a successful result.
     *
     * @template T
     * @param T $value
     * @return Result<T>
     */
    public static function success(mixed $value = null): self
    {
        return new self(true, $value);
    }

    /**
     * Create a failed result.
     *
     * @param string $error Error message
     * @param array $context Additional context for debugging
     */
    public static function failure(string $error, array $context = []): self
    {
        return new self(false, null, $error, $context);
    }

    /**
     * Check if operation succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if operation failed.
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get the success value.
     *
     * @return T
     * @throws \RuntimeException if result is a failure
     */
    public function getValue(): mixed
    {
        if (!$this->success) {
            throw new \RuntimeException("Cannot get value from failed result: {$this->error}");
        }
        return $this->value;
    }

    /**
     * Get the success value or a default.
     *
     * @template D
     * @param D $default
     * @return T|D
     */
    public function getValueOr(mixed $default): mixed
    {
        return $this->success ? $this->value : $default;
    }

    /**
     * Get the error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get error context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Map the success value to a new value.
     *
     * @template U
     * @param callable(T): U $callback
     * @return Result<U>
     */
    public function map(callable $callback): self
    {
        if (!$this->success) {
            return $this;
        }
        return self::success($callback($this->value));
    }

    /**
     * Chain another operation that returns a Result.
     *
     * @template U
     * @param callable(T): Result<U> $callback
     * @return Result<U>
     */
    public function flatMap(callable $callback): self
    {
        if (!$this->success) {
            return $this;
        }
        return $callback($this->value);
    }

    /**
     * Execute callback on success, return self for chaining.
     *
     * @param callable(T): void $callback
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->success) {
            $callback($this->value);
        }
        return $this;
    }

    /**
     * Execute callback on failure, return self for chaining.
     *
     * @param callable(string, array): void $callback
     */
    public function onFailure(callable $callback): self
    {
        if (!$this->success) {
            $callback($this->error, $this->context);
        }
        return $this;
    }

    /**
     * Convert to array for JSON responses.
     */
    public function toArray(): array
    {
        if ($this->success) {
            return [
                'success' => true,
                'data' => $this->value,
            ];
        }

        return [
            'success' => false,
            'error' => $this->error,
            'context' => $this->context,
        ];
    }

    /**
     * Convert to JSON response.
     */
    public function toResponse(int $successCode = 200, int $failureCode = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            $this->toArray(),
            $this->success ? $successCode : $failureCode
        );
    }

    /**
     * Wrap a callable that might throw an exception.
     *
     * @template T
     * @param callable(): T $callback
     * @param string $errorPrefix Prefix for error message
     * @return Result<T>
     */
    public static function try(callable $callback, string $errorPrefix = ''): self
    {
        try {
            return self::success($callback());
        } catch (\Exception $e) {
            $message = $errorPrefix ? "{$errorPrefix}: {$e->getMessage()}" : $e->getMessage();
            return self::failure($message, [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
