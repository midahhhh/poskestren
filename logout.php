<?php
// logout.php - Keluar dari sesi Poskestren Al-Bahjah
session_start();

// Tentukan halaman logout tujuan sebelum session dihapus
$was_wali = (($_SESSION['role'] ?? '') === 'santri_wali');

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

if ($was_wali) {
    header("Location: login.php?msg=logout");
} else {
    header("Location: login_petugas.php?msg=logout");
}
exit();
