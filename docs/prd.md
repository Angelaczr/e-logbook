# Product Requirement Document (PRD)

## 1. Ringkasan Produk

**Enterprise Daily Work Logbook** mencatat aktivitas kerja harian karyawan dalam beberapa item tugas per hari. Karyawan mengirim logbook untuk ditinjau atasan; atasan dapat menyetujui, meminta revisi, atau menolak. HR dan manajemen memantau kepatuhan pengisian, beban kerja, serta alokasi waktu berdasarkan proyek dan departemen.

**Stack:** Laravel 13, Inertia.js, Vue 3 Composition API, PostgreSQL, dan Tailwind CSS.

**Dokumen terkait:** alur pengguna per role ada di [user-flow.md](user-flow.md).

**Catatan role pada implementasi:** kode memakai `super_admin`, `manager`, `supervisor`, dan `employee` (`app/Enums/UserRole.php`). Analitik hanya untuk Manager dan Super Admin; peran HR belum menjadi role terpisah.

## 2. Goal dan Business Value

### Goals

- Menyediakan catatan aktivitas harian yang konsisten dan dapat ditelusuri.
- Memudahkan supervisor memeriksa aktivitas anggota tim dan memberi umpan balik.
- Memberikan HR dan manajemen gambaran kepatuhan serta alokasi waktu menurut departemen dan proyek.

### Business value

- Mengurangi pelaporan manual melalui spreadsheet dan komunikasi terpisah.
- Mempercepat proses peninjauan dengan antrean approval yang jelas.
- Menyediakan data untuk analisis kapasitas dan pelaporan.
- Menjaga rekam jejak perubahan dan keputusan approval.

## 3. Persona dan Matriks Hak Akses

| Kemampuan | Super Admin | Manager / Dept Head | Direct Supervisor | Employee |
|---|---:|---:|---:|---:|
| Mengelola pengguna dan struktur organisasi | Penuh | Departemennya* | Tidak | Tidak |
| Mengelola departemen dan proyek | Penuh | Lihat / kelola* | Lihat | Lihat |
| Membuat dan mengubah draft logbook sendiri | Ya | Ya | Ya | Ya |
| Mengirim logbook sendiri | Ya | Ya | Ya | Ya |
| Melihat logbook pribadi | Ya | Ya | Ya | Ya |
| Meninjau logbook bawahan langsung | Semua | Lingkupnya* | Ya | Tidak |
| Meminta revisi / menyetujui / menolak | Semua | Sesuai kewenangan* | Bawahan langsung | Tidak |
| Melihat metrik tim/departemen | Semua | Departemennya* | Tim langsung | Pribadi |
| Mengekspor laporan | Semua | Departemennya* | Tim langsung* | Pribadi* |
| Melihat audit trail | Semua | Lingkup departemen* | Tim langsung* | Logbook sendiri |

\* Lingkup Manager dan Supervisor harus diterapkan di backend berdasarkan struktur organisasi. Hak ekspor dan akses audit dapat disesuaikan dengan kebijakan HR.

**Prinsip akses:** role menentukan jenis kemampuan, sedangkan struktur departemen dan relasi atasan-bawahan menentukan data yang boleh diakses. Pemeriksaan akses harus dilakukan di server, bukan hanya dengan menyembunyikan elemen UI.

## 4. Core Features dan Functional Requirements

### 4.1 Struktur organisasi dan akses

- Pengguna memiliki role `super_admin`, `department_head`, `direct_supervisor`, atau `employee`.
- Pengguna dapat terhubung dengan departemen.
- Struktur organisasi mencatat atasan langsung dan, bila dibutuhkan, manager atau kepala departemen.
- Admin mengelola akun, status akun, departemen, dan relasi atasan.
- Pengguna non-admin hanya dapat melihat data sesuai lingkup kewenangannya.

### 4.2 Pencatatan logbook harian

- Karyawan membuat satu logbook per tanggal kerja dengan satu atau lebih item aktivitas.
- Item mencatat deskripsi tugas, jam mulai/selesai, durasi, status progres, serta proyek atau cost center opsional.
- Logbook dapat disimpan sebagai draft dan diedit sebelum dikirim.
- Validasi mencegah tanggal, durasi, atau rentang waktu yang tidak valid.
- Setelah dikirim, logbook masuk ke antrean review dan terkunci hingga mendapat permintaan revisi.

### 4.3 Approval dan revisi

- Supervisor melihat logbook `submitted` dari bawahan langsung.
- Supervisor memeriksa rincian logbook dan seluruh item aktivitas.
- Supervisor dapat menyetujui, meminta revisi, atau menolak.
- Approval menyimpan `approved_by` dan `approved_at`.
- Permintaan revisi dan penolakan mewajibkan catatan.
- Employee dapat memperbarui dan mengirim ulang logbook yang diminta untuk direvisi.
- Setiap transisi status dicatat pada audit trail.
- Sistem mencegah approval oleh pihak yang tidak berwenang.

### 4.4 Dashboard dan kepatuhan

- Employee melihat logbook pribadi berdasarkan periode dan status.
- Supervisor melihat antrean review dan ringkasan tim langsung.
- Manager/Dept Head melihat ringkasan departemen dalam lingkupnya.
- HR/Management yang berwenang melihat rasio pengiriman, logbook terlambat/belum dikirim/menunggu review, serta alokasi durasi menurut proyek, cost center, departemen, dan periode.
- Denominator *submission compliance rate* harus terdokumentasi, misalnya jumlah hari kerja terjadwal bagi karyawan aktif dalam periode.

### 4.5 Laporan dan ekspor

- Pengguna berwenang memfilter laporan menurut tanggal, departemen, karyawan, proyek, dan status.
- Ekspor bulanan tersedia dalam format Excel dan/atau PDF.
- Ekspor menerapkan batas akses yang sama dengan dashboard.
- Ekspor besar diproses melalui antrean agar tidak mengganggu permintaan interaktif.

## 5. Non-Functional Requirements

### Performance

- Halaman daftar menggunakan pagination dan filter server-side.
- Query dashboard mengagregasi data di database dan menggunakan indeks yang sesuai.
- Target awal: respons p95 di bawah 2 detik untuk halaman operasional pada beban normal; laporan besar dapat diproses asynchronous.
- Hindari N+1 query dengan eager loading dan pantau query lambat.

### Security dan audit trail

- Gunakan autentikasi berbasis session dan perlindungan CSRF.
- Validasi input melalui Form Request dan otorisasi melalui Policy/Gate.
- Batasi ekspor dan data sensitif sesuai lingkup pengguna.
- Hash password menggunakan mekanisme Laravel.
- Catat aktor, waktu, status sebelum/sesudah, dan komentar setiap keputusan atau revisi.
- Terapkan HTTPS, rate limiting untuk endpoint sensitif, dan kebijakan retensi data.
- Validasi tipe/ukuran lampiran dan simpan file internal di storage privat.

### Scalability dan reliability

- Pisahkan proses interaktif dari ekspor dan notifikasi melalui queue worker.
- Gunakan indeks pada tanggal, status, user, supervisor, department, dan project sesuai pola query.
- Rancang laporan untuk agregasi data besar; cache ringkasan yang dapat dihitung ulang.
- Simpan waktu secara konsisten dalam UTC dan tampilkan sesuai zona waktu organisasi.
- Jadwalkan backup PostgreSQL dan uji pemulihan secara berkala.
