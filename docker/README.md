# Docker

<!-- EGM contract -->

## Overview

Repository-owned Nginx configuration routes requests between the frontend, backend, and local static assets.

## Subdirectories & Boundaries

- [nginx](./nginx/README.md) — Nginx serves the local fixture CDN and routes Bookly traffic to Laravel or Next.js.

## Interface

- The Compose `nginx` service builds from `docker/nginx/Dockerfile` and bind-mounts `nginx.conf` read-only into the running container. [Service definition](../docker-compose.yml#L181) · [Configuration mount](../docker-compose.yml#L193).

## Invariants

- The Docker network gives the `nginx` service the `cdn.bookly.test` alias for local fixture requests. [Network aliases](../docker-compose.yml#L194).

## Side Effects

- The Nginx service publishes container port 80 on host port 8080 in the development Compose file. [Port mapping](../docker-compose.yml#L190).

## Verification

- From the repository root, run `docker compose config --quiet` with the Compose environment configured.

<!-- End EGM contract -->
