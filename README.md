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

The `async` transport may be consumed by several workers. Checking out a tag rewrites a working copy, so
an analysis holds a lock per repository and a second worker on the same one waits instead of measuring
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

Deploying schema changes
------------------------

The schema is managed by doctrine/migrations and `deploy.php` runs them right before the symlink switches.

The production database predates the migration history, so its baseline would try to create tables that are
already there. It recognizes them and records itself as executed instead - nothing to do by hand.

After the deploy that turns libraries into repositories, run `app:repositories:refresh` once to fill in the
stars and descriptions the migration leaves empty.

Workers in production
---------------------

The workers run under supervisor and are restarted onto the new release by `deploy.php`, which expects the
programs to be named `oss_complexity_report_consumer` and `oss_complexity_report_scheduler`:

```ini
[program:oss_complexity_report_consumer]
command=php /var/www/oss-complexity-report/current/bin/console messenger:consume async --time-limit=3600 --env=prod
process_name=%(program_name)s_%(process_num)02d
numprocs=2
user=deployer
autostart=true
autorestart=true
startsecs=0
; analysing a big repository takes minutes - let it finish instead of killing it mid-checkout
stopwaitsecs=900
; the worker shells out to git, so signals have to reach the whole process group
stopasgroup=true
killasgroup=true

[program:oss_complexity_report_scheduler]
command=php /var/www/oss-complexity-report/current/bin/console messenger:consume scheduler_default --time-limit=3600 --env=prod
process_name=%(program_name)s_%(process_num)02d
numprocs=1
user=deployer
autostart=true
autorestart=true
startsecs=0
stopwaitsecs=30
stopasgroup=true
killasgroup=true
```

The scheduler must stay at `numprocs=1`; the consumer may be scaled up. `deploy.php` restarts both with
`sudo supervisorctl`, so the deploy user needs to be allowed to run it.

The same goes for `sudo systemctl reload php8.4-fpm`: php-fpm reaches the application through the `current`
symlink, so opcache serves the release that was compiled under that path until the pool is reloaded - a
deploy without it moves the symlink and changes nothing about what visitors get. Both grants together:

```
deployer ALL=(root) NOPASSWD: /usr/bin/supervisorctl, /usr/bin/systemctl reload php8.4-fpm
```

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
