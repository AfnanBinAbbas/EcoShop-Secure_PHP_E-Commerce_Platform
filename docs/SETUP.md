# Setup

## Prerequisites
- PHP 8.1+ with extensions: pdo, pdo_mysql or pdo_pgsql, mbstring, json, openssl
- Composer (if applicable)
- Web server (nginx or Apache)
- Database server

## Steps

1. Copy repository to your server.
2. Create a database and a user with least privileges.
3. Copy `docs/.env.example` to project root as `.env` and fill values.
4. Run migrations/seeders if provided.
5. Configure your vhost to point to `public/` and block access to non-public dirs.
