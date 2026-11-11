<?php

declare(strict_types=1);

namespace App\Modules\Booking\Controllers;

use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\BookingConflictException;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Catalog\Services\CatalogService;

/**
 * Handles reservation form display, AJAX availability checks, idempotent submissions, and confirmations.
 */
final class BookingController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly BookingService $bookingService,
        private readonly CatalogService $catalogService,
        private readonly Session $session,
        private readonly Config $config
    ) {
    }

    public function createForm(Request $request): Response
    {
        $products = $this->catalogService->getAllActiveForBookingOrSitemap();
        $selectedSlug = trim((string) $request->query('product', ''));

        $selectedProductId = null;
        foreach ($products as $product) {
            if ($product->getSlug() === $selectedSlug) {
                $selectedProductId = $product->getId();
                break;
            }
        }

        // Generate a fresh per-render idempotency key for safe retry protection.
        $idempotencyKey = bin2hex(random_bytes(32));

        $html = $this->views->render('booking.create', [
            'pageTitle' => 'Book Your Stay | ' . $this->config->string('app.name'),
            'metaDescription' => 'Check real-time lodge, camp, and suite availability and reserve securely online.',
            'canonicalPath' => '/bookings',
            'products' => $products,
            'selectedProductId' => $selectedProductId,
            'idempotencyKey' => $idempotencyKey,
        ]);

        return Response::html($html);
    }

    public function checkAvailability(Request $request): Response
    {
        $payload = $request->isPost() ? $request->allPost() : $request->allQuery();
        $quote = $this->bookingService->checkAvailability($payload);

        return Response::json([
            'ok' => true,
            'data' => $quote,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Please sign in to complete your booking.');
        }

        $basePath = $this->config->string('app.base_path', '');

        try {
            $booking = $this->bookingService->createBooking($user->getId(), $request->allPost(), $request);
        } catch (BookingConflictException $conflict) {
            if ($request->expectsJson()) {
                throw $conflict;
            }

            $this->session->flash('flash_error', $conflict->getPublicMessage());
            $this->session->flashOldInput($request->allPost());
            return Response::redirect(app_url('/bookings', $basePath));
        }

        $confirmationUrl = app_url('/bookings/' . $booking->getId() . '/confirmation', $basePath);

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'bookingReference' => $booking->getBookingReference(),
                'redirect' => $confirmationUrl,
            ], 201);
        }

        $this->session->flash('flash_success', 'Reservation confirmed! Reference: ' . $booking->getBookingReference());
        return Response::redirect($confirmationUrl);
    }

    /**
     * @param array<string, string> $params
     */
    public function confirmation(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $bookingId = (int) ($params['id'] ?? 0);
        $booking = $this->bookingService->getConfirmationForUser($bookingId, $user->getId());

        $brandName = $this->config->string('app.name', 'Jaguar Safari');
        $html = $this->views->render('booking.confirmation', [
            'pageTitle' => 'Booking Confirmed ' . $booking->getBookingReference() . ' | ' . $brandName,
            'metaDescription' => 'Reservation confirmation summary and check-in details.',
            'booking' => $booking,
        ]);

        return Response::html($html);
    }
}
