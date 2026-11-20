# SINGLE SOURCE OF TRUTH (SOT) & ARSITEKTUR SISTEM
## SPK Health & Triase Poskestren Al-Bahjah Bogor (PHP Stack)

Dokumen ini merupakan acuan tunggal dan kontrak kerja pengembangan aplikasi Sistem Pendukung Keputusan (SPK) Triase Layanan Kesehatan Santri di Poskestren Al-Bahjah Cabang Bogor.

---

### 1. Profil & Spesifikasi Teknologi
- **Platform**: Web Application (PHP Stack)
- **Runtime / Web Server**: PHP 8.x + Apache (XAMPP Environment)
- **Database**: MySQL / MariaDB (`poskestren_albahjah`)
- **Frontend / Styling**: Tailwind CSS (CDN/Modern utility) + Custom Healthcare Design Tokens + Lucide Icons + Google Font (Inter)
- **Metode SPK**: Simple Additive Weighting (SAW) untuk penentuan prioritas antrean triase medis
- **Prinsip Keamanan Medis**: Eskalasi darurat manual (Emergency Override) tanpa halangan kalkulasi matematis

---

### 2. Standar Palet Warna & Desain UI
| Token | Nilai Hex | Peruntukan |
|---|---|---|
| **Primary Green** | `#16845B` | Tombol utama, navigasi aktif, aksen positif |
| **Deep Green** | `#0B3D2E` | Sidebar, top header, teks judul utama berbobot |
| **Warm Yellow** | `#F4C542` | Badge perhatian non-darurat, triase sedang (P2) |
| **Emergency Red** | `#D64545` | Badge darurat/kritis (P1), tombol destruktif/override |
| **White** | `#FFFFFF` | Permukaan kartu, kontainer tabel, dialog modal |
| **Background** | `#F5F8F6` | Latar belakang seluruh halaman aplikasi |
| **Text Primary** | `#182923` | Warna teks utama dan heading |
| **Muted Text** | `#64756D` | Teks sekunder, keterangan pembantu, timestamp |
| **Border** | `#DCE7E0` | Garis pembatas kartu, baris tabel, border input |

> **Aturan Aksesibilitas Warna**: Indikator status klinis tidak boleh hanya mengandalkan warna semata. Wajib menyertakan kombinasi **Warna + Label Teks Tegas + Ikon SVG**.

---

### 3. Pemetaan 11 Halaman PHP Aplikasi

1. **`index.php` (Landing Page & Antrean Publik)**
   - Beranda publik dengan profil Poskestren Al-Bahjah Bogor, jam operasional, dan alur 4 tahap pelayanan.
   - Tabel ringkas live preview antrean triase dengan masking nama/identitas santri untuk privasi medis.
   - Transparansi 5 kriteria klinis SAW (C1–C5) dan tombol akses portal login.

2. **`login.php` (Autentikasi Multirole & Registrasi Wali)**
   - Autentikasi aman untuk 4 role: Admin, Petugas Kesehatan (Dokter/Perawat), Apoteker/Farmasi, dan Wali Santri / Santri.
   - Tab registrasi akun wali santri yang terverifikasi otomatis dengan NIS santri terdaftar.
   - Pencegahan brute force, sanitasi input, dan regenerasi sesi PHP aman.

3. **`dashboard.php` (Dashboard Operasional Terpadu)**
   - Metrik statistik: Total kunjungan hari ini, antrean P1 (Darurat), P2 (Mendesak), P3 (Biasa), keterisian rawat inap poskestren, dan obat stok kritis.
   - Visualisasi grafik triase & tren penyakit mingguan.
   - Quick table antrean aktif dengan aksi cepat panggil/periksa.

4. **`santri.php` (Master Data Santri)**
   - Manajemen data santri (NIS, nama lengkap, jenis kelamin, kompleks asrama putra/putri, kamar, tanggal lahir, kontak wali).
   - Pencarian instan dan filter asrama/kompleks.
   - Riwayat komorbid/alergi obat per santri.

5. **`nakes.php` (Master Data Tenaga Kesehatan & Pengguna)**
   - Pengelolaan akun nakes (Dokter, Perawat, Petugas Triase, Apoteker).
   - Pencatatan nomor izin/STR, profesi, jadwal jaga, dan status akun aktif.
   - Hak akses Role-Based Access Control (RBAC).

6. **`kriteria_saw.php` (Konfigurasi Kriteria & Bobot SAW)**
   - Tabel konfigurasi 5 kriteria medis (C1: Suhu Tubuh, C2: Skala Nyeri, C3: Riwayat Komorbid, C4: Durasi Sakit, C5: Tanda Vital & Kesadaran).
   - Validasi ketat total bobot persentase wajib 100%.
   - Manajemen skala subkriteria & nilai crisp konversi (1–5).

7. **`triase.php` (Pemeriksaan Skrining & Kalkulasi SAW)**
   - Form input triase: TTV awal (suhu, tensi, nadi, RR, GCS/kesadaran, skala nyeri 1–10).
   - Mesin kalkulasi SAW transparan: Matriks Keputusan (X), Matriks Ternormalisasi (R), dan Nilai Preferensi Akhir (V).
   - Tombol **Emergency Override (Bypass SAW)** untuk kondisi gawat darurat yang membutuhkan penanganan instan tanpa menunggu skor ranking.

8. **`rekam_medis.php` (Rekam Medis & Keputusan Klinis)**
   - Anamnesis, keluhan fisik lanjutan, penetapan diagnosis.
   - Keputusan tindakan: Rawat Jalan, Rawat Inap/Observasi Poskestren, atau Rujukan Rumah Sakit luar.
   - Generator Surat Izin Istirahat Belajar santri dengan QR/kode verifikasi.

9. **`farmasi.php` (Pelayanan Farmasi & Mutasi Stok Obat)**
   - Antrean resep obat langsung terhubung dari keputusan tindakan dokter.
   - Alur status resep: Menunggu Diproses -> Diproses -> Siap Diambil -> Selesai.
   - Pengurangan stok secara transaksional (atomic) untuk menjamin integritas mutasi obat.
   - Peringatan stok obat menipis (< batas minimal) dan mendekati kadaluarsa (expired).

10. **`portal.php` (Portal Santri & Wali Santri)**
    - Akses mandiri untuk santri (login NIS) dan wali santri (login No. HP terdaftar).
    - Memantau status antrean santri bersangkutan secara real-time.
    - Riwayat kunjungan kesehatan, obat yang didapatkan, dan unduh Surat Izin Sakit.

11. **`laporan.php` (Sistem Pelaporan & Rekapitulasi Data)**
    - Rekapitulasi laporan berkala (harian, mingguan, bulanan) dengan filter tanggal.
    - Laporan distribusi triase SAW, laporan kunjungan santri per asrama, laporan 10 besar diagnosa penyakit, dan rekapitulasi mutasi obat.
    - Fitur Cetak (Print View) ramah kertas & Ekspor CSV/Excel.

---

### 4. Rencana Tahapan Eksekusi (Implementation Phases)
- **Fase 0**: Validasi SOT, kontrak kerja 11 halaman, arsitektur database MySQL, dan persetujuan pengguna. *(Tahap Saat Ini)*
- **Fase 1**: Penataan fondasi umum (navbar bersama, header, footer, token CSS, helper fungsi, session guard) dan migrasi data awal.
- **Fase 2**: Implementasi Autentikasi aman (`login.php`) & Dashboard Terpadu (`dashboard.php`).
- **Fase 3**: Implementasi Modul Master Data (`santri.php`, `nakes.php`, `kriteria_saw.php`).
- **Fase 4**: Implementasi Modul Triase & Mesin Kalkulasi SAW Teruji (`triase.php`).
- **Fase 5**: Implementasi Rekam Medis & Surat Izin Istirahat (`rekam_medis.php`).
- **Fase 6**: Implementasi Pelayanan Farmasi & Manajemen Stok Obat (`farmasi.php`).
- **Fase 7**: Implementasi Portal Santri/Wali (`portal.php`) & Pelaporan/Ekspor (`laporan.php`).
- **Fase 8**: Hardening keamanan, pengujian end-to-end, dan dokumentasi operasional.
