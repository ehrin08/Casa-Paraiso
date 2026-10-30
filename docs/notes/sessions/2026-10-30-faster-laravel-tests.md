---
type: session-log
date: 2026-10-30
---

# Faster Laravel tests

## Task

Find out why `php artisan test` was taking so long, and fix it.

## What Changed

- Found the cause while a run was in progress. After 10.5 minutes PHPUnit was at about 11% CPU and mostly in disk sleep on `p9_client_rpc`, the Docker Desktop share that bind-mounts the Windows project folder. MariaDB had one idle connection and no lock waits.
- Measured file access: about 13.5 ms per stat and read on the bind mount, and about 0.01 ms on the `vendor` volume. Each test boots Laravel again and re-reads config, routes and Blade views from the share. OPcache was off for command-line PHP, so each of those files was also compiled again.
- Added `docker/app/php/zz-casa-cli.ini`, copied into the image by the `Dockerfile`. It enables CLI OPcache with a `/tmp/opcache` file cache and checks timestamps every 2 seconds.
- `phpunit.xml` now sets `VIEW_COMPILED_PATH=/tmp/casa-testing-views`, so test views compile inside the container.
- Documented this in [DOCKER_WORKFLOW](../../DOCKER_WORKFLOW.md#test-speed) and [PROJECT_MEMORY](../../PROJECT_MEMORY.md).

## Verification

- `RoleWorkspaceTest` (15 tests): 47.6 s before, 10.7 s after with a warm cache.
- `RosterConcurrencyTest`, `HostingReadinessTest` and `DatabaseTestIsolationTest` passed (9 tests).
- Full suite: 512 tests passed in 176 s. The slowest tests are the roster concurrency cases (up to 5.7 s).
- `setup` rebuilt the image, ran migrations (none pending) and built the assets. The stack started and the app answered over HTTP.
- `git diff --check` passed.

## Decisions

- Blade's per-render timestamp check was kept. Turning it off would let stale compiled views survive between runs after a template edit.
- The ini file only affects the local Docker image. Hostinger releases do not use this `Dockerfile`.

## Follow-ups

- If tests are slow again, check for a Windows Defender scan of the project folder, or consider moving the checkout into the WSL file system.
