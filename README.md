# LiveZilla

The official is no longer update the livezilla project, so I will backup the source code to here.

## Run with Docker

This repo ships with everything needed to run LiveZilla in containers: a PHP 7.4 +
Apache image for the app and a MariaDB container for the database.

> PHP is pinned to **7.4** on purpose — newer PHP (8.2+) removes functions this
> legacy codebase relies on (e.g. `utf8_encode`), which breaks the app at runtime.

### 1. Configure environment

Database credentials and the published port are read from a `.env` file. Copy the
template and edit the values (at minimum change the passwords):

```bash
cp .env.example .env
```

| Variable              | Description                                  | Default     |
| --------------------- | -------------------------------------------- | ----------- |
| `MYSQL_DATABASE`      | Database name                                | `livezilla` |
| `MYSQL_USER`          | Database user                                | `livezilla` |
| `MYSQL_PASSWORD`      | Database user password                       | `livezilla` |
| `MYSQL_ROOT_PASSWORD` | MariaDB root password                        | `rootpassword` |
| `APP_PORT`            | Host port the app is published on            | `8080`      |

`.env` is git-ignored, so your real secrets are never committed.

### 2. Start

```bash
docker compose up -d --build
```

Then open the web installer at **http://localhost:8080** and complete the wizard.
On the **Database** step use these values (the app reaches the DB by its compose
service name):

| Field             | Value                          |
| ----------------- | ------------------------------ |
| Host              | `db`                           |
| Database User     | value of `MYSQL_USER`          |
| Database Password | value of `MYSQL_PASSWORD`      |
| Database Name     | value of `MYSQL_DATABASE`      |
| Table Prefix      | `lz_` (default)                |

### 3. Stop

```bash
docker compose down       # stop, keep data
docker compose down -v    # stop and wipe all data (fresh reinstall next time)
```

### Notes

- **Data persistence** — `_config`, `uploads`, `_log`, `stats` and the database are
  stored in named Docker volumes, so they survive container rebuilds.
- **Auto-removal of the installer** — for security, LiveZilla asks you to delete the
  `install/` folder after setup. The container does this automatically: once
  `_config/config.php` exists (i.e. installation finished), the entrypoint removes
  `install/` on every startup. No manual step needed.
- **HTTPS** — under plain HTTP some features (push notifications, PWA install) are
  unavailable. For production, put a reverse proxy (Nginx / Caddy / Traefik) in
  front to terminate TLS.
- **Changing the DB password later** — the values in `.env` only take effect when the
  database volume is first initialized. If you already have data in the `lz_db`
  volume, changing `.env` won't update it; either recreate the volume
  (`docker compose down -v`) or change the password inside the database manually.
