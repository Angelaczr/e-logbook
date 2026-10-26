# Enterprise Daily Work Logbook & Activity Tracking System

Aplikasi pencatatan pekerjaan harian karyawan yang ditinjau dan disetujui atasan.

## Stack
- Laravel 13, PHP 8.3
- Inertia.js + Vue 3 (Composition API, `<script setup>`, TypeScript)
- Tailwind CSS 4 + shadcn-vue
- PostgreSQL (database `e_logbook_db`)

## Konvensi
- Role dan status memakai PHP Enum string-backed (`app/Enums`), disimpan sebagai kolom string, bukan ENUM database.
- Logika bisnis di `app/Services`; controller tipis.
- Otorisasi lewat Policy (`app/Policies`).
- Validasi lewat Form Request (`app/Http/Requests`).
- Halaman Inertia di `resources/js/pages`.
- Komponen UI memakai shadcn-vue.
- Test memakai Pest.

## Perintah penting
- Test: `php artisan test`
- Lint PHP: `vendor/bin/pint` (cek saja: `vendor/bin/pint --test`)
- Build frontend: `npm run build`; dev: `npm run dev`
- Frontend: `npm run lint`, `npm run typecheck`, `npm test`

## Aturan
- Jangan pernah menampilkan isi `.env` (terutama `DB_PASSWORD`); `.env` harus tetap ada di `.gitignore`.
- Jalankan test setelah setiap perubahan.

## Catatan kondisi saat ini
- Standar dan konteks lain: `BACKEND_STANDARDS.md`, `FRONTEND_STANDARTS.md`, `docs/` (prd, usecase, class, frontend-foundation).
- Pest, shadcn-vue, dan `resources/js/pages` belum terpasang; konvensi di atas adalah target. Saat ini test memakai PHPUnit dan fondasi frontend ada di `src/`.
