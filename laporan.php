<?php
// laporan.php - Sistem Rekapitulasi Laporan Medis, Triase SAW, dan Farmasi
// Poskestren Al-Bahjah Cabang Bogor
ob_start();

$page_title = "Laporan & Rekapitulasi Data";
$page_desc  = "Pelaporan kunjungan klinis, distribusi triase SAW, diagnosa terbanyak, dan mutasi obat.";
require_once 'koneksi.php';

// Hak Akses: Admin, Petugas Kesehatan, Petugas Farmasi
require_login();
$user_role = $_SESSION['role'] ?? 'guest';
if (!in_array($user_role, ['admin', 'petugas_kesehatan', 'petugas_farmasi'])) {
    set_flash('error', "Akses Ditolak: Halaman laporan hanya untuk tenaga medis dan admin.");
    header("Location: dashboard.php");
    exit();
}

// Filter Periode Tanggal
$tgl_mulai     = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai   = $_GET['tgl_selesai'] ?? date('Y-m-d');
$jenis_laporan = $_GET['jenis'] ?? 'kunjungan';

// Sanitasi tanggal
$esc_tgl_mulai   = $conn->real_escape_string($tgl_mulai);
$esc_tgl_selesai = $conn->real_escape_string($tgl_selesai);

// ==============================================================================
// 1. FITUR UNDUH TABEL EXCEL (.XLS RESMI BERFORMAT TABEL & KOP)
// ==============================================================================
if (isset($_GET['export']) && in_array($_GET['export'], ['excel', 'tabel', 'unduh'])) {
    if (ob_get_length()) ob_end_clean();

    $filename = "Laporan_Poskestren_" . $jenis_laporan . "_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
            body { font-family: Arial, sans-serif; font-size: 11px; color: #182923; }
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            th { background-color: #16845B; color: #FFFFFF; font-weight: bold; border: 1px solid #0B3D2E; padding: 7px; text-align: center; font-size: 11px; }
            td { border: 1px solid #DCE7E0; padding: 6px 8px; font-size: 10.5px; vertical-align: middle; }
            .kop-title { font-size: 14px; font-weight: bold; color: #0B3D2E; text-align: center; }
            .kop-sub { font-size: 10px; color: #64756D; text-align: center; }
            .p1 { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
            .p2 { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
            .p3 { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
          </style>';
    echo '</head><body>';

    echo '<div style="text-align:center; padding: 10px 0;">';
    echo '<div class="kop-title">POSKESTREN AL-BAHJAH CABANG BOGOR</div>';
    echo '<div class="kop-sub">Lembaga Pengembangan Dakwah Al-Bahjah — Pos Kesehatan Pesantren & Triase Medis</div>';
    echo '<div style="font-size:12px; font-weight:bold; margin-top:5px; text-decoration:underline;">';
    echo ($jenis_laporan === 'kunjungan') ? 'REKAPITULASI PELAYANAN MEDIS & TRIASE SAW SANTRI' : 'REKAPITULASI MUTASI PENGGUNAAN OBAT FARMASI';
    echo '</div>';
    echo '<div style="font-size:10px; color:#555; margin-top:3px;">Periode: ' . tgl_indo($tgl_mulai, false) . ' s/d ' . tgl_indo($tgl_selesai, false) . '</div>';
    echo '</div>';

    if ($jenis_laporan === 'kunjungan') {
        echo '<table>';
        echo '<thead><tr>
                <th>No</th>
                <th>No. Antrean</th>
                <th>Tanggal Berobat</th>
                <th>NIS</th>
                <th>Nama Santri</th>
                <th>Kamar Asrama</th>
                <th>Keluhan Utama</th>
                <th>Prioritas SAW</th>
                <th>Skor V</th>
                <th>Diagnosa Dokter</th>
                <th>Rekomendasi Tindakan</th>
                <th>Status</th>
              </tr></thead><tbody>';

        $q_ex = $conn->query("SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, pk.diagnosa_dokter, pk.rekomendasi_rawat 
                              FROM kunjungan_sakit k 
                              JOIN santri s ON k.santri_id = s.id 
                              LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                              WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                              ORDER BY k.id DESC");
        $no = 1;
        if ($q_ex && $q_ex->num_rows > 0) {
            while ($r = $q_ex->fetch_assoc()) {
                $pr = $r['tingkat_prioritas'];
                $sk = (float)$r['skor_saw_akhir'];
                $p_cls = ($pr === 'P1_Gawat_Darurat' || $sk >= 0.80) ? 'p1' : (($pr === 'P2_Mendesak' || $sk >= 0.50) ? 'p2' : 'p3');
                $p_lbl = ($pr === 'P1_Gawat_Darurat' || $sk >= 0.80) ? 'P1 - Darurat' : (($pr === 'P2_Mendesak' || $sk >= 0.50) ? 'P2 - Mendesak' : 'P3 - Biasa');

                echo '<tr>';
                echo '<td class="center">' . $no++ . '</td>';
                echo '<td class="center bold">' . htmlspecialchars($r['no_antrean']) . '</td>';
                echo '<td class="center">' . date('d/m/Y H:i', strtotime($r['tanggal_berobat'])) . '</td>';
                echo '<td class="center">' . htmlspecialchars($r['nis']) . '</td>';
                echo '<td class="bold">' . htmlspecialchars($r['nama_lengkap']) . '</td>';
                echo '<td>' . htmlspecialchars($r['kamar_asrama'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($r['keluhan_utama']) . '</td>';
                echo '<td class="' . $p_cls . '">' . $p_lbl . '</td>';
                echo '<td class="center bold">' . number_format($sk, 4) . '</td>';
                echo '<td>' . htmlspecialchars($r['diagnosa_dokter'] ?: '-') . '</td>';
                echo '<td class="center">' . htmlspecialchars($r['rekomendasi_rawat'] ?: 'Rawat Jalan') . '</td>';
                echo '<td class="center">' . htmlspecialchars($r['status_antrean']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="12" class="center" style="padding:15px; color:#888;">Tidak ada data kunjungan pada periode ini.</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        // Tabel Obat
        echo '<table>';
        echo '<thead><tr>
                <th>No</th>
                <th>Waktu Transaksi</th>
                <th>Kode Obat</th>
                <th>Nama Obat</th>
                <th>Jenis Mutasi</th>
                <th>Jumlah</th>
                <th>Satuan</th>
                <th>Keterangan Mutasi</th>
              </tr></thead><tbody>';

        $q_ex = $conn->query("SELECT m.*, o.kode_obat, o.nama_obat, o.satuan 
                              FROM obat_mutasi_stok m 
                              JOIN obat o ON m.obat_id = o.id 
                              WHERE DATE(m.created_at) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                              ORDER BY m.id DESC");
        $no = 1;
        if ($q_ex && $q_ex->num_rows > 0) {
            while ($r = $q_ex->fetch_assoc()) {
                $is_m = ($r['jenis_mutasi'] === 'masuk');
                echo '<tr>';
                echo '<td class="center">' . $no++ . '</td>';
                echo '<td class="center">' . date('d/m/Y H:i', strtotime($r['created_at'])) . '</td>';
                echo '<td class="center bold">' . htmlspecialchars($r['kode_obat']) . '</td>';
                echo '<td class="bold">' . htmlspecialchars($r['nama_obat']) . '</td>';
                echo '<td class="center bold" style="color:' . ($is_m ? '#166534' : '#991b1b') . ';">' . strtoupper($r['jenis_mutasi']) . '</td>';
                echo '<td class="center bold">' . ($is_m ? '+' : '-') . $r['jumlah'] . '</td>';
                echo '<td class="center">' . htmlspecialchars($r['satuan']) . '</td>';
                echo '<td>' . htmlspecialchars($r['keterangan'] ?: '-') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="center" style="padding:15px; color:#888;">Tidak ada catatan mutasi obat pada periode ini.</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '<div style="margin-top:25px; font-size:10px; color:#666;">Dicetak otomatis melalui Sistem SPK Triase Poskestren Al-Bahjah Bogor pada ' . date('d/m/Y H:i') . ' WIB</div>';
    echo '</body></html>';
    exit();
}

// ==============================================================================
// 2. FITUR EKSPOR CSV BERSIH (MURNI TANPA BOCOR HTML)
// ==============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Poskestren_' . $jenis_laporan . '_' . date('Ymd_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Sisipkan UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($jenis_laporan === 'kunjungan') {
        fputcsv($output, ['No Antrean', 'Tanggal', 'NIS', 'Nama Santri', 'Kamar Asrama', 'Keluhan Utama', 'Prioritas SAW', 'Skor SAW', 'Diagnosa', 'Tindakan', 'Status']);
        $q_csv = $conn->query("SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, pk.diagnosa_dokter, pk.rekomendasi_rawat 
                               FROM kunjungan_sakit k 
                               JOIN santri s ON k.santri_id = s.id 
                               LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                               WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                               ORDER BY k.id DESC");
        if ($q_csv) {
            while ($row = $q_csv->fetch_assoc()) {
                fputcsv($output, [
                    $row['no_antrean'],
                    $row['tanggal_berobat'],
                    $row['nis'],
                    $row['nama_lengkap'],
                    $row['kamar_asrama'],
                    $row['keluhan_utama'],
                    $row['tingkat_prioritas'],
                    $row['skor_saw_akhir'],
                    $row['diagnosa_dokter'] ?: '-',
                    $row['rekomendasi_rawat'] ?: 'Rawat Jalan',
                    $row['status_antrean']
                ]);
            }
        }
    } else if ($jenis_laporan === 'obat') {
        fputcsv($output, ['Waktu Mutasi', 'Kode Obat', 'Nama Obat', 'Jenis Mutasi', 'Jumlah', 'Satuan', 'Keterangan']);
        $q_csv = $conn->query("SELECT m.*, o.kode_obat, o.nama_obat, o.satuan 
                               FROM obat_mutasi_stok m 
                               JOIN obat o ON m.obat_id = o.id 
                               WHERE DATE(m.created_at) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                               ORDER BY m.id DESC");
        if ($q_csv) {
            while ($row = $q_csv->fetch_assoc()) {
                fputcsv($output, [
                    $row['created_at'],
                    $row['kode_obat'],
                    $row['nama_obat'],
                    $row['jenis_mutasi'],
                    $row['jumlah'],
                    $row['satuan'],
                    $row['keterangan']
                ]);
            }
        }
    }
    fclose($output);
    exit();
}

// ==============================================================================
// 3. TAMPILAN WEB (LAYOUT RESMI DENGAN SIDEBAR HEADER.PHP)
// ==============================================================================
require_once 'header.php';

// Data Laporan Kunjungan & Triase SAW
$sql_lap_kunjungan = "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, pk.diagnosa_dokter, pk.rekomendasi_rawat 
                      FROM kunjungan_sakit k 
                      JOIN santri s ON k.santri_id = s.id 
                      LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                      WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                      ORDER BY k.id DESC";
$res_lap_kunjungan = $conn->query($sql_lap_kunjungan);

// Ringkasan Statistik Periode
$q_tot = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}'");
$tot_periode = ($q_tot && $q_tot instanceof mysqli_result) ? (int)($q_tot->fetch_assoc()['c'] ?? 0) : 0;

$q_p1 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P1_Gawat_Darurat' OR skor_saw_akhir >= 0.80)");
$p1_periode = ($q_p1 && $q_p1 instanceof mysqli_result) ? (int)($q_p1->fetch_assoc()['c'] ?? 0) : 0;

$q_p2 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P2_Mendesak' OR (skor_saw_akhir >= 0.50 AND skor_saw_akhir < 0.80))");
$p2_periode = ($q_p2 && $q_p2 instanceof mysqli_result) ? (int)($q_p2->fetch_assoc()['c'] ?? 0) : 0;

$q_p3 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P3_Biasa' AND skor_saw_akhir < 0.50)");
$p3_periode = ($q_p3 && $q_p3 instanceof mysqli_result) ? (int)($q_p3->fetch_assoc()['c'] ?? 0) : 0;

// Data 5 Besar Diagnosa Penyakit
$sql_top_diag = "SELECT diagnosa_dokter, COUNT(*) as jml 
                 FROM pemeriksaan_klinis pk 
                 JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id 
                 WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND diagnosa_dokter IS NOT NULL AND diagnosa_dokter != '' 
                 GROUP BY diagnosa_dokter ORDER BY jml DESC LIMIT 5";
$res_top_diag = $conn->query($sql_top_diag);

// Data 5 Besar Pemakaian Obat (Aman & Bergabung dengan kunjungan_sakit)
$sql_top_obat = "SELECT o.nama_obat, o.satuan, SUM(rd.jumlah) as total_keluar 
                 FROM resep_obat_detail rd 
                 JOIN resep_obat r ON rd.resep_id = r.id 
                 JOIN kunjungan_sakit k ON r.kunjungan_id = k.id 
                 JOIN obat o ON rd.obat_id = o.id 
                 WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND r.status_resep = 'selesai' 
                 GROUP BY o.id ORDER BY total_keluar DESC LIMIT 5";
$res_top_obat = $conn->query($sql_top_obat);
?>

<style>
    @media print {
        body { background: white !important; font-size: 11pt; }
        aside, header, footer, .no-print { display: none !important; }
        main { padding: 0 !important; }
        .card, .card-custom { border: none !important; box-shadow: none !important; }
        .print-only { display: block !important; }
    }
    .print-only { display: none; }
</style>

<!-- KOP SURAT CETAK (Hanya Muncul saat Print) -->
<div class="print-only mb-6 pb-4 border-b-2 border-black text-center">
    <h2 class="text-xl font-bold uppercase tracking-wider text-black">POSKESTREN AL-BAHJAH CABANG BOGOR</h2>
    <p class="text-xs text-gray-700">Lembaga Pengembangan Dakwah Al-Bahjah — Pos Kesehatan Pesantren</p>
    <p class="text-[10px] text-gray-600">Jl. Raya Pondok Pesantren Al-Bahjah, Kab. Bogor, Jawa Barat</p>
    <h3 class="text-sm font-bold uppercase mt-3 underline">
        <?= ($jenis_laporan === 'kunjungan') ? 'REKAPITULASI PELAYANAN MEDIS & TRIASE SAW' : 'REKAPITULASI MUTASI PENGGUNAAN OBAT'; ?>
    </h3>
    <p class="text-[10px] text-gray-700">Periode: <?= tgl_indo($tgl_mulai, false); ?> s/d <?= tgl_indo($tgl_selesai, false); ?></p>
</div>

<!-- FILTER PERIODE & AKSI (NO PRINT) -->
<div class="card p-5 mb-6 no-print">
    <form method="GET" action="laporan.php" class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Mulai</label>
                <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai); ?>" class="px-3 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Selesai</label>
                <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai); ?>" class="px-3 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Jenis Laporan</label>
                <select name="jenis" class="px-3 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold">
                    <option value="kunjungan" <?= $jenis_laporan === 'kunjungan' ? 'selected' : ''; ?>>Kunjungan & Triase SAW</option>
                    <option value="obat" <?= $jenis_laporan === 'obat' ? 'selected' : ''; ?>>Mutasi Penggunaan Obat</option>
                </select>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-[#0B3D2E] hover:bg-[#16845B] text-white text-xs font-bold transition whitespace-nowrap">
                    Filter Laporan
                </button>
            </div>
        </div>

        <!-- Tombol Aksi Berjejer Samping-Sampingan (Inline Flex-Nowrap) -->
        <div class="flex items-center gap-2 flex-nowrap shrink-0 overflow-x-auto pt-2 xl:pt-0">
            <!-- Tombol Cetak PDF / Print -->
            <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-white hover:bg-slate-50 text-xs font-bold text-[#0B3D2E] transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap">
                <svg class="w-4 h-4 text-[#16845B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak (Print View)</span>
            </button>

            <!-- Tombol Unduh Tabel Excel (Real Formatted Spreadsheet Table) -->
            <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>&export=excel" 
               class="px-3.5 py-2 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap"
               title="Unduh langsung dalam bentuk format tabel Excel lengkap dengan kop dan border">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Unduh Tabel (Excel)</span>
            </a>

            <!-- Tombol Ekspor CSV -->
            <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>&export=csv" 
               class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-white hover:bg-slate-50 text-xs font-bold text-[#64756D] hover:text-[#0B3D2E] transition flex items-center gap-1.5 shadow-2xs whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>CSV</span>
            </a>
        </div>
    </form>
</div>

<!-- RINGKASAN METRIK PERIODE LAPORAN -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="card p-4 text-center">
        <span class="text-[10px] uppercase font-bold text-[#64756D] block">Total Kunjungan</span>
        <span class="text-2xl font-extrabold text-[#0B3D2E] mt-1 block"><?= $tot_periode; ?></span>
        <span class="text-[10px] text-[#64756D]">santri berobat</span>
    </div>

    <div class="card p-4 text-center border-red-200 bg-red-50/20">
        <span class="text-[10px] uppercase font-bold text-red-700 block">P1 - Darurat</span>
        <span class="text-2xl font-extrabold text-[#D64545] mt-1 block"><?= $p1_periode; ?></span>
        <span class="text-[10px] text-red-600">skor SAW ≥ 0.80</span>
    </div>

    <div class="card p-4 text-center border-amber-200 bg-amber-50/20">
        <span class="text-[10px] uppercase font-bold text-amber-800 block">P2 - Mendesak</span>
        <span class="text-2xl font-extrabold text-amber-700 mt-1 block"><?= $p2_periode; ?></span>
        <span class="text-[10px] text-amber-600">skor SAW 0.50–0.79</span>
    </div>

    <div class="card p-4 text-center">
        <span class="text-[10px] uppercase font-bold text-[#64756D] block">P3 - Biasa</span>
        <span class="text-2xl font-extrabold text-[#16845B] mt-1 block"><?= $p3_periode; ?></span>
        <span class="text-[10px] text-[#64756D]">skor SAW < 0.50</span>
    </div>
</div>

<!-- SECTION TABEL DATA LAPORAN -->
<div class="card overflow-hidden mb-6">
    <div class="p-4 sm:p-5 border-b border-[#DCE7E0] flex items-center justify-between">
        <div>
            <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base">
                <?= $jenis_laporan === 'kunjungan' ? 'Data Kunjungan Klinis & Triase SAW Santri' : 'Data Penggunaan & Mutasi Stok Obat'; ?>
            </h3>
            <p class="text-xs text-[#64756D]">Rentang: <?= tgl_indo($tgl_mulai, false); ?> s/d <?= tgl_indo($tgl_selesai, false); ?></p>
        </div>
        <span class="text-xs font-mono font-bold text-[#16845B]">Poskestren Al-Bahjah Bogor</span>
    </div>

    <div class="overflow-x-auto">
        <?php if ($jenis_laporan === 'kunjungan'): ?>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f0f4f2] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                        <th class="py-3 px-4">No. Antrean & Tgl</th>
                        <th class="py-3 px-4">Identitas Santri</th>
                        <th class="py-3 px-4">Keluhan Utama</th>
                        <th class="py-3 px-4 text-center">Prioritas SAW</th>
                        <th class="py-3 px-4 text-center">Skor V</th>
                        <th class="py-3 px-4">Diagnosa Dokter</th>
                        <th class="py-3 px-4 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php 
                    if ($res_lap_kunjungan && $res_lap_kunjungan->num_rows > 0):
                        while ($row = $res_lap_kunjungan->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-4">
                            <span class="font-mono font-bold text-[#16845B] block"><?= htmlspecialchars($row['no_antrean']); ?></span>
                            <span class="text-[10px] text-[#64756D]"><?= date('d/m/Y', strtotime($row['tanggal_berobat'])); ?></span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($row['nama_lengkap']); ?></span>
                            <span class="text-[10px] text-[#64756D]">NIS: <?= $row['nis']; ?> • <?= htmlspecialchars($row['kamar_asrama'] ?? '-'); ?></span>
                        </td>
                        <td class="py-3 px-4 max-w-xs text-xs"><?= htmlspecialchars($row['keluhan_utama']); ?></td>
                        <td class="py-3 px-4 text-center"><?= badge_triase($row['tingkat_prioritas'], $row['skor_saw_akhir']); ?></td>
                        <td class="py-3 px-4 text-center font-black text-xs text-[#0B3D2E]">
                            <?= number_format((float)$row['skor_saw_akhir'], 3); ?>
                        </td>
                        <td class="py-3 px-4 text-xs font-semibold text-[#182923]">
                            <?= htmlspecialchars($row['diagnosa_dokter'] ?: '-'); ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                <?= htmlspecialchars($row['rekomendasi_rawat'] ?: 'Rawat Jalan'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-[#64756D]">Tidak ada data kunjungan pada rentang tanggal ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- TABEL OBAT -->
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f0f4f2] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Kode & Nama Obat</th>
                        <th class="py-3 px-4 text-center">Jenis Mutasi</th>
                        <th class="py-3 px-4 text-center">Jumlah</th>
                        <th class="py-3 px-4">Keterangan Transaksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php 
                    $q_mut_lap = $conn->query("SELECT m.*, o.kode_obat, o.nama_obat, o.satuan 
                                               FROM obat_mutasi_stok m 
                                               JOIN obat o ON m.obat_id = o.id 
                                               WHERE DATE(m.created_at) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                                               ORDER BY m.id DESC");
                    if ($q_mut_lap && $q_mut_lap->num_rows > 0):
                        while ($mut = $q_mut_lap->fetch_assoc()):
                            $is_m = ($mut['jenis_mutasi'] === 'masuk');
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-4 text-[#64756D] font-mono"><?= tgl_indo($mut['created_at']); ?></td>
                        <td class="py-3 px-4 font-bold text-[#0B3D2E]"><?= htmlspecialchars($mut['nama_obat']); ?></td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $is_m ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                                <?= $mut['jenis_mutasi']; ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center font-bold <?= $is_m ? 'text-[#16845B]' : 'text-red-600'; ?>">
                            <?= $is_m ? '+' : '-'; ?> <?= $mut['jumlah']; ?> <?= htmlspecialchars($mut['satuan']); ?>
                        </td>
                        <td class="py-3 px-4 text-[#64756D]"><?= htmlspecialchars($mut['keterangan']); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-[#64756D]">Tidak ada catatan mutasi obat pada rentang tanggal ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- SECTION STATISTIK KESEHATAN TERTINGGI (TOP 5) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 no-print">
    <!-- Top 5 Diagnosa Terbanyak -->
    <div class="card p-5">
        <h4 class="font-extrabold text-[#0B3D2E] text-xs uppercase tracking-wider mb-3">🩺 5 Diagnosis Terbanyak Santri</h4>
        <div class="space-y-2 text-xs">
            <?php 
            if ($res_top_diag && $res_top_diag->num_rows > 0):
                while ($td = $res_top_diag->fetch_assoc()):
            ?>
            <div class="p-2.5 rounded-xl bg-[#f0f4f2] border border-[#DCE7E0] flex items-center justify-between">
                <span class="font-semibold text-[#182923]"><?= htmlspecialchars($td['diagnosa_dokter']); ?></span>
                <span class="font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px]"><?= $td['jml']; ?> kasus</span>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <p class="text-[11px] text-[#64756D]">Belum ada data diagnosis tercatat.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top 5 Obat Terpakai -->
    <div class="card p-5">
        <h4 class="font-extrabold text-[#0B3D2E] text-xs uppercase tracking-wider mb-3">💊 5 Obat Paling Banyak Diresepkan</h4>
        <div class="space-y-2 text-xs">
            <?php 
            if ($res_top_obat && $res_top_obat->num_rows > 0):
                while ($to = $res_top_obat->fetch_assoc()):
            ?>
            <div class="p-2.5 rounded-xl bg-[#f0f4f2] border border-[#DCE7E0] flex items-center justify-between">
                <span class="font-semibold text-[#182923]"><?= htmlspecialchars($to['nama_obat']); ?></span>
                <span class="font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[11px]"><?= $to['total_keluar']; ?> <?= htmlspecialchars($to['satuan']); ?></span>
            </div>
            <?php 
                endwhile;
            else:
            ?>
            <p class="text-[11px] text-[#64756D]">Belum ada resep obat selesai.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tanda Tangan Cetak Laporan (Print View Only) -->
<div class="print-only mt-10 pt-4 flex justify-between text-xs text-black">
    <div class="text-center w-56">
        <p>Mengetahui,</p>
        <p class="font-bold">Pengasuh / Pimpinan Poskestren</p>
        <div class="h-16"></div>
        <p class="border-t border-black pt-1 font-bold">( ............................................ )</p>
    </div>
    <div class="text-center w-56">
        <p>Bogor, <?= date('d F Y'); ?></p>
        <p class="font-bold">Dokter / Petugas Penanggung Jawab</p>
        <div class="h-16"></div>
        <p class="border-t border-black pt-1 font-bold"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'dr. Penanggung Jawab'); ?></p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
