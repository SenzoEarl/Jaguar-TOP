<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\View\ViewFactory;
use App\Modules\Catalog\Services\CatalogService;
use Monolog\Logger;

/**
 * Serves public marketing and informational pages (Home, About, Contact).
 */
final class PublicPageController
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly CatalogService $catalog,
        private readonly Validator $validator,
        private readonly Session $session,
        private readonly Logger $logger,
        private readonly AuditLogger $auditLogger,
        private readonly Config $config
    ) {
    }

    public function home(Request $request): Response
    {
        $brandName = $this->config->string('app.name', 'Wildcrest Lodges & Camps');
        $featured = $this->catalog->getFeaturedStays(4);

        $html = $this->views->render('public.home', [
            'pageTitle' => $brandName . ' — Wilderness Lodges, Tented Camps & Suites',
            'metaDescription' => 'Book timber lodges, canvas expedition camps, and woodland suites '
                . 'across secluded conservation reserves.',
            'canonicalPath' => '/',
            'featuredProducts' => $featured,
        ]);

        return Response::html($html);
    }

    public function about(Request $request): Response
    {
        $brandName = $this->config->string('app.name', 'Wildcrest Lodges & Camps');

        $html = $this->views->render('public.about', [
            'pageTitle' => 'About Us | ' . $brandName,
            'metaDescription' => 'Discover our conservation hospitality philosophy, '
                . 'solar off-grid properties, and local field stewardship.',
            'canonicalPath' => '/about',
        ]);

        return Response::html($html);
    }

    public function contact(Request $request): Response
    {
        $brandName = $this->config->string('app.name', 'Wildcrest Lodges & Camps');

        $html = $this->views->render('public.contact', [
            'pageTitle' => 'Contact Concierge | ' . $brandName,
            'metaDescription' => 'Reach the Wildcrest concierge desk for private group charters, '
                . 'custom itineraries, and lodge inquiries.',
            'canonicalPath' => '/contact',
        ]);

        return Response::html($html);
    }

    public function submitContact(Request $request): Response
    {
        $data = $this->validator->validate($request->allPost(), [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190',
            'subject' => 'required|string|min:3|max:150',
            'message' => 'required|string|min:10|max:2000',
        ]);

        // // [FUTURE:NOTIFICATIONS] Queue outbound concierge notification email here.
        $this->logger->info('Public contact inquiry submitted.', [
            'request_id' => $request->requestId(),
            'sender_email' => $data['email'],
            'subject' => $data['subject'],
        ]);

        $this->auditLogger->record(
            'public.contact.submitted',
            null,
            'contact_inquiry',
            null,
            $request,
            ['subject' => $data['subject']]
        );

        $this->session->flash(
            'flash_success',
            'Thank you for your inquiry. Our reservations concierge will respond within one business day.'
        );

        return Response::redirect(
            app_url('/contact', $this->config->string('app.base_path', ''))
        );
    }
}
