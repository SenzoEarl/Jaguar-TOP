<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Verifies CSRF token generation, constant-time comparison, and rotation semantics.
 */
final class CsrfTest extends TestCase
{
    public function testGeneratesValidatesAndRotatesTokensPerSession(): void
    {
        $session = Session::createInMemory();
        $session->start();
        $csrf = new Csrf($session);

        $token = $csrf->token();
        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('tampered-token'));
        self::assertFalse($csrf->validate(''));
        self::assertFalse($csrf->validate(null));

        $rotated = $csrf->rotate();
        self::assertSame(64, strlen($rotated));
        self::assertNotSame($token, $rotated);
        self::assertFalse($csrf->validate($token));
        self::assertTrue($csrf->validate($rotated));
    }
}
