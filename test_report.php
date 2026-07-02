<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/superadmin/reports/export', 'GET', [
    'template' => 'stock_current',
    'format' => 'xlsx',
    'date_from' => '2026-06-26',
    'date_to' => '2026-07-02'
]);
app()->instance('request', $request);

$formRequest = app(App\Http\Requests\Reports\ReportExportRequest::class);
$formRequest->setContainer(app());
$formRequest->setRedirector(app(\Illuminate\Routing\Redirector::class));
$formRequest->validateResolved();

$controller = app(App\Http\Controllers\SuperAdmin\ReportExportController::class);
$response = $controller->export($formRequest);
echo "Response class: " . get_class($response) . "\n";
