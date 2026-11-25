<?php
// rekam_medis_admin.php - Arsip & Manajemen Rekam Medis Santri Khusus Admin
$page_title = "Rekam Medis Pasien (Admin)";
$page_desc  = "Arsip rekam medis elektronik (EMR) dan riwayat santri yang telah diperiksa oleh dokter.";
require_once 'header.php';

// Otorisasi Ketat: Khusus Administrator
if ($user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Modul Rekam Medis Admin khusus untuk Administrator.");
    header("Location: " . get_redirect_url($user_role));
    exit();
}

// ════════════════════════════════════════════════════════════════════
// PARAMETER FILTER & PENCARIAN
// ════════════════════════════════════════════════════════════════════
$search     = trim($_GET['q'] ?? '');
$filter_raw = trim($_GET['rekomendasi'] ?? '');
$filter_tgl = trim($_GET['tanggal'] ?? '');
$filter_asr = trim($_GET['asrama'] ?? '');
$detail_id  = (int)($_GET['detail_id'] ?? 0);

// ════════════════════════════════════════════════════════════════════
// DATA DETAIL REKAM MEDIS JIKA DIPILIH
// ════════════════════════════════════════════════════════════════════
$detail_pasien = null;
$detail_resep  = [];
$detail_ri     = null;

if ($detail_id > 0) {
    $stmt_d = $conn->prepare(
        "SELECT pk.*, pk.id as pemeriksaan_id,
                k.id as kunjungan_id, k.no_antrean, k.tanggal_berobat, k.keluhan_utama,
                k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas, k.catatan_dokter,
                k.dokter_pemeriksa, k.tanggal_selesai,
                s.id as santri_id, s.nama_lengkap, s.nis, s.jenis_kelamin, s.kamar_asrama,
                s.kompleks_asrama, s.tanggal_lahir, s.nama_wali, s.no_hp_wali,
                u.nama_lengkap as nama_nakes_akun, u.username as username_nakes
         FROM pemeriksaan_klinis pk
         JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id
         JOIN santri s ON k.santri_id = s.id
         LEFT JOIN users u ON pk.petugas_id = u.id
         WHERE pk.id = ? OR k.id = ?
         LIMIT 1"
    );
    $stmt_d->bind_param("ii", $detail_id, $detail_id);
    $stmt_d->execute();
    $detail_pasien = $stmt_d->get_result()->fetch_assoc();

    if ($detail_pasien) {
        $kid = (int)$detail_pasien['kunjungan_id'];
        
        // Ambil resep obat jika ada
        $stmt_ro = $conn->prepare(
            "SELECT rod.*, o.nama_obat, o.satuan, ro.id as resep_id, ro.status_resep, ro.catatan_resep
             FROM resep_obat ro
             JOIN resep_obat_detail rod ON rod.resep_id = ro.id
             JOIN obat o ON rod.obat_id = o.id
             WHERE ro.kunjungan_id = ?"
        );
        $stmt_ro->bind_param("i", $kid);
        $stmt_ro->execute();
        $detail_resep = $stmt_ro->get_result()->fetch_all(MYSQLI_ASSOC);

        // Ambil data rawat inap jika ada
        $stmt_ri = $conn->prepare("SELECT * FROM rawat_inap WHERE kunjungan_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_ri->bind_param("i", $kid);
        $stmt_ri->execute();
        $detail_ri = $stmt_ri->get_result()->fetch_assoc();
    }
}

// ════════════════════════════════════════════════════════════════════
// STATISTIK RINGKASAN REKAM MEDIS
// ════════════════════════════════════════════════════════════════════
$tot_diperiksa = (int)($conn->query("SELECT COUNT(*) c FROM pemeriksaan_klinis")->fetch_assoc()['c'] ?? 0);
$tot_jalan     = (int)($conn->query("SELECT COUNT(*) c FROM pemeriksaan_klinis WHERE rekomendasi_rawat = 'Rawat Jalan'")->fetch_assoc()['c'] ?? 0);
$tot_inap      = (int)($conn->query("SELECT COUNT(*) c FROM pemeriksaan_klinis WHERE rekomendasi_rawat = 'Rawat Inap Poskestren'")->fetch_assoc()['c'] ?? 0);
$tot_rujuk     = (int)($conn->query("SELECT COUNT(*) c FROM pemeriksaan_klinis WHERE rekomendasi_rawat = 'Rujuk RS'")->fetch_assoc()['c'] ?? 0);

// ════════════════════════════════════════════════════════════════════
// QUERY UTAMA DAFTAR PASIEN YANG SUDAH DIPERIKSA DOKTER
// ════════════════════════════════════════════════════════════════════
$where_clauses = ["1=1"];
$params = [];
$types  = "";

if (!empty($search)) {
    $where_clauses[] = "(s.nama_lengkap LIKE ? OR s.nis LIKE ? OR pk.diagnosa_dokter LIKE ? OR k.keluhan_utama LIKE ? OR k.no_antrean LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $types .= "sssss";
}

if (!empty($filter_raw)) {
    $where_clauses[] = "pk.rekomendasi_rawat = ?";
    $params[] = $filter_raw;
    $types .= "s";
}

if (!empty($filter_tgl)) {
    $where_clauses[] = "DATE(k.tanggal_berobat) = ?";
    $params[] = $filter_tgl;
    $types .= "s";
}

if (!empty($filter_asr)) {
    $where_clauses[] = "s.kompleks_asrama = ?";
    $params[] = $filter_asr;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

$sql_list = "SELECT pk.id as pemeriksaan_id, pk.tekanan_darah, pk.suhu_tubuh, pk.nadi, pk.laju_respirasi,
                    pk.saturasi_oksigen, pk.tingkat_kesadaran, pk.diagnosa_dokter, pk.tindakan_pertama,
                    pk.rekomendasi_rawat,
                    k.id as kunjungan_id, k.no_antrean, k.tanggal_berobat, k.keluhan_utama,
                    k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas, k.dokter_pemeriksa,
                    s.id as santri_id, s.nama_lengkap, s.nis, s.jenis_kelamin, s.kamar_asrama,
                    s.kompleks_asrama, s.nama_wali, s.no_hp_wali,
                    u.nama_lengkap as nama_dokter_akun,
                    (SELECT COUNT(*) FROM resep_obat_detail rod JOIN resep_obat ro ON rod.resep_id = ro.id WHERE ro.kunjungan_id = k.id) as jumlah_obat,
                    (SELECT ro.status_resep FROM resep_obat ro WHERE ro.kunjungan_id = k.id LIMIT 1) as status_resep
             FROM pemeriksaan_klinis pk
             JOIN kunjungan_sakit k ON pk.kunjungan_id = k.id
             JOIN santri s ON k.santri_id = s.id
             LEFT JOIN users u ON pk.petugas_id = u.id
             WHERE $where_sql
             ORDER BY k.id DESC";

$stmt_list = $conn->prepare($sql_list);
if (!empty($params)) {
    $stmt_list->bind_param($types, ...$params);
}
$stmt_list->execute();
$res_pasien = $stmt_list->get_result();
?>

<!-- ============================================================== -->
<!-- HEADER HALAMAN & ACTIONS                                      -->
<!-- ============================================================== -->
<div class="flex flex-col md:flex-row md:items-center justify-between pb-4 border-b border-[#DCE7E0] gap-3 no-print">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-[#0B3D2E]">Rekam Medis Pasien (Admin)</h1>
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-900 border border-purple-200">
                Arsip Pemeriksaan Dokter
            </span>
        </div>
        <p class="text-xs text-[#64756D] mt-0.5">
            Daftar seluruh santri yang telah menjalani skrining triase SAW dan pemeriksaan klinis (SOAP) oleh dokter.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="dashboard.php" class="px-3 py-1.5 rounded-lg border border-[#DCE7E0] bg-white text-xs font-semibold text-[#0B3D2E] hover:bg-slate-50 transition shadow-2xs inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Dashboard Admin</span>
        </a>
        <a href="dokter.php" class="px-3 py-1.5 rounded-lg border border-[#DCE7E0] bg-white text-xs font-semibold text-[#0B3D2E] hover:bg-slate-50 transition shadow-2xs inline-flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            <span>Monitoring Rawat Inap</span>
        </a>
    </div>
</div>

<!-- ============================================================== -->
<!-- 4 KARTU STATISTIK RINGKAS                                       -->
<!-- ============================================================== -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 no-print">
    <!-- Card 1: Total Pasien -->
    <div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl flex items-center gap-3.5 shadow-2xs">
        <div class="w-11 h-11 rounded-lg bg-emerald-50 text-[#0B3D2E] flex items-center justify-center flex-shrink-0 font-bold border border-emerald-100">
            <svg class="w-5 h-5 text-[#16845B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div>
            <span class="text-[11px] font-bold text-[#64756D] uppercase tracking-wider block">Total Selesai Periksa</span>
            <span class="text-xl font-extrabold text-[#0B3D2E] mono-num"><?= number_format($tot_diperiksa); ?></span>
            <span class="text-[11px] text-[#64756D] block">Rekam Medis Terbit</span>
        </div>
    </div>

    <!-- Card 2: Rawat Jalan -->
    <div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl flex items-center gap-3.5 shadow-2xs">
        <div class="w-11 h-11 rounded-lg bg-blue-50 text-blue-800 flex items-center justify-center flex-shrink-0 font-bold border border-blue-100">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        </div>
        <div>
            <span class="text-[11px] font-bold text-[#64756D] uppercase tracking-wider block">Rawat Jalan / Asrama</span>
            <span class="text-xl font-extrabold text-blue-800 mono-num"><?= number_format($tot_jalan); ?></span>
            <span class="text-[11px] text-blue-600 block">Istirahat di Kamar</span>
        </div>
    </div>

    <!-- Card 3: Rawat Inap -->
    <div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl flex items-center gap-3.5 shadow-2xs">
        <div class="w-11 h-11 rounded-lg bg-amber-50 text-amber-800 flex items-center justify-center flex-shrink-0 font-bold border border-amber-100">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <div>
            <span class="text-[11px] font-bold text-[#64756D] uppercase tracking-wider block">Rawat Inap Poskestren</span>
            <span class="text-xl font-extrabold text-amber-800 mono-num"><?= number_format($tot_inap); ?></span>
            <span class="text-[11px] text-amber-600 block">Observasi Bed Medis</span>
        </div>
    </div>

    <!-- Card 4: Rujukan RS -->
    <div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl flex items-center gap-3.5 shadow-2xs">
        <div class="w-11 h-11 rounded-lg bg-red-50 text-red-800 flex items-center justify-center flex-shrink-0 font-bold border border-red-100">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div>
            <span class="text-[11px] font-bold text-[#64756D] uppercase tracking-wider block">Rujukan RS Luar</span>
            <span class="text-xl font-extrabold text-red-800 mono-num"><?= number_format($tot_rujuk); ?></span>
            <span class="text-[11px] text-red-600 block">Faskes Tingkat Lanjut</span>
        </div>
    </div>
</div>

<?php if ($detail_pasien): ?>
<!-- ============================================================== -->
<!-- DETAIL MODAL / LEMBAR REKAM MEDIS TERPILIH                     -->
<!-- ============================================================== -->
<div class="card-panel p-5 bg-white border-2 border-emerald-300 rounded-xl space-y-4 shadow-sm no-print">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-base flex-shrink-0">
                <?= strtoupper(substr($detail_pasien['nama_lengkap'], 0, 1)); ?>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-bold text-base text-[#0B3D2E]"><?= htmlspecialchars($detail_pasien['nama_lengkap']); ?></h2>
                    <span class="mono-num text-xs font-bold text-[#0B3D2E] bg-slate-100 px-2 py-0.5 rounded border border-[#DCE7E0]">
                        <?= htmlspecialchars($detail_pasien['no_antrean']); ?>
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                        Selesai Diperiksa Dokter
                    </span>
                </div>
                <p class="text-xs text-[#64756D] mt-0.5">
                    NIS: <strong class="text-[#182923] mono-num"><?= $detail_pasien['nis']; ?></strong> • 
                    <?= $detail_pasien['jenis_kelamin'] == 'L' ? 'Santri Putra' : 'Santri Putri'; ?> • 
                    Asrama: <strong><?= $detail_pasien['kamar_asrama']; ?> (<?= $detail_pasien['kompleks_asrama']; ?>)</strong> • 
                    Wali: <?= htmlspecialchars($detail_pasien['nama_wali'] ?: '-'); ?> (<?= htmlspecialchars($detail_pasien['no_hp_wali'] ?: '-'); ?>)
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="dashboard.php" class="px-3 py-1.5 rounded-lg border border-[#DCE7E0] bg-white text-[#0B3D2E] hover:bg-slate-50 text-xs font-semibold transition inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Dashboard Admin</span>
            </a>
            <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Lembar EMR</span>
            </button>
            <a href="rekam_medis_admin.php" class="px-3 py-1.5 rounded-lg border border-[#DCE7E0] text-[#64756D] hover:bg-slate-100 text-xs font-semibold transition">
                ✕ Tutup Detail
            </a>
        </div>
    </div>

    <!-- Parameter SOAP Lengkap -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <!-- Subjektif & Objektif -->
        <div class="space-y-3">
            <!-- Tanda-Tanda Vital (Objektif) -->
            <div class="p-3 rounded-lg bg-[#F5F8F6] border border-[#DCE7E0] space-y-2">
                <span class="text-[11px] font-bold text-[#0B3D2E] uppercase block">Tanda-Tanda Vital (Objektif):</span>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2 text-center">
                    <div class="bg-white p-2 rounded border border-[#DCE7E0]">
                        <span class="text-[10px] uppercase text-[#64756D] block">Tekanan Darah</span>
                        <strong class="text-xs text-[#182923] mono-num"><?= htmlspecialchars($detail_pasien['tekanan_darah'] ?: '-'); ?></strong>
                    </div>
                    <div class="bg-white p-2 rounded border border-[#DCE7E0]">
                        <span class="text-[10px] uppercase text-[#64756D] block">Suhu Tubuh</span>
                        <strong class="text-xs text-red-700 mono-num"><?= $detail_pasien['suhu_tubuh']; ?> °C</strong>
                    </div>
                    <div class="bg-white p-2 rounded border border-[#DCE7E0]">
                        <span class="text-[10px] uppercase text-[#64756D] block">Denyut Nadi</span>
                        <strong class="text-xs text-[#182923] mono-num"><?= (int)$detail_pasien['nadi']; ?> x/m</strong>
                    </div>
                    <div class="bg-white p-2 rounded border border-[#DCE7E0]">
                        <span class="text-[10px] uppercase text-[#64756D] block">Laju Napas</span>
                        <strong class="text-xs text-[#182923] mono-num"><?= (int)$detail_pasien['laju_respirasi']; ?> x/m</strong>
                    </div>
                    <div class="bg-white p-2 rounded border border-[#DCE7E0]">
                        <span class="text-[10px] uppercase text-[#64756D] block">Saturasi SpO2</span>
                        <strong class="text-xs text-emerald-700 mono-num"><?= (int)$detail_pasien['saturasi_oksigen']; ?>%</strong>
                    </div>
                </div>
            </div>

            <!-- Subjektif (Keluhan Awal & Triase) -->
            <div class="p-3 rounded-lg bg-slate-50 border border-[#DCE7E0] space-y-1">
                <span class="font-bold text-[#0B3D2E] block">Anamnesis &amp; Keluhan Santri (S):</span>
                <p class="text-[#182923]"><?= nl2br(htmlspecialchars($detail_pasien['keluhan_utama'])); ?></p>
                <div class="pt-1.5 flex items-center gap-2">
                    <span class="text-[10px] font-bold text-[#64756D]">Prioritas Triase SAW:</span>
                    <?= badge_triase($detail_pasien['tingkat_prioritas'], $detail_pasien['skor_saw_akhir']); ?>
                </div>
            </div>
        </div>

        <!-- Asesmen & Planning -->
        <div class="space-y-3">
            <!-- Diagnosis Medis Dokter -->
            <div class="p-3 rounded-lg bg-emerald-50/60 border border-emerald-200 space-y-1">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-[#0B3D2E] block">Diagnosis Klinis Dokter (A):</span>
                    <span class="text-[10px] text-emerald-800 font-semibold">
                        Oleh: <?= htmlspecialchars($detail_pasien['dokter_pemeriksa'] ?: ($detail_pasien['nama_nakes_akun'] ?: 'Dokter Pemeriksa')); ?>
                    </span>
                </div>
                <p class="font-bold text-sm text-[#0B3D2E]"><?= nl2br(htmlspecialchars($detail_pasien['diagnosa_dokter'])); ?></p>
            </div>

            <!-- Tindakan & Disposisi -->
            <div class="p-3 rounded-lg bg-slate-50 border border-[#DCE7E0] space-y-1">
                <span class="font-bold text-[#0B3D2E] block">Tindakan Medis &amp; Disposisi (P):</span>
                <p class="text-[#182923]"><?= nl2br(htmlspecialchars($detail_pasien['tindakan_pertama'] ?: 'Terapi medikamentosa dan anjuran istirahat santri.')); ?></p>
                <div class="pt-1.5 flex items-center gap-2">
                    <span class="text-[10px] font-bold text-[#64756D]">Keputusan Rawat:</span>
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold text-white bg-[#0B3D2E]">
                        <?= htmlspecialchars($detail_pasien['rekomendasi_rawat']); ?>
                    </span>
                    <?php if ($detail_ri): ?>
                    <span class="text-[10px] text-[#64756D]">
                        (Bed: <strong><?= htmlspecialchars($detail_ri['nomor_bed']); ?></strong> - Status: <strong><?= htmlspecialchars($detail_ri['status_rawat']); ?></strong>)
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rincian Resep Obat yang Diberikan -->
    <?php if (!empty($detail_resep)): ?>
    <div class="pt-2 border-t border-[#DCE7E0]">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-bold text-[#0B3D2E]">Resep Obat Digital (R/) yang Diberikan Dokter:</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                Status Farmasi: <?= strtoupper($detail_resep[0]['status_resep'] ?? 'menunggu'); ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border border-[#DCE7E0] rounded-lg">
                <thead>
                    <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                        <th class="py-2 px-3">Nama Obat</th>
                        <th class="py-2 px-3 text-center">Jumlah</th>
                        <th class="py-2 px-3">Aturan Pakai / Dosis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0]">
                    <?php foreach ($detail_resep as $dr): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-semibold text-[#182923]"><?= htmlspecialchars($dr['nama_obat']); ?></td>
                        <td class="py-2 px-3 text-center mono-num font-bold text-[#0B3D2E]"><?= (int)$dr['jumlah']; ?> <?= htmlspecialchars($dr['satuan']); ?></td>
                        <td class="py-2 px-3 text-[#64756D]"><?= htmlspecialchars($dr['dosis_instruksi'] ?: '3 x 1 sesudah makan'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============================================================== -->
<!-- FILTER & PENCARIAN REKAM MEDIS                                 -->
<!-- ============================================================== -->
<div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl no-print">
    <form method="GET" action="rekam_medis_admin.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end text-xs">
        <!-- Input Search -->
        <div class="lg:col-span-2">
            <label class="block font-bold text-[#0B3D2E] mb-1">Cari Rekam Medis:</label>
            <div class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Nama santri, NIS, keluhan, atau diagnosis..." 
                       class="w-full pl-8 pr-3 py-2 rounded-lg border border-[#DCE7E0] text-xs focus:ring-1 focus:ring-[#16845B] focus:border-[#16845B] outline-none">
                <svg class="w-4 h-4 text-[#64756D] absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <!-- Filter Rekomendasi Rawat -->
        <div>
            <label class="block font-bold text-[#0B3D2E] mb-1">Keputusan Tindakan:</label>
            <select name="rekomendasi" class="w-full px-3 py-2 rounded-lg border border-[#DCE7E0] text-xs focus:ring-1 focus:ring-[#16845B] focus:border-[#16845B] outline-none bg-white">
                <option value="">Semua Disposisi</option>
                <option value="Rawat Jalan" <?= $filter_raw === 'Rawat Jalan' ? 'selected' : ''; ?>>Rawat Jalan</option>
                <option value="Rawat Inap Poskestren" <?= $filter_raw === 'Rawat Inap Poskestren' ? 'selected' : ''; ?>>Rawat Inap Poskestren</option>
                <option value="Rujuk RS" <?= $filter_raw === 'Rujuk RS' ? 'selected' : ''; ?>>Rujuk RS</option>
            </select>
        </div>

        <!-- Filter Tanggal -->
        <div>
            <label class="block font-bold text-[#0B3D2E] mb-1">Tanggal Kunjungan:</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($filter_tgl); ?>" 
                   class="w-full px-3 py-2 rounded-lg border border-[#DCE7E0] text-xs focus:ring-1 focus:ring-[#16845B] focus:border-[#16845B] outline-none bg-white">
        </div>

        <!-- Tombol Aksi Filter -->
        <div class="flex items-center gap-2">
            <button type="submit" class="flex-1 py-2 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-bold text-xs transition flex items-center justify-center gap-1 shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span>Filter</span>
            </button>
            <?php if (!empty($search) || !empty($filter_raw) || !empty($filter_tgl) || !empty($filter_asr)): ?>
            <a href="rekam_medis_admin.php" class="px-3 py-2 rounded-lg border border-[#DCE7E0] text-[#64756D] hover:bg-slate-100 font-semibold transition" title="Reset Filter">
                Reset
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================================================== -->
<!-- TABEL UTAMA PASIEN SUDAH DIPERIKSA DOKTER                     -->
<!-- ============================================================== -->
<div class="card-panel p-4 bg-white border border-[#DCE7E0] rounded-xl space-y-3.5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[#DCE7E0] gap-2">
        <div>
            <h2 class="font-bold text-sm text-[#0B3D2E]">Arsip Pasien yang Telah Diperiksa Dokter</h2>
            <p class="text-xs text-[#64756D]">
                Menampilkan seluruh kunjungan dengan hasil pemeriksaan klinis lengkap dan tindakan dokter.
            </p>
        </div>
        <div class="text-xs text-[#64756D]">
            Ditemukan: <strong class="text-[#0B3D2E] mono-num"><?= $res_pasien ? $res_pasien->num_rows : 0; ?></strong> data rekam medis
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                    <th class="py-2.5 px-3">No. Antrean &amp; Tgl</th>
                    <th class="py-2.5 px-3">Identitas Santri</th>
                    <th class="py-2.5 px-3">Dokter Pemeriksa</th>
                    <th class="py-2.5 px-3">Diagnosis Medis &amp; Keluhan</th>
                    <th class="py-2.5 px-3 text-center">Tanda Vital (TTV)</th>
                    <th class="py-2.5 px-3 text-center">Disposisi Rawat</th>
                    <th class="py-2.5 px-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                <?php if ($res_pasien && $res_pasien->num_rows > 0): ?>
                    <?php while ($p = $res_pasien->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/70 transition <?= ($detail_id == $p['pemeriksaan_id'] || $detail_id == $p['kunjungan_id']) ? 'bg-emerald-50/40' : ''; ?>">
                        <!-- No Antrean & Tanggal -->
                        <td class="py-2.5 px-3 whitespace-nowrap">
                            <span class="font-bold text-[#0B3D2E] mono-num block"><?= htmlspecialchars($p['no_antrean']); ?></span>
                            <span class="text-[10px] text-[#64756D] block"><?= tgl_indo($p['tanggal_berobat'], false); ?></span>
                            <div class="mt-1"><?= badge_triase($p['tingkat_prioritas'], $p['skor_saw_akhir']); ?></div>
                        </td>

                        <!-- Identitas Santri -->
                        <td class="py-2.5 px-3">
                            <strong class="text-[#0B3D2E] text-sm block"><?= htmlspecialchars($p['nama_lengkap']); ?></strong>
                            <span class="mono-num text-[11px] text-[#64756D]">NIS: <?= $p['nis']; ?> • <?= $p['jenis_kelamin'] == 'L' ? 'Putra' : 'Putri'; ?></span>
                            <span class="text-[10px] text-emerald-800 font-medium block">
                                <?= htmlspecialchars($p['kamar_asrama'] ?? '-'); ?> (<?= htmlspecialchars($p['kompleks_asrama']); ?>)
                            </span>
                        </td>

                        <!-- Dokter Pemeriksa -->
                        <td class="py-2.5 px-3">
                            <span class="font-bold text-[#0B3D2E] block">
                                <?= htmlspecialchars($p['dokter_pemeriksa'] ?: ($p['nama_dokter_akun'] ?: 'Dokter Poskestren')); ?>
                            </span>
                            <span class="text-[10px] text-[#64756D]">SIP / STR Terdaftar</span>
                        </td>

                        <!-- Diagnosis Medis & Keluhan -->
                        <td class="py-2.5 px-3 max-w-[260px]">
                            <strong class="text-[#0B3D2E] block leading-snug"><?= htmlspecialchars($p['diagnosa_dokter']); ?></strong>
                            <p class="text-[11px] text-[#64756D] truncate mt-0.5" title="<?= htmlspecialchars($p['keluhan_utama']); ?>">
                                Keluhan: <?= htmlspecialchars($p['keluhan_utama']); ?>
                            </p>
                            <?php if ($p['jumlah_obat'] > 0): ?>
                            <span class="inline-flex items-center gap-1 mt-1 text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200 font-semibold">
                                💊 <?= $p['jumlah_obat']; ?> Macam Obat Diresepkan
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Tanda Vital Objektif -->
                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                            <span class="mono-num text-red-700 font-bold block"><?= $p['suhu_tubuh']; ?> °C</span>
                            <span class="mono-num text-[10px] text-[#64756D] block">TD: <?= htmlspecialchars($p['tekanan_darah'] ?: '-'); ?></span>
                            <span class="mono-num text-[10px] text-emerald-700 block">SpO2: <?= (int)$p['saturasi_oksigen']; ?>%</span>
                        </td>

                        <!-- Disposisi Rawat -->
                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                            <?php if ($p['rekomendasi_rawat'] === 'Rawat Inap Poskestren'): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                    Rawat Inap
                                </span>
                            <?php elseif ($p['rekomendasi_rawat'] === 'Rujuk RS'): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-red-100 text-red-900 border border-red-300">
                                    Rujukan RS
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">
                                    Rawat Jalan
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Aksi -->
                        <td class="py-2.5 px-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="rekam_medis_admin.php?detail_id=<?= $p['pemeriksaan_id']; ?>" 
                                   class="px-2.5 py-1 rounded-lg bg-[#0B3D2E] hover:bg-[#16845B] text-white font-semibold text-xs transition inline-flex items-center gap-1 shadow-2xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Lihat EMR</span>
                                </a>
                                <a href="rekam_medis_admin.php?detail_id=<?= $p['pemeriksaan_id']; ?>" 
                                   class="px-2.5 py-1 rounded-lg border border-[#DCE7E0] hover:bg-slate-100 text-[#0B3D2E] font-semibold text-xs transition inline-flex items-center gap-1" title="Buka Detail Rekam Medis">
                                    <span>Detail Medis</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-[#64756D]">
                            <div class="max-w-md mx-auto space-y-1">
                                <svg class="w-10 h-10 text-[#64756D]/40 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="font-bold text-sm text-[#0B3D2E]">Belum Ada Rekam Medis Ditemukan</p>
                                <p class="text-xs text-[#64756D]">Tidak ada rekam medis pasien yang sesuai dengan kata kunci atau filter pencarian saat ini.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
require_once 'footer.php'; 
?>
