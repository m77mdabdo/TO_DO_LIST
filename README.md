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
for work, study and life tasks — while keeping the same simple structure of `classes/`, `handle/` and `inc/`
so it stays easy to learn from and easy to extend.

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
php -S localhost:8000
```

Open <http://localhost:8000>, create an account and start adding tasks.
The database file is created automatically in `database/todo.sqlite`.

Want sample data to play with?

```bash
php database/seed.php
# then log in with: demo@todo.test / demo1234
```

### Option 2 — XAMPP + MySQL

1. Copy the project into `htdocs/TO_DO_LIST`.
2. Create a database called `to_do_list` in phpMyAdmin.
3. Copy `config.php` to `config.local.php` and set:
   ```php
   'driver' => 'mysql',
   'name'   => 'to_do_list',
   'user'   => 'root',
   'pass'   => '',
   ```
4. Visit <http://localhost/TO_DO_LIST>. Tables are created on the first request
   (or import `database/schema.mysql.sql` yourself).

You can also use environment variables instead of `config.local.php`:
`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_NAME`, `APP_TIMEZONE`.

## Project structure

```
TO_DO_LIST/
├── App.php                 # bootstrap: autoloader, config, session, headers, services
├── config.php              # default settings (override with config.local.php)
├── index.php               # the board
├── edit.php                # edit a task
├── login.php / register.php
├── 404.php                 # error page (404, 405, 419…)
│
├── classes/
│   ├── Auth.php            # login, register, throttling
│   ├── Csrf.php            # CSRF tokens
│   ├── Database.php        # PDO connection + first-run migration
│   ├── Request.php         # input helpers, redirects, JSON responses
│   ├── Session.php         # hardened session + flash messages
│   ├── TaskRepository.php  # every task query lives here
│   └── validation/         # one class per rule (Required, Min, Max, Email, In, Date, Str)
│
├── handle/                 # POST-only actions
│   ├── addToDo.php  edit.php  goto.php  delete.php  clearDone.php
│   └── login.php  register.php  logout.php
│
├── inc/                    # layout pieces and view helpers
│   ├── header.php  footer.php  flash.php  task_card.php  helpers.php
│
├── assets/
│   ├── css/style.css       # the whole design (~20 KB, light + dark)
│   ├── js/app.js           # board interactions
│   ├── js/theme.js         # applies the theme before first paint
│   └── img/favicon.svg
│
├── database/
│   ├── schema.sqlite.sql  schema.mysql.sql
│   └── seed.php            # demo data (CLI only)
└── docs/screenshots/
```

## How it works

```
Browser ──GET──▶ index.php ──▶ TaskRepository::board() ──▶ inc/task_card.php
   │
   └──POST (form or fetch)──▶ handle/*.php
                                 1. requirePostWithToken()   (405 / 419)
                                 2. require_login()          (redirect / 401)
                                 3. Validation               (flash errors + old input)
                                 4. TaskRepository           (scoped by user_id)
                                 5. redirect  — or JSON for drag & drop
```

When a card is moved with drag & drop, `handle/goto.php` returns the freshly rendered card HTML and the
new stats as JSON, so the page updates instantly without reloading — and the exact same endpoint still
works as a normal form post when JavaScript is off.

## Security

| Threat | How it's handled |
|---|---|
| SQL injection | PDO prepared statements everywhere, emulated prepares off |
| XSS | Output escaped with `e()` at render time; strict **Content-Security-Policy** (no inline scripts or styles) |
| CSRF | Per-session token on every form and `fetch()` call, compared with `hash_equals` |
| State changes over GET | Every handler rejects non-POST requests (405) |
| Reading other users' tasks (IDOR) | Every query is scoped by `user_id`; foreign ids return 404 |
| Passwords | `password_hash` / `password_verify`, automatic rehash, same timing for unknown emails |
| Brute force | 5 failed logins per IP → 15-minute lock |
| Session fixation / theft | ID regenerated on login, strict mode, `HttpOnly` + `SameSite=Lax` (+ `Secure` on HTTPS) |
| Clickjacking & sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy` |
| Direct file access | `.htaccess` blocks `classes/`, `inc/`, `database/`, config and SQL files on Apache |
| Invalid data | Whitelisted status / priority values, real date parsing, length limits |

## Performance

- **No CSS/JS frameworks** — the old 155 KB Bootstrap file is gone; the page loads one ~20 KB stylesheet and one small script.
- **One query** builds the whole board (instead of one per column), backed by an index on `(user_id, status)`.
- **Search and filters run in the browser** on data that is already on the page.
- **System fonts and inline SVG icons** — no font or icon downloads.
- Assets get a `?v=` cache-busting version from their modified time, so they can be cached aggressively.
- SQLite runs in WAL mode for fast reads and writes.

## Adding your own features

**A new validation rule** — drop a class in `classes/validation/`:

```php
namespace Classes\Validation;

class Url implements Validator
{
    public function check(string $label, mixed $value, array $params = []): string|false
    {
        return filter_var($value, FILTER_VALIDATE_URL) ? false : "$label must be a valid link.";
    }
}
```

and use it: `->validate('link', $link, ['Url'], 'Link')`. Rules can take parameters too: `Min:3`, `In:a,b,c`.

**A new task field** (for example `link`):

1. Add the column to both files in `database/`.
2. Add it to `TaskRepository::FIELDS` and the `INSERT` / `UPDATE` queries.
3. Validate it in `handle/taskRules.php`.
4. Show it in `inc/task_card.php` and add an input to `index.php` / `edit.php`.

**A new action** — create `handle/yourAction.php` and start it with:

```php
require_once __DIR__ . '/../App.php';

$request->requirePostWithToken();
$user = require_login();
```

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
