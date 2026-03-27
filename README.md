<div align="center">

<img src="assets/img/favicon.svg" width="72" alt="Todo Board logo">

# Todo Board

**A bright, fast and secure task board built with plain PHP, PDO and vanilla JavaScript.**
No framework, no build step, no heavy CSS library — just clean OOP you can read in one sitting.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-supported-4479A1?logo=mysql&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-zero%20setup-003B57?logo=sqlite&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-vanilla-F7DF1E?logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/license-MIT-12c29b)

<img src="docs/screenshots/board-light.png" alt="Todo Board — light theme" width="100%">

</div>

---

## Table of contents

- [Why I built it](#why-i-built-it)
- [Features](#features)
- [Screenshots](#screenshots)
- [Getting started](#getting-started)
- [Project structure](#project-structure)
- [Running the tests](#running-the-tests)
- [How it works](#how-it-works)
- [Security](#security)
- [Performance](#performance)
- [Adding your own features](#adding-your-own-features)
- [Roadmap](#roadmap)
- [Author](#author)

---

## Why I built it

This started as a small CRUD exercise (add / edit / delete notes with three statuses) to practice OOP,
SOLID and PDO in native PHP. I rebuilt it into something I actually use every day: a personal board
for work, study and life tasks — and then rebuilt the code itself the way a framework app is organised:
one front controller, a router, middleware, controllers, services, repositories and views, all in
plain PHP with no Composer. Every class has one job and the whole app is covered by tests.

## Features

**Everyday use**
- 🗂️ **Kanban board** — *To do*, *Doing* and *Done* columns
- ⚡ **Quick add** — type a title and hit Enter; open *More details* for priority, due date, list and notes
- 🚩 **Priorities** — High / Normal / Low, color coded and sorted automatically
- 📅 **Due dates** — friendly labels (*Today*, *Tomorrow*, *Sunday*, *2 days late*) with overdue highlighting
- 🏷️ **Lists / categories** — each list gets its own color, with autocomplete from your existing lists
- 🔍 **Instant search & filters** — Today, Overdue, High priority, or any list — no page reload
- 🖱️ **Drag & drop** cards between columns (buttons still work without JavaScript)
- 📊 **Progress & stats** — open, due today, overdue, done today and a completion bar
- 🎉 **A small celebration** when you finish a task
- 🌙 **Dark mode** — follows your system and remembers your choice
- ⌨️ **Keyboard shortcuts** — <kbd>N</kbd> new task, <kbd>/</kbd> search, <kbd>Esc</kbd> clear
- 📱 **Responsive** — works nicely on phones
- 🌍 **Arabic & English text** — every task field uses `dir="auto"`, so RTL text just works

**Accounts**
- 👤 Register / log in / log out — every user has a private board
- 🧹 Clear all finished tasks in one click

## Screenshots

| Light | Dark |
|---|---|
| <img src="docs/screenshots/board-light.png" alt="Board in light mode"> | <img src="docs/screenshots/board-dark.png" alt="Board in dark mode"> |

| Log in | Quick add with details |
|---|---|
| <img src="docs/screenshots/login.png" alt="Login page"> | <img src="docs/screenshots/quick-add.png" alt="Quick add form"> |

| Filter by list | Edit a task |
|---|---|
| <img src="docs/screenshots/filter-work.png" alt="Filtering by the Work list"> | <img src="docs/screenshots/edit.png" alt="Edit task page"> |

<p align="center">
  <img src="docs/screenshots/mobile-dark.png" alt="Mobile view, dark" width="260">
  &nbsp;&nbsp;
  <img src="docs/screenshots/mobile.png" alt="Mobile view, light" width="260">
</p>

## Getting started

### Requirements
- PHP **8.1+** with `pdo_sqlite` (default) or `pdo_mysql`
- That's it — no Composer, no npm

### Option 1 — Zero setup (SQLite)

```bash
git clone https://github.com/m77mdabdo/TO_DO_LIST.git
cd TO_DO_LIST
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000>, create an account and start adding tasks.
The database file is created automatically in `database/todo.sqlite`.

Want sample data to play with?

```bash
php database/seed.php
# then log in with: demo@todo.test / demo1234
```

### Option 2 — XAMPP + MySQL

1. Copy the project into `htdocs/TO_DO_LIST` (make sure `mod_rewrite` is on — it is by default).
2. Create a database called `to_do_list` in phpMyAdmin.
3. Copy `config/app.php` to `config/local.php` and set:
   ```php
   'driver' => 'mysql',
   'name'   => 'to_do_list',
   'user'   => 'root',
   'pass'   => '',
   ```
4. Visit <http://localhost/TO_DO_LIST>. Tables are created on the first request
   (or import `database/schema.mysql.sql` yourself).

The root `.htaccess` forwards every request into `public/`, so nothing else in the project can be opened
from the browser. On a real server, point the document root at `public/` instead.

You can also use environment variables instead of `config/local.php`:
`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_NAME`, `APP_TIMEZONE`, `APP_DEBUG`.

## Project structure

```
TO_DO_LIST/
├── public/                 # the only folder the web server exposes
│   ├── index.php           # front controller: every request starts here
│   └── assets/             # style.css, app.js, theme.js, favicon.svg
│
├── bootstrap/
│   ├── app.php             # loads config and builds the service container
│   └── autoload.php        # PSR-4 autoloader for App\ → src/
├── config/
│   ├── app.php             # settings (override with config/local.php or env vars)
│   └── routes.php          # every URL the app answers
│
├── src/
│   ├── Auth/               # User, AuthService, Authenticator, LoginThrottle, repositories
│   ├── Bootstrap/          # Services: wires interfaces to implementations
│   ├── Controllers/        # AuthController, BoardController, TaskController
│   ├── Database/           # ConnectionFactory, Migrator
│   ├── Http/               # Request, Response, Router, Kernel, middleware, Redirector, FormState
│   ├── Security/           # Csrf
│   ├── Session/            # Session interface, NativeSession, ArraySession
│   ├── Support/            # Clock, Container, TimeAgo, Greeting
│   ├── Task/               # Task, Board, BoardStats, DueDate, Status, Priority, TaskService …
│   ├── Validation/         # Validator + one class per rule
│   └── View/               # View renderer, ViewHelpers, Html, Icons
│
├── views/                  # plain PHP templates (layout, board, card, edit, login, register, error)
├── database/               # schema.sqlite.sql, schema.mysql.sql, seed.php
├── tests/                  # Unit, Integration and Feature tests + a tiny test runner
└── docs/                   # ARCHITECTURE.md and screenshots
```

## How it works

```
Browser ──▶ public/index.php ──▶ Kernel
                                   1. SecurityHeaders       CSP, no framing, nosniff
                                   2. HandleErrors          exceptions → error page / JSON
                                   3. VerifyCsrfToken       every POST (419)
                                   4. Router                404 / 405, {id} parameters
                                   5. Authenticate          redirect to /login, or 401 for fetch()
                                   6. Controller            → TaskService → TaskRepository (scoped by user)
                                 ◀── Response               HTML, redirect, or JSON for drag & drop
```

When a card is moved with drag & drop, `POST /tasks/{id}/move` returns the freshly rendered card HTML and
the new stats as JSON, so the page updates instantly without reloading — and the exact same route still
works as a normal form post when JavaScript is off.

The full tour of the layers, and how each SOLID principle shows up in the code, is in
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Running the tests

```bash
php tests/run.php
```

No PHPUnit needed: `tests/run.php` is a small runner that finds every `*Test.php` file.
The suite has unit tests, integration tests against an in-memory SQLite database, and feature tests
that send real requests through the Kernel. GitHub Actions runs it on PHP 8.1 – 8.4 for every push.

## Security

| Threat | How it's handled |
|---|---|
| SQL injection | PDO prepared statements everywhere, emulated prepares off |
| XSS | Output escaped with `$v->e()` at render time; strict **Content-Security-Policy** (no inline scripts or styles) |
| CSRF | Per-session token on every form and `fetch()` call, compared with `hash_equals` |
| State changes over GET | Every action is a POST route; other methods get 405 |
| Reading other users' tasks (IDOR) | Every query is scoped by `user_id`; foreign ids return 404 |
| Passwords | `password_hash` / `password_verify`, automatic rehash, same timing for unknown emails |
| Brute force | 5 failed logins per IP → 15-minute lock |
| Session fixation / theft | ID regenerated on login, strict mode, `HttpOnly` + `SameSite=Lax` (+ `Secure` on HTTPS) |
| Clickjacking & sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy` |
| Direct file access | Only `public/` is reachable; the root `.htaccess` forwards everything into it |
| Invalid data | Whitelisted status / priority values, real date parsing, length limits |

## Performance

- **No CSS/JS frameworks** — the old 155 KB Bootstrap file is gone; the page loads one ~20 KB stylesheet and one small script.
- **One query** builds the whole board (instead of one per column), backed by an index on `(user_id, status)`.
- **Search and filters run in the browser** on data that is already on the page.
- **System fonts and inline SVG icons** — no font or icon downloads.
- Assets get a `?v=` cache-busting version from their modified time, so they can be cached aggressively.
- SQLite runs in WAL mode for fast reads and writes.

## Adding your own features

A new validation rule is one class that implements `App\Validation\Rule`; a new page is a route in
`config/routes.php` plus a controller method; a new field touches the schema, `TaskData`, the repository,
`TaskRules` and the views. Step-by-step examples are in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#adding-things).

## Roadmap

- [ ] Subtasks / checklists inside a task
- [ ] Recurring tasks (daily, weekly)
- [ ] Reorder cards inside a column
- [ ] Reminder e-mails for tasks due today
- [ ] Export / import as CSV
- [ ] Arabic interface translation

Ideas and pull requests are welcome.

## Author

**Mohamed Abdo** — Full Stack Developer (PHP / Laravel)

- GitHub: [@m77mdabdo](https://github.com/m77mdabdo)

If this project helped you, consider giving it a ⭐
