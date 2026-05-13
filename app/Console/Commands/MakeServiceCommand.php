<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';
    protected $description = 'Create a new service class';

    public function handle()
    {
        $name = $this->argument('name');
        $directory  = app_path('Services');
        $path = "{$directory}/{$name}.php";

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($path)) {
            $this->error("Service {$name} already exists!");
            return;
        }

        $content = "<?php\n\nnamespace App\Services;\n\nclass {$name}\n{\n    // Define your logic here\n}\n";

        File::put($path, $content);

        $this->info("Service {$name} created successfully at app/Services/{$name}.php");
    }
}
