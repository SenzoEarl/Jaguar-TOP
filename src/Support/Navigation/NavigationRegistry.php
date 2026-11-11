<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Support\Enums\Department;

/**
 * Builds role- and department-aware sidebar navigation from `config/navigation.php`.
 *
 * Adding a new staff section or department requires editing only the config map;
 * visibility and read-only badges are automatically enforced via AccessControl.
 */
final class NavigationRegistry
{
    public function __construct(
        private readonly Config $config,
        private readonly AccessControlInterface $accessControl
    ) {
    }

    /**
     * Build grouped navigation items visible to `$user` with active/read-only state.
     *
     * @return list<array{
     *     heading: string,
     *     items: list<array{
     *         key: string,
     *         label: string,
     *         url: string,
     *         path: string,
     *         badge_text: string,
     *         active: bool,
     *         read_only: bool
     *     }>
     * }>
     */
    public function forUser(?AuthenticatedUserInterface $user, string $currentPath): array
    {
        if ($user === null || !$this->accessControl->canAccessStaffPortal($user)) {
            return [];
        }

        $basePath = $this->config->string('app.base_path', '');
        $groups = $this->config->array('navigation.groups', []);
        $result = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $visibleItems = [];
            foreach ($group['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $readOnly = false;
                if (isset($item['department']) && is_string($item['department'])) {
                    $department = Department::tryFrom($item['department']);
                    if ($department === null || !$this->accessControl->canViewDepartment($user, $department)) {
                        continue;
                    }
                    $readOnly = $this->accessControl->isReadOnlyDepartmentView($user, $department);
                } elseif (isset($item['capability']) && is_string($item['capability'])) {
                    if (!$this->accessControl->can($user, $item['capability'])) {
                        continue;
                    }
                } else {
                    continue;
                }

                $path = (string) ($item['path'] ?? '/staff/dashboard');
                $isActive = $currentPath === $path || str_starts_with($currentPath, $path . '/');

                $visibleItems[] = [
                    'key' => (string) ($item['key'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                    'url' => app_url($path, $basePath),
                    'path' => $path,
                    'badge_text' => (string) ($item['badge_text'] ?? ''),
                    'active' => $isActive,
                    'read_only' => $readOnly,
                ];
            }

            if ($visibleItems !== []) {
                $result[] = [
                    'heading' => (string) ($group['heading'] ?? ''),
                    'items' => $visibleItems,
                ];
            }
        }

        return $result;
    }
}
