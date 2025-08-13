# Security Guide

Date: 2025-08-13

This document explains what was removed and what to review further.

## Removed Files (high risk or non-production)

Patterns removed:
- `.env*`, VCS directories, IDE metadata
- `phpinfo.php`, `adminer.php`, `test*.php`, `debug*`, `demo*`
- `*.bak`, `*.old`, `*.orig`, swap/temp files
- Database dumps: `*.sql`, `*.sqlite`, `*.db`
- Logs: `logs/`, `*.log`
- `node_modules/` in production servers

The cleaning script skipped 7 paths. See **docs/cleanup-report.json** for the exact list.

## Static Scan Findings (needs review)

- `EcoShop/api/config.php`:
  - System command execution
    ```php
    chema file not found: " . $schemaFile);
            }
        
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        
            return true;
        } catch (Exception $e) {
            error_log("Database initialization failed: " .
    ```

## Hardening Checklist

- [ ] Force HTTPS, HSTS max-age >= 15552000
- [ ] Secure cookies: `HttpOnly`, `Secure`, `SameSite=Lax/Strict`
- [ ] Disable directory listings
- [ ] Deny access to `docs/`, `config/`, `storage/` from the web
- [ ] Use parameterized queries everywhere
- [ ] Input validation and output escaping (XSS)
- [ ] Rate limiting / CSRF tokens for state-changing actions
- [ ] Rotate secrets and DB credentials
- [ ] Backups encrypted and stored off-server
