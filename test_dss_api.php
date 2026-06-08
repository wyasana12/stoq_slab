<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin1@example.com')->first();
$request = Illuminate\Http\Request::create('/api/dss/recommendations', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });
$controller = app(App\Http\Controllers\DssController::class);
$response = $controller->recommendations($request);
echo json_encode($response->getData(true));
