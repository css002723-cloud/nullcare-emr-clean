# API Reference

This document summarizes the main API endpoints exposed by the Laravel backend for the NullCare EMR project.

## Base URL

- Frontend dev proxy: `/api`
- Direct backend: `http://localhost:8000/api`

## Authentication

Most endpoints require authentication with Sanctum.

Headers:

```http
Authorization: Bearer <token>
Content-Type: application/json
```

## Public Endpoints

| Method | Path | Description |
|---|---|---|
| POST | `/login` | Log in a user |
| POST | `/auth/login` | Alias for login |

## Authenticated Endpoints

| Method | Path | Description |
|---|---|---|
| POST | `/logout` | Log out the current user |
| GET | `/me` | Get current authenticated user |
| POST | `/auth/logout` | Alias for logout |
| GET | `/auth/me` | Alias for current user |
| POST | `/change-password` | Change the current user password |
| POST | `/verify-password` | Verify current password |

## Patients

| Method | Path | Description |
|---|---|---|
| GET | `/patients` | List patients |
| GET | `/patients/{patient}` | Show a patient |
| GET | `/patients/by-uid/{uid}` | Show a patient by UID |
| GET | `/patients/{patient}/history` | Get patient history |
| POST | `/patients/check-duplicate` | Check for duplicate patient records |
| POST | `/patients` | Create a patient |
| PUT | `/patients/{patient}` | Update a patient |
| POST | `/patients/{patient}/allergies` | Add patient allergy |
| POST | `/patients/merge` | Merge patient records |
| GET | `/patients/{patient}/export` | Export patient record as PDF |

## Encounters

| Method | Path | Description |
|---|---|---|
| GET | `/encounters` | List encounters |
| GET | `/encounters/{encounter}` | Show an encounter |
| GET | `/encounters/by-mrn/{mrn}` | Show encounter by MRN |
| GET | `/encounters/note-templates` | List note templates |
| POST | `/encounters` | Create an encounter |
| POST | `/encounters/{encounter}/transition` | Transition an encounter |
| POST | `/encounters/{encounter}/close` | Close an encounter |

## Clinical Notes

| Method | Path | Description |
|---|---|---|
| GET | `/encounters/{encounter}/notes` | List notes for an encounter |
| POST | `/encounters/{encounter}/notes` | Create a clinical note |

## Vitals

| Method | Path | Description |
|---|---|---|
| GET | `/vitals/encounter/{encounter}` | List vitals for an encounter |
| POST | `/vitals` | Record new vitals |

## Orders

| Method | Path | Description |
|---|---|---|
| GET | `/orders` | List orders |
| POST | `/orders` | Create an order |
| POST | `/orders/{order}/acknowledge` | Acknowledge an order |
| PUT | `/orders/{order}/status` | Update order status |

## Laboratory

| Method | Path | Description |
|---|---|---|
| GET | `/lab/catalog` | Get lab catalog |
| GET | `/lab/orders` | List lab orders |
| GET | `/lab/critical-unacknowledged` | List critical unacknowledged results |
| POST | `/lab/orders` | Create a lab order |
| POST | `/lab/orders/{labOrder}/collect` | Collect a lab sample |
| POST | `/lab/orders/{labOrder}/receive` | Receive a lab order |
| POST | `/lab/orders/{labOrder}/result` | Submit lab result |
| POST | `/lab/results/{labResult}/verify` | Verify a lab result |
| POST | `/lab/results/{labResult}/acknowledge-critical` | Acknowledge critical lab result |

## Imaging

| Method | Path | Description |
|---|---|---|
| GET | `/imaging/modalities` | List imaging modalities |
| GET | `/imaging/orders` | List imaging orders |
| POST | `/imaging/orders` | Create an imaging order |
| POST | `/imaging/reports/{imagingReport}/review` | Review an imaging report |
| PUT | `/imaging/orders/{imagingOrder}/status` | Update imaging order status |
| POST | `/imaging/orders/{imagingOrder}/report` | Submit imaging report |

## Pharmacy

| Method | Path | Description |
|---|---|---|
| GET | `/pharmacy/prescriptions` | List prescriptions |
| GET | `/pharmacy/stock` | List pharmacy stock |
| POST | `/pharmacy/prescriptions` | Create a prescription |
| POST | `/pharmacy/prescriptions/{prescription}/dispense` | Dispense a prescription |
| POST | `/pharmacy/stock` | Upsert stock |
| POST | `/pharmacy/prescriptions/{prescription}/administer` | Administer a medication |

## Billing

| Method | Path | Description |
|---|---|---|
| GET | `/billing/invoices` | List invoices |
| POST | `/billing/invoices` | Create an invoice |
| POST | `/billing/invoices/{invoice}/pay` | Pay an invoice |
| POST | `/billing/invoices/{invoice}/waive` | Waive an invoice |
| GET | `/billing/unpaid-report` | Get unpaid invoice report |

## Wards

| Method | Path | Description |
|---|---|---|
| GET | `/wards` | List wards |
| GET | `/wards/occupancy` | Get ward occupancy |
| GET | `/wards/patient/{encounter}` | Get patient ward detail |
| GET | `/wards/fluid-balance/{encounter}` | List fluid balance |
| POST | `/wards/admit` | Admit a patient to a ward |
| POST | `/wards/transfer` | Transfer a patient |
| POST | `/wards/fluid-balance` | Record fluid balance |

## Referrals

| Method | Path | Description |
|---|---|---|
| GET | `/referrals` | List referrals |
| GET | `/referrals/inbox` | List inbox referrals |
| POST | `/referrals` | Create a referral |
| POST | `/referrals/{referral}/read` | Mark referral as read |
| POST | `/referrals/{referral}/accept` | Accept referral |
| POST | `/referrals/{referral}/decline` | Decline referral |

## ICU

| Method | Path | Description |
|---|---|---|
| GET | `/icu/patients` | List ICU patients |
| GET | `/icu/notes/{encounter}` | List ICU notes |
| GET | `/icu/patient/{encounter}` | Get ICU patient detail |
| GET | `/icu/critical-alerts` | List critical alerts |
| GET | `/icu/dashboard` | Get ICU dashboard data |
| POST | `/icu/admit` | Admit patient to ICU |
| POST | `/icu/notes` | Create ICU note |
| POST | `/icu/discharge` | Discharge patient from ICU |

## Dialysis

| Method | Path | Description |
|---|---|---|
| GET | `/dialysis/patients` | List dialysis patients |
| GET | `/dialysis/sessions` | List dialysis sessions |
| GET | `/dialysis/dashboard/{patient}` | Get dialysis dashboard |
| POST | `/dialysis/sessions` | Create dialysis session |
| PUT | `/dialysis/sessions/{dialysisSession}` | Update dialysis session |

## Dashboard

| Method | Path | Description |
|---|---|---|
| GET | `/dashboard/summary` | Get dashboard summary |

## Sync

| Method | Path | Description |
|---|---|---|
| GET | `/sync/status` | Get offline sync status |
| POST | `/sync/push` | Push offline sync queue |

## Inventory

| Method | Path | Description |
|---|---|---|
| GET | `/inventory/categories` | List inventory categories |
| GET | `/inventory/items` | List inventory items |
| GET | `/inventory/items/{inventoryItem}` | Show an inventory item |
| GET | `/inventory/consumption` | List inventory consumption |
| GET | `/inventory/alerts` | List inventory alerts |
| POST | `/inventory/items` | Create inventory item |
| PUT | `/inventory/items/{inventoryItem}` | Update inventory item |
| POST | `/inventory/items/{inventoryItem}/batches` | Receive inventory batch |
| POST | `/inventory/consume` | Consume inventory |

## Equipment

| Method | Path | Description |
|---|---|---|
| GET | `/inventory/equipment` | List equipment |
| GET | `/inventory/equipment/statuses` | List equipment statuses |
| GET | `/inventory/equipment/dashboard` | Get equipment dashboard |
| GET | `/inventory/equipment/{equipment}/maintenance` | List equipment maintenance |
| GET | `/inventory/downtime` | List downtime reports |
| POST | `/inventory/equipment` | Create equipment |
| PUT | `/inventory/equipment/{equipment}` | Update equipment |
| POST | `/inventory/equipment/{equipment}/maintenance` | Log maintenance |
| POST | `/inventory/equipment/{equipment}/downtime` | Report downtime |
| POST | `/inventory/downtime/{downtimeReport}/resolve` | Resolve downtime |

## Admin / System

| Method | Path | Description |
|---|---|---|
| GET | `/users` | List users |
| POST | `/users` | Create a user |
| PUT | `/users/{user}` | Update a user |
| DELETE | `/users/{user}` | Delete a user |
| GET | `/auth/security-alerts` | List security alerts |
| GET | `/audit` | View audit log |

## Research

| Method | Path | Description |
|---|---|---|
| GET | `/research/export` | Export research data |
| GET | `/research/consent-summary` | Get consent summary |

## Notes

- Role-based access is enforced for many routes using middleware such as `admin`, `doctor`, `nurse`, `pharmacist`, `lab_tech`, `radiologist`, `billing`, and `records_officer`.
- The frontend uses the Vite proxy to forward `/api` requests to the Laravel backend during local development.
