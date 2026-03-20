<?php

declare(strict_types=1);

/**
 * Example 01: Eloquent ORM — defining a model, querying, and using relations.
 *
 * This example is self-contained and does not require a full Laravel
 * application. It uses SQLite in-memory, a minimal service provider boot,
 * and the Eloquent capsule manager to set up the database layer.
 *
 * Run with:
 *   php examples/01_eloquent_model.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

// ── 1. Bootstrap Eloquent with an SQLite in-memory database ─────────────────

$capsule = new DB();
$capsule->addConnection([
    'driver'   => 'sqlite',
    'database' => ':memory:',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// ── 2. Create the schema ─────────────────────────────────────────────────────

DB::schema()->create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});

DB::schema()->create('posts', function ($table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('title');
    $table->text('body')->nullable();
    $table->timestamps();
});

// ── 3. Define models ─────────────────────────────────────────────────────────

/**
 * A User model demonstrating fillable mass-assignment and a hasMany relation.
 */
class User extends Model
{
    protected $fillable = ['name', 'email'];

    /** @return \Illuminate\Database\Eloquent\Relations\Has_Many<Post> */
    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Post::class);
    }
}

/**
 * A Post model demonstrating a belongsTo relation.
 */
class Post extends Model
{
    protected $fillable = ['user_id', 'title', 'body'];

    /** @return \Illuminate\Database\Eloquent\Relations\Belongs_To<User, Post> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// ── 4. Seed data ──────────────────────────────────────────────────────────────

$alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
$bob   = User::create(['name' => 'Bob',   'email' => 'bob@example.com']);

$alice->posts()->createMany([
    ['title' => 'Hello World', 'body' => 'My first post.'],
    ['title' => 'Eloquent Tips', 'body' => 'Always eager-load relations.'],
]);

$bob->posts()->create(['title' => "Bob's Post", 'body' => 'A single post.']);

// ── 5. Query examples ────────────────────────────────────────────────────────

// Eager-load to avoid N+1
$users = User::with('posts')->get();

foreach ($users as $user) {
    echo sprintf("User: %s (%d posts)\n", $user->name, $user->posts->count());
    foreach ($user->posts as $post) {
        echo sprintf("  - %s\n", $post->title);
    }
}

// Scope / where clause
$alicePosts = Post::whereHas('user', fn ($q) => $q->where('name', 'Alice'))->get();
echo "\nAlice's posts via whereHas:\n";
foreach ($alicePosts as $post) {
    echo sprintf("  - %s\n", $post->title);
}

// Fluent update
User::where('name', 'Bob')->update(['name' => 'Robert']);
echo "\nUpdated Bob → " . User::find($bob->id)->name . "\n";

// Soft-deletion example is omitted here but requires adding `use SoftDeletes`
// and a `deleted_at` column to the migration.
echo "\nDone.\n";
