# laravel-ts-models

A lightweight Laravel package that generates TypeScript interfaces directly from your Eloquent models — including columns, accessors, relationships, enum casts, and more.

## Requirements

- PHP 8.2+
- Laravel 11.33+

## Installation

Add the repository to your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/nedieyassin/laravel-ts-models"
    }
]
```

Then install:

```bash
composer require --dev nedieyassin/laravel-ts-models:dev-main
```

### Publish the config

```bash
php artisan vendor:publish --tag=ts-models-config
```

This creates `config/ts-models.php` in your Laravel project.

---

## Usage

```bash
# Generate types for all models
php artisan types:generate

# Generate types for a single model
php artisan types:generate --model=App\\Models\\User

# Preview output without writing to file
php artisan types:generate --dry-run
```

Output is written to `resources/js/types/models.ts` by default.

---

## Configuration

`config/ts-models.php`

```php
return [

    // Path to scan for models, relative to base_path()
    'models_path' => 'app/Models',

    // Where to write the generated TypeScript file, relative to base_path()
    'output_path' => 'resources/js/types/models.ts',

    // Model classes to skip
    'excludes' => [
        // App\Models\InternalModel::class,
    ],

    // Override the TypeScript type for specific model fields
    'overrides' => [
        // App\Models\Location::class => [
        //     'coordinate' => [
        //         'type'   => 'Point',
        //         'import' => '@/types/geometry',
        //     ],
        //     'status' => [
        //         'type' => "'active' | 'inactive'",
        //     ],
        // ],
    ],

    // Additional import lines prepended to the generated file
    'imports' => [
        // "import type { Point } from '@/types/geometry'",
    ],

    // Override the Collections enum value for specific models
    // Defaults to the plural camelCase of the model name
    'collection_names' => [
        // App\Models\Person::class => 'people',
    ],

];
```

---

## What Gets Generated

### `IOption` type

Always emitted at the top of the file.

```ts
export type IOption = {
  label: string
  value: string | number | boolean
  disabled?: boolean
}
```

### `Collections` enum

All discovered models as a collections enum for use as API route keys.

```ts
export enum Collections {
  users = 'users',
  posts = 'posts',
  categories = 'categories',
}
```

### Enum casts

PHP backed enums cast on a model are emitted as `const enum` blocks.

```ts
export const enum Gender {
  MALE = 'male',
  FEMALE = 'female',
}
```

If the enum has a `label(): string` method, an options array is also generated:

```ts
export const genderOptions: IOption[] = [
  { label: 'Male', value: Gender.MALE },
  { label: 'Female', value: Gender.FEMALE },
]
```

### Model interfaces

```ts
export interface IUser {
  // columns
  id: number
  name: string
  email: string
  gender: Gender
  status: Status | null
  created_at: string
  updated_at: string

  // accessors
  full_name: string

  // relations
  posts?: IPost[]
  profile?: IProfile

  // counts  (HasMany relations)
  posts_count?: number

  // exists  (HasOne relations)
  profile_exists?: boolean
}
```

---

## Model Setup

### Enum casts

```php
protected $casts = [
    'gender' => Gender::class,               // not nullable → gender: Gender
    'status' => 'nullable:' . Status::class, // nullable     → status: Status | null
];
```

### Accessors

Accessors are detected via reflection. You must declare a return type on the getter closure:

```php
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn (): string => $this->first_name . ' ' . $this->last_name,
    );
}
```

### Relationships

Relationships must have explicit return types to be detected:

```php
// HasMany → typed as IPost[], generates posts_count
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

// HasOne → typed as IProfile, generates profile_exists
public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}
```

### Enum with labels

```php
enum Gender: string
{
    case MALE   = 'male';
    case FEMALE = 'female';

    public function label(): string
    {
        return match($this) {
            self::MALE   => 'Male',
            self::FEMALE => 'Female',
        };
    }
}
```

---

## Using in React / TypeScript

```ts
import type { IUser, IPost } from '@/types/models'
import { Collections, Gender, genderOptions } from '@/types/models'

// Type API responses
const user: IUser = await api.get(`${Collections.users}/1`)

// Use enum on a field
if (user.gender === Gender.MALE) { ... }

// Use options in a select component
<Select options={genderOptions} />
```

---

## Tip: Add to composer scripts

Keep your types in sync by adding a script to your `composer.json`:

```json
"scripts": {
    "types": "php artisan types:generate"
}
```

Then run after any model changes:

```bash
composer types
```

---

## License

MIT