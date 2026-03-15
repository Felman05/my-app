# Doon

Smart Regional Activity Tracking and Recommendation System for CALABARZON.

## Stack
- Backend: Laravel
- Frontend: React + Inertia.js
- Styling: Tailwind CSS
- Database: MySQL (XAMPP compatible)

## Is a database already provided?
Short answer: there is no preloaded production database dump in this repo.

What is provided:
- Laravel migrations in `database/migrations/`
- Seeders in `database/seeders/`
- A local `database/database.sqlite` file used mainly for testing/dev fallback

If you already have `doon_db` with your own SQL schema, you can use it directly.

## XAMPP MySQL Setup
1. Start `Apache` and `MySQL` in XAMPP Control Panel.
2. Ensure your `.env` has:
	- `DB_CONNECTION=mysql`
	- `DB_HOST=127.0.0.1`
	- `DB_PORT=3306`
	- `DB_DATABASE=doon_db`
	- `DB_USERNAME=root`
	- `DB_PASSWORD=`
3. Clear Laravel cached config:
	- `php artisan config:clear`
4. Run app services:
	- `php artisan serve`
	- `npm run dev`

## Import Your Existing SQL Schema
If `doon_db` does not exist yet:
1. Create it in phpMyAdmin or MySQL CLI.
2. Import your SQL script (the schema you provided).

PowerShell + MySQL CLI example:
```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS doon_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root doon_db < path\\to\\your\\doon_db.sql
```

## If You Prefer Laravel-Managed Schema Instead
Use Laravel migrations + seeders instead of importing your SQL dump:
```bash
php artisan migrate:fresh --seed
```

## Schema Compatibility Note
This codebase currently supports both patterns at auth/runtime level:
- `users.role` (string role)
- `users.role_id` with `roles.name` (your SQL style)

The registration flow now auto-handles either shape where possible.

## Test and Build
```bash
php artisan test
npm run build
```

## Current Implemented Foundation
- Role-based auth and dashboard routing
- Core domain models/migrations scaffold
- Landing/Login/Register pages (Inertia)
- Tourist dashboard and activity browsing page
- Provider activity listing + submit flow
- Seeders for CALABARZON provinces/municipalities and categories
