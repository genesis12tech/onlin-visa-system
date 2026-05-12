# Feature: Foundation (Milestone 0)

## Scope
Infrastructure and tooling setup. No business logic.

## Build
- Laravel 12 project with Filament 4 installed
- MySQL 8+ configured
- Redis configured
- Laravel Horizon configured with named queues
- Laravel Boost installed
- PHPUnit test runner configured
- Laravel Pint code style configured
- `docs/` folder with project documentation
- `AGENTS.md` with AI agent rules
- `app/Domain/` folder structure scaffolded
- Filament production authorization configured

## Named Queues
```
high        — time-sensitive workflow transitions
default     — general jobs
emails      — notification emails
documents   — document processing
pdfs        — PDF generation
reports     — exports and reporting jobs
```

## Acceptance Criteria
- [ ] `php artisan test` passes
- [ ] `php artisan route:list` works
- [ ] `php artisan queue:work` runs locally
- [ ] Filament admin panel loads at `/admin`
- [ ] Filament production authorization blocks non-local access
- [ ] `AGENTS.md` contains project-specific AI agent rules

## Security Requirements
- Filament panels must implement `canAccessPanel()` on the User model
- No applicant data exists yet — security policies begin in Milestone 1

## Tests Required
- Basic route test (admin panel responds with redirect or 200)
- Filament authorization test (non-local users cannot access panel)
