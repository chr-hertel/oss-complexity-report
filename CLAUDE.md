# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Symfony 8.1 / PHP 8.4 application that measures how the cyclomatic complexity of PHP open source software
evolved over time. Anyone can submit a GitHub repository; the app clones it, checks out each major/minor
release tag, runs phploc over it, and renders the result as a Chart.js line chart.

The dataset is **submission-driven, not curated**: github.com is the only source (no packagist, no
`composer.json` needed, so `wordpress/wordpress` works too), and `Organization` rows are derived from the
GitHub owner of whatever was submitted. Start page and overview focus on the most starred repositories.

Requires PHP 8.4, Node (see `.nvmrc`) / Yarn and PostgreSQL 15 (`docker-compose up -d` starts one on port
**8432**, matching `DATABASE_URL` in `.env`). `GITHUB_TOKEN` in `.env.local` is optional and only raises the
API rate limit.

## Commands

```bash
# setup
composer install && yarn install && yarn build
docker-compose up -d
symfony serve -d

# full local check (mirrors CI, plus yarn audit and prettier)
bin/check

# individual checks — CI runs these directly, bin/check wraps them in `symfony php`
vendor/bin/php-cs-fixer fix          # --dry-run to only report
vendor/bin/phpstan analyse           # level 8, src/ + tests/ (psalm was dropped)
vendor/bin/phpunit
vendor/bin/phpunit --filter testName tests/Some/FileTest.php   # single test
bin/console lint:yaml config --parse-tags
bin/console lint:twig templates
bin/console lint:container
bin/console doctrine:schema:validate --skip-sync   # CI skips the sync check
bin/console doctrine:migrations:diff              # after changing an entity

# async
bin/console messenger:consume async -vv           # the analysis queue
bin/console messenger:consume scheduler_nightly   # the nightly release scan and star refresh
bin/console messenger:stats                       # what is still queued
bin/console debug:scheduler                       # next run of the recurring messages

# frontend — Vite, wired into Twig by symfony/reprise
yarn dev-server     # vite dev server with HMR, reprise points Twig at it
yarn dev            # one-off development build
yarn build          # production build (deploy.php sets ASSET_BASE for the sub path)
yarn check-style    # prettier over assets/
```

Tests live under `App\Tests\` (PSR-4 → `tests/`) and cover the pure domain logic (input parsing, API
mapping, release detection) — everything else needs a booted kernel and is not covered yet.

## Rebuilding the dataset

Order matters; each command depends on the previous one:

```bash
bin/console doctrine:database:drop --force
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate # schema is managed by doctrine/migrations, not schema:create
bin/console cache:pool:clear cache.app

bin/console doctrine:fixtures:load -n   # submits the seed repositories in AppFixtures (hits the GitHub API)
bin/console app:data:aggregate          # queues an AnalyseRepository message per repository
bin/console messenger:consume async -vv # the actual work: clone, checkout tags, phploc → Tag rows
bin/console app:data:fix -vv            # per-repository data corrections
bin/console app:statistics              # counts + total LOC
```

`app:data:aggregate` only dispatches — nothing happens until a worker consumes `async`, and `app:data:fix`
needs the queue to be empty (`messenger:stats`) to see all the data.

Day-to-day there is no rebuild and nothing to run by hand: submitting dispatches the analysis, and the
nightly schedule looks for new releases and refreshes stars.

Analysis is slow (clones the repository) and leans on the `cache.app` filesystem pool: GitHub responses
expire after 1h, but **per-tag phploc analyses are cached without expiry**. Stale or wrong numbers usually
mean the pool needs clearing, not that the code is broken.

Clones live in `repositories/<owner>/<repository>` (gitignored, a shared dir in deployment) and are
**scratch space, not a cache** — `RepositoryAnalyser` removes the working copy when it is done, so the disk
is bounded by what is being analysed rather than by everything ever submitted. It also asks the remote
before cloning at all, so an up-to-date repository costs one `ls-remote`. `app:repositories:clean` sweeps
what predates that, including directories that no repository maps to anymore (the packagist-era names).

## Architecture

**Entities** (`src/Entity`, Doctrine attributes on constructor-promoted properties, no setters except
`Tag::setCreated` for the fixers and `update()` for GitHub refreshes): `Organization` (a GitHub account,
e.g. `symfony`) → `Repository` (a GitHub repository, e.g. `symfony/console`, holds `stars` + `analysed`) →
`Tag` (one analysed release, holds `linesOfCode` + `averageComplexity` + `created`). `Organization` has no
curated main library anymore — `getMainRepository()` returns its most starred analysed repository, and it
holds nothing but the GitHub `login` it is addressed by plus an avatar: the display name and homepage it
used to carry came from optional profile fields and named the wrong account as often as the right one.

**Domain services** (`src/ComplexityReport`) — the thin `src/Command` classes only wrap these:

- `GitHub/GitHubClient` — the only external source, wraps `api.github.com` (repository, owner, languages)
  behind a 1h cache. `GitHub/RepositoryIdentifier::fromInput()` parses everything users may paste
  (`owner/repo`, https/ssh urls, deep links) and rejects any host but github.com.
- `RepositorySubmitter` — validates a submission (unknown, fork, empty, less than `MIN_PHP_SHARE` PHP),
  creates the `Organization` for its owner on the fly and dispatches `AnalyseRepository`. Rejections are
  `Exception\SubmissionFailed`, whose messages are written to be shown to the submitter. A repository the
  report already carries is **not** one of them: `submit()` returns a `Submission` that says whether this
  submission is what queued it, so pasting a known repository is how people look it up — it is answered
  from the database, before github.com is asked at all.
- `RepositoryRefresher` — re-reads stars and metadata for everything submitted.
- `ReleaseScanner` — which releases of a repository are missing: skips anything `GitTag::isPreRelease()`
  (contains `-`) or `isPatchRelease()` (not a plain `X.Y` / `X.Y.0` version) and anything already stored.
  `scanRemote()` reads refs with `git ls-remote` (no clone, no working copy), `scanWorkingCopy()` fetches
  the clone first.
- `RepositoryAnalyser` — measures those releases: checkout, phploc, `Tag`. Flushes after every release so a
  retry only redoes what is left, then `markAnalysed()`. It does **not** guard the working copy — that is
  the handler's job.
- `Git` / `GitController` / `CodeAnalyser` — `Git` shells out via symfony/process and logs every call on the
  `git` monolog channel; it pins `GIT_TERMINAL_PROMPT=0` so a repository that went private fails instead of
  blocking a worker on a credential prompt. `GitController` adds the domain layer (clone on first use, load
  tags local and remote, checkout, last commit date, remove/list working copies). Metrics come from phploc's
  `Analyser` (`loc`, `classCcnAvg`). Both receive the `$repositoryPath` bound globally in
  `config/services.yaml`.
- `WorkingCopyLock` — one lock per repository, taken by everything that touches its working copy.
- `DataFixer` + `DataFixer/*Fixer` — post-processing for datasets where git history lies (Laminas tags all
  dated at the Zend→Laminas import, PHPUnit tags with a bogus 2006 date, Laravel minors that distort the
  chart). They must no-op when their repository was never submitted. Implementing `FixerInterface` is
  enough: `_instanceof` in `config/services.yaml` tags it `complexity_report.data_fixer` and injects it
  into `DataFixer`.

**Async** (`src/Message`, `src/MessageHandler`, `src/Schedule.php`) — messenger over the doctrine transport
(`async`, plus `failed` for what did not survive its retries; both `auto_setup=0`, the table comes from a
migration). `ScanForNewReleases` fans out into one `ScanRepository` per repository, which asks github.com
for refs and only dispatches `AnalyseRepository` when a release is actually missing — so the nightly run
neither clones nor queues anything for a repository that did not release. `RefreshRepositories` wraps
`RepositoryRefresher`. The nightly `Schedule` is `stateful()` (a deploy restarting the worker must not
re-trigger the night) and `lock()`ed, and hands both tasks to `async` via `RedispatchMessage` instead of
running them in the schedule worker.

`AnalyseRepositoryHandler` takes a `flock` per repository before analysing: checking out a tag rewrites the
shared working copy, so a second worker on the same repository would silently measure the wrong code. A
busy repository throws `RecoverableMessageHandlingException` and is retried — deliberately without
consuming the retry budget, since being busy is not a failure.

**Submitting** is the only write the web exposes, so `submit` checks a CSRF token before anything else and
then spends one token of the `submission` rate limiter (5 per 15 minutes and IP). CSRF is **stateless**
(`config/packages/csrf.yaml`): the token is a double submit cookie written by Symfony's `csrf-protection`
Stimulus controller, which is why the hidden field carries `data-controller="csrf-protection"` and why
reading the report never starts a session. Both rejections are flashes, not exceptions - a human mistyping
twice should not get an error page. Anything that gets through ends on the page of its repository, whether
it was just queued or has been in the report for years.

**Web** (`src/Controller/ReportController`) — routes are distinguished by `priority`, since
`{organization}` and `{id}` both match a single segment: `overview`/`submit` (3) > `repository` (2, digits
only, returns JSON) > `organization` (1, resolves the `Organization` entity from its `login`, optional
`?repository=<id>` preselects one of its repositories). An organization page exists from the moment
something was submitted for it - a repository that carries no releases yet has no chart but a status
telling the visitor it is queued or being measured right now. `start` renders the submit form, the most
starred repositories, the organizations and what is still queued. `Repository::asGraph()` returns a
`GraphData` value object that
JSON-serializes into what the chart expects.

**Frontend** — Vite + symfony/reprise + StimulusBundle. `assets/controllers/chart_controller.js` reads the
preselected repositories from a `data-repositories` attribute rendered by `templates/chart.html.twig`, and
lazily fetches additional ones from the `repository` JSON route when picked in the select2 box. Page
transitions use symfony/ux-swup. `vite.config.js` takes the public path from `ASSET_BASE` (set by the build
task in `deploy.php`) rather than the build mode, so local production builds keep working.

## Conventions

- `declare(strict_types=1)` and `final` on every class in `src/` (the Doctrine entities are the exception
  to `final`), `final readonly` for stateless services; constructor property promotion throughout; typed
  class constants; php-cs-fixer `@Symfony` ruleset.
- Commands are invokable: `final readonly` classes with `__invoke(SymfonyStyle $io, ...)` and
  `#[Argument]` / `#[Option]` attributes - they do not extend `Command`.
- Value objects (`Analysis`, `Statistics`, `GitTag`, `GraphData`, `GitHub/*Data`) are immutable and readonly
  where possible.
- PHPStan runs at level 8 — keep generic annotations on repositories (`@extends ServiceEntityRepository<X>`,
  `@method` hints for magic finders) and array shapes on value objects so it stays clean.
- Pushing to `main` triggers a Deployer release to christopher-hertel.de; PRs run the checks above.
