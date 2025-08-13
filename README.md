# EcoShop

> Modern PHP + JS e‑commerce demo with a focus on secure-by-default setup.

[![standard-readme compliant](https://img.shields.io/badge/standard--readme-OK-brightgreen.svg)](https://github.com/RichardLitt/standard-readme)
[![Security](https://img.shields.io/badge/security-hardened-blue.svg)](#security)
[![License: UNLICENSED](https://img.shields.io/badge/license-unlicensed-lightgrey.svg)](#license)

A cleaned and documented version of **EcoShop**. This package removes common insecure files (dev/test artifacts, backups, env files, db dumps, logs) and adds hardening guidance.

## Table of Contents

- [Background](#background)
- [Install](#install)
- [Usage](#usage)
- [Configuration](#configuration)
- [Security](#security)
- [Directory Structure](#directory-structure)
- [API](#api)
- [Maintainers](#maintainers)
- [Contributing](#contributing)
- [License](#license)

## Background

This project was sanitized to reduce exposure risk in production deployments. The cleaning step programmatically removed files matching risky patterns (e.g., `test*.php`, `phpinfo.php`, logs, backups, `.env`, VCS and IDE configs). See **docs/SECURITY.md** for details and a reproducible script.

## Install

1. Ensure you have PHP 8.1+ and a database server (PostgreSQL or MySQL, depending on your fork).
2. Copy the repository to your web root, e.g.
   ```bash
   cp -R EcoShop /var/www/html/ecoshop
   ```
3. Configure your web server's document root to the `public/` folder if present. Otherwise, deny direct access to `app/`, `config/`, and `vendor/` paths.

## Usage

- Visit the site in your browser after configuring your virtual host.
- Default admin user creation and seeding instructions are in **docs/SETUP.md**.

## Configuration

- Create an `.env` (not committed) with DB credentials and app secrets. Example template: **docs/.env.example**.
- Set `SESSION.cookie_secure=1` and `SESSION.cookie_httponly=1` in PHP or framework config.
- See **docs/CONFIGURATION.md** for all options.

## Security

- Cleaned out 7 unsafe files and folders (logs, backups, test utilities, private data).
- Performed static pattern scan to flag potential risks in PHP files (see **docs/SECURITY.md**).
- Recommendations:
  - Route all requests through a single front controller and whitelist includes.
  - Validate and normalize all user-supplied paths against an allowlist.
  - Disable `allow_url_include`, and set `open_basedir` appropriately.
  - Use prepared statements for DB access; no string concatenation.
  - Restrict file uploads to a non-executable directory and validate MIME + extension.
  - Set a Content Security Policy (CSP) and SameSite cookies.

## Directory Structure

```
EcoShop/
  docs/                 Project documentation
  ...                   Application source (cleaned)
```

## API

If the project exposes endpoints, document them in **docs/API.md** (added placeholder).

## Maintainers

- @AfnanBinAbbas

## Contributing

PRs welcome. Follows [Standard Readme](https://github.com/RichardLitt/standard-readme). See **CONTRIBUTING.md**.

## License

Copyright [2025] [Afnan Bin Abbas]

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.