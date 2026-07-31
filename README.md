Open Source Software Complexity Report
======================================

Measures how the cyclomatic complexity of PHP open source software evolved over time.

Every repository on github.com that is mostly written in PHP can be submitted - a `composer.json` is not
needed, so `wordpress/wordpress` works just as well as `symfony/console`. Submitted repositories are
grouped by their GitHub vendor, and the start page and the overview chart focus on the most starred ones.

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

Recreate Dataset
----------------

```bash
# resetting database and caches
symfony console doctrine:database:drop --force
symfony console doctrine:database:create
symfony console doctrine:schema:create
symfony console cache:pool:clear cache.app

# submits a couple of well known repositories to start with
symfony console doctrine:fixtures:load -n

# clones repositories and analyses code base of every major and minor release
symfony console app:data:aggregate -vv

# fix some data issues
symfony console app:data:fix -vv
```

Submitting Repositories
-----------------------

Repositories are submitted with the form on the start page, or on the command line:

```bash
symfony console app:repository:submit wordpress/wordpress https://github.com/symfony/console
```

Submitting only queues a repository - `app:data:aggregate --pending` analyses everything that came in
since the last run, and `app:repositories:refresh` updates the stars that order the report.

Set `GITHUB_TOKEN` in `.env.local` to raise the github.com API rate limit from 60 to 5.000 requests per
hour. The token only reads public data, so it does not need any scope.
