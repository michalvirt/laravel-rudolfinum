# Production images

`Dockerfile` is a multistage build. The runtime images are Alpine and contain only what production runs: PHP-FPM with production Composer packages, nginx with the public files, and a Node process with the Inertia SSR bundle. Composer, npm, and the frontend toolchain stay in the discarded build stage.

Compose tags the three images it builds:

| Target | Image | What it runs |
| --- | --- | --- |
| `app` | `rudolfinum-app:production` | PHP 8.5 FPM. Also used for migrations, the scheduler, and the queue worker. |
| `web` | `rudolfinum-web:production` | nginx, serving `public/` including the Vite build. |
| `ssr` | `rudolfinum-ssr:production` | Node 22, serving the bundled Inertia SSR server on port 13714. |

MariaDB is not built from this repository. `compose.production.yaml` pulls `mariadb:11.8` and keeps its data in the `database` volume. The application connects with Laravel's `mariadb` driver.

## Requirements

- Docker Engine 23 or newer, or Docker Desktop, with BuildKit enabled (the default).
- Docker Compose v2.24 or newer (`docker compose`).

The first build downloads the base images, installs PHP extensions, and runs `composer install` and `npm ci`. Later builds reuse the BuildKit cache.

## Build the images directly

Run these from the repository root. Pass the same `VITE_APP_NAME` to every target. Vite writes that name into the client bundle and the SSR bundle at build time, so a later change of `APP_NAME` in the environment does not rename the interface until you rebuild.

```bash
docker build --target app --build-arg VITE_APP_NAME="Laravel" -t rudolfinum-app:production .
docker build --target web --build-arg VITE_APP_NAME="Laravel" -t rudolfinum-web:production .
docker build --target ssr --build-arg VITE_APP_NAME="Laravel" -t rudolfinum-ssr:production .
```

If BuildKit is turned off in your environment, prefix the commands with `DOCKER_BUILDKIT=1`.

## Build with Compose

Compose refuses to load `compose.production.yaml` until `.env.production` exists and both database passwords are set. That check also applies to `build`. Create the file in the repository root (it is gitignored) with at least:

```dotenv
APP_NAME=Laravel
DB_PASSWORD=choose-a-long-password
DB_ROOT_PASSWORD=choose-a-different-root-password
```

`DB_PASSWORD` is the application user. `DB_ROOT_PASSWORD` is the MariaDB root user and is not used by PHP.

`APP_NAME` becomes the `VITE_APP_NAME` build argument. Then:

```bash
docker compose --env-file .env.production -f compose.production.yaml build
```

Compose builds `rudolfinum-app:production`, `rudolfinum-web:production`, and `rudolfinum-ssr:production`. Migrate, scheduler, and queue reuse the app image.

## Build arguments

| Argument | Default | Used for |
| --- | --- | --- |
| `VITE_APP_NAME` | `Laravel` | Application name compiled into the frontend and SSR bundle. |
| `PHP_VERSION` | `8.5` | `php` image tag for the build stage and the FPM runtime. |
| `NODE_VERSION` | `22` | `node` image tag for the asset build and the SSR runtime. |

`PHP_VERSION` and `NODE_VERSION` have defaults in the Dockerfile. Compose passes only `VITE_APP_NAME`. Override the versions on a direct build:

```bash
docker build \
  --target app \
  --build-arg PHP_VERSION=8.5 \
  --build-arg NODE_VERSION=22 \
  --build-arg VITE_APP_NAME="Rudolfinum" \
  -t rudolfinum-app:production .
```

Repeat the same `PHP_VERSION`, `NODE_VERSION`, and `VITE_APP_NAME` for the `web` and `ssr` targets so all three images come from one dependency set.

## Another CPU architecture

Images are built for the architecture of the machine running Docker. To publish `linux/amd64` from an Apple Silicon machine:

```bash
docker buildx build --platform linux/amd64 --target app \
  --build-arg VITE_APP_NAME="Laravel" \
  -t rudolfinum-app:production .
```

Use the same `--platform` for `web` and `ssr`. With Compose:

```bash
DOCKER_DEFAULT_PLATFORM=linux/amd64 \
  docker compose --env-file .env.production -f compose.production.yaml build
```

## Confirm the build

```bash
docker image ls rudolfinum-app rudolfinum-web rudolfinum-ssr
```

The build stage creates a throwaway `APP_KEY` so Artisan can generate Wayfinder types and compile assets. That key stays in the build stage. Put the real `APP_KEY` in `.env.production` when you start the stack.

## Start the stack from those images

Fill in the rest of `.env.production` before the first start: `APP_KEY`, `APP_URL`, mail settings, and the Google Sheets values the import needs. Generate a key with:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'
```

Use `0` and `1` for boolean settings such as `APP_DEBUG`. Docker injects environment values as strings, and PHP treats the word `false` as true.

```bash
docker compose --env-file .env.production -f compose.production.yaml up -d
```

After a new build, recreate the containers so the one-shot migration container runs again:

```bash
docker compose --env-file .env.production -f compose.production.yaml up -d --build --force-recreate
```

The site is published on `HTTP_PORT` from `.env.production`, or port 80 when that variable is unset. The health URL is `/up`.
