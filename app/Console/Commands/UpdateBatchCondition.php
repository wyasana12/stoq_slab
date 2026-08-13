<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateBatchCondition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-batch-condition';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa batch yang akan expired dan melakukan update kondisi batch terkait.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai pengecekan batch expired...');

        $today = Carbon::today()->format('Y-m-d');
        $min30Days = Carbon::today()->addDays(30)->format('Y-m-d');

        try {
            $this->info('Memulai pembaruan batch expired...');

            $expiredCount = Batch::whereDate('expired_date', '<', $today)
                ->where('condition', '!=', 'KADALUARSA')
                ->update(['condition' => 'KADALUARSA']);

            $mendekatiCount = Batch::whereDate('expired_date', '>', $today)
                ->whereDate('expired_date', '<=', $min30Days)
                ->where('condition', '!=', 'MENDEKATI_KADALUARSA')
                ->update(['condition' => 'MENDEKATI_KADALUARSA']);

                $this->info("Pembaruan selesai: {$expiredCount} batch expired, {$mendekatiCount} batch mendekati expired.");
        } catch (\Exception $err) {
            Log::error("Gagal memperbarui kondisi batch: " . $err->getMessage());
            $this->error("Terjadi kesalahan sistem saat memperbarui batch.");
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
