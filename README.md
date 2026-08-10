# Kyma Care CRM - Laravel API

Professional property rent, campus finance, chairman residence expense, tax, approval, reporting and WhatsApp-document API.

## Main modules

- Chairman, Admin and Manager role-based access
- Properties, naturally ordered units/shops and secure documents
- Tenant digital files, duplicate protection, agreements, notes and reminders
- Monthly rent generation, partial/full collection and account ledgers
- Premium rent reminder and paid receipt PDFs
- Four campus budgets, utilities and operational expenses
- Chairman Residence - City Housing expense center
- Excise/property taxes with challans and paid receipts
- Cash, bank and central fund transfers
- Expense verification and Chairman approval workflow
- Month closing, audit logs, automated reminders and annual rent increases
- Consolidated monthly PDF report and WhatsApp delivery tracking

## Requirements

- PHP 8.2 or newer
- Composer 2
- MySQL 8 / MariaDB 10.6+
- XAMPP or another PHP environment

## XAMPP installation

1. Put the folder at `C:\xampp\htdocs\Kyma_care\backend`.
2. Start Apache and MySQL from XAMPP.
3. Open phpMyAdmin and run `create_database.sql`, or create a database named `kyma_care`.
4. Open CMD in this directory and run:

```bat
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

API health check:

```text
http://127.0.0.1:8000/api/health
```

Expected response:

```json
{"app":"Kyma Care CRM API","status":"ok"}
```

## Development users

The seeder creates development accounts. Change their passwords before deployment.

- `admin@kymacare.local`
- `chairman@kymacare.local`
- `manager@kymacare.local`
- Development password: `ChangeMe123!`

## Scheduler

Keep this command running during local testing:

```bat
php artisan schedule:work
```

For production, run Laravel scheduler every minute using cron/task scheduler.

## PDF branding

Open **Settings** in the frontend to configure organization identity, accent color, PDF footer, payment instructions and bank details. New reminder, receipt, voucher and monthly report PDFs use those settings.

## WhatsApp setup

Configure these values in `.env` after creating approved WhatsApp Business templates:

```env
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_API_VERSION=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_TEMPLATE_LANGUAGE=en_US
WHATSAPP_RENT_REMINDER_TEMPLATE=kyma_rent_reminder
WHATSAPP_RENT_RECEIPT_TEMPLATE=kyma_rent_receipt
WHATSAPP_MONTHLY_REPORT_TEMPLATE=kyma_monthly_financial_report
```

Payments still save safely if WhatsApp sending fails; delivery errors are recorded separately.

## Safe update commands

```bat
git pull origin main
composer install
php artisan migrate
php artisan db:seed
php artisan permission:cache-reset
php artisan optimize:clear
```

Never run `php artisan migrate:fresh` on a database containing live records.

## Security notes

- `.env`, `vendor`, logs and private documents are excluded from Git.
- Tenant identity documents use the private filesystem disk.
- Approved financial transactions are corrected through controlled workflow rather than silent deletion.
- API errors include a request ID for support and audit tracing.
