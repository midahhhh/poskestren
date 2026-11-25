<?php
// dashboard_farmasi.php - Dashboard Pelayanan Kefarmasian & Logistik Obat Poskestren Al-Bahjah Bogor
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Verifikasi Hak Akses: Petugas Farmasi / Apoteker & Admin
if (!isset($_SESSION['user_id'])) {
    set_flash('error', "Sesi telah berakhir. Silakan masuk kembali.");
    header("Location: login_petugas.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['nama_lengkap'] ?? ($_SESSION['username'] ?? 'Petugas Farmasi');
$user_id   = (int)$_SESSION['user_id'];

if ($user_role !== 'petugas_farmasi' && $user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Halaman ini khusus Petugas Farmasi & Apoteker.");
    header("Location: login_petugas.php");
    exit();
}

// ════════════════════════════════════════════════════════════════════
// HANDLER: POST ACTIONS (CRUD OBAT, RESTOCK, & CATATAN ADMIN)
// ════════════════════════════════════════════════════════════════════
$action = $_POST['action'] ?? '';

// 1. Tambah Obat Baru
if ($action === 'tambah_obat') {
    $kat  = (int)($_POST['kategori_id'] ?? 0);
    $kode = trim($_POST['kode_obat'] ?? '');
    $nama = trim($_POST['nama_obat'] ?? '');
    $sat  = trim($_POST['satuan'] ?? '');
    $min  = (int)($_POST['stok_minimal'] ?? 10);
    $stok = (int)($_POST['stok_sekarang'] ?? 0);
    $exp  = $_POST['exp_date'] ?? '';
    $ket  = trim($_POST['keterangan'] ?? '');

    if ($nama && $kode && $sat && $exp && $kat > 0) {
        $st = $conn->prepare("INSERT INTO obat (kategori_id,kode_obat,nama_obat,satuan,stok_minimal,stok_sekarang,exp_date,keterangan) VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param("isssiiss", $kat, $kode, $nama, $sat, $min, $stok, $exp, $ket);
        if ($st->execute()) {
            $oid = $conn->insert_id;
            if ($stok > 0) {
                $jm  = 'masuk';
                $kt  = "Stok awal pengadaan obat baru (" . ($ket ?: "Logistik Farmasi") . ")";
                $s2  = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
                $s2->bind_param("isisi", $oid, $jm, $stok, $kt, $user_id);
                $s2->execute();
            }
            set_flash('success', "Item obat '$nama' ($kode) berhasil ditambahkan ke inventaris.");
        } else {
            set_flash('error', "Gagal menambahkan obat. Kode obat '$kode' kemungkinan sudah terdaftar atau terjadi kesalahan database.");
        }
    } else {
        set_flash('error', "Mohon lengkapi seluruh kolom formulir penambahan obat (termasuk kategori dan tanggal kadaluarsa).");
    }
    header("Location: dashboard_farmasi.php#inventaris-obat"); 
    exit();
}

// 2. Edit / Perbarui Data Obat
if ($action === 'edit_obat') {
    $id   = (int)($_POST['obat_id'] ?? 0);
    $kat  = (int)($_POST['kategori_id'] ?? 0);
    $kode = trim($_POST['kode_obat'] ?? '');
    $nama = trim($_POST['nama_obat'] ?? '');
    $sat  = trim($_POST['satuan'] ?? '');
    $stok = isset($_POST['stok_sekarang']) ? (int)$_POST['stok_sekarang'] : null;
    $min  = (int)($_POST['stok_minimal'] ?? 10);
    $exp  = $_POST['exp_date'] ?? '';
    $ket  = trim($_POST['keterangan'] ?? '');

    if ($id > 0 && $nama && $kode && $sat && $exp && $kat > 0) {
        $q_lama = $conn->query("SELECT stok_sekarang FROM obat WHERE id = $id LIMIT 1");
        $stok_lama = ($q_lama && $r = $q_lama->fetch_assoc()) ? (int)$r['stok_sekarang'] : 0;

        if ($stok !== null) {
            $st = $conn->prepare("UPDATE obat SET kategori_id=?,kode_obat=?,nama_obat=?,satuan=?,stok_sekarang=?,stok_minimal=?,exp_date=?,keterangan=? WHERE id=?");
            $st->bind_param("isssiissi", $kat, $kode, $nama, $sat, $stok, $min, $exp, $ket, $id);
        } else {
            $st = $conn->prepare("UPDATE obat SET kategori_id=?,kode_obat=?,nama_obat=?,satuan=?,stok_minimal=?,exp_date=?,keterangan=? WHERE id=?");
            $st->bind_param("isssissi", $kat, $kode, $nama, $sat, $min, $exp, $ket, $id);
        }

        if ($st && $st->execute()) {
            if ($stok !== null && $stok !== $stok_lama) {
                $selisih = abs($stok - $stok_lama);
                $jenis_m = $stok > $stok_lama ? 'masuk' : 'keluar';
                $kt_m = "Penyesuaian stok saat edit data (sebelumnya $stok_lama, sekarang $stok)";
                $s2 = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
                $s2->bind_param("isisi", $id, $jenis_m, $selisih, $kt_m, $user_id);
                $s2->execute();
            }
            set_flash('success', "Data obat '$nama' berhasil diperbarui.");
        } else {
            set_flash('error', "Gagal memperbarui data obat: " . ($conn->error ?: "Pastikan kode obat unik."));
        }
    } else {
        set_flash('error', "Mohon lengkapi seluruh kolom formulir edit obat.");
    }
    header("Location: dashboard_farmasi.php#inventaris-obat"); 
    exit();
}

// 3. Restock / Tambah Stok Obat
if ($action === 'restock_obat') {
    $id     = (int)($_POST['obat_id'] ?? 0);
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $ket    = trim($_POST['keterangan'] ?? "Restock pengadaan logistik Farmasi");

    if ($id > 0 && $jumlah > 0) {
        $conn->begin_transaction();
        try {
            $upd = $conn->query("UPDATE obat SET stok_sekarang = stok_sekarang + $jumlah WHERE id = $id");
            if (!$upd) throw new Exception($conn->error ?: "Gagal memperbarui stok obat.");
            $jm = 'masuk';
            $s2 = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
            $s2->bind_param("isisi", $id, $jm, $jumlah, $ket, $user_id);
            $s2->execute();
            $conn->commit();
            set_flash('success', "Berhasil menambahkan $jumlah unit stok obat.");
        } catch (Throwable $e) {
            $conn->rollback();
            set_flash('error', "Gagal restock: " . $e->getMessage());
        }
    } else {
        set_flash('error', "Jumlah unit restock obat harus lebih dari 0.");
    }
    header("Location: dashboard_farmasi.php#inventaris-obat"); 
    exit();
}

// 4. Hapus Obat
if ($action === 'hapus_obat') {
    $id = (int)($_POST['obat_id'] ?? 0);
    if ($id > 0) {
        $res_cek = $conn->query("SELECT COUNT(*) c FROM resep_obat_detail rd 
                                 JOIN resep_obat r ON rd.resep_id=r.id 
                                 WHERE rd.obat_id=$id AND r.status_resep IN ('menunggu_proses','diproses')");
        $cek = ($res_cek && $res_cek instanceof mysqli_result) ? (int)($res_cek->fetch_assoc()['c'] ?? 0) : 0;
        if ($cek > 0) {
            set_flash('error', "Obat tidak dapat dihapus karena masih tercatat dalam antrean resep aktif.");
        } else {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM obat_mutasi_stok WHERE obat_id = $id");
                $conn->query("DELETE FROM resep_obat_detail WHERE obat_id = $id");
                $del = $conn->query("DELETE FROM obat WHERE id = $id");
                if (!$del) {
                    throw new Exception($conn->error ?: "Gagal menghapus data obat dari tabel inventaris.");
                }
                $conn->commit();
                set_flash('success', "Obat berhasil dihapus dari inventaris.");
            } catch (Throwable $e) {
                $conn->rollback();
                set_flash('error', "Gagal menghapus obat: " . $e->getMessage());
            }
        }
    }
    header("Location: dashboard_farmasi.php#inventaris-obat"); 
    exit();
}

// 5. Simpan Catatan Logistik untuk Admin
if ($action === 'simpan_catatan_admin') {
    $id      = (int)($_POST['obat_id'] ?? 0);
    $catatan = trim($_POST['catatan_admin'] ?? '');
    if ($id > 0) {
        $prefix = "[Pesan Farmasi " . date('d/m/Y H:i') . "]: ";
        $ket_baru = $catatan ? $prefix . $catatan : '';
        $st = $conn->prepare("UPDATE obat SET keterangan = ? WHERE id = ?");
        $st->bind_param("si", $ket_baru, $id);
        $st->execute();
        set_flash('success', "Catatan logistik berhasil disimpan & tersinkronisasi ke Dashboard Admin.");
    }
    header("Location: dashboard_farmasi.php#inventaris-obat"); 
    exit();
}

// ════════════════════════════════════════════════════════════════════
// HANDLER: GET ACTIONS (STATUS DISPENSING RESEP)
// ════════════════════════════════════════════════════════════════════
// 1. Siapkan Resep (Mulai Dispensing)
if (isset($_GET['siapkan_resep_id'])) {
    $rid = (int)$_GET['siapkan_resep_id'];
    $conn->query("UPDATE resep_obat SET status_resep='diproses' WHERE id=$rid");
    set_flash('success', "Resep #ANT-" . str_pad($rid, 3, '0', STR_PAD_LEFT) . " berstatus sedang disiapkan / diracik.");
    header("Location: dashboard_farmasi.php#antrean-resep"); 
    exit();
}

// 2. Serahkan Resep ke Santri (Atomic Stock Deduction)
if (isset($_GET['serahkan_resep_id'])) {
    $resep_id = (int)$_GET['serahkan_resep_id'];
    $conn->begin_transaction();
    try {
        $q = $conn->query("SELECT rd.*, o.nama_obat, o.stok_sekarang 
                           FROM resep_obat_detail rd JOIN obat o ON rd.obat_id=o.id 
                           WHERE rd.resep_id=$resep_id FOR UPDATE");
        while ($it = $q->fetch_assoc()) {
            if ($it['stok_sekarang'] < $it['jumlah']) {
                throw new Exception("Stok obat '{$it['nama_obat']}' tidak mencukupi (sisa {$it['stok_sekarang']}, dibutuhkan {$it['jumlah']}).");
            }
            $conn->query("UPDATE obat SET stok_sekarang = stok_sekarang - {$it['jumlah']} WHERE id={$it['obat_id']}");
            $ket = "Penyerahan resep obat santri #$resep_id";
            $mut = 'keluar';
            $s2  = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
            $s2->bind_param("isisi", $it['obat_id'], $mut, $it['jumlah'], $ket, $user_id);
            $s2->execute();
        }
        $conn->query("UPDATE resep_obat SET status_resep='selesai', petugas_farmasi_id=$user_id WHERE id=$resep_id");
        $conn->commit();
        set_flash('success', "Resep telah diserahkan kepada santri. Stok obat otomatis terpotong.");
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('error', "Gagal menyelesaikan resep: " . $e->getMessage());
    }
    header("Location: dashboard_farmasi.php#antrean-resep"); 
    exit();
}

// ════════════════════════════════════════════════════════════════════
// DATA QUERY & STATISTIK LOGISTIK
// ════════════════════════════════════════════════════════════════════
$q_menunggu = $conn->query("SELECT COUNT(*) c FROM resep_obat WHERE status_resep IN ('menunggu','menunggu_proses')");
$cnt_menunggu = ($q_menunggu && $q_menunggu instanceof mysqli_result) ? (int)($q_menunggu->fetch_assoc()['c'] ?? 0) : 0;

$q_diproses = $conn->query("SELECT COUNT(*) c FROM resep_obat WHERE status_resep='diproses'");
$cnt_diproses = ($q_diproses && $q_diproses instanceof mysqli_result) ? (int)($q_diproses->fetch_assoc()['c'] ?? 0) : 0;

$q_selesai = $conn->query("SELECT COUNT(*) c FROM resep_obat WHERE status_resep='selesai'");
$cnt_selesai = ($q_selesai && $q_selesai instanceof mysqli_result) ? (int)($q_selesai->fetch_assoc()['c'] ?? 0) : 0;

$q_kritis = $conn->query("SELECT COUNT(*) c FROM obat WHERE stok_sekarang<=stok_minimal");
$cnt_kritis = ($q_kritis && $q_kritis instanceof mysqli_result) ? (int)($q_kritis->fetch_assoc()['c'] ?? 0) : 0;

$q_hampir_exp = $conn->query("SELECT COUNT(*) c FROM obat WHERE exp_date<=DATE_ADD(CURDATE(),INTERVAL 60 DAY)");
$cnt_hampir_exp = ($q_hampir_exp && $q_hampir_exp instanceof mysqli_result) ? (int)($q_hampir_exp->fetch_assoc()['c'] ?? 0) : 0;

$q_total_obat = $conn->query("SELECT COUNT(*) c FROM obat");
$cnt_total_obat = ($q_total_obat && $q_total_obat instanceof mysqli_result) ? (int)($q_total_obat->fetch_assoc()['c'] ?? 0) : 0;

// Kontak Admin untuk Koordinasi WhatsApp
$res_admin = $conn->query("SELECT nama_lengkap, no_hp FROM users WHERE role='admin' LIMIT 1");
$admin_row = ($res_admin && $res_admin instanceof mysqli_result) ? $res_admin->fetch_assoc() : null;
$admin_wa  = '6281122334455';
if (!empty($admin_row['no_hp'])) {
    $c = preg_replace('/[^0-9]/', '', $admin_row['no_hp']);
    if (str_starts_with($c, '0')) $c = '62' . substr($c, 1);
    if ($c) $admin_wa = $c;
}
$pesan_wa = "Assalamu'alaikum Admin Poskestren Al-Bahjah,\n\n"
          . "Laporan Logistik Farmasi (Apoteker {$user_name}):\n"
          . "• Resep Menunggu: {$cnt_menunggu}\n"
          . "• Stok Kritis: {$cnt_kritis} item\n"
          . "• Mendekati Expired: {$cnt_hampir_exp} item\n\n"
          . "Mohon koordinasi pengadaan obat berkala. Terima kasih.";
$wa_url = "https://api.whatsapp.com/send?phone={$admin_wa}&text=" . rawurlencode($pesan_wa);

// Antrean Resep Aktif
$res_resep = $conn->query(
    "SELECT r.*, k.no_antrean, k.keluhan_utama, k.tanggal_berobat,
            s.nama_lengkap as nama_santri, s.nis, s.kamar_asrama, s.kompleks_asrama
     FROM resep_obat r
     JOIN kunjungan_sakit k ON r.kunjungan_id=k.id
     JOIN santri s ON k.santri_id=s.id
     WHERE r.status_resep IN ('menunggu','menunggu_proses','diproses')
     ORDER BY CASE r.status_resep WHEN 'menunggu' THEN 1 WHEN 'menunggu_proses' THEN 1 ELSE 2 END ASC, r.id ASC LIMIT 15"
);

// Semua Obat untuk Inventaris
$res_semua_obat = $conn->query(
    "SELECT o.*, ok.nama_kategori FROM obat o
     LEFT JOIN obat_kategori ok ON o.kategori_id=ok.id
     ORDER BY o.nama_obat ASC"
);

// Kategori Obat
$res_kategori = $conn->query("SELECT * FROM obat_kategori ORDER BY nama_kategori");
$list_kategori = [];
if ($res_kategori) {
    while ($k = $res_kategori->fetch_assoc()) $list_kategori[] = $k;
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Farmasi & Apotek — Poskestren Al-Bahjah Bogor</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F8F6;
            color: #182923;
        }
        .mono-num {
            font-family: 'JetBrains Mono', monospace;
        }
        .card-panel {
            background-color: #FFFFFF;
            border: 1px solid #DCE7E0;
            border-radius: 12px;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-[#F5F8F6] text-[#182923]">

    <!-- ============================================================== -->
    <!-- TOP HORIZONTAL NAVBAR (SERAGAM DENGAN DOKTER & REKAM MEDIS)   -->
    <!-- ============================================================== -->
    <header class="sticky top-0 z-40 bg-white border-b border-[#DCE7E0] shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                
                <!-- Brand & Logo -->
                <div class="flex items-center gap-3">
                    <a href="dashboard_farmasi.php" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-sm tracking-tight">
                            AB
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-[#0B3D2E] tracking-tight">Poskestren Al-Bahjah</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Unit Farmasi
                                </span>
                            </div>
                            <p class="text-[11px] text-[#64756D] leading-none mt-0.5">Sistem Pelayanan Rekam Medis & Triase Santri</p>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-1 text-xs font-semibold text-[#64756D]">
                    <a href="#antrean-resep" class="px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold">
                        Antrean Resep (<?= $cnt_menunggu + $cnt_diproses; ?>)
                    </a>
                    <a href="#inventaris-obat" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Inventaris Obat
                    </a>
                    <a href="rekam_medis.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Rekam Medis (EMR)
                    </a>
                    <a href="index.php" class="px-3 py-2 rounded-lg hover:text-[#0B3D2E] hover:bg-slate-100 transition">
                        Beranda Publik
                    </a>
                </nav>

                <!-- Pharmacist Profile & Logout -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="text-right hidden sm:block">
                        <span class="block text-xs font-bold text-[#0B3D2E] leading-tight"><?= htmlspecialchars($user_name); ?></span>
                        <span class="block text-[10px] text-[#64756D]">Apoteker / Penanggung Jawab Farmasi</span>
                    </div>
                    <a href="logout.php" onclick="return confirm('Keluar dari sesi farmasi?')" 
                       class="hidden sm:flex px-3 py-1.5 rounded-lg border border-[#DCE7E0] hover:bg-red-50 hover:border-red-200 hover:text-[#D64545] text-xs font-semibold text-[#64756D] transition items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Keluar</span>
                    </a>
                    <!-- Mobile Hamburger Button -->
                    <button type="button" onclick="const m = document.getElementById('mobile-nav-farmasi'); m.classList.toggle('hidden');" 
                            class="md:hidden p-2 rounded-lg text-[#0B3D2E] hover:bg-slate-100 transition flex items-center justify-center" aria-label="Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Menu (Collapsible) -->
        <div id="mobile-nav-farmasi" class="hidden md:hidden px-4 py-3 border-t border-[#DCE7E0] bg-white space-y-1.5">
            <a href="#antrean-resep" class="block px-3 py-2 rounded-lg bg-emerald-50 text-[#0B3D2E] font-bold text-xs">
                Antrean Resep (<?= $cnt_menunggu + $cnt_diproses; ?>)
            </a>
            <a href="#inventaris-obat" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Inventaris Obat
            </a>
            <a href="rekam_medis.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Rekam Medis (EMR)
            </a>
            <a href="index.php" class="block px-3 py-2 rounded-lg text-[#64756D] hover:bg-slate-100 font-semibold text-xs">
                Beranda Publik
            </a>
            <div class="pt-2 mt-1 border-t border-[#DCE7E0] flex items-center justify-between px-1">
                <div>
                    <span class="block text-xs font-bold text-[#0B3D2E]"><?= htmlspecialchars($user_name); ?></span>
                    <span class="block text-[10px] text-[#64756D]">Apoteker</span>
                </div>
                <a href="logout.php" onclick="return confirm('Keluar dari sesi farmasi?')" class="px-2.5 py-1 text-xs font-bold text-red-600 bg-red-50 rounded-md border border-red-200">
                    Keluar
                </a>
            </div>
        </div>
    </header>

    <!-- ============================================================== -->
    <!-- MAIN WORKSPACE                                                 -->
    <!-- ============================================================== -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5 flex-1 w-full">

        <!-- Flash Message Notification -->
        <?php 
        $fok  = get_flash('success');
        $ferr = get_flash('error');
        if ($fok): ?>
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-xs font-medium text-emerald-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($fok); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900 font-bold">&times;</button>
        </div>
        <?php endif; if ($ferr): ?>
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-xs font-medium text-red-900 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-red-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <span><?= htmlspecialchars($ferr); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900 font-bold">&times;</button>
        </div>
        <?php endif; ?>

        <!-- ============================================================== -->
        <!-- KERJASAMA RESMI & SALAM KEMITRAAN MoU                         -->
        <!-- ============================================================== -->
        <div class="card-panel p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 bg-white border-l-4 border-l-[#16845B]">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-900 border border-emerald-300">
                        Kemitraan Resmi MoU 2026
                    </span>
                    <span class="text-xs mono-num text-[#64756D]">
                        No: 028/MOU-LPD/AB-FARMASI/2026
                    </span>
                </div>
                <h1 class="text-base font-bold text-[#0B3D2E]">
                    Pelayanan Kefarmasian & Penyaluran Obat Santri Poskestren Al-Bahjah
                </h1>
                <p class="text-xs text-[#64756D] max-w-4xl leading-relaxed">
                    Kerjasama kemitraan resmi penyediaan perbekalan obat-obatan yang aman, bermutu, dan bersertifikasi halal bagi para santri Pondok Pesantren Al-Bahjah Cabang Bogor.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0 w-full md:w-auto">
                <a href="<?= $wa_url; ?>" target="_blank" 
                   class="w-full md:w-auto px-3.5 py-2 rounded-lg bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow-2xs">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.539 1.761.815 2.791.815 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.59-5.767-5.768-5.767zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.493-5.029-1.353l-6.971 1.821 1.864-6.804c-.958-1.547-1.493-3.344-1.493-5.224 0-5.514 4.486-10 10-10 5.514 0 10 4.486 10 10z"/></svg>
                    <span>Koordinasi Logistik via WA</span>
                </a>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- 5 METRIK OPERASIONAL FARMASI                                   -->
        <!-- ============================================================== -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="card-panel p-3.5 <?= $cnt_menunggu > 0 ? 'border-red-300 bg-red-50/20' : ''; ?>">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Resep Menunggu</div>
                <div class="text-2xl font-bold mt-1 <?= $cnt_menunggu > 0 ? 'text-red-700' : 'text-[#0B3D2E]'; ?> mono-num"><?= $cnt_menunggu; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Perlu disiapkan</div>
            </div>

            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Dalam Peracikan</div>
                <div class="text-2xl font-bold mt-1 text-blue-800 mono-num"><?= $cnt_diproses; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Sedang diproses</div>
            </div>

            <div class="card-panel p-3.5">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Diserahkan Hari Ini</div>
                <div class="text-2xl font-bold mt-1 text-[#16845B] mono-num"><?= $cnt_selesai; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Selesai terdistribusi</div>
            </div>

            <div class="card-panel p-3.5 <?= $cnt_kritis > 0 ? 'border-amber-300 bg-amber-50/20' : ''; ?>">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Stok Kritis</div>
                <div class="text-2xl font-bold mt-1 <?= $cnt_kritis > 0 ? 'text-amber-700' : 'text-[#0B3D2E]'; ?> mono-num"><?= $cnt_kritis; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">Mendekati limit minimum</div>
            </div>

            <div class="card-panel p-3.5 <?= $cnt_hampir_exp > 0 ? 'border-orange-300 bg-orange-50/20' : ''; ?>">
                <div class="text-[11px] font-semibold text-[#64756D] uppercase">Mendekati Kadaluarsa</div>
                <div class="text-2xl font-bold mt-1 <?= $cnt_hampir_exp > 0 ? 'text-orange-700' : 'text-[#0B3D2E]'; ?> mono-num"><?= $cnt_hampir_exp; ?></div>
                <div class="text-[11px] text-[#64756D] mt-0.5">&lt; 60 hari ED (FEFO)</div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TABEL ANTREAN RESEP OBAT SANTRI (DISPENSING QUEUE)             -->
        <!-- ============================================================== -->
        <div id="antrean-resep" class="card-panel p-4 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E]">Antrean Resep Masuk dari Dokter (RME)</h2>
                    <p class="text-xs text-[#64756D]">Resep elektronik yang diterbitkan dokter saat pemeriksaan klinis santri di Poskestren.</p>
                </div>
                <span class="text-xs mono-num font-semibold px-2 py-0.5 rounded bg-slate-100 text-[#0B3D2E] border border-[#DCE7E0]">
                    <?= $cnt_menunggu + $cnt_diproses; ?> Resep Aktif
                </span>
            </div>

            <?php if ($res_resep && $res_resep->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <?php while ($r = $res_resep->fetch_assoc()): 
                    $rid = $r['id'];
                    $items = $conn->query("SELECT rd.*, o.nama_obat, o.satuan 
                                          FROM resep_obat_detail rd JOIN obat o ON rd.obat_id=o.id 
                                          WHERE rd.resep_id=$rid");
                    $is_menunggu = ($r['status_resep'] === 'menunggu' || $r['status_resep'] === 'menunggu_proses');
                ?>
                <div class="p-3.5 rounded-lg border <?= $is_menunggu ? 'border-amber-200 bg-amber-50/15' : 'border-blue-200 bg-blue-50/15'; ?> flex flex-col justify-between space-y-3">
                    <div>
                        <!-- Header Kartu Resep -->
                        <div class="flex items-start justify-between pb-2 border-b border-[#DCE7E0]">
                            <div>
                                <span class="mono-num font-bold text-xs text-[#0B3D2E]"><?= htmlspecialchars($r['no_antrean'] ?: 'ANT-'.$rid); ?></span>
                                <h3 class="font-bold text-sm text-[#0B3D2E]"><?= htmlspecialchars($r['nama_santri']); ?></h3>
                                <p class="text-[11px] text-[#64756D]">NIS: <?= $r['nis']; ?> • <?= htmlspecialchars($r['kamar_asrama'] ?? '-'); ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $is_menunggu ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-blue-100 text-blue-900 border border-blue-300'; ?>">
                                <?= $is_menunggu ? 'Menunggu' : 'Sedang Diracik'; ?>
                            </span>
                        </div>

                        <!-- Daftar R/ Obat -->
                        <div class="space-y-1.5 pt-2 text-xs">
                            <span class="text-[10px] uppercase font-bold text-[#64756D] block">Daftar Terapi Obat (R/):</span>
                            <?php while ($it = $items->fetch_assoc()): ?>
                            <div class="p-1.5 rounded bg-white border border-[#DCE7E0]">
                                <div class="flex justify-between items-center font-semibold text-[#182923]">
                                    <span><?= htmlspecialchars($it['nama_obat']); ?></span>
                                    <span class="mono-num text-[11px] text-[#64756D]"><?= (int)$it['jumlah']; ?> <?= htmlspecialchars($it['satuan'] ?? 'Unit'); ?></span>
                                </div>
                                <div class="text-[11px] text-[#16845B] mt-0.5">
                                    Signa: <?= htmlspecialchars($it['dosis_instruksi'] ?: '-'); ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <?php if (!empty($r['catatan_resep'])): ?>
                        <div class="mt-2 p-2 rounded bg-slate-50 border border-[#DCE7E0] text-[11px] text-[#64756D]">
                            <strong>Instruksi Dokter:</strong> <?= htmlspecialchars($r['catatan_resep']); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tombol Aksi Dispensing -->
                    <div class="pt-2 flex gap-2">
                        <?php if ($is_menunggu): ?>
                        <a href="dashboard_farmasi.php?siapkan_resep_id=<?= $rid; ?>" 
                           class="flex-1 py-1.5 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold text-xs text-center transition">
                            Siapkan Obat
                        </a>
                        <?php else: ?>
                        <a href="dashboard_farmasi.php?serahkan_resep_id=<?= $rid; ?>" 
                           onclick="return confirm('Konfirmasi: Seluruh obat telah diverifikasi dan diserahkan ke santri?')" 
                           class="flex-1 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs text-center transition">
                            Serahkan ke Santri
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="py-8 text-center text-[#64756D]">
                <p class="text-sm font-semibold text-[#0B3D2E]">Tidak ada antrean resep yang menunggu.</p>
                <p class="text-xs text-[#64756D] mt-0.5">Seluruh resep obat santri hari ini telah selesai disiapkan dan diserahkan.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================== -->
        <!-- TABEL INVENTARIS & LOGISTIK OBAT                               -->
        <!-- ============================================================== -->
        <div id="inventaris-obat" class="card-panel p-4 space-y-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-3">
                <div>
                    <h2 class="font-bold text-sm text-[#0B3D2E]">Inventaris & Persediaan Obat Poskestren</h2>
                    <p class="text-xs text-[#64756D]">Katalog obat aktif, pemantauan batas minimum, tanggal kadaluarsa, dan catatan logistik.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="bukaModalTambah()" 
                            class="px-3.5 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Obat Baru</span>
                    </button>
                </div>
            </div>

            <!-- Input Pencarian Real-Time -->
            <div class="flex flex-col sm:flex-row items-center gap-2">
                <input type="text" id="cari-obat" onkeyup="filterObat()" placeholder="Ketik nama obat, kode, atau kategori untuk memfilter..." 
                       class="w-full sm:max-w-md px-3 py-1.5 text-xs rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] focus:outline-none focus:ring-1 focus:ring-[#16845B]">
                <span class="text-xs text-[#64756D] hidden sm:inline">Total: <?= $cnt_total_obat; ?> item obat terdaftar</span>
            </div>

            <!-- Tabel Katalog Obat -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse" id="tabel-obat">
                    <thead>
                        <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3">Kode</th>
                            <th class="py-2.5 px-3">Nama Obat</th>
                            <th class="py-2.5 px-3">Kategori</th>
                            <th class="py-2.5 px-3 text-center">Stok / Minimal</th>
                            <th class="py-2.5 px-3">Expired Date</th>
                            <th class="py-2.5 px-3">Catatan Admin</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                        <?php if ($res_semua_obat && $res_semua_obat->num_rows > 0): ?>
                            <?php while ($ob = $res_semua_obat->fetch_assoc()): 
                                $is_kritis = $ob['stok_sekarang'] <= $ob['stok_minimal'];
                                $is_exp    = strtotime($ob['exp_date']) <= strtotime('+60 days');
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 mono-num font-semibold text-[#64756D]"><?= htmlspecialchars($ob['kode_obat']); ?></td>
                                <td class="py-2.5 px-3 font-bold text-[#0B3D2E]"><?= htmlspecialchars($ob['nama_obat']); ?></td>
                                <td class="py-2.5 px-3 text-[#64756D]"><?= htmlspecialchars($ob['nama_kategori'] ?? 'Umum'); ?></td>
                                <td class="py-2.5 px-3 text-center mono-num font-bold">
                                    <span class="px-2 py-0.5 rounded <?= $is_kritis ? 'bg-red-100 text-red-800 border border-red-300' : 'bg-emerald-50 text-emerald-800 border border-emerald-200'; ?>">
                                        <?= (int)$ob['stok_sekarang']; ?> / <?= (int)$ob['stok_minimal']; ?> <?= htmlspecialchars($ob['satuan']); ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 mono-num <?= $is_exp ? 'text-orange-700 font-bold' : 'text-[#64756D]'; ?>">
                                    <?= date('d/m/Y', strtotime($ob['exp_date'])); ?>
                                </td>
                                <td class="py-2.5 px-3 max-w-[200px] truncate text-[11px] text-[#64756D]">
                                    <?= htmlspecialchars($ob['keterangan'] ?: '-'); ?>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Restock -->
                                        <button type="button" 
                                                data-id="<?= (int)$ob['id']; ?>"
                                                data-nama="<?= htmlspecialchars($ob['nama_obat'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="bukaModalRestock(this.dataset.id, this.dataset.nama)" 
                                                class="px-2 py-1 rounded border border-[#DCE7E0] hover:bg-emerald-50 text-[#16845B] font-semibold transition cursor-pointer" title="Tambah Stok">
                                            + Stok
                                        </button>
                                        <!-- Edit -->
                                        <button type="button" 
                                                data-obat='<?= htmlspecialchars(json_encode($ob, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>'
                                                onclick="bukaModalEdit(JSON.parse(this.dataset.obat))" 
                                                class="px-2 py-1 rounded border border-[#DCE7E0] hover:bg-slate-100 text-[#0B3D2E] font-semibold transition cursor-pointer" title="Edit Data">
                                            Edit
                                        </button>
                                        <!-- Catatan Admin -->
                                        <button type="button" 
                                                data-id="<?= (int)$ob['id']; ?>"
                                                data-nama="<?= htmlspecialchars($ob['nama_obat'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-ket="<?= htmlspecialchars($ob['keterangan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="bukaModalCatatan(this.dataset.id, this.dataset.nama, this.dataset.ket)" 
                                                class="px-2 py-1 rounded border border-[#DCE7E0] hover:bg-amber-50 text-amber-800 font-semibold transition cursor-pointer" title="Kirim Catatan ke Admin">
                                            Catatan
                                        </button>
                                        <!-- Hapus -->
                                        <button type="button" 
                                                data-id="<?= (int)$ob['id']; ?>"
                                                data-nama="<?= htmlspecialchars($ob['nama_obat'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="konfirmasiHapusObat(this.dataset.id, this.dataset.nama)" 
                                                class="px-2 py-1 rounded border border-[#DCE7E0] hover:bg-red-50 text-red-700 font-semibold transition cursor-pointer" title="Hapus Obat">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ============================================================== -->
    <!-- FOOTER                                                         -->
    <!-- ============================================================== -->
    <footer class="mt-auto bg-white border-t border-[#DCE7E0] py-4 text-xs text-[#64756D]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="font-bold text-[#0B3D2E]">Poskestren Al-Bahjah Bogor</span>
                <span>— Instalasi Pelayanan Farmasi Mitra MoU & Manajemen Obat</span>
            </div>
            <div>&copy; <?= date('Y'); ?> Lembaga Pengembangan Dakwah Al-Bahjah.</div>
        </div>
    </footer>

    <!-- ============================================================== -->
    <!-- MODAL 1: TAMBAH OBAT BARU                                      -->
    <!-- ============================================================== -->
    <div id="modal-tambah" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-xl max-w-lg w-full p-5 space-y-3.5 border border-[#DCE7E0] shadow-xl my-auto">
            <div class="flex items-center justify-between pb-2.5 border-b border-[#DCE7E0]">
                <h3 class="font-bold text-sm text-[#0B3D2E]">Tambah Data Obat Baru</h3>
                <button type="button" onclick="tutupModal('modal-tambah')" class="text-[#64756D] hover:text-black font-bold">&times;</button>
            </div>
            <form method="POST" action="dashboard_farmasi.php" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="tambah_obat">
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Kategori Obat</label>
                    <select name="kategori_id" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                        <?php foreach ($list_kategori as $kat): ?>
                        <option value="<?= $kat['id']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Kode Obat</label>
                        <input type="text" name="kode_obat" required placeholder="Contoh: OBT-011" class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Satuan</label>
                        <input type="text" name="satuan" required placeholder="Tablet / Botol / Tube" class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Nama Obat</label>
                    <input type="text" name="nama_obat" required placeholder="Nama obat lengkap..." class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Stok Awal</label>
                        <input type="number" name="stok_sekarang" min="0" value="50" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Batas Stok Minimal</label>
                        <input type="number" name="stok_minimal" min="1" value="10" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Tanggal Kadaluarsa (ED)</label>
                    <input type="date" name="exp_date" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Catatan Tambahan (Khusus Admin)</label>
                    <textarea name="keterangan" rows="2" placeholder="Catatan penyimpanan, dosis, dll..." class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]"></textarea>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="tutupModal('modal-tambah')" class="px-3.5 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold transition">Simpan Obat</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 2: EDIT OBAT                                             -->
    <!-- ============================================================== -->
    <div id="modal-edit" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-xl max-w-lg w-full p-5 space-y-3.5 border border-[#DCE7E0] shadow-xl my-auto">
            <div class="flex items-center justify-between pb-2.5 border-b border-[#DCE7E0]">
                <h3 class="font-bold text-sm text-[#0B3D2E]">Edit Data Obat</h3>
                <button type="button" onclick="tutupModal('modal-edit')" class="text-[#64756D] hover:text-black font-bold">&times;</button>
            </div>
            <form method="POST" action="dashboard_farmasi.php" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="edit_obat">
                <input type="hidden" name="obat_id" id="edit-id">
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Kategori Obat</label>
                    <select name="kategori_id" id="edit-kat" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                        <?php foreach ($list_kategori as $kat): ?>
                        <option value="<?= $kat['id']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Kode Obat</label>
                        <input type="text" name="kode_obat" id="edit-kode" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Satuan</label>
                        <input type="text" name="satuan" id="edit-satuan" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Nama Obat</label>
                    <input type="text" name="nama_obat" id="edit-nama" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Stok Saat Ini</label>
                        <input type="number" name="stok_sekarang" id="edit-stok" min="0" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num font-bold">
                    </div>
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Batas Minimal</label>
                        <input type="number" name="stok_minimal" id="edit-minimal" min="1" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                    <div>
                        <label class="block font-semibold text-[#0B3D2E] mb-1">Tanggal Expired</label>
                        <input type="date" name="exp_date" id="edit-exp" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num">
                    </div>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Catatan Tambahan</label>
                    <textarea name="keterangan" id="edit-ket" rows="2" class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]"></textarea>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="tutupModal('modal-edit')" class="px-3.5 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold transition">Perbarui Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 3: RESTOCK OBAT                                          -->
    <!-- ============================================================== -->
    <div id="modal-restock" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-sm w-full p-5 space-y-3.5 border border-[#DCE7E0] shadow-xl">
            <div class="flex items-center justify-between pb-2 border-b border-[#DCE7E0]">
                <h3 class="font-bold text-sm text-[#0B3D2E]">Tambah Stok Obat</h3>
                <button type="button" onclick="tutupModal('modal-restock')" class="text-[#64756D] hover:text-black font-bold">&times;</button>
            </div>
            <form method="POST" action="dashboard_farmasi.php" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="restock_obat">
                <input type="hidden" name="obat_id" id="restock-id">
                <div class="p-2.5 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0]">
                    <span class="text-[10px] uppercase font-bold text-[#64756D] block">Nama Obat:</span>
                    <strong class="text-xs text-[#0B3D2E] block" id="restock-nama">-</strong>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Jumlah Unit Masuk:</label>
                    <input type="number" name="jumlah" min="1" value="50" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6] mono-num font-bold">
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Keterangan Sumber Logistik:</label>
                    <input type="text" name="keterangan" value="Restock pengadaan logistik Farmasi Al-Bahjah" required class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]">
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="tutupModal('modal-restock')" class="px-3.5 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold transition">+ Simpan Stok</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 4: CATATAN UNTUK ADMIN                                   -->
    <!-- ============================================================== -->
    <div id="modal-catatan" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-5 space-y-3.5 border border-[#DCE7E0] shadow-xl">
            <div class="flex items-center justify-between pb-2 border-b border-[#DCE7E0]">
                <h3 class="font-bold text-sm text-[#0B3D2E]">Kirim Catatan ke Admin</h3>
                <button type="button" onclick="tutupModal('modal-catatan')" class="text-[#64756D] hover:text-black font-bold">&times;</button>
            </div>
            <form method="POST" action="dashboard_farmasi.php" class="space-y-3 text-xs">
                <input type="hidden" name="action" value="simpan_catatan_admin">
                <input type="hidden" name="obat_id" id="catatan-id">
                <div class="p-2.5 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0]">
                    <span class="text-[10px] uppercase font-bold text-[#64756D] block">Obat Terpilih:</span>
                    <strong class="text-xs text-[#0B3D2E] block" id="catatan-nama">-</strong>
                </div>
                <div>
                    <label class="block font-semibold text-[#0B3D2E] mb-1">Tulis Catatan / Permintaan PO Logistik:</label>
                    <textarea name="catatan_admin" id="catatan-isi" rows="3" required placeholder="Contoh: Stok menipis, mohon ajukan PO 10 box ke distributor..." class="w-full p-2 rounded-lg border border-[#DCE7E0] bg-[#F5F8F6]"></textarea>
                    <span class="text-[10px] text-[#64756D] mt-1 block">Catatan ini akan otomatis muncul pada dashboard Admin di modul logistik obat.</span>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="tutupModal('modal-catatan')" class="px-3.5 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold transition">Simpan Catatan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form Tersembunyi untuk Hapus Obat -->
    <form id="form-hapus-obat" method="POST" action="dashboard_farmasi.php" class="hidden">
        <input type="hidden" name="action" value="hapus_obat">
        <input type="hidden" name="obat_id" id="hapus-id">
    </form>

    <!-- JavaScript Handlers -->
    <script>
        function bukaModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function tutupModal(id) {
            document.getElementById(id).classList.add('hidden');
        }
        function bukaModalTambah() {
            bukaModal('modal-tambah');
        }
        function bukaModalEdit(ob) {
            document.getElementById('edit-id').value = ob.id;
            document.getElementById('edit-kode').value = ob.kode_obat;
            document.getElementById('edit-nama').value = ob.nama_obat;
            document.getElementById('edit-kat').value = ob.kategori_id;
            document.getElementById('edit-satuan').value = ob.satuan;
            if (document.getElementById('edit-stok')) {
                document.getElementById('edit-stok').value = ob.stok_sekarang || 0;
            }
            document.getElementById('edit-minimal').value = ob.stok_minimal;
            document.getElementById('edit-exp').value = ob.exp_date;
            document.getElementById('edit-ket').value = ob.keterangan || '';
            bukaModal('modal-edit');
        }
        function bukaModalRestock(id, nama) {
            document.getElementById('restock-id').value = id;
            document.getElementById('restock-nama').textContent = nama;
            bukaModal('modal-restock');
        }
        function bukaModalCatatan(id, nama, ket) {
            document.getElementById('catatan-id').value = id;
            document.getElementById('catatan-nama').textContent = nama;
            document.getElementById('catatan-isi').value = ket;
            bukaModal('modal-catatan');
        }
        function konfirmasiHapusObat(id, nama) {
            if (confirm("Apakah Anda yakin ingin menghapus obat '" + nama + "' dari inventaris?")) {
                document.getElementById('hapus-id').value = id;
                document.getElementById('form-hapus-obat').submit();
            }
        }
        function filterObat() {
            const keyword = document.getElementById('cari-obat').value.toLowerCase();
            const rows = document.querySelectorAll('#tabel-obat tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(keyword) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
