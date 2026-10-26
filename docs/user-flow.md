# Dokumentasi User Flow

Dokumen ini menggambarkan alur pengguna per role berdasarkan PRD ([prd.md](prd.md)), use case ([usecase.md](usecase.md)), serta route, policy, dan service yang ada di kode. Diagram memakai Mermaid.

## 1. Peta Halaman dan Akses

| Halaman | Route | Akses |
|---|---|---|
| Login | `/login` | Tamu (rate limit 5 percobaan/menit) |
| Daftar logbook | `/logbooks` | Semua role (data sendiri) |
| Form logbook (buat/ubah) | `/logbooks/create`, `/logbooks/{id}/edit` | Pemilik, status `draft` atau `revision_requested` |
| Detail logbook | `/logbooks/{id}` | Pemilik, atasan langsung, manager di atas hierarki, super admin |
| Antrean review | `/reviews` | Supervisor, Manager, Super Admin |
| Detail review | `/reviews/{id}` | Reviewer yang berwenang, status `submitted` |
| Analitik | `/analytics` | Manager, Super Admin |
| Notifikasi | `/notifications/*` | Semua role yang login |

Pembatasan akses diterapkan di server (middleware `role`, Gate, dan `LogbookPolicy`), bukan hanya dengan menyembunyikan menu.

## 2. Status Logbook

```mermaid
stateDiagram-v2
    [*] --> Draft: Simpan draft
    Draft --> Submitted: Karyawan mengajukan
    Submitted --> Approved: Reviewer menyetujui
    Submitted --> RevisionRequested: Reviewer minta revisi (catatan wajib)
    Submitted --> Rejected: Reviewer menolak (alasan wajib)
    RevisionRequested --> Submitted: Karyawan memperbaiki & mengajukan ulang
    Approved --> [*]
    Rejected --> [*]
```

| Status | Label UI | Bisa diedit pemilik | Final |
|---|---|:-:|:-:|
| `draft` | Draft | Ya | Tidak |
| `submitted` | Diajukan | Tidak (terkunci) | Tidak |
| `revision_requested` | Perlu Revisi | Ya | Tidak |
| `approved` | Disetujui | Tidak | Ya |
| `rejected` | Ditolak | Tidak | Ya |

Setiap transisi dicatat di riwayat status (aktor, status lama/baru, catatan, waktu).

## 3. Alur Umum: Login

```mermaid
flowchart TD
    A[Buka aplikasi /] --> B{Sudah login?}
    B -- Ya --> H[Halaman beranda sesuai role]
    B -- Tidak --> C[Halaman Login]
    C --> D[Isi email & password]
    D --> E{Kredensial valid<br/>dan akun aktif?}
    E -- Tidak --> F[Tampilkan error]
    F --> G{Melebihi 5 percobaan/menit?}
    G -- Ya --> G1[Diblokir sementara]
    G -- Tidak --> C
    E -- Ya --> H
    H --> I[Logout kapan saja]
```

## 4. Alur Karyawan: Membuat dan Mengajukan Logbook

```mermaid
flowchart TD
    A[Daftar Logbook] --> B[Klik Buat Logbook]
    B --> C[Pilih tanggal kerja & shift]
    C --> D{Logbook tanggal/shift<br/>itu sudah ada?}
    D -- Ya --> D1[Buka logbook yang ada]
    D -- Tidak --> E[Isi item aktivitas]
    D1 --> E
    E --> E1[Deskripsi, jam mulai/selesai,<br/>proyek, progres, output]
    E1 --> F[Simpan Draft]
    F --> G{Validasi lolos?}
    G -- Tidak --> G1[Error di field terkait]
    G1 --> E
    G -- Ya --> H[Status draft tersimpan]
    H --> I{Ajukan sekarang?}
    I -- Belum --> A
    I -- Ya --> J{Minimal 1 item &<br/>validasi waktu lolos?}
    J -- Tidak --> G1
    J -- Ya --> K[Status submitted, terkunci]
    K --> L[Notifikasi ke reviewer]
    L --> M[Menunggu keputusan]
```

Aturan validasi yang berlaku saat simpan/ajukan:

- Tanggal kerja tidak boleh di masa depan.
- Satu logbook per kombinasi tanggal dan shift per karyawan.
- Jam selesai harus berbeda dari jam mulai.
- Rentang jam antar item tidak boleh tumpang tindih.
- Total durasi dalam satu hari maksimal 24 jam.
- Pengajuan membutuhkan minimal satu item.

## 5. Alur Karyawan: Menindaklanjuti Hasil Review

```mermaid
flowchart TD
    A[Notifikasi keputusan diterima] --> B[Buka detail logbook]
    B --> C{Status?}
    C -- Disetujui --> D[Selesai, hanya dapat dilihat]
    C -- Ditolak --> E[Baca alasan, selesai]
    C -- Perlu Revisi --> F[Baca catatan revisi]
    F --> G[Edit item sesuai catatan]
    G --> H[Ajukan ulang]
    H --> I[Status submitted lagi, masuk antrean reviewer]
```

## 6. Alur Reviewer (Supervisor / Manager / Super Admin)

Cakupan data: Supervisor hanya bawahan langsung; Manager hanya karyawan di bawah hierarkinya; Super Admin semua. Reviewer tidak dapat meninjau logbook miliknya sendiri.

```mermaid
flowchart TD
    A[Notifikasi / menu Review] --> B[Antrean review: logbook submitted]
    B --> C[Filter karyawan / tanggal]
    C --> D{Mode?}
    D -- Massal --> E[Pilih beberapa logbook]
    E --> F[Bulk approve]
    D -- Satuan --> G[Buka detail & periksa item]
    G --> H{Keputusan}
    H -- Setujui --> I[approved + approved_by/at]
    H -- Minta revisi --> J[Isi catatan wajib]
    H -- Tolak --> K[Isi alasan wajib]
    J --> L{Catatan terisi?}
    K --> L
    L -- Tidak --> M[Validasi menolak]
    M --> G
    L -- Ya --> N[Status revision_requested / rejected]
    I --> O[Catat riwayat status]
    N --> O
    F --> O
    O --> P[Notifikasi ke karyawan]
```

Kasus tepi:

- Status sudah berubah sejak halaman dibuka: aksi ditolak karena transisi tidak valid; reviewer memuat ulang.
- Reviewer tidak berwenang: server menolak (403) tanpa mengubah data.
- Aksi review dibatasi rate limit; bulk approve punya batas lebih ketat.

## 7. Alur Manager / Super Admin: Analitik dan Ekspor

```mermaid
flowchart TD
    A[Menu Analitik] --> B[Pilih periode]
    B --> C[Filter departemen, karyawan, proyek, status]
    C --> D[Dashboard: kepatuhan pengiriman,<br/>status review, alokasi durasi]
    D --> E{Ada data?}
    E -- Tidak --> F[Tampilan kosong]
    E -- Ya --> G{Ekspor?}
    G -- Tidak --> H[Selesai]
    G -- Ya --> I[Pilih Excel / PDF]
    I --> J{Ukuran besar?}
    J -- Tidak --> K[File langsung diunduh]
    J -- Ya --> L[Diproses queue, notifikasi saat siap]
    L --> K
```

Catatan: Gate `export-reports` sudah ada, tetapi route ekspor belum terpasang; alur ekspor di atas adalah target PRD.

## 8. Alur Notifikasi

| Pemicu | Penerima |
|---|---|
| Logbook diajukan | Reviewer berwenang |
| Disetujui / diminta revisi / ditolak | Pemilik logbook |

Pengguna dapat menandai satu notifikasi atau semuanya sebagai sudah dibaca.

## 9. Ringkasan Perjalanan per Role

| Role | Perjalanan utama |
|---|---|
| Karyawan | Login → buat/isi logbook harian → simpan draft → ajukan → pantau status → revisi bila diminta |
| Supervisor | Login → antrean review bawahan langsung → periksa → setujui/revisi/tolak; tetap mengisi logbook sendiri |
| Manager | Seperti Supervisor untuk cakupan hierarkinya, ditambah analitik departemen dan ekspor |
| Super Admin | Akses review dan analitik penuh; pengelolaan pengguna/organisasi (belum ada UI) |
