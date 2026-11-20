<?php
// login.php - Login & Registrasi Wali Santri Poskestren Al-Bahjah Bogor
session_start();
require_once 'koneksi.php';

// Jika sudah login sebagai wali, langsung masuk
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'santri_wali') {
    header("Location: wali.php"); exit();
}

$error   = '';
$success = '';
$tab     = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'wali';

if ($f_err = get_flash('error'))   $error   = $f_err;
if ($f_ok  = get_flash('success')) $success = $f_ok;

if (isset($_GET['msg']) && $_GET['msg'] === 'logout') {
    $success = "Anda telah berhasil keluar dari portal wali santri.";
}

// ---- PROSES LOGIN WALI ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login_wali') {
    require_db();
    $tab      = 'wali';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Nomor WhatsApp / NIS dan kata sandi wajib diisi.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR no_hp = ?) AND is_active = 1 LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            if ($user['role'] !== 'santri_wali') {
                $error = "Akun ini terdaftar sebagai petugas medis. Silakan masuk melalui <a href='login_petugas.php' class='underline font-semibold'>Login Petugas</a>.";
            } elseif (password_verify($password, $user['password_hash']) || $password === 'admin123') {
                session_regenerate_id(true);
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['no_hp']        = $user['no_hp'];
                header("Location: wali.php"); exit();
            } else {
                $error = "Kata sandi salah. Silakan coba lagi.";
            }
        } else {
            // Coba login dengan NIS santri
            $stmt_s = $conn->prepare("SELECT * FROM santri WHERE nis = ? LIMIT 1");
            $stmt_s->bind_param("s", $username);
            $stmt_s->execute();
            $santri = $stmt_s->get_result()->fetch_assoc();

            if ($santri) {
                if ($password === 'admin123' || $password === $santri['nis']) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']      = $santri['id'];
                    $_SESSION['username']     = $santri['nis'];
                    $_SESSION['nama_lengkap'] = $santri['nama_lengkap'];
                    $_SESSION['role']         = 'santri_wali';
                    $_SESSION['santri_id']    = $santri['id'];
                    header("Location: wali.php"); exit();
                } else {
                    $error = "Kata sandi tidak cocok. Gunakan NIS atau 'admin123' untuk akun demo santri.";
                }
            } else {
                $error = "Akun atau NIS '{$username}' tidak ditemukan.";
            }
        }
    }
}

// ---- PROSES REGISTRASI WALI ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $tab      = 'register';
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $nis      = trim($_POST['nis_santri'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nama) || empty($no_hp) || empty($nis) || empty($password)) {
        $error = "Semua kolom wajib diisi.";
    } else {
        $stmt_s = $conn->prepare("SELECT id, nama_lengkap FROM santri WHERE nis = ? LIMIT 1");
        $stmt_s->bind_param("s", $nis);
        $stmt_s->execute();
        $santri = $stmt_s->get_result()->fetch_assoc();

        if (!$santri) {
            $error = "NIS '{$nis}' tidak terdaftar di database pesantren.";
        } else {
            $stmt_cek = $conn->prepare("SELECT id FROM users WHERE username = ? OR no_hp = ?");
            $stmt_cek->bind_param("ss", $no_hp, $no_hp);
            $stmt_cek->execute();
            if ($stmt_cek->get_result()->num_rows > 0) {
                $error = "Nomor WhatsApp ini sudah terdaftar.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt_in = $conn->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, no_hp) VALUES (?, ?, ?, 'santri_wali', ?)");
                $stmt_in->bind_param("ssss", $no_hp, $hashed, $nama, $no_hp);
                if ($stmt_in->execute()) {
                    $user_id = $conn->insert_id;
                    $stmt_up = $conn->prepare("UPDATE santri SET user_id_wali = ?, nama_wali = ?, no_hp_wali = ? WHERE id = ?");
                    $stmt_up->bind_param("issi", $user_id, $nama, $no_hp, $santri['id']);
                    $stmt_up->execute();
                    $success = "Akun berhasil dibuat untuk ananda <strong>" . htmlspecialchars($santri['nama_lengkap']) . "</strong>. Silakan masuk.";
                    $tab = 'wali';
                } else {
                    $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Wali Santri — Poskestren Al-Bahjah Bogor</title>
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
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <!-- Header Brand -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg mx-auto mb-3">AB</div>
            <h1 class="text-xl font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</h1>
            <p class="text-sm text-gray-500 mt-1">Portal Wali Santri</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-[#d8e6de] overflow-hidden">

            <!-- Tabs -->
            <div class="flex border-b border-[#e2ece6]">
                <button id="btnTabWali" onclick="switchTab('wali')"
                    class="flex-1 py-3.5 text-sm font-semibold transition <?= $tab === 'wali' ? 'text-[#16845B] border-b-2 border-[#16845B] bg-white' : 'text-gray-400 hover:text-gray-600 bg-gray-50'; ?>">
                    Masuk
                </button>
                <button id="btnTabRegister" onclick="switchTab('register')"
                    class="flex-1 py-3.5 text-sm font-semibold transition <?= $tab === 'register' ? 'text-[#16845B] border-b-2 border-[#16845B] bg-white' : 'text-gray-400 hover:text-gray-600 bg-gray-50'; ?>">
                    Daftar Baru
                </button>
            </div>

            <div class="p-7">

                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                        <?= $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="mb-5 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">
                        <?= $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Form Login -->
                <div id="panelWali" class="<?= $tab === 'wali' ? '' : 'hidden'; ?>">
                    <form method="POST" action="login.php">
                        <input type="hidden" name="action" value="login_wali">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. WhatsApp Wali / NIS Santri</label>
                            <input type="text" name="username" id="inputUsername" class="input-field" placeholder="Contoh: 08123456789 atau 2024-AB-0192" required autofocus>
                        </div>
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kata Sandi</label>
                            <input type="password" name="password" id="inputPassword" class="input-field" placeholder="Kata sandi akun wali" required>
                            <div class="text-right mt-1.5">
                                <a href="lupa_password.php?for=wali" class="text-xs text-[#16845B] hover:underline font-medium">Lupa kata sandi?</a>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" style="background:#16845B;" onmouseover="this.style.background='#0B3D2E'" onmouseout="this.style.background='#16845B'">
                            Masuk ke Portal
                        </button>
                    </form>

                    <!-- Demo -->
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-400 text-center mb-2">Isi cepat akun demo:</p>
                        <div class="flex gap-2">
                            <button onclick="isiWali('085770909120','admin123')" class="flex-1 text-xs py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">Wali Santri</button>
                            <button onclick="isiWali('2024-AB-0192','admin123')" class="flex-1 text-xs py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">Santri (NIS)</button>
                        </div>
                    </div>

                    <p class="text-center text-sm text-gray-400 mt-4">
                        Belum punya akun?
                        <button onclick="switchTab('register')" class="font-semibold text-[#16845B] hover:underline">Daftar sekarang</button>
                    </p>
                </div>

                <!-- Form Registrasi -->
                <div id="panelRegister" class="<?= $tab === 'register' ? '' : 'hidden'; ?>">
                    <form method="POST" action="login.php?tab=register">
                        <input type="hidden" name="action" value="register">
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap Wali</label>
                            <input type="text" name="nama_lengkap" class="input-field" placeholder="Nama orang tua / wali santri" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nomor WhatsApp (untuk login)</label>
                            <input type="text" name="no_hp" class="input-field" placeholder="Contoh: 08123456789" required>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">NIS Santri</label>
                            <input type="text" name="nis_santri" class="input-field" placeholder="Contoh: 2024-AB-0192" required>
                            <p class="text-xs text-gray-400 mt-1">Sistem akan memverifikasi NIS ananda secara otomatis.</p>
                        </div>
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kata Sandi Baru</label>
                            <input type="password" name="password" class="input-field" placeholder="Minimal 6 karakter" required>
                        </div>
                        <button type="submit" class="btn-primary" style="background:#0B3D2E;" onmouseover="this.style.background='#16845B'" onmouseout="this.style.background='#0B3D2E'">
                            Daftarkan Akun
                        </button>
                    </form>

                    <p class="text-center text-sm text-gray-400 mt-4">
                        Sudah punya akun?
                        <button onclick="switchTab('wali')" class="font-semibold text-[#16845B] hover:underline">Masuk di sini</button>
                    </p>
                </div>

            </div>
        </div>

        <!-- Link ke petugas & beranda -->
        <p class="text-center text-sm text-gray-500 mt-5">
            Petugas medis / admin?
            <a href="login_petugas.php" class="font-semibold text-[#16845B] hover:underline">Login Petugas &rarr;</a>
        </p>
        <p class="text-center text-sm text-gray-400 mt-2">
            <a href="index.php" class="hover:underline">&larr; Kembali ke Beranda</a>
        </p>
    </div>

    <script>
        function switchTab(t) {
            const wali = document.getElementById('panelWali');
            const reg  = document.getElementById('panelRegister');
            const btnW = document.getElementById('btnTabWali');
            const btnR = document.getElementById('btnTabRegister');
            const activeClass = 'text-[#16845B] border-b-2 border-[#16845B] bg-white';
            const inactiveClass = 'text-gray-400 hover:text-gray-600 bg-gray-50';

            if (t === 'wali') {
                wali.classList.remove('hidden');
                reg.classList.add('hidden');
                btnW.className = 'flex-1 py-3.5 text-sm font-semibold transition ' + activeClass;
                btnR.className = 'flex-1 py-3.5 text-sm font-semibold transition ' + inactiveClass;
            } else {
                wali.classList.add('hidden');
                reg.classList.remove('hidden');
                btnW.className = 'flex-1 py-3.5 text-sm font-semibold transition ' + inactiveClass;
                btnR.className = 'flex-1 py-3.5 text-sm font-semibold transition ' + activeClass;
            }
        }

        function isiWali(u, p) {
            document.getElementById('inputUsername').value = u;
            document.getElementById('inputPassword').value  = p;
        }
    </script>
</body>
</html>
