# DTI Blog

WordPress blog for **dtisolution.id/blog** — a Dockerized WordPress behind the
system Caddy reverse proxy, fronted by Cloudflare (Free plan). The visual layer
is the **`dti-blog`** child theme (parent: Gridlove) that matches the DTI
corporate site.

## Architecture

| Piece | Detail |
|-------|--------|
| Web | `wordpress:php8.3-apache`, container `dti-blog-wp` (prod), bound `127.0.0.1:8090` |
| Staging | container `dti-blog-wp-staging`, bound `127.0.0.1:8091` |
| DB | `mariadb:11`, container `dti-blog-db`, DB `wordpress`, table prefix **`bl0gd0c_`**, no host port (internal network only) |
| Proxy | system Caddy on the host → reverse-proxies to `:8090` |
| CDN | Cloudflare, zone `dtisolution.id` (Free plan) |
| Login | renamed to `/blog/doco-login/` by All-In-One WP Security — `wp-login.php` returns 404 |

> **Note:** the WordPress image has **no mysql client**, so raw SQL must run via
> the `dti-blog-db` container (`mariadb -u root -p"$MARIADB_ROOT_PASSWORD" …`),
> not `wp db query`.

## Repository layout

```
dti-theme/            The child theme source (git). Deployed AS "dti-blog".
  style.css           Design tokens + overrides (cache-busted by filemtime)
  functions.php       Enqueues, nav menu, perf filters, in-article CTA, redirects
  header.php footer.php
  assets/             DTI logos
deploy.sh             Deploy the theme into a container + purge cache
maintenance/          Dated, reproducible ops scripts (one-time audits/migrations)
docker-compose.yml    Production stack
docker-compose.staging.yml
```

> **Theme folder name:** the source dir is `dti-theme/` but WordPress activates it
> as **`dti-blog`** (`Template: gridlove`). `deploy.sh` handles this mapping — do
> not copy the folder by hand.

## Deploy workflow (baku — never skip staging)

```
edit dti-theme/**  ->  ./deploy.sh staging  ->  Nico checks staging
  ->  git commit + push (staging branch)
  ->  ./deploy.sh prod  ->  git commit + push (main branch)
  ->  purge Cloudflare cache
```

- `main` = production, `staging` = staging. Fast-forward merges.
- Every change is pushed to GitHub (`github.com/nicoamon/DTI-Blog-2026`).
- Secrets (`.env`, `.staging-admin-pass`, `*.sql`) are git-ignored — never commit them.

## Maintenance scripts

Reproducible, dated records of ops work. Each takes explicit container/DB args so
it never guesses the target:

| Script | Purpose |
|--------|---------|
| `maintenance/2026-06-16-p1-plugin-cleanup.sh` | Plugin & security cleanup |
| `maintenance/2026-06-16-p2-performance.sh` | Cloudflare + EWWW WebP perf |
| `maintenance/2026-06-16-p3-category-consolidation.sh` | Category merge 25→7 (`<wp-container> <db-name>`) |

Raw SQL runs through `dti-blog-db`; destructive scripts should take a DB backup
first (`mariadb-dump`) and confirm before mutating.
