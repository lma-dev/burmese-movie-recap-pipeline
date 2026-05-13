---
name: laravel-basic-skill
description: "description: Apply baseline Laravel design conventions when scaffolding a new app or adding code to a fresh Laravel project — single-repo domain/controller separation, FormRequest validation, SOLID, N+1 guard"
---

# laravel-app-design

Baseline conventions for a new Laravel app. Apply when scaffolding routes/controllers/models/migrations in a freshly created Laravel project, or when reviewing such code.

## When to use

- Creating a new Laravel app (`laravel new ...`) and laying down the first feature
- Adding a new controller, model, migration, request, resource, enum, or helper to an early-stage app
- Reviewing PRs against the conventions below

Skip if the project already has its own established conventions — defer to those.

## Layering (single repository style)

- One repo, no microservices, no extra DDD layers.
- Keep **domain language out of controllers**. Controllers only:
  1. Receive a `FormRequest`
  2. Delegate to a domain class (Service / Action / Repository — pick one and stay consistent)
  3. Return a `Resource`
- Domain classes live under `app/Domain/<Context>/` (or `app/Services` / `app/Actions` — one style only).
- No business logic, no Eloquent queries, no validation inside controllers.

## Validation — always FormRequest

- Every write endpoint has a dedicated `FormRequest` (`php artisan make:request`).
- `rules()` and `authorize()` live there. No `$request->validate(...)` inside controllers.
- File uploads use Laravel's file validation rules (`file`, `mimes:`, `max:`, `image`, `dimensions:`) inside the FormRequest — not ad-hoc checks.

## SOLID — practical rules, not theatre

- **SRP**: one controller method = one use case. If a method does two things, split the Action.
- **OCP / DIP**: when something has >1 implementation (payment gateway, notifier, exporter), depend on an interface and bind in a ServiceProvider. Do **not** introduce interfaces for things with one implementation.
- **LSP / ISP**: keep interfaces small. Don't add methods "just in case".
- Don't pre-abstract. Two concrete classes is fine; extract an interface on the third.

## N+1 prevention

- Default to eager loading: `->with([...])` on every list query that touches a relation.
- For nested loads, list them explicitly (`with(['posts.comments.author'])`) — don't lazy-chain in Blade/Resource.
- Enable `Model::preventLazyLoading()` in `AppServiceProvider::boot()` for local + staging so the bug surfaces early.
- For aggregates, use `withCount` / `withSum` instead of looping.

## Enums — no magic strings/ints

- Any fixed set of values (status, role, type, kind) → PHP 8.1 backed enum in `app/Enums/`.
- Cast on the model: `protected $casts = ['status' => OrderStatus::class];`
- Never compare against raw strings/ints in controllers, services, or Blade.

## Helpers — no inline duplicated logic

- Repeated pure utility functions live in `app/Helpers/` as a class with static methods, **or** an autoloaded `helpers.php` file registered in `composer.json` `autoload.files`.
- Pick one style per project. Don't mix.
- Helpers must be stateless. Anything that touches DB / HTTP / auth belongs in a service, not a helper.

## Responses — always Resource

- Every JSON response goes through `JsonResource` / `ResourceCollection` (`php artisan make:resource`).
- Controllers never return `$model->toArray()` or raw arrays for API output.
- Shape changes happen in the Resource, not in the controller or model.

## Policies — authorization lives in one place

- Every Eloquent model with non-trivial access rules has a Policy (`php artisan make:policy --model=Foo`).
- Method names match the action verb: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`. Add custom verbs (`publish`, `assign`) only when a built-in doesn't fit.
- Invoke from the controller via `$this->authorize('update', $order)` or from `FormRequest::authorize()` via `$this->user()->can('update', $this->route('order'))`. **Pick one entry point per endpoint** — don't check the same policy in both places.
- No inline `if ($user->id !== $order->user_id) abort(403)` — always go through a Policy.
- Tenant/ownership checks belong in the Policy, not in the controller or query.
- Register policies via Laravel's auto-discovery; only fall back to `AuthServiceProvider::$policies` when the model is in a non-standard namespace.

## Web routes — `routes/web.php`

- Use `Route::resource('posts', PostController::class)` for full CRUD with Blade views. Use `->only([...])` / `->except([...])` to trim.
- URIs in **kebab-case**; route names in **dot.case** following Laravel defaults: `posts.index`, `posts.store`, `order-items.edit`.
- **Always name routes.** Reference them via `route('posts.show', $post)` in Blade and redirects — never hard-code `/posts/1`.
- Group by middleware and prefix:
  ```php
  Route::middleware(['auth', 'verified'])
      ->prefix('admin')
      ->name('admin.')
      ->group(function () { ... });
CSRF is on by default — don't exempt routes in VerifyCsrfToken::$except unless they're genuinely webhook endpoints (which belong in api.php anyway).
Don't mix API and web concerns: if it returns JSON to a SPA, it goes in api.php. web.php is for Blade-rendered pages and form posts that redirect.
Closures in routes are fine for a one-line redirect (Route::redirect('/home', '/dashboard')); anything more goes to a controller.

## API routes — `routes/api.php`

- One resource per controller. Use `Route::apiResource('orders', OrderController::class)` — don't hand-roll the 7 routes.
- URIs in **plural kebab-case**: `/order-items`, not `/OrderItems` or `/orderItem`.
- Version under a prefix from day one: `Route::prefix('v1')->group(...)`. Even if there's only v1, the prefix prevents painful migrations later.
- Group shared middleware: `Route::middleware(['auth:sanctum', 'throttle:api'])->group(...)`.
- Route model binding (`Order $order`) over manual `Order::findOrFail($id)` lookups.
- Custom actions: prefer a new resource over a verb URI. `/orders/{order}/cancel` (POST) is fine; `/cancelOrder/{id}` is not.
- All responses go through Resources (see above). No raw JSON from `api.php` handlers.
- Auth: Sanctum for SPAs / mobile, Passport only if you actually need OAuth2.

## Naming conventions

| Where | Style | Example |
|---|---|---|
| Classes, methods, variables, route names | `camelCase` / `PascalCase` for classes | `getActiveUsers()`, `OrderStatus` |
| **Migration column names** | `snake_case` | `created_at`, `user_id`, `is_active` |
| DB table names | `snake_case`, plural | `order_items` |
| Enum cases | `PascalCase` | `OrderStatus::Pending` |
| Route URIs | `kebab-case` | `/order-items` |

- Names must describe **intent**, not type. `$activeUsers` not `$arr`, `markAsPaid()` not `update2()`.
- No abbreviations unless the codebase already uses them.

## Checklist before opening a PR

- [ ] Controller has no validation, no query, no business logic
- [ ] FormRequest used for all writes (incl. file uploads)
- [ ] Eager loading on every list endpoint; no lazy access in Resources
- [ ] No raw status strings — Enum + cast
- [ ] No duplicated inline helpers — extracted to `app/Helpers/`
- [ ] All API responses wrapped in a Resource
- [ ] Migration columns in `snake_case`; code identifiers in `camelCase`
- [ ] Authorization goes through a Policy — no inline ownership checks
- [ ] API routes use `apiResource` under a version prefix
- [ ] Web routes are named; templates/redirects use `route()` helper

## Anti-patterns

- Repository / Service / Action **all three** in one project — pick one
- Interface for every class "for testability" — only when there are multiple impls
- `$request->all()` straight into `Model::create()` without a FormRequest
- `foreach ($orders as $o) { $o->user->name; }` — classic N+1
- `if ($status === 'paid')` — should be `=== OrderStatus::Paid`
- Authorizing in both `FormRequest::authorize()` and `Controller::authorize()` for the same action
- Hand-rolling 7 routes instead of `apiResource` / `resource`
- Hard-coded URLs in Blade or redirects (`/posts/1` instead of `route('posts.show', $post)`)
- Verb-in-URI APIs (`/getOrders`, `/createUser`) — not RESTful