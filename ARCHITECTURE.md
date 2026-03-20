# Laravel Framework — Architecture Overview

> Version: 12.x
> PHP requirement: 8.2+

## Purpose

This repository is the **Laravel framework core** (`laravel/framework`). It contains all first-party components that power a Laravel application — routing, ORM, HTTP handling, authentication, job dispatching, and more — distributed as a single Composer package with split subtree mirrors for each component.

---

## Directory Structure

```
src/Illuminate/
├── Auth/               Authentication guards, providers, password reset
├── Broadcasting/       Event broadcasting to WebSocket channels
├── Bus/                Command bus / job dispatching infrastructure
├── Cache/              Cache stores (file, redis, database, array, …)
├── Collections/        Base Collection class (split package)
├── Config/             Configuration repository
├── Console/            Artisan command infrastructure
├── Container/          IoC / DI container (split package)
├── Contracts/          Pure interfaces — no concrete dependencies
├── Cookie/             Cookie creation and queuing
├── Database/           Database abstraction + Eloquent ORM
│   └── Eloquent/       Active-Record ORM; Model, Builder, Relations
├── Events/             Synchronous event dispatcher
├── Filesystem/         Flysystem abstraction for local/S3/other drivers
├── Foundation/         Application bootstrap, service providers, Artisan
├── Hashing/            Password hashing (bcrypt, argon2)
├── Http/               Request, Response, JSON responses, middleware
├── Log/                PSR-3 logging via Monolog
├── Mail/               Mailable abstractions and SMTP/SES drivers
├── Notifications/      Multi-channel notifications (email, SMS, Slack, …)
├── Pagination/         Cursor and length-aware paginators
├── Pipeline/           Middleware-style pipeline for passing objects
├── Process/            Wrapper around Symfony Process component
├── Queue/              Queue workers, jobs, failed-job handling
├── Redis/              Redis connection manager
├── Routing/            Router, Route, URL generator, middleware stack
├── Session/            Session stores and flash data
├── Support/            Cross-cutting utilities: Arr, Str, Collection, …
├── Testing/            Test helpers and fakes
├── Translation/        Localisation and pluralisation
├── Validation/         Rule-based input validation
└── View/               Blade template engine
```

---

## Key Design Decisions

### 1. IoC Container as the Backbone

`Illuminate\Container\Container` is the application's dependency-injection container.
`Illuminate\Foundation\Application` extends `Container` and acts as the application object. Every service is bound through the container, enabling swapping implementations in tests without modifying call sites.

### 2. Service Provider Bootstrap

All subsystems are wired up via **Service Providers** (`Illuminate\Support\Service_Provider`). The lifecycle is:

1. `register()` — bind services into the container (never resolve; the container may not be fully populated yet).
2. `boot()` — execute logic that depends on other services being registered.

Boot order is determined by the `$providers` array in `config/app.php` plus any deferred providers.

### 3. Eloquent Active Record

`Illuminate\Database\Eloquent\Model` follows the Active-Record pattern. Each model corresponds to a database table; query building is delegated to `Eloquent\Builder` which wraps the underlying `Database\Query\Builder` and adds model awareness (global scopes, eager loading, soft deletes).

**Dependency flow inside Eloquent:**

```
Model
  └── Builder (Eloquent)
        └── Builder (Query)
              └── Connection
                    └── PDO
```

### 4. HTTP Kernel Pipeline

Every HTTP request passes through a middleware pipeline before reaching a controller:

```
Request → HttpKernel → Router → Route → Middleware stack → Controller action → Response
```

The pipeline is implemented in `Illuminate\Pipeline\Pipeline` and reused for both HTTP middleware and command middleware.

### 5. Event System

`Illuminate\Events\Dispatcher` is a synchronous pub/sub dispatcher. Listeners can be plain callables, class@method strings, or queued listeners that defer work to the queue. Broadcasting wraps events in a serialisable envelope and pushes them to a WebSocket driver (Pusher, Ably, Reverb, or Laravel Echo Server).

### 6. Authentication Guards

`Illuminate\Auth\Auth_Manager` manages named **guards** (e.g. `web`, `api`). Each guard implements `Illuminate\Contracts\Auth\Guard`. The session-based `Session_Guard` and token-based `Token_Guard` are the built-in implementations. Custom guards can be registered via `Auth::extend()`.

---

## Extension Points

| What to extend | How |
|---|---|
| Add an Eloquent cast | Implement `Illuminate\Contracts\Database\Eloquent\Castable` or `Cast_Using_Casts` |
| Add a cache driver | `Cache::extend('driver', fn() => new CacheStore(...))` |
| Add an auth guard | `Auth::extend('guard', fn() => new MyGuard(...))` |
| Add an auth user provider | `Auth::provider('provider', fn() => new MyProvider(...))` |
| Add a queue connector | `Queue::extend('connector', fn() => new MyConnector(...))` |
| Add a filesystem disk | `Storage::extend('driver', fn() => new MyAdapter(...))` |
| Add a validation rule | `Validator::extend('rule', fn() => ...)` or create a `Rule` class |
| Add Artisan commands | Register via a Service Provider `commands([...])` call |
| Add macros to helpers | `Str::macro(...)`, `Collection::macro(...)`, etc. |

---

## Dependency Flow (high-level)

```
Contracts (interfaces, no dependencies)
    ↑
Container (depends only on Contracts + PHP SPL)
    ↑
Support (depends on Container + Contracts)
    ↑
Foundation/Application (glues all components)
    ↑
Http / Routing / Database / Auth / ... (domain components)
    ↑
Your application code
```

No component in the lower levels may depend on a component in the upper levels. The `Contracts` package deliberately has zero external runtime dependencies.

---

## Performance Notes

- Route caching (`php artisan route:cache`) serialises the compiled route collection to a PHP file, avoiding repeated regex compilation on every request. See `Illuminate\Routing\Compiled_Route_Collection`.
- Config caching (`php artisan config:cache`) merges all config files into a single array.
- View caching compiles Blade templates to plain PHP once, stored in `storage/framework/views`.
- Eloquent lazy loading is a common N+1 source. Enable `Model::preventLazyLoading()` in development to catch missing `with()` calls early.
