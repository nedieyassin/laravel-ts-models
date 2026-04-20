<?php

namespace Nedieyassin\LaravelTsModels\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Nedieyassin\LaravelTsModels\ModelInspector;
use Nedieyassin\LaravelTsModels\TypeMapper;
use Nedieyassin\LaravelTsModels\TypescriptGenerator;
use Symfony\Component\Finder\Finder;

class GenerateTypesCommand extends Command
{
    protected $signature = 'types:generate
                                {--model= : Generate types for a single model class}
                                {--dry-run : Print output to console instead of writing to file}';

    protected $description = 'Generate TypeScript interfaces from Laravel Eloquent models';

    public function handle(): int
    {
        $modelsPath = config('ts-models.models_path', 'app/Models');
        $outputPath = config('ts-models.output_path', 'resources/js/types/models.ts');
        $excludes = config('ts-models.excludes', []);
        $singleModel = $this->option('model');
        $dryRun = $this->option('dry-run');

        $models = $singleModel ? $this->loadSingleModel($singleModel) : $this->loadAllModels($modelsPath, $excludes);

        if (empty($models)) {
            $this->error('No models found.');
            return self::FAILURE;
        }

        $generator = new TypescriptGenerator(new ModelInspector(new TypeMapper()));
        $output = $generator->generate($models);

        if ($dryRun) {
            $this->line($output);
            return self::SUCCESS;
        }

        $fullPath = base_path($outputPath);
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $output);

        $count = count($models);
        $this->info("✓ Generated TypeScript types for {$count} model(s) → {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * Load a single model by class name.
     *
     * @return Model[]
     */
    protected function loadSingleModel(string $class): array
    {
        if (!class_exists($class)) {
            $this->error("Model class [{$class}] not found.");
            return [];
        }

        $instance = new $class();
        if (!$instance instanceof Model) {
            $this->error("[{$class}] is not an Eloquent model.");
            return [];
        }

        return [$instance];
    }

    /**
     * Discover and load all models from the given path.
     *
     * @return Model[]
     */
    protected function loadAllModels(string $modelsPath, array $excludes): array
    {
        $models = [];
        $fullPath = base_path($modelsPath);

        if (!is_dir($fullPath)) {
            $this->error("Models path [{$modelsPath}] does not exist.");
            return [];
        }

        $finder = Finder::create()->files()->name('*.php')->in($fullPath)->sortByName();

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $this->pathToClass($relativePath, $modelsPath);

            if (!$class || !class_exists($class)) continue;

            // Skip excluded models
            if (in_array($class, $excludes, true)) {
                $this->line("  ⊘ Skipped: {$class}");
                continue;
            }

            try {
                $instance = new $class();
            } catch (\Throwable) {
                $this->warn("  ⚠ Could not instantiate: {$class}");
                continue;
            }

            if (!$instance instanceof Model) continue;

            $models[] = $instance;
        }

        return $models;
    }

    /**
     * Convert a relative file path to a fully qualified class name.
     */
    protected function pathToClass(string $relativePath, string $modelsPath): ?string
    {
        // Remove .php extension
        $withoutExt = str_replace('.php', '', $relativePath);

        // Build namespace from models path
        // e.g. app/Models → App\Models
        $namespaceBase = str_replace('/', '\\', ucwords($modelsPath, '/'));
        $namespaceBase = implode('\\', array_map('ucfirst', explode('\\', $namespaceBase)));

        return $namespaceBase . '\\' . $withoutExt;
    }
}