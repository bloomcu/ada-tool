<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2
- laravel/cashier (CASHIER) - v15
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the new Laravel structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

<!--
  ============================================================================
  PROJECT CONTEXT — hand-maintained, NOT managed by Laravel Boost.
  Boost only regenerates content BETWEEN the <laravel-boost-guidelines> tags
  above (see GuidelineWriter regex), so anything below this line survives
  `boost:install` / Boost updates. Keep durable product knowledge here to
  avoid re-investigating the codebase each session.
  ============================================================================
-->

# Project Context

> **Maintenance:** Keep this section current. When you discover a durable quirk,
> convention, gotcha, or structural fact about this codebase, add or update it
> here (below the Boost markers) as part of the same change — don't wait to be
> asked. Prefer editing an existing bullet over appending duplicates.

## What this app is

An **ADA / web-accessibility scanning tool** (backend API). It crawls a customer's
website, runs accessibility (WCAG/ADA) checks against each page, and stores the
resulting violations so they can be reviewed and remediated. The frontend is a
separate application; this repo is the Laravel API.

## Architecture

- **Domain-Driven Design layout.** The `DDD\` PSR-4 namespace maps to `app/`
  (see `composer.json` autoload). This is intentional and predates the Laravel 12
  upgrade — do **not** "fix" it to the default `App\` structure.
- Top-level `app/` splits into:
  - `app/Domain/` — domain models + their Resources/Requests/Mail, one folder per
    aggregate (`Organizations`, `Sites`, `Scans`, `Pages`, `Evaluations`, `Base`).
  - `app/Http/` — controllers grouped by domain (`Http/Sites`, `Http/Scans`,
    `Http/Pages`), middleware, and `Http/Base` (auth controllers live here).
  - `app/App/` — cross-cutting: `Services`, `Providers`, `Policies`, `Events`,
    `Console`, `Helpers`, `Scopes`, `Traits`.
- Laravel 10-era structure (Kernel-based), retained through the 9→12 upgrade.
  Middleware in `app/Http/Kernel.php`, not `bootstrap/app.php`.

## Domain model / tenancy

Multi-tenant, scoped by organization. The ownership chain:

```
Organization ──has many──> Site ──has many──> Scan ──has many──> Page
                            │                                       ▲
                            └──has many──> Evaluation ──has many────┘
```

- Every tenant-scoped API route is prefixed with `{organization:slug}` and uses
  `->scopeBindings()`, so route-model binding enforces that a Site/Scan/Page
  actually belongs to that organization. **This tenant isolation is provided by
  routing, not by the models** — any non-HTTP entry point (jobs, commands, MCP
  tools) must re-enforce org scoping manually via the authenticated user.
- `Scan`, `Evaluation`, and `Page` carry the accessibility results. `Scan` and
  `Page` are `Prunable` (scans older than 1 year are pruned).
- `Page` can be individually re-scanned (`rescan_id` → `Scan`).

## External services

- **Apify** (`app/App/Services/Apify/`, bound via `ApifyServiceProvider`) is the
  crawler/scanner engine. `ApifyADAScanner` implements `ApifyInterface`. Kicking
  off a scan triggers an external, **billable** Apify run — treat scan-start /
  rescan / abort as costly side effects, not cheap reads.

## Auth

- **Laravel Sanctum** (token-based). The app already issues personal access
  tokens on login/registration via `createToken('auth_token')` (see
  `app/Http/Base/Auth/*`). The `User` model (`app/Domain/Base/Users/User.php`)
  uses `HasApiTokens`.
- Tenant-scoped API routes sit behind `->middleware('auth:sanctum')` in
  `routes/api.php`.
- **Known gap:** the top-level `scans/*` routes (scan create/status/dataset/abort)
  are currently **unauthenticated** — marked `// TODO: Can these be behind auth?`
  in `routes/api.php`. Do not treat these as a security model to copy; new
  surfaces (e.g. MCP) should require auth.

## MCP status (as of 2026-07)

- `laravel/mcp` v0.8.2 is installed, but only **transitively** via `laravel/boost`
  (not a direct `composer.json` requirement). The MCP server currently running is
  Boost's own dev server.
- There is **no** `routes/ai.php` and **no** `app/Mcp/` — no custom MCP server
  exists yet. Building one is greenfield.
- When built, MCP tools must manually enforce the org-scoping described above
  (flat `/mcp/...` routes have no `{organization:slug}` binding to lean on), and
  should be protected with `->middleware('auth:sanctum')`.
