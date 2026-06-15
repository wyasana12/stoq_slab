<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockMutations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StockMutationSeeder extends Seeder
{
    /**
     * Kategori pergerakan stok untuk simulasi DSS yang realistis.
     * Setiap kategori menghasilkan pola data berbeda agar algoritma
     * klasifikasi (Fast/Slow/Dead Moving) dapat diuji secara menyeluruh.
     */
    private array $movementProfiles = [

        // --- FAST MOVING ---
        // Barang keluar sangat sering, volume besar, konsisten setiap hari
        'fast_consistent' => [
            'transactions'   => [7, 10],   // jumlah transaksi dalam 30 hari
            'qty_range'      => [10, 25],
            'day_spread'     => 30,
            'gap_days'       => [1, 3],      // jarak antar transaksi (hari)
            'label'          => 'Fast Moving - Konsisten',
        ],

        // Barang fast moving tapi pola mingguan (ramai Senin–Rabu, sepi akhir pekan)
        'fast_weekly_peak' => [
            'transactions'   => [5, 8],
            'qty_range'      => [8, 20],
            'day_spread'     => 30,
            'gap_days'       => [1, 5],
            'label'          => 'Fast Moving - Puncak Mingguan',
            'weekday_bias'   => true,        // lebih banyak transaksi di weekday
        ],

        // --- SLOW MOVING ---
        // Keluar jarang, volume kecil, tidak teratur
        'slow_irregular' => [
            'transactions'   => [1, 3],
            'qty_range'      => [1, 4],
            'day_spread'     => 30,
            'gap_days'       => [10, 20],
            'label'          => 'Slow Moving - Tidak Teratur',
        ],

        // Slow moving tapi ada satu lonjakan besar di tengah periode
        'slow_with_spike' => [
            'transactions'   => [1, 2],
            'qty_range'      => [1, 3],
            'day_spread'     => 30,
            'gap_days'       => [15, 30],
            'label'          => 'Slow Moving - Ada Lonjakan',
            'has_spike'      => true,        // akan ditambah 1 transaksi besar di tengah
            'spike_qty'      => [20, 40],
        ],

        // --- DEAD MOVING ---
        // Hampir tidak bergerak selama 30 hari, sangat jarang keluar
        'dead_stock' => [
            'transactions'   => [0, 1],
            'qty_range'      => [1, 2],
            'day_spread'     => 30,
            'gap_days'       => [25, 29],
            'label'          => 'Dead Stock',
        ],

        // --- MUSIMAN / SEASONAL ---
        // Sibuk di awal bulan (distribusi gaji/PO bulanan), sepi di akhir
        'seasonal_monthly' => [
            'transactions'   => [3, 5],
            'qty_range'      => [5, 15],
            'day_spread'     => 30,
            'gap_days'       => [1, 4],
            'label'          => 'Seasonal - Awal Bulan',
            'monthly_bias'   => 'early',     // transaksi menumpuk di tanggal 1–10
        ],

        // Sibuk di akhir bulan (laporan, closing stock)
        'seasonal_end_month' => [
            'transactions'   => [3, 5],
            'qty_range'      => [5, 18],
            'day_spread'     => 30,
            'gap_days'       => [1, 4],
            'label'          => 'Seasonal - Akhir Bulan',
            'monthly_bias'   => 'late',      // transaksi menumpuk di tanggal 20–31
        ],

        // --- TRENDING ---
        // Volume keluar meningkat dari waktu ke waktu (trending up = growing demand)
        'trending_up' => [
            'transactions'   => [4, 7],
            'qty_range'      => [2, 8],
            'day_spread'     => 30,
            'gap_days'       => [3, 7],
            'label'          => 'Trending Naik',
            'trend'          => 'up',        // qty meningkat seiring waktu
        ],

        // Volume keluar menurun dari waktu ke waktu (produk mendekati obsolete)
        'trending_down' => [
            'transactions'   => [3, 5],
            'qty_range'      => [2, 8],
            'day_spread'     => 30,
            'gap_days'       => [3, 7],
            'label'          => 'Trending Turun',
            'trend'          => 'down',      // qty menurun seiring waktu
        ],
    ];

    /**
     * Distribusi berapa batch yang menggunakan tiap profil.
     * Key = nama profil, value = jumlah batch yang dialokasikan.
     */
    private array $profileAllocation = [
        'fast_consistent'    => 5,
        'fast_weekly_peak'   => 4,
        'slow_irregular'     => 6,
        'slow_with_spike'    => 3,
        'dead_stock'         => 4,
        'seasonal_monthly'   => 3,
        'seasonal_end_month' => 3,
        'trending_up'        => 3,
        'trending_down'      => 3,
    ];

    public function run(): void
    {
        $totalNeeded = array_sum($this->profileAllocation);

        // Ambil lebih banyak batch agar cukup untuk semua profil
        $batches = Batch::with(['warehouse', 'product'])
            ->where('current_quantity', '>', 0)
            ->inRandomOrder()
            ->take($totalNeeded + 10) // ambil lebih sebagai cadangan
            ->get();

        if ($batches->isEmpty()) {
            $this->command->warn('Tidak ada batch ditemukan. Pastikan BatchSeeder sudah dijalankan.');
            return;
        }

        $batchIndex = 0;
        $totalCreated = 0;

        foreach ($this->profileAllocation as $profileName => $count) {
            $profile = $this->movementProfiles[$profileName];

            for ($i = 0; $i < $count; $i++) {
                if ($batchIndex >= $batches->count()) {
                    // Jika batch habis, mulai ulang dari awal (batch dipakai ulang dengan profil beda)
                    $batchIndex = 0;
                }

                $batch = $batches[$batchIndex++];
                $created = $this->seedBatchMutations($batch, $profile, $profileName);
                $totalCreated += $created;

                $this->command->line(
                    "  [OK] Batch #{$batch->id} → {$profile['label']} ({$created} transaksi)"
                );
            }
        }

        // Tambahan: mutasi MASUK (IN) untuk beberapa batch agar saldo realistis
        $this->seedInboundMutations($batches->take(10), $totalCreated);

        // ─── SKENARIO DSS (CONTROLLED SEED) ───
        $this->seedDssScenarios();

        $this->command->info("Selesai! Total {$totalCreated} mutasi berhasil dibuat.");
    }

    /**
     * Buat mutasi keluar (OUT) untuk satu batch sesuai profil.
     */
    private function seedBatchMutations(Batch $batch, array $profile, string $profileName): int
{
    $numTransactions = rand(...$profile['transactions']);
    if ($numTransactions === 0) {
        return 0;
    }

    $created = 0;
    $runningQty = $batch->current_quantity;
    
    // ✅ Tambahkan guard: kalau stok awal 0, skip batch ini
    if ($runningQty <= 0) {
        return 0;
    }

    $daySpread = $profile['day_spread'];
    $dates = $this->generateTransactionDates($profile, $numTransactions, $daySpread);
    sort($dates);

    foreach ($dates as $index => $daysAgo) {
        // ✅ Tambahkan guard di sini juga
        if ($runningQty <= 0) {
            break;
        }

        $baseQty = rand(...$profile['qty_range']);

        if (isset($profile['trend'])) {
            $baseQty = $this->applyTrend($baseQty, $index, $numTransactions, $profile['trend']);
        }

        // ✅ Pastikan qtyOut minimal 1 dan tidak melebihi runningQty
        $qtyOut = min($baseQty, $runningQty);
        if ($qtyOut <= 0) {
            break;
        }

        $date = $this->buildDateTime($daysAgo);
        $refType = $this->pickReferenceType($profileName);

        $mutation = new StockMutations([
            'warehouse_id'    => $batch->warehouse_id,
            'batch_id'        => $batch->id,
            'change_quantity' => -$qtyOut,
            'before_quantity' => $runningQty,
            'after_quantity'  => $runningQty - $qtyOut,  // ✅ Dijamin >= 0
            'reference_type'  => $refType,
            'reference_id'    => (string) Str::ulid(),
            'notes'           => "DSS Seed [{$profile['label']}]: Keluar {$daysAgo} hari lalu",
            'status'          => 'SUCCESS',
        ]);

        $mutation->created_at = $date;
        $mutation->updated_at = $date;
        $mutation->save();

        $runningQty -= $qtyOut;
        $created++;
    }

    if (!empty($profile['has_spike'])) {
        $spikeCreated = $this->seedSpikeTransaction($batch, $profile, $runningQty);
        $created += $spikeCreated;
    }

    // UPDATE BATCH QUANTITY IN DATABASE
    $batch->update(['current_quantity' => $runningQty]);

    return $created;
}

    /**
     * Generate array daysAgo (berapa hari lalu) untuk setiap transaksi,
     * mempertimbangkan bias mingguan / bulanan jika ada.
     */
    private function generateTransactionDates(array $profile, int $count, int $daySpread): array
    {
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            if (!empty($profile['weekday_bias'])) {
                // Bias ke hari kerja: coba beberapa kali hingga dapat Senin–Jumat
                $daysAgo = $this->randomWeekdayDaysAgo($daySpread);
            } elseif (!empty($profile['monthly_bias'])) {
                $daysAgo = $this->randomMonthlyBiasDaysAgo($daySpread, $profile['monthly_bias']);
            } else {
                $daysAgo = rand(1, $daySpread);
            }

            $dates[] = $daysAgo;
        }

        return $dates;
    }

    /**
     * Kembalikan daysAgo yang jatuh pada hari kerja (Senin–Jumat).
     */
    private function randomWeekdayDaysAgo(int $maxDays, int $maxTries = 10): int
    {
        for ($attempt = 0; $attempt < $maxTries; $attempt++) {
            $d = rand(1, $maxDays);
            $dow = now()->subDays($d)->dayOfWeek; // 0 = Minggu, 6 = Sabtu
            if ($dow >= 1 && $dow <= 5) {
                return $d;
            }
        }
        return rand(1, $maxDays); // fallback jika tidak ketemu weekday
    }

    /**
     * Kembalikan daysAgo dengan bias ke awal bulan (tanggal 1–10)
     * atau akhir bulan (tanggal 20–31).
     */
    private function randomMonthlyBiasDaysAgo(int $maxDays, string $bias): int
    {
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $d = rand(1, $maxDays);
            $dom = now()->subDays($d)->day; // day-of-month

            if ($bias === 'early' && $dom <= 10) {
                return $d;
            }
            if ($bias === 'late' && $dom >= 20) {
                return $d;
            }
        }
        return rand(1, $maxDays); // fallback
    }

    /**
     * Terapkan efek trend pada qty berdasarkan posisi transaksi dalam urutan.
     * Transaksi awal (index kecil = lama) → qty kecil jika trend naik.
     */
    private function applyTrend(int $baseQty, int $index, int $total, string $trend): int
    {
        if ($total <= 1) {
            return $baseQty;
        }

        // Faktor 0.5 → 1.5 secara linear
        $ratio = $index / ($total - 1); // 0.0 (pertama) → 1.0 (terakhir)

        $multiplier = match ($trend) {
            'up'   => 0.5 + $ratio,          // makin lama makin besar
            'down' => 1.5 - $ratio,          // makin lama makin kecil
            default => 1.0,
        };

        return max(1, (int) round($baseQty * $multiplier));
    }

    /**
     * Tambahkan satu transaksi lonjakan besar di pertengahan periode.
     */
    private function seedSpikeTransaction(Batch $batch, array $profile, int &$currentQty): int
    {
        $spikeQty = rand(...($profile['spike_qty'] ?? [10, 20]));
        $spikeQty = min($spikeQty, max(1, $currentQty - 1));

        if ($spikeQty <= 0) {
            return 0;
        }

        $daysAgo = rand(10, 20); // spike di tengah-tengah periode
        $date = $this->buildDateTime($daysAgo);

        $mutation = new StockMutations([
            'warehouse_id'    => $batch->warehouse_id,
            'batch_id'        => $batch->id,
            'change_quantity' => -$spikeQty,
            'before_quantity' => $currentQty,
            'after_quantity'  => $currentQty - $spikeQty,
            'reference_type'  => 'DISTRIBUTION',
            'reference_id'    => (string) Str::ulid(),
            'notes'           => "DSS Seed [Spike]: Lonjakan permintaan {$daysAgo} hari lalu",
            'status'          => 'SUCCESS',
        ]);

        $mutation->created_at = $date;
        $mutation->updated_at = $date;
        $mutation->save();

        $currentQty -= $spikeQty;

        return 1;
    }

    /**
     * Seed beberapa mutasi MASUK (IN / restock) agar saldo historis lebih wajar.
     */
    private function seedInboundMutations($batches, int &$totalCreated): void
{
    foreach ($batches as $batch) {
        $numInbound = rand(1, 3);

        for ($i = 0; $i < $numInbound; $i++) {
            $daysAgo = rand(20, 30);
            $qtyIn   = rand(20, 80);
            $date    = $this->buildDateTime($daysAgo);

            $mutation = new StockMutations([
                'warehouse_id'    => $batch->warehouse_id,
                'batch_id'        => $batch->id,
                'change_quantity' => $qtyIn,
                'before_quantity' => $batch->current_quantity,
                'after_quantity'  => $batch->current_quantity + $qtyIn,
                'reference_type'  => rand(0, 1) ? 'RECEIVE' : 'RESTOCK', // ✅ fix di sini
                'reference_id'    => (string) Str::ulid(),
                'notes'           => "DSS Seed [Inbound]: Restock {$daysAgo} hari lalu",
                'status'          => 'SUCCESS',
            ]);

            $mutation->created_at = $date;
            $mutation->updated_at = $date;
            $mutation->save();

            $batch->update(['current_quantity' => $batch->current_quantity + $qtyIn]);

            $totalCreated++;
        }
    }
}

    /**
     * Pilih reference_type berdasarkan profil pergerakan.
     */
    private function pickReferenceType(string $profileName): string
{
    return match (true) {
        str_contains($profileName, 'fast')     => rand(0, 2) === 0 ? 'TRANSFER' : 'DISTRIBUTION',
        str_contains($profileName, 'slow')     => rand(0, 1) ? 'DISTRIBUTION' : 'RETURN',
        str_contains($profileName, 'dead')     => 'DISTRIBUTION',
        str_contains($profileName, 'seasonal') => 'DISTRIBUTION',
        default                                => rand(0, 1) ? 'TRANSFER' : 'DISTRIBUTION',
    };
}

    /**
     * Bangun Carbon datetime N hari lalu pada jam operasional (08:00–17:00).
     */
    private function buildDateTime(int $daysAgo): \Illuminate\Support\Carbon
    {
        return now()
            ->subDays($daysAgo)
            ->startOfDay()
            ->addHours(rand(8, 17))
            ->addMinutes(rand(0, 59));
    }

    /**
     * Skenario Terkontrol agar fitur DSS 100% muncul sesuai kebutuhan testing (2 Transfer In, 2 Transfer Out, 2 Restock per gudang).
     */
    private function seedDssScenarios(): void
    {
        $this->command->info('Membuat Skenario Khusus DSS (Transfer In/Out, Restock) untuk testing...');

        $warehouses = \App\Models\Warehouse::all();
        if ($warehouses->count() < 2) {
            $this->command->warn('Butuh minimal 2 gudang untuk membuat skenario transfer.');
            return;
        }

        // Gunakan kategori dan supplier yang ada
        $scenarioCategory = \App\Models\Category::firstOrCreate(['name' => 'DSS Scenario']);
        $scenarioUnit = \App\Models\Unit::first();
        $scenarioSupplier = \App\Models\Supplier::first();

        $foodNamesRestock = ['Indomie Goreng Spesial', 'Kopi Susu Aren', 'Teh Pucuk Harum 350ml', 'Roti Sisir Mentega', 'Beng-Beng Maxx', 'Oreo Supreme', 'Chitato Sapi Panggang 68g', 'Aqua Botol 600ml', 'Pocari Sweat 500ml', 'SilverQueen Cashew 62g'];
        $foodNamesTransfer = ['Ultra Milk Coklat 1L', 'Mie Sedaap Kari Spesial', 'Kusuka Keripik Singkong Original', 'Tolak Angin Cair', 'Kopiko Candy', 'Bear Brand Susu Steril', 'Yakult 50ml', 'Sari Roti Tawar', 'Fruit Tea Apel 500ml', 'Good Time Chocochips'];

        foreach ($warehouses as $index => $wh) {
            $nextWh = $warehouses[($index + 1) % $warehouses->count()];

            // 1. Create 2 RESTOCK for $wh
            for ($i = 1; $i <= 2; $i++) {
                $foodName = $foodNamesRestock[array_rand($foodNamesRestock)];
                $prod = \App\Models\Product::create([
                    'sku' => 'DSS-RES-' . $wh->warehouse_code . '-' . $i . '-' . time(),
                    'name' => $foodName,
                    'category_id' => $scenarioCategory->id,
                    'unit_id' => $scenarioUnit->id,
                ]);

                // Create Fast Moving batch in $wh
                $this->createFastMovingBatch($prod, $wh, 'DSS-BAT-RES-');
            }

            // 2. Create 2 TRANSFER_IN for $wh (which acts as TRANSFER_OUT for $nextWh)
            for ($i = 1; $i <= 2; $i++) {
                $foodName = $foodNamesTransfer[array_rand($foodNamesTransfer)];
                $prod = \App\Models\Product::create([
                    'sku' => 'DSS-TRF-' . $wh->warehouse_code . '-' . $i . '-' . time(),
                    'name' => $foodName,
                    'category_id' => $scenarioCategory->id,
                    'unit_id' => $scenarioUnit->id,
                ]);

                // Fast Moving in $wh (Need Transfer In)
                $this->createFastMovingBatch($prod, $wh, 'DSS-BAT-IN-');

                // Slow Moving in $nextWh (Has excess, will be Transfer Out)
                $this->createSlowMovingBatch($prod, $nextWh, 'DSS-BAT-OUT-');
            }
        }
    }

    private function createFastMovingBatch($product, $warehouse, $prefix) {
        $batch = Batch::create([
            'id' => (string) Str::ulid(),
            'batch_code' => $prefix . $warehouse->warehouse_code . '-' . rand(1000, 9999),
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'receiving_id' => \App\Models\ProductReceiving::first()->id ?? (string) Str::ulid(),
            'initial_quantity' => 50,
            'current_quantity' => 2, // Sisa sangat kecil
            'production_date' => now()->subMonths(2),
            'expired_date' => now()->addYear(),
            'price' => 15000,
            'condition' => 'GOOD',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            // 5 mutasi x 5 qty = 25 unit dalam 30 hari (velocity tinggi)
            StockMutations::create([
                'id' => (string) Str::ulid(),
                'warehouse_id' => $warehouse->id,
                'batch_id' => $batch->id,
                'change_quantity' => -5,
                'before_quantity' => 7,
                'after_quantity' => 2,
                'reference_type' => 'DISTRIBUTION',
                'reference_id' => (string) Str::ulid(),
                'notes' => 'DSS SCENARIO: FAST MOVING',
                'status' => 'SUCCESS',
                'created_at' => now()->subDays(rand(1, 10)),
                'updated_at' => now()->subDays(rand(1, 10)),
            ]);
        }
        return $batch;
    }

    private function createSlowMovingBatch($product, $warehouse, $prefix) {
        $batch = Batch::create([
            'id' => (string) Str::ulid(),
            'batch_code' => $prefix . $warehouse->warehouse_code . '-' . rand(1000, 9999),
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'receiving_id' => \App\Models\ProductReceiving::first()->id ?? (string) Str::ulid(),
            'initial_quantity' => 300,
            'current_quantity' => 295, // Sisa sangat besar
            'production_date' => now()->subMonths(2),
            'expired_date' => now()->addYear(),
            'price' => 15000,
            'condition' => 'GOOD',
        ]);

        StockMutations::create([
            'id' => (string) Str::ulid(),
            'warehouse_id' => $warehouse->id,
            'batch_id' => $batch->id,
            'change_quantity' => -5,
            'before_quantity' => 300,
            'after_quantity' => 295,
            'reference_type' => 'DISTRIBUTION',
            'reference_id' => (string) Str::ulid(),
            'notes' => 'DSS SCENARIO: SLOW MOVING',
            'status' => 'SUCCESS',
            'created_at' => now()->subDays(rand(15, 25)),
            'updated_at' => now()->subDays(rand(15, 25)),
        ]);
        return $batch;
    }
}
