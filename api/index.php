<?php
// api/index.php - Front Controller / Router untuk Vercel Serverless Multi-Page PHP
ob_start();

// Konfigurasi session save path ke /tmp agar aman di Vercel Serverless
if (session_status() === PHP_SESSION_NONE) {
    $sess_path = sys_get_temp_dir();
    if (is_writable($sess_path)) {
        @ini_set('session.save_path', $sess_path);
    }
}

// Set working directory ke root project agar semua include/require relatif bekerja normal
chdir(__DIR__ . '/..');

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed_url = parse_url($request_uri);
$path = $parsed_url['path'] ?? '/';
$path = ltrim($path, '/');

// Jika root, arahkan ke index.php
if ($path === '' || $path === '/') {
    require __DIR__ . '/../index.php';
    exit();
}

$root_dir = realpath(__DIR__ . '/..');
$target = $root_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

// Keamanan: Cegah directory traversal
if (strpos(realpath(dirname($target)) ?: '', $root_dir) !== 0 && !file_exists($target)) {
    http_response_code(403);
    echo "403 Forbidden";
    exit();
}

// 1. Jika file target ada secara eksak
if (file_exists($target) && is_file($target)) {
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        $_SERVER['SCRIPT_NAME'] = '/' . $path;
        $_SERVER['PHP_SELF'] = '/' . $path;
        $_SERVER['SCRIPT_FILENAME'] = $target;
        require $target;
        exit();
    }
    
    // Static file fallback jika lewat router
    $mimes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'sql' => 'text/plain',
        'json' => 'application/json'
    ];
    $content_type = $mimes[$ext] ?? 'application/octet-stream';
    header("Content-Type: " . $content_type);
    readfile($target);
    exit();
}

// 2. Jika diakses tanpa ekstensi .php (contoh: /dashboard -> /dashboard.php)
if (file_exists($target . '.php') && is_file($target . '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $path . '.php';
    $_SERVER['PHP_SELF'] = '/' . $path . '.php';
    $_SERVER['SCRIPT_FILENAME'] = $target . '.php';
    require $target . '.php';
    exit();
}

// 3. Fallback jika tidak ditemukan
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | Poskestren Al-Bahjah</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F5F8F6] text-[#182923] flex items-center justify-center min-h-screen p-4 font-sans">
    <div class="bg-white p-8 rounded-2xl border border-[#DCE7E0] max-w-md w-full text-center shadow-lg">
        <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-black">404</div>
        <h1 class="text-xl font-bold text-[#0B3D2E] mb-2">Halaman Tidak Ditemukan</h1>
        <p class="text-sm text-[#64756D] mb-6">Halaman <code><?= htmlspecialchars($path); ?></code> tidak tersedia di sistem Poskestren.</p>
        <a href="/" class="inline-block px-5 py-2.5 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition">Kembali ke Beranda</a>
    </div>
</body>
</html>
