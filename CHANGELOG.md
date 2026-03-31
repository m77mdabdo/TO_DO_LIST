# Changelog

## 2.0.0 — March 2026

The app was rebuilt from the inside. It looks and works the same for users, but the code is new.

### Changed
- Every request now goes through `public/index.php`, a router and middleware, instead of one PHP file per page.
- Pages are thin controllers (`AuthController`, `BoardController`, `TaskController`) on top of services
  (`TaskService`, `AuthService`) and repositories (`PdoTaskRepository`, `PdoUserRepository`).
- Templates moved to `views/` and receive typed objects (`Task`, `User`, `Board`) instead of arrays.
- Status, priority and due dates are now enums and value objects with their own rules.
- Config moved to `config/app.php` (local overrides in `config/local.php`), routes to `config/routes.php`.
- Clean URLs: `/login`, `/tasks/12/edit`, `/tasks/12/move` …
- Only `public/` is reachable from the browser.

### Added
- A service container that wires every interface to its implementation in one place.
- A test suite with unit, integration and feature tests, and a dependency-free runner (`php tests/run.php`).
- GitHub Actions running the tests on PHP 8.1 – 8.4.
- `docs/ARCHITECTURE.md`.

### Removed
- `classes/`, `handle/`, `inc/` and the old page scripts.

## 1.0.0 — February 2026

- Kanban board with To do, Doing and Done columns, drag & drop and quick add.
- Priorities, due dates, lists, search and filters.
- Accounts with sign-up, login throttling and CSRF protection.
- Dark mode, responsive layout, SQLite or MySQL.
