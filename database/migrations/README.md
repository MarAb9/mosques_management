# Database Migrations

This folder records schema changes applied on top of the original baseline dump
(`ezyro_40059332_mosques_berkane.sql`). Files are numbered in application order.

**Status legend:** each file states whether it is already applied to the
development database. When provisioning a fresh environment, import the baseline
dump first, then apply every migration in order:

```bash
docker compose exec -T db mariadb -u<user> -p<pass> <dbname> < database/migrations/001_add_unique_national_code.sql
docker compose exec -T db mariadb -u<user> -p<pass> <dbname> < database/migrations/002_create_guide_imams.sql
```

Rules (from docs/REFACTOR_CONTRACT_PLAN.md):

- Back up the database before applying any migration.
- Never edit an applied migration; add a new one instead.
- Migrations must not be run automatically by application code.
