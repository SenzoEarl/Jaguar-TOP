<?php

declare(strict_types=1);

namespace App\Modules\Files\Repositories;

use App\Infrastructure\Database\Connection;
use App\Modules\Files\Models\DepartmentFile;
use App\Support\Enums\Department;

/**
 * PDO repository for protected department file metadata (`files` table).
 */
final class FileRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<DepartmentFile>
     */
    public function findByDepartment(Department $department): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT f.*, d.slug AS department_slug, u.full_name AS uploaded_by_name
             FROM files f
             INNER JOIN departments d ON d.id = f.department_id
             INNER JOIN users u ON u.id = f.uploaded_by_user_id
             WHERE d.slug = :slug
             ORDER BY f.created_at DESC, f.id DESC',
            ['slug' => $department->value]
        );

        return array_map(
            static fn (array $row): DepartmentFile => DepartmentFile::fromRow($row),
            $rows
        );
    }

    public function findById(int $id): ?DepartmentFile
    {
        $row = $this->connection->fetchOne(
            'SELECT f.*, d.slug AS department_slug, u.full_name AS uploaded_by_name
             FROM files f
             INNER JOIN departments d ON d.id = f.department_id
             INNER JOIN users u ON u.id = f.uploaded_by_user_id
             WHERE f.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return $row !== null ? DepartmentFile::fromRow($row) : null;
    }

    public function resolveDepartmentId(Department $department): ?int
    {
        $row = $this->connection->fetchOne(
            'SELECT id FROM departments WHERE slug = :slug LIMIT 1',
            ['slug' => $department->value]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function create(
        int $departmentId,
        int $uploadedByUserId,
        string $originalName,
        string $storedName,
        string $mimeType,
        string $extension,
        int $sizeBytes,
        string $checksumSha256,
        ?string $description
    ): DepartmentFile {
        $this->connection->execute(
            'INSERT INTO files (
                department_id,
                uploaded_by_user_id,
                original_name,
                stored_name,
                mime_type,
                file_extension,
                size_bytes,
                checksum_sha256,
                description
            ) VALUES (
                :department_id,
                :uploaded_by_user_id,
                :original_name,
                :stored_name,
                :mime_type,
                :file_extension,
                :size_bytes,
                :checksum_sha256,
                :description
            )',
            [
                'department_id' => $departmentId,
                'uploaded_by_user_id' => $uploadedByUserId,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'mime_type' => $mimeType,
                'file_extension' => $extension,
                'size_bytes' => $sizeBytes,
                'checksum_sha256' => $checksumSha256,
                'description' => $description,
            ]
        );

        $id = $this->connection->lastInsertId();
        $created = $this->findById($id);
        if ($created === null) {
            throw new \RuntimeException('Uploaded file metadata could not be reloaded.');
        }

        return $created;
    }

    public function countByDepartment(Department $department): int
    {
        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS total
             FROM files f
             INNER JOIN departments d ON d.id = f.department_id
             WHERE d.slug = :slug',
            ['slug' => $department->value]
        );

        return (int) ($row['total'] ?? 0);
    }
}
