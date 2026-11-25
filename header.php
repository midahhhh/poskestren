<?php
// header.php - Layout Shell & Sidebar Navigasi Poskestren Al-Bahjah Bogor
ob_start(); // Buffer output agar header() redirect bisa dipanggil kapan saja

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';
require_db();

// Proteksi Autentikasi
if (!isset($_SESSION['user_id'])) {
    set_flash('error', "Silakan login terlebih dahulu untuk mengakses halaman ini.");
    header("Location: login_petugas.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = $_SESSION['role'] ?? 'guest';
$user_name    = $_SESSION['nama_lengkap'] ?? 'Pengguna';

// Deteksi apakah user ini adalah dokter (untuk label & akses)
$is_dokter = ($user_role === 'admin') || (
    $user_role === 'petugas_kesehatan' &&
    (stripos($user_name, 'dr.') !== false ||
     stripos($user_name, 'sp.') !== false ||
     stripos($user_name, 'm.biomed') !== false ||
     stripos($user_name, 'm.kes') !== false)
);

// RBAC
if ($user_role === 'santri_wali') {
    header("Location: wali.php"); exit();
}

// Hanya admin yang boleh buka dashboard utama (dashboard.php)
if ($current_page === 'dashboard.php') {
    if ($user_role === 'petugas_farmasi') {
        header("Location: dashboard_farmasi.php"); exit();
    } elseif ($user_role === 'petugas_kesehatan') {
        header("Location: dashboard_dokter.php"); exit();
    }
}

// rekam_medis_admin.php KHUSUS ADMIN (Dokter diarahkan ke rekam_medis.php)
if ($current_page === 'rekam_medis_admin.php' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Halaman Rekam Medis Pasien ini khusus Admin.");
    header("Location: " . ($user_role === 'petugas_kesehatan' ? 'rekam_medis.php' : 'login_petugas.php')); exit();
}

// farmasi.php KHUSUS ADMIN (Apoteker diarahkan ke dashboard_farmasi.php)
if ($current_page === 'farmasi.php' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Modul Farmasi utama khusus Admin. Silakan kelola resep melalui Dashboard Farmasi.");
    header("Location: dashboard_farmasi.php"); exit();
}

// dashboard_farmasi.php KHUSUS APOTEKER / FARMASI & ADMIN
if ($current_page === 'dashboard_farmasi.php' && !in_array($user_role, ['petugas_farmasi', 'admin'])) {
    set_flash('error', "Akses Ditolak: Halaman ini khusus Farmasi & Apoteker.");
    header("Location: login_petugas.php"); exit();
}

// dokter.php KHUSUS ADMIN (Dokter diarahkan ke dashboard_dokter.php)
if ($current_page === 'dokter.php' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Modul dokter.php khusus Admin. Dokter menggunakan Dashboard Dokter.");
    header("Location: dashboard_dokter.php"); exit();
}

// dashboard_dokter.php KHUSUS DOKTER (Admin selalu diarahkan ke dashboard.php)
if ($current_page === 'dashboard_dokter.php') {
    if ($user_role === 'admin') {
        header("Location: dashboard.php"); exit();
    } elseif ($user_role !== 'petugas_kesehatan') {
        set_flash('error', "Akses Ditolak: Halaman ini khusus Dokter & Tenaga Medis.");
        header("Location: login_petugas.php"); exit();
    }
}

// Farmasi diblok dari triase, nakes, kriteria_saw, dokter, dan dashboard dokter
if ($user_role === 'petugas_farmasi' && in_array($current_page, ['triase.php', 'nakes.php', 'kriteria_saw.php', 'dokter.php', 'dashboard_dokter.php'])) {
    set_flash('error', "Akses Ditolak: Role Farmasi tidak memiliki izin untuk modul ini.");
    header("Location: dashboard_farmasi.php"); exit();
}

// Nakes/Dokter diblok dari dashboard farmasi dan modul farmasi admin
if ($user_role === 'petugas_kesehatan' && in_array($current_page, ['dashboard_farmasi.php', 'farmasi.php'])) {
    set_flash('error', "Akses Ditolak: Modul ini khusus Farmasi / Admin.");
    header("Location: dashboard_dokter.php"); exit();
}




// Label role — dokter mendapat label khusus
$role_label = [
    'admin'             => ['label' => 'Dokter / Admin',  'class' => 'bg-purple-100 text-purple-800 border-purple-200', 'bg' => 'bg-purple-100 text-purple-800 border-purple-200'],
    'petugas_kesehatan' => ['label' => $is_dokter ? 'Dokter' : 'Tenaga Medis', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'bg' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    'petugas_farmasi'   => ['label' => 'Farmasi',         'class' => 'bg-blue-100 text-blue-800 border-blue-200', 'bg' => 'bg-blue-100 text-blue-800 border-blue-200'],
    'santri_wali'       => ['label' => 'Wali Santri',     'class' => 'bg-amber-100 text-amber-800 border-amber-200', 'bg' => 'bg-amber-100 text-amber-800 border-amber-200'],
    'guest'             => ['label' => 'Tamu',            'class' => 'bg-slate-100 text-slate-600 border-slate-200', 'bg' => 'bg-slate-100 text-slate-600 border-slate-200'],
];
$role_badge = $role_label;
$badge      = $role_label[$user_role] ?? $role_label['guest'];

// Map role singkat untuk sidebar
$role_map = [
    'admin'             => 'Dokter / Admin',
    'petugas_kesehatan' => $is_dokter ? 'Dokter' : 'Tenaga Medis',
    'petugas_farmasi'   => 'Farmasi',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Poskestren Al-Bahjah'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ===== Base: selaraskan dengan design login ===== */
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f2;
            color: #182923;
        }

        /* Card */
        .card, .card-custom {
            background: #fff;
            border: 1px solid #d8e6de;
            border-radius: 14px;
        }

        /* Input */
        .input-field, .form-input {
            width: 100%;
            padding: 9px 13px;
            border: 1px solid #d1ddd7;
            border-radius: 8px;
            font-size: 13px;
            background: #f8faf9;
            outline: none;
            transition: border .15s, background .15s;
            font-family: 'Inter', sans-serif;
            color: #182923;
        }
        .input-field:focus, .form-input:focus {
            border-color: #16845B;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(22,132,91,.1);
        }
        select.input-field, select.form-input { cursor: pointer; }
        textarea.input-field, textarea.form-input { resize: vertical; }

        /* Buttons */
        .btn-green {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: #16845B; color: #fff;
            border: none; border-radius: 8px;
            font-size: 13px; font-weight: 600;
            cursor: pointer; transition: background .15s;
            text-decoration: none;
        }
        .btn-green:hover { background: #0B3D2E; }
        .btn-green-sm {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 13px;
            background: #16845B; color: #fff;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            transition: background .15s; text-decoration: none;
        }
        .btn-green-sm:hover { background: #0B3D2E; }
        .btn-outline {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: #fff; color: #0B3D2E;
            border: 1px solid #d1ddd7; border-radius: 8px;
            font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all .15s;
        }
        .btn-outline:hover { background: #f0f4f2; }

        /* Sidebar nav-link */
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 14px; border-radius: 10px;
            font-size: 13px; font-weight: 500;
            color: #a5bfb4;
            transition: all .15s;
            text-decoration: none; cursor: pointer;
        }
        .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .nav-link.active { background: #16845B; color: #fff; font-weight: 600; }
        .nav-link svg { width: 15px; height: 15px; flex-shrink: 0; }
        .nav-section {
            font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            color: #4a8a6a;
            padding: 12px 14px 4px;
        }

        /* Table */
        .tbl { width: 100%; border-collapse: collapse; }
        .tbl thead th {
            padding: 10px 16px; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .05em;
            color: #64756D; background: #f8faf9;
            border-bottom: 1px solid #d8e6de; text-align: left;
        }
        .tbl tbody td {
            padding: 12px 16px; font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #e8f0eb;
        }
        .tbl tbody tr:last-child td { border-bottom: none; }
        .tbl tbody tr:hover td { background: #f8faf9; }

        /* Badge */
        .badge {
            display: inline-flex; align-items: center;
            padding: 2px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 600;
        }

        /* Responsive Mobile Styles */
        @media (max-width: 1023px) {
            body {
                display: block !important;
                height: auto !important;
                min-height: 100vh !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
            }
            .main-content {
                max-width: 100vw !important;
                width: 100% !important;
                height: auto !important;
                min-height: 100vh !important;
                overflow: visible !important;
            }
            .main-content > header {
                padding: 10px 14px !important;
            }
            .main-content > main {
                padding: 14px 12px !important;
            }
            .card, .card-custom {
                border-radius: 12px !important;
            }
            .table-responsive, .overflow-x-auto {
                width: 100%;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
        }

        @media print {
            aside, header, .no-print, #sidebar-backdrop { display: none !important; }
            .main-content { background: white !important; padding: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row bg-[#f0f4f2] text-[#182923]">

    <!-- Mobile Backdrop Overlay -->
    <div id="sidebar-backdrop" onclick="toggleSidebar(false)" 
         class="fixed inset-0 bg-black/60 z-40 hidden transition-opacity duration-300 lg:hidden" aria-hidden="true"></div>

    <!-- ===== SIDEBAR (RESPONSIVE OFF-CANVAS DRAWER ON MOBILE) ===== -->
    <aside id="app-sidebar" 
           class="fixed inset-y-0 left-0 z-50 w-[248px] bg-[#0B3D2E] text-white flex flex-col justify-between h-full shadow-2xl transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0 lg:static lg:w-[232px] lg:flex-shrink-0 lg:h-screen lg:shadow-none overflow-y-auto">

        <!-- Brand & Close Button for Mobile -->
        <div>
            <div style="padding:16px 18px; border-bottom:1px solid rgba(255,255,255,.1); display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <a href="<?= get_redirect_url($user_role); ?>" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit;">
                    <div style="width:36px; height:36px; background:#16845B; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:#fff; flex-shrink:0;">AB</div>
                    <div>
                        <div style="font-weight:700; font-size:14px; color:#fff; line-height:1.2;">Poskestren</div>
                        <div style="font-size:11px; color:#6faa8a;">Al-Bahjah Bogor</div>
                    </div>
                </a>
                <!-- Close Button (Mobile Only) -->
                <button type="button" onclick="toggleSidebar(false)" class="p-1.5 rounded-lg text-emerald-300 hover:text-white hover:bg-white/10 lg:hidden transition" aria-label="Tutup Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- User info -->
            <div style="padding:12px 18px; border-bottom:1px solid rgba(255,255,255,.1);">
                <div style="font-size:10px; color:#4a8a6a; text-transform:uppercase; letter-spacing:.06em; margin-bottom:3px;">Login sebagai</div>
                <div style="font-size:13px; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($user_name); ?></div>
                <div style="font-size:11px; color:#6faa8a; margin-top:2px;"><?= $role_map[$user_role] ?? ucfirst($user_role); ?></div>
            </div>

            <!-- Navigation -->
            <nav style="padding:8px 12px; display:flex; flex-direction:column; gap:2px;">

                <div class="nav-section">Operasional</div>

                <?php if ($user_role === 'admin'): ?>
                <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard Admin
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <a href="dokter.php" class="nav-link <?= $current_page == 'dokter.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Monitoring Dokter &amp; Rawat Inap
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'petugas_kesehatan'): ?>
                <a href="dashboard_dokter.php" class="nav-link <?= $current_page == 'dashboard_dokter.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Dashboard Dokter
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'petugas_farmasi'): ?>
                <a href="dashboard_farmasi.php" class="nav-link <?= $current_page == 'dashboard_farmasi.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    Dashboard Farmasi
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <a href="triase.php" class="nav-link <?= $current_page == 'triase.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Triase SAW
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <a href="rekam_medis_admin.php" class="nav-link <?= in_array($current_page, ['rekam_medis_admin.php', 'rekam_medis.php']) ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Rekam Medis Pasien
                </a>
                <?php elseif ($user_role === 'petugas_kesehatan'): ?>
                <a href="rekam_medis.php" class="nav-link <?= $current_page == 'rekam_medis.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Rekam Medis (EMR)
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <a href="farmasi.php" class="nav-link <?= $current_page == 'farmasi.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Farmasi (Stok &amp; Resep)
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'admin'): ?>
                <div class="nav-section">Data Master</div>

                <a href="santri.php" class="nav-link <?= $current_page == 'santri.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Data Santri
                </a>

                <a href="nakes.php" class="nav-link <?= $current_page == 'nakes.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Tenaga Kesehatan
                </a>

                <a href="kriteria_saw.php" class="nav-link <?= $current_page == 'kriteria_saw.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Kriteria SAW
                </a>

                <a href="laporan.php" class="nav-link <?= $current_page == 'laporan.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Laporan
                </a>
                <?php endif; ?>

                <div class="nav-section">Lainnya</div>

                <?php if ($user_role === 'admin'): ?>
                <a href="wali.php" class="nav-link <?= $current_page == 'wali.php' ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Portal Wali
                </a>
                <?php endif; ?>

                <a href="index.php" class="nav-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Beranda Publik
                </a>
            </nav>
        </div>

        <!-- Logout -->
        <div style="padding:12px 16px; border-top:1px solid rgba(255,255,255,.1);">
            <a href="logout.php" style="display:flex; align-items:center; justify-content:center; gap:7px; padding:9px 12px; background:rgba(214,69,69,.18); border-radius:8px; font-size:12px; font-weight:600; color:#ffaaaa; text-decoration:none; transition:background .15s;"
               onmouseover="this.style.background='rgba(214,69,69,.4)'" onmouseout="this.style.background='rgba(214,69,69,.18)'">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar Sistem
            </a>
        </div>
    </aside>

    <!-- ===== KONTEN UTAMA (RESPONSIVE FOR ALL SCREEN SIZES) ===== -->
    <div class="main-content flex-1 flex flex-col min-h-screen lg:h-screen lg:overflow-y-auto lg:overflow-x-hidden lg:max-w-[calc(100vw-232px)] w-full">

        <!-- Top Bar with Mobile Hamburger Toggle -->
        <header style="background:#fff; border-bottom:1px solid #d8e6de; padding:12px 16px; position:sticky; top:0; z-index:30;" class="flex items-center justify-between sm:px-6">
            <div class="flex items-center gap-3">
                <!-- Mobile Hamburger Toggle Button -->
                <button type="button" onclick="toggleSidebar(true)" class="p-2 -ml-1 rounded-lg text-[#0B3D2E] hover:bg-slate-100 lg:hidden transition flex items-center justify-center shrink-0" aria-label="Buka Menu Navigasi">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <div style="font-size:14px; font-weight:700; color:#0B3D2E; line-height:1.3;" class="sm:text-[15px]"><?= htmlspecialchars($page_title ?? 'Dashboard'); ?></div>
                    <?php if (!empty($page_desc)): ?>
                    <div style="font-size:11px; color:#64756D;" class="hidden sm:block"><?= htmlspecialchars($page_desc); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:11px; font-weight:600; padding:3px 10px; border-radius:999px; border:1px solid #d1ddd7; background:#f8faf9; color:#182923;" class="whitespace-nowrap">
                    <?= $badge['label']; ?>
                </span>
                <?php if ($user_role === 'admin'): ?>
                <a href="triase.php" class="btn-green-sm no-print whitespace-nowrap text-[11px] sm:text-xs">
                    <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="hidden sm:inline">Input Triase</span>
                    <span class="inline sm:hidden">Triase</span>
                </a>
                <?php endif; ?>
            </div>
        </header>

        <script>
            function toggleSidebar(open) {
                const sidebar = document.getElementById('app-sidebar');
                const backdrop = document.getElementById('sidebar-backdrop');
                if (!sidebar || !backdrop) return;
                const shouldOpen = open !== undefined ? open : sidebar.classList.contains('-translate-x-full');
                if (shouldOpen) {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                } else {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }
        </script>

        <!-- Flash Messages -->
        <?php if ($flash_success = get_flash('success')): ?>
        <div style="margin:16px 24px 0; padding:12px 16px; background:#f0fdf4; border:1px solid #d8e6de; border-radius:8px; font-size:13px; color:#15803d; display:flex; align-items:center; justify-content:space-between;">
            <span><?= htmlspecialchars($flash_success); ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;color:#15803d;font-size:16px;">&times;</button>
        </div>
        <?php endif; ?>

        <?php if ($flash_error = get_flash('error')): ?>
        <div style="margin:16px 24px 0; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; font-size:13px; color:#b91c1c; display:flex; align-items:center; justify-content:space-between;">
            <span><?= htmlspecialchars($flash_error); ?></span>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-weight:700;color:#b91c1c;font-size:16px;">&times;</button>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <main style="flex:1; padding:20px 24px;">

