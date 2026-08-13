<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$batch = App\Models\Batch::where('product_id', '01kv7dd4we6jvqyz690y82vksm')->where('receiving_id', '01kv7dd5szp61cmqghh3pk9fcx')->first();
echo "Batch ID: " . $batch->id . "\n";
echo "Initial Quantity: " . $batch->initial_quantity . "\n";
echo "Current Quantity: " . $batch->current_quantity . "\n";

$ri = DB::table('product_receiving_items')->where('receiving_id', '01kv7dd5szp61cmqghh3pk9fcx')->where('product_id', '01kv7dd4we6jvqyz690y82vksm')->first();
echo "Receiving Item Quantity Accepted: " . $ri->quantity_accepted . "\n";
