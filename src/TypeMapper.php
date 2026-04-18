<?php

namespace Nedieyassin\LaravelTsModels;

class TypeMapper
{
    /**
     * Cast type → TypeScript type mappings.
     */
    protected array $castMap = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'decimal' => 'number',
        'real' => 'number',
        'string' => 'string',
        'str' => 'string',
        'char' => 'string',
        'text' => 'string',
        'uuid' => 'string',
        'ulid' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'unknown[]',
        'json' => 'unknown[]',
        'object' => 'Record<string, unknown>',
        'collection' => 'unknown[]',
        'date' => 'string',
        'date:Y-m-d' => 'string',
        'datetime' => 'string',
        'immutable_date' => 'string',
        'immutable_datetime' => 'string',
        'timestamp' => 'string',
        'encrypted' => 'string',
        'hashed' => 'string',
    ];

    /**
     * DB column type → TypeScript type mappings (fallback).
     */
    protected array $dbTypeMap = [
        // integers
        'int' => 'number',
        'int2' => 'number',
        'int4' => 'number',
        'int8' => 'number',
        'integer' => 'number',
        'bigint' => 'number',
        'smallint' => 'number',
        'tinyint' => 'number',
        'mediumint' => 'number',
        'serial' => 'number',
        'bigserial' => 'number',
        'smallserial' => 'number',
        // floats
        'float' => 'number',
        'float4' => 'number',
        'float8' => 'number',
        'double' => 'number',
        'double precision' => 'number',
        'real' => 'number',
        'decimal' => 'number',
        'numeric' => 'number',
        'money' => 'number',
        // strings
        'varchar' => 'string',
        'character varying' => 'string',
        'char' => 'string',
        'bpchar' => 'string',
        'text' => 'string',
        'tinytext' => 'string',
        'mediumtext' => 'string',
        'longtext' => 'string',
        'uuid' => 'string',
        'ulid' => 'string',
        'citext' => 'string',
        'inet' => 'string',
        'cidr' => 'string',
        'macaddr' => 'string',
        'enum' => 'string',
        'set' => 'string',
        'tsvector' => 'string',
        // booleans
        'bool' => 'boolean',
        'boolean' => 'boolean',
        // dates
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'timestamptz' => 'string',
        'timestamp without time zone' => 'string',
        'timestamp with time zone' => 'string',
        'time' => 'string',
        'timetz' => 'string',
        'year' => 'string',
        // json
        'json' => 'unknown[]',
        'jsonb' => 'Record<string, unknown>',
        // binary / other
        'binary' => 'string',
        'bytea' => 'string',
        'blob' => 'string',
        'bit' => 'string',
        'varbit' => 'string',
        'interval' => 'string',
        'point' => 'string',
        'line' => 'string',
        'polygon' => 'string',
        'xml' => 'string',
    ];

    /**
     * Resolve a cast string to a TypeScript type.
     * Returns [tsType, isNullable, enumClass|null]
     */
    public function resolveCast(string $cast): array
    {
        $nullable = false;

        // Handle nullable prefixes: "nullable:int" or "int?"
        if (str_starts_with($cast, 'nullable:')) {
            $nullable = true;
            $cast = substr($cast, 9);
        } elseif (str_ends_with($cast, '?')) {
            $nullable = true;
            $cast = rtrim($cast, '?');
        }

        // Handle decimal precision: decimal:2 → number
        if (str_starts_with($cast, 'decimal:') || str_starts_with($cast, 'float:')) {
            return ['number', $nullable, null];
        }

        // Check if it's an enum class
        if (class_exists($cast) && enum_exists($cast)) {
            return [$cast, $nullable, $cast];
        }

        $lower = strtolower($cast);
        $tsType = $this->castMap[$lower] ?? 'unknown';

        return [$tsType, $nullable, null];
    }

    /**
     * Resolve a raw DB column type to a TypeScript type.
     * Returns [tsType, isNullable]
     */
    public function resolveDbType(string $dbType, bool $nullable): array
    {
        $lower = strtolower(trim($dbType));

        // Strip type modifiers like "character varying(255)" → "character varying"
        $base = preg_replace('/\(.*\)/', '', $lower);
        $base = trim($base);

        $tsType = $this->dbTypeMap[$base] ?? $this->dbTypeMap[$lower] ?? 'unknown';

        return [$tsType, $nullable];
    }
}