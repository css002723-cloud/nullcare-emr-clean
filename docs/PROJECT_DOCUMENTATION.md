# NullCare EMR Project Documentation

## 1. Overview

NullCare EMR is a healthcare management system built as a full-stack web application for clinical and administrative workflows. The project combines a Laravel backend with a React/Vite frontend to support patient intake, encounters, lab workflows, pharmacy, billing, wards, ICU, imaging, and administrative reporting.

The repository is organized into:
- a Laravel API backend under the project root
- a React frontend under the frontend folder
- supporting docs and API references in the repository root

## 2. Purpose

The system is designed to help a hospital or clinic manage:
- patient records and appointments
- clinical encounters and notes
- diagnostic orders and results
- medication and pharmacy workflows
- billing and invoice operations
- ward, ICU, and dialysis workflows
- audit and admin oversight

## 3. Architecture

### Backend
The backend is built with Laravel and exposes a REST-style API through Sanctum authentication.

Key backend areas:
- Controllers: app/Http/Controllers/Api and app/Http/Controllers/Settings
- Models: app/Models
- Requests: app/Http/Requests
- Services: app/Services
- Routes: routes/api.php and routes/web.php
- Migrations and seeders: database/migrations and database/seeders

### Frontend
The frontend is built with React, Vite, Tailwind CSS, and React Router.

Key frontend areas:
- Pages: frontend/src/pages
- Shared UI: frontend/src/components
- Auth context: frontend/src/context
- API client: frontend/src/services/api.js

## 4. Technology Stack

### Backend
- PHP 8.3+
- Laravel 13
- Sanctum for API authentication
- Eloquent ORM
- MySQL-compatible database
- Pest for testing

### Frontend
- React 18
- Vite
- React Router DOM
- Tailwind CSS
- Axios
- lucide-react for icons
- Dexie for client-side storage support

## 5. Main Project Modules

### Patient Management
Used for registering patients, viewing patient records, checking duplicates, and reviewing patient history.

Relevant files:
- app/Http/Controllers/Api/PatientController.php
- app/Models/Patient.php
- frontend/src/pages/Patients.jsx
- frontend/src/pages/PatientDetail.jsx

### Encounters and Clinical Workflow
Supports encounter creation, transitions, closure, and access to notes and clinical charts.

Relevant files:
- app/Http/Controllers/Api/EncounterController.php
- frontend/src/pages/EncounterWorkspace.jsx

### Laboratory Orders and Results
Supports placing lab orders, tracking specimens, receiving samples, and entering results.

Relevant files:
- app/Http/Controllers/Api/LabController.php
- frontend/src/pages/Laboratory.jsx

### Imaging
Supports imaging orders and report handling.

Relevant files:
- app/Http/Controllers/Api/ImagingController.php
- frontend/src/pages/Imaging.jsx

### Pharmacy
Supports prescriptions, stock updates, dispensing, and medication administration.

Relevant files:
- app/Http/Controllers/Api/PharmacyController.php
- frontend/src/pages/Pharmacy.jsx

### Billing
Supports invoices, payments, and billing workflows.

Relevant files:
- app/Http/Controllers/Api/BillingController.php
- frontend/src/pages/Billing.jsx

### Wards, ICU, and Dialysis
Supports ward admission, transfer, ICU workflows, dialysis sessions, and patient monitoring.

Relevant files:
- app/Http/Controllers/Api/WardController.php
- app/Http/Controllers/Api/ICUController.php
- app/Http/Controllers/Api/DialysisController.php
- frontend/src/pages/Wards.jsx
- frontend/src/pages/ICU.jsx
- frontend/src/pages/Dialysis.jsx

### Admin and Audit
Supports user management, system audit logs, and security alerts.

Relevant files:
- app/Http/Controllers/Api/UserController.php
- app/Http/Controllers/Api/AuditController.php
- frontend/src/pages/AdminUsers.jsx
- frontend/src/pages/AdminAudit.jsx

## 6. Authentication and Roles

Authentication is handled through Sanctum and the frontend stores the bearer token in local storage.

Common roles represented in the application include:
- admin
- reception
- nurse
- doctor
- lab_tech
- radiologist
- pharmacist
- billing
- dialysis_tech
- records_officer

Role-based access is enforced on both the API routes and frontend pages.

## 7. Setup Instructions

### Prerequisites
- PHP 8.3 or newer
- Composer
- Node.js 18 or newer
- MySQL or another compatible database

### 1. Install backend dependencies
```bash
composer install
```

### 2. Create environment file
```bash
cp .env.example .env
php artisan key:generate
```

Update the database values in .env as needed:
```env
DB_HOST=127.0.0.1
DB_DATABASE=nullcare_emr_clean
DB_USERNAME=root
DB_PASSWORD=your_password
FRONTEND_URL=http://localhost:5173
```

### 3. Run migrations and seed demo users
```bash
php artisan migrate
php artisan db:seed --class=UsersSeeder
```

### 4. Install frontend dependencies
```bash
cd frontend
npm install
```

## 8. Running the Application

### Start the backend
```bash
php artisan serve --port=8000
```

### Start the frontend
```bash
cd frontend
npm run dev
```

The frontend will usually run on http://localhost:5173 while the backend runs on http://localhost:8000.

### Optional queue worker
If background jobs are used, run:
```bash
php artisan queue:listen
```

## 9. Testing

Run the backend test suite with:
```bash
php artisan test
```

Build the frontend with:
```bash
cd frontend
npm run build
```

## 10. API Reference

The project includes an API reference for the main endpoints used by the frontend:
- [API_REFERENCE.md](../API_REFERENCE.md)

The main API base URL during local development is:
- /api in the frontend dev setup
- http://localhost:8000/api for direct backend access

## 11. Documentation Files

Useful documentation files in this repository:
- [README.md](../README.md) - quick project overview and entry point
- [SETUP_GUIDE.md](../SETUP_GUIDE.md) - detailed setup steps
- [API_REFERENCE.md](../API_REFERENCE.md) - endpoint reference
- [docs/PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md) - full project guide

## 12. Notes for Contributors

When working on this project:
- keep frontend API calls aligned with the Laravel routes
- preserve role-based access rules
- add or update tests when changing core workflows
- document new modules or API endpoints in the API reference

## 13. Summary

NullCare EMR is a multi-module healthcare application with a Laravel API backend and a React frontend. It is designed to support day-to-day hospital workflows from patient intake to diagnostics, treatment, billing, and administration.
