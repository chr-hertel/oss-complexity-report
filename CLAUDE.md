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
bin/console app:repository:analyse moodle/moodle  # measure one repository here instead of in a worker
bin/console debug:scheduler                       # next run of the recurring messages

# frontend — Vite, wired into Twig by symfony/reprise
yarn dev            # vite dev server with HMR, reprise points Twig at it
yarn build          # production build (deploy.php sets ASSET_BASE for the sub path)
yarn check-style    # prettier over assets/
```

Tests live under `App\Tests\` (PSR-4 → `tests/`) and cover the pure domain logic (input parsing, API
mapping, release detection, the rolled up trends, the raw output printed from a measurement) — everything
else needs a booted kernel and is not covered yet.

## Rebuilding the dataset

Order matters; each command depends on the previous one:

```bash
bin/console doctrine:database:drop --force
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate # schema is managed by doctrine/migrations, not schema:create
bin/console cache:pool:clear cache.app

bin/console doctrine:fixtures:load -n   # submits the seed repositories in AppFixtures, which queues them
bin/console messenger:consume async -vv # the actual work: clone, checkout tags, phploc → Tag rows
bin/console app:data:fix -vv            # per-repository data corrections
bin/console app:statistics              # counts + total LOC
```

Submitting is what dispatches `AnalyseRepository`, so nothing queues the seed repositories by hand —
`app:releases:scan` is how a run that was interrupted is picked up again, since it asks github.com which
releases are still missing. `app:data:fix` needs the queue to be empty (`messenger:stats`) to see all the
data.

`app:repository:analyse <owner/repository>` is the exception to all of that: it runs `RepositoryAnalyser`
**in the console process** rather than dispatching, which is the only way to watch a repository that does
not get through the queue. A worker measures where nobody looks, and an analysis that dies without
throwing — a memory limit, an OOM kill — is never acked, so the transport hands the same message back and
it dies again on the next delivery, forever, while the repository stays `analysed IS NULL` and the report
keeps calling it queued. Run on the console it can be given `php -d memory_limit=…`, watched release by
release and stopped. It takes the same `WorkingCopyLock` a worker takes, so it refuses rather than
measuring against a checkout somebody else is rewriting, and prints the peak memory of the run since that
is usually what is being chased. `--queue` dispatches instead, for the one repository the nightly scan
would skip.

Filling a fresh instance with more than the fixtures is `app:repository:submit --file --wait`, pointed at
`data/top-php-repositories.txt` (an operator list, not part of the dataset definition — nothing reads it on
its own). It goes through `RepositorySubmitter` like any other submission, so the same rejections apply, and
`--wait` blocks on `MAX_PENDING` instead of giving up on the rest of the list — which means it only makes
progress while a worker is consuming `async`. Re-running it is free: repositories the report already carries
come back as `Submission::known`.

Day-to-day there is no rebuild and nothing to run by hand: submitting dispatches the analysis, and the
nightly schedule looks for new releases and refreshes stars.

Analysis is slow (clones the repository) and leans on the `cache.app` filesystem pool: GitHub responses
expire after 1h, but **per-tag phploc analyses are cached without expiry**. Stale or wrong numbers usually
mean the pool needs clearing, not that the code is broken. The key of such an analysis carries a version
(`…_analysis_v2`), because an entry without an expiry has no other way of going away — and what an
`Analysis` holds grew when the full phploc measurement started being kept.

Clones live in `repositories/<owner>/<repository>` (gitignored, a shared dir in deployment) and are
**scratch space, not a cache** — `RepositoryAnalyser` removes the working copy when it is done, so the disk
is bounded by what is being analysed rather than by everything ever submitted. It also asks the remote
before cloning at all, so an up-to-date repository costs one `ls-remote`. `app:repositories:clean` sweeps
what predates that, including directories that no repository maps to anymore (the packagist-era names).

## Architecture

**Entities** (`src/Entity`, Doctrine attributes on constructor-promoted properties, no setters except
`Tag::setCreated` for the fixers and `update()` for GitHub
refreshes): `Organization` (a GitHub account,
e.g. `symfony`) → `Repository` (a GitHub repository, e.g. `symfony/console`, holds `stars` + `analysed`) →
`Tag` (one analysed release, holds `linesOfCode` + `averageComplexity` + `created`, plus `metrics`: the
whole phploc measurement those two are read out of, which every release carries — the column was nullable
while the releases measured before it existed were being re-measured, and is not anymore).
`Organization` is not
a screen of its own anymore, only the account a repository is grouped under, and it holds nothing but the
GitHub `login` it is addressed by plus an avatar: the display name and homepage it used to carry came from
optional profile fields and named the wrong account as often as the right one.

An entity answers what it **is**, never what the report **makes of it**: `Repository` used to carry the
complexity, the size and the evolution of its releases, which meant printing a card loaded every release
of every repository. Those are figures of a page and live where the page reads them (`RankedRepository`).
What is left of that association is `getTags()` for the code that actually works on releases (the
analyser, the fixers, `GraphData`) plus `hasData()` and `getReleaseCount()` — and the
collection is mapped `EXTRA_LAZY`, so those two are a `COUNT` rather than twenty thousand hydrated `Tag`s
with their phploc measurements.

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
- `ComplexityLevel` — the four risk bands a cyclomatic complexity is read in (1–10 simple, 11–20
  moderate, 21–50 complex, above that untestable). It is what the scale of the about block is printed from and
  what colours every complexity the report shows, so the bands are written down once — the report measures
  averages, so a band ends where its number does. `chart_controller.js` mirrors the thresholds, because
  the release analysis is built in the browser; like the naming rule of the chart, the mirror only holds
  as long as the rule stays this small.
- `Trend/*` — the whole report rolled up into one figure per time frame (`TrendWindow`: YTD, 12 months,
  5 years, all time), shown in the hero of the start page. `TrendCalculator` is pure: it takes a list of
  `ReleasePoint`s and the current time and returns `Trend` value objects, which is why the rules live
  there and are unit tested — only libraries already measured when a window opened take part in it, each
  counts once and is represented by the release it stood at back then (all time compares every library
  against its own first release). `TrendLoader` is the thin edge: one query via
  `TagRepository::findReleasePoints()`, cached in `cache.app` for an hour under a key that carries the
  day, since the windows move with it.
- `Ranking` + `RankingLoader` / `RankedRepository` / `ReleaseSummary` / `Vendor` — the rankings of the
  start page, and the one rule they follow: **nothing the page shows is counted by loading releases.** A
  card is about three of the twenty thousand releases the report carries — the first, the latest, and the
  one a repository stood at twelve months ago (`RankedRepository::RECENT`) — so
  `TagRepository::findReleaseSummaries()` lets Postgres pick them, one `ReleaseSummary` row per repository
  instead of one row per release. It is native SQL (`array_agg(… ORDER BY …)[1]`, plus a `FILTER` for the
  window) because DQL knows neither, which is the price: that query spells out table and column names.
  What the engine does **not** do is the arithmetic — the percent change and the two cases that have
  nothing to compare against are rules of the report, so they stay in `RankedRepository` and are unit
  tested, the way the `Trend/*` rules are. `Ranking` then sorts those in memory; four orders over the same
  list is not four queries. `Vendor` is the same idea for the pill row closing the page:
  `OrganizationRepository::findVendors()` groups it in one query (`string_agg` for the names a pill links
  to, `HAVING count(*) >= Vendor::MINIMUM` for what is worth naming), rather than loading every account,
  its repositories, and their releases to count them. This is what the start page cost before: it
  hydrated the whole report — every `Tag`, each with the phploc measurement it keeps — twice over, and
  once every release carried its `metrics`, 128M of PHP memory was no longer enough to render it.
- `ReleaseScanner` — which releases of a repository are missing: skips anything `GitTag::isPreRelease()`
  (contains `-`) or `isPatchRelease()` (not a plain `X.Y` / `X.Y.0` version), anything `ExcludedReleases`
  leaves out and anything already stored. `scanRemote()` reads refs with `git ls-remote` (no clone, no
  working copy), `scanWorkingCopy()` fetches the clone first.
- `ExcludedReleases` — the releases the report does not measure although they are proper minors (Laravel
  minors released out of order, everything PHPUnit tagged before 3.0 onto one 2006 import date). It is
  what the scanner asks, because deleting such a release afterwards does not hold: not stored is what
  "missing" means to the scanner, so the next nightly run cloned the repository and measured it back in.
- `RepositoryAnalyser` — measures those releases: checkout, phploc, `Tag`. Flushes after every release so a
  retry only redoes what is left, then `markAnalysed()`. It does **not** guard the working copy — that is
  the handler's job.
- `PhplocReport` — a stored measurement printed the way the phploc command line prints it, by handing it to
  phploc's own `Log\Text`. There is no second layout to keep in step with the tool, which is why the modal
  can be called raw output; the printer writes to standard output, so this catches it in a buffer.
- `Git` / `GitController` / `CodeAnalyser` — `Git` shells out via symfony/process and logs every call on the
  `git` monolog channel; it passes arguments as a list (never a shell line), pins `GIT_TERMINAL_PROMPT=0` so
  a repository that went private fails instead of blocking a worker on a credential prompt, and gives every
  call a `TIMEOUT` so a remote that stalls mid-transfer cannot hold that worker forever. `GitController`
  adds the domain layer (clone on first use, load tags local and remote, checkout under the full
  `refs/tags/` ref, last commit date, remove/list working copies). Metrics come from phploc's `Analyser`:
  the report is drawn from `loc` and `classCcnAvg`, and `Analysis` keeps the whole array anyway — it is
  what the raw output of a release is printed from, and dropping it is what made every other number a
  re-clone away. Both receive the `$repositoryPath` bound globally in `config/services.yaml`.
- `SourceFiles` — which files of a working copy phploc is handed. A submitted repository decides what its
  own files are and git stores a symlink like any other file, so this drops what leaves the working copy
  (`evil.php -> /dev/zero` reads until the worker is out of memory, a link to a file elsewhere would be
  counted into the report) and what is too large to be source code. Links **within** the copy are ordinary
  and stay.
- `WorkingCopyLock` — one lock per repository, taken by everything that touches its working copy.
- `DataFixer` + `DataFixer/*Fixer` — post-processing for datasets where git history lies: `Laminas
  VersionFixer` reads the date git still knows for tags that all sit on the Zend→Laminas import, and
  `ExcludedReleaseFixer` clears releases measured before `ExcludedReleases` listed them. A fixer corrects
  what is stored — keeping a release out of the report is the scanner's job, not a delete that the next
  scan undoes. They must no-op when their repository was never submitted. Implementing `FixerInterface` is
  enough: `_instanceof` in `config/services.yaml` tags it `complexity_report.data_fixer` and injects it
  into `DataFixer`.

**Async** (`src/Message`, `src/MessageHandler`, `src/Schedule.php`) — messenger over the doctrine transport
(`async`, plus `failed` for what did not survive its retries; both `auto_setup=0`, the table comes from a
migration). `ScanForNewReleases` fans out into one `ScanRepository` per repository, which asks github.com
for refs and only dispatches `AnalyseRepository` when a release is actually missing — so the nightly run
neither clones nor queues anything for a repository that did not release. `RefreshRepositories` wraps
`RepositoryRefresher`. The
`Schedule` is `stateful()` (a deploy restarting the worker must not re-trigger the night) and `lock()`ed,
and hands every task to `async` via `RedispatchMessage` instead of running it in the schedule worker.

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
submit form, the rankings, then the block explaining the report, and below it the two quiet lists that
close the page - the GitHub vendors (only those grouping more than one measured repository - a single one
is the repository the rankings already link to), ordered by how many measured repositories they account
for, and the latest additions, which are two columns because something comes in in two ways:
`LATEST_LIMIT` repositories newest submission first, measured or not - the rankings only carry what has
numbers, so this is the one place a repository shows up the day it was added, marked while it is `queued`
or being analysed - and next to them `LATEST_LIMIT` releases, ordered by `Tag` id, since when a release
was measured is not stored and the id is the order the rows were written in; a repository is submitted
once, its releases keep arriving from the nightly scan. All three lists are the same `.pill`: a name on
light ground joined to the one thing said about it on dark - a count, a state, a version - so what
differs between them is only what their dark half carries, and nothing surrounds the two halves (`.badge`
is the single-ground count of the design system, which is a different thing). `chart` is everything
else. There is **one** chart page, not one per organization: `?repositories=symfony/console,nikic/iter`
says which repositories it draws, in that order, and anything the report carries can be added to them.
There is **no** cap on how many: a vendor of fifty measured repositories is a chart of fifty, and what
bounds a query string is the report itself, since it cannot name more repositories than exist -
`CHART_DEFAULT` is only where a chart *starts* when nobody picked anything, the eight most starred, one
per colour of the palette. Past the eighth line the palette starts over, and each pass through it is
stroked differently (`SERIES_DASHES`), so a ninth line shares its colour but not its look; past a full
palette the chart legend is dropped, because the picker chips above say the same thing in the same
colours without taking the height the chart is drawn in.
Repositories are addressed by the slug they carry on github.com, never by a database id - the query string
is a link people read, edit and share, so it says what it draws. The case does not matter, and slugs that
are not repositories are dropped rather than answered with an error.
`?metric=loc` says the lines are drawn as lines of code instead - the switch above the chart
(`chart_controller.js`, `METRICS`), which is a redraw and not a fetch, since a release is two numbers and
both are in every graph the page already has. Complexity is what the report is about, so it is what a
chart opens on and it carries no `metric` at all; only the other one is written into the query string.
Switching resets the zoom, because a window onto a complexity of four says nothing about a codebase of
half a million lines. The metric is read in the browser and nowhere else: it decides how the same data is
drawn, not which data the server has to render.
`ChartSelection` is what the template reads: the series, the repositories still waiting for a worker, the
options of the picker, and what the page is called. The screen is **not** named after the way it was
reached - it is named after what is in it, by one rule that survives the chart being edited in place: the
repository itself while it is the only one, a count as soon as there are more. `chart_controller.js`
applies the same rule to the headline, its github.com link and the document title whenever the selection
changes, which is why the rule has to stay this small. A repository has a chart from the moment it is
submitted: no releases yet means no lines but a status telling the visitor it is queued or being measured
right now - the one state the browser cannot rename, since nothing can be picked in it.

What the report does not say by itself is `templates/about.html.twig`: how a number gets into it, what
that number is worth, and the scale it is read against. It is not the footer anymore - the footer is the
credits - but a band each screen places itself, since where it belongs differs: on the start page it
stands under the rankings and before the lists closing them, on the chart page it ends the screen,
rendered outside `<main>` so it is the element right before the credits and `.about + .site-footer`
closes the two into the one band they were.

The **scale** (`templates/complexity_scale.html.twig`) opens that block, across both columns that explain
the report rather than inside the one about the metric - it is what every number of the report is read
against, so it comes before the texts, and at half the width every band would set its risk in two lines.
It is the four bands of `ComplexityLevel` over the ramp they run through,
the bands washed white so their ranges stay readable and only the hairlines between them and the strip
below them carry the colour at full strength. On a phone the ramp turns and the bands stack on it,
which is the same reading in the direction there is room for. Every complexity elsewhere is marked with a
dot in the colour of its band - on a card it stands where the `Ø` stood, since a metric leads with one
glyph and the row has no width to spare, and in the release analysis it leads the numbers that are
complexities rather than changes to one.

Routes are distinguished by `priority`, since `{organization}` and the slug of a repository both match
whatever is left — and every one of them is **negative**, because routing sorts the whole collection and
not the controller: a positive priority puts a catch-all in front of what the framework registers, and
`/_wdt/{token}` and `/_profiler/{token}` are two segments like every repository slug, so the profiler
answered 404 as a repository nobody submitted. Below zero the report is what is left over, which is what it
is: `chart`/`search`/`submit` (-1) > `repository` (-2, `owner/repository`, returns one line of
the chart as JSON - the slug is the whole route, so the select box can request it relative to the page it
is on) and `raw` (-2, `owner/repository/<tag>/raw`, one release as phploc printed it, `text/plain` and
cacheable for a day since a released tag does not get measured differently again; a release the report does
not carry answers 404) > `organization` (-3). The last one is the page GitHub accounts used to have, kept as a permanent
redirect into the chart - `?repository=<id>` included, since that is how the links that were handed out
address a repository. `Repository::asGraph()` returns a `GraphData` value object that JSON-serializes into
what the chart expects: the releases it draws plus the github.com url and the stars of the repository they
belong to, so a line picked later carries the same facts as one the page was rendered with.

**Frontend** — Vite + symfony/reprise + StimulusBundle. `assets/controllers/chart_controller.js` reads the
preselected repositories from a `data-repositories` attribute rendered by `templates/chart.html.twig`, and
lazily fetches additional ones from the `repository` JSON route when picked in the box above it - what is
picked is written back into the query string, so a chart someone put together is a link. The release
analysis below the chart is one tab per line, built from the same graphs: every repository in the chart can
be read release by release, and a tab carries the colour of its series - and since a point in the chart
*is* a release, clicking one opens it there: the line it belongs to becomes the tab being read and the
point the release, scrolled to only when the section is off screen. Its head says what a release is read
against: the repository it belongs to, linking to github.com and carrying its stars and its first measured
release, and the day the release was tagged - which is the only date the report has, since when a release
was measured is whenever it was submitted or a nightly scan found it and says nothing about the code.
Next to the box that picks the release stands what the report does *not* say about it: phploc counts sixty
numbers and the report plots two, and the rest is shown as phploc printed it - on the dark ground it was
printed on (`--terminal-*`, the one dark surface that is not the brand), in a `<dialog>` the panel behind
it keeps its release through. It is fetched when it is opened rather than carried by the page - a chart
holds hundreds of releases and this is read one at a time - and kept afterwards, since a measurement does
not change. Every release carries a measurement, so the button is simply there and `GraphData` says nothing
about it - it used to carry a `raw` flag per release, for the ones measured before the output was kept.
Turbo caches the page as it is left, so the dialog is closed on
`turbo:before-cache` - one that came back open would come back not modal, since only `showModal()` puts a
dialog in the top layer.
The chart zooms and pans
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
back button is the page as it looks when it is left, which is why every box renders itself by replacing
what is there: a page that comes back open comes back the same, not doubled. `vite.config.js` takes the
public path from `ASSET_BASE` (set by the build task in `deploy.php`) rather than the build mode, so local
production builds keep working.

Both boxes people type in are the **same** control: `assets/combobox.js` is the menu under a field - the
rows, opening and closing it, and the keyboard that walks it, with what a row says and what picking one
means left to the box that built it. `search_controller.js` is the one on the start page, which asks the
server what an input means; `repository_picker_controller.js` is the one above the chart, which has more
than one answer - a chip per line, coloured by its position, which is why a pick moves its option to the
end of the `<select>` behind it. That select is the state, not a widget: the picker writes to it and
dispatches its `change`, so the chart is redrawn by one event however a repository got in or out. Only the
keyboard highlights a row (`.is-active`) - hovering is the pointer's own business, and picking happens on
`mousedown`, before the blur that closes the menu can race it.

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
