<?php

declare(strict_types=1);

namespace App\Modules\Files\Models;

use App\Support\Enums\Department;

/**
 * Domain entity representing metadata for a protected department document.
 */
final class DepartmentFile
{
    public function __construct(
        private readonly int $id,
        private readonly int $departmentId,
        private readonly Department $department,
        private readonly int $uploadedByUserId,
        private readonly string $uploadedByName,
        private readonly string $originalName,
        private readonly string $storedName,
        private readonly string $mimeType,
        private readonly string $fileExtension,
        private readonly int $sizeBytes,
        private readonly string $checksumSha256,
        private readonly ?string $description,
        private readonly string $createdAt
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['department_id'],
            Department::from((string) $row['department_slug']),
            (int) $row['uploaded_by_user_id'],
            (string) ($row['uploaded_by_name'] ?? 'Staff'),
            (string) $row['original_name'],
            (string) $row['stored_name'],
            (string) $row['mime_type'],
            (string) $row['file_extension'],
            (int) $row['size_bytes'],
            (string) $row['checksum_sha256'],
            isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
            (string) ($row['created_at'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDepartmentId(): int
    {
        return $this->departmentId;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function getUploadedByUserId(): int
    {
        return $this->uploadedByUserId;
    }

    public function getUploadedByName(): string
    {
        return $this->uploadedByName;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function formattedSize(): string
    {
        if ($this->sizeBytes >= 1048576) {
            return number_format($this->sizeBytes / 1048576, 2) . ' MB';
        }
        if ($this->sizeBytes >= 1024) {
            return number_format($this->sizeBytes / 1024, 1) . ' KB';
        }
        return $this->sizeBytes . ' B';
    }

    public function getChecksumSha256(): string
    {
        return $this->checksumSha256;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
