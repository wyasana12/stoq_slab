<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateDssCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dss:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DSS analysis & recomendation cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        GenerateDssCacheCommand::dispatch();

        $this->info('Dss Job Cache dispached.');

        return self::SUCCESS;
    }
}
