<?php
// laporan.php - Sistem Rekapitulasi Laporan Medis, Triase SAW, dan Farmasi
// Poskestren Al-Bahjah Cabang Bogor
ob_start();

$page_title = "Laporan & Rekapitulasi Data";
$page_desc  = "Pelaporan resmi pelayanan klinis, distribusi triase SAW, diagnosa penyakit, dan mutasi obat ber-Kop Surat.";
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

// Sanitasi tanggal aman
$esc_tgl_mulai   = ($conn && $conn instanceof mysqli && empty($conn->connect_error)) ? $conn->real_escape_string($tgl_mulai) : addslashes($tgl_mulai);
$esc_tgl_selesai = ($conn && $conn instanceof mysqli && empty($conn->connect_error)) ? $conn->real_escape_string($tgl_selesai) : addslashes($tgl_selesai);

// Nomor Surat Resmi Berdasarkan Bulan dan Tahun
$bln_romawi = [
    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
    7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
];
$no_surat_resmi = "0" . date('n') . "/LP-MED/POSKESTREN-AB/BGR/" . ($bln_romawi[(int)date('n')] ?? 'XI') . "/" . date('Y');

// ==============================================================================
// 1. FITUR UNDUH TABEL EXCEL (.XLS RESMI BERFORMAT TABEL & KOP SURAT)
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
            th { background-color: #0B3D2E; color: #FFFFFF; font-weight: bold; border: 1px solid #0B3D2E; padding: 8px; text-align: center; font-size: 11px; }
            td { border: 1px solid #DCE7E0; padding: 6px 8px; font-size: 10.5px; vertical-align: middle; }
            .kop-yayasan { font-size: 12px; font-weight: bold; color: #182923; text-align: center; letter-spacing: 1px; }
            .kop-lembaga { font-size: 15px; font-weight: bold; color: #0B3D2E; text-align: center; letter-spacing: 0.5px; }
            .kop-alamat { font-size: 9.5px; color: #475569; text-align: center; }
            .judul-dokumen { font-size: 13px; font-weight: bold; color: #0B3D2E; text-align: center; text-decoration: underline; margin-top: 10px; }
            .no-dokumen { font-size: 10px; color: #334155; text-align: center; margin-top: 2px; }
            .p1 { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
            .p2 { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
            .p3 { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
          </style>';
    echo '</head><body>';

    // KOP SURAT EXCEL
    echo '<div style="text-align:center; padding: 10px 0; border-bottom: 2px solid #0B3D2E;">';
    echo '<div class="kop-yayasan">YAYASAN LEMBAGA PENGEMBANGAN DAKWAH AL-BAHJAH</div>';
    echo '<div class="kop-lembaga">POS KESEHATAN PESANTREN (POSKESTREN) AL-BAHJAH BOGOR</div>';
    echo '<div class="kop-alamat">Jl. Raya Pondok Pesantren Al-Bahjah, Kec. Megamendung, Kab. Bogor, Jawa Barat 16770 | Hotline Medis: 0811-2233-4455</div>';
    echo '<div class="judul-dokumen">' . ($jenis_laporan === 'kunjungan' ? 'LAPORAN REKAPITULASI PELAYANAN KLINIS & SISTEM TRIASE SAW SANTRI' : 'LAPORAN REKAPITULASI MUTASI PENGGUNAAN OBAT FARMASI') . '</div>';
    echo '<div class="no-dokumen">Nomor: ' . $no_surat_resmi . ' • Periode: ' . tgl_indo($tgl_mulai, false) . ' s/d ' . tgl_indo($tgl_selesai, false) . '</div>';
    echo '</div>';

    if ($jenis_laporan === 'kunjungan') {
        echo '<table>';
        echo '<thead><tr>
                <th>No</th>
                <th>No. Antrean</th>
                <th>Tanggal Berobat</th>
                <th>NIS</th>
                <th>Nama Lengkap Santri</th>
                <th>Kamar Asrama</th>
                <th>Keluhan Utama</th>
                <th>Prioritas SAW</th>
                <th>Skor Akhir (V)</th>
                <th>Diagnosa Dokter</th>
                <th>Tindakan / Disposisi</th>
                <th>Status Pelayanan</th>
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

    // Blok Tanda Tangan Excel
    echo '<table style="margin-top:30px; border:none; width:100%;">';
    echo '<tr>';
    echo '<td style="border:none; width:50%; text-align:center;">
            Mengetahui,<br><b>Pimpinan Poskestren Al-Bahjah Bogor</b><br><br><br><br>
            <b><u>Ust. H. Ahmad Fauzi, S.Pd.I</u></b><br>NIP: 2018-AB-BGR-004
          </td>';
    echo '<td style="border:none; width:50%; text-align:center;">
            Bogor, ' . tgl_indo(date('Y-m-d'), false) . '<br><b>Dokter / Petugas Penanggung Jawab</b><br><br><br><br>
            <b><u>' . htmlspecialchars($_SESSION['nama_lengkap'] ?? 'dr. Medis Poskestren') . '</u></b><br>SIP: 446/1829/STR-DOK/BGR
          </td>';
    echo '</tr></table>';

    echo '<div style="margin-top:20px; font-size:9px; color:#666;">Dokumen Resmi SPK Triase SAW Poskestren Al-Bahjah Bogor diekspor pada ' . date('d/m/Y H:i') . ' WIB</div>';
    echo '</body></html>';
    exit();
}

// ==============================================================================
// 2. FITUR EKSPOR CSV BERSIH (DOWNLOAD LANGSUNG DENGAN HEADER RESMI)
// ==============================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_end_clean();

    $filename = "Laporan_Poskestren_" . $jenis_laporan . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Sisipkan UTF-8 BOM agar Excel dapat membuka teks UTF-8 tanpa merusak karakter
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header Metadata Resmi
    fputcsv($output, ['POSKESTREN AL-BAHJAH CABANG BOGOR']);
    fputcsv($output, ['LAPORAN RESMI: ' . strtoupper($jenis_laporan === 'kunjungan' ? 'Kunjungan Klinis & Triase SAW Santri' : 'Mutasi Penggunaan Obat')]);
    fputcsv($output, ['Periode', $tgl_mulai . ' s/d ' . $tgl_selesai]);
    fputcsv($output, ['Nomor Dokumen', $no_surat_resmi]);
    fputcsv($output, []); // Baris kosong

    if ($jenis_laporan === 'kunjungan') {
        fputcsv($output, ['No Antrean', 'Tanggal Berobat', 'NIS', 'Nama Santri', 'Kamar Asrama', 'Keluhan Utama', 'Prioritas SAW', 'Skor SAW', 'Diagnosa Dokter', 'Rekomendasi Tindakan', 'Status Antrean']);
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
                    $row['kamar_asrama'] ?? '-',
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
                    $row['keterangan'] ?: '-'
                ]);
            }
        }
    }
    fclose($output);
    exit();
}

// ==============================================================================
// QUERY DATA UNTUK DOKUMEN & STATISTIK
// ==============================================================================
$is_db_ready = ($conn && $conn instanceof mysqli && empty($conn->connect_error));

// Data Kunjungan
$sql_lap_kunjungan = "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama, 
                             pk.diagnosa_dokter, pk.rekomendasi_rawat, pk.suhu_tubuh, pk.tensi_darah, pk.nadi, pk.spo2
                      FROM kunjungan_sakit k 
                      JOIN santri s ON k.santri_id = s.id 
                      LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                      WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                      ORDER BY k.id DESC";
$res_lap_kunjungan = $is_db_ready ? $conn->query($sql_lap_kunjungan) : false;

// Ringkasan Statistik
$tot_periode = 0;
$p1_periode  = 0;
$p2_periode  = 0;
$p3_periode  = 0;
$inap_periode = 0;

if ($is_db_ready) {
    $q_tot = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}'");
    $tot_periode = ($q_tot && $q_tot instanceof mysqli_result) ? (int)($q_tot->fetch_assoc()['c'] ?? 0) : 0;

    $q_p1 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P1_Gawat_Darurat' OR skor_saw_akhir >= 0.80)");
    $p1_periode = ($q_p1 && $q_p1 instanceof mysqli_result) ? (int)($q_p1->fetch_assoc()['c'] ?? 0) : 0;

    $q_p2 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P2_Mendesak' OR (skor_saw_akhir >= 0.50 AND skor_saw_akhir < 0.80))");
    $p2_periode = ($q_p2 && $q_p2 instanceof mysqli_result) ? (int)($q_p2->fetch_assoc()['c'] ?? 0) : 0;

    $q_p3 = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND (tingkat_prioritas = 'P3_Biasa' AND skor_saw_akhir < 0.50)");
    $p3_periode = ($q_p3 && $q_p3 instanceof mysqli_result) ? (int)($q_p3->fetch_assoc()['c'] ?? 0) : 0;

    // Statistik Disposisi
    $q_inap = $conn->query("SELECT COUNT(*) as c FROM pemeriksaan_klinis pk JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND pk.rekomendasi_rawat LIKE '%Inap%'");
    $inap_periode = ($q_inap && $q_inap instanceof mysqli_result) ? (int)($q_inap->fetch_assoc()['c'] ?? 0) : 0;
}

// Data 5 Besar Diagnosa Penyakit
$sql_top_diag = "SELECT diagnosa_dokter, COUNT(*) as jml 
                 FROM pemeriksaan_klinis pk 
                 JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id 
                 WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND diagnosa_dokter IS NOT NULL AND diagnosa_dokter != '' 
                 GROUP BY diagnosa_dokter ORDER BY jml DESC LIMIT 5";
$res_top_diag = $is_db_ready ? $conn->query($sql_top_diag) : false;

// Data 5 Besar Pemakaian Obat
$sql_top_obat = "SELECT o.nama_obat, o.satuan, SUM(rd.jumlah) as total_keluar 
                 FROM resep_obat_detail rd 
                 JOIN resep_obat r ON rd.resep_id = r.id 
                 JOIN kunjungan_sakit k ON r.kunjungan_id = k.id 
                 JOIN obat o ON rd.obat_id = o.id 
                 WHERE DATE(k.tanggal_berobat) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' AND r.status_resep = 'selesai' 
                 GROUP BY o.id ORDER BY total_keluar DESC LIMIT 5";
$res_top_obat = $is_db_ready ? $conn->query($sql_top_obat) : false;

// Data Mutasi Obat jika jenis = obat
$sql_lap_obat = "SELECT m.*, o.kode_obat, o.nama_obat, o.satuan 
                 FROM obat_mutasi_stok m 
                 JOIN obat o ON m.obat_id = o.id 
                 WHERE DATE(m.created_at) BETWEEN '{$esc_tgl_mulai}' AND '{$esc_tgl_selesai}' 
                 ORDER BY m.id DESC";
$res_lap_obat = ($is_db_ready && $jenis_laporan === 'obat') ? $conn->query($sql_lap_obat) : null;

// ==============================================================================
// 3. FITUR CETAK LEMBAR RESMI MANDIRI (STANDALONE PRINT VIEW DENGAN KOP SURAT)
// ==============================================================================
if (isset($_GET['print']) && $_GET['print'] == '1') {
    if (ob_get_length()) ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cetak Laporan Resmi - Poskestren Al-Bahjah Bogor</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cinzel:wght@700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; color: #1e293b; background-color: #f8fafc; }
            @page {
                size: A4 landscape;
                margin: 10mm 12mm 12mm 12mm;
            }
            @media print {
                body { background: white !important; font-size: 10pt; color: black !important; }
                .no-print { display: none !important; }
                .print-container { box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
                table { page-break-inside: auto; }
                tr { page-break-inside: avoid; page-break-after: auto; }
                thead { display: table-header-group; }
                tfoot { display: table-footer-group; }
            }
            .border-double-custom {
                border-bottom: 3.5px solid #0B3D2E;
                position: relative;
            }
            .border-double-custom::after {
                content: '';
                position: absolute;
                bottom: -5px;
                left: 0;
                right: 0;
                height: 1px;
                background-color: #0B3D2E;
            }
        </style>
    </head>
    <body class="p-4 sm:p-8">

        <!-- FLOATING ACTION BAR FOR SCREEN PREVIEW -->
        <div class="no-print max-w-7xl mx-auto mb-6 bg-slate-900 text-white p-3.5 rounded-2xl shadow-xl flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#16845B] flex items-center justify-center font-bold text-lg">
                    🖨️
                </div>
                <div>
                    <h3 class="font-bold text-sm leading-tight">Pratinjau Lembar Cetak Dokumen Resmi Ber-Kop Surat</h3>
                    <p class="text-xs text-slate-300">Poskestren Al-Bahjah Cabang Bogor • Dokumen Siap Cetak (A4 Landscape / Portrait)</p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button onclick="window.print()" class="px-5 py-2 bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold rounded-xl shadow-md transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Sekarang (Print / PDF)</span>
                </button>
                <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-semibold rounded-xl transition">
                    ⬅️ Kembali ke Menu Laporan
                </a>
            </div>
        </div>

        <!-- PRINTABLE CONTAINER WITH REAL KOP SURAT -->
        <div class="print-container max-w-7xl mx-auto bg-white border border-[#DCE7E0] shadow-sm rounded-2xl p-6 sm:p-10 text-slate-900">
            
            <!-- ===================== KOP SURAT RESMI ===================== -->
            <div class="border-double-custom pb-3 mb-6">
                <div class="flex items-center justify-between gap-4">
                    <!-- LOGO EMBLEM RESMI AL-BAHJAH (VECTOR SVG) -->
                    <div class="w-20 h-20 shrink-0 flex items-center justify-center">
                        <svg class="w-20 h-20 drop-shadow-sm" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" r="47" fill="#0B3D2E" stroke="#F4C542" stroke-width="3"/>
                            <circle cx="50" cy="50" r="41" fill="none" stroke="#FFFFFF" stroke-width="1.2" stroke-dasharray="3 2"/>
                            <!-- Bulan Sabit Keemasan -->
                            <path d="M48 22C33 22 22 35 22 50C22 65 33 78 48 78C39 71 35 60 35 50C35 40 39 29 48 22Z" fill="#F4C542"/>
                            <!-- Simbol Medis Palang Putih & Hijau Poskestren -->
                            <path d="M57 34H67V44H77V56H67V66H57V56H47V44H57V34Z" fill="#FFFFFF"/>
                            <path d="M59 36H65V46H75V54H65V64H59V54H49V46H59V36Z" fill="#16845B"/>
                            <!-- Bintang Islam Emas -->
                            <polygon points="50,25 52,30 57,30 53,33 55,38 50,35 45,38 47,33 43,30 48,30" fill="#F4C542"/>
                        </svg>
                    </div>

                    <!-- IDENTITAS RESMI LEMBAGA -->
                    <div class="text-center flex-1 px-2">
                        <h3 class="text-xs sm:text-sm font-bold tracking-widest text-[#0B3D2E] uppercase">
                            YAYASAN LEMBAGA PENGEMBANGAN DAKWAH AL-BAHJAH
                        </h3>
                        <h1 class="text-base sm:text-xl font-extrabold text-[#0B3D2E] tracking-tight uppercase mt-0.5" style="font-family:'Cinzel', serif, 'Inter';">
                            POS KESEHATAN PESANTREN (POSKESTREN) AL-BAHJAH BOGOR
                        </h1>
                        <p class="text-[11px] font-semibold text-[#16845B] mt-0.5 tracking-wide">
                            Layanan Kesehatan Pratama Santri, Triase Kegawatdaruratan SPK SAW & Observasi Rawat Inap
                        </p>
                        <p class="text-[9.5px] text-slate-600 mt-1 leading-snug">
                            Jl. Raya Pondok Pesantren Al-Bahjah, Kec. Megamendung, Kab. Bogor, Jawa Barat 16770<br>
                            Hotline Medis: 0811-2233-4455 | Telp: (0251) 824-9988 | Email: poskestren.bogor@albahjah.or.id
                        </p>
                    </div>

                    <!-- KODE KLINIK & AKREDITASI -->
                    <div class="w-24 text-right hidden sm:block shrink-0">
                        <div class="border border-[#0B3D2E] rounded-lg p-1.5 text-center bg-emerald-50/40">
                            <span class="block text-[8px] font-bold text-slate-500 uppercase">KODE POSKESTREN</span>
                            <span class="block text-[10px] font-black font-mono text-[#0B3D2E]">PKT-32.01.24</span>
                            <span class="inline-block mt-0.5 px-1 py-0.2 text-[7px] font-bold bg-[#16845B] text-white rounded">TERVERIFIKASI</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== JUDUL LAPORAN & NOMOR RESMI ===================== -->
            <div class="text-center my-5">
                <h2 class="text-sm sm:text-base font-extrabold text-[#0B3D2E] uppercase underline tracking-wide">
                    <?= $jenis_laporan === 'kunjungan' 
                        ? 'LAPORAN REKAPITULASI PELAYANAN KLINIS & SISTEM TRIASE SANTRI' 
                        : 'LAPORAN REKAPITULASI PENGGUNAAN & MUTASI STOK OBAT FARMASI'; ?>
                </h2>
                <div class="flex flex-wrap items-center justify-center gap-3 text-xs text-slate-600 mt-1.5">
                    <span><b>Nomor:</b> <span class="font-mono"><?= $no_surat_resmi; ?></span></span>
                    <span>•</span>
                    <span><b>Periode Laporan:</b> <?= tgl_indo($tgl_mulai, false); ?> s/d <?= tgl_indo($tgl_selesai, false); ?></span>
                    <span>•</span>
                    <span><b>Dicetak:</b> <?= date('d/m/Y H:i'); ?> WIB</span>
                </div>
            </div>

            <!-- ===================== RINGKASAN METRIK RESMI ===================== -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 sm:gap-3 mb-5 text-center text-xs">
                <div class="p-2.5 rounded-xl border border-slate-300 bg-slate-50">
                    <span class="text-[9.5px] uppercase font-bold text-slate-500 block">Total Berobat</span>
                    <span class="text-lg font-black text-[#0B3D2E] block mt-0.5"><?= $tot_periode; ?></span>
                    <span class="text-[9px] text-slate-600">Santri Terlayani</span>
                </div>
                <div class="p-2.5 rounded-xl border border-red-300 bg-red-50">
                    <span class="text-[9.5px] uppercase font-bold text-red-800 block">P1 - Darurat</span>
                    <span class="text-lg font-black text-red-700 block mt-0.5"><?= $p1_periode; ?></span>
                    <span class="text-[9px] text-red-600">Skor SAW ≥ 0.80</span>
                </div>
                <div class="p-2.5 rounded-xl border border-amber-300 bg-amber-50">
                    <span class="text-[9.5px] uppercase font-bold text-amber-800 block">P2 - Mendesak</span>
                    <span class="text-lg font-black text-amber-700 block mt-0.5"><?= $p2_periode; ?></span>
                    <span class="text-[9px] text-amber-700">Skor SAW 0.50–0.79</span>
                </div>
                <div class="p-2.5 rounded-xl border border-emerald-300 bg-emerald-50">
                    <span class="text-[9.5px] uppercase font-bold text-emerald-800 block">P3 - Biasa</span>
                    <span class="text-lg font-black text-emerald-700 block mt-0.5"><?= $p3_periode; ?></span>
                    <span class="text-[9px] text-emerald-700">Skor SAW < 0.50</span>
                </div>
                <div class="p-2.5 rounded-xl border border-blue-300 bg-blue-50">
                    <span class="text-[9.5px] uppercase font-bold text-blue-800 block">Rawat Inap</span>
                    <span class="text-lg font-black text-blue-700 block mt-0.5"><?= $inap_periode; ?></span>
                    <span class="text-[9px] text-blue-600">Observasi Poskestren</span>
                </div>
            </div>

            <!-- ===================== TABEL DOKUMEN CETAK ===================== -->
            <div class="overflow-x-auto">
                <?php if ($jenis_laporan === 'kunjungan'): ?>
                    <table class="w-full text-left text-[10.5px] border-collapse border border-slate-700">
                        <thead>
                            <tr class="bg-[#0B3D2E] text-white font-bold text-center">
                                <th class="border border-slate-700 py-2 px-2 w-8">No</th>
                                <th class="border border-slate-700 py-2 px-2.5 w-24">No. Antrean & Tgl</th>
                                <th class="border border-slate-700 py-2 px-3">Identitas Santri</th>
                                <th class="border border-slate-700 py-2 px-3">Keluhan & Tanda Vital</th>
                                <th class="border border-slate-700 py-2 px-2.5 w-24">Prioritas SAW</th>
                                <th class="border border-slate-700 py-2 px-2 w-16">Skor (V)</th>
                                <th class="border border-slate-700 py-2 px-3">Diagnosa Dokter</th>
                                <th class="border border-slate-700 py-2 px-2.5 w-24">Tindakan</th>
                                <th class="border border-slate-700 py-2 px-2 w-20">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-800">
                            <?php 
                            $no = 1;
                            if ($res_lap_kunjungan && $res_lap_kunjungan->num_rows > 0):
                                while ($row = $res_lap_kunjungan->fetch_assoc()):
                                    $pr = $row['tingkat_prioritas'];
                                    $sk = (float)$row['skor_saw_akhir'];
                                    $is_p1 = ($pr === 'P1_Gawat_Darurat' || $sk >= 0.80);
                                    $is_p2 = (!$is_p1 && ($pr === 'P2_Mendesak' || $sk >= 0.50));
                            ?>
                            <tr class="<?= $no % 2 === 0 ? 'bg-slate-50' : 'bg-white'; ?>">
                                <td class="border border-slate-400 py-1.5 px-2 text-center font-semibold"><?= $no++; ?></td>
                                <td class="border border-slate-400 py-1.5 px-2.5 text-center font-mono">
                                    <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($row['no_antrean']); ?></span>
                                    <span class="text-[9px] text-slate-500"><?= date('d/m/Y H:i', strtotime($row['tanggal_berobat'])); ?></span>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3">
                                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($row['nama_lengkap']); ?></span>
                                    <span class="text-[9.5px] text-slate-600">NIS: <?= $row['nis']; ?> • <?= htmlspecialchars($row['kamar_asrama'] ?? '-'); ?></span>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3 text-[10px]">
                                    <span class="font-semibold block"><?= htmlspecialchars($row['keluhan_utama']); ?></span>
                                    <?php if (!empty($row['suhu_tubuh']) || !empty($row['tensi_darah'])): ?>
                                        <span class="text-[9px] text-slate-500 block">
                                            S: <?= $row['suhu_tubuh'] ?: '-'; ?>°C | TD: <?= $row['tensi_darah'] ?: '-'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-2 text-center">
                                    <?php if ($is_p1): ?>
                                        <span class="px-1.5 py-0.5 text-[9px] font-black bg-red-100 text-red-900 border border-red-300 rounded">P1 - Darurat</span>
                                    <?php elseif ($is_p2): ?>
                                        <span class="px-1.5 py-0.5 text-[9px] font-black bg-amber-100 text-amber-900 border border-amber-300 rounded">P2 - Mendesak</span>
                                    <?php else: ?>
                                        <span class="px-1.5 py-0.5 text-[9px] font-black bg-emerald-100 text-emerald-900 border border-emerald-300 rounded">P3 - Biasa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-2 text-center font-mono font-bold">
                                    <?= number_format($sk, 3); ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3 text-[10px] font-medium">
                                    <?= htmlspecialchars($row['diagnosa_dokter'] ?: '-'); ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-2.5 text-center text-[10px]">
                                    <span class="font-semibold"><?= htmlspecialchars($row['rekomendasi_rawat'] ?: 'Rawat Jalan'); ?></span>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-2 text-center text-[9.5px] uppercase font-semibold">
                                    <?= htmlspecialchars($row['status_antrean']); ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="9" class="border border-slate-400 py-6 text-center text-slate-500 italic">
                                    Tidak ada data kunjungan klinis yang tercatat pada rentang periode ini.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <!-- TABEL RESMI MUTASI OBAT -->
                    <table class="w-full text-left text-[10.5px] border-collapse border border-slate-700">
                        <thead>
                            <tr class="bg-[#0B3D2E] text-white font-bold text-center">
                                <th class="border border-slate-700 py-2 px-2 w-10">No</th>
                                <th class="border border-slate-700 py-2 px-3 w-32">Waktu Transaksi</th>
                                <th class="border border-slate-700 py-2 px-3 w-28">Kode Obat</th>
                                <th class="border border-slate-700 py-2 px-4">Nama Obat Farmasi</th>
                                <th class="border border-slate-700 py-2 px-3 text-center w-24">Jenis Mutasi</th>
                                <th class="border border-slate-700 py-2 px-3 text-center w-24">Jumlah</th>
                                <th class="border border-slate-700 py-2 px-4">Keterangan / Keperluan</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-800">
                            <?php 
                            $no = 1;
                            if ($res_lap_obat && $res_lap_obat->num_rows > 0):
                                while ($m = $res_lap_obat->fetch_assoc()):
                                    $is_m = ($m['jenis_mutasi'] === 'masuk');
                            ?>
                            <tr class="<?= $no % 2 === 0 ? 'bg-slate-50' : 'bg-white'; ?>">
                                <td class="border border-slate-400 py-1.5 px-2 text-center font-semibold"><?= $no++; ?></td>
                                <td class="border border-slate-400 py-1.5 px-3 text-center font-mono text-[10px]">
                                    <?= date('d/m/Y H:i', strtotime($m['created_at'])); ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3 text-center font-mono font-bold text-[#0B3D2E]">
                                    <?= htmlspecialchars($m['kode_obat']); ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-4 font-bold text-slate-900">
                                    <?= htmlspecialchars($m['nama_obat']); ?>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase <?= $is_m ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-red-100 text-red-900 border border-red-300'; ?>">
                                        <?= $m['jenis_mutasi']; ?>
                                    </span>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-3 text-center font-bold font-mono">
                                    <span class="<?= $is_m ? 'text-[#16845B]' : 'text-red-700'; ?>">
                                        <?= $is_m ? '+' : '-'; ?><?= $m['jumlah']; ?> <?= htmlspecialchars($m['satuan']); ?>
                                    </span>
                                </td>
                                <td class="border border-slate-400 py-1.5 px-4 text-[10px] text-slate-600">
                                    <?= htmlspecialchars($m['keterangan'] ?: '-'); ?>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="7" class="border border-slate-400 py-6 text-center text-slate-500 italic">
                                    Tidak ada catatan mutasi obat farmasi pada rentang periode ini.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ===================== LEMBAR PENGESAHAN & TANDA TANGAN RESMI ===================== -->
            <div class="mt-10 pt-4 flex justify-between items-start text-xs text-slate-900">
                <div class="text-center w-64">
                    <p class="text-slate-600">Mengetahui,</p>
                    <p class="font-bold text-slate-900 mt-0.5">Pimpinan Poskestren Al-Bahjah Bogor</p>
                    <!-- Ruang Cap Stempel & Tanda Tangan -->
                    <div class="h-20 flex items-center justify-center relative">
                        <span class="text-[9px] text-slate-400 border border-dashed border-slate-300 px-3 py-1 rounded">
                            (Cap / Stempel Resmi)
                        </span>
                    </div>
                    <p class="font-bold underline text-slate-950">Ust. H. Ahmad Fauzi, S.Pd.I</p>
                    <p class="text-[9.5px] text-slate-600">NIP: 2018-AB-BGR-004</p>
                </div>

                <div class="text-center w-64">
                    <p class="text-slate-600">Bogor, <?= tgl_indo(date('Y-m-d'), false); ?></p>
                    <p class="font-bold text-slate-900 mt-0.5">Dokter / Penanggung Jawab Medis</p>
                    <!-- Ruang Tanda Tangan -->
                    <div class="h-20 flex items-center justify-center">
                        <span class="text-[9px] text-slate-400 border border-dashed border-slate-300 px-3 py-1 rounded">
                            (Tanda Tangan & Paraf)
                        </span>
                    </div>
                    <p class="font-bold underline text-slate-950"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'dr. Penanggung Jawab Medis'); ?></p>
                    <p class="text-[9.5px] text-slate-600">SIP/STR: 446/1829/STR-DOK/BGR</p>
                </div>
            </div>

            <!-- FOOTER RESMI DOKUMEN -->
            <div class="mt-8 pt-3 border-t border-slate-300 flex items-center justify-between text-[9px] text-slate-500">
                <span>Dokumen Resmi Sistem Pendukung Keputusan (SPK) Triase SAW Poskestren Al-Bahjah Cabang Bogor</span>
                <span>Halaman 1 dari 1 • Status: Disahkan</span>
            </div>

        </div>
    </body>
    </html>
    <?php
    exit();
}

// ==============================================================================
// 4. TAMPILAN DASHBOARD WEB (DENGAN SIDEBAR & HEADER LENGKAP)
// ==============================================================================
require_once 'header.php';
?>

<!-- FILTER PERIODE & AKSI UTAMA -->
<div class="card p-5 mb-6">
    <form method="GET" action="laporan.php" class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Mulai</label>
                <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai); ?>" class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold text-[#182923] focus:outline-none focus:ring-2 focus:ring-[#16845B]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Selesai</label>
                <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai); ?>" class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold text-[#182923] focus:outline-none focus:ring-2 focus:ring-[#16845B]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Jenis Laporan</label>
                <select name="jenis" class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-semibold text-[#182923] focus:outline-none focus:ring-2 focus:ring-[#16845B]">
                    <option value="kunjungan" <?= $jenis_laporan === 'kunjungan' ? 'selected' : ''; ?>>Kunjungan & Triase SAW Santri</option>
                    <option value="obat" <?= $jenis_laporan === 'obat' ? 'selected' : ''; ?>>Mutasi Penggunaan Stok Obat</option>
                </select>
            </div>

            <div>
                <button type="submit" class="px-4 py-2 rounded-xl bg-[#0B3D2E] hover:bg-[#16845B] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Filter Data</span>
                </button>
            </div>
        </div>

        <!-- TOMBOL AKSI CETAK BER-KOP SURAT & EKSPOR -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Tombol Cetak Lembar Resmi (Kop Surat) Membuka Halaman Khusus Cetak -->
            <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>&print=1" 
               target="_blank"
               class="px-4 py-2 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition flex items-center gap-2 shadow-sm"
               title="Buka tampilan cetak resmi lengkap dengan Kop Surat Al-Bahjah Bogor">
                <svg class="w-4 h-4 text-[#F4C542]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>🖨️ Cetak Dokumen (Kop Surat)</span>
            </a>

            <!-- Tombol Unduh Tabel Excel Berformat Kop -->
            <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>&export=excel" 
               class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-white hover:bg-slate-50 text-xs font-bold text-[#0B3D2E] transition flex items-center gap-1.5 shadow-2xs"
               title="Unduh berkas Excel resmi">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Unduh Excel (.xls)</span>
            </a>

            <!-- Tombol Ekspor CSV (Langsung Unduh) -->
            <a href="laporan.php?tgl_mulai=<?= urlencode($tgl_mulai); ?>&tgl_selesai=<?= urlencode($tgl_selesai); ?>&jenis=<?= urlencode($jenis_laporan); ?>&export=csv" 
               download="Laporan_Poskestren.csv"
               class="px-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-white hover:bg-slate-50 text-xs font-bold text-[#64756D] hover:text-[#0B3D2E] transition flex items-center gap-1.5 shadow-2xs"
               title="Ekspor data murni CSV">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh CSV</span>
            </a>
        </div>
    </form>
</div>

<!-- RINGKASAN METRIK PERIODE LAPORAN -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
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

<!-- ============================================================================== -->
<!-- LEMBAR DOKUMEN RESMI BER-KOP SURAT (PRATINJAU LANGSUNG DI LAYAR) -->
<!-- ============================================================================== -->
<div class="card p-6 sm:p-10 mb-8 border border-[#DCE7E0] bg-white shadow-sm rounded-2xl relative">
    
    <!-- KOP SURAT RESMI DI DALAM HALAMAN -->
    <div class="border-b-4 border-double border-[#0B3D2E] pb-4 mb-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- EMBLEM LOGO VEKTOR AL-BAHJAH -->
            <div class="w-16 h-16 shrink-0 flex items-center justify-center">
                <svg class="w-16 h-16 drop-shadow-sm" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="47" fill="#0B3D2E" stroke="#F4C542" stroke-width="3"/>
                    <circle cx="50" cy="50" r="41" fill="none" stroke="#FFFFFF" stroke-width="1.2" stroke-dasharray="3 2"/>
                    <path d="M48 22C33 22 22 35 22 50C22 65 33 78 48 78C39 71 35 60 35 50C35 40 39 29 48 22Z" fill="#F4C542"/>
                    <path d="M57 34H67V44H77V56H67V66H57V56H47V44H57V34Z" fill="#FFFFFF"/>
                    <path d="M59 36H65V46H75V54H65V64H59V54H49V46H59V36Z" fill="#16845B"/>
                    <polygon points="50,25 52,30 57,30 53,33 55,38 50,35 45,38 47,33 43,30 48,30" fill="#F4C542"/>
                </svg>
            </div>

            <!-- TEKS KOP SURAT -->
            <div class="text-center flex-1">
                <h3 class="text-xs font-bold tracking-widest text-[#0B3D2E] uppercase">
                    YAYASAN LEMBAGA PENGEMBANGAN DAKWAH AL-BAHJAH
                </h3>
                <h2 class="text-base sm:text-lg font-black text-[#0B3D2E] tracking-tight uppercase mt-0.5">
                    POS KESEHATAN PESANTREN (POSKESTREN) AL-BAHJAH BOGOR
                </h2>
                <p class="text-xs font-semibold text-[#16845B] mt-0.5">
                    Layanan Triase Kegawatdaruratan Medis Santri SPK SAW & Rawat Inap
                </p>
                <p class="text-[10px] text-slate-500 mt-1">
                    Jl. Raya Pondok Pesantren Al-Bahjah, Kec. Megamendung, Kab. Bogor, Jawa Barat 16770 • Hotline Medis: 0811-2233-4455
                </p>
            </div>

            <!-- BADGE AKREDITASI -->
            <div class="hidden sm:block text-right shrink-0">
                <span class="inline-block text-[9px] font-mono font-bold px-2 py-1 bg-emerald-50 text-[#0B3D2E] border border-emerald-300 rounded-lg">
                    NO: PKT-32.01.24
                </span>
            </div>
        </div>
    </div>

    <!-- JUDUL DOKUMEN & METADATA -->
    <div class="text-center my-4">
        <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base uppercase underline">
            <?= $jenis_laporan === 'kunjungan' 
                ? 'LAPORAN REKAPITULASI PELAYANAN KLINIS & SISTEM TRIASE SANTRI' 
                : 'LAPORAN REKAPITULASI PENGGUNAAN & MUTASI STOK OBAT FARMASI'; ?>
        </h3>
        <p class="text-xs text-slate-600 mt-1">
            Nomor: <span class="font-mono font-semibold"><?= $no_surat_resmi; ?></span> • Periode: <span class="font-semibold"><?= tgl_indo($tgl_mulai, false); ?> s/d <?= tgl_indo($tgl_selesai, false); ?></span>
        </p>
    </div>

    <!-- TABEL DATA LAPORAN BER-BORDER TEGAS -->
    <div class="overflow-x-auto mt-4">
        <?php if ($jenis_laporan === 'kunjungan'): ?>
            <table class="w-full text-left text-xs border-collapse border border-[#DCE7E0]">
                <thead>
                    <tr class="bg-[#0B3D2E] text-white font-bold border-b border-[#0B3D2E]">
                        <th class="py-3 px-3 text-center w-10">No</th>
                        <th class="py-3 px-4">No. Antrean & Tgl</th>
                        <th class="py-3 px-4">Identitas Santri</th>
                        <th class="py-3 px-4">Keluhan & Vital Sign</th>
                        <th class="py-3 px-4 text-center">Prioritas SAW</th>
                        <th class="py-3 px-4 text-center">Skor V</th>
                        <th class="py-3 px-4">Diagnosa Medis</th>
                        <th class="py-3 px-4 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php 
                    $no_web = 1;
                    if ($res_lap_kunjungan && $res_lap_kunjungan->num_rows > 0):
                        // Reset pointer jika sudah terpakai
                        $res_lap_kunjungan->data_seek(0);
                        while ($row = $res_lap_kunjungan->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-3 text-center font-bold text-slate-500"><?= $no_web++; ?></td>
                        <td class="py-3 px-4">
                            <span class="font-mono font-bold text-[#16845B] block"><?= htmlspecialchars($row['no_antrean']); ?></span>
                            <span class="text-[10px] text-[#64756D]"><?= date('d/m/Y H:i', strtotime($row['tanggal_berobat'])); ?></span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="font-bold text-[#0B3D2E] block"><?= htmlspecialchars($row['nama_lengkap']); ?></span>
                            <span class="text-[10px] text-[#64756D]">NIS: <?= $row['nis']; ?> • <?= htmlspecialchars($row['kamar_asrama'] ?? '-'); ?></span>
                        </td>
                        <td class="py-3 px-4 max-w-xs text-xs">
                            <span class="font-medium text-slate-800 block"><?= htmlspecialchars($row['keluhan_utama']); ?></span>
                            <?php if (!empty($row['suhu_tubuh'])): ?>
                                <span class="text-[10px] text-slate-500">Suhu: <?= $row['suhu_tubuh']; ?>°C</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center"><?= badge_triase($row['tingkat_prioritas'], $row['skor_saw_akhir']); ?></td>
                        <td class="py-3 px-4 text-center font-black text-xs text-[#0B3D2E]">
                            <?= number_format((float)$row['skor_saw_akhir'], 3); ?>
                        </td>
                        <td class="py-3 px-4 text-xs font-semibold text-[#182923]">
                            <?= htmlspecialchars($row['diagnosa_dokter'] ?: '-'); ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                <?= htmlspecialchars($row['rekomendasi_rawat'] ?: 'Rawat Jalan'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-[#64756D]">Tidak ada data kunjungan pada rentang tanggal ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- TABEL OBAT -->
            <table class="w-full text-left text-xs border-collapse border border-[#DCE7E0]">
                <thead>
                    <tr class="bg-[#0B3D2E] text-white font-bold border-b border-[#0B3D2E]">
                        <th class="py-3 px-3 text-center w-10">No</th>
                        <th class="py-3 px-4">Waktu Transaksi</th>
                        <th class="py-3 px-4">Kode & Nama Obat</th>
                        <th class="py-3 px-4 text-center">Jenis Mutasi</th>
                        <th class="py-3 px-4 text-center">Jumlah</th>
                        <th class="py-3 px-4">Keterangan Transaksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php 
                    $no_web_o = 1;
                    if ($res_lap_obat && $res_lap_obat->num_rows > 0):
                        $res_lap_obat->data_seek(0);
                        while ($mut = $res_lap_obat->fetch_assoc()):
                            $is_m = ($mut['jenis_mutasi'] === 'masuk');
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-3 text-center font-bold text-slate-500"><?= $no_web_o++; ?></td>
                        <td class="py-3 px-4 text-[#64756D] font-mono"><?= tgl_indo($mut['created_at']); ?></td>
                        <td class="py-3 px-4">
                            <span class="font-mono text-[10px] text-slate-500 block"><?= htmlspecialchars($mut['kode_obat']); ?></span>
                            <span class="font-bold text-[#0B3D2E]"><?= htmlspecialchars($mut['nama_obat']); ?></span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $is_m ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                                <?= $mut['jenis_mutasi']; ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center font-bold <?= $is_m ? 'text-[#16845B]' : 'text-red-600'; ?>">
                            <?= $is_m ? '+' : '-'; ?> <?= $mut['jumlah']; ?> <?= htmlspecialchars($mut['satuan']); ?>
                        </td>
                        <td class="py-3 px-4 text-[#64756D]"><?= htmlspecialchars($mut['keterangan'] ?: '-'); ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-[#64756D]">Tidak ada catatan mutasi obat pada rentang tanggal ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- TANDA TANGAN DI DALAM PRATINJAU DOKUMEN -->
    <div class="mt-8 pt-6 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center sm:items-start text-xs text-slate-800 gap-6">
        <div class="text-center w-64">
            <p class="text-slate-500">Mengetahui,</p>
            <p class="font-bold text-[#0B3D2E]">Pimpinan Poskestren Al-Bahjah</p>
            <div class="h-16 flex items-center justify-center">
                <span class="text-[9px] text-slate-400 border border-dashed border-slate-200 px-3 py-1 rounded">
                    (Stempel Poskestren)
                </span>
            </div>
            <p class="font-bold underline text-slate-900">Ust. H. Ahmad Fauzi, S.Pd.I</p>
            <p class="text-[9.5px] text-slate-500">NIP: 2018-AB-BGR-004</p>
        </div>

        <div class="text-center w-64">
            <p class="text-slate-500">Bogor, <?= tgl_indo(date('Y-m-d'), false); ?></p>
            <p class="font-bold text-[#0B3D2E]">Dokter / Penanggung Jawab Medis</p>
            <div class="h-16 flex items-center justify-center">
                <span class="text-[9px] text-slate-400 border border-dashed border-slate-200 px-3 py-1 rounded">
                    (Paraf Medis)
                </span>
            </div>
            <p class="font-bold underline text-slate-900"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'dr. Penanggung Jawab Medis'); ?></p>
            <p class="text-[9.5px] text-slate-500">SIP/STR: 446/1829/STR-DOK/BGR</p>
        </div>
    </div>
</div>

<!-- SECTION STATISTIK KESEHATAN TERTINGGI (TOP 5) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
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
            <p class="text-[11px] text-[#64756D]">Belum ada data diagnosis tercatat pada periode ini.</p>
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
            <p class="text-[11px] text-[#64756D]">Belum ada resep obat selesai pada periode ini.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
