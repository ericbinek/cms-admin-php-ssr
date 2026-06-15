# schema.org aligned CMS Admin (PHP)

[![Tests](https://github.com/ericbinek/cms-admin-php-ssr/actions/workflows/test.yml/badge.svg)](https://github.com/ericbinek/cms-admin-php-ssr/actions/workflows/test.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
![Version](https://img.shields.io/badge/version-0.1.0-blue.svg)
![Status](https://img.shields.io/badge/status-work_in_progress-orange.svg)
![Build in public](https://img.shields.io/badge/build-in_public-ff69b4.svg)
![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-blueviolet.svg)

A server rendered admin interface for a schema.org aligned CMS, written in plain PHP 8.5.

There is no Composer and no `vendor/` directory. It serves semantic HTML from PHP's built in web server, with no template engine and no build step.

It is login protected and offers full create, edit, and delete management for 10 schema.org entity types such as BlogPosting, Person, and WebPage. It is a stateless proxy: the browser holds an HttpOnly session cookie, the server translates it into a bearer token for the CMS API, and the API stays the authority for authentication and permissions. State changing forms carry a CSRF synchronizer token.

A conformance test suite defines the markup and behavior.

## Status: work in progress (v0.1.0)

This is an ongoing build-in-public project, shared only for community and communication purposes. Do not deploy it in production. Do not rely on its interfaces or data format remaining stable.

## No Composer

This is modern PHP without the dependency tree: no `composer install`, no `vendor/`, no framework. Strict types are on everywhere, the server is the built in `php -S`, and the `composer.json` here only describes the project, it does not pull anything in. Clone it and run it.

## Requirements

- PHP 8.5 or newer

## Installation

```sh
git clone https://github.com/ericbinek/cms-admin-php-ssr.git
cd cms-admin-php-ssr
cp .env.example .env
```

## Running

```sh
php -S 0.0.0.0:5002 -t public src/server.php
```

The server listens on `PORT` (default 5002).

## Usage

Open http://localhost:5002/ in a browser and sign in. Accounts live in the CMS API; there is no self-registration.
Each entity has a list view at `/<plural>`, a detail view at `/<plural>/:id`, and create/edit/delete flows.

Configure the upstream API via the `API_BASE_URL` environment variable. Set `COOKIE_SECURE=true` when serving over HTTPS.

## Entities

- `BlogPosting`
- `Person`
- `WebPage`
- `ImageObject`
- `CategoryCode`
- `CategoryCodeSet`
- `DefinedTerm`
- `DefinedTermSet`
- `Comment`
- `WebSite`

## Testing

```sh
php bin/test.php
```

## Contributing

Contributions are welcome. This is a build-in-public project, so issues, questions, and ideas count as much as pull requests. If you send code, keep it dependency free with `declare(strict_types=1)` and no Composer packages, and keep the conformance suite green, since the tests are the contract. Run them with `php bin/test.php`.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full guidelines.

## License

MIT. See [LICENSE](LICENSE).
