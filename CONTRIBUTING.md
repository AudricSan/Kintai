# Contributing to Kintai

🌐 **English** · [Français](CONTRIBUTING.fr.md) · [日本語](CONTRIBUTING.ja.md)

Thank you for your interest in contributing!

## Before You Start

Kintai is licensed under the **GNU Affero General Public License v3.0** (AGPL-3.0).
By contributing, you agree that your contributions will be licensed under the same terms.

## How to Contribute

### Reporting Bugs

Open an issue using the **Bug Report** template. Include:
- PHP version and OS
- Steps to reproduce
- Expected vs. actual behavior
- Relevant logs from `storage/logs/`

### Suggesting Features

Open an issue using the **Feature Request** template. Explain the use case and why it fits the project's scope — see [docs/vision.md](docs/vision.md) for the product direction.

### Submitting a Pull Request

1. Fork the repository
2. Create a branch: `git checkout -b feat/my-feature` or `fix/my-bug`
3. Make your changes following the conventions below
4. Run the tests: `./vendor/bin/phpunit`
5. Open a pull request against the `main` branch

## Code Conventions

- PHP 8.3+, strict types (`declare(strict_types=1)`) in every file
- Namespace root: `kintai\` → `src/`
- Controllers: `final class`, constructor injection, signature `method(Request $request): Response` — route parameters are read via `$request->param('name')`, never as method arguments
- Persistence goes through repository interfaces (`src/Core/Repositories/*Interface.php`) bound in `RepositoryServiceProvider` — controllers and services never touch Eloquent models directly
- HTTP-level errors use the exception hierarchy in `src/Core/Exceptions/`
- New DB tables: add one Eloquent migration in `database/migrations/php/` — it must work for both SQLite and MySQL (no per-driver files)
- SQLite here does not enforce `ON DELETE CASCADE` — delete dependent rows explicitly in repository code
- Comments in code are written in French; everything else (commit messages, PR descriptions, docs) in English
- No external framework dependencies beyond `illuminate/database` (Eloquent, used as an ORM only)
- Never use inline `style="..."` in views — extend a CSS module under `public/assets/css/src/` instead

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```

Every new or changed feature should come with PHPUnit tests under `tests/Unit/` (and `tests/Integration/` where relevant).

## Documentation languages

README, CHANGELOG, CONTRIBUTING, SECURITY and everything under `docs/` ship in English, French (`.fr.md`) and Japanese (`.ja.md`). English is the source of truth — if you change an English doc, updating the translations is appreciated but not required to get a PR merged. `php scripts/check-translations.php` reports missing or stale translations (non-blocking, runs in CI on every push).

## Where to look first

- [docs/architecture.md](docs/architecture.md) — how the framework is put together
- [docs/database.md](docs/database.md) — models, repositories, migrations
- [CHANGELOG.md](CHANGELOG.md) — what's been done recently
