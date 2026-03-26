# Contributing to Kintai

Thank you for your interest in contributing!

## Before You Start

Kintai is licensed under the **PolyForm Noncommercial License 1.0.0**.
By contributing, you agree that your contributions will be licensed
under the same terms.

## How to Contribute

### Reporting Bugs

Open an issue using the **Bug Report** template. Include:
- PHP version and OS
- Steps to reproduce
- Expected vs. actual behavior
- Relevant logs from `storage/logs/`

### Suggesting Features

Open an issue using the **Feature Request** template. Explain
the use case and why it fits the project's scope.

### Submitting a Pull Request

1. Fork the repository
2. Create a branch: `git checkout -b feat/my-feature` or `fix/my-bug`
3. Make your changes following the conventions below
4. Run the tests: `./vendor/bin/phpunit`
5. Open a pull request against the `main` branch

## Code Conventions

- PHP 8.3+ strict types (`declare(strict_types=1)`)
- Namespace root: `kintai\` → `src/`
- Controllers: `final class`, constructor injection, signature `method(Request): Response`
- HTTP exceptions: use the hierarchy in `src/Core/Exceptions/`
- New DB tables: add migration files in all three drivers (`sqlite/`, `mysql/`, `jsondb/`)
- Comments: in French
- No external framework dependencies

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```
