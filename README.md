Open Source Software Complexity Report
======================================

Requirements
------------

* PHP 8.2
* Node & Yarn
* A database (eg. PostgreSQL)

Setup
-----

```
$ git clone git@github.com:chr-hertel/oss-complexity-report
$ cd oss-complexity-report
$ composer install
$ yarn install
$ yarn encore dev
$ docker-compose up -d
$ symfony serve -d
```

Recreate Dataset
----------------

```
# resetting database and caches
$ bin/console doctrine:database:drop
$ bin/console doctrine:database:create
$ bin/console doctrine:schema:create
$ bin/console cache:pool:clear cache.app

# loads projects to analyse from fixtures to database
$ bin/console doctrine:fixtures:load -n

# fetches project libraries from packagist.org and stores them in database
$ bin/console app:libraries:load -vv

# clones repositories and analyses code base of every major and minor release
$ bin/console app:data:aggregate -vv

# fix some data issues
$ bin/console app:data:fix -vv
```
