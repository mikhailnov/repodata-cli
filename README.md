# repodata-cli

## Russian

Это простой скрипт на php, который извлекает версию заданного пакета из метаданных репозитория RPM-пакетов. Метаданные создаются с помощью, например, `createrepo_c`.

Использование:

`./repodata.php get-package-version путь/к/repomd.xml имя-пакета`

В ответ выдается версия пакета на stdout. Код возврата 0, если удалось получить версию заданного пакета, во всех остальных случаях не 0.

## English

This is a simple php script that gets version of a specified package from metadata of a repository of RPM packages. Repository metadata can be created with, for example, `createrepo_c`.

Usage:

`./repodata.php get-package-version path/to/repomd.xml package-name`

## Dependencies / Зависимости

* php-cli
* php-bz2
* php-sqlite3
* php-xmlreader
