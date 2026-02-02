<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\JudoToernooiException;
use App\Exceptions\MollieException;
use App\Exceptions\ImportException;
use App\Exceptions\ExternalServiceException;
use PHPUnit\Framework\TestCase;

class JudoToernooiExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_base_exception_with_user_message(): void
    {
        $exception = new JudoToernooiException(
            message: 'Technical error details',
            userMessage: 'Something went wrong',
            context: ['key' => 'value']
        );

        $this->assertEquals('Technical error details', $exception->getMessage());
        $this->assertEquals('Something went wrong', $exception->getUserMessage());
        $this->assertEquals(['key' => 'value'], $exception->getContext());
    }

    /** @test */
    public function it_uses_message_as_user_message_when_not_provided(): void
    {
        $exception = new JudoToernooiException('Error message');

        $this->assertEquals('Error message', $exception->getUserMessage());
    }
}

class MollieExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_api_error(): void
    {
        $exception = MollieException::apiError('/payments', 'Invalid API key');

        $this->assertInstanceOf(MollieException::class, $exception);
        $this->assertEquals(MollieException::ERROR_API, $exception->getCode());
        $this->assertStringContainsString('/payments', $exception->getMessage());
    }

    /** @test */
    public function it_creates_timeout_error(): void
    {
        $exception = MollieException::timeout('/payments');

        $this->assertEquals(MollieException::ERROR_TIMEOUT, $exception->getCode());
        $this->assertStringContainsString('timeout', strtolower($exception->getUserMessage()));
    }

    /** @test */
    public function it_creates_token_expired_error(): void
    {
        $exception = MollieException::tokenExpired(123);

        $this->assertEquals(MollieException::ERROR_TOKEN_EXPIRED, $exception->getCode());
        $this->assertArrayHasKey('organisator_id', $exception->getContext());
    }

    /** @test */
    public function it_creates_payment_creation_failed_error(): void
    {
        $exception = MollieException::paymentCreationFailed('Invalid amount');

        $this->assertEquals(MollieException::ERROR_PAYMENT, $exception->getCode());
    }
}

class ImportExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_file_read_error(): void
    {
        $exception = ImportException::fileReadError('test.csv', 'File not found');

        $this->assertInstanceOf(ImportException::class, $exception);
        $this->assertStringContainsString('test.csv', $exception->getMessage());
    }

    /** @test */
    public function it_creates_missing_columns_error(): void
    {
        $exception = ImportException::missingColumns(['naam', 'geboortejaar']);

        $this->assertStringContainsString('naam', $exception->getMessage());
        $this->assertStringContainsString('geboortejaar', $exception->getMessage());
    }

    /** @test */
    public function it_creates_row_error_with_line_number(): void
    {
        $exception = ImportException::rowError(5, 'Invalid date format');

        $this->assertArrayHasKey('row', $exception->getContext());
        $this->assertEquals(5, $exception->getContext()['row']);
    }

    /** @test */
    public function it_creates_partial_import_error(): void
    {
        $errors = [
            ['row' => 1, 'error' => 'Error 1'],
            ['row' => 2, 'error' => 'Error 2'],
        ];

        $exception = ImportException::partialImport(10, 2, $errors);

        $this->assertArrayHasKey('imported', $exception->getContext());
        $this->assertArrayHasKey('failed', $exception->getContext());
        $this->assertEquals(10, $exception->getContext()['imported']);
        $this->assertEquals(2, $exception->getContext()['failed']);
    }
}

class ExternalServiceExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_timeout_error(): void
    {
        $exception = ExternalServiceException::timeout('python-solver', 30);

        $this->assertInstanceOf(ExternalServiceException::class, $exception);
        $this->assertStringContainsString('30', $exception->getMessage());
    }

    /** @test */
    public function it_creates_connection_failed_error(): void
    {
        $exception = ExternalServiceException::connectionFailed('api-server', 'Connection refused');

        $this->assertStringContainsString('api-server', $exception->getMessage());
    }

    /** @test */
    public function it_creates_python_solver_error(): void
    {
        $exception = ExternalServiceException::pythonSolverError('Module not found', 1);

        $this->assertArrayHasKey('exit_code', $exception->getContext());
        $this->assertEquals(1, $exception->getContext()['exit_code']);
    }
}
