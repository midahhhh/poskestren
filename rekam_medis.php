<?php
// rekam_medis.php - Rekam Medis Elektronik (RME / SOAP) & Pelayanan Klinis Santri
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Verifikasi Hak Akses: Dokter, Tenaga Medis, atau Admin
if (!isset($_SESSION['user_id'])) {
    set_flash('error', "Sesi telah berakhir. Silakan masuk kembali.");
    header("Location: login_petugas.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? 'Dokter Pemeriksa');
$user_id   = (int)$_SESSION['user_id'];

if ($user_role !== 'petugas_kesehatan' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Modul Rekam Medis khusus Tenaga Medis & Dokter.");
    header("Location: login_petugas.php");
    exit();
}

// Pengamanan Ketat: Jika Administrator masuk, alihkan langsung ke Rekam Medis Khusus Admin
if ($user_role === 'admin') {
    $target = 'rekam_medis_admin.php';
    if (!empty($_GET['kunjungan_id'])) {
        $target .= '?detail_id=' . (int)$_GET['kunjungan_id'];
    }
    header("Location: " . $target);
    exit();
}

$is_admin = false;
$dashboard_kembali_url   = 'dashboard_dokter.php';
$dashboard_kembali_label = '← Kembali ke Dashboard Dokter';
$user_role_label         = 'Dokter Pemeriksa / SIP Terdaftar';
$unit_badge              = 'Poli Dokter';
$unit_badge_class        = 'bg-emerald-50 text-emerald-800 border-emerald-200';
$farmasi_kembali_url     = 'dashboard_farmasi.php';

$kunjungan_id = (int)($_GET['kunjungan_id'] ?? 0);
$err = "";

// ════════════════════════════════════════════════════════════════════
// PROSES SIMPAN REKAM MEDIS & KEPUTUSAN TINDAKAN (SOAP)
// ════════════════════════════════════════════════════════════════════
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['action']) && $_POST['action'] === 'simpan_rekam_medis') {
    $kunjungan_id      = (int)$_POST['kunjungan_id'];
    $tekanan_darah     = trim($_POST['tekanan_darah'] ?? '120/80');
    $suhu_tubuh        = floatval($_POST['suhu_tubuh'] ?? 36.8);
    $nadi              = (int)($_POST['nadi'] ?? 80);
    $laju_respirasi    = (int)($_POST['laju_respirasi'] ?? 20);
    $saturasi_oksigen  = (int)($_POST['saturasi_oksigen'] ?? 98);
    $tingkat_kesadaran = trim($_POST['tingkat_kesadaran'] ?? 'Kompos Mentis');
    $diagnosa_dokter   = trim($_POST['diagnosa_dokter'] ?? '');
    $tindakan_pertama  = trim($_POST['tindakan_pertama'] ?? '');
    $rekomendasi_rawat = $_POST['rekomendasi_rawat'] ?? 'Rawat Jalan';
    $nomor_bed         = trim($_POST['nomor_bed'] ?? '');
    $lama_istirahat    = (int)($_POST['lama_istirahat'] ?? 2);
    $catatan_resep     = trim($_POST['catatan_resep'] ?? '');

    if (empty($diagnosa_dokter)) {
        $err = "Diagnosis klinis dokter wajib diisi.";
    } elseif ($kunjungan_id <= 0) {
        $err = "Data kunjungan santri tidak valid.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Simpan atau perbarui data pemeriksaan klinis
            $cek_pem = $conn->prepare("SELECT id FROM pemeriksaan_klinis WHERE kunjungan_id = ?");
            $cek_pem->bind_param("i", $kunjungan_id);
            $cek_pem->execute();
            $res_pem = $cek_pem->get_result();

            if ($res_pem->num_rows > 0) {
                $stmt_up = $conn->prepare("UPDATE pemeriksaan_klinis SET petugas_id = ?, tekanan_darah = ?, suhu_tubuh = ?, nadi = ?, laju_respirasi = ?, saturasi_oksigen = ?, tingkat_kesadaran = ?, diagnosa_dokter = ?, tindakan_pertama = ?, rekomendasi_rawat = ? WHERE kunjungan_id = ?");
                $stmt_up->bind_param("isdiiissssi", $user_id, $tekanan_darah, $suhu_tubuh, $nadi, $laju_respirasi, $saturasi_oksigen, $tingkat_kesadaran, $diagnosa_dokter, $tindakan_pertama, $rekomendasi_rawat, $kunjungan_id);
                $stmt_up->execute();
            } else {
                $stmt_in = $conn->prepare("INSERT INTO pemeriksaan_klinis (kunjungan_id, petugas_id, tekanan_darah, suhu_tubuh, nadi, laju_respirasi, saturasi_oksigen, tingkat_kesadaran, diagnosa_dokter, tindakan_pertama, rekomendasi_rawat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_in->bind_param("iisdiiissss", $kunjungan_id, $user_id, $tekanan_darah, $suhu_tubuh, $nadi, $laju_respirasi, $saturasi_oksigen, $tingkat_kesadaran, $diagnosa_dokter, $tindakan_pertama, $rekomendasi_rawat);
                $stmt_in->execute();
            }

            // 2. Perbarui status kunjungan dan catatan dokter
            $catatan_ringkas = "Diagnosis: $diagnosa_dokter. Tindakan: $rekomendasi_rawat.";
            $stmt_kunj = $conn->prepare("UPDATE kunjungan_sakit SET status_antrean = 'pemeriksaan', catatan_dokter = ?, dokter_pemeriksa = ? WHERE id = ?");
            $stmt_kunj->bind_param("ssi", $catatan_ringkas, $user_name, $kunjungan_id);
            $stmt_kunj->execute();

            // 3. Kelola Rawat Inap Poskestren jika diputuskan rawat inap
            if ($rekomendasi_rawat === 'Rawat Inap Poskestren') {
                $nomor_bed = $nomor_bed ?: 'Bed Observasi Poskestren';
                $cek_ri = $conn->prepare("SELECT id FROM rawat_inap WHERE kunjungan_id = ? AND status_rawat = 'Dirawat'");
                $cek_ri->bind_param("i", $kunjungan_id);
                $cek_ri->execute();
                if ($cek_ri->get_result()->num_rows === 0) {
                    $catatan_ri = "Pasien diobservasi intensif di Poskestren. Diagnosis: $diagnosa_dokter.";
                    $stmt_ri = $conn->prepare("INSERT INTO rawat_inap (kunjungan_id, nomor_bed, status_rawat, catatan_perkembangan) VALUES (?, ?, 'Dirawat', ?)");
                    $stmt_ri->bind_param("iss", $kunjungan_id, $nomor_bed, $catatan_ri);
                    $stmt_ri->execute();
                }
            }

            // 4. Proses Resep Farmasi Elektronik (R/) jika dokter memilih obat
            if (!empty($_POST['obat_id']) && is_array($_POST['obat_id'])) {
                // Buat atau perbarui header resep_obat
                $cek_res = $conn->prepare("SELECT id FROM resep_obat WHERE kunjungan_id = ?");
                $cek_res->bind_param("i", $kunjungan_id);
                $cek_res->execute();
                $res_r = $cek_res->get_result();

                if ($res_r->num_rows > 0) {
                    $resep_id = $res_r->fetch_assoc()['id'];
                    $up_res = $conn->prepare("UPDATE resep_obat SET catatan_resep = ?, status_resep = 'menunggu' WHERE id = ?");
                    $up_res->bind_param("si", $catatan_resep, $resep_id);
                    $up_res->execute();
                    // Hapus detail lama untuk diperbarui
                    $conn->query("DELETE FROM resep_obat_detail WHERE resep_id = $resep_id");
                } else {
                    $ins_res = $conn->prepare("INSERT INTO resep_obat (kunjungan_id, status_resep, catatan_resep) VALUES (?, 'menunggu', ?)");
                    $ins_res->bind_param("is", $kunjungan_id, $catatan_resep);
                    $ins_res->execute();
                    $resep_id = $conn->insert_id;
                }

                // Tambahkan detail baris obat
                $stmt_dt = $conn->prepare("INSERT INTO resep_obat_detail (resep_id, obat_id, jumlah, dosis_instruksi) VALUES (?, ?, ?, ?)");
                foreach ($_POST['obat_id'] as $idx => $ob_id) {
                    $ob_id_int = (int)$ob_id;
                    $jml_int   = max(1, (int)($_POST['jumlah'][$idx] ?? 1));
                    $instruksi = trim($_POST['dosis_instruksi'][$idx] ?? '3 x 1 sesudah makan');
                    if ($ob_id_int > 0) {
                        $stmt_dt->bind_param("iiis", $resep_id, $ob_id_int, $jml_int, $instruksi);
                        $stmt_dt->execute();
                    }
                }
            }

            $conn->commit();
            set_flash('success', "Rekam Medis santri berhasil disimpan. Resep digital telah diteruskan ke Unit Farmasi.");
            header("Location: rekam_medis.php?kunjungan_id=$kunjungan_id&action=lihat");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $err = "Gagal menyimpan rekam medis: " . $e->getMessage();
        }
    }
}

// ════════════════════════════════════════════════════════════════════
// PENGAMBILAN DATA PASIEN AKTIF & REKAM MEDIS
// ════════════════════════════════════════════════════════════════════
$kunjungan = null;
$rekam_existing = null;
$resep_existing = [];

if ($kunjungan_id > 0) {
    $stmt_k = $conn->prepare(
        "SELECT k.id, k.no_antrean, k.tanggal_berobat, k.keluhan_utama, k.status_antrean,
                k.skor_saw_akhir, k.tingkat_prioritas, k.catatan_dokter,
                s.id as santri_id, s.nama_lengkap, s.nis, s.jenis_kelamin, s.kamar_asrama,
                s.kompleks_asrama, s.tanggal_lahir, s.nama_wali, s.no_hp_wali
         FROM kunjungan_sakit k
         JOIN santri s ON k.santri_id = s.id
         WHERE k.id = ?"
    );
    $stmt_k->bind_param("i", $kunjungan_id);
    $stmt_k->execute();
    $kunjungan = $stmt_k->get_result()->fetch_assoc();

    if ($kunjungan) {
        // Ambil data pemeriksaan klinis yang sudah ada (jika ada)
        $stmt_pk = $conn->prepare(
            "SELECT pk.*, u.nama_lengkap as nama_nakes
             FROM pemeriksaan_klinis pk
             LEFT JOIN users u ON pk.petugas_id = u.id
             WHERE pk.kunjungan_id = ?"
        );
        $stmt_pk->bind_param("i", $kunjungan_id);
        $stmt_pk->execute();
        $rekam_existing = $stmt_pk->get_result()->fetch_assoc();

        // Ambil resep obat jika ada
        $stmt_ro = $conn->prepare(
            "SELECT rod.*, o.nama_obat, o.satuan, ro.status_resep, ro.catatan_resep
             FROM resep_obat ro
             JOIN resep_obat_detail rod ON rod.resep_id = ro.id
             JOIN obat o ON rod.obat_id = o.id
             WHERE ro.kunjungan_id = ?"
        );
        $stmt_ro->bind_param("i", $kunjungan_id);
        $stmt_ro->execute();
        $resep_existing = $stmt_ro->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Master Data Obat untuk Resep
$list_obat = [];
$res_o = $conn->query("SELECT id, nama_obat, satuan, stok_sekarang FROM obat WHERE stok_sekarang > 0 ORDER BY nama_obat ASC");
if ($res_o) {
    $list_obat = $res_o->fetch_all(MYSQLI_ASSOC);
}

// Antrean Santri yang Siap Diperiksa Dokter
$q_ready = $conn->query(
    "SELECT k.id, k.no_antrean, k.keluhan_utama, k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas,
            s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama, s.jenis_kelamin
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan')
     ORDER BY CASE k.tingkat_prioritas 
         WHEN 'P1_Gawat_Darurat' THEN 1 
         WHEN 'P2_Mendesak' THEN 2 
         ELSE 3 END ASC,
         k.skor_saw_akhir DESC, k.id ASC LIMIT 25"
);

// Pasien yang Sudah Selesai Diperiksa Dokter (Arsip EMR)
$q_selesai = $conn->query(
    "SELECT pk.id as pemeriksaan_id, pk.tekanan_darah, pk.suhu_tubuh, pk.nadi, pk.laju_respirasi,
            pk.saturasi_oksigen, pk.diagnosa_dokter, pk.tindakan_pertama, pk.rekomendasi_rawat,
            k.id as kunjungan_id, k.no_antrean, k.tanggal_berobat, k.keluhan_utama,
            k.status_antrean, k.tingkat_prioritas, k.skor_saw_akhir, k.dokter_pemeriksa,
            s.id as santri_id, s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama, s.jenis_kelamin,
            u.nama_lengkap as nama_nakes_akun,
            (SELECT COUNT(*) FROM resep_obat_detail rod JOIN resep_obat ro ON rod.resep_id = ro.id WHERE ro.kunjungan_id = k.id) as jumlah_obat
     FROM pemeriksaan_klinis pk
     JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN users u ON pk.petugas_id = u.id
     ORDER BY k.id DESC LIMIT 25"
);
$cnt_selesai = $q_selesai ? $q_selesai->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $kunjungan ? 'RME: ' . htmlspecialchars($kunjungan['nama_lengkap']) : 'Rekam Medis Santri'; ?> — Poskestren Al-Bahjah</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F8F6;
            color: #182923;
        }
        .mono-num {
            font-family: 'JetBrains Mono', monospace;
        }
        .card-panel {
            background-color: #FFFFFF;
            border: 1px solid #DCE7E0;
            border-radius: 12px;
        }
        @media print {
            header, footer, .no-print { display: none !important; }
            body { background: #FFFFFF !important; color: #000000 !important; font-size: 12pt; }
            .print-document { 
                border: none !important; 
                box-shadow: none !important; 
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#F5F8F6] text-[#182923]">

    <!-- ============================================================== -->
    <!-- TOP HORIZONTAL NAVBAR                                          -->
    <!-- ============================================================== -->
    <header class="sticky top-0 z-40 bg-white border-b border-[#DCE7E0] shadow-xs no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                
                <!-- Brand & Logo -->
                <div class="flex items-center gap-3">
                    <a href="<?= $dashboard_kembali_url; ?>" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-sm tracking-tight">
                            AB
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-[#0B3D2E] tracking-tight">Poskestren Al-Bahjah</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $unit_badge_class; ?>">
                                    <?= $unit_badge; ?>
                                </span>
                            </div>
                            <p class="text-[11px] text-[#64756D] leading-none mt-0.5">Sistem Pelayanan Rekam Medis & Triase Santri</p>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 text-xs font-semibold text-[#64756D]">
                    <?php if ($is_admin): ?>
                    <a href="dashboard.php" class="px-3 py-2 rounded-lg text-[#0B3D2E] bg-slate-100 font-bold hover:bg-slate-200 transition">
                        Dashboard Admin
                    </a>
                    <a href="dashboard_dokter.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Antrean Dokter
                    </a>
                    <?php else: ?>
                    <a href="dashboard_dokter.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Antrean Pasien
                    </a>
                    <?php endif; ?>
                    <a href="rekam_medis.php" class="px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold">
                        Rekam Medis (EMR)
                    </a>
                    <a href="index.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Beranda Publik
                    </a>
                </nav>

                <!-- Doctor / Admin Profile & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="text-right hidden sm:block">
                        <span class="block text-xs font-bold text-[#0B3D2E] leading-tight"><?= htmlspecialchars($user_name); ?></span>
                        <span class="block text-[10px] text-[#64756D]"><?= $user_role_label; ?></span>
                    </div>
                    <a href="logout.php" onclick="return confirm('Keluar dari sesi?')" 
                       class="hidden sm:flex px-3 py-1.5 rounded-lg border border-[#DCE7E0] hover:bg-red-50 hover:border-red-200 hover:text-[#D64545] text-xs font-semibold text-[#64756D] transition items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Keluar</span>
                    </a>
                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="const m = document.getElementById('mobile-nav-rekam'); m.classList.toggle('hidden');" 
                            class="md:hidden p-2 rounded-lg text-[#0B3D2E] hover:bg-slate-100 transition flex items-center justify-center" aria-label="Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Menu (Collapsible) -->
        <div id="mobile-nav-rekam" class="hidden md:hidden px-4 py-3 border-t border-[#DCE7E0] bg-white space-y-1.5 no-print">
            <?php if ($is_admin): ?>
            <a href="dashboard.php" class="block px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold text-xs">
                Dashboard Admin
            </a>
            <a href="dashboard_dokter.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Antrean Dokter
            </a>
            <?php else: ?>
            <a href="dashboard_dokter.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Antrean Pasien
            </a>
            <?php endif; ?>
            <a href="rekam_medis.php" class="block px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold text-xs">
                Rekam Medis (EMR)
            </a>
            <a href="index.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Beranda Publik
            </a>
            <div class="pt-2 mt-1 border-t border-[#DCE7E0] flex items-center justify-between px-1">
                <div>
                    <span class="block text-xs font-bold text-[#0B3D2E]"><?= htmlspecialchars($user_name); ?></span>
                    <span class="block text-[10px] text-[#64756D]"><?= $user_role_label; ?></span>
                </div>
                <a href="logout.php" onclick="return confirm('Keluar dari sesi?')" class="px-2.5 py-1 text-xs font-bold text-red-600 bg-red-50 rounded-md border border-red-200">
                    Keluar
                </a>
            </div>
        </div>
    </header>

    <!-- ============================================================== -->
    <!-- MAIN CONTENT AREA                                              -->
    <!-- ============================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5 flex-1 w-full">

        <!-- Flash Message Notification -->
        <?php 
        $fok  = get_flash('success');
        $ferr = get_flash('error');
        if ($fok): ?>
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-900 flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($fok); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold">&times;</button>
        </div>
        <?php endif; if ($ferr || !empty($err)): ?>
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs font-medium text-red-900 flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-red-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($ferr ?: $err); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 font-bold">&times;</button>
        </div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-xs font-medium text-purple-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2 no-print shadow-2xs">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Anda membuka antrean input klinis dokter. Untuk melihat data pasien yang <strong>sudah diperiksa</strong>, silakan buka <strong>Rekam Medis Admin</strong>.</span>
            </div>
            <a href="rekam_medis_admin.php" class="px-3 py-1 rounded-md bg-[#0B3D2E] hover:bg-[#16845B] text-white font-bold transition flex items-center gap-1 shadow-2xs self-start sm:self-auto">
                Buka Rekam Medis Admin →
            </a>
        </div>
        <?php endif; ?>

        <?php if (!$kunjungan): ?>
        <!-- ========================================================== -->
        <!-- VIEW 1: DAFTAR ANTREAN PASIEN SIAP DIPERIKSA DOKTER        -->
        <!-- ========================================================== -->
        <div class="card-panel p-4 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E]">Antrean Pasien Menunggu Pemeriksaan Klinis (SOAP)</h2>
                    <p class="text-xs text-[#64756D]">Pilih santri untuk membuka lembar rekam medis, input diagnosa, dan menerbitkan resep elektronik.</p>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($is_admin): ?>
                    <a href="rekam_medis_admin.php" class="px-3 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white text-xs font-semibold transition shadow-2xs">
                        Rekam Medis Admin →
                    </a>
                    <?php endif; ?>
                    <a href="<?= $dashboard_kembali_url; ?>" class="px-3 py-1.5 rounded-lg border border-[#DCE7E0] text-[#0B3D2E] text-xs font-semibold hover:bg-slate-50 transition">
                        <?= $dashboard_kembali_label; ?>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3">No. Antrean</th>
                            <th class="py-2.5 px-3">Identitas Santri</th>
                            <th class="py-2.5 px-3">Kamar / Asrama</th>
                            <th class="py-2.5 px-3">Keluhan Awal (Triase)</th>
                            <th class="py-2.5 px-3">Prioritas SAW</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                        <?php if ($q_ready && $q_ready->num_rows > 0): ?>
                            <?php while ($qr = $q_ready->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 mono-num font-bold text-[#0B3D2E]">
                                    <?= htmlspecialchars($qr['no_antrean'] ?: 'ANT-'.$qr['id']); ?>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($qr['nama_lengkap']); ?></span>
                                    <span class="mono-num text-[10px] text-[#64756D]">NIS: <?= $qr['nis']; ?> • <?= $qr['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri'; ?></span>
                                </td>
                                <td class="py-2.5 px-3 text-[#64756D]">
                                    <?= htmlspecialchars($qr['kamar_asrama'] ?? '-'); ?>
                                    <span class="block text-[10px] text-emerald-800 font-medium"><?= $qr['kompleks_asrama']; ?></span>
                                </td>
                                <td class="py-2.5 px-3 max-w-[280px]">
                                    <span class="font-medium text-[#182923]"><?= htmlspecialchars($qr['keluhan_utama']); ?></span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <?= badge_triase($qr['tingkat_prioritas'], $qr['skor_saw_akhir']); ?>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <a href="rekam_medis.php?kunjungan_id=<?= $qr['id']; ?>&action=periksa" 
                                       class="px-3 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition inline-flex items-center gap-1">
                                        <span>Buka Form SOAP</span>
                                        <span>→</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-[#64756D]">
                                    Tidak ada pasien yang menunggu pemeriksaan saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- VIEW 1B: ARSIP REKAM MEDIS SANTRI YANG SUDAH DIPERIKSA     -->
        <!-- ========================================================== -->
        <div class="card-panel p-4 space-y-3.5 mt-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E] flex items-center gap-2">
                        <span>Riwayat Pasien Selesai Diperiksa (Arsip EMR)</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"><?= $cnt_selesai; ?> Pasien</span>
                    </h2>
                    <p class="text-xs text-[#64756D]">Daftar santri yang telah mendapatkan tindakan medis, diagnosis klinis, dan resep dokter.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3">No. Antrean & Tgl</th>
                            <th class="py-2.5 px-3">Identitas Santri</th>
                            <th class="py-2.5 px-3">Dokter Pemeriksa</th>
                            <th class="py-2.5 px-3">Diagnosis Medis & Keluhan</th>
                            <th class="py-2.5 px-3">Tanda Vital (TTV)</th>
                            <th class="py-2.5 px-3">Disposisi Rawat</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                        <?php if ($q_selesai && $q_selesai->num_rows > 0): ?>
                            <?php while ($qs = $q_selesai->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3">
                                    <span class="mono-num font-bold text-xs text-[#0B3D2E] block">
                                        <?= htmlspecialchars($qs['no_antrean'] ?: 'ANT-'.$qs['kunjungan_id']); ?>
                                    </span>
                                    <span class="text-[10px] text-[#64756D] block">
                                        <?= date('d F Y', strtotime($qs['tanggal_berobat'])); ?>
                                    </span>
                                    <div class="mt-1">
                                        <?= badge_triase($qs['tingkat_prioritas'], $qs['skor_saw_akhir']); ?>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($qs['nama_lengkap']); ?></span>
                                    <span class="mono-num text-[10px] text-[#64756D]">NIS: <?= $qs['nis']; ?> • <?= $qs['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri'; ?></span>
                                    <span class="block text-[10px] text-emerald-800 font-medium"><?= htmlspecialchars($qs['kamar_asrama'] ?? '-'); ?> (<?= $qs['kompleks_asrama']; ?>)</span>
                                </td>
                                <td class="py-2.5 px-3 text-[#182923]">
                                    <strong class="block text-xs"><?= htmlspecialchars($qs['dokter_pemeriksa'] ?: ($qs['nama_nakes_akun'] ?: 'Dokter Poskestren')); ?></strong>
                                    <span class="text-[10px] text-[#64756D]">SIP / STR Terdaftar</span>
                                </td>
                                <td class="py-2.5 px-3 max-w-[260px]">
                                    <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($qs['diagnosa_dokter'] ?: 'Pemeriksaan Rutin'); ?></span>
                                    <span class="text-[11px] text-[#64756D] block">Keluhan: <?= htmlspecialchars($qs['keluhan_utama']); ?></span>
                                    <?php if ((int)$qs['jumlah_obat'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        💊 <?= (int)$qs['jumlah_obat']; ?> Macam Obat Diresepkan
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="mono-num font-bold text-xs <?= (float)$qs['suhu_tubuh'] >= 38.0 ? 'text-red-600' : 'text-emerald-700'; ?> block">
                                        <?= number_format((float)$qs['suhu_tubuh'], 1); ?> °C
                                    </span>
                                    <span class="mono-num text-[10px] text-[#64756D] block">TD: <?= htmlspecialchars($qs['tekanan_darah'] ?: '120/80'); ?></span>
                                    <span class="mono-num text-[10px] text-emerald-800 block">SpO2: <?= (int)$qs['saturasi_oksigen']; ?>%</span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <?php 
                                    $rw = $qs['rekomendasi_rawat'];
                                    $rw_cls = 'bg-slate-100 text-slate-800 border-slate-300';
                                    if ($rw === 'Rawat Jalan') $rw_cls = 'bg-emerald-100 text-emerald-900 border-emerald-300';
                                    if ($rw === 'Rawat Inap Poskestren') $rw_cls = 'bg-amber-100 text-amber-900 border-amber-300';
                                    if ($rw === 'Rujuk RS') $rw_cls = 'bg-red-100 text-red-900 border-red-300';
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $rw_cls; ?>">
                                        <?= htmlspecialchars($rw); ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="rekam_medis.php?kunjungan_id=<?= $qs['kunjungan_id']; ?>&action=lihat" 
                                           class="px-2.5 py-1 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-[11px] transition inline-flex items-center gap-1 shadow-2xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <span>Lihat EMR</span>
                                        </a>
                                        <a href="rekam_medis.php?kunjungan_id=<?= $qs['kunjungan_id']; ?>&action=edit" 
                                           class="px-2 py-1 rounded-lg border border-[#DCE7E0] hover:bg-slate-100 text-[#0B3D2E] font-semibold text-[11px] transition">
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-[#64756D]">
                                    Belum ada arsip riwayat rekam medis santri yang selesai diperiksa.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>

        <!-- ========================================================== -->
        <!-- VIEW 2: LEMBAR REKAM MEDIS SANTRI TERPILIH                 -->
        <!-- ========================================================== -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between no-print gap-2">
            <div class="flex items-center gap-2.5">
                <a href="rekam_medis.php" class="text-xs font-semibold text-[#16845B] hover:text-[#0B3D2E] transition flex items-center gap-1">
                    <span>← Kembali ke Antrean Santri</span>
                </a>
                <span class="text-[#DCE7E0]">|</span>
                <a href="<?= $dashboard_kembali_url; ?>" class="text-xs font-semibold text-[#64756D] hover:text-[#0B3D2E] transition">
                    <?= $dashboard_kembali_label; ?>
                </a>
            </div>
            <div class="text-xs text-[#64756D]">
                Status Kunjungan: <strong class="text-[#0B3D2E] uppercase font-bold"><?= str_replace('_', ' ', $kunjungan['status_antrean']); ?></strong>
            </div>
        </div>

        <!-- HEADER IDENTITAS KLINIS SANTRI (PATIENT DEMOGRAPHICS) -->
        <div class="card-panel p-4 bg-white border-[#DCE7E0] no-print">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-lg bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                        <?= strtoupper(substr($kunjungan['nama_lengkap'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-[#0B3D2E] text-base"><?= htmlspecialchars($kunjungan['nama_lengkap']); ?></h2>
                            <span class="mono-num text-xs font-bold text-[#0B3D2E] bg-slate-100 px-2 py-0.5 rounded border border-[#DCE7E0]">
                                <?= htmlspecialchars($kunjungan['no_antrean'] ?: 'ANT-'.$kunjungan['id']); ?>
                            </span>
                        </div>
                        <p class="text-xs text-[#64756D] mt-0.5">
                            NIS: <strong class="mono-num text-[#182923]"><?= $kunjungan['nis']; ?></strong> • 
                            <?= $kunjungan['jenis_kelamin'] == 'L' ? 'Santri Putra' : 'Santri Putri'; ?> • 
                            Asrama: <strong><?= $kunjungan['kamar_asrama']; ?> (<?= $kunjungan['kompleks_asrama']; ?>)</strong>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="p-2.5 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] text-xs">
                        <span class="text-[10px] uppercase font-bold text-[#64756D] block">Kontak Wali:</span>
                        <span class="font-medium"><?= htmlspecialchars($kunjungan['nama_wali'] ?: '-'); ?> (<?= htmlspecialchars($kunjungan['no_hp_wali'] ?: '-'); ?>)</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] text-xs">
                        <span class="text-[10px] uppercase font-bold text-[#64756D] block">Triase & Skor SAW:</span>
                        <div class="mt-0.5"><?= badge_triase($kunjungan['tingkat_prioritas'], $kunjungan['skor_saw_akhir']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($rekam_existing && (($_GET['action'] ?? '') === 'lihat' || (($_SERVER["REQUEST_METHOD"] ?? '') !== 'POST' && ($_GET['action'] ?? '') !== 'edit'))): ?>
            <!-- ====================================================== -->
            <!-- VIEW RINGKASAN REKAM MEDIS & SURAT KETERANGAN SAKIT    -->
            <!-- ====================================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                
                <!-- Lembar Hasil Rekam Medis Elektronik (8 Cols) -->
                <div class="lg:col-span-8 space-y-4 no-print">
                    <div class="card-panel p-5 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-[#DCE7E0]">
                            <div>
                                <h3 class="font-bold text-sm text-[#0B3D2E]">Hasil Pemeriksaan Klinis (SOAP)</h3>
                                <p class="text-[11px] text-[#64756D]">Dokter Pemeriksa: <strong><?= htmlspecialchars($rekam_existing['nama_nakes'] ?? $user_name); ?></strong></p>
                            </div>
                            <a href="rekam_medis.php?kunjungan_id=<?= $kunjungan['id']; ?>&action=edit" 
                               class="px-2.5 py-1 rounded border border-[#DCE7E0] text-xs font-semibold hover:bg-slate-50 transition">
                                Edit Data Medis
                            </a>
                        </div>

                        <!-- 5 Parameter Tanda Vital (TTV) -->
                        <div>
                            <span class="text-[11px] font-bold text-[#64756D] uppercase block mb-1.5">Tanda-Tanda Vital Objektif:</span>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 p-3 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] text-center">
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-[#64756D] block">Tekanan Darah</span>
                                    <span class="text-sm font-bold text-[#182923] mono-num"><?= htmlspecialchars($rekam_existing['tekanan_darah'] ?: '-'); ?></span>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-[#64756D] block">Suhu Tubuh</span>
                                    <span class="text-sm font-bold text-red-700 mono-num"><?= $rekam_existing['suhu_tubuh']; ?> °C</span>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-[#64756D] block">Denyut Nadi</span>
                                    <span class="text-sm font-bold text-[#182923] mono-num"><?= (int)$rekam_existing['nadi']; ?> x/mnt</span>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-[#64756D] block">Laju Napas (RR)</span>
                                    <span class="text-sm font-bold text-[#182923] mono-num"><?= (int)$rekam_existing['laju_respirasi']; ?> x/mnt</span>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-[#64756D] block">Saturasi SpO2</span>
                                    <span class="text-sm font-bold text-emerald-700 mono-num"><?= (int)$rekam_existing['saturasi_oksigen']; ?>%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Anamnesis & Diagnosa -->
                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="font-bold text-[#0B3D2E] block mb-0.5">Keluhan Utama (Anamnesis):</span>
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-[#DCE7E0] text-[#182923]">
                                    <?= nl2br(htmlspecialchars($kunjungan['keluhan_utama'])); ?>
                                </div>
                            </div>

                            <div>
                                <span class="font-bold text-[#0B3D2E] block mb-0.5">Diagnosis Medis / ICD-10 Kerja:</span>
                                <div class="p-2.5 rounded-lg bg-emerald-50/50 border border-emerald-200 font-bold text-[#0B3D2E]">
                                    <?= nl2br(htmlspecialchars($rekam_existing['diagnosa_dokter'])); ?>
                                </div>
                            </div>

                            <div>
                                <span class="font-bold text-[#0B3D2E] block mb-0.5">Tindakan Medis Awal:</span>
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-[#DCE7E0] text-[#182923]">
                                    <?= nl2br(htmlspecialchars($rekam_existing['tindakan_pertama'] ?: 'Terapi medikamentosa dan anjuran istirahat.')); ?>
                                </div>
                            </div>

                            <div>
                                <span class="font-bold text-[#0B3D2E] block mb-0.5">Disposisi & Rekomendasi Rawat:</span>
                                <span class="inline-block px-3 py-1 rounded-md text-xs font-bold bg-[#0B3D2E] text-white">
                                    <?= htmlspecialchars($rekam_existing['rekomendasi_rawat']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Rincian Resep Obat yang Diteruskan -->
                        <?php if (!empty($resep_existing)): ?>
                        <div class="pt-3 border-t border-[#DCE7E0] space-y-2">
                            <span class="font-bold text-xs text-[#0B3D2E] block">Resep Elektronik (R/) untuk Farmasi:</span>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border border-[#DCE7E0]">
                                    <thead class="bg-[#F5F8F6] text-[#0B3D2E]">
                                        <tr>
                                            <th class="py-2 px-2.5">Nama Obat</th>
                                            <th class="py-2 px-2.5">Jumlah</th>
                                            <th class="py-2 px-2.5">Signa / Aturan Minum</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#DCE7E0]">
                                        <?php foreach ($resep_existing as $ro): ?>
                                        <tr>
                                            <td class="py-2 px-2.5 font-semibold text-[#0B3D2E]"><?= htmlspecialchars($ro['nama_obat']); ?></td>
                                            <td class="py-2 px-2.5 mono-num"><?= $ro['jumlah']; ?> <?= htmlspecialchars($ro['satuan']); ?></td>
                                            <td class="py-2 px-2.5 text-[#64756D]"><?= htmlspecialchars($ro['dosis_instruksi']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kolom Kanan: Surat Keterangan Istirahat Belajar (Cetak) (4 Cols) -->
                <div class="lg:col-span-4 space-y-3">
                    <div class="card-panel p-5 bg-white border border-[#DCE7E0] print-document shadow-sm">
                        <!-- KOP SURAT RESMI PESANTREN -->
                        <div class="text-center pb-2.5 border-b-2 border-slate-800 mb-3">
                            <div class="text-[10px] font-bold tracking-wider text-slate-800 uppercase">
                                Lembaga Pengembangan Dakwah Al-Bahjah Bogor
                            </div>
                            <h4 class="font-black text-xs text-slate-950 uppercase tracking-tight">
                                Pos Kesehatan Pesantren (Poskestren)
                            </h4>
                            <p class="text-[9px] text-slate-600 leading-tight">
                                Cabang Bogor • Layanan Rawat Jalan & Triase Santri 24 Jam<br>
                                Hotline Medis: 0811-2233-4455
                            </p>
                        </div>

                        <!-- JUDUL SURAT -->
                        <div class="text-center mb-3">
                            <h5 class="text-xs font-bold text-slate-900 underline uppercase tracking-wide">
                                Surat Keterangan Istirahat Belajar
                            </h5>
                            <span class="text-[9px] mono-num text-slate-600">
                                Nomor: 0<?= $kunjungan['id']; ?>/SK-MEDIS/AB-BGR/<?= date('m/Y'); ?>
                            </span>
                        </div>

                        <div class="text-[11px] space-y-2 text-slate-800 leading-relaxed">
                            <p>Menerangkan bahwa santri yang beridentitas di bawah ini:</p>
                            
                            <table class="w-full text-[11px] my-1">
                                <tr>
                                    <td class="w-24 text-slate-600 py-0.5">Nama Santri</td>
                                    <td class="font-bold text-slate-900">: <?= htmlspecialchars($kunjungan['nama_lengkap']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-slate-600 py-0.5">Nomor Induk (NIS)</td>
                                    <td class="mono-num">: <?= $kunjungan['nis']; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-slate-600 py-0.5">Kamar / Asrama</td>
                                    <td>: <?= $kunjungan['kamar_asrama']; ?> (<?= $kunjungan['kompleks_asrama']; ?>)</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-600 py-0.5">Diagnosa Klinis</td>
                                    <td class="font-medium">: <?= htmlspecialchars($rekam_existing['diagnosa_dokter']); ?></td>
                                </tr>
                            </table>

                            <p class="pt-1 text-justify">
                                Berdasarkan hasil pemeriksaan fisik klinis dokter, santri tersebut dalam kondisi sakit dan <strong>memerlukan istirahat belajar</strong> serta dibebaskan sementara dari kegiatan belajar mengajar (KBM) dan halaqah selama:
                            </p>

                            <div class="text-center py-1.5 px-3 bg-slate-50 border border-slate-300 font-bold text-slate-900 rounded">
                                2 (Dua) Hari Terhitung Sejak Tanggal <?= date('d M Y'); ?>
                            </div>

                            <p class="text-justify text-[10px] text-slate-600">
                                Demikian surat keterangan ini diterbitkan dengan sebenarnya untuk dipergunakan sebagaimana mestinya oleh pengurus asrama dan bagian kurikulum KBM.
                            </p>

                            <!-- TANDA TANGAN DOKTER PEMERIKSA -->
                            <div class="pt-4 text-right text-[10px]">
                                <div>Bogor, <?= date('d F Y'); ?></div>
                                <div class="font-bold text-slate-800">Dokter Penanggung Jawab,</div>
                                <div class="h-12 flex items-end justify-end">
                                    <span class="text-[9px] text-emerald-800 italic font-mono bg-emerald-50 px-1 py-0.5 border border-emerald-200">
                                        [Telah Ditandatangani Elektronik]
                                    </span>
                                </div>
                                <div class="font-bold underline text-slate-900 mt-1">
                                    <?= htmlspecialchars($rekam_existing['nama_nakes'] ?? $user_name); ?>
                                </div>
                                <div class="text-slate-600 text-[9px]">SIP / STR Dokter Poskestren Al-Bahjah</div>
                            </div>
                        </div>

                        <!-- Action Button Cetak -->
                        <div class="mt-4 pt-3 border-t border-[#DCE7E0] no-print space-y-2">
                            <button type="button" onclick="window.print()" 
                                    class="w-full py-2 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition flex items-center justify-center gap-1.5 shadow-2xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                <span>Cetak Surat Izin Sakit</span>
                            </button>
                            <a href="<?= $farmasi_kembali_url; ?>" class="block w-full py-2 rounded-lg border border-[#DCE7E0] text-center text-xs font-semibold text-[#0B3D2E] hover:bg-slate-50 transition">
                                Buka Unit Farmasi Obat →
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <!-- ====================================================== -->
            <!-- VIEW FORM INPUT SOAP KLINIS BARU OLEH DOKTER           -->
            <!-- ====================================================== -->
            <form method="POST" action="rekam_medis.php" class="space-y-4">
                <input type="hidden" name="action" value="simpan_rekam_medis">
                <input type="hidden" name="kunjungan_id" value="<?= $kunjungan['id']; ?>">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    
                    <!-- Kolom Kiri: S (Subjektif) & O (Objektif / TTV) (7 Cols) -->
                    <div class="lg:col-span-7 space-y-4">
                        
                        <!-- Panel TTV & Anamnesis Objektif -->
                        <div class="card-panel p-4 space-y-3.5">
                            <div class="pb-2.5 border-b border-[#DCE7E0]">
                                <h3 class="font-bold text-sm text-[#0B3D2E]">1. Pengukuran Tanda Vital & Pemeriksaan Fisik Objektif</h3>
                                <p class="text-xs text-[#64756D]">Input parameter TTV obyektif santri saat dilakukan evaluasi klinis.</p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Tekanan Darah (mmHg)</label>
                                    <input type="text" name="tekanan_darah" placeholder="Contoh: 110/70" 
                                           value="<?= htmlspecialchars($rekam_existing['tekanan_darah'] ?? '110/70'); ?>" 
                                           class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                                </div>
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Suhu Tubuh (°C) *</label>
                                    <input type="number" step="0.1" name="suhu_tubuh" required placeholder="37.8" 
                                           value="<?= htmlspecialchars($rekam_existing['suhu_tubuh'] ?? '37.8'); ?>" 
                                           class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] text-red-700 font-bold mono-num">
                                </div>
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Denyut Nadi (x/mnt)</label>
                                    <input type="number" name="nadi" placeholder="80" 
                                           value="<?= (int)($rekam_existing['nadi'] ?? 80); ?>" 
                                           class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                                </div>
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Laju Napas RR (x/mnt)</label>
                                    <input type="number" name="laju_respirasi" placeholder="20" 
                                           value="<?= (int)($rekam_existing['laju_respirasi'] ?? 20); ?>" 
                                           class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                                </div>
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Saturasi SpO2 (%)</label>
                                    <input type="number" name="saturasi_oksigen" placeholder="98" 
                                           value="<?= (int)($rekam_existing['saturasi_oksigen'] ?? 98); ?>" 
                                           class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                                </div>
                                <div>
                                    <label class="block font-semibold text-[#0B3D2E] mb-1">Tingkat Kesadaran</label>
                                    <select name="tingkat_kesadaran" class="w-full px-2.5 py-1.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] text-xs">
                                        <option value="Kompos Mentis">Kompos Mentis (Sadar Penuh)</option>
                                        <option value="Apatis / Lemas">Apatis / Lemas</option>
                                        <option value="Somnolen">Somnolen</option>
                                        <option value="Sopor">Sopor / Penurunan Respon</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Diagnosis Klinis & Tindakan -->
                            <div class="space-y-3 pt-3 border-t border-[#DCE7E0]">
                                <div>
                                    <label class="block text-xs font-bold text-[#0B3D2E] mb-1">Diagnosis Medis Dokter (ICD-10 / Klinis) *</label>
                                    <textarea name="diagnosa_dokter" required rows="2" 
                                              placeholder="Contoh: Febris ec Suspek ISPA Akut, Faringitis, Gastritis Akut..."
                                              class="w-full p-2.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] focus:outline-none focus:ring-1 focus:ring-[#16845B] text-xs"><?= htmlspecialchars($rekam_existing['diagnosa_dokter'] ?? ''); ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-[#0B3D2E] mb-1">Tindakan Medis Awal di Poskestren</label>
                                    <textarea name="tindakan_pertama" rows="2" 
                                              placeholder="Contoh: Pemberian antipiretik oral, hidrasi cairan hangat, anjuran istirahat..."
                                              class="w-full p-2.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] focus:outline-none focus:ring-1 focus:ring-[#16845B] text-xs"><?= htmlspecialchars($rekam_existing['tindakan_pertama'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Plan, Disposisi & Resep Farmasi (5 Cols) -->
                    <div class="lg:col-span-5 space-y-4">
                        <div class="card-panel p-4 space-y-3.5">
                            <div class="pb-2.5 border-b border-[#DCE7E0]">
                                <h3 class="font-bold text-sm text-[#0B3D2E]">2. Disposisi & Resep Elektronik (R/)</h3>
                                <p class="text-xs text-[#64756D]">Tetapkan rencana tindak lanjut perawatan santri serta obat.</p>
                            </div>

                            <!-- Pilihan Rekomendasi Rawat -->
                            <div>
                                <label class="block text-xs font-semibold text-[#0B3D2E] mb-1">Keputusan Rawat / Disposisi</label>
                                <select name="rekomendasi_rawat" id="selectRekomendasi" onchange="toggleBedRawat(this.value)" required 
                                        class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] text-xs font-bold text-[#0B3D2E]">
                                    <option value="Rawat Jalan">Rawat Jalan (Istirahat di Kamar Asrama)</option>
                                    <option value="Rawat Inap Poskestren">Rawat Inap Poskestren (Observasi Bed)</option>
                                    <option value="Rujuk RS">Rujuk Rumah Sakit / Faskes Lanjutan</option>
                                </select>
                            </div>

                            <!-- Input Nomor Bed jika Rawat Inap -->
                            <div id="boxBed" class="hidden p-2.5 rounded-lg bg-amber-50 border border-amber-200">
                                <label class="block text-xs font-semibold text-amber-900 mb-1">Nomor Bed / Ruang Rawat:</label>
                                <input type="text" name="nomor_bed" placeholder="Contoh: Bed 02 (Ruang As-Syifa)" 
                                       class="w-full p-1.5 rounded border border-amber-300 bg-white text-xs">
                            </div>

                            <!-- Resep Obat Elektronik -->
                            <div class="pt-3 border-t border-[#DCE7E0] space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-[#0B3D2E]">Resep Obat (R/):</label>
                                    <button type="button" onclick="tambahBarisObat()" class="text-[11px] font-semibold text-[#16845B] hover:underline">
                                        + Tambah Baris Obat
                                    </button>
                                </div>
                                
                                <div class="space-y-2" id="containerObat">
                                    <!-- Baris Obat 1 -->
                                    <div class="p-2.5 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] space-y-2 obat-row">
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="col-span-2">
                                                <select name="obat_id[]" class="w-full p-1.5 rounded border border-[#DCE7E0] text-xs bg-white">
                                                    <option value="">-- Pilih Obat --</option>
                                                    <?php foreach ($list_obat as $ob): ?>
                                                    <option value="<?= $ob['id']; ?>"><?= htmlspecialchars($ob['nama_obat']); ?> (Stok: <?= $ob['stok_sekarang']; ?> <?= $ob['satuan']; ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <input type="number" name="jumlah[]" placeholder="Jml" min="1" value="10" 
                                                       class="w-full p-1.5 rounded border border-[#DCE7E0] text-xs text-center bg-white mono-num">
                                            </div>
                                        </div>
                                        <div>
                                            <input type="text" name="dosis_instruksi[]" placeholder="Signa: 3 x 1 tablet sesudah makan" 
                                                   class="w-full p-1.5 rounded border border-[#DCE7E0] text-xs bg-white">
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-[#0B3D2E] mb-1">Catatan Tambahan untuk Petugas Farmasi:</label>
                                    <input type="text" name="catatan_resep" placeholder="Contoh: Diminum jika demam, antibiotik dihabiskan" 
                                           class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] text-xs">
                                </div>
                            </div>

                            <!-- Tombol Submit Simpan -->
                            <div class="pt-3 border-t border-[#DCE7E0]">
                                <button type="submit" 
                                        class="w-full py-2.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Simpan Rekam Medis (SOAP)</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>

        <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- ============================================================== -->
    <!-- FOOTER                                                         -->
    <!-- ============================================================== -->
    <footer class="mt-auto bg-white border-t border-[#DCE7E0] py-4 text-xs text-[#64756D] no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</span>
                <span>— Rekam Medis Elektronik Terintegrasi</span>
            </div>
            <div>&copy; <?= date('Y'); ?> Lembaga Pengembangan Dakwah Al-Bahjah.</div>
        </div>
    </footer>

    <!-- JavaScript Handlers -->
    <script>
        function toggleBedRawat(val) {
            const box = document.getElementById('boxBed');
            if (box) {
                if (val === 'Rawat Inap Poskestren') {
                    box.classList.remove('hidden');
                } else {
                    box.classList.add('hidden');
                }
            }
        }

        function tambahBarisObat() {
            const container = document.getElementById('containerObat');
            const rowPertama = container.querySelector('.obat-row');
            if (rowPertama) {
                const clone = rowPertama.cloneNode(true);
                clone.querySelectorAll('input').forEach(input => {
                    if (input.type === 'number') input.value = 10;
                    else input.value = '';
                });
                container.appendChild(clone);
            }
        }
    </script>
</body>
</html>
