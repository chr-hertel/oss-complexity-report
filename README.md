Open Source Software Complexity Report
======================================

Requirements
------------

* PHP 7.4

Setup
-----

```
$ git clone git@github.com:chr-hertel/oss-complexity-report
$ cd oss-complexity-report
$ composer install
$ symfony serve -d
```

Recreate Dataset
----------------

```
# resetting database and caches
$ rm var/data.db
$ bin/console doctrine:database:create
$ bin/console doctrine:schema:create
$ bin/console cache:pool:clear cache.app

# loads projects to analyse from fixtures to database
$ bin/console doctrine:fixtures:load

# fetches project libraries from packagist.org and stores them in database
$ bin/console app:libraries:load

# clones repositories and analyses code base of every major and minor release
$ bin/console app:data:aggregate
```
