<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cacheService = app(App\Services\DssCacheService::class);
$data = $cacheService->getRecommendation(30);
$counts = [];
foreach($data as $r) {
    $w = $r['warehouse_id'];
    $counts[$w] = ($counts[$w] ?? 0) + 1;
}
echo json_encode($counts);
