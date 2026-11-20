<?php
// setup_database.php - Seed Data Komprehensif Poskestren Al-Bahjah Bogor
require_once 'koneksi.php';

$conn->set_charset("utf8mb4");

echo "Memulai inisialisasi & seeder database...\n";

// 1. Pastikan tabel saw_sub_kriteria terisi
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE saw_sub_kriteria");
$conn->query("TRUNCATE TABLE saw_nilai_pemeriksaan");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
$sub_kriteria = [
    // C1: Suhu Tubuh (30%, Benefit)
    [1, "Demam Tinggi (>= 39.0 °C)", 5],
    [1, "Demam Sedang (38.1 - 38.9 °C)", 4],
    [1, "Subfebris (37.5 - 38.0 °C)", 3],
    [1, "Normal Rendah (36.5 - 37.4 °C)", 2],
    [1, "Hipotermia (< 36.5 °C)", 3],

    // C2: Skala Nyeri / Keparahan (25%, Benefit)
    [2, "Nyeri Sangat Berat / Tak Tertahankan (Skala 9-10)", 5],
    [2, "Nyeri Berat (Skala 7-8)", 4],
    [2, "Nyeri Sedang (Skala 4-6)", 3],
    [2, "Nyeri Ringan (Skala 1-3)", 2],
    [2, "Tidak Ada Nyeri (Skala 0)", 1],

    // C3: Riwayat Komorbid (20%, Benefit)
    [3, "Komorbid Berat Aktif (Asma Akut / Jantung / Kejang)", 5],
    [3, "Komorbid Sedang (Gastritis Akut / Alergi Berat)", 4],
    [3, "Komorbid Ringan Terkontrol (Alergi Ringan)", 3],
    [3, "Ada Riwayat Penyakit Menular", 3],
    [3, "Tidak Ada Riwayat Komorbid", 1],

    // C4: Durasi Sakit (15%, Benefit)
    [4, "Mendadak Akut (< 3 Jam dengan perburukan)", 5],
    [4, "Akut (3 - 12 Jam)", 4],
    [4, "Subakut (1 - 3 Hari)", 3],
    [4, "Kronis (> 3 Hari tanpa perburukan)", 2],
    [4, "Keluhan Berkala / Kontrol Rutin", 1],

    // C5: Tanda Vital & Kesadaran (10%, Benefit)
    [5, "GCS < 13 / Penurunan Kesadaran (Unresponsive/Pain)", 5],
    [5, "Sesak Napas Berat (RR > 30x/mnt / SpO2 < 92%)", 5],
    [5, "Gelisah / Tampak Sangat Lemah (Verbal response)", 3],
    [5, "Tanda Vital Sedikit Berubah (SpO2 93-95%)", 2


],
    [5, "Kompos Mentis Penuh / TTV Normal", 1],
];

$stmt_sub = $conn->prepare("INSERT INTO saw_sub_kriteria (kriteria_id, deskripsi, nilai_crisp) VALUES (?, ?, ?)");
foreach ($sub_kriteria as $sk) {
    $stmt_sub->bind_param("isi", $sk[0], $sk[1], $sk[2]);
    $stmt_sub->execute();
}
echo "✓ Sub-kriteria SAW berhasil diisi (" . count($sub_kriteria) . " data).\n";

// 2. Isi tabel users jika kosong atau belum lengkap
$password_hash = password_hash('admin123', PASSWORD_BCRYPT);
$users = [
    ['admin', $password_hash, 'dr. H. Ahmad Fauzan, Sp.A', 'admin', '081122334455'],
    ['nakes1', $password_hash, 'dr. Siti Nurhaliza, M.Biomed', 'petugas_kesehatan', '081234567801'],
    ['nakes2', $password_hash, 'Ns. Hendra Pratama, S.Kep', 'petugas_kesehatan', '081234567802'],
    ['farmasi1', $password_hash, 'Apt. Rahmat Hidayat, S.Farm', 'petugas_farmasi', '081234567803'],
    ['wali_farhan', $password_hash, 'Ahmad Hidayat (Wali M. Farhan)', 'santri_wali', '081234567890'],
    ['wali_budi', $password_hash, 'Budi Santoso (Wali Fathurrahman)', 'santri_wali', '081298765432'],
    ['2024-AB-0192', $password_hash, 'M. Farhan (Akun Santri)', 'santri_wali', '081234567891'],
];

foreach ($users as $u) {
    $cek = $conn->query("SELECT id FROM users WHERE username = '{$u[0]}'");
    if ($cek->num_rows == 0) {
        $stmt_u = $conn->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, no_hp) VALUES (?, ?, ?, ?, ?)");
        $stmt_u->bind_param("sssss", $u[0], $u[1], $u[2], $u[3], $u[4]);
        $stmt_u->execute();
    }
}
echo "✓ Akun pengguna (Users) berhasil disiapkan.\n";

// Hubungkan akun wali ke santri
$res_wali = $conn->query("SELECT id FROM users WHERE username = 'wali_farhan'")->fetch_assoc();
if ($res_wali) {
    $conn->query("UPDATE santri SET user_id_wali = {$res_wali['id']} WHERE nis = '2024-AB-0192'");
}

// 3. Tambahkan beberapa data santri jika kurang
$santri_extra = [
    ['2024-AB-0305', 'Muhammad Zaky Pratama', 'L', 'Kamar 102 (Khalid)', 'Putra', '2008-03-15', 'Sulaiman Zaky', '081377889900'],
    ['2023-AB-0112', 'Aisyah Humaira', 'P', 'Kamar 301 (Fatimah)', 'Putri', '2007-08-22', 'dr. Imran Rosyadi', '081900112233'],
    ['2024-AB-0244', 'Khadijah Azzahra', 'P', 'Kamar 204 (Aisyah)', 'Putri', '2008-12-10', 'Hj. Maryam', '085299887766'],
];
foreach ($santri_extra as $s) {
    $cek_s = $conn->query("SELECT id FROM santri WHERE nis = '{$s[0]}'");
    if ($cek_s->num_rows == 0) {
        $stmt_s = $conn->prepare("INSERT INTO santri (nis, nama_lengkap, jenis_kelamin, kamar_asrama, kompleks_asrama, tanggal_lahir, nama_wali, no_hp_wali) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_s->bind_param("ssssssss", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]);
        $stmt_s->execute();
    }
}
echo "✓ Master data santri berhasil diverifikasi.\n";

// 4. Kategori Obat & Data Obat
$kategori = ['Analgesik & Antipiretik', 'Antibiotik & Antijamur', 'Antihistamin & Alergi', 'Saluran Pencernaan', 'Vitamin & Suplemen', 'Obat Luar & Antiseptik'];
foreach ($kategori as $kat) {
    $cek_k = $conn->query("SELECT id FROM obat_kategori WHERE nama_kategori = '$kat'");
    if ($cek_k->num_rows == 0) {
        $conn->query("INSERT INTO obat_kategori (nama_kategori) VALUES ('$kat')");
    }
}

// Ambil ID Kategori
$kat_map = [];
$res_k = $conn->query("SELECT id, nama_kategori FROM obat_kategori");
while ($r = $res_k->fetch_assoc()) {
    $kat_map[$r['nama_kategori']] = $r['id'];
}

$obat_list = [
    [$kat_map['Analgesik & Antipiretik'], 'OBT-001', 'Paracetamol 500mg Tablet', 'Tablet', 20, 150, '2027-08-30', 'Penurun demam dan pereda nyeri'],
    [$kat_map['Analgesik & Antipiretik'], 'OBT-002', 'Ibuprofen 400mg Kaplet', 'Kaplet', 15, 65, '2027-04-15', 'Anti-inflamasi dan pereda demam'],
    [$kat_map['Antibiotik & Antijamur'], 'OBT-003', 'Amoxicillin 500mg Kapsul', 'Kapsul', 25, 45, '2026-12-31', 'Antibiotik spektrum luas (resep dokter)'],
    [$kat_map['Antihistamin & Alergi'], 'OBT-004', 'Cetirizine 10mg Tablet', 'Tablet', 10, 8, '2027-01-20', 'Antialergi dan gatal (Stok Menipis)'],
    [$kat_map['Saluran Pencernaan'], 'OBT-005', 'Antasida Doen Tablet Kunyah', 'Tablet', 30, 120, '2027-11-10', 'Pereda nyeri lambung dan asam lambung'],
    [$kat_map['Saluran Pencernaan'], 'OBT-006', 'Oralit Serbuk Sachet', 'Sachet', 40, 200, '2028-02-14', 'Rehidrasi santri diare / muntah'],
    [$kat_map['Vitamin & Suplemen'], 'OBT-007', 'Vitamin C 500mg IPI', 'Tablet', 20, 5, '2026-11-05', 'Mendekati expired dan stok kritis!'],
    [$kat_map['Obat Luar & Antiseptik'], 'OBT-008', 'Povidone Iodine 60ml', 'Botol', 10, 25, '2027-09-01', 'Antiseptik luka luar'],
    [$kat_map['Obat Luar & Antiseptik'], 'OBT-009', 'Salep Hidrokortison 1%', 'Tube', 10, 18, '2027-06-25', 'Salep alergi gatal dan radang kulit'],
    [$kat_map['Analgesik & Antipiretik'], 'OBT-010', 'Paracetamol Sirup 120mg/5ml', 'Botol', 10, 14, '2027-03-12', 'Sirup penurun panas santri junior'],
];

foreach ($obat_list as $ob) {
    $cek_ob = $conn->query("SELECT id FROM obat WHERE kode_obat = '{$ob[1]}'");
    if ($cek_ob->num_rows == 0) {
        $stmt_ob = $conn->prepare("INSERT INTO obat (kategori_id, kode_obat, nama_obat, satuan, stok_minimal, stok_sekarang, exp_date, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ob->bind_param("isssiiss", $ob[0], $ob[1], $ob[2], $ob[3], $ob[4], $ob[5], $ob[6], $ob[7]);
        $stmt_ob->execute();
    }
}
echo "✓ Master data obat & kategori obat berhasil disiapkan.\n";

// 5. Tambah data kunjungan jika belum ada
$cek_kunjungan = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit")->fetch_assoc()['c'];
if ($cek_kunjungan < 5) {
    $conn->query("INSERT INTO kunjungan_sakit (no_antrean, santri_id, keluhan_utama, status_antrean, skor_saw_akhir, tingkat_prioritas) VALUES 
    ('ANT-004', 4, 'Nyeri lambung akut melilit dan mual muntah 2x', 'pemeriksaan', 0.7250, 'P2_Mendesak'),
    ('ANT-005', 5, 'Luka lecet kaki tergores seng asrama saat piket', 'menunggu_triase', 0.2840, 'P3_Biasa')");
}

// 6. Isi saw_nilai_pemeriksaan untuk seluruh 5 kunjungan jika kosong
$cek_np = $conn->query("SELECT COUNT(*) as c FROM saw_nilai_pemeriksaan")->fetch_assoc()['c'];
if ($cek_np == 0) {
    // Data nilai crisp [C1, C2, C3, C4, C5] untuk masing-masing kunjungan
    $data_skor_kunjungan = [
        1 => [5, 5, 5, 4, 4], // Farhan (Demam tinggi 39.4, Nyeri hebat, komorbid asma) -> P1 Darurat
        2 => [4, 3, 3, 4, 2], // Fathurrahman (Demam 38.2, batuk berdahak 5 hari) -> P2 Mendesak
        3 => [2, 2, 1, 2, 1], // Abdullah (Alergi ringan, flu tanpa demam) -> P3 Biasa
        4 => [3, 4, 4, 5, 3], // Zaky (Nyeri lambung melilit akut) -> P2 Mendesak
        5 => [2, 2, 1, 2, 1]  // Aisyah (Lecet luka ringan) -> P3 Biasa
    ];

    $stmt_ins_np = $conn->prepare("INSERT INTO saw_nilai_pemeriksaan (kunjungan_id, kriteria_id, sub_kriteria_id, nilai_raw, nilai_normalisasi, nilai_terbobot) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($data_skor_kunjungan as $k_id => $skors) {
        for ($c = 1; $c <= 5; $c++) {
            $raw_val = $skors[$c - 1];
            // Cari sub kriteria id dengan crisp tersebut
            $sub_id = $conn->query("SELECT id FROM saw_sub_kriteria WHERE kriteria_id = $c AND nilai_crisp = $raw_val LIMIT 1")->fetch_assoc()['id'] ?? 1;
            $stmt_ins_np->bind_param("iiiddd", $k_id, $c, $sub_id, $raw_val, $raw_val, $raw_val);
            $stmt_ins_np->execute();
        }
    }
    echo "✓ Nilai matriks pemeriksaan SAW berhasil diisi untuk seluruh antrean.\n";
}

// 7. Buat rekam medis contoh & resep obat contoh
$kunjungan_1 = $conn->query("SELECT id FROM kunjungan_sakit WHERE no_antrean = 'ANT-001'")->fetch_assoc();
$petugas = $conn->query("SELECT id FROM users WHERE role = 'petugas_kesehatan' LIMIT 1")->fetch_assoc();
$farmasi = $conn->query("SELECT id FROM users WHERE role = 'petugas_farmasi' LIMIT 1")->fetch_assoc();

if ($kunjungan_1 && $petugas) {
    $cek_pem = $conn->query("SELECT id FROM pemeriksaan_klinis WHERE kunjungan_id = {$kunjungan_1['id']}");
    if ($cek_pem->num_rows == 0) {
        $conn->query("INSERT INTO pemeriksaan_klinis (kunjungan_id, petugas_id, tekanan_darah, suhu_tubuh, nadi, laju_respirasi, saturasi_oksigen, tingkat_kesadaran, diagnosa_dokter, tindakan_pertama, rekomendasi_rawat) VALUES 
        ({$kunjungan_1['id']}, {$petugas['id']}, '110/70', 39.4, 104, 28, 94, 'Apatis / Lemas', 'Bronkopneumonia Ringan & Febris Tinggi', 'Pemberian oksigen nasal 2 lpm, kompres hangat, antipiretik oral', 'Rawat Inap Poskestren')");

        // Catat Rawat Inap
        $conn->query("INSERT INTO rawat_inap (kunjungan_id, nomor_bed, status_rawat, catatan_perkembangan) VALUES 
        ({$kunjungan_1['id']}, 'Bed 02 (Ruang Al-Kindi)', 'Dirawat', 'Observasi suhu tiap 2 jam, pastikan asupan cairan cukup')");
    }

    // Buat resep obat contoh
    $cek_resep = $conn->query("SELECT id FROM resep_obat WHERE kunjungan_id = {$kunjungan_1['id']}");
    if ($cek_resep->num_rows == 0) {
        $conn->query("INSERT INTO resep_obat (kunjungan_id, status_resep, catatan_resep) VALUES 
        ({$kunjungan_1['id']}, 'diproses', 'Diminum setelah makan, perbanyak air putih')");
        $resep_id = $conn->insert_id;

        $obat_p = $conn->query("SELECT id FROM obat WHERE kode_obat = 'OBT-001'")->fetch_assoc();
        $obat_a = $conn->query("SELECT id FROM obat WHERE kode_obat = 'OBT-003'")->fetch_assoc();

        if ($obat_p && $obat_a) {
            $conn->query("INSERT INTO resep_obat_detail (resep_id, obat_id, jumlah, dosis_instruksi) VALUES 
            ($resep_id, {$obat_p['id']}, 10, '3 x 1 tablet sesudah makan (bila demam)'),
            ($resep_id, {$obat_a['id']}, 15, '3 x 1 kapsul dihabiskan')");
        }
    }
}

// 8. Log Mutasi Awal Obat & Notifikasi Wali Contoh
$cek_mutasi = $conn->query("SELECT COUNT(*) as c FROM obat_mutasi_stok")->fetch_assoc()['c'];
if ($cek_mutasi == 0) {
    $u_admin = $conn->query("SELECT id FROM users WHERE username = 'admin'")->fetch_assoc()['id'] ?? 1;
    $conn->query("INSERT INTO obat_mutasi_stok (obat_id, jenis_mutasi, jumlah, keterangan, user_id) VALUES 
        (1, 'masuk', 100, 'Penerimaan logistik dinas kesehatan', $u_admin),
        (5, 'masuk', 50, 'Pengadaan persediaan rutin poskestren', $u_admin)");
    echo "✓ Data awal log mutasi obat berhasil dicatat.\n";
}

$cek_notif = $conn->query("SELECT COUNT(*) as c FROM notifikasi_wali")->fetch_assoc()['c'];
if ($cek_notif == 0) {
    $conn->query("INSERT INTO notifikasi_wali (santri_id, kunjungan_id, judul, pesan, status_baca) VALUES 
        (1, 1, 'Pemberitahuan Kesehatan Ananda M. Farhan', 'Ananda saat ini sedang dalam penanganan di Poskestren Al-Bahjah karena febris dan batuk. Mohon doanya.', 0)");
    echo "✓ Notifikasi wali santri siap.\n";
}

// 9. Jalankan kalkulasi SAW terpusat
jalankan_kalkulasi_saw($conn);
echo "✓ Perhitungan SAW diperbarui dan tersinkronisasi ke seluruh antrean.\n";

echo "Selesai! Database 'poskestren_albahjah' terisi dengan lengkap dan siap digunakan.\n";
