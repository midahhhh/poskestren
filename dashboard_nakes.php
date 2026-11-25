<?php
session_start();

// PROTEKSI: Hanya role 'nakes' atau 'admin' yang boleh masuk
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'nakes'])) {
    header('Location: login_petugas.php?error=access_denied');
    exit;
}

$success_msg = '';
$error_msg = '';

// INISIALISASI SESI (Sinkron mutlak dengan Dashboard Admin)
if (!isset($_SESSION['triage_queue'])) { $_SESSION['triage_queue'] = []; }
if (!isset($_SESSION['discharge_reports'])) { $_SESSION['discharge_reports'] = []; }
if (!isset($_SESSION['medicines'])) {
    $_SESSION['medicines'] = [
        ['id' => 1, 'kode' => 'OBT-001', 'nama' => 'Paracetamol 500mg', 'kategori' => 'Analgetik', 'stok' => 117, 'min_stok' => 20, 'expired' => '2027-08-15', 'satuan' => 'Tablet'],
        ['id' => 2, 'kode' => 'OBT-002', 'nama' => 'Amoxicillin 500mg', 'kategori' => 'Antibiotik', 'stok' => 14, 'min_stok' => 20, 'expired' => '2026-12-01', 'satuan' => 'Kaplet']
    ];
}

// PROSES TINDAKAN MEDIS & RESEP OLEH NAKES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'proses_tindakan') {
        $nis = $_POST['santri_nis'] ?? '';
        $nama = $_POST['santri_nama'] ?? '';
        $asrama = $_POST['asrama'] ?? '';
        $keluhan = $_POST['keluhan'] ?? '';
        $diagnosa = trim($_POST['diagnosa'] ?? 'Pemeriksaan Umum Poskestren');
        $tindakan = trim($_POST['tindakan'] ?? 'Pemberian Obat & Istirahat');
        $obat_id = intval($_POST['obat_id'] ?? 0);
        $jml_obat = intval($_POST['jml_obat'] ?? 0);

        $nama_obat_resep = 'Tanpa Obat';

        // Kurangi Stok Obat jika dipilih
        if ($obat_id > 0 && $jml_obat > 0) {
            foreach ($_SESSION['medicines'] as &$med) {
                if (isset($med['id']) && $med['id'] === $obat_id) {
                    if ($med['stok'] >= $jml_obat) {
                        $med['stok'] -= $jml_obat;
                        $nama_obat_resep = $med['nama'] . " ({$jml_obat} " . ($med['satuan'] ?? 'Tablet') . ")";
                    } else {
                        $error_msg = "Gagal: Stok obat {$med['nama']} tidak mencukupi (Sisa stok: {$med['stok']})!";
                    }
                    break;
                }
            }
            unset($med);
        }

        if (empty($error_msg)) {
            // Masukkan otomatis ke Laporan Keluar Poskes (Sinkron dengan Admin)
            array_unshift($_SESSION['discharge_reports'], [
                'santri_nis' => $nis,
                'santri_nama' => $nama,
                'asrama' => $asrama,
                'keluhan' => $keluhan,
                'diagnosa' => $diagnosa . ' | Tindakan: ' . $tindakan . ' | Resep: ' . $nama_obat_resep,
                'status_keluar' => 'Selesai Diperiksa Nakes / Sembuh',
                'waktu_keluar' => date('d/m/Y H:i') . ' WIB'
            ]);

            // Hapus dari antrean aktif triase secara bersih
            $_SESSION['triage_queue'] = array_values(array_filter($_SESSION['triage_queue'], fn($q) => !($q['santri_nis'] === $nis && $q['keluhan'] === $keluhan)));

            $success_msg = "Tindakan medis untuk santri {$nama} berhasil dicatat dan otomatis masuk ke Laporan Poskes!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nakes & Perawat — Poskestren Al-Bahjah Bogor</title>
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
    <style>body { font-family: 'Inter', sans-serif; background-color: #F5F8F6; color: #182923; }</style>
</head>
<body class="bg-app-bg text-text-main min-h-screen flex flex-col">

    <!-- NAVBAR ATAS NAKES -->
    <header class="bg-deep-green text-white px-6 sm:px-8 py-4 flex items-center justify-between shadow-md">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-green text-white rounded-xl flex items-center justify-center font-bold">
                <i data-lucide="stethoscope" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-base font-bold leading-tight">Dashboard Tenaga Medis (Nakes)</h1>
                <p class="text-xs text-gray-300">Pos Kesehatan Pesantren Al-Bahjah Bogor</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Petugas Medis'); ?></p>
                <p class="text-[11px] text-emerald-300">Perawat / Dokter Jaga</p>
            </div>
            <a href="dashboard_admin.php" class="hidden sm:inline-block px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold transition-colors">
                Kembali ke Admin
            </a>
            <a href="index.php" class="flex items-center gap-2 py-2 px-3 bg-emergency-red/30 hover:bg-emergency-red text-red-100 rounded-xl text-xs font-semibold transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
            </a>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 sm:p-8 space-y-6">

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

        <!-- KARTU STATISTIK RINGKAS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                <span class="text-xs font-semibold text-text-muted">Pasien Menunggu Perawatan</span>
                <p class="text-2xl font-bold text-deep-green"><?php echo count($_SESSION['triage_queue']); ?> Santri</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                <span class="text-xs font-semibold text-text-muted">Total Stok Obat Tersedia</span>
                <p class="text-2xl font-bold text-primary-green">
                    <?php echo array_sum(array_column($_SESSION['medicines'], 'stok')); ?> Unit
                </p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-border-color shadow-sm space-y-1">
                <span class="text-xs font-semibold text-text-muted">Obat Stok Kritis</span>
                <p class="text-2xl font-bold text-amber-600">
                    <?php 
                    echo count(array_filter($_SESSION['medicines'], function($m) {
                        $min = $m['min_stok'] ?? 20;
                        return $m['stok'] <= $min;
                    })); 
                    ?> Jenis
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- KOLOM KIRI (2/3): ANTREAN PASIEN & KELUHAN -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-4">
                    <div class="flex justify-between items-center border-b border-border-color pb-3">
                        <div>
                            <h2 class="text-base font-bold text-deep-green flex items-center gap-2">
                                <i data-lucide="users" class="w-5 h-5 text-primary-green"></i> Antrean Pasien Masuk & Keluhan
                            </h2>
                            <p class="text-xs text-text-muted">Daftar santri yang menunggu tindakan medis berdasarkan prioritas skor Triase SAW</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($_SESSION['triage_queue'])): ?>
                            <div class="p-8 text-center text-text-muted bg-app-bg rounded-xl border border-border-color text-xs">
                                Tidak ada santri dalam antrean saat ini. Semua pelayanan telah selesai.
                            </div>
                        <?php else: ?>
                            <?php foreach ($_SESSION['triage_queue'] as $q): ?>
                                <div class="p-5 bg-app-bg rounded-2xl border border-border-color space-y-3">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <span class="font-bold text-sm text-deep-green block"><?php echo htmlspecialchars($q['santri_nama']); ?></span>
                                            <span class="text-xs text-text-muted">NIS: <?php echo htmlspecialchars($q['santri_nis']); ?> | <?php echo htmlspecialchars($q['asrama']); ?></span>
                                        </div>
                                        <div>
                                            <?php if (str_contains($q['priority'], 'Darurat')): ?>
                                                <span class="px-2.5 py-1 bg-red-100 text-emergency-red font-bold text-[10px] rounded-full">🔴 Darurat (Merah)</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-bold text-[10px] rounded-full">🟡 Prioritas (Skor: <?php echo $q['saw_score']; ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- DETAIL KELUHAN PASIEN -->
                                    <div class="p-3 bg-white rounded-xl border border-border-color space-y-1">
                                        <span class="text-[11px] font-bold text-primary-green block">Catatan Anamnesis / Keluhan Santri:</span>
                                        <p class="text-xs text-text-main"><?php echo htmlspecialchars($q['keluhan']); ?></p>
                                    </div>

                                    <!-- FORM INPUT TINDAKAN & RESEP OBAT -->
                                    <form action="" method="POST" class="bg-white p-4 rounded-xl border border-border-color space-y-3">
                                        <input type="hidden" name="action" value="proses_tindakan">
                                        <input type="hidden" name="santri_nis" value="<?php echo htmlspecialchars($q['santri_nis']); ?>">
                                        <input type="hidden" name="santri_nama" value="<?php echo htmlspecialchars($q['santri_nama']); ?>">
                                        <input type="hidden" name="asrama" value="<?php echo htmlspecialchars($q['asrama']); ?>">
                                        <input type="hidden" name="keluhan" value="<?php echo htmlspecialchars($q['keluhan']); ?>">

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-text-muted mb-1">Diagnosa Nakes</label>
                                                <input type="text" name="diagnosa" required placeholder="Cth: Febris / Gastritis" class="w-full text-xs p-2 bg-app-bg border rounded-lg">
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-text-muted mb-1">Tindakan Medis</label>
                                                <input type="text" name="tindakan" required placeholder="Cth: Kompres & Istirahat" class="w-full text-xs p-2 bg-app-bg border rounded-lg">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-text-muted mb-1">Pemberian Resep Obat</label>
                                                <select name="obat_id" class="w-full text-xs p-2 bg-app-bg border rounded-lg">
                                                    <option value="0">-- Tanpa Obat / Rawat Jalan --</option>
                                                    <?php foreach ($_SESSION['medicines'] as $med): ?>
                                                        <?php if (isset($med['id'])): ?>
                                                            <option value="<?php echo $med['id']; ?>" <?php echo ($med['stok'] <= 0) ? 'disabled' : ''; ?>>
                                                                <?php echo htmlspecialchars($med['nama']); ?> (Stok: <?php echo $med['stok']; ?> <?php echo $med['satuan'] ?? 'Tablet'; ?>)
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-text-muted mb-1">Jumlah Obat</label>
                                                <input type="number" name="jml_obat" value="1" min="1" class="w-full text-xs p-2 bg-app-bg border rounded-lg">
                                            </div>
                                        </div>

                                        <button type="submit" class="w-full py-2.5 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-lg text-xs transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                                            <i data-lucide="check-circle" class="w-4 h-4"></i> Berikan Penanganan & Selesaikan Pasien
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN (1/3): PEMANTAUAN STOK OBAT FARMASI -->
            <div class="space-y-4">
                <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-4">
                    <div class="border-b border-border-color pb-3 flex justify-between items-center">
                        <div>
                            <h2 class="text-base font-bold text-deep-green flex items-center gap-2">
                                <i data-lucide="pill" class="w-5 h-5 text-primary-green"></i> Stok Obat Farmasi
                            </h2>
                            <p class="text-xs text-text-muted">Ketersediaan obat real-time</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                                <tr>
                                    <th class="px-3 py-2">Nama Obat</th>
                                    <th class="px-3 py-2 text-right">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-color text-text-main">
                                <?php foreach ($_SESSION['medicines'] as $med): ?>
                                    <tr>
                                        <td class="px-3 py-2.5">
                                            <span class="font-bold text-deep-green block"><?php echo htmlspecialchars($med['nama']); ?></span>
                                            <span class="text-text-muted text-[10px]"><?php echo htmlspecialchars($med['kategori'] ?? 'Obat'); ?></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold">
                                            <?php 
                                            $min_s = $med['min_stok'] ?? 20;
                                            if ($med['stok'] <= $min_s): 
                                            ?>
                                                <span class="px-2 py-0.5 bg-red-100 text-emergency-red rounded-full text-[10px]"><?php echo $med['stok']; ?> <?php echo $med['satuan'] ?? 'Tablet'; ?> (Kritis)</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px]"><?php echo $med['stok']; ?> <?php echo $med['satuan'] ?? 'Tablet'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>