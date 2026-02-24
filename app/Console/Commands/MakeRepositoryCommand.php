<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeRepositoryCommand extends Command
{
    // Nama perintah yang akan dipanggil di terminal
    protected $signature = 'make:repository {name}';
    protected $description = 'Create a new repository class';

    public function handle()
    {
        $name = $this->argument('name');
        $directory = app_path('Repositories');
        $path = "{$directory}/{$name}.php";

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($path)) {
            $this->error("Repository {$name} already exists!");
            return;
        }

        $content = "<?php\n\nnamespace App\Repositories;\n\nclass {$name}\n{\n    // Define your logic here\n}\n";

        File::put($path, $content);

        $this->info("Repository {$name} created successfully at app/Repositories/{$name}.php");
    }
}