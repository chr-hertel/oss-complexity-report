Open Source Software Complexity Report
======================================

Measures how the cyclomatic complexity of PHP open source software evolved over time.

Every repository on github.com that is mostly written in PHP can be submitted - a `composer.json` is not
needed, so `wordpress/wordpress` works just as well as `symfony/console`. Submitted repositories are
grouped by the GitHub account that owns them, and the start page and the overview chart focus on the most
starred ones.

Requirements
------------

* PHP 8.4
* Node 26 (see .nvmrc) & Yarn
* A database (e.g. PostgreSQL)

Setup
-----

```bash
git clone git@github.com:chr-hertel/oss-complexity-report
cd oss-complexity-report
composer install
yarn install
yarn build
docker-compose up -d
symfony serve -d
```

Assets are bundled by Vite and wired into Twig by [symfony/reprise][reprise].
Run `yarn build` for a one-off build, or `yarn dev` to start the Vite dev
server with hot module replacement - reprise picks it up automatically.

[reprise]: https://github.com/symfony/reprise

Background Processing
---------------------

Analysing a repository clones it and runs phploc over every release, which is far too slow to happen
while someone waits for a HTTP response. It is therefore handled by [symfony/messenger][messenger] with
the Doctrine transport, and kept up to date by [symfony/scheduler][scheduler]:

| Message              | Does                                                                        |
|----------------------|-----------------------------------------------------------------------------|
| `ScanForNewReleases` | fans out into one `ScanRepository` per submitted repository                 |
| `ScanRepository`     | asks github.com for tags and queues an analysis if a release is missing     |
| `AnalyseRepository`  | clones, checks out every new release and measures it                        |
| `RefreshRepositories`| re-reads stars and metadata, which decide the order of the whole report      |

Only `AnalyseRepository` is expensive. `ScanRepository` reads refs with `git ls-remote`, so the nightly
check neither clones anything nor touches a working copy that is being analysed, and the queue only ever
fills up with repositories that really did release something.

Two workers are needed - one for the queue, one for the schedule:

```bash
symfony console messenger:consume async -vv
symfony console messenger:consume scheduler_default -vv
```

The `async` transport may be consumed by several workers, `scheduler_default` must stay at exactly one -
otherwise the nightly run happens more than once. Checking out a tag rewrites a working copy, so an
analysis holds a lock per repository and a second worker on the same one waits instead of measuring
whatever the first one just checked out. The lock is a `flock` by default (see `LOCK_DSN`), which only
works if all workers run on the same machine - switch it to `postgresql+advisory://` if they ever do not.

Failed messages are kept: `messenger:failed:show` lists them, `messenger:failed:retry` puts them back.

### Disk usage

Clones in `repositories/` are scratch space, not a cache. A working copy is only needed while releases are
measured - looking for new ones reads refs from github.com - so an analysis removes it when it is done, and
a repository that never releases again never occupies disk. That bounds the disk by what is being analysed
instead of by everything ever submitted, at the price of cloning again when a repository does release.

`app:repositories:clean` removes what predates that: working copies from before this behaviour, from
repositories that were renamed, and from workers that were killed mid-analysis. It skips whatever is being
analysed right now, so it is safe to run while workers are busy.

[messenger]: https://symfony.com/doc/current/messenger.html
[scheduler]: https://symfony.com/doc/current/scheduler.html

Recreate Dataset
----------------

```bash
# resetting database and caches
symfony console doctrine:database:drop --force
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
symfony console cache:pool:clear cache.app

# submits a couple of well known repositories to start with
symfony console doctrine:fixtures:load -n

# queues every submitted repository ...
symfony console app:data:aggregate

# ... and this clones and analyses them - the long one, run it until the queue is empty
symfony console messenger:consume async -vv

# fix some data issues
symfony console app:data:fix -vv
```

`messenger:stats` shows what is left to do.

Schema changes
--------------

The schema is managed by doctrine/migrations, never by `doctrine:schema:update` - run
`doctrine:migrations:diff` after changing an entity and `doctrine:migrations:migrate` to apply what came
out of it. A deploy runs the pending migrations before it switches to the new release.

Error Reporting
---------------

Errors are reported to [Sentry][sentry] - uncaught exceptions of the web app, of the console commands and
of everything the workers run. It is configured by `SENTRY_DSN`, which is empty everywhere but production:
without a DSN the SDK collects nothing and sends nothing, so nothing has to be switched off for local
development. Set it in `.env.local` to try it out, and in the environment of the deployment to turn it on
in production.

Two things are deliberately not reported: 404 and 405, which on a public site are what bots produce rather
than what is broken, and messages that are going to be retried. A repository another worker is holding
throws by design, and github.com failing once is what the retry strategy is for - only what runs out of
retries and lands in the failure transport is an incident.

Every deploy writes the revision it puts live into `SENTRY_RELEASE`, so an error says which release it
happened on. Tracing is off: this is error reporting, not performance monitoring.

[sentry]: https://docs.sentry.io/platforms/php/guides/symfony/

Submitting Repositories
-----------------------

Repositories are submitted with the form on the start page, or on the command line:

```bash
symfony console app:repository:submit wordpress/wordpress https://github.com/symfony/console
```

Submitting queues the repository for analysis right away, so it shows up as soon as a worker gets to it.
Every night the schedule looks for releases that are missing and refreshes the stars, which means there
is nothing left to run by hand - `app:releases:scan` and `app:repositories:refresh` only trigger the same
work earlier.

The form is protected by a stateless CSRF token - a double submit cookie written by the `csrf-protection`
Stimulus controller, so readers of the report never get a session - and submissions are limited to five per
quarter of an hour and IP, since each one spends github.com API quota and ends in a clone.

Set `GITHUB_TOKEN` in `.env.local` to raise the github.com API rate limit from 60 to 5.000 requests per
hour. The token only reads public data, so it does not need any scope.
