<?php

namespace Nedieyassin\LaravelTsModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TypescriptGenerator
{
    protected array $allEnums = [];
    protected array $allImports = [];

    public function __construct(protected ModelInspector $inspector)
    {
    }

    /**
     * Generate the full TypeScript file content from a list of model instances.
     *
     * @param Model[] $models
     */
    public function generate(array $models): string
    {
        $this->allEnums = [];
        $this->allImports = [];

        // First pass: inspect all models and collect enums + imports
        $inspected = [];
        foreach ($models as $model) {
            $data = $this->inspector->inspect($model);
            $inspected[] = ['model' => $model, 'data' => $data];

            foreach ($data['enums'] as $enumClass => $enumData) {
                $this->allEnums[$enumClass] = $enumData;
            }

            // Collect override imports from config
            $overrides = config('ts-models.overrides', [])[$model::class] ?? [];
            foreach ($overrides as $override) {
                if (!empty($override['import'])) {
                    $this->allImports[] = "import type { {$override['type']} } from '{$override['import']}'";
                }
            }
        }

        $lines = [];

        // Extra imports from config
        foreach (config('ts-models.imports', []) as $import) {
            $lines[] = $import;
        }

        // Override-driven imports
        foreach (array_unique($this->allImports) as $import) {
            $lines[] = $import;
        }

        if (!empty($lines)) {
            $lines[] = '';
        }

        // IOption type
        $lines[] = 'export type IOption = {';
        $lines[] = '  label: string';
        $lines[] = '  value: string | number | boolean';
        $lines[] = '  disabled?: boolean';
        $lines[] = '}';
        $lines[] = '';

        // Collections enum
        $lines[] = 'export enum Collections {';
        $collectionNames = config('ts-models.collection_names', []);
        foreach ($inspected as $entry) {
            $class = $entry['model']::class;
            $name = class_basename($entry['model']);
            $camelName = isset($collectionNames[$class])
                ? $collectionNames[$class]
                : lcfirst(Str::plural($name));
            $lines[] = "  {$camelName} = '{$camelName}',";
        }
        $lines[] = '}';
        $lines[] = '';

        // Emit all enums
        foreach ($this->allEnums as $enumClass => $enumData) {
            $lines = array_merge($lines, $this->buildEnum($enumClass, $enumData));
        }

        // Second pass: emit interfaces
        foreach ($inspected as $entry) {
            $lines = array_merge($lines, $this->buildInterface($entry['model'], $entry['data']));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Build a const enum block and optional options array for an enum class.
     */
    protected function buildEnum(string $enumClass, array $enumData): array
    {
        $lines = [];
        $enumName = class_basename($enumClass);
        $cases = $enumData['cases'];
        $hasLabel = $enumData['hasLabel'];

        // const enum
        $lines[] = "export enum {$enumName} {";
        foreach ($cases as $case) {
            $value = is_string($case['value']) ? "'{$case['value']}'" : $case['value'];
            $lines[] = "  {$case['name']} = {$value},";
        }
        $lines[] = '}';
        $lines[] = '';

        // options array if label() exists
        if ($hasLabel) {
            $optionsName = lcfirst($enumName) . 'Options';
            $lines[] = "export const {$optionsName}: IOption[] = [";
            foreach ($cases as $case) {
                try {
                    $label = $enumClass::from($case['value'])->label();
                } catch (\Throwable) {
                    $label = ucfirst(strtolower(str_replace('_', ' ', $case['name'])));
                }
                $lines[] = "  { label: '{$label}', value: {$enumName}.{$case['name']} },";
            }
            $lines[] = ']';
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Build the TypeScript interface for a single model.
     */
    protected function buildInterface(Model $model, array $data): array
    {
        $lines = [];
        $name = class_basename($model);
        $iName = "I{$name}";
        $overrides = config('ts-models.overrides', [])[$model::class] ?? [];
        $fields = $data['fields'];
        $relations = $data['relations'];
        $accessors = $data['accessors'];

        $lines[] = "export interface {$iName} {";

        // Fields
        if (!empty($fields)) {
            $lines[] = '  // columns';
            foreach ($fields as $colName => [$tsType, $nullable, $enumClass]) {
                if ($enumClass) {
                    $enumName = class_basename($enumClass);
                    $suffix = $nullable ? ' | null' : '';
                    $lines[] = "  {$colName}: {$enumName}{$suffix}";
                } elseif (isset($overrides[$colName])) {
                    $lines[] = "  {$colName}: {$overrides[$colName]['type']}";
                } else {
                    $suffix = $nullable ? ' | null' : '';
                    $lines[] = "  {$colName}: {$tsType}{$suffix}";
                }
            }
        }

        // Accessors
        if (!empty($accessors)) {
            $lines[] = '  // accessors';
            foreach ($accessors as $accName => $tsType) {
                $lines[] = "  {$accName}: {$tsType}";
            }
        }

        // Relations
        if (!empty($relations)) {
            $lines[] = '  // relations';
            foreach ($relations as $relName => $rel) {
                if ($rel['kind'] === 'many') {
                    $lines[] = "  {$relName}?: {$rel['type']}[]";
                } else {
                    $lines[] = "  {$relName}?: {$rel['type']}";
                }
            }
        }

        // _count for HasMany relations
        $manyRelations = array_filter($relations, fn($r) => $r['kind'] === 'many');
        if (!empty($manyRelations)) {
            $lines[] = '  // counts';
            foreach ($manyRelations as $relName => $rel) {
                $lines[] = "  {$relName}_count?: number";
            }
        }

        // _exists for HasOne relations
        $oneRelations = array_filter($relations, fn($r) => $r['kind'] === 'one');
        if (!empty($oneRelations)) {
            $lines[] = '  // exists';
            foreach ($oneRelations as $relName => $rel) {
                $lines[] = "  {$relName}_exists?: boolean";
            }
        }

        $lines[] = '}';
        $lines[] = '';

        return $lines;
    }
}