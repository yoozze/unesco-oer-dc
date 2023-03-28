# UNESCO OER DC Portal

## System Requirements
* Docker
* Linux OS (or WSL on Windows)

## Services
* `cms`: CMS service, i.e. Drupal service (debian + apache)
* `db`: Database service, i.e. mySQL
* `dbadmin`: Database GUI admin, i.e. phpMyAdmin (in `development` environment only)
* `email`: Dummy email server, i.e. mailhog (in `development` environment only)

## Installation
```
bash run.sh --build
bash run.sh --start
bash run.sh --enter
bash drupal.sh --install
```

## Usage

### Build images

```
$ bash run.sh --build
```
You can build images separately by providing service name, e.g. to build `cms` only, run:
```
$ bash run.sh --build cms
```

### Start containers

```
$ bash run.sh --start
```
You can start containers separately by providing service name, e.g. to start `cms` only, run:
```
$ bash run.sh --start cms
```

### Stop containers

```
$ bash run.sh --stop
```
You can stop containers separately by providing service name, e.g. to stop `cms` only, run:
```
$ bash run.sh --stop cms
```

### Start logging

```
$ bash run.sh --log
```

### Enter into container

```
$ bash run.sh --enter
```
By default `cms` conatiner is entered. Yo can enter different conatainer by providing service name, e.g.:
```
$ bash run.sh --enter db
```

### Synchronize core files

```
$ bash run.sh --sync-core
```
This copies drupal core, contributed modules and contributed themes to host. This is useful for development.

### Create website archive

```
$ bash run.sh --archive-dump
```
This creates full website dump (code, files, and database) in `archive` directory.
Note: `cms` service must be running.

### Restore website from archive

```
$ bash run.sh --archive-restore path/to/archive
```
This restores website from given archive dump (code, files, and database).
Note: `cms` service must be running.
