# Union Star Auto Garage CRM V2

A professional upgrade of the existing **Union Star Auto Garage CRM**, built on the current Asilify/Simcify PHP codebase while preserving the existing database structure and historical business data.

## Overview

Union Star Auto Garage CRM V2 modernizes the existing garage management system with a cleaner UI/UX, improved workshop visibility, professional document generation, safer PDF rendering, better navigation, and enhanced customer/accounting workflows.

The upgrade is designed to work with the **existing live database**. Existing customers, vehicles, quotations, invoices, job cards, payments, inventory records, suppliers, and historical data remain compatible.

## Current Release

**Version:** V2.7  
**Status:** Stable development build  
**Database Migration:** Not required for the current V2 release

## Key Features

### Dashboard & UI/UX
- Premium automotive-style sidebar and navigation
- Professional top header and account area
- Garage Command Center dashboard
- Live KPI cards using existing CRM data
- Workshop status overview
- Recent invoice summary
- Revenue and payment insights
- Low-stock inventory monitoring
- Responsive cards, tables, forms and modals
- Improved spacing, edge alignment and overflow handling
- Better mobile and tablet responsiveness
- Consistent professional styling across CRM modules

### Customers & Vehicles
- Existing customer records preserved
- Existing vehicle/project records preserved
- Customer profiles and historical records
- Client Statements available from the sidebar
- Date-filtered customer statement generation
- Safe handling of archived/missing historical vehicle references

### Sales & Payments
- Quotations
- Invoices
- Payments
- Client Statements
- Paid / Partial / Unpaid status handling
- Existing financial records remain compatible

> **Note:** The old Billing sidebar item has been removed in the current build.

### Workshop Management
- Job Cards
- Existing workshop/project status tracking
- Vehicle/job history
- Task and parts information
- Existing workflow compatibility

### Inventory & Suppliers
- Inventory management
- Inventory logs
- Parts lists
- Supplier management
- Low-stock monitoring based on current inventory/restock values

## Professional PDF System

The generated documents have been redesigned into a consistent **Union Star Auto Garage premium navy + orange document system**.

Updated documents include:

- Invoice PDF
- Quotation PDF
- Job Card PDF
- Client Statement PDF
- Other supported report PDFs

### PDF Improvements
- Professional branded header
- Union Star Auto Garage identity
- Clean customer and vehicle information sections
- Better tables and totals layout
- Improved typography and spacing
- Professional status indicators
- A4-friendly document layout
- Safer PDF streaming for localhost/XAMPP and live server use
- Improved compatibility with older TCPDF/TCPDI code
- Client Statement summary and transaction history
- Multi-page Client Statement support

The previous **`TOTAL DUE AED 0.00`** block has been removed from the Invoice PDF.

## Database Safety

This project has been upgraded specifically to preserve the existing CRM database.

### No destructive database changes

The current V2 release does **not**:

- Rename existing tables
- Drop existing tables
- Reset existing records
- Delete historical data
- Require a mandatory database migration

The CRM continues to use the database configured in:

```text
.env
```

Existing records such as:

- Customers
- Vehicles
- Quotations
- Invoices
- Job Cards
- Payments
- Expenses
- Inventory
- Suppliers
- Users

remain part of the existing system.

## Important Live Deployment Warning

If the live database already contains the correct production data:

> **DO NOT import an old development SQL backup over the live database.**

Any SQL backup included with a development package should only be treated as a **rollback/reference backup**.

For live deployment, keep the production `.env` database credentials and production database unchanged unless a future release explicitly includes a reviewed migration.

## Security

Do **not** commit sensitive production information to GitHub.

The following should remain excluded from version control:

```gitignore
.env
*.sql
*.zip
database_backup/
backups/*.sql
uploads/logs/
uploads/temp/
.DS_Store
Thumbs.db
```

If `/uploads` contains customer documents, vehicle photos, invoices, private attachments, or other business data, those files should also be excluded from a public repository.

Production credentials, API keys, mail passwords, database passwords, access tokens, and customer-private documents must never be committed to Git.

## Recommended Local Setup

Example XAMPP location:

```text
C:\xampp\htdocs\UnionStar_CRM_V2_5
```

Start:

- Apache
- MySQL

Then open:

```text
http://localhost/UnionStar_CRM_V2_5/
```

The application includes subfolder-aware routing improvements for local XAMPP installations.

## Recommended Live Deployment

1. Create a fresh backup of the current live website files.
2. Export a fresh backup of the live database.
3. Keep a copy of the current production `.env`.
4. Deploy and test the upgraded CRM on a staging copy first.
5. Keep the live database connection settings unchanged.
6. Verify login and dashboard access.
7. Test Customers and Vehicles.
8. Test Quotations.
9. Test Invoices and Invoice PDFs.
10. Test Job Cards and Job Card PDFs.
11. Test Client Statements and Statement PDFs.
12. Test Inventory and Suppliers.
13. Test user permissions and settings.
14. Verify `/uploads` files are present.
15. Deploy the tested files to the live document root.

## Important Uploads Warning

Do not delete or overwrite the production `/uploads` directory with an empty development directory.

It may contain:

- Company branding
- Customer files
- Vehicle images
- Documents
- Attachments
- Historical CRM assets

Always back it up before deployment.

## Rollback Plan

Before every production deployment:

1. Backup the current live source files.
2. Backup the current production database.
3. Preserve the existing `.env`.
4. Preserve the production `/uploads` directory.

If a release causes an issue, restore the previous source files while keeping the database backup available.

## Git Workflow

After making local changes:

```bash
git status
git add .
git commit -m "Describe the CRM update"
git push
```

Example:

```bash
git add .
git commit -m "Improve client statement PDF and CRM UI"
git push
```

Repository:

```text
https://github.com/Hobsinnovation/Garage_crm.git
```

## Technology

- PHP
- Asilify / Simcify custom framework
- Pecee SimpleRouter
- MySQL
- TCPDF / TCPDI
- Bootstrap-based UI
- JavaScript / jQuery
- DataTables
- Composer

## Existing Database Modules

The existing CRM database includes modules/tables for areas such as:

- Clients
- Companies
- Projects / Vehicles
- Quotes
- Quote Items
- Invoices
- Invoice Items
- Job Cards
- Payments
- Expenses
- Inventory
- Inventory Logs
- Parts
- Suppliers
- Tasks
- Insurance
- Users
- Settings
- Marketing / Campaigns

## Development Principle

The main principle of Union Star Auto Garage CRM V2 is:

> **Preserve the existing business data, improve the system around it.**

The goal is to progressively evolve the current CRM into a more advanced garage operating system without unnecessarily breaking existing workflows or historical records.

---

**Union Star Auto Garage CRM V2**  
Professional Garage Management System
