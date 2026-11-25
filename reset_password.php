<?php
// reset_password.php — Form ganti password via token (1 menit)
session_start();
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php"); exit;
}

$token = trim($_GET['token'] ?? '');
$error   = '';
$success = '';
$user    = null;

if (empty($token)) {
    $error = "Token tidak valid.";
} else {
    // Cari token yang masih valid
    $stmt = $conn->prepare(
        "SELECT pr.*, u.username, u.nama_lengkap, u.role
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        // Cek apakah token ada tapi sudah expired
        $stmt2 = $conn->prepare("SELECT expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
        $stmt2->bind_param("s", $token);
        $stmt2->execute();
        $expired = $stmt2->get_result()->fetch_assoc();

        if ($expired && $expired['used']) {
            $error = "Token ini sudah pernah digunakan. Silakan buat link reset baru.";
        } else {
            $error = "Link reset sudah kedaluwarsa. Silakan buat link reset baru.";
        }
    }
}

// PROSES GANTI PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $error = "Kata sandi minimal 6 karakter.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Konfirmasi kata sandi tidak cocok.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

        // Update password
        $stmt3 = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt3->bind_param("si", $hashed, $user['user_id']);
        $stmt3->execute();

        // Tandai token sudah dipakai
        $stmt4 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt4->bind_param("s", $token);
        $stmt4->execute();

        $redirect = ($user['role'] === 'santri_wali') ? 'login.php' : 'login_petugas.php';
        set_flash('success', "Kata sandi berhasil diubah. Silakan login dengan kata sandi baru.");
        header("Location: $redirect"); exit;
    }
}

// Hitung sisa waktu
$sisa_detik = 0;
if ($user) {
    $sisa_detik = max(0, strtotime($user['expires_at']) - time());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Poskestren Al-Bahjah</title>
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
        .btn-primary:disabled { background:#9ca3af; cursor:not-allowed; }
        .strength-bar { height:4px; border-radius:2px; transition:all .3s; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">

    <!-- Brand -->
    <div class="text-center mb-8">
        <div class="w-12 h-12 rounded-xl bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-lg mx-auto mb-3">AB</div>
        <h1 class="text-xl font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</h1>
        <p class="text-sm text-gray-500 mt-1">Buat Kata Sandi Baru</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-[#d8e6de] p-8">

        <?php if (!empty($error) && !$user): ?>
            <!-- Token tidak valid / expired -->
            <div class="text-center py-4">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h2 class="text-base font-bold text-gray-800 mb-2">Link Tidak Valid</h2>
                <p class="text-sm text-gray-500 mb-5"><?= $error; ?></p>
                <a href="lupa_password.php" class="inline-block px-5 py-2.5 bg-[#16845B] text-white font-semibold rounded-xl text-sm hover:bg-[#0B3D2E] transition">
                    Buat Link Reset Baru
                </a>
            </div>

        <?php elseif ($user): ?>

            <!-- Info user + countdown -->
            <div class="mb-5 p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
                <div class="w-9 h-9 bg-[#16845B] text-white rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-[#0B3D2E] truncate"><?= htmlspecialchars($user['nama_lengkap']); ?></div>
                    <div class="text-xs text-gray-500">@<?= htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div id="countdown" class="text-lg font-bold text-red-600 font-mono"></div>
                    <div class="text-[10px] text-gray-400">tersisa</div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php?token=<?= urlencode($token); ?>" id="reset-form">

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kata Sandi Baru</label>
                    <div class="relative">
                        <input type="password" name="new_password" id="new_password" class="input-field pr-10"
                               placeholder="Minimal 6 karakter" required minlength="6" oninput="checkStrength(this.value)">
                        <button type="button" onclick="toggleVis('new_password', this)"
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="mt-1.5 flex gap-1">
                        <div id="s1" class="strength-bar flex-1 bg-gray-200"></div>
                        <div id="s2" class="strength-bar flex-1 bg-gray-200"></div>
                        <div id="s3" class="strength-bar flex-1 bg-gray-200"></div>
                        <div id="s4" class="strength-bar flex-1 bg-gray-200"></div>
                    </div>
                    <p id="strength-text" class="text-[11px] text-gray-400 mt-0.5"></p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Konfirmasi Kata Sandi</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" class="input-field pr-10"
                               placeholder="Ulangi kata sandi baru" required>
                        <button type="button" onclick="toggleVis('confirm_password', this)"
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="submit-btn" class="btn-primary">Simpan Kata Sandi Baru</button>
            </form>

        <?php endif; ?>
    </div>

    <p class="text-center text-sm text-gray-400 mt-5">
        <a href="login_petugas.php" class="hover:underline text-[#16845B]">&larr; Kembali ke Login</a>
    </p>
</div>

<?php if ($user && $sisa_detik > 0): ?>
<script>
    let sisa = <?= $sisa_detik; ?>;
    const el  = document.getElementById('countdown');
    const btn = document.getElementById('submit-btn');

    (function tick() {
        const m = Math.floor(sisa / 60);
        const s = sisa % 60;
        el.textContent = m + ':' + String(s).padStart(2, '0');
        if (sisa <= 10) el.classList.add('text-red-700');
        if (sisa <= 0) {
            el.textContent = 'HABIS';
            el.classList.add('text-gray-400');
            btn.disabled = true;
            btn.textContent = 'Link Kedaluwarsa';
            document.querySelectorAll('.input-field').forEach(i => i.disabled = true);
            return;
        }
        sisa--;
        setTimeout(tick, 1000);
    })();

    function toggleVis(id, btn) {
        const input = document.getElementById(id);
        input.type  = input.type === 'password' ? 'text' : 'password';
    }

    function checkStrength(val) {
        const bars  = [document.getElementById('s1'),document.getElementById('s2'),document.getElementById('s3'),document.getElementById('s4')];
        const label = document.getElementById('strength-text');
        bars.forEach(b => b.style.background = '#e5e7eb');

        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const colors = ['#ef4444','#f97316','#eab308','#16845B'];
        const labels = ['Sangat Lemah','Lemah','Sedang','Kuat'];
        for (let i = 0; i < score; i++) bars[i].style.background = colors[score-1];
        label.textContent = score > 0 ? labels[score-1] : '';
        label.style.color = score > 0 ? colors[score-1] : '';
    }
</script>
<?php endif; ?>

</body>
</html>
