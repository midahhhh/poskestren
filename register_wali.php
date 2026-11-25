<?php
session_start();
include 'koneksi.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_wali   = trim($_POST['nama_wali'] ?? '');
    $no_hp       = trim($_POST['no_hp'] ?? '');
    $nis_anak    = trim($_POST['nis_anak'] ?? '');
    $nama_anak   = trim($_POST['nama_anak'] ?? '');
    $asrama_anak = trim($_POST['asrama_anak'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if (empty($nama_wali) || empty($no_hp) || empty($nis_anak) || empty($nama_anak) || empty($asrama_anak) || empty($password)) {
        $error_message = 'Semua kolom formulir pendaftaran wajib diisi.';
    } else {
        // Cek apakah NIS santri sudah ada di tabel santri, jika belum masukkan
        $cek_santri = mysqli_query($conn, "SELECT * FROM santri WHERE nis = '$nis_anak'");
        if ($cek_santri && mysqli_num_rows($cek_santri) == 0) {
            mysqli_query($conn, "INSERT INTO santri (nis, nama_lengkap, asrama) VALUES ('$nis_anak', '$nama_anak', '$asrama_anak')");
        }

        // Menyimpan menggunakan kolom 'nama_lengkap' (sesuaikan jika di db Anda namanya berbeda)
        $query_insert = "INSERT INTO users (username, password, nama_lengkap, role, no_hp) VALUES ('$nis_anak', '$password', '$nama_wali', 'wali', '$no_hp')";
        
        if (mysqli_query($conn, $query_insert)) {
            $_SESSION['user_id'] = $nis_anak;
            $_SESSION['nama'] = $nama_wali;
            $_SESSION['role'] = 'wali';
            $_SESSION['linked_santri'] = [
                'nis' => $nis_anak,
                'nama' => $nama_anak,
                'asrama' => $asrama_anak,
                'status' => 'Sehat / Aktif Belajar'
            ];

            header('Location: portal_wali.php');
            exit;
        } else {
            $error_message = 'Gagal mendaftarkan akun ke database: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Akun Wali Santri — Poskestren Al-Bahjah</title>
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
<body class="bg-app-bg text-text-main min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl border border-border-color p-8 space-y-6">
        <div class="text-center space-y-2">
            <a href="index.php" class="inline-flex items-center justify-center w-12 h-12 bg-deep-green text-white rounded-xl shadow-md mb-2">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </a>
            <h1 class="text-2xl font-bold text-deep-green">Pendaftaran Wali Santri</h1>
            <p class="text-xs text-text-muted">Masukkan data diri dan informasi anak Anda di Poskestren</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="p-4 rounded-xl bg-red-50 border border-emergency-red/30 text-emergency-red text-xs flex items-start gap-3">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="register_wali.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-text-main mb-1">Nama Lengkap Orang Tua / Wali</label>
                <input type="text" name="nama_wali" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white" placeholder="Cth: Bpk. H. Abdullah">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-main mb-1">Nomor WhatsApp / HP</label>
                    <input type="text" name="no_hp" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white" placeholder="0812xxxxxxxx">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-main mb-1">Nomor Induk Santri (NIS Anak)</label>
                    <input type="text" name="nis_anak" id="nis_anak" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white font-mono" placeholder="Cth: 2411070087">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-text-main mb-1">Nama Lengkap Anak (Santri)</label>
                    <input type="text" name="nama_anak" id="nama_anak" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white" placeholder="Nama santri...">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-main mb-1">Asrama / Kamar Anak</label>
                    <input type="text" name="asrama_anak" id="asrama_anak" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white" placeholder="Cth: Asrama Khadijah A">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-text-main mb-1">Kata Sandi Akun Portal</label>
                <input type="password" name="password" required class="w-full text-xs p-3 bg-app-bg border border-border-color rounded-xl focus:outline-none focus:border-primary-green focus:bg-white" placeholder="••••••••">
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-primary-green hover:bg-deep-green text-white font-semibold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 text-xs">
                <span>Daftar & Hubungkan Data Anak</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>

        <div class="text-center pt-4 border-t border-border-color text-xs text-text-muted">
            Sudah memiliki akun? <a href="login.php" class="text-primary-green font-bold hover:underline">Masuk di sini</a>
        </div>
    </div>
    <script>
        lucide.createIcons();
        const dbSantri = {
            '2411070087': { nama: 'Hamidah Qudsyiatun Nufus', asrama: 'Asrama Khadijah A' },
            '2411070012': { nama: 'Ahmad Fauzan', asrama: 'Asrama Al-Fatih 3A' }
        };
        document.getElementById('nis_anak').addEventListener('input', function() {
            const nis = this.value.trim();
            if (dbSantri[nis]) {
                document.getElementById('nama_anak').value = dbSantri[nis].nama;
                document.getElementById('asrama_anak').value = dbSantri[nis].asrama;
            }
        });
    </script>
</body>
</html>