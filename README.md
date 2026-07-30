Open Source Software Complexity Report
======================================

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

# loads projects to analyse from fixtures to database
symfony console doctrine:fixtures:load -n

# fetches project libraries from packagist.org and stores them in database
symfony console app:libraries:load -vv

# clones repositories and analyses code base of every major and minor release
symfony console app:data:aggregate -vv

# fix some data issues
symfony console app:data:fix -vv
```
