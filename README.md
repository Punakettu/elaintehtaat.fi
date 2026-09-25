# Eläintehtaat

Drupal 11 site running on Docker Compose.

| Service | Image              | Purpose                                | Host port |
|---------|--------------------|----------------------------------------|-----------|
| varnish | varnish:7          | HTTP cache in front of nginx           | **8080**  |
| nginx   | nginx:1.27-alpine  | Web server, serves `web/`              | 8081 (bypasses Varnish) |
| php     | php:8.3-fpm-alpine | PHP-FPM + Composer + Drush             | –         |
| mysql   | mariadb:12.3       | Database                               | 3306      |
| redis   | redis:7-alpine     | Cache backend (drupal/redis, PhpRedis) | 6379      |
| rustfs  | rustfs/rustfs      | S3-compatible object storage for files | 9000 (S3 API), 9001 (console) |
| rustfs-init | amazon/aws-cli | One-shot: creates the bucket, exits    | –         |

## Quick start

```sh
cp .env.example .env
docker compose up -d --build
make install                  # composer install + drush site:install --existing-config
make config-export            # export active config to config/sync after changing settings in the UI
```

Open <http://localhost:8080> and log in with `admin` / `admin`.

## Layout

```
compose.yml                      service definitions
docker/                          per-service config (php Dockerfile + ini, nginx, varnish VCL, mysql, rustfs)
composer.json                    Drupal project
config/sync/                     exported Drupal configuration
web/                             document root
web/sites/default/settings.php   Drupal settings
```

## Day-to-day

```sh
make up / make down         start / stop
make logs                   tail logs
make shell                  sh into the php container
make varnish-purge          flush Varnish
make reset                  wipe containers + volumes and rebuild
```

## File storage (S3 / RustFS)

`public://` is served from the S3 bucket via the [s3fs](https://www.drupal.org/project/s3fs)
module; `private://` stays on local disk. Settings live in `settings.php` and
come from the `S3_*` variables in `.env`:

| Variable        | Default          | Used by                                   |
|-----------------|------------------|-------------------------------------------|
| `S3_BUCKET`     | `drupal-files`   | bucket name, created by `rustfs-init`     |
| `S3_ACCESS_KEY` | `rustfsadmin`    | RustFS root credentials + Drupal          |
| `S3_SECRET_KEY` | `rustfsadmin`    |                                           |
| `S3_REGION`     | `us-east-1`      |                                           |
| `S3_PUBLIC_HOST`| `localhost:9000` | host the *browser* uses in file URLs      |

File URLs handed to the browser look like `http://localhost:9000/drupal-files/s3fs-public/<path>`.
The bucket has a public-read policy so those URLs need no signing. Image
style derivatives are generated on first request via `/s3/files/styles/...`
and then redirected to the bucket.

- Console: <http://localhost:9001> (login with the access/secret key).
- To point at real S3 or another provider, change the `S3_*` variables and the
  `use_customhost` / `hostname` / `domain` lines in `settings.php`.

## Caching notes

- Anonymous page responses carry `Cache-Control: max-age=3600, public`
  (`system.performance` config) and are cached by Varnish. Check the
  `X-Varnish-Cache: HIT|MISS` response header.
- Varnish passes through anything under `/admin`, `/user`, `/batch` and any
  request with a Drupal session cookie.
- `drupal/purge` and `drupal/varnish_purge` are in `composer.json` but not yet
  enabled. Enable them and add a Varnish purger (BAN with `Cache-Tags` header,
  host `varnish`, port 80) to get tag-based invalidation instead of waiting
  for the TTL. The VCL already accepts `PURGE`, `BAN` and `URIBAN` from the
  Docker network.
- Redis backs all cache bins except `form`, and also stores the compiled
  service container (`bootstrap_container_definition`).

## Deployment

Publishing a GitHub release deploys that release's tag (`.github/workflows/deploy.yml`). [Deployer](https://deployer.org) (`deploy.php`)
rsyncs the build to `~/public_html/releases/<n>`. It then runs
`drush deploy` (updb, config import, cache rebuild, deploy hooks) and switches the
`~/public_html/current` symlink. A database dump is saved to `~/backups/elaintehtaat`
before each deploy.

`settings.local.php` is generated from GitHub secrets on every deploy.

### Dependabot updates

Dependabot (`.github/dependabot.yml`) opens weekly PRs for Drupal packages. On
those PRs `.github/workflows/dependabot-config.yml` installs the site with the
`main` packages and config, upgrades to the PR's packages, runs `drush updb`,
and commits any exported config changes to the PR branch.

It needs a fine-grained personal access token for this repository with
**Contents: read and write**, saved as a **Dependabot** secret named
`DEPENDABOT_CONFIG_TOKEN` (Settings → Secrets and variables → Dependabot).
Dependabot runs can't read Actions secrets, and pushes made with the default
`GITHUB_TOKEN` don't trigger CI.

Once the workflow has committed to a PR, Dependabot stops rebasing it. Comment
`@dependabot recreate` to pick up a newer release; the workflow then runs again.
