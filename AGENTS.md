Baca AGENTS.md, docs/frontend-standard.md, docs/ui-handoff/UI-MASTER.md,
FEATURE-MAP.md, dan design-system/\*. Jangan mengerjakan fitur apa pun dulu.

Bangun fondasi sesuai standar:

1. Tooling: TS strict, alias @/, ESLint (rules-of-hooks, exhaustive-deps, larangan literal nama
   role: daftar ambil dari docs/ui-handoff/ jika ada), dependency-cruiser dengan aturan bagian 5,
   Vitest + Testing Library + MSW.
2. Struktur src/ bagian 2 (hanya folder yang dibutuhkan).
3. Styling: <isi keputusan Anda: CSS Modules atau Tailwind>. Pasang tokens dari
   design-system/tokens.\*, font, dan aset.
4. shared/: httpClient + pemetaan ApiError, formatCurrency (input integer sen) + test,
   komponen bersama dari design-system/components.md (hanya yang dipakai >= 2 layar),
   komponen loading/error/empty.
5. app/: QueryClient default, AuthProvider, PermissionProvider + usePermission, RequireAuth,
   RequirePermission, router lazy, error boundary.
6. layouts/: shell dan navigasi dari FEATURE-MAP. Menu dibangun dari permission,
   bukan dari nama role.
7. Mode dev: MSW aktif, dengan /me/permissions palsu yang bisa diganti untuk menguji berbagai
   kombinasi permission.
   Jalankan lint, tsc, dependency-cruiser, test. Laporkan apa yang selesai dan apa yang tertunda.
