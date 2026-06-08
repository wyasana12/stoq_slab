<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recs = app(App\Services\DssRecommendationService::class)->recommend();
$counts = [];
foreach($recs as $r) {
    $w = $r['warehouse_name'];
    $counts[$w] = ($counts[$w] ?? 0) + 1;
}
echo json_encode($counts);
