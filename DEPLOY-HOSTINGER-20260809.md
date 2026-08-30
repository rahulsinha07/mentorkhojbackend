# Hostinger deploy — locked SSH target

## Always use this host

```bash
ssh -p 65002 u134822100@145.79.208.118
```

| Field | Value |
|-------|--------|
| Host | `145.79.208.118` |
| Port | `65002` |
| User | `u134822100` |
| Shell | `/bin/bash` |
| Laravel | `~/domains/aihealtheval.com/public_html` |
| Local tree | `/Users/rahul/Downloads/aihealtheval.com` |

**Do not use** `217.21.94.220` — that host returns `/sbin/nologin` and SFTP closes.

## Cursor MCP (preferred for all future deploys)

Server: **`mentorkhoj-hostinger`**

- Global: `~/.cursor/mcp.json`
- This repo: `.cursor/mcp.json`
- Code: `/Users/rahul/mentorkhoj-react/mcp/mentorkhoj-hostinger/`
- Skill: `~/.cursor/skills/mentorkhoj-hostinger-deploy/`

### Tools

1. `ssh_ping`
2. `deploy_laravel_patch_from_local` — SCP relative files + migrate + clear caches
3. `deploy_demo_bookings_admin_patch` — demo bookings delete/notes patch
4. `artisan_migrate_and_clear`

Reload MCP in Cursor Settings after changes.

## Manual patch (same host)

```bash
HOST=u134822100@145.79.208.118
PORT=65002
# scp files then:
ssh -p $PORT $HOST <<'REMOTE'
set -euo pipefail
cd ~/domains/aihealtheval.com/public_html
php artisan migrate --force
php artisan route:clear && php artisan view:clear && php artisan config:clear
echo DEPLOY_OK
REMOTE
```

## Old full-zip notes (2026-08-09)

Earlier zip deploy used the old IP and hit nologin. Prefer MCP patch deploy on **145.79.208.118** instead of full zip unless doing a full rebuild.
