<?php

declare(strict_types=1);

namespace App\Infrastructure\View;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\CurrentUserProviderInterface;
use App\Core\Session;
use App\Support\Enums\Role;
use eftec\bladeone\BladeOne;

/**
 * Centralized BladeOne template factory and renderer.
 *
 * // [CONFIG:BLADEONE] Single dedicated configuration point for BladeOne.
 * // [SECURITY:XSS] Enforces `{{ }}` HTML escaping by default across all templates.
 * // [SECURITY:CSRF] Injects runtime `@csrf` directive bound to session token.
 *
 * Configured settings:
 *  - Views Path (`VIEW_PATH`): Source `.blade.php` directory resolved from project root.
 *  - Compiled Cache Path (`VIEW_CACHE_PATH`): Non-web compiled PHP cache directory.
 *  - Mode (`VIEW_MODE`):
 *      * `debug` -> BladeOne::MODE_DEBUG (recompiles every request during local dev)
 *      * `auto`  -> BladeOne::MODE_AUTO (recompiles when template timestamp changes)
 *      * `fast`  -> BladeOne::MODE_FAST (zero stat checks; production optimal)
 *  - Custom Directives:
 *      * `@csrf` -> outputs runtime hidden CSRF input using shared `$csrfFieldName` & `$csrfToken`
 *                   (evaluated at render time so compiled cache never leaks a user's token)
 *      * `@can('capability')` / `@endcan` -> delegates to `AccessControlInterface::can()`
 *      * `@role('slug')` / `@endrole` -> checks exact role slug via `AccessControlInterface::hasRole()`
 *      * `@minlevel('slug')` / `@endminlevel` -> checks explicit hierarchical minimum role level
 *  - Shared Globals:
 *      * `appName`, `appUrl`, `basePath`, `currentUser`, `csrfToken`, `csrfFieldName`,
 *        `csrfHeaderName`, `currency`, `contactEmail`, `contactPhone`, `businessLocation`,
 *        `flashSuccess`, `flashError`, `flashWarning`, `validationErrors`, `oldInput`, `accessControl`.
 *  - Escaping Policy:
 *      * Always use `{{ $expr }}` (`htmlspecialchars` ENT_QUOTES UTF-8).
 *      * `{!! $expr !!}` is banned in application views unless explicitly commented and
 *        restricted to server-generated JSON-LD schema blocks or sanitized HTML fragments.
 */
final class ViewFactory
{
    private readonly BladeOne $blade;

    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly AccessControlInterface $accessControl,
        private readonly ?CurrentUserProviderInterface $userProvider = null
    ) {
        // 1. Resolve template directory & compiled cache directory from .env-backed config.
        $viewsPath = $config->resolvePath($config->string('view.path', 'resources/views'));
        $cachePath = $config->resolvePath($config->string('view.cache_path', 'storage/cache/blade'));

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0750, true);
        }

        // 2. Map VIEW_MODE to BladeOne compilation constant.
        $mode = match (strtolower($config->string('view.mode', 'auto'))) {
            'debug' => BladeOne::MODE_DEBUG,
            'fast' => BladeOne::MODE_FAST,
            default => BladeOne::MODE_AUTO,
        };

        $this->blade = new BladeOne($viewsPath, $cachePath, $mode);
        $this->blade->setBaseUrl($config->string('app.url', ''));

        $this->registerDirectives();
    }

    /**
     * Render a BladeOne template with shared globals and per-view data.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $currentUser = $this->userProvider?->currentUser();
        $csrfToken = $this->csrf->token();

        $this->blade->csrf_token = $csrfToken;
        $this->blade->setAuth(
            $currentUser?->getUsername() ?? '',
            $currentUser?->getRole()->slug() ?? '',
            []
        );
        $this->blade->setCanFunction(
            fn (string $capability): bool => $this->accessControl->can($currentUser, $capability)
        );

        $shared = [
            'appName' => $this->config->string('app.name', 'Wildcrest Lodges & Camps'),
            'appUrl' => $this->config->string('app.url', 'http://127.0.0.1:8000'),
            'basePath' => $this->config->string('app.base_path', ''),
            'currency' => $this->config->string('app.booking_currency', 'USD'),
            'contactEmail' => $this->config->string('app.contact_email', ''),
            'contactPhone' => $this->config->string('app.contact_phone', ''),
            'businessLocation' => $this->config->string('app.business_location', ''),
            'currentUser' => $currentUser,
            'accessControl' => $this->accessControl,
            'csrfToken' => $csrfToken,
            'csrfFieldName' => $this->csrf->fieldName(),
            'csrfHeaderName' => $this->csrf->headerName(),
            'flashSuccess' => $this->session->pullFlash('flash_success'),
            'flashError' => $this->session->pullFlash('flash_error'),
            'flashWarning' => $this->session->pullFlash('flash_warning'),
            'validationErrors' => $this->session->pullFlash('validation_errors', []),
            'oldInput' => $this->session->pullOldInput(),
        ];

        foreach ($shared as $key => $value) {
            $this->blade->share($key, $value);
        }

        return $this->blade->run($template, $data);
    }

    private function registerDirectives(): void
    {
        // Custom @csrf directive prints runtime token & field name from shared globals.
        $this->blade->directive('csrf', static function (): string {
            return '<?php echo \'<input type="hidden" name="\' . e($csrfFieldName ?? \'_csrf_token\') '
                . '. \'" value="\' . e($csrfToken ?? \'\') . \'">\'; ?>';
        });

        // Custom @role('slug') ... @endrole directive delegated to AccessControl.
        $this->blade->directive('role', static function (string $expression): string {
            return '<?php $_targetRole = ' . Role::class . '::fromSlug((string) ' . $expression . '); '
                . 'if ($_targetRole !== null && isset($accessControl) '
                . '&& $accessControl->hasRole($currentUser ?? null, $_targetRole)): ?>';
        });
        $this->blade->directive('endrole', static fn (): string => '<?php endif; ?>');

        // Custom @minlevel('slug') ... @endminlevel directive delegated to AccessControl.
        $this->blade->directive('minlevel', static function (string $expression): string {
            return '<?php $_minRole = ' . Role::class . '::fromSlug((string) ' . $expression . '); '
                . 'if ($_minRole !== null && isset($accessControl) '
                . '&& $accessControl->hasMinLevel($currentUser ?? null, $_minRole)): ?>';
        });
        $this->blade->directive('endminlevel', static fn (): string => '<?php endif; ?>');
    }
}
