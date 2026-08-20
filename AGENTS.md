# Agent instructions — School CRM

**Project:** Generic school & coaching CRM (fork of Folks India)  
**Path:** `F:\Rohit Development\Full school soft\school-crm`

## Before coding

1. Read **`docs/GENERIC_SCHOOL_CRM_PROJECT.md`** (full brief).
2. For module on/off packaging, read **`docs/MODULE_ARCHITECTURE.md`** — new features must declare a module key and respect `FeatureGate`.
3. This is **not** Folks India — do not edit `F:\Rohit Development\Folks India` unless that workspace is open.
4. Use a **separate database** from Folks India (`school_crm` locally).
5. User prefers to run terminal commands themselves unless they ask you to run them.
6. Do not commit `.env` or push to `folksindia` remote without explicit request.

## Product goal

Rebrand and generalize the CRM for **any school or coaching institute** — configurable institute name, logo, and content; not hard-coded “Folks India”.

## Key commands

```powershell
cd "F:\Rohit Development\Full school soft\school-crm"
php artisan serve
php artisan crm:ensure-admin
php artisan test
```

## Deploy note

CloudPanel/nginx requires `public/vendor/livewire/` (run `php artisan crm:publish-assets` on server).

## Client install guide

Share **`docs/install/`** with each school — start at [`docs/install/README.md`](docs/install/README.md).  
- Server: [`docs/install/server-checklist.md`](docs/install/server-checklist.md)  
- Customize: [`docs/install/customize-for-school.md`](docs/install/customize-for-school.md)  
In admin: **Setup → Setup Guide**.

## Module packaging

See **`docs/MODULE_ARCHITECTURE.md`** for the full module catalog (existing + planned), dependencies, and phases. Closing one licensed module must not break the rest.
