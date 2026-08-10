# Union Star Auto Garage CRM V2

## What this build does
This release upgrades the existing Asilify/Simcify PHP CRM without replacing the current database schema.

### Included in V2
- Premium dark automotive navigation/sidebar
- Professional top header and account area
- New Garage Command Center dashboard
- Live KPI cards from existing tables
- Workshop status overview using existing project statuses
- Recent invoice summary with paid/partial/unpaid status
- Low-stock watch based on existing inventory/restock fields
- Revenue chart using existing project payments
- Business snapshot and quick navigation
- Consistent professional UI for existing tables, cards, forms, modals and DataTables
- Professional brand treatment for Invoice, Quotation, Job Card and other generated PDF reports
- Premium sign-in styling
- Original source backups for changed files
- Original supplied SQL dump retained as a rollback/reference backup

## Database safety
No existing table was renamed, dropped or reset in this V2 release.
No mandatory migration is required for this build.

The CRM continues to use the existing database configured in `.env`.

### IMPORTANT FOR LIVE DEPLOYMENT
If the current live database already contains the correct customers, vehicles, invoices, quotes, job cards, payments and inventory:

**DO NOT import `backups/original_database_2026-08-08.sql` over the live database.**

That SQL file is only a backup/reference copy of the data supplied for development.

## Recommended deployment
1. Make one fresh cPanel backup of current `public_html` and database.
2. Put the site in maintenance/low-traffic mode if possible.
3. Upload this V2 source over a staging copy first.
4. Keep the live `.env` database settings unchanged.
5. Test login, customers, vehicles, quotes, invoices, job cards, inventory and PDF rendering.
6. When approved, deploy the same V2 files to the live document root.
7. Do not delete `/uploads` or replace it with an empty folder.

## Rollback
Original modified files are available inside `/backups`.
A supplied SQL backup is also retained there.
