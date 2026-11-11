<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies centralized input validation rules and structured error bags.
 */
final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidatesAndCastsCleanInput(): void
    {
        $clean = $this->validator->validate([
            'username' => ' guest_user ',
            'email' => 'guest@example.com',
            'guests_count' => '4',
            'priority' => 'high',
            'notes' => '',
        ], [
            'username' => 'required|username',
            'email' => 'required|email',
            'guests_count' => 'required|int|min:1|max:8',
            'priority' => 'required|in:low,medium,high',
            'notes' => 'nullable|string|max:200',
        ]);

        self::assertSame('guest_user', $clean['username']);
        self::assertSame(4, $clean['guests_count']);
        self::assertNull($clean['notes']);
    }

    public function testThrowsValidationExceptionWithFieldErrors(): void
    {
        try {
            $this->validator->validate([
                'check_in_date' => '2026-12-15',
                'check_out_date' => '2026-12-12',
                'password' => 'abc',
                'password_confirmation' => 'xyz',
            ], [
                'check_in_date' => 'required|date',
                'check_out_date' => 'required|date|after_field:check_in_date',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required|same:password',
            ]);
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();
            self::assertArrayHasKey('check_out_date', $errors);
            self::assertArrayHasKey('password', $errors);
            self::assertArrayHasKey('password_confirmation', $errors);
            self::assertSame(422, $exception->getHttpStatus());
        }
    }
}
