# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Symfony 8.1 / PHP 8.4 application that measures how the cyclomatic complexity of PHP open source software
evolved over time. Anyone can submit a GitHub repository; the app clones it, checks out each major/minor
release tag, runs phploc over it, and renders the result as a Chart.js line chart.

The dataset is **submission-driven, not curated**: github.com is the only source (no packagist, no
`composer.json` needed, so `wordpress/wordpress` works too), and `Organization` rows are derived from the
GitHub owner of whatever was submitted and are a lens on the repositories, not a place of their own. Start
page and chart focus on the most starred repositories.

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
bin/console messenger:consume scheduler_default   # the nightly release scan and star refresh
bin/console messenger:stats                       # what is still queued
bin/console debug:scheduler                       # next run of the recurring messages

# frontend — Vite, wired into Twig by symfony/reprise
yarn dev-server     # vite dev server with HMR, reprise points Twig at it
yarn dev            # one-off development build
yarn build          # production build (deploy.php sets ASSET_BASE for the sub path)
yarn check-style    # prettier over assets/
```

Tests live under `App\Tests\` (PSR-4 → `tests/`) and cover the pure domain logic (input parsing, API
mapping, release detection, the rolled up trends) — everything else needs a booted kernel and is not
covered yet.

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
`Tag` (one analysed release, holds `linesOfCode` + `averageComplexity` + `created`). `Organization` is not
a screen of its own anymore, only the account a repository is grouped under, and it holds nothing but the
GitHub `login` it is addressed by plus an avatar: the display name and homepage it used to carry came from
optional profile fields and named the wrong account as often as the right one.

**Domain services** (`src/ComplexityReport`) — the thin `src/Command` classes only wrap these:

- `GitHub/GitHubClient` — the only external source, wraps `api.github.com` (repository, owner, languages)
  behind a 1h cache. `GitHub/RepositoryIdentifier::fromInput()` parses everything users may paste
  (`owner/repo`, https/ssh urls, deep links) and rejects any host but github.com.
- `RepositorySubmitter` — validates a submission (unknown, fork, empty, larger than `MAX_SIZE`, less than
  `MIN_PHP_SHARE` PHP) and refuses everything while `MAX_PENDING` repositories are already waiting for a
  worker — the submitter picks what gets cloned, so what a submission may cost is capped before it is
  queued rather than after. It then creates the `Organization` for its owner on the fly and dispatches
  `AnalyseRepository`. Rejections are
  `Exception\SubmissionFailed`, whose messages are written to be shown to the submitter. A repository the
  report already carries is **not** one of them: `submit()` returns a `Submission` that says whether this
  submission is what queued it, so pasting a known repository is how people look it up — it is answered
  from the database, before github.com is asked at all.
- `RepositoryRefresher` — re-reads stars and metadata for everything submitted.
- `Trend/*` — the whole report rolled up into one figure per time frame (`TrendWindow`: YTD, 12 months,
  5 years, all time), shown in the hero of the start page. `TrendCalculator` is pure: it takes a list of
  `ReleasePoint`s and the current time and returns `Trend` value objects, which is why the rules live
  there and are unit tested — only libraries already measured when a window opened take part in it, each
  counts once and is represented by the release it stood at back then (all time compares every library
  against its own first release). `TrendLoader` is the thin edge: one query via
  `TagRepository::findReleasePoints()`, cached in `cache.app` for an hour under a key that carries the
  day, since the windows move with it.
- `ReleaseScanner` — which releases of a repository are missing: skips anything `GitTag::isPreRelease()`
  (contains `-`) or `isPatchRelease()` (not a plain `X.Y` / `X.Y.0` version) and anything already stored.
  `scanRemote()` reads refs with `git ls-remote` (no clone, no working copy), `scanWorkingCopy()` fetches
  the clone first.
- `RepositoryAnalyser` — measures those releases: checkout, phploc, `Tag`. Flushes after every release so a
  retry only redoes what is left, then `markAnalysed()`. It does **not** guard the working copy — that is
  the handler's job.
- `Git` / `GitController` / `CodeAnalyser` — `Git` shells out via symfony/process and logs every call on the
  `git` monolog channel; it passes arguments as a list (never a shell line), pins `GIT_TERMINAL_PROMPT=0` so
  a repository that went private fails instead of blocking a worker on a credential prompt, and gives every
  call a `TIMEOUT` so a remote that stalls mid-transfer cannot hold that worker forever. `GitController`
  adds the domain layer (clone on first use, load tags local and remote, checkout under the full
  `refs/tags/` ref, last commit date, remove/list working copies). Metrics come from phploc's `Analyser`
  (`loc`, `classCcnAvg`). Both receive the `$repositoryPath` bound globally in `config/services.yaml`.
- `SourceFiles` — which files of a working copy phploc is handed. A submitted repository decides what its
  own files are and git stores a symlink like any other file, so this drops what leaves the working copy
  (`evil.php -> /dev/zero` reads until the worker is out of memory, a link to a file elsewhere would be
  counted into the report) and what is too large to be source code. Links **within** the copy are ordinary
  and stay.
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

**Submitting** is the only write the web exposes, so `submit` spends one token of the `submission` rate
limiter (5 per 15 minutes and IP) before anything else and only then checks the CSRF token - the limiter
comes first because a request without a token costs something too, and this way a script cannot send more
of them than a visitor may send submissions. CSRF is **stateless** (`config/packages/csrf.yaml`): the token
is a double submit cookie written by Symfony's `csrf-protection` Stimulus controller, which is why the
hidden field carries `data-controller="csrf-protection"`, and a browser without javascript still passes on
the same-origin check. It is a cross-site guard, not a bot guard - the rate limiter is what bounds the
work.

Reading the report never starts a session, and these two refusals must not either: a flash writes a
session, so `refuse()` answers them with a plain `429`/`400` instead. Everything a *visitor* runs into -
an unknown repository, a fork, too little PHP - stays a flash on the start page, redirected to with the
303 turbo needs, because a human mistyping twice should not get an error page. Anything that gets through
ends on the page of its repository, whether it was just queued or has been in the report for years.

**Web** (`src/Controller/ReportController`) — two screens: `start` renders the trend in the hero, the
submit form, the rankings, a line of GitHub owners and what is still queued, and `chart` is everything
else. There is **one** chart page, not one per organization: `?repositories=symfony/console,nikic/iter`
says which repositories it draws, in that order, up to `CHART_LIMIT` (the chart has eight colours), and
anything the report carries can be added to them. Without a selection it opens on the most starred.
Repositories are addressed by the slug they carry on github.com, never by a database id - the query string
is a link people read, edit and share, so it says what it draws. The case does not matter, and slugs that
are not repositories are dropped rather than answered with an error.
`ChartSelection` is what the template reads: the series, the repositories still waiting for a worker, the
options of the picker, and what the page is called. The screen is **not** named after the way it was
reached - it is named after what is in it, by one rule that survives the chart being edited in place: the
repository itself while it is the only one, a count as soon as there are more. `chart_controller.js`
applies the same rule to the headline, its github.com link and the document title whenever the selection
changes, which is why the rule has to stay this small. A repository has a chart from the moment it is
submitted: no releases yet means no lines but a status telling the visitor it is queued or being measured
right now - the one state the browser cannot rename, since nothing can be picked in it.

Routes are distinguished by `priority`, since `{organization}` and the slug of a repository both match
whatever is left: `chart`/`search`/`submit` (3) > `repository` (2, `owner/repository`, returns the releases
as JSON - the slug is the whole route, so the select box can request it relative to the page it is on) >
`organization` (1). The last one is the page GitHub accounts used to have, kept as a permanent redirect
into the chart - `?repository=<id>` included, since that is how the links that were handed out address a
repository. `Repository::asGraph()` returns a `GraphData` value object that JSON-serializes into what the
chart expects.

**Frontend** — Vite + symfony/reprise + StimulusBundle. `assets/controllers/chart_controller.js` reads the
preselected repositories from a `data-repositories` attribute rendered by `templates/chart.html.twig`, and
lazily fetches additional ones from the `repository` JSON route when picked in the select2 box - what is
picked is written back into the query string, so a chart someone put together is a link. The release
analysis below the chart is one tab per line, built from the same graphs: every repository in the chart can
be read release by release, and a tab carries the colour of its series - and since a point in the chart
*is* a release, clicking one opens it there: the line it belongs to becomes the tab being read and the
point the release, scrolled to only when the section is off screen. The chart zooms and pans
(chartjs-plugin-zoom), which the address bar knows nothing about, so the way back is the `.chart-reset`
button floating over it - rendered `hidden` and shown by the controller only while `isZoomedOrPanned()`.
`trend_controller.js` switches the time frame of the hero figure, which is rendered for all four windows
at once, so nothing is fetched when one is picked.
`refresh_controller.js` reloads the page every 30s while the status above the chart says a repository is
queued or being measured - it is only rendered in that state, so the reloading stops by itself once the
data is there, and a backgrounded tab skips its turn. Page transitions are Turbo Drive (`@hotwired/turbo`,
imported in `assets/app.js`; swup and its per-page controller are gone): it takes over every link and form
of the site at once, the fade is the browser's view transition enabled by the `view-transition` meta tag,
and `submit` answers with **303** because turbo follows a form's redirect itself. What Turbo caches for the
back button is the page as it looks when it is left, so `chart_controller.js` tears its select2 box down on
`turbo:before-cache` as well as on disconnect. `vite.config.js` takes the public path from `ASSET_BASE`
(set by the build task in `deploy.php`) rather than the build mode, so local production builds keep working.

**Error reporting** (`config/packages/sentry.yaml`) — sentry/sentry-symfony, registered by hand since
`allow-contrib` is false and its recipe therefore never ran. `SENTRY_DSN` is empty in the committed `.env`
and an empty DSN makes the SDK collect and send nothing, so the bundle is a no-op outside production, where
the DSN lives in the shared `.env.local`. Two exclusions are deliberate: 404/405 are `ignore_exceptions`
(what bots produce, and monolog excludes the same codes), and `messenger.capture_soft_fails: false` keeps
retried messages out - `AnalyseRepositoryHandler` throws `RecoverableMessageHandlingException` for a busy
repository by design, so only what reaches the `failed` transport is reported. Tracing is disabled: this is
error reporting, not performance monitoring. The `build` task in `deploy.php` writes the deployed revision
to `SENTRY_RELEASE` in a per release `.env.prod.local` before `dotenv:dump` picks it up.

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
