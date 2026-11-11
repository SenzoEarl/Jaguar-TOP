<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Core\Config;
use App\Exceptions\FileUploadException;
use App\Support\Enums\Department;
use finfo;

/**
 * Secure department file storage located strictly outside the web-exposed `public/` root.
 *
 * Enforces size limits from `.env`, extension allowlists, actual binary MIME detection
 * via PHP's `finfo`, randomized stored filenames, and realpath traversal guards.
 */
final class DepartmentFileStorage
{
    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimes
     */
    public function __construct(
        private readonly string $baseStoragePath,
        private readonly int $maxBytes,
        private readonly array $allowedExtensions,
        private readonly array $allowedMimes
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $rawPath = $config->string('uploads.storage_path', 'storage/departments');

        /** @var list<string> $extensions */
        $extensions = array_map('strtolower', $config->array('uploads.allowed_extensions', []));
        /** @var list<string> $mimes */
        $mimes = array_map('strtolower', $config->array('uploads.allowed_mimes', []));

        return new self(
            $config->resolvePath($rawPath),
            max(1024, $config->int('uploads.max_bytes', 5242880)),
            $extensions,
            $mimes
        );
    }

    /**
     * Validate and store an uploaded file inside `storage/departments/{departmentSlug}/`.
     *
     * @param array<string, mixed> $uploadedFile Entry in `$_FILES` shape
     * @return array{
     *     original_name: string,
     *     stored_name: string,
     *     mime_type: string,
     *     extension: string,
     *     size_bytes: int,
     *     checksum_sha256: string
     * }
     */
    public function store(Department $department, array $uploadedFile): array
    {
        $errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new FileUploadException(
                $this->uploadErrorMessage($errorCode),
                $errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE ? 413 : 422,
                'PHP upload error code: ' . $errorCode
            );
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new FileUploadException('Uploaded temporary file is missing.', 422);
        }

        $sizeBytes = (int) ($uploadedFile['size'] ?? filesize($tmpPath));
        if ($sizeBytes <= 0) {
            throw new FileUploadException('Empty files cannot be uploaded.', 422);
        }
        if ($sizeBytes > $this->maxBytes) {
            throw new FileUploadException(
                sprintf('File exceeds the maximum allowed size of %d bytes.', $this->maxBytes),
                413
            );
        }

        $rawOriginal = (string) ($uploadedFile['name'] ?? 'document');
        $originalName = $this->sanitizeOriginalFilename($rawOriginal);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
            throw new FileUploadException(
                'File extension is not permitted for department uploads.',
                415,
                'Disallowed extension: ' . $extension
            );
        }

        // Inspect actual file bytes via finfo rather than trusting client-supplied Content-Type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->file($tmpPath));
        if ($detectedMime === '' || !in_array($detectedMime, $this->allowedMimes, true)) {
            throw new FileUploadException(
                'Detected file content type is not allowed.',
                415,
                'Disallowed MIME: ' . $detectedMime
            );
        }

        if (!$this->isExtensionCompatibleWithMime($extension, $detectedMime)) {
            throw new FileUploadException(
                'File extension does not match its detected content format.',
                422,
                sprintf('Extension/MIME mismatch: %s vs %s', $extension, $detectedMime)
            );
        }

        $departmentDir = $this->departmentDirectory($department);
        if (!is_dir($departmentDir)) {
            mkdir($departmentDir, 0750, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $departmentDir . DIRECTORY_SEPARATOR . $storedName;

        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destination)
            : copy($tmpPath, $destination);

        if (!$moved) {
            throw new FileUploadException('Failed to store uploaded file safely.', 500);
        }

        chmod($destination, 0640);
        $checksum = hash_file('sha256', $destination) ?: '';

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $detectedMime,
            'extension' => $extension,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * Resolve a stored filename strictly within the target department directory.
     */
    public function resolveForDownload(Department $department, string $storedName): string
    {
        // Strip directory separators and verify canonical realpath stays inside department folder.
        $safeLeaf = basename($storedName);
        if ($safeLeaf !== $storedName || preg_match('/^[a-f0-9]{32}\.[a-z0-9]{1,10}$/', $safeLeaf) !== 1) {
            throw new FileUploadException('Invalid stored file reference.', 400);
        }

        $deptDirReal = realpath($this->departmentDirectory($department));
        $candidateReal = realpath($this->departmentDirectory($department) . DIRECTORY_SEPARATOR . $safeLeaf);

        if ($deptDirReal === false || $candidateReal === false) {
            throw new FileUploadException('Requested file does not exist on disk.', 404);
        }

        $normalizedDir = rtrim($deptDirReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidateReal, $normalizedDir) || !is_file($candidateReal)) {
            throw new FileUploadException('Access outside department storage directory denied.', 403);
        }

        return $candidateReal;
    }

    private function departmentDirectory(Department $department): string
    {
        return rtrim($this->baseStoragePath, '/\\') . DIRECTORY_SEPARATOR . $department->value;
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $leaf = basename(str_replace('\\', '/', $filename));
        $cleaned = preg_replace('/[^\w.\- ]+/', '_', $leaf) ?: 'document';
        return mb_substr(trim($cleaned), 0, 200);
    }

    private function isExtensionCompatibleWithMime(string $extension, string $mime): bool
    {
        $map = [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ];

        $allowedForExt = $map[$extension] ?? [];
        return in_array($mime, $allowedForExt, true);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the allowed size limit.',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted before completion.',
            UPLOAD_ERR_NO_FILE => 'Please select a file to upload.',
            default => 'The uploaded file could not be processed.',
        };
    }
}
