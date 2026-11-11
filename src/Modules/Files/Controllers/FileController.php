<?php

declare(strict_types=1);

namespace App\Modules\Files\Controllers;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Files\Services\FileService;
use App\Support\Enums\Department;
use App\Support\Navigation\NavigationRegistry;

/**
 * Serves the authenticated staff document vault and streams protected files.
 */
final class FileController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly FileService $fileService,
        private readonly AccessControlInterface $accessControl,
        private readonly NavigationRegistry $navigation,
        private readonly Session $session,
        private readonly Config $config
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $targetDepartment = $user->getDepartment() ?? Department::GeneralAdmin;
        $basePath = $this->config->string('app.base_path', '');

        return Response::redirect(app_url('/staff/files/' . $targetDepartment->value, $basePath));
    }

    /**
     * @param array<string, string> $params
     */
    public function departmentIndex(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $department = Department::tryFrom((string) ($params['department'] ?? ''));
        if ($department === null) {
            throw new NotFoundException('Department vault not found.');
        }

        $files = $this->fileService->listDepartmentFiles($user, $department);
        $isReadOnly = $this->accessControl->isReadOnlyDepartmentView($user, $department);

        $visibleDepartments = [];
        foreach (Department::cases() as $candidate) {
            if ($this->accessControl->canViewDepartment($user, $candidate)) {
                $visibleDepartments[] = [
                    'slug' => $candidate->value,
                    'label' => $candidate->label(),
                    'active' => $candidate === $department,
                    'read_only' => $this->accessControl->isReadOnlyDepartmentView($user, $candidate),
                ];
            }
        }

        $html = $this->views->render('staff.files.index', [
            'pageTitle' => $department->label() . ' Files | Staff Portal',
            'department' => $department,
            'isReadOnly' => $isReadOnly,
            'files' => $files,
            'visibleDepartments' => $visibleDepartments,
            'sidebarGroups' => $this->navigation->forUser($user, $request->path()),
            'maxUploadBytes' => $this->config->int('uploads.max_bytes', 5242880),
            'allowedExtensions' => $this->config->array('uploads.allowed_extensions', []),
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string, string> $params
     */
    public function upload(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $department = Department::tryFrom((string) ($params['department'] ?? ''));
        if ($department === null) {
            throw new NotFoundException('Department not found.');
        }

        $uploadedFile = $request->file('document');
        $description = $request->post('description');
        $record = $this->fileService->upload(
            $user,
            $department,
            $uploadedFile,
            is_string($description) ? $description : null,
            $request
        );

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'file' => [
                    'id' => $record->getId(),
                    'originalName' => $record->getOriginalName(),
                    'sizeFormatted' => $record->formattedSize(),
                    'mimeType' => $record->getMimeType(),
                ],
            ], 201);
        }

        $this->session->flash('flash_success', sprintf('Uploaded "%s" securely.', $record->getOriginalName()));
        return Response::redirect(
            app_url('/staff/files/' . $department->value, $this->config->string('app.base_path', ''))
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $fileId = (int) ($params['id'] ?? 0);
        $resolved = $this->fileService->prepareDownload($user, $fileId, $request);
        $file = $resolved['file'];

        return Response::file(
            $resolved['absolute_path'],
            $file->getOriginalName(),
            $file->getMimeType(),
            $file->getSizeBytes()
        );
    }
}
