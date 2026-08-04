# NullCare EMR Project Documentation

## 1. Project Overview

NullCare EMR is a full-stack healthcare management system designed to support clinical, administrative, and operational workflows in a hospital or clinic environment. The project combines a Laravel backend API with a React frontend interface to provide a complete electronic medical record experience.

This system supports:
- patient registration and record management
- clinical encounters and notes
- laboratory ordering and result tracking
- imaging workflows
- pharmacy and medication management
- billing and invoice processing
- ward, ICU, and dialysis operations
- admin and audit monitoring

## 2. Objectives

The core goals of the project are to:
- centralize patient information
- streamline clinical and administrative workflows
- improve traceability of orders and results
- provide role-based access to different departments
- support future extension with additional hospital features

## 3. System Architecture

### Backend
The backend is built with Laravel and exposes a REST-style API secured with Sanctum authentication.

Main backend areas:
- Controllers: app/Http/Controllers
- Models: app/Models
- Requests: app/Http/Requests
- Services: app/Services
- Routes: routes/api.php and routes/web.php
- Database: database/migrations and database/seeders

### Frontend
The frontend is built with React and Vite, using Tailwind CSS for styling and React Router for navigation.

Main frontend areas:
- Pages: frontend/src/pages
- Reusable UI: frontend/src/components
- Auth state: frontend/src/context
- API client: frontend/src/services/api.js

## 4. Technology Stack

### Backend
- PHP 8.3+
- Laravel 13
- Sanctum
- Eloquent ORM
- MySQL-compatible database
- Pest testing framework

### Frontend
- React 18
- Vite
- React Router DOM
- Tailwind CSS
- Axios
- lucide-react
- Dexie for client-side storage support

## 5. Main Modules

### 5.1 Patient Management
Handles patient registration, profile viewing, duplicate detection, and patient history.

Relevant areas:
- app/Http/Controllers/Api/PatientController.php
- app/Models/Patient.php
- frontend/src/pages/Patients.jsx
- frontend/src/pages/PatientDetail.jsx

### 5.2 Clinical Encounters
Supports encounter creation, transitions, closure, and clinical workspace activities.

Relevant areas:
- app/Http/Controllers/Api/EncounterController.php
- frontend/src/pages/EncounterWorkspace.jsx

### 5.3 Laboratory Workflow
Supports ordering lab tests, collecting specimens, receiving samples, and entering results.

Relevant areas:
- app/Http/Controllers/Api/LabController.php
- frontend/src/pages/Laboratory.jsx

### 5.4 Imaging Workflow
Supports imaging order creation and report handling.

Relevant areas:
- app/Http/Controllers/Api/ImagingController.php
- frontend/src/pages/Imaging.jsx

### 5.5 Pharmacy Workflow
Supports prescriptions, stock management, dispensing, and administration.

Relevant areas:
- app/Http/Controllers/Api/PharmacyController.php
- frontend/src/pages/Pharmacy.jsx

### 5.6 Billing Workflow
Supports invoice management, payments, and billing operations.

Relevant areas:
- app/Http/Controllers/Api/BillingController.php
- frontend/src/pages/Billing.jsx

### 5.7 Wards, ICU, and Dialysis
Supports admissions, transfers, monitoring, ICU notes, and dialysis sessions.

Relevant areas:
- app/Http/Controllers/Api/WardController.php
- app/Http/Controllers/Api/ICUController.php
- app/Http/Controllers/Api/DialysisController.php
- frontend/src/pages/Wards.jsx
- frontend/src/pages/ICU.jsx
- frontend/src/pages/Dialysis.jsx

### 5.8 Administration and Audit
Supports user management, audit logs, and security alerts.

Relevant areas:
- app/Http/Controllers/Api/UserController.php
- app/Http/Controllers/Api/AuditController.php
- frontend/src/pages/AdminUsers.jsx
- frontend/src/pages/AdminAudit.jsx

## 6. Authentication and Authorization

Authentication uses Laravel Sanctum with bearer tokens. The frontend stores the token locally and sends it with API requests.

The system also uses role-based access control for different departments and users, including roles such as:
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

## 7. Setup Instructions

### Prerequisites
- PHP 8.3 or newer
- Composer
- Node.js 18 or newer
- MySQL or another compatible database

### Backend Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Then configure your database credentials in the .env file.

### Database Setup
```bash
php artisan migrate
php artisan db:seed --class=UsersSeeder
```

### Frontend Setup
```bash
cd frontend
npm install
```

## 8. Running the Application

### Start backend
```bash
php artisan serve --port=8000
```

### Start frontend
```bash
cd frontend
npm run dev
```

The typical local setup uses:
- backend: http://localhost:8000
- frontend: http://localhost:5173

## 9. Testing and Build

### Backend tests
```bash
php artisan test
```

### Frontend build
```bash
cd frontend
npm run build
```

## 10. API Reference

The main API reference is available in:
- API_REFERENCE.md

The frontend primarily communicates with routes under /api, including authentication, patients, encounters, lab orders, pharmacy, billing, and admin features.

## 11. Documentation Files

Useful files in the repository:
- README.md
- SETUP_GUIDE.md
- API_REFERENCE.md
- PROJECT_DOCUMENTATION.md

## 12. Notes for Developers

When extending this project:
- keep frontend API calls aligned with Laravel routes
- preserve role-based access rules
- update documentation when adding new modules or endpoints
- add or update tests for changes to core workflows

## 13. Summary

NullCare EMR is a multi-module healthcare application designed to support hospital workflows from patient intake and encounters to lab results, pharmacy operations, billing, and administration. It is structured as a Laravel API backend plus a React frontend, making it suitable for further extension and deployment.
