# Presentasi UTS — Sistem Informasi Kesehatan & SPK Triase Santri
### Poskestren Al-Bahjah Bogor

---

---

# SLIDE 1 — JUDUL PROYEK

## Sistem Informasi Kesehatan & SPK Triase Santri
### Berbasis Metode Simple Additive Weighting (SAW)

**Lembaga:** Pos Kesehatan Pesantren (Poskestren) Al-Bahjah Bogor
**Di bawah naungan:** Lembaga Pengembangan Dakwah (LPD) Al-Bahjah — Buya Yahya
**Platform:** PHP 8.0 + MySQL (XAMPP)
**Status Proyek:** ≥ 75% Selesai

---

---

# SLIDE 2 — LATAR BELAKANG

## Mengapa Sistem Ini Dibutuhkan?

Pondok Pesantren Al-Bahjah Bogor memiliki ratusan santri yang tinggal di asrama. Seiring bertumbuhnya jumlah santri, kebutuhan pelayanan kesehatan yang cepat, tepat, dan terdata menjadi semakin mendesak.

### Permasalahan yang Ditemukan

- Pencatatan kesehatan santri masih **manual** menggunakan buku dan kertas — rawan hilang dan sulit ditelusuri
- Tidak ada sistem **prioritas penanganan** — santri dilayani berdasarkan urutan datang, bukan tingkat kegawatan klinis
- Santri dalam kondisi kritis bisa **tertunda penanganannya** karena kalah urutan antrian
- Wali santri **tidak mendapat informasi real-time** tentang kondisi kesehatan anaknya di pesantren
- Manajemen stok obat dan resep masih dilakukan secara manual, rawan selisih dan kesalahan pemberian obat

### Solusi

Membangun **Sistem Pendukung Keputusan (SPK)** berbasis metode SAW yang mampu:
- Menentukan prioritas triase klinis secara **objektif dan otomatis**
- Mengintegrasikan rekam medis, farmasi, dan portal wali dalam **satu platform**
- Memberikan informasi real-time kepada seluruh pihak yang berkepentingan

---

---

# SLIDE 3 — RUMUSAN MASALAH

## Pertanyaan Penelitian

**1.** Bagaimana merancang sistem yang dapat mengklasifikasikan prioritas penanganan santri sakit secara **objektif** berdasarkan 5 parameter klinis terukur (suhu tubuh, skala nyeri, riwayat komorbid, durasi sakit, dan vitalitas)?

**2.** Bagaimana mengintegrasikan **rekam medis digital, manajemen farmasi, dan portal wali santri** dalam satu sistem informasi kesehatan yang terpadu?

**3.** Bagaimana mengimplementasikan metode **Simple Additive Weighting (SAW)** dalam sistem triase medis berbasis web yang dapat digunakan langsung oleh petugas kesehatan pesantren?

---

---

# SLIDE 4 — BATASAN MASALAH

## Ruang Lingkup Sistem

### Yang Termasuk dalam Sistem

- Triase SAW dengan 5 kriteria klinis, bobot dapat dikonfigurasi oleh admin
- Rekam medis digital — diagnosis dokter, tindakan, dan rekomendasi rawat
- Manajemen farmasi — antrean resep digital dan pengelolaan stok obat
- Portal wali santri — pemantauan kondisi ananda secara real-time
- Laporan dan rekapitulasi kunjungan, distribusi triase, dan mutasi obat
- Sistem autentikasi berlapis dengan Role-Based Access Control (RBAC)
- Fitur chat CS real-time antara wali santri dan petugas poskestren
- Reset password dengan token yang kedaluwarsa otomatis dalam 1 menit

### Yang Tidak Termasuk dalam Sistem

- Telemedicine atau layanan video call antara dokter dan pasien
- Integrasi dengan sistem BPJS atau rekam medis rumah sakit luar
- Versi aplikasi mobile (hanya web berbasis localhost)
- Sistem billing atau pembayaran jasa medis
- Penanganan hasil laboratorium atau tes klinis lanjutan

---

---

# SLIDE 5 — METODE SAW

## Simple Additive Weighting (SAW)

Metode SAW adalah metode Sistem Pendukung Keputusan (SPK) multikriteria yang bekerja dengan menghitung **skor akhir** dari setiap alternatif (pasien) berdasarkan bobot dan nilai masing-masing kriteria klinis. Santri dengan skor tertinggi mendapat prioritas penanganan pertama.

### 5 Kriteria Klinis & Bobotnya

| Kode | Kriteria | Bobot |
|------|----------|-------|
| C1 | Suhu Tubuh | 30% |
| C2 | Skala Nyeri / Keparahan | 25% |
| C3 | Riwayat Komorbid / Alergi | 20% |
| C4 | Durasi Sakit | 15% |
| C5 | Vitalitas & Tanda-Tanda Vital | 10% |
| | **Total** | **100%** |

### Proses Perhitungan

1. Petugas mengisi nilai tiap kriteria (skala 1–5) untuk santri yang datang
2. Sistem menormalisasi nilai: **R = X / max(X)**
3. Sistem menghitung skor akhir: **V = 0.30×R1 + 0.25×R2 + 0.20×R3 + 0.15×R4 + 0.10×R5**
4. Santri diurutkan dari skor tertinggi ke terendah

### Klasifikasi Prioritas

| Prioritas | Skor SAW | Tindakan |
|-----------|----------|----------|
| P1 — Gawat Darurat | V ≥ 0.80 | Tangani segera |
| P2 — Mendesak | 0.50 ≤ V < 0.80 | Dalam 30 menit |
| P3 — Biasa / Ringan | V < 0.50 | Antrian normal |

> **Emergency Override:** Dokter dapat menetapkan P1 secara manual untuk kondisi yang mengancam jiwa (kejang, tidak sadar, syok) tanpa menunggu kalkulasi sistem.

---

---

# SLIDE 6 — PROGRESS PENGERJAAN

## Perkembangan Proyek hingga UTS

**Total Progress: ≥ 75% Selesai** — Melampaui syarat minimal UTS (50%)

### Fase Pengerjaan

| Fase | Cakupan | Status |
|------|---------|--------|
| Fase 1 — Fondasi | Koneksi database, autentikasi, RBAC, login multi-role, reset password 1 menit | ✅ Selesai |
| Fase 2 — Triase SAW | Mesin kalkulasi SAW, input 5 kriteria, emergency override, ranking real-time | ✅ Selesai |
| Fase 3 — Rekam Medis & Farmasi | Rekam medis dokter, resep digital, atomic transaction stok, audit mutasi | ✅ Selesai |
| Fase 4 — Portal Wali & Chat | Portal wali santri, chat real-time, polling balasan, notifikasi badge | ✅ Selesai |
| Fase 5 — Dashboard Dokter & Farmasi | Dashboard klinis dokter, monitor rawat inap, dashboard farmasi, papan shift | ✅ Selesai |
| Fase 6 — Laporan & Optimasi | Ekspor CSV, laporan grafis, pengujian akhir | 🔄 Ongoing |

### Statistik Proyek

| Item | Jumlah |
|------|--------|
| Halaman PHP | 15+ file |
| Tabel Database | 17 tabel |
| Role Pengguna | 5 role |
| Kriteria SAW | 5 kriteria |
| Modul Selesai | 11 dari 12 modul |

---

---

# SLIDE 7 — PERAN & FUNGSI MODUL

## Penjelasan Setiap Modul dalam Sistem

Sistem terdiri dari **12 modul** yang saling terintegrasi, dibagi menjadi 5 kelompok fungsional:

### Kelompok 1 — Akses & Keamanan
Mengatur siapa yang boleh masuk ke sistem dan apa yang boleh mereka lakukan. Setiap pengguna diarahkan otomatis ke halaman yang sesuai dengan jabatannya setelah login.

### Kelompok 2 — Triase & Keputusan Klinis
Inti dari sistem — menghitung prioritas penanganan santri secara objektif menggunakan metode SAW. Dokter juga bisa melihat detail parameter gejala tiap pasien secara lengkap.

### Kelompok 3 — Rekam Medis & Tindakan
Mencatat hasil pemeriksaan fisik, diagnosis, tindakan yang diberikan, dan keputusan apakah santri perlu rawat jalan, rawat inap, atau dirujuk ke rumah sakit.

### Kelompok 4 — Farmasi & Obat
Menerima resep dari dokter secara digital, memproses penyerahan obat, serta mengelola ketersediaan stok dengan pencatatan masuk-keluar yang akurat.

### Kelompok 5 — Informasi & Komunikasi
Menyediakan portal bagi wali santri untuk memantau kondisi anaknya dan berkomunikasi langsung dengan petugas melalui fitur chat real-time.

---

---

# SLIDE 8 — MODUL YANG TELAH DIKERJAKAN

## Detail Masing-Masing Modul

### 1. Beranda Publik (`index.php`)
Halaman utama yang dapat diakses siapa saja tanpa login. Menampilkan profil Poskestren Al-Bahjah, alur pelayanan 4 tahap, dan chat CS langsung untuk wali santri. Dirancang sebagai pintu masuk bagi pengunjung umum dan wali santri.

### 2. Autentikasi & Reset Password (`login_petugas.php`, `login.php`, `reset_password.php`)
Sistem login terpisah untuk petugas medis dan wali santri. Setelah login, sistem mendeteksi jabatan pengguna secara otomatis dan mengarahkan ke halaman yang sesuai — dokter ke Dashboard Dokter, farmasi ke Dashboard Farmasi. Dilengkapi fitur lupa password dengan token yang kedaluwarsa dalam 1 menit.

### 3. Dashboard Umum (`dashboard.php`)
Diakses oleh perawat dan nakes. Menampilkan statistik real-time (kunjungan hari ini, jumlah P1/P2/P3, rawat inap, stok kritis), tabel antrean triase aktif dengan ranking SAW, grafik donut distribusi triase, dan panel pesan masuk dari pengunjung/wali.

### 4. Dashboard Dokter (`dokter.php`)
Khusus dokter dan admin. Menampilkan kondisi klinis tiap santri secara rinci — parameter SAW per kriteria dengan visualisasi bar, nilai normalisasi, tanda-tanda vital (TTV), diagnosis, serta monitor pasien rawat inap aktif yang bisa diperbarui catatan perkembangannya langsung dari dashboard.

### 5. Triase SAW (`triase.php`)
Form input skrining klinis santri. Petugas memilih santri, mengisi keluhan, dan memilih nilai tiap kriteria (C1–C5). Sistem otomatis menghitung skor SAW dan menampilkan hasil ranking. Tersedia tombol Emergency Override untuk eskalasi darurat langsung ke P1.

### 6. Rekam Medis (`rekam_medis.php`)
Dokter mengisi pemeriksaan fisik lengkap — tekanan darah, suhu, nadi, laju napas, saturasi oksigen, tingkat kesadaran. Kemudian menetapkan diagnosis, tindakan pertama, dan keputusan rawat (Rawat Jalan / Rawat Inap / Rujuk RS). Resep obat dibuat langsung di halaman yang sama.

### 7. Dashboard Farmasi (`dashboard_farmasi.php`)
Tampilan khusus apoteker dengan 3 tab: Antrean Resep Digital, Inventaris Stok Obat, dan Log Mutasi. Penyerahan obat dilakukan dengan sistem atomic transaction yang memastikan stok terpotong tepat dan tercatat di log audit. Dilengkapi peringatan stok kritis dan obat mendekati kedaluwarsa.

### 8. Data Santri (`santri.php`)
Manajemen data induk santri — NIS, nama lengkap, jenis kelamin, asrama, kamar, tanggal lahir, nama wali, dan nomor HP wali. Dilengkapi fitur pencarian, filter asrama, dan tombol aksi cepat triase langsung dari daftar santri.

### 9. Tenaga Kesehatan & Shift (`nakes.php`)
Menampilkan papan nama petugas yang sedang bertugas hari ini (dokter jaga, perawat triase, apoteker). Admin dapat mengubah nama petugas shift kapan saja karena jadwal jaga bergantian. Di bawahnya tersedia direktori semua akun petugas aktif di sistem.

### 10. Konfigurasi Kriteria SAW (`kriteria_saw.php`)
Admin dapat mengubah bobot persentase tiap kriteria (total wajib 100%), serta mengelola deskripsi dan nilai crisp (1–5) dari setiap sub-kriteria. Perubahan bobot langsung mempengaruhi semua kalkulasi SAW berikutnya.

### 11. Portal Wali Santri (`wali.php`)
Wali santri login untuk memantau kondisi anaknya — status antrean, hasil triase, rekam medis, dan resep obat. Tersedia fitur chat real-time dengan petugas CS dengan quick reply, polling balasan tiap 5 detik, dan tab "Profil Saya" untuk melihat data akun dan data santri terkait.

### 12. Laporan (`laporan.php`)
Rekap data kunjungan, distribusi triase, 10 diagnosa terbanyak, dan mutasi stok obat dengan filter periode tanggal. Dilengkapi fitur cetak dan ekspor CSV. *(Modul dalam pengerjaan lanjutan)*

---

---

# SLIDE 9 — DEMO SISTEM

## Rencana Demonstrasi

Akses sistem: **http://localhost/Proyek_Sem5/**

### Skenario Demo

| No | Langkah | Yang Ditunjukkan |
|----|---------|-----------------|
| 1 | Login sebagai Admin (`admin` / `admin123`) | Redirect otomatis ke Dashboard Dokter |
| 2 | Input Triase Santri Baru | Form 5 kriteria, kalkulasi SAW, ranking real-time |
| 3 | Buat Rekam Medis | TTV, diagnosis, resep digital dari halaman yang sama |
| 4 | Login sebagai Farmasi (`farmasi1` / `admin123`) | Dashboard Farmasi — terima & proses resep |
| 5 | Login sebagai Wali (`085770909120` / `admin123`) | Portal wali, kirim pesan ke CS |
| 6 | Balas chat dari Dashboard Admin | Panel pesan, lihat profil pengirim, kirim balasan |

### Akun Demo Lengkap

| Role | Username | Password | Masuk ke |
|------|----------|----------|---------|
| Admin / Dokter | `admin` | `admin123` | Dashboard Dokter |
| Dokter | `nakes1` | `admin123` | Dashboard Dokter |
| Perawat | `nakes2` | `admin123` | Dashboard Umum |
| Apoteker | `farmasi1` | `admin123` | Dashboard Farmasi |
| Wali Santri | `085770909120` | `admin123` | Portal Wali |

---

*Sistem Informasi Kesehatan Poskestren Al-Bahjah Bogor*
*Proyek Semester 5 — UTS | PHP 8.0 + MySQL + SAW*
