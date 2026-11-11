<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * HTTP Response abstraction supporting HTML, JSON, XML, redirects, and safe file streaming.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private int $statusCode,
        private string $body,
        private array $headers = [],
        private readonly ?string $filePath = null
    ) {
    }

    /**
     * Create an HTML response.
     *
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            $statusCode,
            $html,
            array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers)
        );
    }

    /**
     * Create a JSON API response.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $statusCode = 200, array $headers = []): self
    {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return new self(
            $statusCode,
            $encoded,
            array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers)
        );
    }

    /**
     * Create an XML response (e.g. dynamic sitemap.xml).
     *
     * @param array<string, string> $headers
     */
    public static function xml(string $xml, int $statusCode = 200, array $headers = []): self
    {
        return new self(
            $statusCode,
            $xml,
            array_merge(['Content-Type' => 'application/xml; charset=UTF-8'], $headers)
        );
    }

    /**
     * Create a redirect response.
     *
     * @param array<string, string> $headers
     */
    public static function redirect(string $location, int $statusCode = 302, array $headers = []): self
    {
        // Guard against header injection via CR/LF characters.
        $safeLocation = str_replace(["\r", "\n"], '', $location);

        return new self(
            $statusCode,
            '',
            array_merge(['Location' => $safeLocation], $headers)
        );
    }

    /**
     * Create a binary file download response streamed directly from non-web storage.
     */
    public static function file(
        string $absolutePath,
        string $downloadFilename,
        string $mimeType,
        int $sizeBytes
    ): self {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException('Download file is missing or unreadable.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($downloadFilename)) ?: 'download';

        return new self(
            200,
            '',
            [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $sizeBytes,
                'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
            $absolutePath
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        foreach ($headers as $name => $value) {
            $clone->headers[$name] = $value;
        }
        return $clone;
    }

    /**
     * Emit status code, headers, and body/file stream to the SAPI output buffer.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($this->filePath !== null) {
            $stream = fopen($this->filePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Failed to open file stream for download.');
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
            return;
        }

        echo $this->body;
    }
}
