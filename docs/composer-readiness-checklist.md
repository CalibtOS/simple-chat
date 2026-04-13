# Phase 9 (Optional) - Composer Readiness Checklist

Use this when deciding whether to adopt Composer + PSR-4.

## Ready when these are true

- Router, handlers, and middleware concepts are clear to you.
- You are comfortable creating plain PHP classes manually.
- You understand namespaces and class/file mapping conceptually.
- You can explain dependency injection basics (constructor receives collaborators).
- Your current folder layout is stable enough to map to namespaces.

## What Composer will give you

- Autoloading (no manual `require_once` chain for classes)
- Dependency management (install/update third-party packages safely)
- Consistent project bootstrap (`vendor/autoload.php`)

## Learning exercise before enabling Composer

1. Pick one class chain:
   - `controllers/MeController` -> `services/MeService` -> `repositories/UserRepository`
2. Explain the dependency graph in words.
3. Write the equivalent namespace plan on paper.

If this is easy, you are ready for the Composer phase.

