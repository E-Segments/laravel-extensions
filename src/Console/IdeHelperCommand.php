<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Console;

use Esegments\LaravelExtensions\Support\IdeHelperGenerator;
use Illuminate\Console\Command;

/**
 * Command to generate IDE helper file for extension points.
 *
 * @example
 * ```bash
 * php artisan extension:ide-helper
 * php artisan extension:ide-helper --output=custom_path.php
 * ```
 */
class IdeHelperCommand extends Command
{
    protected $signature = 'extension:ide-helper
        {--output= : Output file path (default: _extension_helper.php)}';

    protected $description = 'Generate IDE helper file for extension points';

    public function handle(IdeHelperGenerator $generator): int
    {
        $outputPath = $this->option('output') ?? base_path('_extension_helper.php');

        $this->info('Generating IDE helper...');

        $content = $generator->generate();

        file_put_contents($outputPath, $content);

        $this->info("IDE helper generated at: {$outputPath}");
        $this->newLine();
        $this->line('Add to your .gitignore:');
        $this->line('  _extension_helper.php');

        return self::SUCCESS;
    }
}
