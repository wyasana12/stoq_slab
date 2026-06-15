<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement("ALTER TABLE stock_mutations MODIFY COLUMN reference_type ENUM('DISTRIBUTION','TRANSFER','RESTOCK','RETURN','RECEIVE','DISPOSAL') NOT NULL");
echo "Done\n";
