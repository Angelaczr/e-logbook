# Fondasi Frontend

## Keputusan

- Frontend menggunakan Vue 3 Composition API, TypeScript strict, dan Inertia.js.
- Styling menggunakan Tailwind CSS 4, yang sudah menjadi dependency project. CSS variables menjadi lapisan token semantik sementara.
- Permission dimuat dari endpoint server `/me/permissions`; UI tidak menghitung permission dari role.
- Route Inertia dimuat secara lazy dari `src/features/<feature>/pages/<Page>.vue`.

## Batas dokumentasi desain

Instruksi project merujuk `docs/frontend-standard.md`, `docs/ui-handoff/UI-MASTER.md`, `FEATURE-MAP.md`, serta `design-system/tokens.*` dan `design-system/components.md`. File tersebut tidak ada saat fondasi ini dibuat. `FRONTEND_STANDARTS.md` yang tersedia adalah standar React, sehingga aturan struktur dan dependency diadaptasi untuk Vue; detail visual, menu, font khusus, aset, dan daftar komponen bersama menunggu dokumen desain yang benar. Font menggunakan stack sistem supaya build tidak perlu mengambil font dari internet.

Menu menggunakan permission sebagai syarat tampil. `src/app/router/routes.ts` sengaja belum memuat route bisnis hingga `FEATURE-MAP.md` tersedia.

## Pengujian izin lokal

MSW hanya diaktifkan pada mode development. Atur kombinasi izin melalui console browser sebelum memuat ulang aplikasi:

```js
localStorage.setItem('e-logbook.mock-permissions', 'dashboard:read,logbook:create');
location.reload();
```

Hapus key `e-logbook.mock-permissions` untuk kembali ke kombinasi contoh bawaan. Alternatif sementara di tab aktif: set `window.__MOCK_PERMISSIONS__` ke array permission lalu reload. Mock hanya menggantikan `/me/permissions`; endpoint lain diteruskan ke backend.
