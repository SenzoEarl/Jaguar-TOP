<?php

/**
 * Front Controller — ONLY PHP entry point exposed under `public/`.
 */

declare(strict_types=1);

use App\Core\Application;
use App\Core\Request;
use App\Modules\Auth\AuthModule;
use App\Modules\Booking\BookingModule;
use App\Modules\Catalog\CatalogModule;
use App\Modules\Files\FilesModule;
use App\Modules\Staff\StaffModule;
use App\Modules\Tasks\TasksModule;

$rootPath = dirname(__DIR__);
require_once $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$application = new Application($rootPath, [
    new AuthModule(),
    new CatalogModule(),
    new BookingModule(),
    new TasksModule(),
    new FilesModule(),
    new StaffModule(),
    // [FUTURE:SALES-MODULE] Register new SalesModule() here.
    // [FUTURE:HR-MODULE] Register new HrModule() here.
    // [FUTURE:MANAGER-REPORTING] Register new ReportingModule() here.
], true);

$request = Request::fromGlobals($application->config()->string('app.base_path', ''));
$response = $application->handle($request);
$response->send();
