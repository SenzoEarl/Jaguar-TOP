<?php

declare(strict_types=1);

namespace App\Modules\Files\Services;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Request;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FileUploadException;
use App\Exceptions\NotFoundException;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\Storage\DepartmentFileStorage;
use App\Modules\Files\Models\DepartmentFile;
use App\Modules\Files\Repositories\FileRepository;
use App\Support\Enums\Department;

/**
 * Coordinates RBAC-protected department file uploads, listings, and authenticated downloads.
 */
final class FileService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FileRepository $files,
        private readonly DepartmentFileStorage $storage,
        private readonly AccessControlInterface $accessControl,
        private readonly Validator $validator,
        private readonly AuditLogger $auditLogger
    ) {
    }

    /**
     * @return list<DepartmentFile>
     */
    public function listDepartmentFiles(AuthenticatedUserInterface $actor, Department $department): array
    {
        if (!$this->accessControl->canViewDepartment($actor, $department)) {
            throw new AuthorizationException('You do not have permission to view files in this department.');
        }

        return $this->files->findByDepartment($department);
    }

    /**
     * @param array<string, mixed>|null $uploadedFile
     */
    public function upload(
        AuthenticatedUserInterface $actor,
        Department $department,
        ?array $uploadedFile,
        ?string $description,
        Request $request
    ): DepartmentFile {
        if (!$this->accessControl->canWriteDepartment($actor, $department)) {
            throw new AuthorizationException(
                'Uploads are blocked because this department is read-only for your account.'
            );
        }

        if ($uploadedFile === null) {
            throw new FileUploadException('Please choose a document to upload.', 422);
        }

        $cleanInput = $this->validator->validate(['description' => $description], [
            'description' => 'nullable|string|max:255',
        ]);
        $cleanDescription = isset($cleanInput['description']) && $cleanInput['description'] !== ''
            ? (string) $cleanInput['description']
            : null;

        $departmentId = $this->files->resolveDepartmentId($department);
        if ($departmentId === null) {
            throw new NotFoundException('Target department was not found.');
        }

        $stored = $this->storage->store($department, $uploadedFile);

        /** @var DepartmentFile $record */
        $record = $this->connection->transaction(function () use (
            $departmentId,
            $actor,
            $stored,
            $cleanDescription
        ): DepartmentFile {
            return $this->files->create(
                $departmentId,
                $actor->getId(),
                $stored['original_name'],
                $stored['stored_name'],
                $stored['mime_type'],
                $stored['extension'],
                $stored['size_bytes'],
                $stored['checksum_sha256'],
                $cleanDescription
            );
        });

        $this->auditLogger->record(
            'file.uploaded',
            $actor->getId(),
            'files',
            $record->getId(),
            $request,
            [
                'department' => $department->value,
                'original_name' => $record->getOriginalName(),
                'size_bytes' => $record->getSizeBytes(),
                'mime_type' => $record->getMimeType(),
            ]
        );

        return $record;
    }

    /**
     * Authorize and resolve an authenticated download from non-web department storage.
     *
     * @return array{file: DepartmentFile, absolute_path: string}
     */
    public function prepareDownload(
        AuthenticatedUserInterface $actor,
        int $fileId,
        Request $request
    ): array {
        $file = $this->files->findById($fileId);
        if ($file === null) {
            throw new NotFoundException('Department file not found.');
        }

        if (!$this->accessControl->canViewDepartment($actor, $file->getDepartment())) {
            throw new AuthorizationException('You are not authorized to download files for this department.');
        }

        $absolutePath = $this->storage->resolveForDownload($file->getDepartment(), $file->getStoredName());

        $this->auditLogger->record(
            'file.downloaded',
            $actor->getId(),
            'files',
            $file->getId(),
            $request,
            ['department' => $file->getDepartment()->value, 'original_name' => $file->getOriginalName()]
        );

        return [
            'file' => $file,
            'absolute_path' => $absolutePath,
        ];
    }
}
