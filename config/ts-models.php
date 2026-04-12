<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Models Path
    |--------------------------------------------------------------------------
    | The path where your Eloquent models live, relative to base_path().
    */
    'models_path' => 'app/Models',

    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    | Where the generated TypeScript file will be written, relative to base_path().
    */
    'output_path' => 'resources/js/types/models.ts',

    /*
    |--------------------------------------------------------------------------
    | Excludes
    |--------------------------------------------------------------------------
    | Fully qualified model class names to skip during generation.
    |
    | Example:
    | 'excludes' => [
    |     App\Models\Telescope\EntryModel::class,
    | ],
    */
    'excludes' => [],

    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    | Override the generated TypeScript type for specific model fields.
    | Optionally provide an import path for custom types.
    |
    | Example:
    | 'overrides' => [
    |     App\Models\Location::class => [
    |         'coordinate' => [
    |             'type'   => 'Point',
    |             'import' => '@/types/geometry',
    |         ],
    |         'choices' => [
    |             'type' => "'good' | 'bad'",
    |         ],
    |     ],
    | ],
    */
    'overrides' => [],

    /*
    |--------------------------------------------------------------------------
    | Extra Imports
    |--------------------------------------------------------------------------
    | Any additional import lines to prepend to the generated file.
    |
    | Example:
    | 'imports' => [
    |     "import type { Point } from '@/types/geometry'",
    | ],
    */
    'imports' => [],

    /*
    |--------------------------------------------------------------------------
    | Collection Names
    |--------------------------------------------------------------------------
    | Override the Collections enum value for specific models.
    | By default, the plural camelCase of the model name is used.
    |
    | Example:
    | 'collection_names' => [
    |     App\Models\Person::class => 'people',
    |     App\Models\Category::class => 'cats',
    | ],
    */
    'collection_names' => [],

];