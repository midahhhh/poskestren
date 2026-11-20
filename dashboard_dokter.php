<?php
// dashboard_dokter.php - Dashboard Pelayanan Medis Dokter Poskestren Al-Bahjah Bogor
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Verifikasi Hak Akses: Khusus Dokter / Petugas Kesehatan & Admin
if (!isset($_SESSION['user_id'])) {
    set_flash('error', "Sesi telah berakhir. Silakan masuk kembali.");
    header("Location: login_petugas.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? 'Dokter Pemeriksa');
$user_id   = (int)$_SESSION['user_id'];

if ($user_role !== 'petugas_kesehatan' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Halaman ini khusus diperuntukkan bagi Dokter & Tenaga Medis.");
    header("Location: login_petugas.php");
    exit();
}

$hari_ini = date('Y-m-d');

// ════════════════════════════════════════════════════════════════════
// HANDLER: PENETAPAN PASIEN SEMBUH / SELESAI OLEH DOKTER
// ════════════════════════════════════════════════════════════════════
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tetapkan_sembuh') {
    $kunjungan_id   = (int)$_POST['kunjungan_id'];
    $catatan_dokter = trim($_POST['catatan_dokter'] ?? '');
    $nama_dokter    = !empty($user_name) ? $user_name : 'Dokter Poskestren';

    if (empty($catatan_dokter)) {
        $catatan_dokter = "Kondisi fisik telah stabil dan keluhan membaik. Santri dinyatakan pulih dan diperbolehkan kembali mengikuti KBM serta kegiatan asrama.";
    }

    if ($kunjungan_id > 0) {
        // 1. Update status kunjungan menjadi 'selesai'
        $stmt_up = $conn->prepare("UPDATE kunjungan_sakit SET status_antrean = 'selesai', tanggal_selesai = NOW(), catatan_dokter = ?, dokter_pemeriksa = ? WHERE id = ?");
        $stmt_up->bind_param("ssi", $catatan_dokter, $nama_dokter, $kunjungan_id);
        $stmt_up->execute();

        // 2. Jika santri dirawat inap, update status menjadi 'Sembuh/KBM'
        $stmt_ri = $conn->prepare("UPDATE rawat_inap SET status_rawat = 'Sembuh/KBM', tanggal_keluar = NOW(), catatan_perkembangan = CONCAT(IFNULL(catatan_perkembangan,''), '\n[Pernyataan Dokter]: ', ?) WHERE kunjungan_id = ? AND status_rawat = 'Dirawat'");
        $stmt_ri->bind_param("si", $catatan_dokter, $kunjungan_id);
        $stmt_ri->execute();

        // 3. Jalankan kalkulasi SAW agar antrean terbarui
        jalankan_kalkulasi_saw($conn);

        set_flash('success', "Pasien berhasil dinyatakan selesai/sembuh. Status data telah diperbarui secara sistemik.");
        header("Location: dashboard_dokter.php#pasien-sembuh");
        exit();
    }
}

// ════════════════════════════════════════════════════════════════════
// STATISTIK MEDIS OPERASIONAL HARI INI
// ════════════════════════════════════════════════════════════════════
$tot_hari        = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) = '$hari_ini'")->fetch_assoc()['c'];
$p1_count        = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P1_Gawat_Darurat' OR skor_saw_akhir >= 0.80)")->fetch_assoc()['c'];
$p2_count        = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P2_Mendesak' OR (skor_saw_akhir >= 0.50 AND skor_saw_akhir < 0.80))")->fetch_assoc()['c'];
$p3_count        = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P3_Biasa' AND skor_saw_akhir < 0.50)")->fetch_assoc()['c'];
$rawat_inap      = $conn->query("SELECT COUNT(*) c FROM rawat_inap WHERE status_rawat = 'Dirawat'")->fetch_assoc()['c'];
$sembuh_hari_ini = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean = 'selesai' AND (DATE(tanggal_selesai) = '$hari_ini' OR DATE(tanggal_berobat) = '$hari_ini')")->fetch_assoc()['c'];

// Pasien Aktif dalam Antrean Medis (Menunggu Pemeriksaan Dokter)
$res_pasien = $conn->query(
    "SELECT k.id as kunjungan_id, k.no_antrean, k.keluhan_utama, k.tanggal_berobat,
            k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas,
            s.id as santri_id, s.nama_lengkap, s.nis, s.jenis_kelamin,
            s.kamar_asrama, s.kompleks_asrama, s.nama_wali, s.no_hp_wali,
            pk.id as pemeriksaan_id, pk.suhu_tubuh, pk.tekanan_darah, pk.nadi,
            pk.laju_respirasi, pk.saturasi_oksigen, pk.tingkat_kesadaran,
            pk.diagnosa_dokter, pk.rekomendasi_rawat
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan')
     ORDER BY CASE k.tingkat_prioritas 
         WHEN 'P1_Gawat_Darurat' THEN 1 
         WHEN 'P2_Mendesak' THEN 2 
         ELSE 3 END ASC, k.skor_saw_akhir DESC, k.id ASC"
);

// Pasien yang Selesai / Sembuh Hari Ini
$res_sembuh = $conn->query(
    "SELECT k.id as kunjungan_id, k.no_antrean, k.keluhan_utama, k.tanggal_berobat,
            k.tanggal_selesai, k.catatan_dokter, k.dokter_pemeriksa,
            s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama,
            pk.diagnosa_dokter, pk.rekomendasi_rawat
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     WHERE k.status_antrean = 'selesai'
     AND (DATE(k.tanggal_selesai) = '$hari_ini' OR DATE(k.tanggal_berobat) = '$hari_ini')
     ORDER BY k.tanggal_selesai DESC, k.id DESC LIMIT 15"
);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poli Medis Dokter — Poskestren Al-Bahjah Bogor</title>
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
        .filter-btn.active {
            background-color: #0B3D2E;
            color: #FFFFFF;
            border-color: #0B3D2E;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#F5F8F6] text-[#182923]">

    <!-- ============================================================== -->
    <!-- TOP HORIZONTAL NAVBAR                                          -->
    <!-- ============================================================== -->
    <header class="sticky top-0 z-40 bg-white border-b border-[#DCE7E0] shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                
                <!-- Brand & Logo -->
                <div class="flex items-center gap-3">
                    <a href="dashboard_dokter.php" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-sm tracking-tight">
                            AB
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-[#0B3D2E] tracking-tight">Poskestren Al-Bahjah</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Poli Dokter
                                </span>
                            </div>
                            <p class="text-[11px] text-[#64756D] leading-none mt-0.5">Sistem Pelayanan Rekam Medis & Triase Santri</p>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 text-xs font-semibold text-[#64756D]">
                    <a href="dashboard_dokter.php" class="px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold">
                        Antrean Pasien
                    </a>
                    <a href="rekam_medis.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Rekam Medis (EMR)
                    </a>
                    
                    <a href="index.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Beranda Publik
                    </a>
                </nav>

                <!-- Doctor Profile & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="text-right hidden sm:block">
                        <span class="block text-xs font-bold text-[#0B3D2E] leading-tight"><?= htmlspecialchars($user_name); ?></span>
                        <span class="block text-[10px] text-[#64756D]">Dokter Pemeriksa / SIP Terdaftar</span>
                    </div>
                    <a href="logout.php" onclick="return confirm('Keluar dari sesi dokter?')" 
                       class="hidden sm:flex px-3 py-1.5 rounded-lg border border-[#DCE7E0] hover:bg-red-50 hover:border-red-200 hover:text-[#D64545] text-xs font-semibold text-[#64756D] transition items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Keluar</span>
                    </a>
                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="const m = document.getElementById('mobile-nav-dokter'); m.classList.toggle('hidden');" 
                            class="md:hidden p-2 rounded-lg text-[#0B3D2E] hover:bg-slate-100 transition flex items-center justify-center" aria-label="Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Menu (Collapsible) -->
        <div id="mobile-nav-dokter" class="hidden md:hidden px-4 py-3 border-t border-[#DCE7E0] bg-white space-y-1.5">
            <a href="dashboard_dokter.php" class="block px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold text-xs">
                Antrean Pasien
            </a>
            <a href="rekam_medis.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Rekam Medis (EMR)
            </a>
            <a href="index.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Beranda Publik
            </a>
            <div class="pt-2 mt-1 border-t border-[#DCE7E0] flex items-center justify-between px-1">
                <div>
                    <span class="block text-xs font-bold text-[#0B3D2E]"><?= htmlspecialchars($user_name); ?></span>
                    <span class="block text-[10px] text-[#64756D]">Dokter Pemeriksa</span>
                </div>
                <a href="logout.php" onclick="return confirm('Keluar dari sesi dokter?')" class="px-2.5 py-1 text-xs font-bold text-red-600 bg-red-50 rounded-md border border-red-200">
                    Keluar
                </a>
            </div>
        </div>
    </header>

    <!-- ============================================================== -->
    <!-- MAIN WORKSPACE                                                 -->
    <!-- ============================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5 flex-1 w-full">

        <!-- Flash Message Notification -->
        <?php 
        $fok  = get_flash('success');
        $ferr = get_flash('error');
        if ($fok): ?>
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($fok); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold">&times;</button>
        </div>
        <?php endif; if ($ferr): ?>
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs font-medium text-red-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-red-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($ferr); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 font-bold">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ============================================================== -->
        <!-- CLINICAL STATUS HEADER & WORKSPACE META                        -->
        <!-- ============================================================== -->
        <div class="card-panel p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    <h1 class="text-base font-bold text-[#0B3D2E]">Pelayanan Rawat Jalan & Pemeriksaan Medis Santri</h1>
                </div>
                <p class="text-xs text-[#64756D] mt-0.5">
                    Hari ini: <strong><?= date('l, d F Y'); ?></strong> • Unit Poskestren Al-Bahjah Cabang Bogor
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="rekam_medis.php" class="px-3.5 py-2 rounded-lg bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Form Pemeriksaan Baru</span>
                </a>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TRIAGE ALERT BAR                                               -->
        <!-- ============================================================== -->
        <?php if ($p1_count > 0): ?>
        <div class="p-3.5 rounded-lg bg-red-50 border border-red-300 text-red-900 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="px-2 py-0.5 rounded bg-red-600 text-white font-bold text-[10px] tracking-wide uppercase">
                    Prioritas 1 Darurat
                </span>
                <span class="font-medium">
                    Terdapat <strong><?= $p1_count; ?> pasien kritis</strong> dalam antrean yang memerlukan evaluasi dokter segera.
                </span>
            </div>
            <a href="#tabel-antrean" class="text-red-800 font-bold hover:underline text-xs">
                Tangani Pasien P1 ↓
            </a>
        </div>
        <?php else: ?>
        <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-200 text-emerald-900 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Status Triase Terkendali: Tidak ada antrean kritis P1 (Gawat Darurat) saat ini.</span>
            </div>
            <span class="text-[11px] text-[#64756D]">Sistem Triase SAW Aktif</span>
        </div>
        <?php endif; ?>

        <!-- ============================================================== -->
        <!-- 4 CLINICAL KEY METRICS                                         -->
        <!-- ============================================================== -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Menunggu Pemeriksaan</div>
                <div class="text-2xl font-bold mt-1 text-[#0B3D2E] mono-num"><?= $res_pasien ? $res_pasien->num_rows : 0; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">
                    P1: <strong class="text-red-700"><?= $p1_count; ?></strong> • 
                    P2: <strong class="text-amber-700"><?= $p2_count; ?></strong> • 
                    P3: <strong class="text-emerald-700"><?= $p3_count; ?></strong>
                </div>
            </div>

            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Observasi / Rawat Inap</div>
                <div class="text-2xl font-bold mt-1 text-blue-900 mono-num"><?= $rawat_inap; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Kamar Rawat Poskestren</div>
            </div>

            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Selesai / Sembuh Hari Ini</div>
                <div class="text-2xl font-bold mt-1 text-[#16845B] mono-num"><?= $sembuh_hari_ini; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Kembali ke asrama & KBM</div>
            </div>

            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Total Registrasi Hari Ini</div>
                <div class="text-2xl font-bold mt-1 text-[#182923] mono-num"><?= $tot_hari; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Semua tingkat triase</div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TABEL ANTREAN PASIEN AKTIF (CLINICAL WORKLIST)                 -->
        <!-- ============================================================== -->
        <div id="tabel-antrean" class="card-panel p-4 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E]">Daftar Antrean Pasien Santri</h2>
                    <p class="text-xs text-[#64756D]">Prioritas diurutkan secara obyektif berdasarkan kalkulasi SAW dan kegawatan klinis.</p>
                </div>

                <!-- Filter Kategori Triase -->
                <div class="flex items-center gap-1.5 text-xs overflow-x-auto pb-1 max-w-full whitespace-nowrap">
                    <button type="button" onclick="filterTriase('all')" id="btn-filter-all" class="filter-btn active px-2.5 py-1 rounded-md border border-[#DCE7E0] font-semibold">
                        Semua (<?= $res_pasien ? $res_pasien->num_rows : 0; ?>)
                    </button>
                    <button type="button" onclick="filterTriase('P1')" id="btn-filter-P1" class="filter-btn px-2.5 py-1 rounded-md border border-[#DCE7E0] font-semibold text-red-700">
                        P1 (<?= $p1_count; ?>)
                    </button>
                    <button type="button" onclick="filterTriase('P2')" id="btn-filter-P2" class="filter-btn px-2.5 py-1 rounded-md border border-[#DCE7E0] font-semibold text-amber-700">
                        P2 (<?= $p2_count; ?>)
                    </button>
                    <button type="button" onclick="filterTriase('P3')" id="btn-filter-P3" class="filter-btn px-2.5 py-1 rounded-md border border-[#DCE7E0] font-semibold text-emerald-700">
                        P3 (<?= $p3_count; ?>)
                    </button>
                </div>
            </div>

            <?php if ($res_pasien && $res_pasien->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3">No. Antrean</th>
                            <th class="py-2.5 px-3">Identitas Santri</th>
                            <th class="py-2.5 px-3">Asrama & Kamar</th>
                            <th class="py-2.5 px-3">TTV Skrining Awal</th>
                            <th class="py-2.5 px-3">Keluhan & Diagnosis Kerja</th>
                            <th class="py-2.5 px-3">Prioritas SAW</th>
                            <th class="py-2.5 px-3 text-right">Aksi Klinis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0] text-[#182923]" id="tabel-pasien-body">
                        <?php while ($p = $res_pasien->fetch_assoc()): 
                            $suhu = (float)($p['suhu_tubuh'] ?? 0);
                            $p_tier = 'P3';
                            if (strpos($p['tingkat_prioritas'], 'P1') !== false || (float)$p['skor_saw_akhir'] >= 0.80) {
                                $p_tier = 'P1';
                            } elseif (strpos($p['tingkat_prioritas'], 'P2') !== false || ((float)$p['skor_saw_akhir'] >= 0.50 && (float)$p['skor_saw_akhir'] < 0.80)) {
                                $p_tier = 'P2';
                            }
                        ?>
                        <tr class="hover:bg-slate-50/70 transition baris-pasien" data-triase="<?= $p_tier; ?>">
                            <td class="py-2.5 px-3 mono-num font-bold text-[#0B3D2E]">
                                <?= htmlspecialchars($p['no_antrean'] ?: 'ANT-'.$p['kunjungan_id']); ?>
                                <span class="block text-[10px] text-[#64756D] font-normal">
                                    <?= date('H:i', strtotime($p['tanggal_berobat'])); ?> WIB
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-[#0B3D2E]"><?= htmlspecialchars($p['nama_lengkap']); ?></div>
                                <span class="mono-num text-[10px] text-[#64756D]">NIS: <?= $p['nis']; ?> • <?= $p['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri'; ?></span>
                            </td>
                            <td class="py-2.5 px-3 text-[#64756D]">
                                <?= htmlspecialchars($p['kamar_asrama'] ?? '-'); ?>
                                <span class="block text-[10px] text-emerald-800 font-medium"><?= $p['kompleks_asrama']; ?></span>
                            </td>
                            <td class="py-2.5 px-3 mono-num text-xs">
                                <span class="font-bold <?= $suhu >= 38.5 ? 'text-red-700' : ($suhu >= 37.5 ? 'text-amber-700' : 'text-[#182923]'); ?>">
                                    <?= $suhu > 0 ? number_format($suhu, 1) . '°C' : '-'; ?>
                                </span>
                                <span class="text-[10px] text-[#64756D] block">
                                    TD: <?= htmlspecialchars($p['tekanan_darah'] ?: '-'); ?> | SpO2: <?= (int)($p['saturasi_oksigen'] ?? 0) ? (int)$p['saturasi_oksigen'].'%' : '-'; ?>
                                </span>
                            </td>
                            <td class="py-2.5 px-3 max-w-[240px]">
                                <span class="font-medium block text-[#182923]"><?= htmlspecialchars($p['keluhan_utama']); ?></span>
                                <span class="text-[11px] text-[#16845B] font-semibold block mt-0.5">
                                    Dx: <?= htmlspecialchars($p['diagnosa_dokter'] ?: 'Belum diperiksa'); ?>
                                </span>
                            </td>
                            <td class="py-2.5 px-3">
                                <?= badge_triase($p['tingkat_prioritas'], $p['skor_saw_akhir']); ?>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="rekam_medis.php?kunjungan_id=<?= $p['kunjungan_id']; ?>&action=periksa" 
                                       class="px-2.5 py-1.5 rounded bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition">
                                        Periksa (SOAP)
                                    </a>
                                    <button type="button" 
                                            onclick="bukaModalSembuh(<?= $p['kunjungan_id']; ?>, '<?= addslashes($p['nama_lengkap']); ?>', '<?= addslashes($p['nis']); ?>', '<?= addslashes($p['keluhan_utama']); ?>')"
                                            class="px-2.5 py-1.5 rounded border border-[#DCE7E0] hover:bg-emerald-50 text-[#16845B] font-semibold text-xs transition">
                                        Selesai / Sembuh
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="py-8 text-center text-[#64756D]">
                <p class="text-sm font-semibold text-[#0B3D2E]">Tidak ada antrean pasien yang menunggu saat ini.</p>
                <p class="text-xs text-[#64756D] mt-0.5">Seluruh santri yang terdaftar telah selesai diperiksa dan mendapatkan tindakan.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================== -->
        <!-- TABEL RIWAYAT PASIEN SELESAI / SEMBUH HARI INI                 -->
        <!-- ============================================================== -->
        <div id="pasien-sembuh" class="card-panel p-4 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E]">Pasien Selesai Ditangani Hari Ini</h2>
                    <p class="text-xs text-[#64756D]">Catatan santri yang telah dinyatakan selesai pemeriksaan klinis atau sembuh dari rawat inap.</p>
                </div>
                <span class="text-xs mono-num font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <?= $sembuh_hari_ini; ?> Santri Selesai
                </span>
            </div>

            <?php if ($res_sembuh && $res_sembuh->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3">No. Antrean</th>
                            <th class="py-2.5 px-3">Nama Santri</th>
                            <th class="py-2.5 px-3">Asrama</th>
                            <th class="py-2.5 px-3">Diagnosis Klinis</th>
                            <th class="py-2.5 px-3">Catatan / Pernyataan Medis</th>
                            <th class="py-2.5 px-3">Waktu Selesai</th>
                            <th class="py-2.5 px-3 text-right">Rekam Medis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                        <?php while ($sb = $res_sembuh->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-2.5 px-3 mono-num font-semibold text-[#64756D]"><?= htmlspecialchars($sb['no_antrean'] ?: 'ANT-'.$sb['kunjungan_id']); ?></td>
                            <td class="py-2.5 px-3 font-bold text-[#0B3D2E]"><?= htmlspecialchars($sb['nama_lengkap']); ?></td>
                            <td class="py-2.5 px-3 text-[#64756D]"><?= htmlspecialchars($sb['kamar_asrama'] ?? '-'); ?></td>
                            <td class="py-2.5 px-3 font-medium text-[#182923]"><?= htmlspecialchars($sb['diagnosa_dokter'] ?: 'Pemeriksaan umum'); ?></td>
                            <td class="py-2.5 px-3 max-w-[260px] text-[11px] text-[#64756D]">
                                <?= htmlspecialchars($sb['catatan_dokter'] ?: 'Pasien telah pulih dan diperbolehkan kembali beraktivitas.'); ?>
                            </td>
                            <td class="py-2.5 px-3 mono-num text-[11px] text-[#64756D]">
                                <?= $sb['tanggal_selesai'] ? date('H:i', strtotime($sb['tanggal_selesai'])) . ' WIB' : '-'; ?>
                            </td>
                            <td class="py-2.5 px-3 text-right">
                                <a href="rekam_medis.php?kunjungan_id=<?= $sb['kunjungan_id']; ?>&action=lihat" 
                                   class="text-[#16845B] hover:text-[#0B3D2E] font-semibold text-xs inline-flex items-center gap-1">
                                    <span>Lihat EMR</span>
                                    <span>→</span>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="py-6 text-center text-[#64756D]">
                <p class="text-xs">Belum ada pasien yang berstatus selesai pada hari ini.</p>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ============================================================== -->
    <!-- FOOTER                                                         -->
    <!-- ============================================================== -->
    <footer class="mt-auto bg-white border-t border-[#DCE7E0] py-4 text-xs text-[#64756D]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</span>
                <span>— Ruang Poli Medis & Sistem Informasi Klinis Santri</span>
            </div>
            <div>&copy; <?= date('Y'); ?> Lembaga Pengembangan Dakwah Al-Bahjah.</div>
        </div>
    </footer>

    <!-- ============================================================== -->
    <!-- MODAL PENETAPAN PASIEN SEMBUH                                  -->
    <!-- ============================================================== -->
    <div id="modal-sembuh" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-4 border border-[#DCE7E0] shadow-xl">
            <div class="flex items-center justify-between pb-2.5 border-b border-[#DCE7E0]">
                <h3 class="font-bold text-sm text-[#0B3D2E]">Konfirmasi Pasien Pulih / Selesai</h3>
                <button type="button" onclick="tutupModalSembuh()" class="text-[#64756D] hover:text-black font-bold">&times;</button>
            </div>

            <form method="POST" action="dashboard_dokter.php" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="tetapkan_sembuh">
                <input type="hidden" name="kunjungan_id" id="sembuh-kunjungan-id">

                <div class="p-3 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] space-y-0.5">
                    <span class="text-[10px] uppercase font-bold text-[#64756D]">Identitas Santri:</span>
                    <div class="font-bold text-[#0B3D2E] text-sm" id="sembuh-nama-santri">-</div>
                    <div class="text-[11px] text-[#64756D]">
                        NIS: <span id="sembuh-nis" class="mono-num font-semibold text-[#182923]">-</span> • 
                        <span id="sembuh-keluhan" class="truncate">-</span>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Catatan Medis Keluar / Pulang:</label>
                    <textarea name="catatan_dokter" id="sembuh-catatan" rows="3" required 
                              class="w-full p-2.5 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] focus:outline-none focus:ring-1 focus:ring-[#16845B] text-xs leading-relaxed" 
                              placeholder="Kondisi fisik telah stabil dan keluhan membaik. Santri dinyatakan pulih dan diperbolehkan kembali mengikuti KBM serta kegiatan asrama."></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="tutupModalSembuh()" class="px-3.5 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] font-semibold">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-[#16845B] hover:bg-[#0B3D2E] text-white font-semibold transition">
                        Simpan & Selesaikan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Handlers -->
    <script>
        function filterTriase(tier) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('btn-filter-' + tier);
            if (activeBtn) activeBtn.classList.add('active');

            const rows = document.querySelectorAll('.baris-pasien');
            rows.forEach(row => {
                if (tier === 'all' || row.dataset.triase === tier) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function bukaModalSembuh(kunjunganId, nama, nis, keluhan) {
            document.getElementById('sembuh-kunjungan-id').value = kunjunganId;
            document.getElementById('sembuh-nama-santri').textContent = nama;
            document.getElementById('sembuh-nis').textContent = nis;
            document.getElementById('sembuh-keluhan').textContent = keluhan;
            document.getElementById('sembuh-catatan').value = "Kondisi fisik telah stabil dan keluhan membaik. Santri dinyatakan pulih dan diperbolehkan kembali mengikuti KBM serta kegiatan asrama.";
            document.getElementById('modal-sembuh').classList.remove('hidden');
        }

        function tutupModalSembuh() {
            document.getElementById('modal-sembuh').classList.add('hidden');
        }
    </script>
</body>
</html>
