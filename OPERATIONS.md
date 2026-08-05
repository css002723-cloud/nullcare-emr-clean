# Operations & Maintenance (skeleton)

This document is a short, practical operations and maintenance checklist for deploying and running NullCare EMR in production. Expand each section with site-specific details.

1. Summary
- Purpose: outline frequent maintenance tasks, backup & restore, logging, monitoring, and upgrade steps.

2. Contacts
- Owner: <institution technical contact>
- Clinical lead: <name>
- Support escalation: <email / phone>

3. Deployment & prerequisites
- PHP 8.3, Composer, Node 18+, MySQL/Postgres (or SQLite for small demo), Redis (optional)
- Recommended: containerised deployment (Docker) or a managed VM with supervisor for queues.
- Environment variables: copy from .env.example and set DB, MAIL, APP_KEY, S3 credentials (if used).

4. Start / Stop
- Local dev: `composer setup` (see composer.json scripts)
- Production (example): run migrations `php artisan migrate --force`, build assets `npm run build`.

5. Backups
- Database: nightly mysqldump / pg_dump with 7-day rotation.
- File storage: periodic sync of storage/app/public and any uploaded PDFs.
- Test restores quarterly.

6. Upgrades & migrations
- Pull code, run tests (see Test section), run `php artisan migrate --force`.
- Run `php artisan db:seed --class=DemoClinicalSeeder` only for demo data — do not seed on live production.

7. Monitoring & alerts
- Capture application logs (stderr) and send to a centralized system (ELK/LogDNA or similar).
- Monitor queue failures and background jobs.
- Configure uptime checks for web and API endpoints.

8. Security & privacy
- Ensure HTTPS for all endpoints.
- Rotate API keys and credentials per policy.
- Data retention: define retention policy for audit logs and patient exports.

9. Testing
- Run `php artisan test` as part of any deployment.
- Important to include CDS/EWS unit tests when changing alert logic.

10. Training & handover
- Provide 1-day clinician training session and an admin handover containing credentials and a runbook for common tasks.

---

Add site-specific details (hostnames, backup scripts, escalation phone numbers) here.
