# Standar Frontend: React Feature-Based

Catatan standar untuk semua project React saya. Basis: `naserrasoulii/feature-based-react`
dengan penyesuaian naming dan arsitektur di bawah. Bagian 17 bisa disalin ke
`AGENTS.md` / `.github/copilot-instructions.md` agar AI agent mengikutinya.
Penyesuaian khusus project SMTB ada di Lampiran A.

> Filosofi sama dengan standar backend: **pemisahan dijaga arah import yang
> ditegakkan tool di CI, bukan disiplin manual.**

---

## 1. Prinsip Utama

1. **Kelompokkan berdasarkan business feature, bukan role.** Jangan buat folder
   `admin/`, `user/`, `manager/`. Role diselesaikan lewat **permission dan aksi dari server**.
2. **Satu feature = satu folder mandiri** berisi components, hooks, pages, services,
   stores, types miliknya sendiri.
3. **Kode lintas fitur** masuk ke `shared/`. Kode yang hanya dipakai satu fitur
   tetap berada di fitur tersebut.
4. **Route didaftarkan eksplisit** di `app/router/`. Nama berkas tidak otomatis
   menjadi route.
5. **Pemilihan implementasi** (demo vs HTTP, dsb.) dilakukan di `app/config/`,
   tidak pernah di halaman atau komponen UI.
6. **Backend adalah sumber kebenaran** untuk bentuk data, permission, dan aturan
   bisnis. Frontend tidak menulis ulang tipe response dan tidak menduplikasi aturan domain.
7. **Aturan dependensi wajib ditegakkan tool** (bagian 5). Tanpa pagar, pemisahan luntur diam-diam.
8. **Mulai sederhana.** Abstraksi (adapter, store, barrel) dibuat hanya bila ada
   alasan konkret, bukan "untuk jaga-jaga".

---

## 2. Struktur Folder

```text
src/
  app/
    config/                  Konfigurasi, env, pemilihan implementasi
    providers/               Provider global (auth, query client, theme)
    router/                  Definisi route, guard, composition route
  features/
    <feature-name>/          kebab-case, contoh: auth, user-management, proposal
      components/            Komponen khusus fitur
      hooks/                 Hook khusus fitur (useXxx.ts), termasuk query/mutation
      pages/                 Halaman fitur (XxxPage.tsx)
      services/              Pemanggil API, mapper ke view model
      stores/                Context / state klien khusus fitur (jarang)
      schemas/               Schema form (xxxSchema.ts)
      constants/             Konstanta khusus fitur (termasuk query keys)
      types/                 Tipe UI/view model khusus fitur
      index.ts               Public API tipis fitur (opsional, lihat bagian 15)
  layouts/                   Layout shell (AdminLayout.tsx, AuthLayout.tsx)
  shared/
    api/
      generated/             Tipe/klien hasil generate dari OpenAPI backend (jangan edit)
    components/              Komponen reusable lintas fitur
    hooks/                   Hook reusable lintas fitur
    services/                Klien HTTP bersama (httpClient.ts)
    utils/                   Utilitas bersama
    constants/               Konstanta bersama (permissions.ts)
    types/                   Tipe bersama (pagination, apiError)
  assets/
  App.tsx                    Penggabungan provider + router
  main.tsx                   Bootstrap React
```

Buat subfolder hanya jika dibutuhkan. Jangan membuat folder kosong.

---

## 3. Naming Convention

| Jenis           | Convention               | Contoh              |
| --------------- | ------------------------ | ------------------- |
| React Component | PascalCase               | `UserTable.tsx`     |
| Page            | PascalCase + `Page`      | `UsersPage.tsx`     |
| Layout          | PascalCase + `Layout`    | `AdminLayout.tsx`   |
| Hook            | camelCase + prefix `use` | `useUsers.ts`       |
| Service         | camelCase + `Service`    | `userService.ts`    |
| Schema          | camelCase + `Schema`     | `userSchema.ts`     |
| Type            | camelCase                | `user.ts`           |
| Constants       | camelCase                | `userStatus.ts`     |
| Query keys      | camelCase + `Keys`       | `proposalKeys.ts`   |
| Utility         | camelCase                | `formatCurrency.ts` |
| Context         | PascalCase + `Context`   | `AuthContext.tsx`   |
| Provider        | PascalCase + `Provider`  | `AuthProvider.tsx`  |

### Aturan tambahan

- **Nama folder** selalu `kebab-case` (`user-management`, bukan `userManagement`).
- **Nama export di dalam berkas** mengikuti jenisnya:
  - Component, Page, Layout, Context, Provider: `PascalCase` (`UsersPage`)
  - Hook, service instance, util, schema: `camelCase` (`useUsers`, `userService`, `userSchema`)
  - Tipe/interface: `PascalCase` (`User`, `UserStatus`) walau nama berkasnya `user.ts`
  - Konstanta nilai tetap: `UPPER_SNAKE_CASE` atau objek `as const` dengan key jelas
- **Satu komponen utama per berkas**, nama berkas sama dengan nama export.
- **Berkas test** berdampingan: `UserTable.test.tsx`, `formatCurrency.test.ts`.
- **Berkas style** mengikuti nama komponen: `UsersPage.css` / `UsersPage.module.css`.
- **Berkas hasil generate** berada di `shared/api/generated/` dan tidak diedit manual.
- Hindari nama generik seperti `utils.ts`, `helpers.ts`, `common.ts`, `data.ts`.
  Gunakan nama yang menjelaskan isinya (`formatCurrency.ts`).

### Penyesuaian terhadap template asli

| Template asli               | Standar saya                   |
| --------------------------- | ------------------------------ |
| `hooks/use-auth.ts`         | `hooks/useAuth.ts`             |
| `pages/proposal/index.tsx`  | `pages/ProposalsPage.tsx`      |
| `pages/proposal/create.tsx` | `pages/ProposalCreatePage.tsx` |
| `pages/proposal/[id].tsx`   | `pages/ProposalDetailPage.tsx` |
| `AuthService` (berkas)      | `authService.ts`               |

---

## 4. Contoh Feature Lengkap

```text
features/
  proposal/
    components/
      ProposalTable.tsx
      ProposalForm.tsx
      ProposalStatusBadge.tsx
      ProposalActions.tsx        # tombol dari allowedActions (bagian 10)
    hooks/
      useProposals.ts
      useProposalDetail.ts
      useCreateProposal.ts
    pages/
      ProposalsPage.tsx
      ProposalCreatePage.tsx
      ProposalDetailPage.tsx
    services/
      proposalService.ts
    schemas/
      proposalSchema.ts
    constants/
      proposalStatus.ts
      proposalKeys.ts
    types/
      proposal.ts                # view model UI; tipe API diimpor dari generated
    index.ts                     # public API tipis (opsional)
```

Route didaftarkan eksplisit dan di-lazy-load per fitur di `app/router/`:

```tsx
{
  path: "/proposals",
  lazy: () => import("@/features/proposal/pages/ProposalsPage"),
},
{ path: "/proposals/create", lazy: () => import("@/features/proposal/pages/ProposalCreatePage") },
{ path: "/proposals/:id", lazy: () => import("@/features/proposal/pages/ProposalDetailPage") },
```

---

## 5. Alur Dependency dan Penegakan di CI

```text
Page / Component
   -> Hook (useXxx)              # query/mutation, membungkus service
      -> Service (xxxService)    # memanggil API, memetakan ke view model
         -> shared/services/httpClient + shared/api/generated
```

Aturan arah dependency:

- **UI tidak memanggil HTTP langsung.** Selalu lewat hook, lalu service.
- **Service tidak mengimpor komponen atau hook React.**
- **Types** adalah lapisan paling bawah: boleh diimpor siapa saja, tidak mengimpor siapa pun.
- **`shared/`** tidak boleh mengimpor dari `features/`.
- **Fitur A tidak mengimpor internal fitur B.** Jika butuh berbagi, pindahkan ke
  `shared/`, atau konsumsi lewat public API fitur (`index.ts`) atau tipe kontrak.
- **`app/`** boleh mengimpor dari `features/` dan `shared/` (composition root).
- **Tidak ada circular dependency.**

**Wajib dijaga tool.** Pasang `dependency-cruiser` (atau `eslint-plugin-boundaries`)
sejak fitur pertama; pelanggaran = build gagal. Contoh `.dependency-cruiser.cjs` (ringkas):

```js
module.exports = {
  forbidden: [
    {
      name: "no-circular",
      severity: "error",
      from: {},
      to: { circular: true },
    },
    {
      name: "shared-tanpa-features",
      severity: "error",
      from: { path: "^src/shared/" },
      to: { path: "^src/features/" },
    },
    {
      name: "antar-fitur-lewat-public-api",
      severity: "error",
      comment: "Fitur lain hanya boleh diimpor lewat index.ts-nya",
      from: { path: "^src/features/([^/]+)/" },
      to: {
        path: "^src/features/[^/]+/",
        pathNot: ["^src/features/$1/", "^src/features/[^/]+/index\\.ts$"],
      },
    },
    {
      name: "service-tanpa-ui",
      severity: "error",
      from: { path: "^src/features/[^/]+/services/" },
      to: {
        path: [
          "(^|/)node_modules/react(-dom)?/",
          "^src/features/[^/]+/(hooks|components|pages)/",
        ],
      },
    },
    {
      name: "ui-tanpa-http-langsung",
      severity: "error",
      from: { path: "^src/features/[^/]+/(components|pages)/" },
      to: { path: "^src/shared/services/httpClient" },
    },
  ],
};
```

Pola adapter (mis. `DemoAuthRepository` vs `HttpAuthRepository` dengan pemilihan di
`app/config/auth.ts`) **hanya dipakai bila benar-benar ada lebih dari satu
implementasi**. Satu implementasi cukup berupa service biasa.

---

## 6. State Management

### 6.1 Urutan keputusan: di mana state ditaruh

Jangan melompat ke langkah yang lebih tinggi dari yang dibutuhkan:

1. **Bisa dihitung dari state/props lain?** Hitung saat render. Jangan disimpan (derived state).
2. **Berasal dari server?** Server state (6.2).
3. **Perlu dibagikan atau di-bookmark** (filter, halaman, sort, tab)? URL (6.4).
4. **Dipakai satu komponen atau subtree dekat?** `useState`/`useReducer` lokal; angkat ke induk terdekat seperlunya.
5. **Dipakai banyak komponen berjauhan dalam satu fitur, jarang berubah** (sesi, tema)? Context (6.3).
6. **Sering berubah, banyak konsumen, perlu selector?** Store ringan di `stores/` fitur (6.3).

| Jenis state                       | Tempat                        | Default yang disarankan          |
| --------------------------------- | ----------------------------- | -------------------------------- |
| Server state (data dari API)      | Hook fitur                    | TanStack Query                   |
| State form                        | Komponen form                 | React Hook Form                  |
| State UI lokal (modal, tab)       | Komponen                      | `useState` / `useReducer`        |
| State klien bersama (jarang)      | `stores/` fitur atau provider | Context; store ringan bila perlu |
| State URL (filter, halaman, sort) | Search params                 | router                           |

Library di atas adalah default; boleh diganti, tetapi **urutan keputusan dan pemisahan jenis state tetap berlaku**.
Jangan menyalin data server ke `useState` atau store.

### 6.2 Server state

- Setiap fitur punya **query key factory** di `constants/xxxKeys.ts`, tanpa string key tersebar:

```ts
export const proposalKeys = {
  all: ["proposal"] as const,
  list: (filter: ProposalFilter) =>
    [...proposalKeys.all, "list", filter] as const,
  detail: (id: string) => [...proposalKeys.all, "detail", id] as const,
};
```

- Mutation meng-invalidate key lewat factory yang sama.
- Atur `staleTime`, `retry`, dan `gcTime` default di `app/providers/` (QueryClient). Jangan retry error 4xx.
- **Optimistic update** hanya untuk aksi ringan yang aman di-rollback (mis. toggle).
  Aksi yang hasilnya ditentukan aturan server (approval, transaksi keuangan) **menunggu respons server**.
- Jangan menghitung ulang aturan bisnis di klien (status yang boleh dilakukan, validasi
  lintas-dokumen). Minta ke server (bagian 10).
- Akses `import.meta.env` / `process.env` hanya di `app/config/`.

### 6.3 State klien (Context dan store)

- Dimiliki fitur (`stores/`), **hanya state klien** (UI, wizard, draft). Bukan data server.
- **Provider global hanya** untuk auth, query client, theme, dan permission. Provider lain
  dipasang di route fitur yang memakainya, bukan di `App.tsx`.
- **Context:** satu concern per Context (jangan satu "AppContext" raksasa); nilai di-memoize;
  diekspos lewat hook (`useAuth`), komponen tidak memanggil `useContext` langsung.
- **Store:** diakses lewat selector, bukan seluruh store; aksi dinamai kata kerja
  (`openDialog`, `resetDraft`); tidak diakses langsung dari fitur lain (lewat public API fitur).
- Jangan menyimpan data sensitif di storage browser. Persist hanya preferensi UI.

### 6.4 State URL

- Filter, sort, halaman, dan tab yang perlu dibagikan atau di-bookmark disimpan di search params.
  **URL adalah sumber kebenaran**; jangan duplikasi ke `useState`.
- Bungkus parse/serialize dalam satu hook per fitur (mis. `useProposalFilter`) dan
  validasi nilai dari URL (jangan percaya input mentah).

### 6.5 Efek (`useEffect`)

- **Bukan** untuk mengambil data (pakai query), **bukan** untuk menurunkan state dari
  state lain, **bukan** untuk merespons event pengguna (pakai event handler).
- Boleh untuk sinkronisasi dengan sistem di luar React (DOM, subscription, timer) dan
  **wajib punya cleanup** bila membuka sumber daya.
- Dependency array lengkap; `react-hooks/rules-of-hooks` dan `react-hooks/exhaustive-deps`
  diset `error` di ESLint.

---

## 7. Hooks

| Jenis hook        | Isi                                | Lokasi                | Contoh                           |
| ----------------- | ---------------------------------- | --------------------- | -------------------------------- |
| Query hook        | `useQuery` membungkus service      | `features/<x>/hooks/` | `useProposals`                   |
| Mutation hook     | `useMutation` + invalidasi         | `features/<x>/hooks/` | `useCreateProposal`              |
| Hook UI/state     | Logika UI, state URL, bantuan form | `features/<x>/hooks/` | `useProposalFilter`              |
| Hook lintas fitur | Generik, tidak mengenal domain     | `shared/hooks/`       | `usePermission`, `usePagination` |

Aturan:

1. **Satu tanggung jawab per hook**, kira-kira ≤ 80 baris. Lebih dari itu, pecah.
2. `useQuery` / `useMutation` **hanya dipanggil di dalam hook fitur**, tidak langsung di
   komponen atau halaman. Komponen memakai `useProposals(filter)`.
3. Hook membungkus **service**; hook tidak memanggil `httpClient` langsung.
4. Hook **tidak memuat aturan bisnis**. Aturan domain ada di server; hook hanya menyajikan hasilnya.
5. **Efek samping UI** (toast, navigasi, menutup dialog) dilakukan lewat callback dari
   pemanggil (`mutate(data, { onSuccess })`), bukan di dalam hook, agar hook bisa dipakai ulang.
6. Kembalikan **objek bernama** (`{ data, isLoading, error }`). Tuple hanya untuk ≤ 2 nilai
   bergaya `useState`.
7. Hook tidak mengembalikan JSX. Parameter memakai tipe terdefinisi (bukan objek anonim),
   dan nilai yang masuk ke query key harus stabil.
8. Hook fitur tidak mengimpor hook internal fitur lain (lewat public API fitur).
9. Hook hanya dipanggil di level atas komponen/hook lain (`rules-of-hooks` = `error`).
10. Hook diuji lewat komponen atau `renderHook`, bukan dengan memeriksa state internal.

---

## 8. Komponen dan Styling

### Komponen

- **Dua peran:** komponen tampilan (props masuk, JSX keluar, tanpa fetch) dan
  halaman/penghubung (`XxxPage` memanggil hook dan merangkai komponen). **Page dibuat tipis.**
- **Ukuran:** komponen > ~200 baris atau JSX bersarang > 3 tingkat dipecah/diekstrak.
- **Props:** tipe eksplisit (`XxxProps`), kira-kira ≤ 7 props. Bila lebih, kelompokkan
  atau pakai komposisi (`children`, slot). Hindari prop drilling lebih dari 2 tingkat:
  pakai komposisi atau Context fitur.
- Hindari tumpukan flag boolean (`isA`, `isB`, `isC`); pakai `variant` atau union terdiskriminasi.
- Props tidak dimutasi. Handler internal dinamai `handleXxx`; prop callback dinamai `onXxx`.
- `key` pada daftar berasal dari id stabil, bukan indeks (kecuali daftar statis).
- Komponen di `shared/components/` **tidak mengenal domain** (tidak mengimpor dari `features/`).
- Komponen UI dasar (`Button`, `Input`, `Modal`, `DataTable`) hanya ada di `shared/components/`;
  fitur memakainya dan tidak menulis ulang.

### Styling

- **Satu pendekatan per project** (CSS Modules atau Tailwind). Pilih di awal, catat di
  README project, jangan dicampur.
- **Design token** (warna, spasi, tipografi) di satu tempat (CSS variables atau konfigurasi
  Tailwind). Tidak ada nilai warna literal (`#fff`, `rgb(...)`) di komponen.
- Dukungan tema gelap (bila dibutuhkan) lewat token, bukan percabangan di komponen.

---

## 9. Kontrak API dan Tipe

- **Schema backend adalah satu-satunya sumber kebenaran.** Tipe response dan request
  di-generate dari OpenAPI backend ke `shared/api/generated/` lewat script (jalankan
  ulang saat backend berubah; hasilnya di-commit).
- Frontend **tidak menulis ulang** tipe response secara manual.
- `features/<x>/types/` hanya untuk **view model UI** (bentuk yang nyaman bagi komponen)
  dan boleh mengimpor dari `generated/` dengan `import type`.
- Pemetaan API → view model dilakukan di **service**, bukan di komponen.
- Tangani format error seragam dari backend (kode, pesan, `requestId`) di satu tempat
  (`httpClient`), lalu petakan ke tipe `ApiError` di `shared/types/apiError.ts`.

### Konvensi TypeScript

- Aktifkan `strict`. Dilarang `any` dan non-null assertion (`!`) tanpa komentar alasan.
- Hindari `enum`. Pakai objek `as const` + tipe union dari nilainya.
- Status dan varian dimodelkan sebagai **union terdiskriminasi**; `switch` dibuat
  _exhaustive_ (cabang `never`) agar nilai baru dari backend langsung terdeteksi compiler.
- Hindari `as` casting. Data dari luar (URL, storage, respons tak terpercaya) divalidasi
  dengan schema/type guard di batas masuk.
- `type` sebagai default; `interface` boleh untuk props komponen atau kontrak yang diperluas.
- Gunakan `import type` untuk tipe saja.

---

## 10. Role, Permission, dan Guard

Prinsip: **UI bertanya "boleh apa?", bukan "kamu siapa?"**. Satu aplikasi melayani
semua role; yang berbeda adalah data dan aksi yang diizinkan server.

- Tidak ada folder per role. **Tidak ada string nama role di kode frontend.**
- Definisikan permission sebagai konstanta di `shared/constants/permissions.ts`
  (format `resource:action`, mengikuti backend).
- Daftar permission aktor diambil dari **satu endpoint** (mis. `/me/permissions`) yang
  dihitung backend. Frontend tidak menurunkan permission dari nama role.
- Proteksi route lewat guard di `app/router/` (`RequireAuth`, `RequirePermission`).
- Sembunyikan/tampilkan elemen UI lewat hook: `usePermission("proposal:create")`.
- Navigasi menu dibangun dari permission, bukan dari `if (role === ...)`.
- **Aksi per dokumen** (bergantung status, kepemilikan, cakupan) **dikirim server**
  sebagai `allowedActions`; UI hanya merender tombol sesuai daftar itu:

```tsx
// response: { id, status, allowedActions: ["approve", "return"] }
{
  proposal.allowedActions.includes("approve") && (
    <ApproveButton id={proposal.id} />
  );
}
```

- Scoping data (hanya unit/paroki milik aktor) dilakukan server. Halaman yang sama
  dirender untuk semua role.
- **Menyembunyikan UI bukan keamanan.** Penegakan tetap di backend; UI hanya kenyamanan.
- Beranda atau widget yang berbeda per jenis pengguna dipilih lewat permission,
  tetap di satu komponen/aplikasi. Pecah aplikasi hanya bila alur kerja benar-benar
  berbeda dan penggunanya tidak bersinggungan.

Penegakan: aturan ESLint melarang literal nama role (contoh di Lampiran A).

---

## 11. Form dan Validasi

- Satu schema per form di `schemas/xxxSchema.ts` (default: Zod + React Hook Form).
- Schema form memvalidasi **bentuk dan kenyamanan input**. **Makna bisnis** (transisi
  status, cakupan, periode tutup) divalidasi backend; frontend menampilkan hasilnya.
- Tampilkan error server (mis. konflik, validasi) pada field/form terkait; sertakan
  `requestId` untuk error tak terduga agar mudah dilacak.
- Cegah submit ganda (disable tombol saat mutation berjalan).

---

## 12. Error, Loading, dan Empty State

- Setiap route punya **error boundary**; error tak terduga menampilkan pesan generik
  - `requestId`, tanpa stack trace.
- Petakan status HTTP secara konsisten: 401 → alur login/refresh, 403 → halaman tanpa akses,
  404 → not found, 409 → pesan konflik yang bisa ditindaklanjuti.
- Setiap daftar/data punya tiga state eksplisit: **loading, error, empty**. Jangan layar kosong.
- Komponen state ini ada di `shared/components/` agar seragam.

---

## 13. Performa dan Aksesibilitas

- **Lazy-load route per fitur** (bagian 4). Tidak ada import statis halaman fitur di router.
- Hindari optimasi prematur: jangan `useMemo`/`useCallback` tanpa masalah terukur
  (lebih sedikit lagi bila compiler React dipakai).
- Daftar panjang: paginasi di server, virtualisasi bila perlu.
- Gunakan elemen semantik (`button`, `a`, `label`), label untuk setiap input, dan
  navigasi keyboard yang bisa dipakai pada dialog/modal.
- Jangan merender HTML mentah dari data (hindari `dangerouslySetInnerHTML`).
- Token akses: pilih cookie `httpOnly` bila backend mendukung; hindari `localStorage`.

---

## 14. Testing

| Lapisan                       | Cara                                                         |
| ----------------------------- | ------------------------------------------------------------ |
| Util, mapper, schema          | Unit test (Vitest), tanpa mock                               |
| Hook dan komponen             | Testing Library, menguji **perilaku yang terlihat pengguna** |
| Panggilan jaringan            | Mock di batas jaringan (MSW), bukan mock service internal    |
| Guard dan permission          | Test: tanpa permission → tidak tampil / ditolak              |
| Alur kritis (login, approval) | E2E (Playwright), secukupnya                                 |

- Test berdampingan dengan berkasnya (`XxxPage.test.tsx`).
- Jangan menguji detail implementasi (state internal, jumlah render).

---

## 15. Aturan Import

- Gunakan **path alias** (`@/features/...`, `@/shared/...`) untuk lintas folder
  teratas, relative import (`./`) hanya di dalam folder yang sama.
- Impor langsung dari berkas **di dalam fitur yang sama**. Antar-fitur hanya lewat
  `index.ts` public API tipis (export seperlunya; bukan barrel berantai).
- Urutan import: (1) library eksternal, (2) alias `@/`, (3) relative, (4) style/aset.
- Gunakan `import type { ... }` untuk import yang hanya berupa tipe.

---

## 16. Checklist Menambah Fitur Baru

1. Buat `features/<feature-name>/` (kebab-case).
2. Pastikan tipe API sudah ter-generate (`shared/api/generated/`); tambah `types/xxx.ts` hanya untuk view model.
3. Tambah `services/xxxService.ts` (+ adapter hanya bila ada >1 implementasi).
4. Tambah `constants/xxxKeys.ts` dan `hooks/useXxx.ts` yang membungkus service.
5. Tambah `components/` dan `pages/XxxPage.tsx`; tombol aksi dokumen dari `allowedActions`.
6. Tambah `schemas/xxxSchema.ts` bila ada form.
7. Daftarkan route eksplisit dan lazy di `app/router/`, pasang guard permission.
8. Tambah permission di `shared/constants/permissions.ts` bila fitur dibatasi.
9. Tentukan tempat tiap state sesuai urutan keputusan (bagian 6); pastikan ada state loading/error/empty dan error boundary.
10. Tambah test (hook/komponen, guard) dan pastikan CI hijau (dependency-cruiser, lint, type check).

---

## 17. Blok Instruksi untuk AI Agent

Salin blok ini ke `AGENTS.md` atau `.github/copilot-instructions.md`:

```markdown
# Aturan project (wajib diikuti)

- Arsitektur: React feature-based. Kelompokkan kode per business feature di
  `src/features/<kebab-case>/`, JANGAN per role.
- Akses: permission + guards + `allowedActions` dari server. DILARANG menulis nama
  role (string) di kode frontend.
- Subfolder fitur: components, hooks, pages, services, stores, schemas, constants, types.
- Kode lintas fitur ada di `src/shared/`. `shared` tidak boleh import dari `features`.
- Antar-fitur hanya lewat `index.ts` public API fitur. Tidak ada circular import.
- Alur: Page/Component -> Hook -> Service -> httpClient/generated. UI tidak boleh
  memanggil HTTP langsung. Service tidak boleh import React/hook/komponen.
- Server state pakai TanStack Query + query key factory per fitur. Jangan salin data
  server ke useState/store.
- Urutan state: derived (hitung saat render) -> server (query) -> URL -> useState lokal
  -> Context -> store. Jangan lompat ke level lebih tinggi dari yang dibutuhkan.
- useEffect bukan untuk fetch, menurunkan state, atau merespons event. Wajib cleanup.
- Hook: satu tanggung jawab, useQuery/useMutation hanya di hook fitur (bukan di komponen),
  toast/navigasi lewat callback pemanggil, kembalikan objek bernama, tanpa aturan bisnis.
- Komponen: Page tipis; komponen > ~200 baris dipecah; props bertipe eksplisit;
  hindari prop drilling > 2 tingkat; shared/components tidak mengenal domain.
- Styling: satu pendekatan per project, warna dari design token, tanpa nilai literal.
- TypeScript: strict, tanpa any/enum/`as` sembarangan; status sebagai union + exhaustive switch.
- Tipe API di-generate ke `src/shared/api/generated/` (jangan edit, jangan tulis ulang
  manual). `features/*/types/` hanya view model UI.
- Jangan duplikasi aturan bisnis di klien; pakai `allowedActions` dari server.
- Route didaftarkan eksplisit dan lazy di `src/app/router/`.
- Env dan pemilihan implementasi hanya di `src/app/config/`.
- Setiap data punya state loading/error/empty. Tambah test untuk perilaku dan guard.

## Naming

- Component: PascalCase (UserTable.tsx); Page: XxxPage.tsx; Layout: XxxLayout.tsx
- Hook: useXxx.ts; Service: xxxService.ts; Schema: xxxSchema.ts; Query keys: xxxKeys.ts
- Type: camelCase file (user.ts), nama tipe PascalCase; Constants/Utility: camelCase
- Context/Provider: XxxContext.tsx / XxxProvider.tsx
- Folder: kebab-case. Hindari nama generik (utils.ts, helpers.ts, common.ts).
- Gunakan alias `@/` untuk import lintas folder dan `import type` untuk tipe.
```

---

## Lampiran A: Penyesuaian Project SMTB

Berlaku untuk `smtbk3-frontend`. Backend: Fastify + MongoDB, arsitektur Hexagonal ramping
(lihat `architecture.md`).

- **Tipe API:** schema TypeBox backend → OpenAPI → generate ke `src/shared/api/generated/`
  lewat script CI dan commit ke repo frontend. Folder `types/` per fitur tidak lagi
  menulis bentuk response secara manual.
- **Satu UI untuk semua role.** Matriks role → permission ada di
  `rbac-permission-matrix.md` (status draft). Frontend hanya mengenal permission
  `resource:action` dan `allowedActions`.
- **Uang = integer sen** dari API. Format hanya lewat `shared/utils/formatCurrency.ts`;
  jangan aritmetika desimal/float di klien.
- **Tahun fiskal dan periode tutup** berasal dari server/config, bukan literal di UI.
- **Aksi yang tidak ada `delete`** pada data keuangan: UI menampilkan `void` / `reverse` / `archive`
  sesuai `allowedActions`, bukan tombol hapus.
- **Dashboard keuangan, alat superadmin, impersonate:** halaman terpisah di rute yang
  dijaga permission, bukan aplikasi terpisah.

Aturan ESLint pelarang nama role (perbarui daftar sesuai matriks final):

```js
// eslint.config.js (potongan)
'no-restricted-syntax': ['error', {
  selector:
    "Literal[value=/^(superadmin|romoParoki|kabid|kasie|kawil|kaling|bendaharaRekan|bendaharaKeuskupan|ekonomKeuskupan)$/]",
  message: 'Jangan memakai nama role di frontend. Gunakan permission atau allowedActions.',
}],
```

---

## Lampiran B: Perubahan dari Versi Sebelumnya

| Area                    | Sebelumnya                      | Sekarang                                                                                                 |
| ----------------------- | ------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Penegakan arsitektur    | Aturan tertulis saja            | `dependency-cruiser` di CI (bagian 5)                                                                    |
| Antar-fitur             | Dilarang, tanpa mekanisme       | Hanya lewat `index.ts` public API, dijaga tool                                                           |
| Adapter/Repository      | Pola default di alur dependency | Hanya bila ada >1 implementasi                                                                           |
| State                   | Provider/Store di alur          | Urutan keputusan, server state via TanStack Query + query keys, aturan Context/store/URL/efek (bagian 6) |
| Hooks                   | Hanya penamaan                  | Jenis, batas tanggung jawab, efek samping via callback (bagian 7)                                        |
| Komponen & styling      | Hanya penamaan                  | Page tipis, ukuran, props, token desain (bagian 8)                                                       |
| TypeScript              | Tidak diatur                    | Konvensi `strict`, union terdiskriminasi, tanpa enum/cast sembarangan (bagian 9)                         |
| Tipe API                | Ditulis manual di `types/`      | Di-generate dari OpenAPI backend                                                                         |
| Permission              | Konstanta + guard               | Ditambah `/me/permissions`, `allowedActions`, larangan nama role                                         |
| Routing                 | Eksplisit                       | Eksplisit + lazy per fitur                                                                               |
| Error/loading           | Tidak diatur                    | Bagian 12 (boundary, status HTTP, tiga state)                                                            |
| Form                    | Hanya `schemas/`                | Bagian 11 (batas peran schema vs backend)                                                                |
| Testing, performa, a11y | Tidak diatur                    | Bagian 13 dan 14                                                                                         |
