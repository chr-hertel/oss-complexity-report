Open Source Software Complexity Report
======================================

Requirements
------------

* PHP 8.4
* Node & Yarn
* A database (e.g. PostgreSQL)

Setup
-----

```bash
git clone git@github.com:chr-hertel/oss-complexity-report
cd oss-complexity-report
composer install
yarn install
yarn encore dev
docker-compose up -d
symfony serve -d
```

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
