# Architecture

Todo Board is plain PHP 8.1 — no framework and no Composer — but it is organised the way
a framework app would be, so every class has one job and can be tested on its own.

## The life of a request

```
public/index.php
  └─ bootstrap/app.php            builds the Container (src/Bootstrap/Services.php)
  └─ Request::fromGlobals()       the only place that reads $_GET / $_POST / $_SERVER
  └─ Kernel::handle()
       ├─ SecurityHeaders         CSP, X-Frame-Options, nosniff …
       ├─ HandleErrors            any exception → error page or JSON
       ├─ VerifyCsrfToken         every POST needs the session token
       ├─ Router::match()         404 / 405, path parameters like {id}
       ├─ route middleware        Authenticate or RedirectIfAuthenticated
       └─ Controller              returns a Response (HTML, JSON or redirect)
  └─ Response::send()
```

Nothing is echoed before `send()`, which is why the feature tests can run the whole app
in memory: they build the same container, swap in an `ArraySession`, a `FrozenClock` and an
in-memory SQLite database, and call `Kernel::handle()` directly.

## Layers

| Folder | What lives there | Depends on |
|---|---|---|
| `src/Task`, `src/Auth` | Entities (`Task`, `User`), value objects (`DueDate`, `Status`, `Priority`), services and repository interfaces | nothing from HTTP |
| `src/Validation` | `Validator` and one class per rule | nothing |
| `src/Database` | PDO connection factory and the first-run migrator | PDO |
| `src/Http` | Request, Response, Router, Kernel, middleware, redirects and form state | Session, View |
| `src/Controllers` | Thin classes that turn a Request into service calls and a Response | services, View |
| `src/View` + `views/` | Template renderer, helpers, escaping, icons, and the PHP templates | — |
| `src/Bootstrap` | `Services`: the one place that wires interfaces to implementations | everything |

The domain (`Task`, `Auth`) never imports anything from `Http`. When it needs to say
"not found" it throws an exception that implements `App\Support\NotFound`, and the
`HandleErrors` middleware turns that into a 404.

## SOLID in practice

- **Single responsibility** — `Csrf` only issues and checks tokens, `FormState` only carries
  errors and old input through a redirect, `LoginThrottle` only counts failed logins,
  `Authenticator` only knows who is logged in, `AuthService` only signs people up and in.
- **Open/closed** — a new validation rule is a new class registered with `Validator::extend()`;
  a new page is a new route and controller method. Existing classes don't change.
- **Liskov substitution** — `ArraySession` and `NativeSession`, `SystemClock` and `FrozenClock`,
  `PdoTaskRepository` and any other `TaskRepository` can replace each other; the tests rely on it.
- **Interface segregation** — small interfaces: `Session`, `Clock`, `Rule`, `Middleware`,
  `TaskRepository`, `UserRepository`, `LoginThrottle`.
- **Dependency inversion** — services ask for interfaces in their constructor. Only
  `Services::register()` knows which concrete class is used.

## Adding things

**A validation rule**

```php
namespace App\Validation\Rules;

use App\Validation\Rule;

final class Url implements Rule
{
    public function check(string $label, mixed $value, array $params): ?string
    {
        return filter_var($value, FILTER_VALIDATE_URL) ? null : "$label must be a valid link.";
    }
}
```

Register it in `Validator::__construct()` (or call `$validator->extend('url', new Url())`)
and use it as `'link' => ['Link', 'url|max:255']`.

**A task field** (for example `link`)

1. Add the column to both schema files in `database/`.
2. Add it to `TaskData`, `Task::fromRow()` and the queries in `PdoTaskRepository`.
3. Add its rules to `TaskRules::fields()` and its name to `TaskController::FIELDS`.
4. Show it in `views/tasks/card.php` and add an input to `views/tasks/board.php` and `views/tasks/edit.php`.

**A page or action**

1. Add a method to a controller (or a new controller registered in `Services`).
2. Add the route in `config/routes.php`, inside the `Authenticate` group if it needs a login.
3. Add a feature test in `tests/Feature/`.

## Tests

```bash
php tests/run.php           # everything
php tests/run.php Board     # only tests whose name contains "Board"
```

- `tests/Unit` — single classes, no database.
- `tests/Integration` — repositories and auth against an in-memory SQLite database.
- `tests/Feature` — full requests through the Kernel, from sign-up to clearing finished tasks.
