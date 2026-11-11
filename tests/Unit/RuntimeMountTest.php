<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RuntimeMountTest extends TestCase
{
    public function testDetectsRootMountPathOnDedicatedVirtualHost(): void
    {
        $basePath = Request::detectMountedBasePath([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/stays?type=lodge',
        ]);
        $backslashRoot = Request::detectMountedBasePath([
            'SCRIPT_NAME' => '\\',
            'REQUEST_URI' => '/',
        ]);

        self::assertSame('', $basePath);
        self::assertSame('', $backslashRoot);
    }

    public function testDetectsParentSubdirectoryWhenForwardedViaRootHtaccess(): void
    {
        $basePath = Request::detectMountedBasePath([
            'SCRIPT_NAME' => '/TOP/public/index.php',
            'REQUEST_URI' => '/TOP/stays',
        ]);

        self::assertSame('/TOP', $basePath);
    }

    public function testDetectsDirectPublicSubdirectoryMount(): void
    {
        $basePath = Request::detectMountedBasePath([
            'SCRIPT_NAME' => '/TOP/public/index.php',
            'REQUEST_URI' => '/TOP/public/',
        ]);

        self::assertSame('/TOP/public', $basePath);
    }

    public function testRejectsMalformedHostHeaderWhenInferringAppUrl(): void
    {
        $validUrl = Request::detectCurrentAppUrl('/TOP', [
            'HTTP_HOST' => 'localhost',
            'HTTPS' => 'off',
        ]);
        $invalidUrl = Request::detectCurrentAppUrl('/TOP', [
            'HTTP_HOST' => 'evil.example/path@inject',
        ]);

        self::assertSame('http://localhost/TOP', $validUrl);
        self::assertNull($invalidUrl);
    }

    public function testAppliesRuntimeDefaultsWithoutClobberingExplicitConfig(): void
    {
        $rootPath = dirname(__DIR__, 2);
        $config = new Config($rootPath, [
            'app' => [
                'base_path' => '',
                'url' => 'http://127.0.0.1:8000',
            ],
        ]);

        $updated = $config->withWebRuntimeDefaults('/TOP', 'http://localhost/TOP');
        self::assertSame('/TOP', $updated->string('app.base_path'));
        self::assertSame('http://localhost/TOP', $updated->string('app.url'));

        $explicit = new Config($rootPath, [
            'app' => [
                'base_path' => '/custom',
                'url' => 'https://example.org/custom',
            ],
        ]);
        $unchanged = $explicit->withWebRuntimeDefaults('/TOP', 'http://localhost/TOP');
        self::assertSame('/custom', $unchanged->string('app.base_path'));
        self::assertSame('https://example.org/custom', $unchanged->string('app.url'));
    }
}
