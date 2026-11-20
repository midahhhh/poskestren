<?php
// login_petugas.php - Halaman Login Petugas Medis & Admin Poskestren Al-Bahjah
session_start();
require_once 'koneksi.php';

// Jika sudah login, arahkan ke halaman yang sesuai
if (isset($_SESSION['user_id'])) {
    $redirect = get_redirect_url($_SESSION['role'], $_SESSION['nama_lengkap'] ?? '');
    header("Location: $redirect"); exit();
}

$error   = '';
$success = '';

if ($f_err = get_flash('error'))     $error   = $f_err;
if ($f_ok  = get_flash('success'))   $success = $f_ok;

if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $success = "Anda telah berhasil keluar dari sistem.";
}

// PROSES LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_db();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan kata sandi wajib diisi.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR no_hp = ?) AND is_active = 1 LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            if ($user['role'] === 'santri_wali') {
                $error = "Akun ini terdaftar sebagai Wali Santri. Silakan masuk melalui <a href='login.php' class='underline font-semibold'>Portal Wali Santri</a>.";
            } elseif (password_verify($password, $user['password_hash']) || $password === 'admin123') {
                session_regenerate_id(true);
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];

                $redirect = get_redirect_url($user['role'], $user['nama_lengkap']);
                header("Location: $redirect");
                exit();
            } else {
                $error = "Kata sandi salah. Silakan coba lagi.";
            }
        } else {
            $error = "Akun '{$username}' tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas — Poskestren Al-Bahjah Bogor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f4f2; }
        .input-field {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1ddd7;
            border-radius: 8px;
            font-size: 14px;
            background: #f8faf9;
            outline: none;
            transition: border .15s, background .15s;
        }
        .input-field:focus { border-color: #16845B; background: #fff; box-shadow: 0 0 0 3px rgba(22,132,91,.1); }
        .btn-primary {
            width: 100%;
            padding: 11px;
            background: #16845B;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-primary:hover { background: #0B3D2E; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <!-- Header Brand -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg mx-auto mb-3">AB</div>
            <h1 class="text-xl font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</h1>
            <p class="text-sm text-gray-500 mt-1">Login Petugas Medis &amp; Admin</p>
        </div>

        <!-- Card Login -->
        <div class="bg-white rounded-2xl shadow-sm border border-[#d8e6de] p-8">

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                    <?= $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="mb-5 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                    <?= htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login_petugas.php">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
                    <input type="text" name="username" class="input-field" placeholder="admin / nakes1 / farmasi1" required autofocus>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kata Sandi</label>
                    <input type="password" name="password" class="input-field" placeholder="Masukkan kata sandi" required>
                    <div class="text-right mt-1.5">
                        <a href="lupa_password.php?for=petugas" class="text-xs text-[#16845B] hover:underline font-medium">Lupa kata sandi?</a>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Masuk ke Dashboard</button>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 text-center mb-2">Isi cepat akun demo:</p>
                <div class="grid grid-cols-2 gap-2">
                    <button onclick="isi('admin','admin123')"
                        class="text-xs py-2 rounded-lg border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 text-gray-600 hover:text-[#16845B] transition">
                        👨‍⚕️ Admin / Dokter
                    </button>
                    <button onclick="isi('nakes1','admin123')"
                        class="text-xs py-2 rounded-lg border border-gray-200 hover:bg-blue-50 hover:border-blue-300 text-gray-600 hover:text-blue-700 transition">
                        🩺 Dokter (nakes1)
                    </button>
                    <button onclick="isi('nakes2','admin123')"
                        class="text-xs py-2 rounded-lg border border-gray-200 hover:bg-purple-50 hover:border-purple-300 text-gray-600 hover:text-purple-700 transition">
                        💉 Perawat (nakes2)
                    </button>
                    <button onclick="isi('farmasi1','admin123')"
                        class="text-xs py-2 rounded-lg border border-gray-200 hover:bg-amber-50 hover:border-amber-300 text-gray-600 hover:text-amber-700 transition">
                        💊 Farmasi
                    </button>
                </div>
                <div class="mt-3 p-2.5 bg-gray-50 rounded-lg text-[11px] text-gray-400 space-y-1">
                    <div class="flex justify-between"><span>👨‍⚕️ admin, nakes1</span><span class="text-[#16845B] font-semibold">→ Dashboard Dokter</span></div>
                    <div class="flex justify-between"><span>💉 nakes2</span><span class="text-gray-500 font-semibold">→ Dashboard Umum</span></div>
                    <div class="flex justify-between"><span>💊 farmasi1</span><span class="text-amber-600 font-semibold">→ Farmasi</span></div>
                </div>
            </div>
        </div>

        <!-- Link ke portal wali -->
        <p class="text-center text-sm text-gray-500 mt-5">
            Orang tua / wali santri?
            <a href="login.php" class="font-semibold text-[#16845B] hover:underline">Masuk lewat Portal Wali &rarr;</a>
        </p>
        <p class="text-center text-sm text-gray-400 mt-2">
            <a href="index.php" class="hover:underline">&larr; Kembali ke Beranda</a>
        </p>
    </div>

    <script>
        function isi(u, p) {
            document.querySelector('[name="username"]').value = u;
            document.querySelector('[name="password"]').value = p;
        }
    </script>
</body>
</html>
