# Hostinger deploy for aihealeval-backend-full-20260809.zip

## Zip ready (local)

Path (either copy):
- `/Users/rahul/Downloads/aihealtheval-backend-full-20260809.zip`
- `/Users/rahul/Downloads/aihealtheval.com/aihealtheval-backend-full-20260809.zip`

Size ~169MB · includes `.env` · vendor · demo booking API/admin/migrations
Excludes: `.git`, mentor `storage/app/public/product/*`, install junk zips

Verified inside zip:
- aihealtheval.com/.env
- DemoBookingController.php
- 2026_08_03_000001_create_demo_bookings_table.php
- routes/api/v1/demo-bookings.php

## Blocker found during run (2026-08-09)

SSH password auth works, but the Hostinger account cannot run commands or SFTP:

```
/sbin/nologin: No such file or directory
```

Subsystem `sftp` also exits immediately (exit 1).  
Fix in **Hostinger hPanel → Advanced → SSH Access**: enable SSH and set a normal shell (bash). Wait a few minutes after enabling.

Product photo folder offline copy already present under:
`~/Downloads/mentorkhoj-product-backup` (prior Jul 21 backup). Live scp refresh failed for the same reason.

## After SSH shell is fixed — run these on your Mac Terminal

```bash
DATE=20260809
ZIP=~/Downloads/aihealtheval-backend-full-${DATE}.zip
HOST=u134822100@217.21.94.220
PORT=65002

# Optional product backup refresh
scp -P $PORT -r $HOST:/home/u134822100/domains/aihealtheval.com/public_html/storage/app/public/product \
  ~/Downloads/mentorkhoj-product-backup/

# Upload zip
scp -P $PORT "$ZIP" $HOST:~/

# Deploy on server
ssh -p $PORT $HOST <<'REMOTE'
set -euo pipefail
DATE=20260809
ZIP=~/aihealtheval-backend-full-${DATE}.zip
WEB=~/domains/aihealtheval.com/public_html
cp "$WEB/.env" "$WEB/.env.bak.$(date +%Y%m%d%H%M%S)" || true
rm -rf /tmp/aihealtheval-deploy-extract
mkdir -p /tmp/aihealtheval-deploy-extract
unzip -q -o "$ZIP" -d /tmp/aihealtheval-deploy-extract
rsync -a --exclude 'storage/app/public/product/' \
  /tmp/aihealtheval-deploy-extract/aihealtheval.com/ "$WEB/"
cd "$WEB"
php artisan migrate --force
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan route:list | grep -i demo || true
echo DEPLOY_OK
REMOTE
```

## Manual File Manager fallback

If SSH stays broken, use Hostinger File Manager:
1. Upload `aihealtheval-backend-full-20260809.zip` to home
2. Extract, merge `aihealtheval.com/*` into `domains/aihealtheval.com/public_html/`
3. Do **not** overwrite `storage/app/public/product`
4. Ensure `.env` is the one from the zip
5. In hPanel Terminal (if available): `cd public_html && php artisan migrate --force && php artisan config:clear`
