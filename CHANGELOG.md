# Changelog

All notable changes to `andika/tameng` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] — 2026-08-28

### Added

- Typed policy method signatures for PHPStan level 7 compatibility (`User $user`, `StudyTracker $model`)
- Automatic `use` statement imports in generated policy files
- Auth user model resolved from guard provider config
- Resource model resolved from `getModel()` for type hints

### Fixed

- PHPStan CI failure caused by untracked `database` path in `phpstan.neon.dist`

## [1.1.0] — 2026-08-26

### Added

- Configurable navigation (group, label, icon, sort) via `config/tameng.php` and `TamengPlugin` fluent API

### Fixed

- Optimized N+1 permission queries in role permission cards (batched `whereIn` instead of per-entity `exists()`)
- Fixed fragile model resolution in `PermissionHelper::entityName()` (uses static `getModel()` instead of constructor instantiation)
- Fixed misleading `tameng:super-admin` command syntax in README

### Changed

- Minimum PHP version raised to ^8.3 (required by `spatie/laravel-permission ^8.0`)
- Minimum Laravel version raised to 12+ (Laravel 11 blocked by security advisories)
- Dropped Laravel 11 and PHP 8.2 from CI matrix

### Removed

- Empty stub methods from `TamengServiceProvider`
- Commented-out `ModelFactory.php` and empty `database/factories/` directory
- Unused placeholder comments from config and tests

## [1.0.0] — Public Release - 2026-24-08

> First stable release of Tameng — a Filament 5 plugin for role & permission management built on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission).

### Features

- 🛡️ **Role Management** — manage roles and permissions from a single page with embedded permission cards
    - 📦 Resource Permissions
    - 📄 Page Permissions
    - 🧩 Widget Permissions
    - ✨ Custom Permissions
- 🤖 **Automatic Permission Generation** — scans panels and creates permissions by convention
    - 📜 Policy generation from publishable stubs
    - 🏷️ Configurable case formatting (snake, kebab, pascal, camel, upper_snake)
    - 🔗 Entity discovery across all panels
- 👑 **Super Admin Bypass** — users with the configured role skip all authorization checks
- 🔄 **Multi-tenancy Support** — automatic tenant scoping with spatie teams + Filament panel tenancy
- 🎨 **Intuitive UI** — Shield-inspired card grid layout with icons per entity type
- ⚡ **Fine-grained CLI Tooling** — install, generate, and super-admin commands
