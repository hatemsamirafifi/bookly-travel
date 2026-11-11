# Nginx

<!-- EGM contract -->

## Overview

Nginx serves the local fixture CDN and routes Bookly traffic to Laravel or Next.js.

## Interface

- `php_fpm` targets `laravel:9000` and `nextjs_app` targets `nextjs:3000`. [Upstreams](./nginx.conf#L26).
- `/api`, `/admin`, `/livewire`, `/storage`, and `/_ignition` use the Laravel front-controller fallback. The catch-all location proxies to `nextjs_app`. [API prefix](./nginx.conf#L71) · [Admin prefix](./nginx.conf#L76) · [Livewire prefix](./nginx.conf#L85) · [Storage and Ignition](./nginx.conf#L125) · [Catch-all](./nginx.conf#L146).
- `/vendor/hugerte/` resolves to the editor distribution copied into `/usr/share/bookly/hugerte/` during the Nginx image build. [Alias](./nginx.conf#L115) · [Dockerfile](./Dockerfile#L6).

## Invariants

- The Bookly server declares `default_server`, so `Host: nginx` reaches the application listener. The fixture server matches `cdn.bookly.test`. [Fixture host](./nginx.conf#L42) · [Default listener](./nginx.conf#L60).
- `/css/`, `/js/`, and `/images/` use `try_files` with a `404` fallback for missing assets. [CSS](./nginx.conf#L95) · [JavaScript](./nginx.conf#L101) · [Images](./nginx.conf#L107).

## Side Effects

- The fixture vhost serves files from `/var/cdn` and writes a dedicated access log. [Fixture configuration](./nginx.conf#L44).
- PHP requests are forwarded via FastCGI and the catch-all passes HTTP requests to Next.js. [FastCGI](./nginx.conf#L137) · [HTTP proxy](./nginx.conf#L147).

## Verification

- From the repository root, run `docker compose config --quiet` with the Compose environment configured.

<!-- End EGM contract -->
