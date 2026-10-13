# Updating the primary local Docker app

The primary stack serves `http://localhost:8080` using production Next.js and
PHP-FPM. PHP and Next.js application source is copied into the images rather
than read from a Windows bind mount on every cold request. These instructions
assume the existing local stack and its environment files are configured.
Edit the workspace normally, then rebuild the services to deploy those edits:

```powershell
docker compose build php-dependencies laravel queue scheduler nextjs
docker compose up -d php-dependencies laravel queue scheduler nextjs
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
```

Reload Nginx after recreating upstream containers so it resolves their current
addresses. Wait for `bookly-frontend` to finish its production build and report
`Ready` before opening the site. A container's `running` state alone does not
mean the build or HTTP server is ready.

Backend `.env`, frontend `.env.local`, Laravel storage, and dependency volumes
remain mounted. Nginx still serves backend public assets from the workspace.
PostgreSQL, Redis, and Meilisearch keep their existing data volumes. Image
rebuilds do not run migrations or seed the database. Run required
migrations separately after backing up the database. Avoid `docker compose down
-v`, which removes persistent volumes.

The backend build context excludes local environment files, uploaded storage,
and generated bootstrap PHP cache files. The browser CI stack has its own
Compose file and continues using its isolated source mounts.

For a cold-start check, restart only the application services, reload Nginx,
wait for the frontend build, and request both `/en` and
`/api/public/homepage?locale=en`. Repeat after more than five minutes of idle
time to exercise expired homepage cache entries. Check all three locales and
inspect Nginx logs for new upstream timeouts; one warm successful request is
insufficient to diagnose intermittent 504 responses.
