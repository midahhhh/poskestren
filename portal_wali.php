<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'wali') {
    header('Location: login.php');
    exit;
}

$nama_wali = $_SESSION['nama'] ?? 'Wali Santri';

// Mengambil data anak yang spesifik terhubung dengan akun wali yang sedang login
$data_anak = $_SESSION['linked_santri'] ?? [
    'nis' => '2411070087',
    'nama' => 'Hamidah Qudsyiatun Nufus',
    'asrama' => 'Asrama Khadijah A',
    'status' => 'Sehat / Aktif Belajar'
];

$dokter_jaga = $_SESSION['dokter_aktif'] ?? [
    'nama' => 'Dr. dr. H. Moh. Faqih, Sp.A',
    'spesialisasi' => 'Dokter Spesialis Anak & Kesehatan Pesantren',
    'jadwal' => 'Senin & Kamis (09.00 - 13.00 WIB)'
];

// Nomor WhatsApp CS Poskestren
$whatsapp_cs = '6281234567890';
$wa_message = urlencode("Assalamu'alaikum Wr. Wb. Petugas Poskestren Al-Bahjah Bogor, saya " . $nama_wali . " wali dari santri " . $data_anak['nama'] . " (NIS: " . $data_anak['nis'] . ") ingin berkonsultasi.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Wali Santri — Poskestren Al-Bahjah Bogor</title>
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
                <div class="w-10 h-10 bg-deep-green text-white rounded-xl flex items-center justify-center font-bold shadow-md">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-sm font-bold text-deep-green block leading-tight">Portal Keluarga & Wali Santri</span>
                    <span class="text-xs text-text-muted">Poskestren Al-Bahjah Bogor</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-deep-green"><?php echo htmlspecialchars($nama_wali); ?></p>
                    <p class="text-[11px] text-text-muted">Wali Terautentikasi</p>
                </div>
                <a href="logout.php" class="p-2 bg-red-50 hover:bg-emergency-red text-emergency-red hover:text-white rounded-xl transition-colors" title="Keluar">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6 w-full">
        <!-- Banner -->
        <div class="bg-white border border-border-color p-6 sm:p-8 rounded-2xl shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="space-y-2">
                <span class="px-3 py-1 bg-primary-green/10 text-primary-green rounded-full text-[11px] font-semibold">Layanan Transparansi Keluarga</span>
                <h1 class="text-xl sm:text-2xl font-bold text-deep-green">Selamat Datang, <?php echo htmlspecialchars($nama_wali); ?></h1>
                <p class="text-xs text-text-muted">Data kesehatan anak Anda terhubung otomatis dari sistem pesantren.</p>
            </div>
            <!-- Tombol WA CS -->
            <a href="https://wa.me/<?php echo $whatsapp_cs; ?>?text=<?php echo $wa_message; ?>" target="_blank" class="px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs flex items-center gap-2 shadow-sm transition-all shrink-0">
                <i data-lucide="phone-call" class="w-4 h-4"></i> <span>Hubungi CS Poskes (WhatsApp)</span>
            </a>
        </div>

        <!-- Dokter Pemeriksa Aktif -->
        <div class="bg-white p-6 rounded-2xl border border-border-color shadow-sm flex items-start gap-4">
            <div class="w-12 h-12 bg-deep-green/10 text-deep-green rounded-xl flex items-center justify-center shrink-0">
                <i data-lucide="stethoscope" class="w-6 h-6"></i>
            </div>
            <div class="space-y-1">
                <span class="text-[11px] font-bold text-deep-green uppercase tracking-wider">Dokter Spesialis / Pemeriksa Poskes Saat Ini</span>
                <h3 class="text-base font-bold text-deep-green"><?php echo htmlspecialchars($dokter_jaga['nama']); ?></h3>
                <p class="text-xs text-text-muted"><?php echo htmlspecialchars($dokter_jaga['spesialisasi']); ?> • Jadwal: <?php echo htmlspecialchars($dokter_jaga['jadwal']); ?></p>
            </div>
        </div>

        <!-- Informasi Anak Sesuai Pendaftaran NIS -->
        <div class="bg-white rounded-2xl border border-border-color shadow-sm p-6 space-y-6">
            <h2 class="text-base font-bold text-deep-green flex items-center gap-2">
                <i data-lucide="users" class="w-5 h-5 text-primary-green"></i> Keadaan & Rekam Medis Anak Anda
            </h2>

            <div class="p-5 bg-app-bg rounded-xl border border-border-color grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                <div>
                    <span class="text-[11px] text-text-muted font-semibold block">Nama & NIS Anak</span>
                    <h3 class="font-bold text-sm text-deep-green mt-0.5"><?php echo htmlspecialchars($data_anak['nama']); ?></h3>
                    <p class="text-xs text-text-muted">NIS: <?php echo htmlspecialchars($data_anak['nis']); ?></p>
                </div>
                <div>
                    <span class="text-[11px] text-text-muted font-semibold block">Asrama / Kamar</span>
                    <p class="text-xs font-bold text-text-main mt-0.5"><?php echo htmlspecialchars($data_anak['asrama']); ?></p>
                </div>
                <div class="sm:text-right">
                    <span class="text-[11px] text-text-muted font-semibold block mb-1">Status Kesehatan Saat Ini</span>
                    <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[11px]"><?php echo htmlspecialchars($data_anak['status']); ?></span>
                </div>
            </div>

            <!-- Tabel Riwayat Kesehatan Anak -->
            <div class="overflow-x-auto pt-2">
                <table class="w-full text-left text-xs">
                    <thead class="bg-app-bg text-text-muted border-b border-border-color uppercase font-semibold">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Keluhan</th>
                            <th class="px-4 py-3">Diagnosa Dokter</th>
                            <th class="px-4 py-3">Tindakan Poskes</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-color text-text-main">
                        <tr>
                            <td class="px-4 py-4 font-medium">15 Agustus 2026</td>
                            <td class="px-4 py-4">Pusing & Masuk Angin Ringan</td>
                            <td class="px-4 py-4 font-bold text-deep-green">Fatigue / Kelelahan Ringan</td>
                            <td class="px-4 py-4">Pemberian Vitamin & Istirahat 1 Jam</td>
                            <td class="px-4 py-4 text-right">
                                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-semibold text-[10px]">Selesai</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="bg-deep-green text-white py-6 mt-12 text-center text-xs text-gray-300">
        &copy; 2026 Poskestren Al-Bahjah Bogor. Portal Wali Santri Terpadu.
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>