<?php

namespace App\Console\Commands;

use App\Models\AlertConfig;
use App\Models\AlertLog;
use App\Models\Batch;
use App\Notifications\BatchExpiryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendWarehouseAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-warehouse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa batch yang akan expired dan mengirim alert ke admin/staff warehouse terkait.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai pengecekan alert expired...');

        $configs = AlertConfig::where('is_active', true)->get();

        if ($configs->isEmpty()) {
            $this->warn('Tidak ada konfigurasi alert yang aktif saat ini.');
            return Command::SUCCESS;
        }

        foreach ($configs as $c) {
            $targetData = Carbon::today()->addDays($c->days_before)->format('Y-m-d');

            $this->info("Memproses konfigurasi {$c->name} (Target Date: {$targetData})");

            $batches = Batch::with('warehouse.admins', 'product')
                ->whereDate('expired_date', $targetData)
                ->whereDoesntHave('alerts', function ($q) use ($c) {
                    $q->where('alert_config_id', $c->id);
                })
                ->get();

            if ($batches->isEmpty()) {
                $this->line("-> Tidak ada batch yang expired untuk H-{$c->days_before} hari ini.");
                continue;
            }

            foreach ($batches as $b) {
                $warehouse = $b->warehouse;

                if (!$warehouse || $warehouse->admins->isEmpty()) {
                    $this->warn("-> Batch {$b->batch_code} diabaikan karena Warehouse tidak ditemukan atau tidak memiliki admin/staff.");
                    continue;
                }

                $title = "🚨 [PERINGATAN H-{$c->days_before}] Stok Expired di Gudang: {$warehouse->name}";

                $message = "Halo Tim Gudang, diberitahukan bahwa produk '{$b->product->name}' " .
                           "dengan kode batch '{$b->batch_code}' (Jumlah: {$b->current_quantity}) " .
                           "yang berada di lokasi '{$b->rack_location}' akan segera kedaluwarsa dalam " .
                           "{$c->days_before} hari, tepatnya pada tanggal {$b->expired_date}. " .
                           "Mohon segera lakukan tindakan manajemen stok (FEFO).";

                try {
                    Notification::send($warehouse->admins, new BatchExpiryNotification($title, $message));

                    AlertLog::create([
                        'warehouse_id' => $warehouse->id,
                        'batch_id' => $b->id,
                        'alert_config_id' => $c->id,
                        'title' => $title,
                        'message' => $message,
                    ]);

                    $totalPenerima = $warehouse->admins->count();
                    $this->info(" [SUKSES] Alert dikirim ke {$totalPenerima} personil di warehouse {$warehouse->name} (Batch: {$b->batch_code})");
                } catch (\Exception $err) {
                    Log::error("Gagal mengirim alert untuk kode batch {$b->batch_code} ".$err->getMessage());
                    $this->error(" [GAGAL] Gagal mengirim alert untuk kode batch {$b->batch_code}");
                }
            }
        }

        $this->info('Proses pengecekan alert expired selesai.');
        return Command::SUCCESS;
    }
}
