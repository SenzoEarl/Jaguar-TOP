<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\AccessControlInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\AuthenticationException;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Auth\Services\AuthService;

/**
 * Handles guest login, customer registration, and authenticated logout flows.
 */
final class AuthController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly AuthService $auth,
        private readonly AccessControlInterface $accessControl,
        private readonly Validator $validator,
        private readonly Session $session,
        private readonly Config $config
    ) {
    }

    public function showLogin(Request $request): Response
    {
        $html = $this->views->render('auth.login', [
            'pageTitle' => 'Sign In | ' . $this->config->string('app.name'),
            'metaDescription' => 'Sign in to manage your Wildcrest Lodges reservations or access the staff portal.',
            'canonicalPath' => '/account/login',
        ]);

        return Response::html($html);
    }

    public function login(Request $request): Response
    {
        $data = $this->validator->validate($request->allPost(), [
            'identifier' => 'required|string|min:3|max:190',
            'password' => 'required|string|min:1|max:200',
        ]);

        $basePath = $this->config->string('app.base_path', '');

        try {
            $user = $this->auth->attemptLogin(
                (string) $data['identifier'],
                (string) $data['password'],
                $request
            );
        } catch (AuthenticationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            $this->session->flash('flash_error', $exception->getPublicMessage());
            $this->session->flashOldInput(['identifier' => (string) $data['identifier']]);
            return Response::redirect(app_url('/account/login', $basePath));
        }

        $intended = $this->session->pullFlash('intended_url');
        $landing = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')
            ? $intended
            : $this->accessControl->defaultLandingPath($user);

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'redirect' => app_url($landing, $basePath),
            ]);
        }

        $this->session->flash('flash_success', sprintf('Welcome back, %s.', $user->getFullName()));
        return Response::redirect(app_url($landing, $basePath));
    }

    public function showRegister(Request $request): Response
    {
        $html = $this->views->render('auth.register', [
            'pageTitle' => 'Create Account | ' . $this->config->string('app.name'),
            'metaDescription' => 'Register a guest account to book Wildcrest lodges, tented camps, and suites.',
            'canonicalPath' => '/account/register',
        ]);

        return Response::html($html);
    }

    public function register(Request $request): Response
    {
        $data = $this->validator->validate($request->allPost(), [
            'username' => 'required|username',
            'email' => 'required|email|max:190',
            'full_name' => 'required|string|min:2|max:120',
            'password' => 'required|string|min:10|max:128',
            'password_confirmation' => 'required|same:password',
        ]);

        $user = $this->auth->registerCustomer(
            (string) $data['username'],
            (string) $data['email'],
            (string) $data['full_name'],
            (string) $data['password'],
            $request
        );

        $basePath = $this->config->string('app.base_path', '');
        $this->session->flash('flash_success', 'Account created. Welcome to Wildcrest Lodges & Camps!');

        return Response::redirect(app_url($this->accessControl->defaultLandingPath($user), $basePath));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request);
        $this->session->flash('flash_success', 'You have been signed out securely.');

        return Response::redirect(
            app_url('/account/login', $this->config->string('app.base_path', ''))
        );
    }
}
