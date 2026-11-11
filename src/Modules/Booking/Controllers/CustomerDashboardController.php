<?php

declare(strict_types=1);

namespace App\Modules\Booking\Controllers;

use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Booking\Services\BookingService;

/**
 * Customer portal controller displaying profile details and personal booking history.
 */
final class CustomerDashboardController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly BookingService $bookingService,
        private readonly Config $config
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Please sign in to view your dashboard.');
        }

        $bookings = $this->bookingService->getUserHistory($user->getId());

        $html = $this->views->render('customer.dashboard', [
            'pageTitle' => 'My Bookings & Account | ' . $this->config->string('app.name'),
            'metaDescription' => 'View upcoming stays, past reservations, and account details.',
            'bookings' => $bookings,
        ]);

        return Response::html($html);
    }
}
