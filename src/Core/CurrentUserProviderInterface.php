<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Resolves the currently authenticated user for a request lifecycle without
 * coupling Core or ViewFactory to the Auth module implementation.
 */
interface CurrentUserProviderInterface
{
    public function currentUser(): ?AuthenticatedUserInterface;
}
