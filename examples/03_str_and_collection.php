<?php

declare(strict_types=1);

/**
 * Example 03: Str helper and Collection — common string and collection patterns.
 *
 * Demonstrates the most frequently used Str static methods and the fluent
 * Collection API. Both are usable outside a full Laravel application with no
 * additional bootstrapping.
 *
 * Run with:
 *   php examples/03_str_and_collection.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// ── 1. Str — string utilities ─────────────────────────────────────────────────

echo "=== Str examples ===\n\n";

// Case conversion
echo Str::title('hello world')                     . "\n";  // Hello World
echo Str::camel('hello_world')                     . "\n";  // helloWorld
echo Str::snake('helloWorld')                      . "\n";  // hello_world
echo Str::kebab('helloWorld')                      . "\n";  // hello-world
echo Str::studly('hello_world')                    . "\n";  // HelloWorld

// Substring operations
echo Str::after('user@example.com', '@')           . "\n";  // example.com
echo Str::before('user@example.com', '@')          . "\n";  // user
echo Str::between('[secret]', '[', ']')            . "\n";  // secret
echo Str::limit('The quick brown fox', 10)         . "\n";  // The quick...
echo Str::words('The quick brown fox', 2)          . "\n";  // The quick...

// Testing / pattern matching
var_dump(Str::startsWith('laravel/framework', 'laravel'));  // bool(true)
var_dump(Str::endsWith('laravel/framework', 'work'));       // bool(true)
var_dump(Str::contains('hello world', 'world'));            // bool(true)
var_dump(Str::is('laravel/*', 'laravel/framework'));        // bool(true)

// UUID / ULID generation
echo Str::uuid()  . "\n";  // e.g. "550e8400-e29b-41d4-a716-446655440000"
echo Str::ulid()  . "\n";  // e.g. "01ARZ3NDEKTSV4RRFFQ69G5FAV"

// Masking sensitive data
echo Str::mask('alice@example.com', '*', 3, 10) . "\n";  // ali**********com

// Fluent Stringable chaining
$result = Str::of('  Hello, World!  ')
    ->trim()
    ->lower()
    ->replace('hello', 'hi')
    ->title();

echo $result . "\n";  // Hi, World!

// ── 2. Collection — fluent array transformations ──────────────────────────────

echo "\n=== Collection examples ===\n\n";

$users = collect([
    ['name' => 'Alice', 'age' => 30, 'role' => 'admin'],
    ['name' => 'Bob',   'age' => 25, 'role' => 'editor'],
    ['name' => 'Carol', 'age' => 35, 'role' => 'admin'],
    ['name' => 'Dave',  'age' => 22, 'role' => 'viewer'],
]);

// Filter and map
$admins = $users
    ->filter(fn ($u) => $u['role'] === 'admin')
    ->map(fn ($u) => $u['name']);

echo "Admins: " . $admins->implode(', ') . "\n";  // Alice, Carol

// Group by
$byRole = $users->groupBy('role');
foreach ($byRole as $role => $members) {
    echo "Role '{$role}': " . $members->pluck('name')->implode(', ') . "\n";
}

// Sort, reduce
$averageAge = $users->avg('age');
echo "Average age: {$averageAge}\n";  // 28

$oldest = $users->sortByDesc('age')->first();
echo "Oldest: {$oldest['name']}\n";  // Carol

// Unique, contains, first/last
$roles = $users->pluck('role')->unique()->sort()->values();
echo "Unique roles: " . $roles->implode(', ') . "\n";

// chunk for batch processing
$users->chunk(2)->each(function (Collection $batch, int $i) {
    echo "Batch {$i}: " . $batch->pluck('name')->implode(', ') . "\n";
});

// lazy() — memory-efficient iteration over large sets
$sum = collect(range(1, 1_000_000))
    ->lazy()
    ->filter(fn ($n) => $n % 2 === 0)
    ->take(5)
    ->sum();

echo "Sum of first 5 even numbers (lazy): {$sum}\n";  // 30
