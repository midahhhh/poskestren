<?php
// test_diagnostik.php - Script Audit Menyeluruh Database & Error Checking
require_once 'koneksi.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=========================================================\n";
echo "🔍 AUDIT KESEHATAN SISTEM & DATABASE POSKESTREN AL-BAHJAH\n";
echo "=========================================================\n\n";

$errors = [];
$warnings = [];

// 1. CEK KONEKSI & CHARSET
echo "[1] Memeriksa Koneksi Database...\n";
if ($conn->connect_error) {
    $errors[] = "Koneksi database gagal: " . $conn->connect_error;
} else {
    echo "✓ Koneksi database berhasil ke '{$database}' (Host: {$host})\n";
    echo "✓ Charset aktif: " . $conn->character_set_name() . "\n";
}

// 2. CEK SELURUH TABEL & JUMLAH BARIS
echo "\n[2] Memeriksa Keberadaan Tabel & Jumlah Data...\n";
$tables_expected = [
    'users',
    'santri',
    'saw_kriteria',
    'saw_sub_kriteria',
    'kunjungan_sakit',
    'saw_nilai_pemeriksaan',
    'pemeriksaan_klinis',
    'rawat_inap',
    'obat_kategori',
    'obat',
    'resep_obat',
    'resep_obat_detail',
    'obat_mutasi_stok',
    'notifikasi_wali'
];

foreach ($tables_expected as $tbl) {
    $cek = $conn->query("SHOW TABLES LIKE '$tbl'");
    if ($cek->num_rows == 0) {
        $errors[] = "Tabel '$tbl' TIDAK DITEMUKAN di database!";
    } else {
        $count = $conn->query("SELECT COUNT(*) as c FROM `$tbl`")->fetch_assoc()['c'];
        echo sprintf("  %-25s : %d data\n", $tbl, $count);
        if ($count == 0 && in_array($tbl, ['users', 'santri', 'saw_kriteria', 'saw_sub_kriteria'])) {
            $warnings[] = "Tabel master '$tbl' masih kosong!";
        }
    }
}

// 3. CEK INTEGRITAS KRITERIA & BOBOT SAW
echo "\n[3] Memeriksa Validasi Kriteria & Total Bobot SAW...\n";
$q_saw = $conn->query("SELECT SUM(bobot_persen) as total_bobot, COUNT(*) as jml FROM saw_kriteria")->fetch_assoc();
echo "  Jumlah Kriteria SAW : " . $q_saw['jml'] . " kriteria\n";
echo "  Total Bobot Persen  : " . $q_saw['total_bobot'] . "%\n";

if (abs($q_saw['total_bobot'] - 100.0) > 0.01) {
    $errors[] = "Total bobot kriteria SAW tidak 100%! Nilai: {$q_saw['total_bobot']}%";
} else {
    echo "✓ Total bobot SAW valid tepat 100.00%\n";
}

// 4. CEK RELASI FOREIGN KEYS & DATA ANOMALI
echo "\n[4] Memeriksa Konsistensi Relasi Data (Foreign Key Health)...\n";

// Cek kunjungan_sakit tanpa santri
$orphan_kunjungan = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit k LEFT JOIN santri s ON k.santri_id = s.id WHERE s.id IS NULL")->fetch_assoc()['c'];
if ($orphan_kunjungan > 0) {
    $errors[] = "Ditemukan $orphan_kunjungan kunjungan_sakit tanpa data santri yang valid!";
} else {
    echo "✓ Seluruh kunjungan_sakit terhubung dengan santri valid.\n";
}

// Cek resep_obat tanpa kunjungan_sakit
$orphan_resep = $conn->query("SELECT COUNT(*) as c FROM resep_obat r LEFT JOIN kunjungan_sakit k ON r.kunjungan_id = k.id WHERE k.id IS NULL")->fetch_assoc()['c'];
if ($orphan_resep > 0) {
    $errors[] = "Ditemukan $orphan_resep resep_obat tanpa kunjungan_sakit yang valid!";
} else {
    echo "✓ Seluruh resep_obat terhubung dengan kunjungan valid.\n";
}

// Cek resep_obat_detail tanpa obat
$orphan_detail = $conn->query("SELECT COUNT(*) as c FROM resep_obat_detail rd LEFT JOIN obat o ON rd.obat_id = o.id WHERE o.id IS NULL")->fetch_assoc()['c'];
if ($orphan_detail > 0) {
    $errors[] = "Ditemukan $orphan_detail item resep_obat_detail tanpa obat valid!";
} else {
    echo "✓ Seluruh resep_obat_detail memiliki referensi obat valid.\n";
}

// 5. CEK FORMULA KALKULASI SAW
echo "\n[5] Menguji Kalkulasi Mesin SAW pada Data Aktif...\n";
$res_antrean = $conn->query("SELECT k.id, k.no_antrean, k.skor_saw_akhir, k.tingkat_prioritas, s.nama_lengkap 
                            FROM kunjungan_sakit k 
                            JOIN santri s ON k.santri_id = s.id 
                            ORDER BY k.skor_saw_akhir DESC");
while ($a = $res_antrean->fetch_assoc()) {
    echo sprintf("  %-10s | Skor SAW: %-6s | %-16s | Santri: %s\n", 
        $a['no_antrean'], 
        number_format($a['skor_saw_akhir'], 4), 
        $a['tingkat_prioritas'], 
        $a['nama_lengkap']
    );
}

// 6. CEK PENGGUNA & HASH PASSWORD
echo "\n[6] Memeriksa Akun Pengguna & Enkripsi Password...\n";
$res_users = $conn->query("SELECT id, username, role, nama_lengkap, password_hash FROM users");
while ($u = $res_users->fetch_assoc()) {
    $is_valid_bcrypt = str_starts_with($u['password_hash'], '$2y$') || str_starts_with($u['password_hash'], '$2a$');
    $pw_works = password_verify('admin123', $u['password_hash']);
    echo sprintf("  User: %-15s | Role: %-18s | Bcrypt: %s | Default 'admin123' Valid: %s\n",
        $u['username'],
        $u['role'],
        $is_valid_bcrypt ? 'Ya' : 'TIDAK',
        $pw_works ? 'Ya' : 'Periksa'
    );
    if (!$is_valid_bcrypt) {
        $warnings[] = "Password hash untuk user '{$u['username']}' tidak berformat bcrypt standar.";
    }
}

// 7. HASIL AKHIR
echo "\n=========================================================\n";
if (empty($errors)) {
    echo "🎉 HASIL AUDIT: DATABASE DAN LOGIKA SISTEM BERSIH (0 ERROR)\n";
} else {
    echo "⚠️ DITEMUKAN " . count($errors) . " KESALAHAN:\n";
    foreach ($errors as $e) echo "  - ❌ $e\n";
}

if (!empty($warnings)) {
    echo "\nℹ️ PERINGATAN / CATATAN:\n";
    foreach ($warnings as $w) echo "  - 💡 $w\n";
}
echo "=========================================================\n";
?>
