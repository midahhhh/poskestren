<?php
session_start();

// PROTEKSI KETAT: Hanya role 'admin' yang boleh masuk
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login_petugas.php?error=access_denied');
    exit;
}

$success_msg = '';
$error_msg = '';

// =========================================================================
// INISIALISASI & RESET SESI AMAN
// =========================================================================
if (!isset($_SESSION['triage_queue'])) {
    $_SESSION['triage_queue'] = [];
}

if (!isset($_SESSION['medicines'])) {
    $_SESSION['medicines'] = [
        ['id' => 1, 'kode' => 'OBT-001', 'nama' => 'Paracetamol 500mg', 'kategori' => 'Analgetik', 'stok' => 117, 'min_stok' => 20, 'expired' => '2027-08-15', 'satuan' => 'Tablet'],
        ['id' => 2, 'kode' => 'OBT-002', 'nama' => 'Amoxicillin 500mg', 'kategori' => 'Antibiotik', 'stok' => 14, 'min_stok' => 20, 'expired' => '2026-12-01', 'satuan' => 'Kaplet']
    ];
}

if (!isset($_SESSION['santri_list'])) {
    $_SESSION['santri_list'] = [
        ['nis' => '2411070087', 'nama' => 'Hamidah Qudsyiatun Nufus', 'asrama' => 'Asrama Khadijah A', 'status' => 'Sehat'],
        ['nis' => '2411070012', 'nama' => 'Ahmad Fauzan', 'asrama' => 'Asrama Al-Fatih 3A', 'status' => 'Dirawat / Di Poskes']
    ];
}

if (!isset($_SESSION['petugas_list'])) {
    $_SESSION['petugas_list'] = [
        ['nama' => 'Dr. dr. H. Moh. Faqih, Sp.A', 'role' => 'Dokter Penanggung Jawab', 'shift' => 'Senin & Kamis (09:00 - 13:00 WIB)', 'kontak' => '081234567890'],
        ['nama' => 'Ns. M. Rizki, S.Kep', 'role' => 'Perawat / Nakes Jaga', 'shift' => 'Shift Pagi (07:00 - 14:00 WIB)', 'kontak' => '081987654321']
    ];
}

if (!isset($_SESSION['discharge_reports'])) {
    $_SESSION['discharge_reports'] = [
        [
            'santri_nis' => '2411070012',
            'santri_nama' => 'Ahmad Fauzan',
            'asrama' => 'Asrama Al-Fatih 3A',
            'keluhan' => 'Demam tinggi dan gejala tipes ringan',
            'diagnosa' => 'Febris hari ke-3 (Pemulihan)',
            'status_keluar' => 'Dirawat / Di Poskes',
            'waktu_keluar' => '30/10/2026 14:30 WIB'
        ]
    ];
}

if (!isset($_SESSION['saw_criteria'])) {
    $_SESSION['saw_criteria'] = [
        ['code' => 'C1', 'name' => 'Suhu Tubuh / Demam', 'desc' => 'Indikator suhu tubuh santri', 'weight' => 30],
        ['code' => 'C2', 'name' => 'Skala Tingkat Nyeri', 'desc' => 'Tingkat keparahan keluhan nyeri', 'weight' => 25],
        ['code' => 'C3', 'name' => 'Riwayat Komorbid', 'desc' => 'Penyakit penyerta atau riwayat khusus', 'weight' => 20],
        ['code' => 'C4', 'name' => 'Durasi Keluhan', 'desc' => 'Lamanya gejala dirasakan sebelum diperiksa', 'weight' => 15],
        ['code' => 'C5', 'name' => 'Tingkat Vitalitas', 'desc' => 'Kondisi kesadaran dan fisik umum', 'weight' => 10]
    ];
}

// =========================================================================
// PROCESS POST REQUESTS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH PEMERIKSAAN TRIASE
    if ($action === 'add_triage_assessment') {
        $nis = trim($_POST['santri_nis'] ?? '');
        $nama = trim($_POST['santri_nama'] ?? '');
        $asrama = trim($_POST['asrama'] ?? '');
        $keluhan = trim($_POST['keluhan'] ?? '');
        
        $c1 = intval($_POST['c1'] ?? 1);
        $c2 = intval($_POST['c2'] ?? 1);
        $c3 = intval($_POST['c3'] ?? 1);
        $c4 = intval($_POST['c4'] ?? 1);
        $c5 = intval($_POST['c5'] ?? 1);
        $eskalasi = isset($_POST['eskalasi_darurat']) ? true : false;

        $criteria = $_SESSION['saw_criteria'];
        $w1 = $criteria[0]['weight'] / 100;
        $w2 = $criteria[1]['weight'] / 100;
        $w3 = $criteria[2]['weight'] / 100;
        $w4 = $criteria[3]['weight'] / 100;
        $w5 = $criteria[4]['weight'] / 100;

        $score = ($c1 * $w1) + ($c2 * $w2) + ($c3 * $w3) + ($c4 * $w4) + ($c5 * $w5);
        $score_formatted = number_format($score, 2);

        $priority = 'Ringan (Hijau)';
        if ($eskalasi || $score >= 3.5) {
            $priority = 'Gawat Darurat (Merah)';
        } elseif ($score >= 2.5) {
            $priority = 'Sedang (Kuning)';
        }

        // Tambah ke antrean aktif tanpa batasan ketat agar langsung tampil di antrean
        array_unshift($_SESSION['triage_queue'], [
            'santri_nis' => $nis,
            'santri_nama' => $nama,
            'asrama' => $asrama,
            'keluhan' => $keluhan,
            'saw_score' => $score_formatted,
            'priority' => $priority,
            'time' => date('H:i') . ' WIB'
        ]);
        $success_msg = 'Data pemeriksaan triase berhasil masuk ke antrean aktif!';
    }

    // 2. SELESAI & PINDAHKAN DARI ANTREAN KE LAPORAN AKHIR
    if ($action === 'discharge_patient') {
        $nis = $_POST['santri_nis'] ?? '';
        $nama = $_POST['santri_nama'] ?? '';
        $asrama = $_POST['asrama'] ?? '';
        $keluhan = $_POST['keluhan'] ?? '';
        $status_keluar = $_POST['status_keluar'] ?? 'Selesai Diperiksa / Sembuh';

        // Masukkan ke Laporan Akhir (discharge_reports)
        array_unshift($_SESSION['discharge_reports'], [
            'santri_nis' => $nis,
            'santri_nama' => $nama,
            'asrama' => $asrama,
            'keluhan' => $keluhan,
            'diagnosa' => 'Penanganan Medis Selesai',
            'status_keluar' => $status_keluar,
            'waktu_keluar' => date('d/m/Y H:i') . ' WIB'
        ]);

        // Hapus dari antrean aktif agar hilang dari halaman utama antrean
        $_SESSION['triage_queue'] = array_values(array_filter($_SESSION['triage_queue'], fn($q) => !($q['santri_nis'] === $nis && $q['keluhan'] === $keluhan)));

        $success_msg = "Pasien atas nama {$nama} telah diselesaikan dan berhasil dicatat ke Laporan Keluar Poskes!";
    }

    // 3. TAMBAH DATA SANTRI
    if ($action === 'add_santri') {
        $nis = trim($_POST['s_nis'] ?? '');
        $nama = trim($_POST['s_nama'] ?? '');
        $asrama = trim($_POST['s_asrama'] ?? '');
        $status = trim($_POST['s_status'] ?? 'Sehat');

        if (!empty($nis) && !empty($nama)) {
            array_unshift($_SESSION['santri_list'], [
                'nis' => $nis,
                'nama' => $nama,
                'asrama' => $asrama,
                'status' => $status
            ]);

            if ($status === 'Dirawat / Di Poskes' || $status === 'Sakit Ringan') {
                array_unshift($_SESSION['discharge_reports'], [
                    'santri_nis' => $nis,
                    'santri_nama' => $nama,
                    'asrama' => $asrama,
                    'keluhan' => 'Pencatatan langsung via Data Santri (' . $status . ')',
                    'diagnosa' => 'Observasi & Perawatan Poskes',
                    'status_keluar' => $status,
                    'waktu_keluar' => date('d/m/Y H:i') . ' WIB'
                ]);
            }

            $success_msg = 'Data santri berhasil ditambahkan!';
        }
    }

    if ($action === 'add_petugas') {
        $p_nama = trim($_POST['p_nama'] ?? '');
        $p_role = trim($_POST['p_role'] ?? 'Perawat / Nakes Jaga');
        $p_shift = trim($_POST['p_shift'] ?? '');
        $p_kontak = trim($_POST['p_kontak'] ?? '');

        if (!empty($p_nama) && !empty($p_shift)) {
            array_unshift($_SESSION['petugas_list'], [
                'nama' => $p_nama,
                'role' => $p_role,
                'shift' => $p_shift,
                'kontak' => $p_kontak
            ]);
            $success_msg = 'Jadwal shift petugas berhasil ditambahkan!';
        }
    }

    if ($action === 'add_medicine') {
        $_SESSION['medicines'][] = [
            'id' => time(),
            'kode' => trim($_POST['m_kode'] ?? ''),
            'nama' => trim($_POST['m_nama'] ?? ''),
            'kategori' => trim($_POST['m_kategori'] ?? ''),
            'stok' => intval($_POST['m_stok'] ?? 0),
            'min_stok' => intval($_POST['m_min_stok'] ?? 10),
            'expired' => trim($_POST['m_expired'] ?? ''),
            'satuan' => trim($_POST['m_satuan'] ?? 'Tablet')
        ];
        $success_msg = 'Data obat baru berhasil ditambahkan!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin & SPK Triase — Poskestren Al-Bahjah Bogor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-green': '#16845B',
                        'deep-green': '#0B3D2E',
                        'warm-yellow': '#F4C542',
                        'emergency-red': '#D64545',
                        'app-bg': '#F5F8F6',
                        'text-main': '#182923',
                        'text-muted': '#64756D',
                        'border-color': '#DCE7E0',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F5F8F6; color: #182923; }
        @media print {
            aside, header, button, .no-print { display: none !important; }
            main { overflow: visible !important; background-color: white !important; padding: 0 !important; }
            body { background-color: white !important; }
            #tab-laporan { display: block !important; }
        }
    </style>
</head>
<body class="bg-app-bg text-text-main min-h-screen flex">

    <!-- SIDEBAR NAVIGASI UTAMA -->
    <aside class="w-64 bg-deep-green text-white p-6 flex flex-col justify-between shrink-0 hidden md:flex">
        <div class="space-y-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary-green text-white rounded-xl flex items-center justify-center font-bold shadow-md">
                    <i data-lucide="cross" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-base font-bold block leading-tight">Poskestren</span>
                    <span class="text-xs text-gray-300 font-medium">Al-Bahjah Bogor</span>
                </div>
            </div>

            <nav class="space-y-1.5 pt-4 border-t border-white/10 text-xs font-medium">
                <a href="#dashboard" onclick="switchTab('dashboard')" id="nav-dashboard" class="flex items-center gap-3 px-4 py-3 bg-primary-green text-white rounded-xl shadow-sm transition-colors">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Ringkasan & Triase
                </a>
                <a href="#pemeriksaan" onclick="switchTab('pemeriksaan')" id="nav-pemeriksaan" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="stethoscope" class="w-4 h-4 text-primary-green"></i> Input Pemeriksaan Triase
                </a>
                <a href="#saw" onclick="switchTab('saw')" id="nav-saw" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="sliders" class="w-4 h-4 text-primary-green"></i> Konfigurasi Kriteria SAW
                </a>
                <a href="#farmasi" onclick="switchTab('farmasi')" id="nav-farmasi" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="pill" class="w-4 h-4 text-primary-green"></i> Master Obat & Stok
                </a>
                <a href="#santri" onclick="switchTab('santri')" id="nav-santri" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="users" class="w-4 h-4 text-primary-green"></i> CRUD Data Santri
                </a>
                <a href="#petugas" onclick="switchTab('petugas')" id="nav-petugas" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="clock" class="w-4 h-4 text-primary-green"></i> Shift & Dokter Jaga
                </a>
                <a href="#laporan" onclick="switchTab('laporan')" id="nav-laporan" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-white/10 rounded-xl transition-colors">
                    <i data-lucide="file-text" class="w-4 h-4 text-primary-green"></i> Laporan Keluar Poskes
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-white/10 space-y-3">
            <div class="p-3 bg-white/5 rounded-xl border border-white/10 text-xs">
                <p class="font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Administrator'); ?></p>
                <p class="text-emerald-300 text-[11px]">Role: Admin / Petugas Medis</p>
            </div>
            <a href="index.php" class="flex items-center justify-center gap-2 w-full py-2.5 px-3 bg-emergency-red/25 hover:bg-emergency-red text-red-100 rounded-xl text-xs font-semibold transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i> Keluar Sistem
            </a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="flex-1 flex flex-col min-h-screen overflow-y-auto">
        <header class="bg-white border-b border-border-color px-6 py-4 flex items-center justify-between sticky top-0 z-30">
            <div>
                <h1 class="text-xl font-bold text-deep-green">Sistem Penunjang Keputusan (SPK) Triase Poskestren</h1>
                <p class="text-xs text-text-muted">Pos Kesehatan Pesantren Al-Bahjah Bogor</p>
            </div>
            <a href="index.php" class="md:hidden p-2 text-emergency-red hover:bg-red-50 rounded-xl">
                <i data-lucide="log-out" class="w-5 h-5"></i>
            </a>
        </header>

        <div class="p-6 sm:p-8 space-y-6 max-w-7xl w-full mx-auto">

            <!-- NOTIFIKASI PESAN -->
            <?php if (!empty($success_msg)): ?>
                <div class="p-4 rounded-xl bg-emerald-50 border border-primary-green/30 text-primary-green text-xs flex items-center gap-3">
                    <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="p-4 rounded-xl bg-red-50 border border-emergency-red/30 text-emergency-red text-xs flex items-center gap-3">
                    <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- =============================================================== -->
            <!-- TAB 1: RINGKASAN & ANTREAN TRIASE SAW -->
            <!-- =============================================================== -->
            <div id="tab-dashboard" class="space-y-6">
                <!-- Status Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-text-muted">Antrean Darurat (Merah)</span>
                        <p class="text-2xl font-bold text-emergency-red">
                            <?php echo count(array_filter($_SESSION['triage_queue'], fn($q) => str_contains($q['priority'], 'Darurat'))); ?> Pasien
                        </p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-text-muted">Antrean Sedang (Kuning)</span>
                        <p class="text-2xl font-bold text-warm-yellow">
                            <?php echo count(array_filter($_SESSION['triage_queue'], fn($q) => str_contains($q['priority'], 'Sedang'))); ?> Pasien
                        </p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-text-muted">Santri Dirawat di Poskes</span>
                        <p class="text-2xl font-bold text-deep-green">
                            <?php echo count(array_filter($_SESSION['santri_list'], fn($s) => $s['status'] === 'Dirawat / Di Poskes')); ?> Santri
                        </p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                        <span class="text-xs font-semibold text-text-muted">Obat Stok Kritis</span>
                        <p class="text-2xl font-bold text-amber-600">
                            <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
    <span class="text-xs font-semibold text-text-muted">Obat Stok Kritis</span>
    <p class="text-2xl font-bold text-amber-600">
        <?php 
        echo count(array_filter($_SESSION['medicines'], function($m) {
            // Menggunakan ?? 20 untuk mencegah error jika min_stok tidak ada
            $min = isset($m['min_stok']) ? $m['min_stok'] : 20;
            return $m['stok'] <= $min;
        })); 
        ?> Jenis
    </p>
</div>
                        </p>
                    </div>
                </div>

                <!-- Tabel Urutan Antrean SAW -->
                <div class="bg-white rounded-2xl border border-border-color shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-border-color flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-bold text-deep-green">Hasil Pemeringkatan Triase SAW (Antrean Aktif)</h2>
                            <p class="text-xs text-text-muted">Pasien yang sedang dalam antrean aktif pemeriksaan medis</p>
                        </div>
                        <button onclick="switchTab('pemeriksaan')" class="px-3.5 py-2 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-xl text-xs transition-colors flex items-center gap-1.5">
                            <i data-lucide="plus" class="w-4 h-4"></i> + Pemeriksaan Baru
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Peringkat & Skor SAW</th>
                                    <th class="px-4 py-3">Nama Santri & NIS</th>
                                    <th class="px-4 py-3">Keluhan Klinis</th>
                                    <th class="px-4 py-3">Kategori Triase</th>
                                    <th class="px-4 py-3">Waktu Masuk</th>
                                    <th class="px-4 py-3 text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php if (empty($_SESSION['triage_queue'])): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-text-muted">Belum ada data antrean pemeriksaan triase aktif.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($_SESSION['triage_queue'] as $rank => $q): ?>
                                        <tr>
                                            <td class="px-4 py-4">
                                                <span class="font-bold text-sm text-deep-green">#<?php echo $rank + 1; ?></span>
                                                <span class="ml-2 px-2 py-0.5 bg-gray-100 rounded text-mono font-bold">Skor: <?php echo $q['saw_score']; ?></span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="font-bold text-deep-green block"><?php echo htmlspecialchars($q['santri_nama']); ?></span>
                                                <span class="text-text-muted text-[11px]">NIS: <?php echo htmlspecialchars($q['santri_nis']); ?> | <?php echo htmlspecialchars($q['asrama']); ?></span>
                                            </td>
                                            <td class="px-4 py-4 max-w-xs truncate"><?php echo htmlspecialchars($q['keluhan']); ?></td>
                                            <td class="px-4 py-4">
                                                <?php if (str_contains($q['priority'], 'Darurat')): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-red-100 text-emergency-red font-bold text-[10px]">🔴 Darurat (Merah)</span>
                                                <?php elseif (str_contains($q['priority'], 'Sedang')): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px]">🟡 Sedang (Kuning)</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">🟢 Ringan (Hijau)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 text-text-muted"><?php echo $q['time']; ?></td>
                                            <td class="px-4 py-4 text-right">
                                                <form action="#dashboard" method="POST" class="inline">
                                                    <input type="hidden" name="action" value="discharge_patient">
                                                    <input type="hidden" name="santri_nis" value="<?php echo htmlspecialchars($q['santri_nis']); ?>">
                                                    <input type="hidden" name="santri_nama" value="<?php echo htmlspecialchars($q['santri_nama']); ?>">
                                                    <input type="hidden" name="asrama" value="<?php echo htmlspecialchars($q['asrama']); ?>">
                                                    <input type="hidden" name="keluhan" value="<?php echo htmlspecialchars($q['keluhan']); ?>">
                                                    <input type="hidden" name="status_keluar" value="Selesai Diperiksa / Sembuh">
                                                    <button type="submit" class="px-3 py-1.5 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-lg transition-colors">
                                                        Selesai & Catat Laporan
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 2: INPUT PEMERIKSAAN TRIASE -->
            <!-- =============================================================== -->
            <div id="tab-pemeriksaan" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-bold text-deep-green">Form Observasi & Kalkulasi Triase SAW</h2>
                        <p class="text-xs text-text-muted">Isi parameter kriteria klinis awal santri untuk menghasilkan pemeringkatan prioritas antrean</p>
                    </div>

                    <form action="#pemeriksaan" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="add_triage_assessment">

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-app-bg p-4 rounded-xl border border-border-color">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Nomor Induk Santri (NIS)</label>
                                <input type="text" name="santri_nis" required class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl" placeholder="2411070087">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Nama Lengkap Santri</label>
                                <input type="text" name="santri_nama" required class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl" placeholder="Nama santri...">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Asrama / Kamar</label>
                                <input type="text" name="asrama" required class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl" placeholder="Cth: Khadijah A">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1">Catatan Anamnesis / Keluhan Utama</label>
                            <textarea name="keluhan" rows="2" required class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl" placeholder="Deskripsikan gejala yang dirasakan santri..."></textarea>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-deep-green uppercase tracking-wider">Skala Subkriteria Parameter Medis (1 - 5)</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted mb-1">C1: Suhu Tubuh</label>
                                    <select name="c1" class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl">
                                        <option value="1">Normal (36.5°C - 37.2°C)</option>
                                        <option value="2">Subfebris (37.3°C - 37.9°C)</option>
                                        <option value="3">Demam Sedang (38.0°C - 38.9°C)</option>
                                        <option value="4" selected>Demam Tinggi (39.0°C - 39.9°C)</option>
                                        <option value="5">Demam Sangat Tinggi (≥40.0°C)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted mb-1">C2: Skala Nyeri</label>
                                    <select name="c2" class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl">
                                        <option value="1">1-2: Nyeri Sangat Ringan</option>
                                        <option value="2">3-4: Nyeri Ringan</option>
                                        <option value="3" selected>5-6: Nyeri Sedang</option>
                                        <option value="4">7-8: Nyeri Berat</option>
                                        <option value="5">9-10: Nyeri Sangat Hebat</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted mb-1">C3: Komorbid / Alergi</label>
                                    <select name="c3" class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl">
                                        <option value="1">Tidak Ada Komorbid</option>
                                        <option value="2">Riwayat Alergi Ringan</option>
                                        <option value="3" selected>Riwayat Asma / Maag</option>
                                        <option value="4">Riwayat Jantung / Paru</option>
                                        <option value="5">Komorbid Kompleks Akut</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted mb-1">C4: Durasi Sakit</label>
                                    <select name="c4" class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl">
                                        <option value="1">Baru hari ini (&lt;24 jam)</option>
                                        <option value="2" selected>1 - 2 Hari</option>
                                        <option value="3">3 - 4 Hari</option>
                                        <option value="4">5 - 7 Hari</option>
                                        <option value="5">&gt; 7 Hari (Kronis)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-text-muted mb-1">C5: Vitalitas & Nadi</label>
                                    <select name="c5" class="w-full text-xs p-2.5 bg-white border border-border-color rounded-xl">
                                        <option value="1">Kesadaran Penuh (Compos Mentis)</option>
                                        <option value="2">Apatis / Lemah</option>
                                        <option value="3" selected>Somnolen / Mengantuk Berat</option>
                                        <option value="4">Sopor / Gelisah Akut</option>
                                        <option value="5">Koma / Tidak Sadar</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 bg-red-50 border border-emergency-red/30 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <i data-lucide="alert-octagon" class="w-5 h-5 text-emergency-red shrink-0"></i>
                                <div>
                                    <span class="text-xs font-bold text-emergency-red block">Eskalasi Gawat Darurat Klinis (Bypass Ranking)</span>
                                    <span class="text-[11px] text-text-muted">Centang jika kondisi santri membutuhkan tindakan langsung tanpa menunggu kalkulasi antrean.</span>
                                </div>
                            </div>
                            <input type="checkbox" name="eskalasi_darurat" value="1" class="w-5 h-5 text-emergency-red rounded border-border-color focus:ring-emergency-red">
                        </div>

                        <button type="submit" class="py-3 px-6 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-xl text-xs transition-colors shadow-sm">
                            Kalkulasi Skor SAW & Simpan Antrean
                        </button>
                    </form>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 3: KONFIGURASI KRITERIA & BOBOT SAW -->
            <!-- =============================================================== -->
            <div id="tab-saw" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-bold text-deep-green">Pengaturan Kriteria & Bobot Metode SAW</h2>
                        <p class="text-xs text-text-muted">Atur persentase bobot kriteria medis. Total seluruh bobot kriteria wajib bernilai 100%.</p>
                    </div>

                    <form action="#saw" method="POST" class="space-y-4 max-w-2xl">
                        <input type="hidden" name="action" value="update_saw_weights">
                        
                        <div class="space-y-3">
                            <?php foreach ($_SESSION['saw_criteria'] as $i => $c): ?>
                                <div class="p-3 bg-app-bg rounded-xl border border-border-color flex items-center justify-between gap-4">
                                    <div>
                                        <span class="font-bold text-xs text-deep-green"><?php echo $c['code']; ?> — <?php echo $c['name']; ?></span>
                                        <span class="text-[11px] text-text-muted block"><?php echo $c['desc']; ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <input type="number" name="weights[]" value="<?php echo $c['weight']; ?>" min="0" max="100" required class="w-20 text-xs p-2 text-center bg-white border border-border-color rounded-lg font-bold">
                                        <span class="text-xs font-bold text-text-muted">%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="py-3 px-6 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-xl text-xs transition-colors">
                            Validasi & Simpan Bobot SAW
                        </button>
                    </form>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 4: FARMASI & MASTER OBAT -->
            <!-- =============================================================== -->
            <div id="tab-farmasi" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
                    <div class="flex justify-between items-center border-b border-border-color pb-4">
                        <div>
                            <h2 class="text-base font-bold text-deep-green">Inventaris Obat & Persediaan Farmasi</h2>
                            <p class="text-xs text-text-muted">Kelola stok obat, pantau obat dengan stok kritis dan tanggal kedaluwarsa</p>
                        </div>
                    </div>

                    <form action="#farmasi" method="POST" class="bg-app-bg p-4 rounded-xl border border-border-color grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                        <input type="hidden" name="action" value="add_medicine">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Kode</label>
                            <input type="text" name="m_kode" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="OBT-005">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nama Obat</label>
                            <input type="text" name="m_nama" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Nama obat...">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Kategori</label>
                            <input type="text" name="m_kategori" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Cth: Antibiotik">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Stok & Min</label>
                            <div class="flex gap-1">
                                <input type="number" name="m_stok" placeholder="Stok" required class="w-1/2 text-xs p-2 bg-white border rounded-lg">
                                <input type="number" name="m_min_stok" placeholder="Min" required class="w-1/2 text-xs p-2 bg-white border rounded-lg">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Tgl Expired</label>
                            <input type="date" name="m_expired" required class="w-full text-xs p-2 bg-white border rounded-lg">
                        </div>
                        <div>
                            <input type="hidden" name="m_satuan" value="Tablet">
                            <button type="submit" class="w-full py-2 bg-primary-green text-white text-xs font-semibold rounded-lg hover:bg-deep-green">Tambah Obat</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Kode & Nama Obat</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3">Sisa Stok</th>
                                    <th class="px-4 py-3">Tanggal Expired</th>
                                    <th class="px-4 py-3 text-right">Status Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php foreach ($_SESSION['medicines'] as $m): ?>
                                    <tr>
                                        <td class="px-4 py-3">
                                            <span class="font-bold text-deep-green block"><?php echo htmlspecialchars($m['nama']); ?></span>
                                            <span class="text-text-muted text-[11px]"><?php echo $m['kode']; ?></span>
                                        </td>
                                      <td class="px-4 py-3"><?php echo htmlspecialchars($m['kategori'] ?? 'Umum'); ?></td>
<td class="px-4 py-3 font-bold"><?php echo $m['stok']; ?> <?php echo $m['satuan']; ?></td>
<td class="px-4 py-3"><?php echo htmlspecialchars($m['expired'] ?? '-'); ?></td>
                                        <td class="px-4 py-3 text-right">
                                            <?php if ($m['stok'] <= $m['min_stok']): ?>
                                                <span class="px-2.5 py-1 bg-red-100 text-emergency-red rounded-full font-bold text-[10px]">⚠️ Stok Kritis</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]">Aman</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 5: CRUD DATA SANTRI -->
            <!-- =============================================================== -->
            <div id="tab-santri" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-bold text-deep-green">Data Induk Santri Poskestren</h2>
                        <p class="text-xs text-text-muted">Kelola data santri serta status kesehatan (sehat, sakit, atau dirawat di poskes).</p>
                    </div>

                    <form action="#santri" method="POST" class="bg-app-bg p-4 rounded-xl border border-border-color grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                        <input type="hidden" name="action" value="add_santri">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nomor Induk Santri (NIS)</label>
                            <input type="text" name="s_nis" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="241107... ">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nama Lengkap Santri</label>
                            <input type="text" name="s_nama" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Nama santri...">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Asrama / Kamar</label>
                            <input type="text" name="s_asrama" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Cth: Khadijah A">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Status Kesehatan</label>
                            <select name="s_status" class="w-full text-xs p-2 bg-white border rounded-lg">
                                <option value="Sehat">🟢 Sehat</option>
                                <option value="Sakit Ringan">🟡 Sakit Ringan</option>
                                <option value="Dirawat / Di Poskes">🔴 Dirawat / Di Poskes</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="w-full py-2 bg-primary-green text-white text-xs font-semibold rounded-lg hover:bg-deep-green transition-colors">Tambah Santri</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">NIS</th>
                                    <th class="px-4 py-3">Nama Santri</th>
                                    <th class="px-4 py-3">Asrama / Kamar</th>
                                    <th class="px-4 py-3">Status Kesehatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php foreach ($_SESSION['santri_list'] as $s): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-mono font-bold"><?php echo htmlspecialchars($s['nis']); ?></td>
                                        <td class="px-4 py-3 font-semibold text-deep-green"><?php echo htmlspecialchars($s['nama']); ?></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($s['asrama']); ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ($s['status'] === 'Dirawat / Di Poskes'): ?>
                                                <span class="px-2.5 py-1 bg-red-100 text-emergency-red rounded-full font-bold text-[10px]">🔴 Dirawat / Di Poskes</span>
                                            <?php elseif ($s['status'] === 'Sakit Ringan'): ?>
                                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full font-bold text-[10px]">🟡 Sakit Ringan</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]">🟢 Sehat</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 6: SHIFT & DOKTER JAGA -->
            <!-- =============================================================== -->
            <div id="tab-petugas" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
                    <div>
                        <h2 class="text-base font-bold text-deep-green">Jadwal Shift & Dokter Jaga Poskestren</h2>
                        <p class="text-xs text-text-muted">Atur dan tambahkan jadwal penugasan tenaga medis, dokter, atau perawat jaga harian.</p>
                    </div>

                    <form action="#petugas" method="POST" class="bg-app-bg p-4 rounded-xl border border-border-color grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                        <input type="hidden" name="action" value="add_petugas">
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nama Lengkap & Gelar</label>
                            <input type="text" name="p_nama" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Dr. / Ns. Nama...">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Peran / Posisi</label>
                            <select name="p_role" class="w-full text-xs p-2 bg-white border rounded-lg">
                                <option value="Dokter Penanggung Jawab">👨‍⚕️ Dokter Penanggung Jawab</option>
                                <option value="Perawat / Nakes Jaga">💉 Perawat / Nakes Jaga</option>
                                <option value="Staf Farmasi / Obat">💊 Staf Farmasi / Obat</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Keterangan Shift / Jadwal</label>
                            <input type="text" name="p_shift" required class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="Cth: Shift Pagi (07:00 - 14:00)">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold mb-1">Nomor Kontak / WA</label>
                            <input type="text" name="p_kontak" class="w-full text-xs p-2 bg-white border rounded-lg" placeholder="0812...">
                        </div>
                        <div>
                            <button type="submit" class="w-full py-2 bg-primary-green text-white text-xs font-semibold rounded-lg hover:bg-deep-green transition-colors">Tambah Jadwal</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Nama Petugas / Dokter</th>
                                    <th class="px-4 py-3">Peran Medis</th>
                                    <th class="px-4 py-3">Jadwal Shift / Hari</th>
                                    <th class="px-4 py-3">Kontak</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php foreach ($_SESSION['petugas_list'] as $p): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-deep-green"><?php echo htmlspecialchars($p['nama']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]">
                                                <?php echo htmlspecialchars($p['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-text-main"><?php echo htmlspecialchars($p['shift']); ?></td>
                                        <td class="px-4 py-3 font-mono text-text-muted"><?php echo htmlspecialchars($p['kontak'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- TAB 7: LAPORAN KELUAR POSKES -->
            <!-- =============================================================== -->
            <div id="tab-laporan" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-bold text-deep-green">Laporan Rekam Medis & Pasien Keluar Poskes</h2>
                            <p class="text-xs text-text-muted">Arsip rekapitulasi harian pasien yang pernah diperiksa, sakit, dirawat, hingga selesai dari poskestren.</p>
                        </div>
                        <button onclick="window.print()" class="px-4 py-2 bg-deep-green hover:bg-primary-green text-white font-semibold rounded-xl text-xs transition-colors flex items-center gap-2 shadow-sm">
                            <i data-lucide="printer" class="w-4 h-4"></i> Cetak Laporan
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Waktu Keluar / Selesai</th>
                                    <th class="px-4 py-3">Nama Santri & NIS</th>
                                    <th class="px-4 py-3">Keluhan Awal</th>
                                    <th class="px-4 py-3">Status / Keterangan Keluar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php if (empty($_SESSION['discharge_reports'])): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-text-muted">Belum ada data laporan pasien keluar poskes.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($_SESSION['discharge_reports'] as $rep): ?>
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-deep-green"><?php echo htmlspecialchars($rep['waktu_keluar']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="font-bold block"><?php echo htmlspecialchars($rep['santri_nama']); ?></span>
                                                <span class="text-text-muted text-[11px]">NIS: <?php echo htmlspecialchars($rep['santri_nis']); ?> | <?php echo htmlspecialchars($rep['asrama']); ?></span>
                                            </td>
                                            <td class="px-4 py-3 max-w-xs truncate"><?php echo htmlspecialchars($rep['keluhan']); ?></td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]">
                                                    <?php echo htmlspecialchars($rep['status_keluar']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();

        function switchTab(tabId) {
            const tabs = ['dashboard', 'pemeriksaan', 'saw', 'farmasi', 'santri', 'petugas', 'laporan'];
            tabs.forEach(t => {
                const el = document.getElementById('tab-' + t);
                const nav = document.getElementById('nav-' + t);
                if (el) el.classList.add('hidden');
                if (nav) {
                    nav.classList.remove('bg-primary-green', 'text-white', 'shadow-sm');
                    nav.classList.add('text-gray-300', 'hover:bg-white/10');
                }
            });

            const activeEl = document.getElementById('tab-' + tabId);
            const activeNav = document.getElementById('nav-' + tabId);
            if (activeEl) activeEl.classList.remove('hidden');
            if (activeNav) {
                activeNav.classList.remove('text-gray-300', 'hover:bg-white/10');
                activeNav.classList.add('bg-primary-green', 'text-white', 'shadow-sm');
            }
        }
    </script>
</body>
</html>