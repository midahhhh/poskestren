<?php
// koneksi.php - Koneksi ke Database MySQL Poskestren Al-Bahjah Bogor & Sistem Helper Terpadu

// Konfigurasi Session untuk Serverless (Vercel) / Lingkungan Shared
if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || is_dir('/var/task')) {
        @ini_set('session.save_path', '/tmp');
    } elseif (!empty(session_save_path()) && !@is_writable(session_save_path())) {
        @ini_set('session.save_path', sys_get_temp_dir());
    }
    session_start();
}

// Konfigurasi Database (Mendukung Environment Variables untuk Vercel / Cloud Database & Fallback XAMPP Lokal)
$is_vercel = (getenv('VERCEL') == '1' || isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL']) || is_dir('/var/task') || (PHP_OS_FAMILY === 'Linux' && !file_exists('/opt/lampp')));
$default_host = $is_vercel ? "gateway01.ap-northeast-1.prod.aws.tidbcloud.com" : "localhost";
$default_port = $is_vercel ? 4000 : 3306;
$default_user = $is_vercel ? "UyGRhsQysVSR6rK.root" : "root";
$default_pass = $is_vercel ? "xxlAmK97oAPty9k3" : "";
$default_database = "poskestren_albahjah";

$host     = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: $default_host);
$user     = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: $default_user);
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : $default_pass);
$database = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: $default_database);
$port     = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: $default_port));

// Matikan exception throwing bawaan PHP 8.1+ agar tidak crash saat real_connect gagal
mysqli_report(MYSQLI_REPORT_OFF);

// Inisialisasi koneksi dengan dukungan SSL Cloud Database (seperti TiDB Cloud / Aiven)
$conn = mysqli_init();
if (!$conn) {
    die("Koneksi MySQL gagal diinisialisasi.");
}

$is_ssl = (getenv('DB_SSL') === 'true' || getenv('MYSQL_SSL') === 'true' || strpos($host, 'tidbcloud') !== false || strpos($host, 'aivencloud') !== false);
if ($is_ssl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
}

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

$db_connected = false;
$db_error = '';

try {
    $flags = $is_ssl ? MYSQLI_CLIENT_SSL : 0;
    $connected = @$conn->real_connect($host, $user, $password, $database, $port, NULL, $flags);
    if (!$connected || $conn->connect_error) {
        // Fallback otomatis: Jika localhost mati dan tidak ada custom DB_HOST, hubungkan ke TiDB Cloud
        if ($host === 'localhost' && !getenv('DB_HOST')) {
            $cloud_host = "gateway01.ap-northeast-1.prod.aws.tidbcloud.com";
            $cloud_port = 4000;
            $cloud_user = "UyGRhsQysVSR6rK.root";
            $cloud_pass = "xxlAmK97oAPty9k3";
            $cloud_db   = "poskestren_albahjah";

            $conn->close();
            $conn = mysqli_init();
            $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $cloud_connected = @$conn->real_connect($cloud_host, $cloud_user, $cloud_pass, $cloud_db, $cloud_port, NULL, MYSQLI_CLIENT_SSL);
            if ($cloud_connected && !$conn->connect_error) {
                $host = $cloud_host;
                $user = $cloud_user;
                $port = $cloud_port;
                $database = $cloud_db;
                $db_connected = true;
                @$conn->set_charset("utf8mb4");
            } else {
                $db_error = $conn->connect_error ?: mysqli_connect_error();
                $db_connected = false;
            }
        } else {
            $db_error = $conn->connect_error ?: mysqli_connect_error();
            $db_connected = false;
        }
    } else {
        $db_connected = true;
        @$conn->set_charset("utf8mb4");
    }
} catch (Throwable $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

// Helper: Memastikan koneksi database aktif pada halaman yang membutuhkan query
function require_db() {
    global $conn, $db_connected, $db_error, $host, $database, $port;
    if (!$db_connected || !$conn) {
        die("<div style='font-family:sans-serif;padding:24px;background:#fee2e2;color:#991b1b;border-radius:12px;max-width:640px;margin:40px auto;border:1px solid #f87171;'>
                <h3 style='margin-top:0;'>⚠️ Database Belum Terhubung</h3>
                <p>Sistem tidak dapat terhubung ke host database: <code>" . htmlspecialchars($host) . ":" . $port . "</code> dengan nama database <code>" . htmlspecialchars($database) . "</code>.</p>
                <p style='font-size:13px;color:#7f1d1d;'>Jika di Vercel, pastikan Environment Variable <b>DB_PASSWORD</b> sudah diisi di Vercel Project Settings (Settings -> Environment Variables).</p>
                <p style='font-size:13px;color:#7f1d1d;'>Untuk menginisialisasi atau mengisi data dummy, buka <a href='install.php' style='color:#16845B;font-weight:bold;text-decoration:underline;'>install.php</a>.</p>
                <div style='background:#fff;padding:8px 12px;border-radius:6px;font-family:monospace;font-size:12px;color:#b91c1c;'>Error: " . htmlspecialchars($db_error) . "</div>
             </div>");
    }
}

// Helper: Cek sesi login
function require_login($target = 'petugas') {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash_error'] = "Silakan login terlebih dahulu untuk mengakses sistem.";
        $target_url = ($target === 'wali') ? 'login.php' : 'login_petugas.php';
        header("Location: " . $target_url);
        exit();
    }
}

// Helper: Cek otorisasi role
function require_role($allowed_roles = []) {
    require_login();
    if (!in_array($_SESSION['role'], (array)$allowed_roles)) {
        $_SESSION['flash_error'] = "Akses Ditolak: Role Anda tidak memiliki izin untuk halaman ini.";
        header("Location: dashboard.php");
        exit();
    }
}

// Helper: Format tanggal Indonesia
function tgl_indo($datetime, $with_time = true) {
    if (!$datetime || $datetime == '0000-00-00 00:00:00') return '-';
    $timestamp = strtotime($datetime);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('d', $timestamp);
    $m = $bulan[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);
    $t = date('H:i', $timestamp);
    return $with_time ? "$d $m $y, $t WIB" : "$d $m $y";
}

// Helper: Format badge prioritas triase (Clean developer aesthetic)
function badge_triase($tingkat_prioritas, $skor = null) {
    if ($tingkat_prioritas == 'P1_Gawat_Darurat' || ($skor !== null && $skor >= 0.80)) {
        return '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-bold bg-red-100 text-red-900 border border-red-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                    P1 - Darurat
                </span>';
    } else if ($tingkat_prioritas == 'P2_Mendesak' || ($skor !== null && $skor >= 0.50)) {
        return '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                    P2 - Mendesak
                </span>';
    } else {
        return '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    P3 - Biasa
                </span>';
    }
}

// Helper: Tentukan halaman tujuan sesuai role & jabatan
function get_redirect_url($role, $nama_lengkap = '') {
    switch ($role) {
        case 'admin':
            return 'dashboard.php';

        case 'petugas_kesehatan':
            return 'dashboard_dokter.php';

        case 'petugas_farmasi':
            return 'dashboard_farmasi.php';

        case 'santri_wali':
            return 'wali.php';

        default:
            return 'dashboard.php';
    }
}
function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

// Helper: Mesin Kalkulasi SAW Terpusat
if (!function_exists('jalankan_kalkulasi_saw')) {
    function jalankan_kalkulasi_saw($conn) {
        // 1. Ambil seluruh kunjungan yang sedang menunggu triase / pemeriksaan
        $res_antrean = $conn->query("SELECT id, is_emergency_override FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase', 'pemeriksaan')");
        $antrean_ids = [];
        $is_override_map = [];
        if ($res_antrean) {
            while ($r = $res_antrean->fetch_assoc()) {
                $antrean_ids[] = (int)$r['id'];
                $is_override_map[$r['id']] = ((int)($r['is_emergency_override'] ?? 0) === 1);
            }
        }

        if (empty($antrean_ids)) return;

        $id_list_str = implode(',', $antrean_ids);

        // 2. Ambil nilai raw untuk setiap alternatif dan kriteria
        $res_vals = $conn->query("SELECT kunjungan_id, kriteria_id, nilai_raw FROM saw_nilai_pemeriksaan WHERE kunjungan_id IN ($id_list_str)");
        $matriks_x = [];
        if ($res_vals) {
            while ($v = $res_vals->fetch_assoc()) {
                $kid = (int)$v['kunjungan_id'];
                $crit = (int)$v['kriteria_id'];
                $matriks_x[$kid][$crit] = (float)$v['nilai_raw'];
            }
        }

        // Ambil nilai skala crisp maksimum dari saw_sub_kriteria (skala standar triase 1–5)
        $res_max = $conn->query("SELECT kriteria_id, MAX(nilai_crisp) as max_c FROM saw_sub_kriteria GROUP BY kriteria_id");
        $max_kriteria = [];
        if ($res_max) {
            while ($mx = $res_max->fetch_assoc()) {
                $max_kriteria[(int)$mx['kriteria_id']] = (float)$mx['max_c'];
            }
        }
        for ($c = 1; $c <= 5; $c++) {
            if (!isset($max_kriteria[$c]) || $max_kriteria[$c] <= 0) {
                $max_kriteria[$c] = 5.0;
            }
        }

        // 3. Ambil bobot kriteria W dari saw_kriteria
        $res_w = $conn->query("SELECT id, bobot_persen FROM saw_kriteria");
        $bobot_w = [];
        if ($res_w) {
            while ($w = $res_w->fetch_assoc()) {
                $bobot_w[(int)$w['id']] = ((float)$w['bobot_persen']) / 100.0;
            }
        }

        // 4. Hitung Normalisasi (R) dan Nilai Preferensi Terbobot (V)
        foreach ($antrean_ids as $kid) {
            $skor_akhir = 0.0;
            for ($c = 1; $c <= 5; $c++) {
                $val_x   = $matriks_x[$kid][$c] ?? 1.0;
                $max_val = $max_kriteria[$c];

                // Kriteria Benefit SAW: R_ij = X_ij / Max(X_j)
                $r_ij = $max_val > 0 ? ($val_x / $max_val) : 0.0;
                $w_val = $bobot_w[$c] ?? 0.20;
                $terbobot = $r_ij * $w_val;
                $skor_akhir += $terbobot;

                // Update ke tabel saw_nilai_pemeriksaan
                $stmt_u = $conn->prepare("UPDATE saw_nilai_pemeriksaan SET nilai_normalisasi = ?, nilai_terbobot = ? WHERE kunjungan_id = ? AND kriteria_id = ?");
                if ($stmt_u) {
                    $stmt_u->bind_param("ddii", $r_ij, $terbobot, $kid, $c);
                    $stmt_u->execute();
                }
            }

            // Tentukan Tingkat Prioritas berdasarkan Skor SAW atau Emergency Override
            if (!empty($is_override_map[$kid])) {
                $prioritas = 'P1_Gawat_Darurat';
                $skor_akhir = max($skor_akhir, 1.0000);
            } else {
                if ($skor_akhir >= 0.80) {
                    $prioritas = 'P1_Gawat_Darurat';
                } elseif ($skor_akhir >= 0.50) {
                    $prioritas = 'P2_Mendesak';
                } else {
                    $prioritas = 'P3_Biasa';
                }
            }

            // Update ke tabel kunjungan_sakit
            $stmt_k_up = $conn->prepare("UPDATE kunjungan_sakit SET skor_saw_akhir = ?, tingkat_prioritas = ? WHERE id = ?");
            if ($stmt_k_up) {
                $stmt_k_up->bind_param("dsi", $skor_akhir, $prioritas, $kid);
                $stmt_k_up->execute();
            }
        }
    }
}
?>
