<?php

namespace Nedieyassin\LaravelTsModels;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ModelInspector
{
    public function __construct(protected TypeMapper $mapper)
    {
    }

    /**
     * Inspect a model and return structured data for the generator.
     */
    public function inspect(Model $model): array
    {
        $reflection = new ReflectionClass($model);
        $table = $model->getTable();
        $casts = $model->getCasts();
        $overrides = config('ts-models.overrides', [])[$model::class] ?? [];

        $dbColumns = $this->getDbColumns($table);
        $fields = $this->resolveFields($model, $reflection, $casts, $dbColumns, $overrides);
        $relations = $this->resolveRelations($model, $reflection);
        $accessors = $this->resolveAccessors($reflection, $casts);
        $enums = $this->resolveEnums($casts);

        return [
            'fields' => $fields,
            'relations' => $relations,
            'accessors' => $accessors,
            'enums' => $enums,
        ];
    }

    /**
     * Get all columns from the DB for a given table.
     * Returns [ columnName => [ 'type' => string, 'nullable' => bool ] ]
     * @throws \Exception
     */
    protected function getDbColumns(string $table): array
    {
        $columns = [];
        try {
            foreach (Schema::getColumns($table) as $col) {
                $columns[$col['name']] = [
                    'type' => $col['type_name'] ?? $col['type'] ?? 'string',
                    'nullable' => (bool)($col['nullable'] ?? false),
                ];
            }
        } catch (\Throwable) {
            // If schema inspection fails (e.g. no DB connection), return empty
            throw  new \Exception("Failed to get db columns for $table");
        }
        return $columns;
    }

    /**
     * Resolve all model fields to [name => [tsType, nullable, enumClass|null, isOverride]].
     */
    protected function resolveFields(
        Model           $model,
        ReflectionClass $reflection,
        array           $casts,
        array           $dbColumns,
        array           $overrides
    ): array
    {
        $fields = [];


        // Process all DB columns first
        foreach ($dbColumns as $colName => $colInfo) {

            if (isset($casts[$colName])) {
                [$tsType, $nullable, $enumClass] = $this->mapper->resolveCast($casts[$colName]);

                // Merge DB nullable with cast nullable
                $nullable = $nullable || $colInfo['nullable'];

                // Check if property is declared as nullable enum via reflection
                if ($enumClass && $this->isPropertyNullable($reflection, $colName)) {
                    $nullable = true;
                }

                $fields[$colName] = [$tsType, $nullable, $enumClass, false];
            } else {
                [$tsType, $nullable] = $this->mapper->resolveDbType($colInfo['type'], $colInfo['nullable']);
                $fields[$colName] = [$tsType, $nullable, null, false];
            }
        }

        // Handle any casts not found in DB columns (computed/virtual)
        foreach ($casts as $colName => $castType) {
            if (isset($fields[$colName])) continue;
            [$tsType, $nullable, $enumClass] = $this->mapper->resolveCast($castType);
            $fields[$colName] = [$tsType, $nullable, $enumClass, false];
        }

        // Always include timestamps
        foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
            if (!isset($fields[$ts]) && $model->usesTimestamps()) {
                $nullable = $ts === 'deleted_at';
                $fields[$ts] = ['string', $nullable, null, false];
            }
        }

        // Apply overrides from config
        foreach ($overrides as $fieldName => $override) {
            $fields[$fieldName] = [$override['type'], false, null, true];
        }

        return $fields;
    }

    /**
     * Detect if a model property is declared with a nullable type hint.
     */
    protected function isPropertyNullable(ReflectionClass $reflection, string $property): bool
    {
        if (!$reflection->hasProperty($property)) return false;
        $prop = $reflection->getProperty($property);
        $type = $prop->getType();
        return $type?->allowsNull() ?? false;
    }

    /**
     * Resolve all Eloquent relationships via reflection.
     * Returns [ relationName => [ 'type' => tsType, 'kind' => 'one'|'many' ] ]
     */
    protected function resolveRelations(Model $model, ReflectionClass $reflection): array
    {
        $relations = [];

        $manyTypes = [HasMany::class, BelongsToMany::class, HasManyThrough::class, MorphMany::class];
        $oneTypes = [HasOne::class, BelongsTo::class, MorphOne::class, MorphTo::class];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited Model methods and non-relation methods
            if ($method->getDeclaringClass()->getName() === Model::class) continue;
            if ($method->getNumberOfParameters() > 0) continue;

            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) continue;

            $returnTypeName = $returnType->getName();

            $isMany = false;
            $isOne = false;

            foreach ($manyTypes as $manyClass) {
                if ($returnTypeName === $manyClass || is_subclass_of($returnTypeName, $manyClass)) {
                    $isMany = true;
                    break;
                }
            }

            if (!$isMany) {
                foreach ($oneTypes as $oneClass) {
                    if ($returnTypeName === $oneClass || is_subclass_of($returnTypeName, $oneClass)) {
                        $isOne = true;
                        break;
                    }
                }
            }

            if (!$isMany && !$isOne) continue;

            try {
                $relationInstance = $model->{$method->getName()}();
                $related = $relationInstance->getRelated();
                $relatedClass = get_class($related);
                $relatedName = 'I' . class_basename($relatedClass);

                $relations[$method->getName()] = [
                    'type' => $relatedName,
                    'kind' => $isMany ? 'many' : 'one',
                ];
            } catch (\Throwable) {
                // If relation can't be instantiated, skip
            }
        }

        return $relations;
    }

    /**
     * Detect accessor/mutator methods (methods returning Attribute).
     * Returns [ accessorName => tsType ]
     */
    protected function resolveAccessors(ReflectionClass $reflection, array $casts): array
    {
        $accessors = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->getDeclaringClass()->getName() === Model::class) continue;
            if ($method->getNumberOfParameters() > 0) continue;

            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) continue;
            if ($returnType->getName() !== Attribute::class) continue;

            // Skip if already handled as a cast
            $name = $method->getName();
            if (isset($casts[$name])) continue;

            // Try to determine the return type from the getter closure
            $tsType = $this->inferAccessorType($method, $reflection);
            $accessors[$name] = $tsType;
        }

        return $accessors;
    }

    /**
     * Try to infer the TypeScript type of an accessor from its getter return type.
     */
    protected function inferAccessorType(ReflectionMethod $method, ReflectionClass $reflection): string
    {
        try {
            // Instantiate the Attribute to inspect the getter closure's return type
            $instance = $reflection->newInstanceWithoutConstructor();
            $attribute = $instance->{$method->getName()}();

            if ($attribute instanceof Attribute && $attribute->get !== null) {
                $closure = new \ReflectionFunction($attribute->get);
                $closureReturn = $closure->getReturnType();

                if ($closureReturn instanceof ReflectionNamedType) {
                    [$tsType] = $this->mapper->resolveCast($closureReturn->getName());
                    return $tsType;
                }
            }
        } catch (\Throwable) {
        }

        return 'unknown';
    }

    /**
     * Collect all enum classes from casts.
     * Returns [ enumClass => [ 'cases' => [...], 'hasLabel' => bool ] ]
     */
    protected function resolveEnums(array $casts): array
    {
        $enums = [];

        foreach ($casts as $castType) {
            // Strip nullable markers
            $castType = ltrim(str_replace('nullable:', '', $castType), '?');
            $castType = rtrim($castType, '?');

            if (!class_exists($castType) || !enum_exists($castType)) continue;
            if (isset($enums[$castType])) continue;

            $reflection = new ReflectionClass($castType);
            $cases = [];

            foreach ($castType::cases() as $case) {
                $cases[] = [
                    'name' => $case->name,
                    'value' => $case->value ?? $case->name,
                ];
            }

            $hasLabel = $reflection->hasMethod('label') &&
                $reflection->getMethod('label')->getReturnType()?->getName() === 'string';

            $enums[$castType] = [
                'cases' => $cases,
                'hasLabel' => $hasLabel,
            ];
        }

        return $enums;
    }
}