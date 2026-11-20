<?php
// wali.php - Portal Informasi & Pemantauan Kesehatan Santri Khusus Wali Santri Poskestren Al-Bahjah Bogor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';
require_db();

// Proteksi akses: Wajib login
if (!isset($_SESSION['user_id'])) {
    set_flash('error', "Silakan masuk terlebih dahulu untuk mengakses Portal Wali Santri.");
    header("Location: login.php");
    exit();
}

// Jika login sebagai admin / petugas, arahkan ke dashboard internal
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'santri_wali') {
    if ($_SESSION['role'] === 'petugas_kesehatan') {
        header("Location: dashboard_dokter.php");
    } elseif ($_SESSION['role'] === 'petugas_farmasi') {
        header("Location: dashboard_farmasi.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'santri_wali';
$user_name = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? 'Wali Santri');
$user_hp   = $_SESSION['no_hp'] ?? '';

// 1. Ambil seluruh data santri yang diasuh oleh wali ini (Mendukung wali dengan >1 santri)
$daftar_anak_wali = [];
$stmt_anak = $conn->prepare("SELECT * FROM santri WHERE user_id_wali = ? OR no_hp_wali = ? OR nis = ? ORDER BY nama_lengkap ASC");
$stmt_anak->bind_param("iss", $user_id, $user_hp, $_SESSION['username']);
$stmt_anak->execute();
$res_anak = $stmt_anak->get_result();
while ($row_a = $res_anak->fetch_assoc()) {
    $daftar_anak_wali[$row_a['id']] = $row_a;
}

// 2. Identifikasi santri yang sedang aktif dipilih
$santri_data = null;
$nis_cari    = trim($_GET['nis'] ?? '');
$santri_id_pilih = isset($_GET['santri_id']) ? (int)$_GET['santri_id'] : 0;

if (!empty($nis_cari)) {
    // Pencarian manual via NIS
    $stmt_c = $conn->prepare("SELECT * FROM santri WHERE nis = ? LIMIT 1");
    $stmt_c->bind_param("s", $nis_cari);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    if ($res_c->num_rows > 0) {
        $santri_data = $res_c->fetch_assoc();
    }
} elseif ($santri_id_pilih > 0 && isset($daftar_anak_wali[$santri_id_pilih])) {
    $santri_data = $daftar_anak_wali[$santri_id_pilih];
} elseif (!empty($daftar_anak_wali)) {
    // Default ambil anak pertama dari wali
    $santri_data = reset($daftar_anak_wali);
}

// Jika santri ditemukan, kumpulkan ID anak untuk kueri status sakit
$wali_santri_ids = array_keys($daftar_anak_wali);
if ($santri_data && !in_array($santri_data['id'], $wali_santri_ids)) {
    $wali_santri_ids[] = $santri_data['id'];
}

$antrean_aktif      = null;
$klinis_aktif       = null;
$resep_detail_aktif = [];
$rawat_aktif        = null;
$riwayat_kunjungan  = [];

if ($santri_data) {
    $sid = (int)$santri_data['id'];

    // Ambil antrean aktif terbaru (yang belum selesai / sedang dirawat / dalam antrean)
    $stmt_a = $conn->prepare("SELECT * FROM kunjungan_sakit 
                              WHERE santri_id = ? 
                              AND status_antrean IN ('menunggu_triase', 'pemeriksaan', 'observasi', 'rawat_inap', 'rawat_jalan')
                              ORDER BY id DESC LIMIT 1");
    $stmt_a->bind_param("i", $sid);
    $stmt_a->execute();
    $res_aktif = $stmt_a->get_result();
    if ($res_aktif->num_rows > 0) {
        $antrean_aktif = $res_aktif->fetch_assoc();
        $kid = (int)$antrean_aktif['id'];

        // Detail klinis / TTV dokter
        $stmt_k = $conn->prepare("SELECT * FROM pemeriksaan_klinis WHERE kunjungan_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_k->bind_param("i", $kid);
        $stmt_k->execute();
        $klinis_aktif = $stmt_k->get_result()->fetch_assoc();

        // Detail obat yang diresepkan
        $stmt_ob = $conn->prepare("SELECT rod.*, o.nama_obat, o.satuan, r.status_resep, r.catatan_resep 
                                   FROM resep_obat r 
                                   JOIN resep_obat_detail rod ON rod.resep_id = r.id 
                                   JOIN obat o ON o.id = rod.obat_id 
                                   WHERE r.kunjungan_id = ?");
        $stmt_ob->bind_param("i", $kid);
        $stmt_ob->execute();
        $res_ob = $stmt_ob->get_result();
        while ($ro = $res_ob->fetch_assoc()) {
            $resep_detail_aktif[] = $ro;
        }

        // Detail rawat inap jika ada
        $stmt_rw = $conn->prepare("SELECT * FROM rawat_inap WHERE kunjungan_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_rw->bind_param("i", $kid);
        $stmt_rw->execute();
        $rawat_aktif = $stmt_rw->get_result()->fetch_assoc();
    }

    // Riwayat kunjungan medis sebelumnya
    $stmt_r = $conn->prepare("SELECT k.*, pk.diagnosa_dokter, pk.rekomendasi_rawat, pk.suhu_tubuh, pk.tekanan_darah, r.status_resep 
                              FROM kunjungan_sakit k 
                              LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                              LEFT JOIN resep_obat r ON r.kunjungan_id = k.id 
                              WHERE k.santri_id = ? 
                              ORDER BY k.id DESC LIMIT 8");
    $stmt_r->bind_param("i", $sid);
    $stmt_r->execute();
    $res_r = $stmt_r->get_result();
    while ($row = $res_r->fetch_assoc()) {
        $riwayat_kunjungan[] = $row;
    }
}

// 3. Kueri ananda milik wali yang saat ini sedang dalam penanganan medis
$daftar_ananda_sakit = [];
if (!empty($wali_santri_ids)) {
    $ids_str = implode(',', array_map('intval', $wali_santri_ids));
    $sql_sakit = "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama, s.nama_wali, s.no_hp_wali,
                         pk.diagnosa_dokter, pk.rekomendasi_rawat, pk.suhu_tubuh, pk.tekanan_darah, pk.keluhan_fisik,
                         r.status_resep
                  FROM kunjungan_sakit k
                  JOIN santri s ON s.id = k.santri_id
                  LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
                  LEFT JOIN resep_obat r ON r.kunjungan_id = k.id
                  WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan', 'observasi', 'rawat_inap', 'rawat_jalan')
                  AND s.id IN ($ids_str)
                  ORDER BY k.id DESC";
    $res_sakit = $conn->query($sql_sakit);
    if ($res_sakit) {
        while ($row_sk = $res_sakit->fetch_assoc()) {
            $daftar_ananda_sakit[] = $row_sk;
        }
    }
}

// 4. Data antrean umum poskestren untuk transparansi santri yang sedang dirawat
$antrean_umum_poskestren = [];
$res_umum = $conn->query("SELECT k.id, k.no_antrean, k.tingkat_prioritas, k.status_antrean, k.tanggal_berobat, k.keluhan_utama,
                                 s.nis, s.kamar_asrama, s.kompleks_asrama,
                                 CONCAT(LEFT(s.nama_lengkap, 3), '***') AS nama_samaran,
                                 pk.suhu_tubuh, pk.diagnosa_dokter
                          FROM kunjungan_sakit k
                          JOIN santri s ON s.id = k.santri_id
                          LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
                          WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan', 'observasi', 'rawat_inap')
                          ORDER BY CASE k.tingkat_prioritas 
                              WHEN 'P1_Gawat_Darurat' THEN 1 
                              WHEN 'P2_Mendesak' THEN 2 
                              ELSE 3 END ASC, k.skor_saw_akhir DESC, k.id DESC
                          LIMIT 6");
if ($res_umum) {
    while ($ru = $res_umum->fetch_assoc()) {
        $antrean_umum_poskestren[] = $ru;
    }
}

// 5. Data Tim Nakes / Shift Jaga Hari Ini
$tim_jaga = [];
$res_jaga = $conn->query("SELECT * FROM shift_jaga ORDER BY id ASC");
if ($res_jaga) {
    while ($rj = $res_jaga->fetch_assoc()) {
        $tim_jaga[$rj['posisi']] = $rj;
    }
}

// Nama wali yang ditampilkan
$nama_wali_display = !empty($santri_data['nama_wali']) ? $santri_data['nama_wali'] : $user_name;

// Helper: Link WhatsApp Otomatis dengan template pesan santun & lengkap
function buat_url_wa_wali($nama, $nis, $kamar, $keluhan, $prioritas, $skor, $status) {
    $prioritas_txt = str_replace(['P1_Gawat_Darurat', 'P2_Mendesak', 'P3_Biasa'], ['P1 (Gawat Darurat)', 'P2 (Mendesak)', 'P3 (Biasa / Stabil)'], $prioritas);
    $status_txt    = ucwords(str_replace('_', ' ', $status));
    $skor_txt      = number_format((float)$skor, 3);

    $pesan = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
           . "Yth. Petugas Siaga Medis Poskestren Al-Bahjah Bogor,\n\n"
           . "Saya adalah orang tua / wali dari ananda santri:\n"
           . "• Nama Santri : " . $nama . "\n"
           . "• NIS : " . $nis . "\n"
           . "• Asrama : " . $kamar . "\n\n"
           . "Berdasarkan pantauan Portal Wali Santri, ananda tercatat sedang sakit dengan keterangan:\n"
           . "• Keluhan Utama : " . ($keluhan ?: 'Dalam pemantauan') . "\n"
           . "• Prioritas Triase : " . $prioritas_txt . " (Skor SAW: " . $skor_txt . ")\n"
           . "• Status Penanganan : " . $status_txt . "\n\n"
           . "Mohon izin bertanya mengenai perkembangan kondisi kesehatan ananda saat ini dan arahan lebih lanjut dari dokter / nakes jaga. Terima kasih atas dedikasi dan perawatannya kepada para santri.\n\n"
           . "Jazakumullahu Khairan Katsiran,\nWassalamu'alaikum Wr. Wb.";

    return "https://api.whatsapp.com/send?phone=6281122334455&text=" . rawurlencode($pesan);
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Wali Santri — Poskestren Al-Bahjah Bogor</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'deep-green': '#0B3D2E',
                        'primary-green': '#16845B',
                        'light-green': '#E8F5EE',
                        'warm-yellow': '#F4C542',
                        'emergency-red': '#D64545',
                        'app-bg': '#F5F8F6',
                        'card-bg': '#FFFFFF',
                        'border-color': '#DCE7E0',
                        'text-main': '#182923',
                        'text-muted': '#64756D',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #F5F8F6;
            color: #182923;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #F5F8F6;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #DCE7E0;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #16845B;
        }
        /* Soft pulse indicator */
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 0.4; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .pulse-ring {
            animation: pulse-ring 2.5s infinite ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-app-bg text-text-main selection:bg-primary-green selection:text-white">

    <!-- MAIN HEADER & STICKY NAVIGATION -->
    <!-- ========================================== -->
    <header class="bg-card-bg border-b border-border-color sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                
                <!-- Logo & Brand Identity -->
                <div class="flex items-center gap-3">
                    <a href="wali.php" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-deep-green to-primary-green text-white flex items-center justify-center font-bold text-base shadow-sm group-hover:scale-105 transition">
                            AB
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-sm sm:text-base text-deep-green tracking-tight leading-none">
                                    Portal Wali Santri
                                </span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-light-green text-primary-green border border-emerald-200">
                                    Resmi
                                </span>
                            </div>
                            <p class="text-[11px] text-text-muted mt-0.5 leading-none">
                                Poskestren Al-Bahjah Cabang Bogor
                            </p>
                        </div>
                    </a>
                </div>

                <!-- Desktop In-Page Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 text-xs font-semibold text-text-muted">
                    <a href="#ananda" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Profil Ananda</a>
                    <a href="#status-klinis" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Kondisi & TTV</a>
                    <a href="#riwayat-medis" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Riwayat Berobat</a>
                    <a href="#jadwal-nakes" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Tim Jaga</a>
                    <a href="#edukasi-saw" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Alur Triase SAW</a>
                    <a href="#faq-wali" class="px-3 py-2 rounded-lg hover:text-deep-green hover:bg-slate-100 transition">Panduan & FAQ</a>
                </nav>

                <!-- User Profile & Action Area -->
                <div class="flex items-center gap-3">
                    <!-- Dropdown Pilihan Ananda (Jika memiliki >1 santri terdaftar) -->
                    <?php if (count($daftar_anak_wali) > 1): ?>
                    <div class="relative hidden sm:block">
                        <form method="GET" action="wali.php">
                            <select name="santri_id" onchange="this.form.submit()" 
                                    class="text-xs font-semibold bg-app-bg border border-border-color rounded-lg px-2.5 py-1.5 text-deep-green focus:outline-none focus:ring-1 focus:ring-primary-green cursor-pointer">
                                <?php foreach ($daftar_anak_wali as $ak_id => $ak_data): ?>
                                <option value="<?= $ak_id; ?>" <?= ($santri_data && $santri_data['id'] == $ak_id) ? 'selected' : ''; ?>>
                                    Ananda: <?= htmlspecialchars($ak_data['nama_lengkap']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Info Akun Wali & Tombol Keluar -->
                    <div class="flex items-center gap-2 pl-2 border-l border-border-color">
                        <div class="text-right hidden sm:block">
                            <span class="block text-xs font-bold text-deep-green leading-tight"><?= htmlspecialchars($nama_wali_display); ?></span>
                            <span class="block text-[10px] text-text-muted">Akun Wali Terverifikasi</span>
                        </div>
                        <a href="logout.php" 
                           onclick="return confirm('Apakah Ayah / Bunda yakin ingin keluar dari Portal Wali?')"
                           class="hidden sm:flex p-2 sm:px-3 sm:py-1.5 rounded-lg border border-border-color hover:border-red-200 hover:bg-red-50 hover:text-emergency-red text-xs font-semibold text-text-muted transition items-center gap-1.5"
                           title="Keluar dari sistem">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span>Keluar</span>
                        </a>
                        <!-- Mobile Hamburger Button -->
                        <button type="button" onclick="const mw = document.getElementById('mobile-nav-wali'); mw.classList.toggle('hidden');" 
                                class="md:hidden p-2 rounded-lg text-deep-green hover:bg-slate-100 transition flex items-center justify-center" aria-label="Menu">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Dropdown Menu -->
        <div id="mobile-nav-wali" class="hidden md:hidden px-4 py-3 border-t border-border-color bg-white space-y-1.5">
            <a href="#ananda" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Profil Ananda</a>
            <a href="#status-klinis" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Kondisi &amp; TTV</a>
            <a href="#riwayat-medis" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Riwayat Berobat</a>
            <a href="#jadwal-nakes" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Tim Jaga</a>
            <a href="#edukasi-saw" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Alur Triase SAW</a>
            <a href="#faq-wali" class="block px-3 py-2 rounded-lg text-deep-green hover:bg-slate-100 font-semibold text-xs">Panduan &amp; FAQ</a>
            
            <?php if (count($daftar_anak_wali) > 1): ?>
            <div class="pt-2 border-t border-border-color">
                <span class="text-[11px] font-bold text-text-muted block mb-1">Ganti Pilihan Ananda:</span>
                <form method="GET" action="wali.php">
                    <select name="santri_id" onchange="this.form.submit()" 
                            class="w-full text-xs font-semibold bg-app-bg border border-border-color rounded-lg px-2.5 py-2 text-deep-green">
                        <?php foreach ($daftar_anak_wali as $ak_id => $ak_data): ?>
                        <option value="<?= $ak_id; ?>" <?= ($santri_data && $santri_data['id'] == $ak_id) ? 'selected' : ''; ?>>
                            Ananda: <?= htmlspecialchars($ak_data['nama_lengkap']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?>

            <div class="pt-2 mt-1 border-t border-border-color flex items-center justify-between px-1">
                <div>
                    <span class="block text-xs font-bold text-deep-green"><?= htmlspecialchars($nama_wali_display); ?></span>
                    <span class="block text-[10px] text-text-muted">Akun Wali</span>
                </div>
                <a href="logout.php" onclick="return confirm('Apakah Ayah / Bunda yakin ingin keluar dari Portal Wali?')" class="px-2.5 py-1 text-xs font-bold text-red-600 bg-red-50 rounded-md border border-red-200">
                    Keluar
                </a>
            </div>
        </div>
    </header>

    <!-- ========================================== -->
    <!-- 3. BANNER PERINGATAN MEDIS JIKA SAKIT      -->
    <!-- ========================================== -->
    <?php if (!empty($daftar_ananda_sakit)): ?>
    <div class="bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3.5 w-3.5 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-amber-300"></span>
                    </span>
                    <div>
                        <strong class="font-extrabold uppercase tracking-wide">Pemberitahuan Medis Khusus Ayah / Bunda:</strong>
                        <span class="ml-1 text-rose-100">
                            Terdapat <strong><?= count($daftar_ananda_sakit); ?> ananda</strong> Anda yang sedang dalam penanganan rawat / antrean triase di Poskestren Al-Bahjah.
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="#status-klinis" class="px-3.5 py-1.5 rounded-lg bg-white text-emergency-red font-extrabold text-xs hover:bg-rose-50 transition shadow-xs flex items-center gap-1.5">
                        <span>Pantau Kondisi Sekarang</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Flash message jika ada -->
    <?php 
    $flash_sukses = get_flash('success');
    $flash_gagal  = get_flash('error');
    ?>
    <?php if ($flash_sukses): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-primary-green text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span><?= htmlspecialchars($flash_sukses); ?></span>
            </div>
        </div>
    </div>
    <?php elseif ($flash_gagal): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="p-4 rounded-xl border bg-red-50 border-red-200 text-emergency-red text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span><?= htmlspecialchars($flash_gagal); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- 4. HERO SECTION & IDENTITAS KELUARGA       -->
    <!-- ========================================== -->
    <section class="bg-white border-b border-border-color py-8 sm:py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                <!-- Kolom Kiri: Sambutan Hangat & Pengantar Manusiawi (7 Cols) -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-light-green text-primary-green text-xs font-bold border border-emerald-200">
                        <svg class="w-4 h-4 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <span>Amanah Kesehatan Santri Pesantren</span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-deep-green tracking-tight leading-tight">
                        Bismillah, Pantau Kesehatan Ananda dengan Hati Tenang & Transparan
                    </h1>

                    <p class="text-xs sm:text-sm text-text-muted leading-relaxed">
                        Assalamu’alaikum Warahmatullahi Wabarakatuh, Ayah / Bunda <strong><?= htmlspecialchars($nama_wali_display); ?></strong>. 
                        Melalui portal terpadu ini, Ayah Bunda dapat melihat langsung rekam perkembangan medis ananda, hasil pemeriksaan dokter, instruksi resep obat, 
                        hingga mengunduh Surat Izin Istirahat Belajar secara resmi.
                    </p>

                    <!-- Fast Selector & NIS Search Bar -->
                    <div class="pt-2">
                        <form method="GET" action="wali.php" class="p-2 rounded-2xl bg-app-bg border border-border-color flex flex-col sm:flex-row items-center gap-2 shadow-2xs">
                            <div class="relative flex-1 w-full">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="nis" value="<?= htmlspecialchars($nis_cari); ?>" 
                                       placeholder="Cari NIS ananda (Contoh: 2024-AB-0192)..." 
                                       class="w-full pl-10 pr-3 py-2.5 rounded-xl bg-white text-xs font-mono font-bold text-text-main placeholder-slate-400 border border-border-color focus:outline-none focus:ring-2 focus:ring-primary-green focus:border-transparent transition">
                            </div>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-primary-green hover:bg-deep-green text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-xs flex-shrink-0">
                                <span>Cari Data Santri</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </button>
                        </form>

                        <!-- Pilihan Cepat Santri Terdaftar -->
                        <?php if (!empty($daftar_anak_wali)): ?>
                        <div class="flex flex-wrap items-center gap-2 mt-2.5 text-[11px] text-text-muted">
                            <span class="font-medium">Ananda Terdaftar:</span>
                            <?php foreach ($daftar_anak_wali as $child): ?>
                            <a href="wali.php?santri_id=<?= $child['id']; ?>#ananda" 
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border <?= ($santri_data && $santri_data['id'] == $child['id']) ? 'bg-primary-green text-white border-primary-green font-bold' : 'bg-white text-deep-green border-border-color hover:bg-emerald-50'; ?> transition">
                                <span><?= htmlspecialchars($child['nama_lengkap']); ?></span>
                                <span class="text-[10px] font-mono opacity-80">(<?= $child['nis']; ?>)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kolom Kanan: Card Ringkasan Nakes Siaga & Jadwal Hari Ini (5 Cols) -->
                <div class="lg:col-span-5 bg-gradient-to-br from-app-bg to-emerald-50/40 border border-border-color rounded-2xl p-5 space-y-4 shadow-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-border-color">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-text-muted tracking-wider block">Jadwal & Fasilitas</span>
                            <h3 class="text-sm font-extrabold text-deep-green">Poskestren Al-Bahjah Hari Ini</h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                            24 Jam Aktif
                        </span>
                    </div>

                    <!-- Ringkasan Info Operasional -->
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1 border-b border-border-color/60">
                            <span class="text-text-muted flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Poli Santri Putra:</span>
                            </span>
                            <span class="font-bold text-deep-green">08.00 - 12.00 WIB</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-border-color/60">
                            <span class="text-text-muted flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Poli Santri Putri:</span>
                            </span>
                            <span class="font-bold text-deep-green">13.30 - 17.00 WIB</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-border-color/60">
                            <span class="text-text-muted flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <span>Dokter Jaga:</span>
                            </span>
                            <span class="font-bold text-deep-green">
                                <?= htmlspecialchars($tim_jaga['dokter']['nama'] ?? 'dr. H. Ahmad Fauzan, Sp.A'); ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-text-muted flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>Hotline Darurat Pesantren:</span>
                            </span>
                            <a href="tel:081122334455" class="font-mono font-extrabold text-emergency-red hover:underline">0811-2233-4455</a>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="pt-2 flex flex-col sm:flex-row gap-2">
                        <a href="https://wa.me/6281122334455?text=Assalamu%27alaikum%20Warahmatullahi%20Wabarakatuh%20Poskestren%20Al-Bahjah,%20saya%20wali%20santri%20ingin%20berkonsultasi..." 
                           target="_blank" 
                           class="flex-1 py-2.5 px-3 rounded-xl bg-primary-green hover:bg-deep-green text-white font-bold text-xs transition text-center shadow-xs flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.539 1.761.815 2.791.815 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.59-5.767-5.768-5.767zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.493-5.029-1.353l-6.971 1.821 1.864-6.804c-.958-1.547-1.493-3.344-1.493-5.224 0-5.514 4.486-10 10-10 5.514 0 10 4.486 10 10z"/></svg>
                            <span>WhatsApp Poskestren</span>
                        </a>
                        <a href="#faq-wali" class="py-2.5 px-3 rounded-xl bg-white border border-border-color text-deep-green hover:bg-slate-50 font-bold text-xs transition text-center">
                            Panduan Wali
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- 5. KARTU IDENTITAS & DATA MEDIS ANANDA    -->
    <!-- ========================================== -->
    <?php if ($santri_data): ?>
    <section id="ananda" class="py-8 bg-app-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Header Kartu Santri -->
            <div class="bg-white rounded-3xl border border-border-color p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-border-color gap-6">
                    
                    <!-- Avatar & Biodata Santri -->
                    <div class="flex items-start sm:items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-deep-green to-primary-green text-white font-black text-2xl flex items-center justify-center shadow-md flex-shrink-0">
                            <?= strtoupper(substr($santri_data['nama_lengkap'], 0, 1)); ?>
                        </div>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-xl sm:text-2xl font-black text-deep-green tracking-tight">
                                    <?= htmlspecialchars($santri_data['nama_lengkap']); ?>
                                </h2>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-light-green text-primary-green border border-emerald-200">
                                    NIS: <?= htmlspecialchars($santri_data['nis']); ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $santri_data['jenis_kelamin'] === 'L' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-pink-50 text-pink-700 border border-pink-200'; ?>">
                                    <?= $santri_data['jenis_kelamin'] === 'L' ? 'Santri Putra' : 'Santri Putri'; ?>
                                </span>
                            </div>
                            <p class="text-xs text-text-muted flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Asrama: <strong class="text-deep-green"><?= htmlspecialchars($santri_data['kamar_asrama'] ?? '-'); ?></strong></span>
                                <span>•</span>
                                <span>Kompleks: <strong class="text-deep-green"><?= htmlspecialchars($santri_data['kompleks_asrama'] ?? '-'); ?></strong></span>
                                <span>•</span>
                                <span>Wali Terdata: <strong class="text-deep-green"><?= htmlspecialchars($santri_data['nama_wali'] ?? '-'); ?></strong></span>
                            </p>
                        </div>
                    </div>

                    <!-- Status Kondisi Medis Real-Time -->
                    <div class="bg-app-bg p-4 rounded-2xl border border-border-color text-left md:text-right min-w-[220px]">
                        <span class="text-[10px] uppercase font-bold text-text-muted tracking-wider block">Status Kesehatan Saat Ini:</span>
                        <div class="mt-1">
                            <?php if ($antrean_aktif): ?>
                                <?= badge_triase($antrean_aktif['tingkat_prioritas'], $antrean_aktif['skor_saw_akhir']); ?>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Alhamdulillah Sehat Wal 'Afiat</span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[10px] text-text-muted block mt-1.5">
                            <?= $antrean_aktif ? 'Sedang dalam penanganan medis' : 'Aktif mengikuti kegiatan KBM asrama'; ?>
                        </span>
                    </div>

                </div>

                <!-- ============================================== -->
                <!-- JIKA ANANDA SEDANG SAKIT: DETAIL PENANGANAN   -->
                <!-- ============================================== -->
                <?php if ($antrean_aktif): ?>
                <div id="status-klinis" class="mt-6 pt-2 space-y-6">
                    
                    <!-- STEPPER TRACKER 5 TAHAP ALUR PENANGANAN -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-emerald-50/70 via-teal-50/40 to-slate-50 border border-emerald-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-primary-green tracking-wider block">Pelacak Penanganan Klinis</span>
                                <h3 class="text-sm sm:text-base font-extrabold text-deep-green">Tahapan Pelayanan Medis Ananda</h3>
                            </div>
                            <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-lg bg-white border border-border-color text-deep-green">
                                No. Antrean: <?= htmlspecialchars($antrean_aktif['no_antrean'] ?? 'ANT-001'); ?>
                            </span>
                        </div>

                        <?php
                        // Hitung tahapan stepper
                        $st = $antrean_aktif['status_antrean'];
                        $step = 1;
                        if ($st === 'menunggu_triase') $step = 2;
                        elseif ($st === 'pemeriksaan') $step = 3;
                        elseif ($st === 'rawat_jalan' || $st === 'observasi' || $st === 'rawat_inap') $step = 4;
                        elseif ($st === 'selesai') $step = 5;
                        ?>

                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                            <!-- Step 1 -->
                            <div class="p-3 rounded-xl <?= $step >= 1 ? 'bg-white border-2 border-primary-green shadow-2xs' : 'bg-slate-100/60 border border-border-color text-slate-400'; ?>">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 rounded-full <?= $step >= 1 ? 'bg-primary-green text-white' : 'bg-slate-300 text-slate-600'; ?> text-[10px] font-black flex items-center justify-center">1</span>
                                    <span class="font-bold text-xs text-deep-green">Lapor Asrama</span>
                                </div>
                                <p class="text-[11px] text-text-muted leading-tight">Pengantaran santri ke Poskestren</p>
                            </div>
                            <!-- Step 2 -->
                            <div class="p-3 rounded-xl <?= $step >= 2 ? 'bg-white border-2 border-primary-green shadow-2xs' : 'bg-slate-100/60 border border-border-color text-slate-400'; ?>">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 rounded-full <?= $step >= 2 ? 'bg-primary-green text-white' : 'bg-slate-300 text-slate-600'; ?> text-[10px] font-black flex items-center justify-center">2</span>
                                    <span class="font-bold text-xs text-deep-green">Skrining SAW</span>
                                </div>
                                <p class="text-[11px] text-text-muted leading-tight">Ukur prioritas klinis & TTV awal</p>
                            </div>
                            <!-- Step 3 -->
                            <div class="p-3 rounded-xl <?= $step >= 3 ? 'bg-white border-2 border-primary-green shadow-2xs' : 'bg-slate-100/60 border border-border-color text-slate-400'; ?>">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 rounded-full <?= $step >= 3 ? 'bg-primary-green text-white' : 'bg-slate-300 text-slate-600'; ?> text-[10px] font-black flex items-center justify-center">3</span>
                                    <span class="font-bold text-xs text-deep-green">Pemeriksaan Dokter</span>
                                </div>
                                <p class="text-[11px] text-text-muted leading-tight">Diagnosis & tindakan medis pertama</p>
                            </div>
                            <!-- Step 4 -->
                            <div class="p-3 rounded-xl <?= $step >= 4 ? 'bg-white border-2 border-primary-green shadow-2xs' : 'bg-slate-100/60 border border-border-color text-slate-400'; ?>">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 rounded-full <?= $step >= 4 ? 'bg-primary-green text-white' : 'bg-slate-300 text-slate-600'; ?> text-[10px] font-black flex items-center justify-center">4</span>
                                    <span class="font-bold text-xs text-deep-green">Farmasi & Resep</span>
                                </div>
                                <p class="text-[11px] text-text-muted leading-tight">Penyiapan obat & aturan minum</p>
                            </div>
                            <!-- Step 5 -->
                            <div class="p-3 rounded-xl <?= $step >= 5 ? 'bg-white border-2 border-primary-green shadow-2xs' : 'bg-slate-100/60 border border-border-color text-slate-400'; ?>">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-5 h-5 rounded-full <?= $step >= 5 ? 'bg-primary-green text-white' : 'bg-slate-300 text-slate-600'; ?> text-[10px] font-black flex items-center justify-center">5</span>
                                    <span class="font-bold text-xs text-deep-green">Observasi / Pulih</span>
                                </div>
                                <p class="text-[11px] text-text-muted leading-tight">Pemulihan santri & kembali KBM</p>
                            </div>
                        </div>
                    </div>

                    <!-- MONITOR TANDA VITAL (TTV) & KONDISI FISIK -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-extrabold text-deep-green uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Hasil Pemeriksaan Tanda-Tanda Vital (TTV)</span>
                            </h3>
                            <span class="text-[11px] text-text-muted">
                                Waktu Periksa: <?= tgl_indo($antrean_aktif['tanggal_berobat']); ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <!-- Suhu Tubuh -->
                            <?php 
                            $suhu = (float)($klinis_aktif['suhu_tubuh'] ?? 0);
                            $suhu_badge = $suhu >= 38.5 ? 'bg-red-50 text-emergency-red border-red-200' : ($suhu >= 37.5 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-primary-green border-emerald-200');
                            ?>
                            <div class="p-4 rounded-2xl bg-white border border-border-color shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-text-muted block">Suhu Tubuh</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-deep-green font-mono">
                                        <?= $suhu > 0 ? number_format($suhu, 1) : '-'; ?>
                                    </span>
                                    <span class="text-xs text-text-muted font-bold">°C</span>
                                </div>
                                <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold border <?= $suhu_badge; ?>">
                                    <?= $suhu >= 38.5 ? 'Demam Tinggi' : ($suhu >= 37.5 ? 'Subfebris / Demam' : 'Suhu Normal'); ?>
                                </span>
                            </div>

                            <!-- Tekanan Darah -->
                            <div class="p-4 rounded-2xl bg-white border border-border-color shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-text-muted block">Tekanan Darah</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-deep-green font-mono">
                                        <?= htmlspecialchars(!empty($klinis_aktif['tekanan_darah']) ? $klinis_aktif['tekanan_darah'] : '110/70'); ?>
                                    </span>
                                    <span class="text-xs text-text-muted font-bold">mmHg</span>
                                </div>
                                <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    Tensi Terkontrol
                                </span>
                            </div>

                            <!-- Nadi & Respirasi -->
                            <div class="p-4 rounded-2xl bg-white border border-border-color shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-text-muted block">Denyut Nadi</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-deep-green font-mono">
                                        <?= (int)($klinis_aktif['nadi'] ?? 82); ?>
                                    </span>
                                    <span class="text-xs text-text-muted font-bold">bpm</span>
                                </div>
                                <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-primary-green border border-emerald-200">
                                    Irama Reguler
                                </span>
                            </div>

                            <!-- Saturasi Oksigen (SpO2) -->
                            <div class="p-4 rounded-2xl bg-white border border-border-color shadow-2xs">
                                <span class="text-[10px] uppercase font-bold text-text-muted block">Saturasi Oksigen (SpO2)</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-deep-green font-mono">
                                        <?= (int)($klinis_aktif['saturasi_oksigen'] ?? 98); ?>
                                    </span>
                                    <span class="text-xs text-text-muted font-bold">%</span>
                                </div>
                                <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-primary-green border border-emerald-200">
                                    Oksigenasi Baik
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- KELUHAN, DIAGNOSIS DOKTER & TINDAKAN -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Keluhan Awal -->
                        <div class="p-4 rounded-2xl bg-app-bg border border-border-color space-y-1.5">
                            <span class="text-[10px] uppercase font-bold text-text-muted block">Keluhan Utama Ananda</span>
                            <p class="text-xs font-bold text-deep-green leading-relaxed">
                                "<?= htmlspecialchars($antrean_aktif['keluhan_utama'] ?: 'Mengeluh tidak enak badan dan demam'); ?>"
                            </p>
                            <span class="text-[10px] text-text-muted block mt-2">
                                Skrining Triase SAW: <strong>Skor <?= number_format($antrean_aktif['skor_saw_akhir'], 3); ?></strong>
                            </span>
                        </div>

                        <!-- Diagnosis Dokter -->
                        <div class="p-4 rounded-2xl bg-app-bg border border-border-color space-y-1.5">
                            <span class="text-[10px] uppercase font-bold text-text-muted block">Diagnosis Klinis Dokter</span>
                            <p class="text-xs font-bold text-deep-green leading-relaxed">
                                <?= htmlspecialchars($klinis_aktif['diagnosa_dokter'] ?? ($antrean_aktif['catatan_dokter'] ?? 'Dalam pemeriksaan / observasi dokter')); ?>
                            </p>
                            <span class="text-[10px] text-primary-green font-semibold block mt-2">
                                Dokter: <?= htmlspecialchars($antrean_aktif['dokter_pemeriksa'] ?? 'dr. H. Ahmad Fauzan, Sp.A'); ?>
                            </span>
                        </div>

                        <!-- Rekomendasi Rawat & Surat Izin -->
                        <div class="p-4 rounded-2xl bg-light-green/60 border border-emerald-200 flex flex-col justify-between">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-primary-green block">Rekomendasi Tindakan</span>
                                <h4 class="text-xs font-extrabold text-deep-green mt-0.5">
                                    <?= htmlspecialchars($klinis_aktif['rekomendasi_rawat'] ?? ($antrean_aktif['status_antrean'] === 'rawat_inap' ? 'Rawat Inap Poskestren' : 'Rawat Jalan di Asrama')); ?>
                                </h4>
                                <p class="text-[11px] text-text-muted mt-1 leading-snug">
                                    Santri dianjurkan beristirahat untuk mempercepat pemulihan dan mencegah penularan di kamar asrama.
                                </p>
                            </div>
                            <div class="pt-3">
                                <a href="rekam_medis.php?kunjungan_id=<?= $antrean_aktif['id']; ?>&view=detail" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-deep-green hover:bg-primary-green text-white font-bold text-xs transition shadow-2xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Cetak Surat Izin Sakit</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- RINCIAN RESEP OBAT YANG DIBERIKAN DOKTER -->
                    <?php if (!empty($resep_detail_aktif)): ?>
                    <div class="p-5 rounded-2xl bg-white border border-border-color space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-extrabold text-deep-green uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-primary-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                <span>Resep Obat & Terapi Medis dari Farmasi</span>
                            </h4>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                Disiapkan Farmasi Poskestren
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-app-bg text-deep-green font-bold border-b border-border-color">
                                        <th class="py-2 px-3">Nama Obat</th>
                                        <th class="py-2 px-3 text-center">Jumlah</th>
                                        <th class="py-2 px-3">Aturan Minum & Dosis</th>
                                        <th class="py-2 px-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border-color">
                                    <?php foreach ($resep_detail_aktif as $r_item): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2.5 px-3 font-bold text-deep-green">
                                            <?= htmlspecialchars($r_item['nama_obat']); ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-mono text-text-muted">
                                            <?= (int)$r_item['jumlah']; ?> <?= htmlspecialchars($r_item['satuan'] ?? 'Tablet'); ?>
                                        </td>
                                        <td class="py-2.5 px-3 font-semibold text-text-main">
                                            <?= htmlspecialchars($r_item['dosis_instruksi'] ?: 'Sesuai instruksi nakes'); ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                <?= ucfirst(str_replace('_', ' ', $r_item['status_resep'] ?? 'Diberikan')); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- FAST ACTION: HUBUNGI NAKES & WHATSAPP OTOMATIS -->
                    <div class="p-5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-md">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center flex-shrink-0 text-white border border-white/20">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.539 1.761.815 2.791.815 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.59-5.767-5.768-5.767zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.493-5.029-1.353l-6.971 1.821 1.864-6.804c-.958-1.547-1.493-3.344-1.493-5.224 0-5.514 4.486-10 10-10 5.514 0 10 4.486 10 10z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-white text-sm sm:text-base">Ingin Mengetahui Perkembangan Ananda Langsung?</h4>
                                <p class="text-xs text-emerald-100">
                                    Hubungi tim nakes siaga poskestren dengan pesan WhatsApp yang telah disiapkan otomatis.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 w-full sm:w-auto flex-shrink-0">
                            <a href="<?= buat_url_wa_wali($santri_data['nama_lengkap'], $santri_data['nis'], $santri_data['kamar_asrama'], $antrean_aktif['keluhan_utama'], $antrean_aktif['tingkat_prioritas'], $antrean_aktif['skor_saw_akhir'], $antrean_aktif['status_antrean']); ?>" 
                               target="_blank" 
                               class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-white hover:bg-emerald-50 text-deep-green font-extrabold text-xs transition shadow-sm flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.539 1.761.815 2.791.815 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.59-5.767-5.768-5.767zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.493-5.029-1.353l-6.971 1.821 1.864-6.804c-.958-1.547-1.493-3.344-1.493-5.224 0-5.514 4.486-10 10-10 5.514 0 10 4.486 10 10z"/></svg>
                                <span>Kirim Pesan WhatsApp</span>
                            </a>
                            <a href="tel:081122334455" class="px-3.5 py-2.5 rounded-xl bg-emerald-800/80 hover:bg-emerald-800 text-white font-bold text-xs transition border border-emerald-500/40" title="Panggilan Telepon GSM">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- ============================================== -->
                <!-- JIKA ANANDA SEHAT: LAPORAN KEBUGARAN TENANG    -->
                <!-- ============================================== -->
                <?php else: ?>
                <div class="mt-6 pt-2">
                    <div class="p-6 sm:p-8 rounded-3xl bg-gradient-to-br from-light-green/80 via-emerald-50/50 to-white border border-emerald-200">
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div class="w-16 h-16 rounded-2xl bg-primary-green text-white flex items-center justify-center flex-shrink-0 shadow-md">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="space-y-1.5 text-center sm:text-left flex-1">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-emerald-200 text-emerald-900 inline-block">
                                    Laporan Kebugaran Santri
                                </span>
                                <h3 class="text-lg sm:text-xl font-extrabold text-deep-green">
                                    Alhamdulillah, Ananda <?= htmlspecialchars($santri_data['nama_lengkap']); ?> Saat Ini dalam Keadaan Sehat
                                </h3>
                                <p class="text-xs sm:text-sm text-text-muted leading-relaxed max-w-2xl">
                                    Tidak tercatat adanya keluhan sakit, rawat inap, ataupun antrean triase medis di Poskestren Al-Bahjah. 
                                    Ananda saat ini mengikuti seluruh agenda KBM formal, halaqah tahfidz Al-Qur'an, dan kegiatan harian di kamar asrama dengan baik.
                                </p>
                            </div>
                        </div>

                        <!-- Doa & Tips Kesehatan Pesantren -->
                        <div class="mt-6 pt-6 border-t border-emerald-200/60 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-100">
                                <span class="font-bold text-deep-green block mb-1">Makan & Gizi Terjaga</span>
                                <p class="text-text-muted leading-tight">Dapur pesantren menyediakan menu bergizi seimbang 3x sehari dengan pengawasan sanitasi rutin.</p>
                            </div>
                            <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-100">
                                <span class="font-bold text-deep-green block mb-1">Piket Kamar Bersih</span>
                                <p class="text-text-muted leading-tight">Pembina asrama membimbing santri menjaga sirkulasi udara kamar dan kebersihan ranjang.</p>
                            </div>
                            <div class="bg-white/80 p-3.5 rounded-xl border border-emerald-100">
                                <span class="font-bold text-deep-green block mb-1">Doa Perlindungan Harian</span>
                                <p class="text-text-muted leading-tight">Santri senantiasa dibiasakan membaca wirid & doa perlindungan (Ratib Al-Haddad) setiap ba'da Ashar.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- ============================================== -->
            <!-- 6. RIWAYAT BEROBAT & REKAM MEDIS ANANDA        -->
            <!-- ============================================== -->
            <div id="riwayat-medis" class="bg-white rounded-3xl border border-border-color p-6 sm:p-8 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-border-color gap-2">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-primary-green tracking-wider block">Catatan Medis Terdahulu</span>
                        <h3 class="text-base sm:text-lg font-black text-deep-green">
                            Riwayat Kunjungan & Pemeriksaan Ananda
                        </h3>
                    </div>
                    <span class="text-xs text-text-muted">
                        Total Kunjungan Terdata: <strong><?= count($riwayat_kunjungan); ?> kali</strong>
                    </span>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-app-bg text-deep-green font-bold border-b border-border-color">
                                <th class="py-3 px-3.5">Tanggal Periksa</th>
                                <th class="py-3 px-3.5">Keluhan Utama</th>
                                <th class="py-3 px-3.5">Diagnosis Dokter</th>
                                <th class="py-3 px-3.5 text-center">TTV (Suhu/Tensi)</th>
                                <th class="py-3 px-3.5 text-center">Tindakan / Rawat</th>
                                <th class="py-3 px-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-color text-text-main">
                            <?php if (!empty($riwayat_kunjungan)): ?>
                                <?php foreach ($riwayat_kunjungan as $rk): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-3.5 font-mono text-text-muted whitespace-nowrap">
                                        <?= tgl_indo($rk['tanggal_berobat'], true); ?>
                                    </td>
                                    <td class="py-3 px-3.5 max-w-[220px] font-medium">
                                        <?= htmlspecialchars($rk['keluhan_utama']); ?>
                                    </td>
                                    <td class="py-3 px-3.5 font-bold text-deep-green">
                                        <?= htmlspecialchars($rk['diagnosa_dokter'] ?: 'Pemeriksaan umum santri'); ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-center font-mono text-xs">
                                        <?= $rk['suhu_tubuh'] ? $rk['suhu_tubuh'] . '°C' : '-'; ?> / <?= htmlspecialchars($rk['tekanan_darah'] ?: '-'); ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-light-green text-primary-green border border-emerald-200">
                                            <?= htmlspecialchars($rk['rekomendasi_rawat'] ?: 'Rawat Jalan'); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3.5 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $rk['status_antrean'] === 'selesai' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                                            <?= str_replace('_', ' ', $rk['status_antrean']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-text-muted">
                                        <div class="max-w-xs mx-auto space-y-1">
                                            <p class="font-bold text-deep-green">Belum Ada Riwayat Kunjungan Sakit</p>
                                            <p class="text-[11px]">Alhamdulillah, ananda belum pernah tercatat memiliki keluhan sakit serius di Poskestren.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- 7. TRANSPARANSI ANTREAN MEDIS POSKESTREN   -->
    <!-- ========================================== -->
    <section class="py-12 bg-white border-y border-border-color">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="px-2.5 py-1 rounded-full bg-light-green text-primary-green text-[10px] font-extrabold uppercase tracking-wider inline-block mb-1 border border-emerald-200">
                        Transparansi Layanan Medis
                    </span>
                    <h2 class="text-xl sm:text-2xl font-black text-deep-green tracking-tight">
                        Live Monitor Antrean & Perawatan di Poskestren
                    </h2>
                    <p class="text-xs text-text-muted mt-1">
                        Informasi kondisi santri yang sedang dirawat disamarkan demi menjaga privasi medis ananda.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-app-bg text-deep-green border border-border-color">
                        <span class="w-2 h-2 rounded-full bg-primary-green animate-ping"></span>
                        <span><?= count($antrean_umum_poskestren); ?> Santri dalam Rawat</span>
                    </span>
                </div>
            </div>

            <!-- Tabel Antrean Aktif -->
            <div class="bg-app-bg rounded-2xl border border-border-color overflow-hidden shadow-2xs">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-white text-deep-green font-extrabold border-b border-border-color">
                                <th class="py-3 px-4">No. Antrean</th>
                                <th class="py-3 px-4">Identitas Santri</th>
                                <th class="py-3 px-4">Asrama</th>
                                <th class="py-3 px-4">Tingkat Triase SAW</th>
                                <th class="py-3 px-4">Keluhan Utama</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-color text-text-main">
                            <?php if (!empty($antrean_umum_poskestren)): ?>
                                <?php foreach ($antrean_umum_poskestren as $au): ?>
                                <tr class="hover:bg-white/80 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-deep-green">
                                        <?= htmlspecialchars($au['no_antrean']); ?>
                                    </td>
                                    <td class="py-3 px-4 font-semibold">
                                        <?= htmlspecialchars($au['nama_samaran']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-text-muted">
                                        <?= htmlspecialchars($au['kamar_asrama'] ?? '-'); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?= badge_triase($au['tingkat_prioritas']); ?>
                                    </td>
                                    <td class="py-3 px-4 max-w-[200px] truncate text-text-muted">
                                        <?= htmlspecialchars($au['keluhan_utama']); ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-100 text-blue-800">
                                            <?= str_replace('_', ' ', $au['status_antrean']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-text-muted">
                                        Alhamdulillah, tidak ada santri yang sedang dirawat inap saat ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- 8. TIM MEDIS & JADWAL SHIFT JAGA           -->
    <!-- ========================================== -->
    <section id="jadwal-nakes" class="py-14 bg-app-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <span class="px-3 py-1 rounded-full bg-light-green text-primary-green text-xs font-bold border border-emerald-200">
                    Tenaga Kesehatan Profesional
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-deep-green tracking-tight">
                    Tim Medis Siaga Poskestren Al-Bahjah
                </h2>
                <p class="text-xs sm:text-sm text-text-muted leading-relaxed">
                    Setiap hari, dokter spesialis/umum berizin, perawat triase, dan apoteker bersinergi memastikan keselamatan santri secara terpadu.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card Dokter -->
                <div class="bg-white rounded-3xl border border-border-color p-6 shadow-xs hover:border-primary-green transition">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-primary-green flex items-center justify-center font-bold text-2xl shadow-2xs">
                            🩺
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-primary-green block">Dokter Penanggung Jawab</span>
                            <h3 class="font-extrabold text-base text-deep-green leading-snug">
                                <?= htmlspecialchars($tim_jaga['dokter']['nama'] ?? 'dr. H. Ahmad Fauzan, Sp.A'); ?>
                            </h3>
                            <span class="text-[11px] text-text-muted">Pemeriksaan Klinis & Terapi Medis</span>
                        </div>
                    </div>
                    <p class="text-xs text-text-muted leading-relaxed">
                        Menangani diagnosis penyakit, pemeriksaan tanda vital mendalam, serta memutuskan tindakan rawat jalan, observasi, atau rujukan rumah sakit.
                    </p>
                </div>

                <!-- Card Perawat Triase -->
                <div class="bg-white rounded-3xl border border-border-color p-6 shadow-xs hover:border-primary-green transition">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-2xl shadow-2xs">
                            📋
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-blue-700 block">Perawat Siaga Triase</span>
                            <h3 class="font-extrabold text-base text-deep-green leading-snug">
                                <?= htmlspecialchars($tim_jaga['perawat']['nama'] ?? 'Ns. Hendra Pratama, S.Kep'); ?>
                            </h3>
                            <span class="text-[11px] text-text-muted">Skrining SAW & UGD 24 Jam</span>
                        </div>
                    </div>
                    <p class="text-xs text-text-muted leading-relaxed">
                        Melakukan anamnesis awal, pengecekan suhu & tensi santri, kalkulasi bobot SAW, dan pertolongan pertama pada kondisi gawat darurat.
                    </p>
                </div>

                <!-- Card Apoteker -->
                <div class="bg-white rounded-3xl border border-border-color p-6 shadow-xs hover:border-primary-green transition">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-2xl shadow-2xs">
                            💊
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-amber-700 block">Apoteker Poskestren</span>
                            <h3 class="font-extrabold text-base text-deep-green leading-snug">
                                <?= htmlspecialchars($tim_jaga['farmasi']['nama'] ?? 'Apt. Rahmat Hidayat, S.Farm'); ?>
                            </h3>
                            <span class="text-[11px] text-text-muted">Pengelolaan Obat & Dosis Santri</span>
                        </div>
                    </div>
                    <p class="text-xs text-text-muted leading-relaxed">
                        Menyiapkan obat sesuai resep dokter, memastikan tanggal kadaluarsa aman, serta memberikan instruksi aturan minum yang tepat bagi santri.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- 9. EDUKASI TRIASE METODE SAW               -->
    <!-- ========================================== -->
    <section id="edukasi-saw" class="py-14 bg-white border-t border-border-color">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <span class="px-3 py-1 rounded-full bg-light-green text-primary-green text-xs font-bold border border-emerald-200">
                    Sistem Pendukung Keputusan (SPK) Medis
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-deep-green tracking-tight">
                    Mengapa Antrean Ditentukan Menggunakan Metode SAW?
                </h2>
                <p class="text-xs sm:text-sm text-text-muted leading-relaxed">
                    Di Poskestren Al-Bahjah, antrean penanganan medis tidak semata-mata berdasarkan urutan siapa yang datang lebih dulu, melainkan 
                    <strong>tingkat kegawatan klinis santri secara objektif</strong> untuk mencegah risiko komplikasi darurat.
                </p>
            </div>

            <!-- 5 Kriteria SAW Medis -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="p-4 rounded-2xl bg-app-bg border border-border-color">
                    <span class="w-7 h-7 rounded-lg bg-deep-green text-white text-xs font-black flex items-center justify-center mb-2">C1</span>
                    <h4 class="font-bold text-xs text-deep-green">Suhu Tubuh (30%)</h4>
                    <p class="text-[11px] text-text-muted mt-1 leading-snug">Demam tinggi di atas 39°C diprioritaskan demi mencegah kejang demam.</p>
                </div>

                <div class="p-4 rounded-2xl bg-app-bg border border-border-color">
                    <span class="w-7 h-7 rounded-lg bg-deep-green text-white text-xs font-black flex items-center justify-center mb-2">C2</span>
                    <h4 class="font-bold text-xs text-deep-green">Skala Nyeri (25%)</h4>
                    <p class="text-[11px] text-text-muted mt-1 leading-snug">Nyeri akut hebat seperti kolik abdomen atau cedera fraktur.</p>
                </div>

                <div class="p-4 rounded-2xl bg-app-bg border border-border-color">
                    <span class="w-7 h-7 rounded-lg bg-deep-green text-white text-xs font-black flex items-center justify-center mb-2">C3</span>
                    <h4 class="font-bold text-xs text-deep-green">Riwayat Komorbid (20%)</h4>
                    <p class="text-[11px] text-text-muted mt-1 leading-snug">Santri dengan riwayat asma kambuhan atau alergi berat.</p>
                </div>

                <div class="p-4 rounded-2xl bg-app-bg border border-border-color">
                    <span class="w-7 h-7 rounded-lg bg-deep-green text-white text-xs font-black flex items-center justify-center mb-2">C4</span>
                    <h4 class="font-bold text-xs text-deep-green">Durasi Sakit (15%)</h4>
                    <p class="text-[11px] text-text-muted mt-1 leading-snug">Kecepatan timbulnya gejala akut mendadak dibandingkan keluhan berkala.</p>
                </div>

                <div class="p-4 rounded-2xl bg-app-bg border border-border-color">
                    <span class="w-7 h-7 rounded-lg bg-deep-green text-white text-xs font-black flex items-center justify-center mb-2">C5</span>
                    <h4 class="font-bold text-xs text-deep-green">Tanda Vital (10%)</h4>
                    <p class="text-[11px] text-text-muted mt-1 leading-snug">Tingkat kesadaran santri (GCS) dan kecukupan saturasi oksigen.</p>
                </div>
            </div>

            <!-- Emergency Override Box -->
            <div class="p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-emergency-red text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                        🚨
                    </span>
                    <div>
                        <strong class="font-extrabold block">Prosedur Emergency Override (Bypass SAW):</strong>
                        <p class="text-rose-800 leading-snug">
                            Apabila santri mengalami henti napas, syok anafilaksis, atau penurunan kesadaran tiba-tiba, tim dokter berhak melakukan penanganan instan tanpa menunggu kalkulasi skor matematis.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- 10. TANYA JAWAB (FAQ) & PANDUAN WALI       -->
    <!-- ========================================== -->
    <section id="faq-wali" class="py-14 bg-app-bg border-t border-border-color">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="text-center space-y-2">
                <span class="px-3 py-1 rounded-full bg-light-green text-primary-green text-xs font-bold border border-emerald-200">
                    Panduan Praktis
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-deep-green tracking-tight">
                    Tanya Jawab Seputar Layanan Santri
                </h2>
                <p class="text-xs sm:text-sm text-text-muted">
                    Jawaban resmi atas hal-hal yang paling sering ditanyakan oleh para wali santri.
                </p>
            </div>

            <div class="space-y-3">
                <!-- FAQ 1 -->
                <div class="bg-white rounded-2xl border border-border-color overflow-hidden shadow-2xs">
                    <button type="button" onclick="toggleFaq('faq-1')" class="w-full p-5 text-left font-bold text-xs sm:text-sm text-deep-green flex items-center justify-between gap-3">
                        <span>Apakah ada biaya tambahan untuk pemeriksaan dokter dan obat di Poskestren?</span>
                        <svg id="icon-faq-1" class="w-4 h-4 text-primary-green transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="faq-1" class="hidden px-5 pb-5 text-xs text-text-muted leading-relaxed border-t border-border-color/60 pt-3">
                        Seluruh layanan pemeriksaan nakes, konsultasi dokter penanggung jawab, serta obat-obatan esensial standar poskestren 
                        <strong>telah terfasilitasi dan gratis</strong> bagi seluruh santri aktif Pondok Pesantren Al-Bahjah. Biaya hanya timbul apabila diperlukan tindakan spesialis lanjutan atau rawat di Rumah Sakit Luar rujukan.
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="bg-white rounded-2xl border border-border-color overflow-hidden shadow-2xs">
                    <button type="button" onclick="toggleFaq('faq-2')" class="w-full p-5 text-left font-bold text-xs sm:text-sm text-deep-green flex items-center justify-between gap-3">
                        <span>Bagaimana jika ananda memiliki riwayat alergi obat atau obat rutin dari dokter keluarga?</span>
                        <svg id="icon-faq-2" class="w-4 h-4 text-primary-green transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="faq-2" class="hidden px-5 pb-5 text-xs text-text-muted leading-relaxed border-t border-border-color/60 pt-3">
                        Ayah Bunda dimohon menitipkan obat rutin beserta salinan resep dokter keluarga kepada bagian Farmasi Poskestren melalui pembina asrama. Data alergi ananda dicatat ke dalam database kriteria SAW agar tidak ada obat kontraindikasi yang diberikan.
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="bg-white rounded-2xl border border-border-color overflow-hidden shadow-2xs">
                    <button type="button" onclick="toggleFaq('faq-3')" class="w-full p-5 text-left font-bold text-xs sm:text-sm text-deep-green flex items-center justify-between gap-3">
                        <span>Apakah orang tua diperkenankan menjenguk jika ananda dirawat inap di Poskestren?</span>
                        <svg id="icon-faq-3" class="w-4 h-4 text-primary-green transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="faq-3" class="hidden px-5 pb-5 text-xs text-text-muted leading-relaxed border-t border-border-color/60 pt-3">
                        Demi ketenangan pemulihan santri, kunjungan dijadwalkan secara tertib pada jam besuk medis (10.00 - 12.00 WIB dan 16.00 - 18.00 WIB) dengan izin perawat siaga. Wali dapat melakukan video call terfasilitasi melalui Hotline WhatsApp Poskestren.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- 11. FOOTER RESMI POSKESTREN AL-BAHJAH      -->
    <!-- ========================================== -->
    <footer class="mt-auto bg-deep-green text-white border-t border-emerald-950 py-10 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 pb-6 border-b border-emerald-900/60">
                <div class="flex items-center gap-3 text-center md:text-left">
                    <div class="w-10 h-10 rounded-xl bg-primary-green text-white font-bold flex items-center justify-center text-sm shadow-xs">
                        AB
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-white">Pos Kesehatan Pesantren (Poskestren) Al-Bahjah</h4>
                        <p class="text-[11px] text-emerald-200">Cabang Bogor — Lembaga Pengembangan Dakwah Al-Bahjah</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-4 text-emerald-200 text-xs">
                    <a href="#ananda" class="hover:text-white transition">Data Ananda</a>
                    <span>•</span>
                    <a href="#status-klinis" class="hover:text-white transition">Status Medis</a>
                    <span>•</span>
                    <a href="#jadwal-nakes" class="hover:text-white transition">Jadwal Nakes</a>
                    <span>•</span>
                    <a href="#faq-wali" class="hover:text-white transition">FAQ Wali</a>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-emerald-300">
                <p>&copy; <?= date('Y'); ?> Poskestren Al-Bahjah Bogor. Sistem Pendukung Keputusan Triase Medis SAW.</p>
                <p>Hotline Medis 24 Jam: <strong>0811-2233-4455</strong></p>
            </div>
        </div>
    </footer>

    <!-- ============================================================== -->
    <!-- 12. LIVE CHAT CS WIDGET (TERINTEGRASI api_chat.php)           -->
    <!-- ============================================================== -->
    <div class="fixed bottom-6 right-6 z-50">
        <button id="wali-chat-btn" onclick="toggleWaliChat()"
            class="w-14 h-14 bg-primary-green hover:bg-deep-green text-white rounded-full flex items-center justify-center shadow-2xl transition-all hover:scale-105 relative group">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span id="wali-chat-badge" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-emergency-red rounded-full border-2 border-white text-[10px] font-bold flex items-center justify-center">!</span>
        </button>
    </div>

    <!-- Live Chat Floating Window -->
    <div id="wali-chat-box" class="hidden fixed bottom-24 right-6 z-50 w-80 sm:w-96 bg-white rounded-3xl shadow-2xl border border-border-color overflow-hidden flex-col"
         style="max-height:540px; display:none;">

        <!-- Header Chat -->
        <div class="bg-deep-green p-4 text-white flex-shrink-0">
            <div class="flex items-center gap-2 mb-2.5">
                <button onclick="waliSwitchTab('chat')" id="wali-tab-chat"
                    class="flex-1 py-1.5 text-xs font-bold rounded-xl bg-primary-green text-white transition">
                    💬 Chat CS Medis
                </button>
                <button onclick="waliSwitchTab('profil')" id="wali-tab-profil"
                    class="flex-1 py-1.5 text-xs font-bold rounded-xl text-emerald-300 hover:bg-white/10 transition">
                    👤 Profil Saya
                </button>
                <button onclick="toggleWaliChat()" class="text-emerald-200 hover:text-white ml-1 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-primary-green flex items-center justify-center flex-shrink-0 shadow-2xs">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <div class="text-xs font-extrabold text-white">CS Poskestren Al-Bahjah</div>
                    <div class="flex items-center gap-1.5 text-[10px] text-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block animate-pulse"></span>
                        Petugas Siaga Melayani
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Messages Container -->
        <div id="wali-chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 text-xs bg-app-bg custom-scrollbar" style="min-height:220px; max-height:280px;">
            <div class="flex items-start gap-2">
                <div class="w-7 h-7 bg-primary-green text-white rounded-full flex items-center justify-center flex-shrink-0 text-[10px] font-bold">CS</div>
                <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-border-color max-w-[85%] shadow-2xs">
                    <p class="font-bold text-primary-green mb-0.5 text-[11px]">Petugas Poskestren</p>
                    Assalamu’alaikum, Ayah / Bunda <strong><?= htmlspecialchars($user_name); ?></strong>. Ada yang bisa kami bantu terkait kondisi kesehatan ananda?
                    <span class="block text-[9px] text-text-muted mt-1"><?= date('H:i'); ?> WIB</span>
                </div>
            </div>
        </div>

        <!-- Panel Profil Saya -->
        <div id="wali-profil-panel" class="hidden flex-1 overflow-y-auto p-4 text-xs bg-white custom-scrollbar" style="max-height:280px;">
            <div class="text-center mb-4">
                <div class="w-14 h-14 bg-deep-green text-white rounded-2xl flex items-center justify-center font-bold text-xl mx-auto mb-2 shadow-xs">
                    <?= strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div class="font-bold text-sm text-deep-green"><?= htmlspecialchars($user_name); ?></div>
                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                    Akun Wali Santri
                </span>
            </div>

            <div class="space-y-2 text-xs">
                <div class="p-2.5 bg-app-bg rounded-xl border border-border-color">
                    <span class="text-[10px] text-text-muted uppercase block">Username / No. HP</span>
                    <strong class="text-deep-green font-mono"><?= htmlspecialchars($_SESSION['username'] ?? '-'); ?></strong>
                </div>
                <?php if ($santri_data): ?>
                <div class="p-2.5 bg-light-green rounded-xl border border-emerald-200">
                    <span class="text-[10px] text-primary-green uppercase font-bold block">Ananda Terkait</span>
                    <strong class="text-deep-green block"><?= htmlspecialchars($santri_data['nama_lengkap']); ?> (<?= $santri_data['nis']; ?>)</strong>
                    <span class="text-[11px] text-text-muted">Kamar: <?= htmlspecialchars($santri_data['kamar_asrama'] ?? '-'); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick reply suggestions -->
        <div class="px-3 py-2 bg-white border-t border-border-color flex gap-2 overflow-x-auto text-[11px] flex-shrink-0 custom-scrollbar">
            <button onclick="waliQuickMsg('Bagaimana kondisi ananda saya saat ini?')"
                class="px-2.5 py-1.5 bg-app-bg border border-border-color hover:bg-emerald-50 hover:text-primary-green text-text-main rounded-xl whitespace-nowrap transition font-semibold">
                Status ananda
            </button>
            <button onclick="waliQuickMsg('Kapan jadwal jam besuk santri di poskestren?')"
                class="px-2.5 py-1.5 bg-app-bg border border-border-color hover:bg-emerald-50 hover:text-primary-green text-text-main rounded-xl whitespace-nowrap transition font-semibold">
                Jam besuk
            </button>
            <button onclick="waliQuickMsg('Obat apa saja yang diberikan dokter ke ananda?')"
                class="px-2.5 py-1.5 bg-app-bg border border-border-color hover:bg-emerald-50 hover:text-primary-green text-text-main rounded-xl whitespace-nowrap transition font-semibold">
                Info obat
            </button>
        </div>

        <!-- Input Chat -->
        <form id="wali-chat-form" onsubmit="waliSendMsg(event)" class="p-3 bg-white border-t border-border-color flex gap-2 flex-shrink-0">
            <input type="text" id="wali-chat-input" placeholder="Tulis pesan untuk nakes..."
                class="flex-1 text-xs border border-border-color rounded-xl px-3 py-2.5 focus:outline-none focus:border-primary-green bg-app-bg focus:bg-white transition"
                required>
            <button type="submit" class="bg-primary-green hover:bg-deep-green text-white p-2.5 rounded-xl transition flex items-center justify-center shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            </button>
        </form>
    </div>

    <!-- ============================================================== -->
    <!-- JAVASCRIPT LOGIC & FAQ ACCORDION                               -->
    <!-- ============================================================== -->
    <script>
        function toggleFaq(id) {
            const el = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                if (icon) icon.classList.add('rotate-180');
            } else {
                el.classList.add('hidden');
                if (icon) icon.classList.remove('rotate-180');
            }
        }

        // Live Chat Logic
        const WALI_SESSION_KEY = 'wali_<?= (int)$user_id; ?>';
        const WALI_NAMA        = <?= json_encode($user_name); ?>;
        let waliChatOpen       = false;
        let waliLastId         = 0;
        let waliPollTimer      = null;

        function toggleWaliChat() {
            const box = document.getElementById('wali-chat-box');
            waliChatOpen = !waliChatOpen;
            if (waliChatOpen) {
                box.style.display = 'flex';
                box.classList.remove('hidden');
                document.getElementById('wali-chat-badge').classList.add('hidden');
                loadWaliHistory();
                startWaliPolling();
            } else {
                box.style.display = 'none';
                box.classList.add('hidden');
            }
        }

        function waliSwitchTab(tab) {
            const chatMsg  = document.getElementById('wali-chat-messages');
            const profil   = document.getElementById('wali-profil-panel');
            const quickRep = document.querySelector('#wali-chat-box .px-3.py-2.bg-white.border-t');
            const form     = document.getElementById('wali-chat-form');
            const btnChat  = document.getElementById('wali-tab-chat');
            const btnProf  = document.getElementById('wali-tab-profil');

            if (tab === 'chat') {
                chatMsg.classList.remove('hidden');
                profil.classList.add('hidden');
                if (quickRep) quickRep.classList.remove('hidden');
                form.classList.remove('hidden');
                btnChat.classList.add('bg-primary-green', 'text-white');
                btnChat.classList.remove('text-emerald-300');
                btnProf.classList.remove('bg-primary-green', 'text-white');
                btnProf.classList.add('text-emerald-300');
            } else {
                chatMsg.classList.add('hidden');
                profil.classList.remove('hidden');
                if (quickRep) quickRep.classList.add('hidden');
                form.classList.add('hidden');
                btnProf.classList.add('bg-primary-green', 'text-white');
                btnProf.classList.remove('text-emerald-300');
                btnChat.classList.remove('bg-primary-green', 'text-white');
                btnChat.classList.add('text-emerald-300');
            }
        }

        function loadWaliHistory() {
            fetch(`api_chat.php?action=thread&session_key=${encodeURIComponent(WALI_SESSION_KEY)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.ok || !data.messages || data.messages.length === 0) return;
                const container = document.getElementById('wali-chat-messages');
                container.innerHTML = '';
                data.messages.forEach(m => {
                    const time = new Date(m.created_at).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
                    if (m.dari_cs == 1) {
                        waliAppendCS(container, m.pesan, time);
                    } else {
                        waliAppendUser(container, m.pesan, time);
                    }
                    waliLastId = Math.max(waliLastId, m.id);
                });
                container.scrollTop = container.scrollHeight;
            }).catch(e => console.log('Chat fetch error:', e));
        }

        function waliSendMsg(e) {
            e.preventDefault();
            const input     = document.getElementById('wali-chat-input');
            const container = document.getElementById('wali-chat-messages');
            const text      = input.value.trim();
            if (!text) return;

            waliAppendUser(container, text);
            input.value = '';

            fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'send', pesan: text, session_key: WALI_SESSION_KEY, nama: WALI_NAMA })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) waliLastId = Math.max(waliLastId, data.id);
            }).catch(e => console.log('Chat send error:', e));
        }

        function waliQuickMsg(text) {
            document.getElementById('wali-chat-input').value = text;
            document.getElementById('wali-chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
        }

        function startWaliPolling() {
            if (waliPollTimer) return;
            waliPollTimer = setInterval(waliPollReplies, 5000);
        }

        function waliPollReplies() {
            fetch(`api_chat.php?action=poll&session_key=${encodeURIComponent(WALI_SESSION_KEY)}&last_id=${waliLastId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.ok || !data.messages || data.messages.length === 0) return;
                const container = document.getElementById('wali-chat-messages');
                data.messages.forEach(m => {
                    waliAppendCS(container, m.pesan, m.time);
                    waliLastId = Math.max(waliLastId, m.id);
                    if (!waliChatOpen) {
                        const badge = document.getElementById('wali-chat-badge');
                        badge.classList.remove('hidden');
                        badge.textContent = '!';
                    }
                });
            }).catch(e => console.log('Chat poll error:', e));
        }

        function waliAppendUser(container, text, time) {
            const t = time || new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
            container.insertAdjacentHTML('beforeend', `
                <div class="flex justify-end">
                    <div class="bg-primary-green text-white p-3 rounded-2xl rounded-tr-none max-w-[85%] text-xs shadow-2xs">
                        ${waliEsc(text)}
                        <span class="block text-[9px] text-emerald-200 mt-1 text-right">${t}</span>
                    </div>
                </div>`);
            container.scrollTop = container.scrollHeight;
        }

        function waliAppendCS(container, text, time) {
            const t = time || new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start gap-2">
                    <div class="w-7 h-7 bg-primary-green text-white rounded-full flex items-center justify-center flex-shrink-0 text-[10px] font-bold">CS</div>
                    <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-border-color max-w-[85%] text-xs shadow-2xs">
                        <p class="font-bold text-primary-green mb-0.5 text-[11px]">Petugas Poskestren</p>
                        ${waliEsc(text)}
                        <span class="block text-[9px] text-text-muted mt-1">${t}</span>
                    </div>
                </div>`);
            container.scrollTop = container.scrollHeight;
        }

        function waliEsc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    </script>

</body>
</html>
