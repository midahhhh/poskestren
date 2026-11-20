<?php
// lupa_password.php — Kirim token reset password (berlaku 1 menit)
session_start();
require_once 'koneksi.php';

// Jika sudah login, tidak perlu di sini
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php"); exit;
}

// Buat tabel reset jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token      VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used       TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$target = $_GET['for'] ?? 'petugas'; // 'petugas' atau 'wali'
$error   = '';
$success = '';
$step    = 'request'; // 'request' | 'sent'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $error = "Masukkan username atau nomor HP.";
    } else {
        // Cari user
        $stmt = $conn->prepare("SELECT id, nama_lengkap, role, username FROM users WHERE (username = ? OR no_hp = ?) AND is_active = 1 LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = "Akun dengan username/nomor '$identifier' tidak ditemukan.";
        } elseif ($target === 'petugas' && $user['role'] === 'santri_wali') {
            $error = "Akun ini adalah Wali Santri. Gunakan <a href='lupa_password.php?for=wali' class='underline font-semibold'>form reset wali santri</a>.";
        } elseif ($target === 'wali' && $user['role'] !== 'santri_wali') {
            $error = "Akun ini adalah Petugas Medis. Gunakan <a href='lupa_password.php?for=petugas' class='underline font-semibold'>form reset petugas</a>.";
        } else {
            // Hapus token lama yang belum dipakai
            $conn->query("DELETE FROM password_resets WHERE user_id = {$user['id']}");

            // Buat token baru, expired 1 menit
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 60); // 60 detik

            $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $user['id'], $token, $expires_at);
            $stmt2->execute();

            // Simpan di session untuk link langsung (karena tidak ada email)
            $_SESSION['reset_token_preview'] = $token;
            $_SESSION['reset_nama']          = $user['nama_lengkap'];

            $step    = 'sent';
            $success = $token;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password — Poskestren Al-Bahjah</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f4f2; }
        .input-field {
            width: 100%; padding: 10px 14px;
            border: 1px solid #d1ddd7; border-radius: 8px;
            font-size: 14px; background: #f8faf9; outline: none;
            transition: border .15s, background .15s;
        }
        .input-field:focus { border-color: #16845B; background: #fff; box-shadow: 0 0 0 3px rgba(22,132,91,.1); }
        .btn-primary { width:100%; padding:11px; background:#16845B; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:background .15s; }
        .btn-primary:hover { background:#0B3D2E; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">

    <!-- Brand -->
    <div class="text-center mb-8">
        <div class="w-12 h-12 rounded-xl bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg mx-auto mb-3">AB</div>
        <h1 class="text-xl font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</h1>
        <p class="text-sm text-gray-500 mt-1">Reset Kata Sandi</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-[#d8e6de] p-8">

        <?php if ($step === 'request'): ?>

            <div class="mb-5">
                <h2 class="text-base font-bold text-[#0B3D2E] mb-1">Lupa Kata Sandi?</h2>
                <p class="text-sm text-gray-500">Masukkan username atau nomor HP akunmu. Link reset akan tersedia selama <strong class="text-red-600">1 menit</strong>.</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="lupa_password.php?for=<?= htmlspecialchars($target); ?>">
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        <?= $target === 'wali' ? 'Nomor WhatsApp / NIS Santri' : 'Username Akun'; ?>
                    </label>
                    <input type="text" name="identifier" class="input-field"
                           placeholder="<?= $target === 'wali' ? '08123... atau NIS' : 'admin / nakes1'; ?>"
                           required autofocus>
                </div>
                <button type="submit" class="btn-primary">Buat Link Reset Password</button>
            </form>

        <?php else: // step = sent ?>

            <?php
            $reset_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                       . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . urlencode($success);
            ?>

            <div class="mb-4 text-center">
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="text-base font-bold text-[#0B3D2E]">Link Reset Siap</h2>
                <p class="text-sm text-gray-500 mt-1">Halo, <strong><?= htmlspecialchars($_SESSION['reset_nama'] ?? ''); ?></strong>!</p>
            </div>

            <!-- Countdown -->
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-center">
                <p class="text-xs text-gray-500 mb-1">Link akan kedaluwarsa dalam</p>
                <div id="countdown" class="text-3xl font-bold text-red-600 font-mono">1:00</div>
                <p class="text-xs text-red-500 mt-1">Setelah itu, buat link baru.</p>
            </div>

            <!-- Link reset -->
            <div class="mb-5 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-xs text-gray-400 mb-2 font-semibold uppercase tracking-wide">Link Reset Password:</p>
                <p class="text-xs text-gray-600 break-all font-mono bg-white p-2 rounded border border-gray-100"><?= htmlspecialchars($reset_url); ?></p>
            </div>

            <a href="<?= htmlspecialchars($reset_url); ?>" id="reset-link"
               class="block w-full text-center py-3 bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold rounded-xl text-sm transition mb-3">
                Klik di Sini untuk Reset Password →
            </a>
            <p class="text-xs text-gray-400 text-center">Link ini hanya berlaku 1 menit dari sekarang.</p>

        <?php endif; ?>

    </div>

    <p class="text-center text-sm text-gray-400 mt-5">
        <a href="<?= $target === 'wali' ? 'login.php' : 'login_petugas.php'; ?>" class="hover:underline text-[#16845B]">
            &larr; Kembali ke Login
        </a>
    </p>
</div>

<?php if ($step === 'sent'): ?>
<script>
    // Countdown 60 detik
    let seconds = 60;
    const el    = document.getElementById('countdown');
    const link  = document.getElementById('reset-link');

    const timer = setInterval(() => {
        seconds--;
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        el.textContent = m + ':' + String(s).padStart(2, '0');
        if (seconds <= 10) el.classList.add('text-red-700');
        if (seconds <= 0) {
            clearInterval(timer);
            el.textContent = 'KEDALUWARSA';
            el.classList.remove('text-red-600');
            el.classList.add('text-gray-400');
            link.style.pointerEvents = 'none';
            link.style.opacity = '0.4';
            link.textContent = 'Link Sudah Kedaluwarsa';
        }
    }, 1000);
</script>
<?php endif; ?>

</body>
</html>
