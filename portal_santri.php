<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'santri') {
    header('Location: login.php');
    exit;
}

$nama_santri = $_SESSION['nama'] ?? 'Hamidah Qudsyiatun Nufus';
$nis_santri = $_SESSION['nis'] ?? '2411070087';
$dokter_jaga = $_SESSION['dokter_aktif'] ?? [
    'nama' => 'Dr. dr. H. Moh. Faqih, Sp.A',
    'spesialisasi' => 'Dokter Spesialis Anak & Kesehatan Pesantren',
    'jadwal' => 'Senin & Kamis (09.00 - 13.00 WIB)'
];

// Simulasi Riwayat Rekam Medis Sesuai Nama Santri
$all_medical_records = [
    'Hamidah Qudsyiatun Nufus' => [
        ['tanggal' => '15 Agustus 2026', 'keluhan' => 'Pusing & Masuk Angin Ringan', 'diagnosa' => 'Fatigue / Kelelahan Ringan', 'tindakan' => 'Pemberian Vitamin & Istirahat 1 Jam', 'status' => 'Selesai'],
        ['tanggal' => '20 September 2026', 'keluhan' => 'Demam ringan & Batuk', 'diagnosa' => 'Faringitis Akut', 'tindakan' => 'Pemberian Paracetamol & Lozenges', 'status' => 'Selesai']
    ],
    'Ahmad Fauzan' => [
        ['tanggal' => '24 Oktober 2026', 'keluhan' => 'Demam tinggi mendadak (39.5°C)', 'diagnosa' => 'Febris Tinggi / Suspek Infeksi', 'tindakan' => 'Kompres Hangat & Infus Observasi Poskes', 'status' => 'Dirawat / Di Poskes']
    ]
];

$riwayat_santri = $all_medical_records[$nama_santri] ?? [
    ['tanggal' => 'Hari Ini', 'keluhan' => 'Pemeriksaan Rutin Berkala', 'diagnosa' => 'Sehat / Normal', 'tindakan' => 'Edukasi Kesehatan', 'status' => 'Normal']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Santri — Poskestren Al-Bahjah Bogor</title>
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
<body class="bg-app-bg text-text-main min-h-screen flex flex-col justify-between">

    <header class="bg-white border-b border-border-color sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-20 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary-green text-white rounded-xl flex items-center justify-center font-bold shadow-md">
                    <i data-lucide="user" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-sm font-bold text-deep-green block leading-tight">Portal Santri</span>
                    <span class="text-xs text-text-muted">Poskestren Al-Bahjah Bogor</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-deep-green"><?php echo htmlspecialchars($nama_santri); ?></p>
                    <p class="text-[11px] text-text-muted">NIS: <?php echo htmlspecialchars($nis_santri); ?></p>
                </div>
                <a href="logout.php" class="p-2 bg-red-50 hover:bg-emergency-red text-emergency-red hover:text-white rounded-xl transition-colors" title="Keluar">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6 w-full">
        <div class="bg-gradient-to-r from-deep-green to-primary-green text-white p-6 sm:p-8 rounded-2xl shadow-sm space-y-2">
            <span class="px-3 py-1 bg-white/20 text-white rounded-full text-[11px] font-semibold">Akun Terautentikasi</span>
            <h1 class="text-2xl sm:text-3xl font-bold">Ahlan wa Sahlan, <?php echo htmlspecialchars($nama_santri); ?></h1>
            <p class="text-xs sm:text-sm text-gray-200">Berikut adalah rekam medis dan informasi kesehatan pribadi Anda di Poskestren.</p>
        </div>

        <!-- Dokter Pemeriksa Aktif -->
        <div class="bg-white p-6 rounded-2xl border border-border-color shadow-sm flex items-start gap-4">
            <div class="w-12 h-12 bg-primary-green/10 text-primary-green rounded-xl flex items-center justify-center shrink-0">
                <i data-lucide="stethoscope" class="w-6 h-6"></i>
            </div>
            <div class="space-y-1">
                <span class="text-[11px] font-bold text-primary-green uppercase tracking-wider">Dokter Penanggung Jawab Poskes</span>
                <h3 class="text-base font-bold text-deep-green"><?php echo htmlspecialchars($dokter_jaga['nama']); ?></h3>
                <p class="text-xs text-text-muted"><?php echo htmlspecialchars($dokter_jaga['spesialisasi']); ?> • Jadwal: <?php echo htmlspecialchars($dokter_jaga['jadwal']); ?></p>
            </div>
        </div>

        <!-- Rekam Medis Sesuai Nama Santri -->
        <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-4">
            <h2 class="text-base font-bold text-deep-green flex items-center gap-2">
                <i data-lucide="file-text" class="w-5 h-5 text-primary-green"></i> Rekam Medis & Riwayat Kunjungan Kesehatan Anda
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Keluhan Utama</th>
                            <th class="px-4 py-3">Diagnosa Medis</th>
                            <th class="px-4 py-3">Tindakan / Terapi</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-color text-text-main">
                        <?php foreach ($riwayat_santri as $rm): ?>
                            <tr>
                                <td class="px-4 py-4 font-medium"><?php echo $rm['tanggal']; ?></td>
                                <td class="px-4 py-4"><?php echo $rm['keluhan']; ?></td>
                                <td class="px-4 py-4 font-bold text-deep-green"><?php echo $rm['diagnosa']; ?></td>
                                <td class="px-4 py-4"><?php echo $rm['tindakan']; ?></td>
                                <td class="px-4 py-4 text-right">
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-semibold text-[10px]"><?php echo $rm['status']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="bg-deep-green text-white py-6 mt-12 text-center text-xs text-gray-300">
        &copy; 2026 Poskestren Al-Bahjah Bogor. Portal Santri Terpadu.
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>