# NullCare EMR - Complete Setup Guide

## Prerequisites
- PHP 8.3+
- MySQL 5.7+
- Node.js 18+
- Composer

## Backend Setup

### 1. Clone and Install Dependencies
```bash
git clone https://github.com/css002723-cloud/nullcare-emr-clean.git
cd nullcare-emr-clean
composer install
```

### 2. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and update:
```
DB_HOST=127.0.0.1
DB_DATABASE=nullcare_emr_clean
DB_USERNAME=root
DB_PASSWORD=your_password
FRONTEND_URL=http://localhost:5173
```

### 3. Database Setup
```bash
php artisan migrate --seed
```
This runs `DatabaseSeeder`, which seeds demo users, drug stock, and a clinical demo dataset in the correct order. (Note: `UsersSeeder.php` in this folder is leftover from an earlier schema — it references `Role`/`Department` models that no longer exist and will error if run directly. Use the command above, not `--class=UsersSeeder`.)

### 4. Start Backend Server
```bash
php artisan serve --port=8000
```

Backend runs on: `http://localhost:8000`

---

## Frontend Setup

### 1. Install Dependencies
```bash
cd frontend
npm install
```

### 2. Environment Setup
```bash
cp .env.example .env.local
```

Verify `.env.local` contains:
```
VITE_API_BASE_URL=http://localhost:8000/api
```

### 3. Start Frontend Dev Server
```bash
npm run dev
```

Frontend runs on: `http://localhost:5173`

---

## Test Login Credentials

Login is by **username**, not email. All accounts share the same password.

| Username | Password | Role |
|-------|----------|------|
| admin | nullcare123 | Administrator |
| reception1 | nullcare123 | Reception |
| nurse1 | nullcare123 | Nurse |
| doctor1 | nullcare123 | Doctor |
| labtech1 | nullcare123 | Lab Technician |
| radiologist1 | nullcare123 | Radiologist |
| pharmacist1 | nullcare123 | Pharmacist |
| billing1 | nullcare123 | Billing Officer |
| dialysis1 | nullcare123 | Dialysis Technician |
| records1 | nullcare123 | Records Officer |

---

## Running Both Services

### Terminal 1 (Backend)
```bash
php artisan serve --port=8000
```

### Terminal 2 (Frontend)
```bash
cd frontend
npm run dev
```

### Terminal 3 (Optional - Queue Worker)
```bash
php artisan queue:listen
```

---

## Production Build

### Frontend Build
```bash
cd frontend
npm run build
```

Output: `frontend/dist/`

---

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check DB credentials in `.env`
- Run: `php artisan migrate:fresh --seed`

### CORS Errors
- Backend CORS middleware is enabled
- Verify `FRONTEND_URL` in `.env` matches your frontend URL

### API Not Responding
- Check backend is running on port 8000
- Verify `VITE_API_BASE_URL` in frontend `.env.local`
- Check browser console for network errors

### Login Not Working
- Verify database seeders ran: `php artisan migrate --seed`
- Login uses **username**, not email (e.g. `doctor1`, not an email address) — check for exact match, case-sensitive
- Clear browser localStorage and try again

---

## Demo Accounts and Roles

See the **Test Login Credentials** table above. Seeded via `database/seeders/TestUserSeeder.php` — the file actually wired into `DatabaseSeeder`. `UsersSeeder.php` also exists in this folder but is unused, stale leftover from an earlier version of the schema; don't run it.

---

## Next Steps

1. ✅ Complete database migrations
2. ✅ Seed test users
3. ✅ Configure CORS
4. ✅ Test login flow
5. 📝 Build remaining CRUD pages
6. 📝 Add error handling and validation feedback
7. 📝 Implement offline mode with IndexedDB
8. 📝 Add unit tests
9. 📝 Deploy to production

---

## API Endpoints

### Authentication
- `POST /api/login` - Login
- `POST /api/logout` - Logout  
- `GET /api/auth/me` - Get current user

### Patients
- `GET /api/patients` - List patients
- `POST /api/patients` - Create patient
- `GET /api/patients/{id}` - Get patient details
- `GET /api/patients/check-duplicates` - Check duplicate patients

### Encounters
- `POST /api/encounters` - Create encounter
- `GET /api/encounters/{id}` - Get encounter
- `PATCH /api/encounters/{id}` - Update encounter
- `POST /api/encounters/{id}/close` - Close encounter

### Other Resources
- See `routes/api.php` for full API documentation

---

## Support

For issues or questions, check the README.md or create an issue on GitHub.
