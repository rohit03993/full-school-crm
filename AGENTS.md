# Agent instructions — School CRM

**Project:** Generic school & coaching CRM (fork of Folks India)  
**Path:** `F:\Rohit Development\Full school soft\school-crm`

## Before coding

1. Read **`docs/GENERIC_SCHOOL_CRM_PROJECT.md`** (full brief).
2. This is **not** Folks India — do not edit `F:\Rohit Development\Folks India` unless that workspace is open.
3. Use a **separate database** from Folks India (`school_crm` locally).
4. User prefers to run terminal commands themselves unless they ask you to run them.
5. Do not commit `.env` or push to `folksindia` remote without explicit request.

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

Share **`docs/INSTALL_AND_CUSTOMIZE_GUIDE.md`** with each school/coaching/college — covers install, wizard, terminology, custom fields, and checklists by institute type. In admin: **Setup → Setup Guide**.
