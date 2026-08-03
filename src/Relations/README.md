# Relations

Resolver-based relation loading for modular SDK contexts where modules communicate through contracts and DTO/array payloads only.

## Purpose

This folder provides an in-memory relation loader that:

- avoids N+1 by batching contract calls
- supports nested relation paths (for example: `student.profile.address`)
- reuses fetched data via `RelationCache`
- stays framework-agnostic at data layer (no Eloquent relation objects, no DB joins)

## How classes connect

1. `RelationProvider`

- Implemented by modules to expose relation definitions.

1. `RelationRegistryBuilder`

- Reads all installed modules.
- Instantiates each provider.
- Merges provider relation maps into one registry array.

1. `RelationEngine`

- Accepts registry + optional `RelationCache`.
- Parses requested relation paths.
- Picks resolver by relation `type`.
- Resolves relation in batches.
- Recursively resolves nested relations.

1. `Resolvers/*`

- Each resolver implements `RelationResolver`.
- Resolver performs batch contract calls and injects resolved data into each item.

1. `Relation`

- Static helper methods that return correctly-shaped relation config arrays.

## Resolver map (default)

- `belongsTo` => `BelongsToResolver`
- `hasOne` => `HasOneResolver`
- `hasMany` => `HasManyResolver`
- `belongsToMany` => `BelongsToManyResolver`
- `morphOne` => `MorphOneResolver`
- `morphMany` => `MorphManyResolver`
- `morphTo` => `MorphToResolver`

You can extend at runtime with:

- `RelationEngine::addResolver(string $type, string $resolverClass)`
- `RelationEngine::addResolvers(array $typeToResolver)`

## Spec and validation

- Spec version is exposed as `RelationEngine::SPEC_VERSION` (currently `1.0.0`).
- Schema rules are defined in `RelationSpec::requiredKeysByType()`.
- Runtime validation is handled by `RelationConfigValidator`.
- Typed errors live under `Relations/Exceptions`:
  - `InvalidRelationConfigException`
  - `UnsupportedRelationTypeException`
  - `RelationResolutionException`

Validation mode:

- default (non-strict): invalid relation entries are skipped to preserve backward compatibility
- strict: `RelationEngine::strictValidation(true)` throws typed exceptions

## Relation config shapes

### belongsTo

```php
Relation::belongsTo(StudentContract::class, 'student_id', 'id');
```

Keys:

- `type`, `contract`, `local_key`, `foreign_key`

### hasOne

```php
Relation::hasOne(StudentProfileContract::class, 'student_id', 'id');
```

Keys:

- `type`, `contract`, `local_key`, `foreign_key`

### hasMany

```php
Relation::hasMany(ResultContract::class, 'student_id', 'id');
```

Keys:

- `type`, `contract`, `local_key`, `foreign_key`

### belongsToMany

```php
Relation::belongsToMany(
    ClubContract::class,
    MembershipContract::class,
    'student_id',  // pivot_parent_key
    'club_id',     // pivot_related_key
    'id',          // local_key
    'id'           // foreign_key on related
);
```

Keys:

- `type`, `contract`, `pivot_contract`, `pivot_parent_key`, `pivot_related_key`, `local_key`, `foreign_key`

### morphOne

```php
Relation::morphOne(MediaContract::class, 'owner_id', 'student', 'id', 'owner_type');
```

Keys:

- `type`, `contract`, `local_key`, `foreign_key`, `morph_type_key`, `morph_type`

### morphMany

```php
Relation::morphMany(CommentContract::class, 'commentable_id', 'student', 'id', 'commentable_type');
```

Keys:

- `type`, `contract`, `local_key`, `foreign_key`, `morph_type_key`, `morph_type`

### morphTo

```php
Relation::morphTo([
    'student' => StudentContract::class,
    'teacher' => TeacherContract::class,
], 'owner_type', 'owner_id', 'id');
```

Keys:

- `type`, `morph_map`, `morph_type_key`, `morph_id_key`, `foreign_key`

## End-to-end usage

### 1) Define module provider

```php
use SchoolPalm\ModuleBridge\Relations\Relation;
use SchoolPalm\ModuleBridge\Relations\RelationProvider;

final class IdManagerRelationProvider implements RelationProvider
{
    public function relations(): array
    {
        return [
            'student' => Relation::belongsTo(StudentContract::class, 'student_id', 'id'),
            'profile' => Relation::hasOne(StudentProfileContract::class, 'student_id', 'id'),
            'results' => Relation::hasMany(ResultContract::class, 'student_id', 'id'),
        ];
    }
}
```

### 2) Build registry

```php
$registry = app(\SchoolPalm\ModuleBridge\Relations\RelationRegistryBuilder::class)
    ->build($installedModules);
```

### 3) Load relations

```php
$engine = new \SchoolPalm\ModuleBridge\Relations\RelationEngine($registry);

$items = $engine->load($items, [
    'student',
    'profile',
    'results',
    'student.address',
]);
```

## Notes on nested relations

Nested relations are resolved by dot notation and applied recursively after each parent relation is injected.

Example:

- request: `student.address`
- step 1: resolve `student` on each root item
- step 2: resolve `address` for the injected `student` items

## Caching behavior

`RelationCache` is shared by one `RelationEngine` instance.

- repeated relation loads with same key set + contract reuse cache entries
- cache keys are scoped by type and relation-specific metadata

If you want request-scoped caching, instantiate one engine per request/operation.

## Contract expectations

Resolvers assume contracts expose one of the following:

- `findMany(array $ids): array`
- `query(array $filters)->get(): array`

Keep returned rows/DTOs array-accessible when using current resolvers.
