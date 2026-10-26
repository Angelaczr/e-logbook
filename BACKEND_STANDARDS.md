# Standar Backend: Fastify + RBAC + Hexagonal Architecture

Catatan standar untuk semua project backend saya. Pasangan dari `FRONTEND_STANDARDS.md`
(prinsip yang sama: **kelompokkan per business feature, bukan per role**).

Stack acuan: Node.js LTS, TypeScript (strict), Fastify v5, Pino, JSON Schema/TypeBox
atau Zod untuk validasi, ORM/query builder bebas (di balik port).

---

## 1. Prinsip Utama

1. **Hexagonal (Ports & Adapters):** logika bisnis tidak tahu Fastify, database, atau
   library eksternal. Semua itu adalah adapter di tepi.
2. **Per feature (modul bisnis):** satu folder per fitur, bukan per layer global
   (`controllers/`, `models/`) dan bukan per role.
3. **Fastify hanyalah adapter HTTP.** Route = pintu masuk tipis, bukan tempat logika.
4. **Schema-first:** setiap route punya schema request dan response.
5. **RBAC deny-by-default:** route tanpa deklarasi permission tidak boleh diakses.
6. **Plugin encapsulation:** manfaatkan sistem plugin Fastify, jangan global state.

---

## 2. Struktur Folder

Versi **lean**: satu berkas per peran, peran ditentukan oleh **akhiran nama berkas**,
bukan oleh folder. Folder `domain/ application/ infrastructure/` per modul tidak dipakai
lagi (riwayat: versi tiga-lapis ada di git/cadangan; alasannya: satu fitur membengkak
menjadi 15-20 berkas dan banyak yang hanya berisi 5-10 baris).

```text
src/
  app.ts                         Rakit aplikasi (buildApp) - tanpa listen
  server.ts                      Entry point: load config, listen, graceful shutdown
  config/
    env.ts                       Validasi & parsing environment variable
  plugins/                       Plugin teknis SHARED (pakai fastify-plugin)
    authPlugin.ts                JWT/session + decorator request.auth
    rbacPlugin.ts                Decorator authorize()/requirePermission()
    errorHandlerPlugin.ts        setErrorHandler + mapping error domain -> HTTP
    securityPlugin.ts            helmet, cors, rate-limit
    loggerPlugin.ts              konfigurasi Pino, request id
  modules/
    <feature-name>/              kebab-case: proposal, user-management, auth
      proposal.domain.ts         Entity, value object, status, policy, error domain
      proposal.usecases.ts       Port keluar (interface) + use case
      proposal.mongo.ts          Adapter keluar: repository + record + mapper
      proposal.routes.ts         Adapter masuk Fastify: schema + route + composition root
      proposal.api.ts            Kontrak publik untuk modul lain (hanya bila ada konsumen)
      proposal.test.ts           Test (domain murni, use case, HTTP inject)
  shared/
    kernel/                      Result, Money, base error, TxContext, port bersama
    infra/                       Adapter bersama (mis. MongoCrudRepository, transaction runner)
    types/
  test/
    helpers/                     buildTestApp, MongoDB in-memory replica set
```

Ukuran fitur menentukan jumlah berkas (jangan membuat berkas kosong):

| Jenis fitur | Berkas |
|---|---|
| CRUD tipis (master data, konten) | `x.mongo.ts` + `x.routes.ts`. Route boleh memanggil port repository langsung; tanpa `domain`/`usecases` |
| Domain kaya (aturan, status, uang) | `x.domain.ts`, `x.usecases.ts`, `x.mongo.ts`, `x.routes.ts` (+ `x.api.ts` bila dipakai modul lain) |

**Cara tumbuh (scalable):**
- Satu peran boleh punya **beberapa berkas** dengan akhiran yang sama:
  `postDisbursement.usecases.ts`, `period.domain.ts`, `kolektes.mongo.ts`. Aturan lapisan
  mengikuti akhiran, jadi menambah berkas tidak mengubah aturan.
- Berkas melewati sekitar 300 baris atau memuat lebih dari satu aggregate: pecah per
  aggregate atau per use case; boleh dikelompokkan ke subfolder (aturan tetap berdasar akhiran).
- Fitur CRUD tipis yang makin kompleks: tambahkan `x.domain.ts` dan `x.usecases.ts`;
  berkas lain tidak berubah.
- Mengganti database: tulis `x.postgres.ts` dengan port yang sama; `usecases` dan `domain`
  tidak berubah.

---

## 3. Dependency Rule (Hexagonal)

```text
*.routes.ts, *.mongo.ts   ->   *.usecases.ts   ->   *.domain.ts
      (adapter)                 (use case + port)        (aturan)
```

Aturan impor, ditentukan oleh akhiran berkas (dijaga dependency-cruiser, build gagal bila dilanggar):

| Berkas | Boleh mengimpor | Dilarang |
|---|---|---|
| `*.domain.ts` | `shared/kernel`, sesama `*.domain.ts` modul sendiri | fastify, driver DB, `process.env`, `axios`, `fs`, `new Date()` |
| `*.usecases.ts` | `*.domain.ts` modul sendiri, `shared/kernel` | `fastify`, driver DB, `*.mongo.ts`, `*.routes.ts`, modul lain |
| `*.mongo.ts` | `*.usecases.ts` (hanya tipe port), `*.domain.ts`, `shared/kernel`, `shared/infra`, driver DB | `fastify`, `*.routes.ts` |
| `*.routes.ts` | `*.usecases.ts`, `*.mongo.ts` (hanya untuk wiring), `*.api.ts` modul lain (hanya untuk wiring), fastify, TypeBox | driver DB langsung, query di handler |
| `*.api.ts` | tipe dari `*.usecases.ts` modul sendiri | implementasi adapter |

- Dependency selalu menunjuk ke dalam. Tidak pernah sebaliknya.
- **Modul tidak mengimpor internal modul lain.** Komunikasi lewat `*.api.ts` modul lain.
  Port ke modul lain (`xxxGateway`) dideklarasikan di `*.usecases.ts` pemanggil dan
  diimplementasikan di fungsi wiring pada `*.routes.ts`, yang memanggil `*.api.ts` modul pemilik.
- **Composition root** = plugin di `*.routes.ts` per fitur dan `app.ts`. Hanya tempat ini
  yang boleh tahu semua implementasi konkret.

Aliran request:

```text
HTTP Request
 -> Fastify hook (auth, authorize)
 -> Route handler (validasi lewat schema, panggil use case)
 -> Use case (orkestrasi, aturan aplikasi)
 -> Domain (aturan bisnis) + Port keluar (repository/gateway)
 -> Adapter (*.mongo.ts, API eksternal)
 <- Hasil -> schema response -> Response
```

---

## 4. Naming Convention

| Jenis | Convention | Contoh |
|---|---|---|
| Berkas domain (entity, status, policy, error) | `<nama>.domain.ts` | `proposal.domain.ts` |
| Berkas use case + port | `<nama>.usecases.ts` | `proposal.usecases.ts` |
| Adapter keluar | `<nama>.<teknologi>.ts` | `proposal.mongo.ts`, `proposal.postgres.ts` |
| Adapter masuk + wiring | `<nama>.routes.ts` | `proposal.routes.ts` |
| Kontrak publik antar-modul | `<nama>.api.ts` | `ledger.api.ts` |
| Entity / Aggregate (class) | PascalCase | `class Proposal` |
| Use case (fungsi pabrik) | `make` + KataKerjaBisnis | `makeSubmitProposal` |
| Port (interface) | PascalCase tanpa `I` | `ProposalRepository`, `LedgerGateway` |
| Shared plugin | camelCase + `Plugin` | `authPlugin.ts` |
| Config / Constants | camelCase | `env.ts`, `permissions.ts` |
| Test | nama berkas + `.test` | `proposal.test.ts` |

Aturan tambahan:

- Folder: `kebab-case`. Akhiran berkas (`.domain`, `.usecases`, `.mongo`, `.routes`, `.api`) menentukan lapisan; jangan membuat akhiran baru tanpa memperbarui aturan CI.
- Class/type/interface: `PascalCase`. Fungsi/variabel: `camelCase`. Konstanta tetap: `UPPER_SNAKE_CASE`.
- Jangan pakai awalan `I` pada interface (`ProposalRepository`, bukan `IProposalRepository`).
- Hindari nama generik: `utils.ts`, `helpers.ts`, `common.ts`, `manager`, `handler` tanpa konteks.
- Endpoint REST: kata benda jamak, kebab-case: `/proposals`, `/proposals/:id/approve`.

---

## 5. Standar Fastify

### 5.1 Plugin & encapsulation

- **Semuanya plugin.** Route, utilitas, dan integrasi didaftarkan lewat `register()`.
- **Plugin teknis shared** (db, auth, rbac, error handler) dibungkus `fastify-plugin`
  karena memang perlu terlihat oleh scope lain.
- **Route plugin TIDAK memakai `fastify-plugin`**, supaya hook dan decorator-nya
  tetap terisolasi di scope fitur itu.
- Urutan register: config -> logger/security -> auth -> rbac -> error handler -> modul fitur.
- `app.ts` mengekspor `buildApp(deps)` tanpa `listen()`, agar mudah diuji dengan `inject()`.

```ts
// modules/proposal/proposal.routes.ts (composition root fitur + route)
export const proposalRoutes: FastifyPluginAsync<{ db: Database; ledgerApi: LedgerApi }> = async (app, opts) => {
  const repo = new MongoProposalRepository(opts.db);                       // proposal.mongo.ts
  const ledger: LedgerGateway = { post: (c, tx) => opts.ledgerApi.post(c, tx) };  // gateway = wiring ke *.api.ts
  const submit = makeSubmitProposal({ repo, ledger });                     // proposal.usecases.ts

  app.post("/:id/submit", { schema: submitSchema, onRequest: [app.authenticate],
    preHandler: [app.requirePermission(PERMISSIONS.proposal.create)] },
    async (req, reply) => reply.send(await submit.execute({ id: req.params.id, actor: req.auth.actor, scope: req.auth.scope })));
};
// app.ts: await app.register(proposalRoutes, { prefix: "/proposals", db, ledgerApi })
```

### 5.2 Route tipis, schema-first

- Handler hanya: ambil input -> panggil use case -> map output. **Tanpa logika bisnis, tanpa query DB.**
- Wajib `schema` untuk `body`, `params`, `querystring`, dan `response`. Schema TypeBox juga menjadi tipe dan dokumentasi OpenAPI, sehingga tidak perlu berkas DTO/mapper HTTP terpisah.
- Schema response mencegah kebocoran field sensitif (`passwordHash`, dsb.).
- Gunakan type provider (TypeBox/Zod) agar tipe handler sinkron dengan schema.

```ts
app.post(
  "/",
  {
    schema: createProposalSchema,
    onRequest: [app.authenticate],
    preHandler: [app.requirePermission(PERMISSIONS.proposal.create)],
  },
  async (request, reply) => {
    const result = await createProposal.execute({
      ...request.body,
      actorId: request.auth.userId,
    });
    return reply.code(201).send(result);   // schema response menyaring field
  },
);
```

### 5.3 Hooks lifecycle

| Hook | Dipakai untuk |
|---|---|
| `onRequest` | Autentikasi (verifikasi token), request id |
| `preValidation` | Normalisasi input sebelum validasi (jarang) |
| `preHandler` | **Otorisasi (RBAC)**, pengecekan prasyarat |
| `onSend` / `preSerialization` | Transformasi respons global (hindari berlebihan) |
| `onResponse` | Metrik / audit ringan |
| `setErrorHandler` | Satu tempat untuk memetakan error ke HTTP |

### 5.4 Error handling

- Domain/use case melempar **error domain** (`ProposalNotFoundError`, `ForbiddenError`),
  bukan `reply.code(...)`.
- `errorHandlerPlugin` memetakan error domain -> status HTTP + format respons seragam.
- Format error seragam, contoh:

```json
{ "error": { "code": "PROPOSAL_NOT_FOUND", "message": "Proposal tidak ditemukan", "requestId": "..." } }
```

- Jangan bocorkan stack trace atau pesan internal ke klien di production.
- Error tak terduga: log level `error` dengan request id, balas `500` generik.

### 5.5 Konfigurasi & keamanan

- Semua env divalidasi saat boot (`config/env.ts`); aplikasi **gagal start** jika tidak valid.
- Jangan akses `process.env` di luar `config/`.
- Aktifkan: `@fastify/helmet`, `@fastify/cors` (whitelist origin), `@fastify/rate-limit`
  (lebih ketat untuk login).
- Atur `bodyLimit`, timeout, dan `trustProxy` sesuai deployment.
- Logging: Pino bawaan Fastify, JSON terstruktur, redaksi field sensitif
  (`authorization`, `password`, `token`).
- **Graceful shutdown:** tangani `SIGINT`/`SIGTERM` dengan `app.close()`.
- Sediakan endpoint `/health` (liveness) dan `/ready` (readiness).

### 5.6 Async & performa

- Handler `async` yang **return** nilai atau `reply`, jangan campur callback dan `await`.
- Jangan blokir event loop (hindari kerja CPU berat sinkron di handler).
- Gunakan serialisasi schema response (lebih cepat dan aman).

---

## 6. RBAC (Role-Based Access Control)

### 6.1 Model

```text
User --(many)--> Role --(many)--> Permission
```

- **Permission** adalah satuan otorisasi sebenarnya. Format: `resource:action`,
  contoh `proposal:create`, `proposal:approve`, `user:read`.
- **Role** hanyalah kumpulan permission (`admin`, `reviewer`, `staff`).
- **Kode tidak boleh mengecek role** (`if (role === "admin")`). Kode hanya mengecek
  **permission**. Role bisa berubah tanpa mengubah kode.
- Permission didefinisikan sebagai konstanta tunggal, bukan string tersebar.

```ts
// modules/auth/domain/permissions.ts
export const PERMISSIONS = {
  proposal: {
    read: "proposal:read",
    create: "proposal:create",
    approve: "proposal:approve",
  },
  user: { read: "user:read", manage: "user:manage" },
} as const;
```

### 6.2 Alur otorisasi

1. **Authentication (401):** `onRequest` memverifikasi token, mengisi `request.auth`
   (`userId`, `roles`, opsional `tenantId`).
2. **Permission check (403):** `preHandler` memanggil `requirePermission(...)`.
   Permission di-resolve dari role di sisi server (cache dengan TTL/invalidasi), jangan
   percaya klaim permission yang dikirim klien.
3. **Resource check (403/404):** aturan kepemilikan atau konteks (misal "hanya pembuat
   yang boleh mengedit draft") dicek di **use case/domain**, karena butuh data.

```ts
// plugins/rbacPlugin.ts
app.decorate("requirePermission", (...required: Permission[]) =>
  async (request: FastifyRequest) => {
    const granted = await permissionResolver.resolve(request.auth.roles);
    const ok = required.every((p) => granted.has(p));
    if (!ok) throw new ForbiddenError(required);
  },
);
```

### 6.3 Aturan RBAC

- **Deny by default.** Route non-publik tanpa `requirePermission` ditolak, atau gagal
  saat boot lewat guard yang memeriksa semua route (`onRoute` hook).
- Route publik harus eksplisit (`config: { public: true }`).
- **401** = belum login/token tidak valid. **403** = login tapi tidak berhak.
- Gunakan `404` alih-alih `403` bila keberadaan resource sendiri sensitif.
- Role `admin` tidak "bypass" diam-diam; berikan permission lengkap lewat role.
- Seed role/permission lewat migration, bukan hardcode di handler.
- Catat **audit log** untuk aksi sensitif (siapa, aksi, resource, waktu, hasil).
- Multi-tenant: `tenantId` wajib ikut di setiap query repository, bukan filter opsional.
- Port permission (`PermissionResolver`) berada di `application`; implementasinya
  (DB/cache) di `infrastructure`.
- Uji RBAC: tiap route punya test untuk 401, 403, dan sukses.

---

## 7. Tips Hexagonal

1. **Mulai dari use case, bukan dari tabel.** Tulis dulu input, output, dan port yang
   dibutuhkan; baru pikirkan adapter.
2. **Port didefinisikan oleh kebutuhan application**, bukan oleh bentuk database.
   `ProposalRepository.save(proposal)` lebih baik daripada `insertRow(...)`.
3. **Pisahkan model hanya bila bentuknya berbeda.** Modul domain kaya: entity domain dan
   record persistence dipisah, dengan `toEntity`/`toRecord` di dalam `*.mongo.ts`. Modul CRUD
   tipis: record = entity. Response HTTP selalu lewat schema response; jangan mengembalikan
   dokumen DB mentah (`_id`, field internal) ke klien.
4. **Satu use case = satu fungsi pabrik dengan satu method `execute`**, ditulis dalam
   `*.usecases.ts` (dependency lewat parameter pabrik). Nama berupa kata kerja bisnis:
   `makeApproveProposal`, bukan `ProposalService.doStuff`.
5. **Jangan buat port untuk semuanya.** Port dibuat untuk hal yang berubah atau perlu
   di-mock: DB, API eksternal, email, clock, ID generator. Utilitas murni tidak perlu port.
6. **Inject dependency lewat parameter pabrik/constructor.** Tanpa singleton global, tanpa
   `new` adapter di dalam use case.
7. **Aturan bisnis di domain**, bukan di use case atau route. Use case hanya mengorkestrasi.
   Contoh: `proposal.approve(actor)` yang memvalidasi status, bukan `if` di handler.
8. **Transaksi** dikelola oleh application melalui port (`UnitOfWork`/`TransactionRunner`),
   bukan oleh route.
9. **Tanpa adapter in-memory wajib per repository.** Tes use case memakai MongoDB
   in-memory mode replica set (transaksi tetap teruji) atau fake kecil di dalam berkas test
   bila perlu mem-mock port.
10. **Pilih adapter di composition root / `config`**, bukan lewat `if (env === ...)`
    tersebar di kode.
11. **Event domain** untuk komunikasi antar modul agar modul tetap longgar
    (`ProposalApproved`), alih-alih saling memanggil repository.
12. **Jangan over-engineer.** CRUD sederhana = dua berkas (`*.mongo.ts`, `*.routes.ts`) dan
    repository dari base class generik (`shared/infra`). Pertahankan arah dependency,
    sederhanakan sisanya. Aturan yang tidak boleh dihemat: transaksi pada tulis keuangan,
    `scope` wajib pada setiap baca, tanpa `delete` data keuangan, tes domain.
13. **Uji sesuai lapisan:**
    - Domain: unit test murni, tanpa mock.
    - Use case: DB in-memory (replica set) atau fake port inline di test.
    - Adapter persistence: integration test dengan DB nyata (container / replica set in-memory).
    - HTTP: `app.inject()` end-to-end, termasuk RBAC.

---

## 8. Clean Code Rules

### Penamaan
- Nama harus mengungkapkan maksud: `expiredProposals`, bukan `list2`.
- Fungsi = kata kerja (`approveProposal`), variabel/boolean = kata benda atau
  pertanyaan (`isExpired`, `hasPermission`).
- Hindari singkatan membingungkan (`prop`, `tmp`, `mgr`, `data`, `info`).
- Satu konsep, satu kata (jangan campur `fetch`/`get`/`retrieve` untuk hal sama).

### Fungsi
- Kecil dan melakukan **satu hal**. Target di bawah ~20 baris.
- Maksimal 3 parameter; lebih dari itu gunakan objek parameter bernama.
- Tanpa flag boolean sebagai parameter (pecah jadi dua fungsi).
- Hindari side effect tersembunyi; fungsi yang mengubah state harus tampak dari namanya.
- **Early return / guard clause** daripada `if` bersarang (maks ~2 level indent).
- Pisahkan **command** (mengubah) dan **query** (membaca).

### Struktur & desain
- **SOLID**, terutama SRP dan DIP (bergantung pada abstraksi/port).
- **DRY secukupnya:** hapus duplikasi pengetahuan, jangan paksa abstraksi dini
  (aturan tiga: abstraksi setelah muncul ketiga kalinya).
- **YAGNI:** jangan tambah fitur/abstraksi yang belum dibutuhkan.
- Utamakan **komposisi** daripada pewarisan.
- Utamakan **immutability** (`readonly`, `const`); hindari mutasi parameter.
- Hindari **magic number/string**; beri nama konstanta.
- Hindari **primitive obsession**: bungkus konsep domain dalam value object (`Email`, `Money`).

### TypeScript
- `strict: true`. Dilarang `any`; gunakan `unknown` lalu persempit tipe.
- Hindari type assertion (`as`) kecuali di batas sistem dengan komentar alasan.
- Gunakan union literal / enum untuk status, bukan string bebas.
- Return type fungsi publik dieksplisitkan.
- Hindari `null` dan `undefined` bercampur; pilih satu konvensi per proyek.

### Error & komentar
- Jangan telan error (`catch {}` kosong). Tangani atau lempar ulang dengan konteks.
- Gunakan error bertipe, bukan `throw new Error("string")` mentah di domain.
- Validasi di **batas sistem** (HTTP, env, pesan eksternal); di dalam domain percayai tipe.
- Komentar menjelaskan **mengapa**, bukan **apa**. Kode yang butuh komentar "apa"
  biasanya perlu di-rename atau dipecah.
- Hapus kode mati dan `console.log`. Gunakan logger.
- Tidak ada TODO tanpa konteks/tiket.

### Proses
- Satu commit = satu perubahan logis. Format Conventional Commits (`feat:`, `fix:`, `refactor:`).
- Lint + format (ESLint + Prettier) dan type-check wajib lolos sebelum merge.
- Test untuk setiap use case dan setiap aturan domain; bug fix dimulai dari test yang gagal.
- Boy Scout Rule: tinggalkan kode sedikit lebih bersih dari saat ditemukan.

---

## 9. Checklist Menambah Fitur Baru

1. Buat `modules/<feature-name>/` (kebab-case). Pilih jenis: CRUD tipis atau domain kaya (bagian 2).
2. Domain kaya: tulis `x.domain.ts` (entity, status, error) dan test domain murni.
3. Tulis `x.usecases.ts`: port keluar (`XRepository`, `XGateway`) + use case.
4. Tambah **permission** baru di `permissions.ts` dan seed ke role terkait.
5. Tulis `x.mongo.ts` (repository, record, mapper di dalam) + integration test.
6. Tulis `x.routes.ts`: schema + route (tipis, `onRequest` auth, `preHandler` permission) + wiring.
7. Daftarkan di `app.ts`. Tambah `x.api.ts` hanya bila modul lain membutuhkannya.
8. Tambah test HTTP: sukses, 401, 403, validasi gagal.
9. Jalankan dependency-cruiser: tidak ada impor melanggar aturan akhiran berkas dan tidak ada impor silang antar modul.

---

## 10. Blok Instruksi untuk AI Agent

Salin ke `AGENTS.md` atau `.github/copilot-instructions.md`:

```markdown
# Aturan backend (wajib diikuti)

- Stack: Node.js + TypeScript strict + Fastify v5. Arsitektur Hexagonal LEAN per feature
  di `src/modules/<kebab-case>/`. Lapisan ditentukan AKHIRAN berkas, bukan folder:
  `x.domain.ts`, `x.usecases.ts` (port + use case), `x.<tech>.ts` (adapter DB, mis. `x.mongo.ts`),
  `x.routes.ts` (schema + route + wiring), `x.api.ts` (kontrak publik antar-modul).
- CRUD tipis cukup `x.mongo.ts` + `x.routes.ts`. Jangan membuat berkas kosong atau
  folder domain/application/infrastructure.
- Dependency: routes/mongo -> usecases -> domain. `*.domain.ts` hanya impor shared/kernel.
  `*.usecases.ts` DILARANG impor fastify, driver DB, *.mongo.ts, *.routes.ts, modul lain.
  `*.mongo.ts` DILARANG impor fastify. `*.routes.ts` DILARANG impor driver DB.
- Antar-modul hanya lewat `*.api.ts`; port ke modul lain (`XxxGateway`) dideklarasikan di
  `*.usecases.ts` dan diwiring di `*.routes.ts`.
- Route Fastify tipis: schema (TypeBox) untuk body/params/querystring/response, panggil use case.
  Tanpa logika bisnis dan tanpa query DB di handler.
- Plugin shared pakai fastify-plugin; plugin route fitur TIDAK pakai fastify-plugin.
- Auth di `onRequest`, otorisasi RBAC di `preHandler`. Cek PERMISSION, bukan role.
  Permission format `resource:action`, konstanta tunggal. Deny by default (401/403).
- Cek kepemilikan resource di use case/domain. Setiap baca repository menerima `scope`.
- Operasi multi-koleksi keuangan: satu `TransactionRunner.run`, `tx` diteruskan ke semua
  tulis; event dikirim setelah commit. Data keuangan tidak dihapus (void/reverse/arsip).
- Error domain bertipe; dipetakan ke HTTP hanya di errorHandlerPlugin.
- Env divalidasi di `config/env.ts`; jangan akses process.env di tempat lain.
- Dilarang `any`. Use case = fungsi pabrik `makeXxx(deps)` dengan satu `execute`.
- Setiap use case dan aturan domain wajib ada test; RBAC diuji (200/401/403).

## Naming
- Berkas: `proposal.domain.ts`, `proposal.usecases.ts`, `proposal.mongo.ts`, `proposal.routes.ts`,
  `proposal.api.ts`, `proposal.test.ts`. Class/type PascalCase tanpa awalan `I`.
- Folder kebab-case. Hindari utils/helpers/common generik.

## Clean code
- Fungsi kecil, satu tanggung jawab, maks 3 parameter, guard clause, tanpa magic value.
- Komentar menjelaskan "mengapa". Tanpa console.log, tanpa catch kosong.
```

---

## Referensi

- Dokumentasi Fastify: Plugins Guide, Encapsulation, Hooks, Validation and Serialization,
  Testing (`fastify.dev/docs/latest`).
- Pola umum dari komunitas Fastify: schema-first, plugin encapsulation, `@fastify/autoload`
  (opsional untuk memuat plugin), Pino logging, dan graceful shutdown.
- Hexagonal Architecture (Alistair Cockburn), Clean Code (Robert C. Martin).
