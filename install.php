<?php
// install.php - Web Installer & Database Dummy Seeder untuk Poskestren Al-Bahjah
require_once 'koneksi.php';

$pesan = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    $sql_file = __DIR__ . '/database_dummy.sql';
    if (!file_exists($sql_file)) {
        $pesan = "File database_dummy.sql tidak ditemukan di direktori proyek.";
    } else {
        $sql = file_get_contents($sql_file);
        
        // Nonaktifkan foreign key checks sementara untuk import bersih
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Eksekusi multi query
        if ($conn->multi_query($sql)) {
            do {
                // Simpan hasil atau lewati ke query berikutnya
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            $sukses = true;
            $pesan = "Database berhasil diinisialisasi dan seluruh data dummy telah masuk ke database!";
        } else {
            $pesan = "Gagal mengeksekusi SQL: " . $conn->error;
        }
    }
}

// Cek status tabel
$tabel_count = 0;
$res = $conn->query("SHOW TABLES");
if ($res) {
    $tabel_count = $res->num_rows;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inisialisasi Database Dummy | Poskestren Al-Bahjah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#F5F8F6] text-[#182923] min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-2xl border border-[#DCE7E0] p-8 shadow-xl">
        <div class="flex items-center gap-3 pb-4 border-b border-[#DCE7E0] mb-6">
            <div class="w-10 h-10 rounded-xl bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg">
                AB
            </div>
            <div>
                <h1 class="text-lg font-extrabold text-[#0B3D2E]">Setup Database Dummy</h1>
                <p class="text-xs text-[#64756D]">Poskestren Al-Bahjah Cabang Bogor</p>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="mb-6 p-4 rounded-xl text-xs font-semibold <?= $sukses ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                <?= $sukses ? '✓ ' : '⚠ '; ?><?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="space-y-4 text-xs text-[#64756D] mb-6">
            <div class="p-4 bg-[#f0f4f2] rounded-xl border border-[#DCE7E0] space-y-2">
                <div class="flex justify-between font-semibold text-[#182923]">
                    <span>Host Terhubung:</span>
                    <span class="font-mono text-[#0B3D2E]"><?= htmlspecialchars($host); ?>:<?= $port; ?></span>
                </div>
                <div class="flex justify-between font-semibold text-[#182923]">
                    <span>Nama Database:</span>
                    <span class="font-mono text-[#16845B]"><?= htmlspecialchars($database); ?></span>
                </div>
                <div class="flex justify-between font-semibold text-[#182923]">
                    <span>Jumlah Tabel Saat Ini:</span>
                    <span class="font-bold"><?= $tabel_count; ?> Tabel</span>
                </div>
            </div>

            <p>Halaman ini akan mengimpor seluruh struktur tabel dan <strong>data dummy</strong> yang dibutuhkan oleh aplikasi Poskestren Al-Bahjah Bogor (Akun Nakes, Santri, Kriteria SAW, Obat, dan Rekam Medis).</p>
        </div>

        <?php if ($sukses): ?>
            <div class="space-y-3">
                <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900 space-y-1">
                    <p class="font-bold">Informasi Akun Login Default:</p>
                    <p>• <strong>Admin / Dokter:</strong> <code>admin</code> (Password: <code>admin123</code>)</p>
                    <p>• <strong>Perawat:</strong> <code>nakes1</code> (Password: <code>admin123</code>)</p>
                    <p>• <strong>Farmasi:</strong> <code>farmasi1</code> (Password: <code>admin123</code>)</p>
                </div>
                <div class="flex gap-2">
                    <a href="login_petugas.php" class="flex-1 py-3 text-center rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition">
                        Buka Halaman Login Petugas &rarr;
                    </a>
                    <a href="index.php" class="px-5 py-3 text-center rounded-xl border border-[#DCE7E0] bg-white text-[#0B3D2E] text-xs font-bold hover:bg-slate-50 transition">
                        Beranda
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="install.php">
                <input type="hidden" name="action" value="install">
                <button type="submit" onclick="return confirm('Mulai inisialisasi dan impor data dummy ke database?')" class="w-full py-3.5 rounded-xl bg-[#0B3D2E] hover:bg-[#16845B] text-white text-xs font-bold transition shadow-sm">
                    Inisialisasi & Isi Data Dummy Sekarang
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
