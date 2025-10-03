# How to Update and Deploy the Site (Arch Linux + Apache + Cloudflare Tunnel)

This guide explains, step-by-step, how to update code, push to GitHub, deploy live at `https://app.theloginpage.me/LoginPage`, run DB migrations, and roll back safely.

---

## 0) Prerequisites (one-time, already done)
- Arch server with Apache + PHP-FPM working
- Cloudflare Tunnel running and routing `app.theloginpage.me` → `http://127.0.0.1:80`
- Systemd overrides letting Apache read `/home/ryu/Work/LoginPage`
- SSH key added to GitHub

Useful commands:
- Check tunnel: `systemctl status cloudflared --no-pager`
- Check Apache: `sudo systemctl status httpd --no-pager`
- Apache logs: `sudo tail -n 100 /var/log/httpd/app_error.log`

---

## 1) Make and Test Changes Locally
1. Edit code under `/home/ryu/Work/LoginPage`.
2. Run a quick PHP syntax check if needed (optional):
   - `php -l /home/ryu/Work/LoginPage/path/to/file.php`
3. Test locally in browser:
   - Local: `http://127.0.0.1/LoginPage/`
   - Public: `https://app.theloginpage.me/LoginPage/`

Notes:
- Local HTTP stays plain (no local cert). Public uses HTTPS via Cloudflare.
- If local redirects to https://localhost, update the Apache vhost to skip HTTPS redirect for localhost (already configured in vhost).

---

## 2) Commit and Push to GitHub
From `/home/ryu/Work/LoginPage`:
```bash
git status
git add -A
git commit -m "feat/fix: <short description>"
git push -u origin $(git branch --show-current)
```

If SSH agent errors:
```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
git push
```

---

## 3) Apply Server-Side Changes (Apache)
Most code updates are picked up immediately since Apache serves from the working tree.

If you change Apache config (vhost, handlers, etc.):
```bash
sudo apachectl -t && sudo systemctl restart httpd
```

If you change file/dir permissions:
```bash
# Ensure Apache (http user) can traverse/read paths
sudo setfacl -m u:http:rx /home
sudo setfacl -m u:http:rx /home/ryu
sudo setfacl -m u:http:rx /home/ryu/Work
sudo setfacl -R -m u:http:rX /home/ryu/Work/LoginPage
```

Verify quickly:
```bash
curl -I https://app.theloginpage.me/LoginPage/
sudo tail -n 50 /var/log/httpd/app_error.log
```

---

## 4) Database Migrations (when schema changes)
Files: `scripts/db/schema.sql`, `scripts/db/email-verification-update.sql`, `scripts/db/password-reset-update.sql`, or your new migration.

Steps:
1. Back up the DB (recommended):
   ```bash
   mysqldump -u <user> -p <database> > ~/backup-$(date +%F_%H%M)-loginpage.sql
   ```
2. Apply migration:
   ```bash
   mysql -u <user> -p <database> < /home/ryu/Work/LoginPage/scripts/db/<your-migration>.sql
   ```
3. Validate:
   - Load the app pages that use new tables/columns
   - Check logs: `sudo tail -n 100 /var/log/httpd/app_error.log`

---

## 5) Cloudflare Tunnel and DNS
Normally no change is required. If you changed the public hostname:
1. Update `~/.cloudflared/config.yml`:
   ```yaml
   tunnel: loginpage
   credentials-file: /home/ryu/.cloudflared/<TUNNEL-UUID>.json
   ingress:
     - hostname: app.theloginpage.me
       service: http://127.0.0.1:80
     - service: http_status:404
   ```
2. Reload service:
   ```bash
   sudo systemctl restart cloudflared
   systemctl status cloudflared --no-pager
   ```
3. Recreate DNS route if needed:
   ```bash
   cloudflared tunnel route dns loginpage app.theloginpage.me
   ```

---

## 6) Update Outbound URLs (Emails)
If environment/public domain changed, update:
- `src/app/services/EmailService.php`
- `src/app/services/EmailServiceSMTP.php`

Ensure all links use `https://app.theloginpage.me/LoginPage/...`

Send a test email and verify links open from a phone (off local network).

---

## 7) Post-Deploy Checklist
- Public site loads: `https://app.theloginpage.me/LoginPage/`
- Login/registration works
- Email verification and password reset links work on mobile
- Admin/profile page loads and heartbeat/last-activity updates without errors
- Apache logs are clean:
  - `sudo tail -n 100 /var/log/httpd/app_error.log`

---

## 8) Rollback
If something breaks:
1. Restore previous commit:
   ```bash
   git log --oneline
   git checkout <good_commit>
   # or
   git revert <bad_commit>
   git push
   ```
2. If DB migration broke things, restore backup:
   ```bash
   mysql -u <user> -p <database> < ~/backup-YYYY-MM-DD_HHMM-loginpage.sql
   ```
3. Restart services if configs changed:
   ```bash
   sudo apachectl -t && sudo systemctl restart httpd
   sudo systemctl restart cloudflared
   ```

---

## 9) Troubleshooting
**Issue:** 301 to http from HTTPS
- Ensure vhost has:
  - `SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on`
  - `DirectorySlash Off`
  - Redirect forces https only for `app.theloginpage.me`

**Issue:** 403 or permission denied under `/home/ryu`
- Apply ACLs as in section 3
- Keep systemd override for httpd: `ProtectHome=false`, `ReadWritePaths=/home/ryu/Work/LoginPage`

**Issue:** Email links point to localhost
- Update EmailService files to use the public domain

**Issue:** DNS/CNAME missing
- `cloudflared tunnel route dns loginpage app.theloginpage.me`
- In Cloudflare DNS, CNAME `app` → `<TUNNEL-UUID>.cfargotunnel.com` (Proxied)

**Issue:** Local browser says connection refused on https://localhost
- Use `http://127.0.0.1/LoginPage/` locally, use `https://app.theloginpage.me/LoginPage/` publicly

---

## 10) Optional Security Hardening
- Cloudflare: Enable “Always Use HTTPS”; HSTS later when stable
- Apache vhost header:
  - `Header always set Strict-Transport-Security "max-age=31536000"`
  - `Header set X-Content-Type-Options nosniff`
- Rate limit login endpoints (Cloudflare WAF rules)

---

## 11) One-Line Update Flow (Typical)
```bash
cd /home/ryu/Work/LoginPage
git pull
sudo apachectl -t && sudo systemctl restart httpd
sudo systemctl restart cloudflared   # only if tunnel config changed
```


