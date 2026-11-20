<?php
// dashboard.php - Dashboard Operasional Terpadu Poskestren Al-Bahjah
$page_title = "Dashboard Admin";
$page_desc  = "Ringkasan antrean, kontrol operasional, dan statistik klinis hari ini.";
require_once 'header.php';

// Hanya role admin yang boleh mengakses dashboard utama ini
if ($user_role === 'santri_wali') { 
    header("Location: wali.php"); exit(); 
} elseif ($user_role === 'petugas_farmasi') {
    header("Location: dashboard_farmasi.php"); exit();
} elseif ($user_role === 'petugas_kesehatan') {
    header("Location: dashboard_dokter.php"); exit();
}

$hari_ini = date('Y-m-d');

$tot_kunjungan    = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) = '$hari_ini'")->fetch_assoc()['c'];
$p1_count         = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas = 'P1_Gawat_Darurat' OR skor_saw_akhir >= 0.80)")->fetch_assoc()['c'];
$p2_count         = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas = 'P2_Mendesak' OR (skor_saw_akhir >= 0.50 AND skor_saw_akhir < 0.80))")->fetch_assoc()['c'];
$p3_count         = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas = 'P3_Biasa' AND skor_saw_akhir < 0.50)")->fetch_assoc()['c'];
$rawat_inap_count = $conn->query("SELECT COUNT(*) as c FROM rawat_inap WHERE status_rawat = 'Dirawat'")->fetch_assoc()['c'];
$stok_kritis      = $conn->query("SELECT COUNT(*) as c FROM obat WHERE stok_sekarang <= stok_minimal")->fetch_assoc()['c'];
$sembuh_hari_ini  = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean = 'selesai' AND (DATE(tanggal_selesai) = '$hari_ini' OR DATE(tanggal_berobat) = '$hari_ini')")->fetch_assoc()['c'];
$sembuh_total     = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean = 'selesai'")->fetch_assoc()['c'];

$res_antrean = $conn->query(
    "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama
     FROM kunjungan_sakit k JOIN santri s ON k.santri_id = s.id
     WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan')
     ORDER BY CASE WHEN k.tingkat_prioritas='P1_Gawat_Darurat' THEN 1 ELSE 2 END, k.skor_saw_akhir DESC, k.id ASC"
);

$res_sembuh = $conn->query(
    "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, pk.diagnosa_dokter
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     WHERE k.status_antrean = 'selesai'
     ORDER BY COALESCE(k.tanggal_selesai, k.tanggal_berobat) DESC LIMIT 10"
);

$res_obat = $conn->query(
    "SELECT * FROM obat WHERE stok_sekarang <= stok_minimal OR exp_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     ORDER BY stok_sekarang ASC LIMIT 5"
);
?>

<!-- QUICK ACTION CARDS ADMIN -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <a href="santri.php" class="card p-3.5 hover:bg-emerald-50/40 border-[#d8e6de] transition flex items-center gap-3">
        <div class="w-9 h-9 rounded bg-emerald-100 text-[#16845B] flex items-center justify-center font-bold text-sm flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold text-[#0B3D2E]">Master Santri</div>
            <div class="text-[11px] text-gray-500">Kelola identitas &amp; asrama</div>
        </div>
    </a>

    <a href="triase.php" class="card p-3.5 hover:bg-emerald-50/40 border-[#d8e6de] transition flex items-center gap-3">
        <div class="w-9 h-9 rounded bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold text-[#0B3D2E]">Input Skrining Triase</div>
            <div class="text-[11px] text-gray-500">Kalkulasi SAW &amp; TTV</div>
        </div>
    </a>

    <a href="farmasi.php" class="card p-3.5 hover:bg-emerald-50/40 border-[#d8e6de] transition flex items-center gap-3">
        <div class="w-9 h-9 rounded bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold text-[#0B3D2E]">Kelola Obat (Admin)</div>
            <div class="text-[11px] text-gray-500">Restock &amp; audit stok obat</div>
        </div>
    </a>

    <a href="laporan.php" class="card p-3.5 hover:bg-emerald-50/40 border-[#d8e6de] transition flex items-center gap-3">
        <div class="w-9 h-9 rounded bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold text-[#0B3D2E]">Laporan &amp; Ekspor</div>
            <div class="text-[11px] text-gray-500">Rekap kunjungan &amp; triase</div>
        </div>
    </a>
</div>

<!-- STAT CARDS -->
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    <?php
    $stats = [
        ['label' => 'Kunjungan Hari Ini', 'value' => $tot_kunjungan,    'sub' => 'santri',           'color' => '#0B3D2E', 'border' => ''],
        ['label' => 'Santri Sembuh',       'value' => $sembuh_hari_ini,  'sub' => 'pulih hari ini',   'color' => '#16845B', 'border' => 'border-emerald-300 bg-emerald-50/30'],
        ['label' => 'P1 — Darurat',        'value' => $p1_count,         'sub' => 'menunggu',          'color' => '#D64545', 'border' => 'border-red-200'],
        ['label' => 'P2 — Mendesak',       'value' => $p2_count,         'sub' => 'dalam pantau',      'color' => '#b45309', 'border' => 'border-amber-200'],
        ['label' => 'P3 — Biasa',          'value' => $p3_count,         'sub' => 'kondisi stabil',    'color' => '#16845B', 'border' => ''],
        ['label' => 'Rawat Inap',          'value' => $rawat_inap_count, 'sub' => 'bed terisi',        'color' => '#0B3D2E', 'border' => ''],
        ['label' => 'Stok Kritis',         'value' => $stok_kritis,      'sub' => 'item obat',         'color' => $stok_kritis > 0 ? '#D64545' : '#0B3D2E', 'border' => $stok_kritis > 0 ? 'border-red-200' : ''],
    ];
    foreach ($stats as $s):
    ?>
    <div class="card p-3.5 <?= $s['border']; ?>">
        <div class="text-[11px] text-gray-500 mb-1"><?= $s['label']; ?></div>
        <div class="text-2xl font-bold" style="color:<?= $s['color']; ?>"><?= $s['value']; ?></div>
        <div class="text-[10px] text-gray-400 mt-0.5"><?= $s['sub']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- MAIN GRID -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- ANTREAN & DATA PASIEN SEMBUH (2/3) -->
    <div class="lg:col-span-2 space-y-5">
        <div class="card">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-[#0B3D2E]">Antrean Triase Aktif</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Urutan prioritas berdasarkan skor SAW</p>
                </div>
                <a href="triase.php" class="text-xs font-semibold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-3 py-1.5 rounded transition">+ Input Triase</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 font-semibold border-b border-gray-100">
                            <th class="px-4 py-3 text-left w-10">#</th>
                            <th class="px-4 py-3 text-left">Santri</th>
                            <th class="px-4 py-3 text-left">Keluhan</th>
                            <th class="px-4 py-3 text-center">Prioritas</th>
                            <th class="px-4 py-3 text-center">Skor</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($res_antrean && $res_antrean->num_rows > 0):
                            $rank = 1;
                            while ($row = $res_antrean->fetch_assoc()):
                                $darurat = ($row['tingkat_prioritas'] === 'P1_Gawat_Darurat' || $row['skor_saw_akhir'] >= 0.80);
                        ?>
                        <tr class="hover:bg-gray-50 <?= $darurat ? 'bg-red-50/40' : ''; ?>">
                            <td class="px-4 py-3">
                                <span class="inline-flex w-6 h-6 items-center justify-center rounded text-xs font-bold
                                    <?= $rank === 1 ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-600'; ?>">
                                    <?= $rank++; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800 text-xs"><?= htmlspecialchars($row['nama_lengkap']); ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($row['nis']); ?> · <?= htmlspecialchars($row['kamar_asrama']); ?></div>
                            </td>
                            <td class="px-4 py-3 max-w-[160px]">
                                <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($row['keluhan_utama']); ?></p>
                                <span class="text-xs text-gray-400"><?= tgl_indo($row['tanggal_berobat'], true); ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?= badge_triase($row['tingkat_prioritas'], $row['skor_saw_akhir']); ?>
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-sm
                                <?= $row['skor_saw_akhir'] >= 0.80 ? 'text-red-600' : ($row['skor_saw_akhir'] >= 0.50 ? 'text-amber-600' : 'text-green-600'); ?>">
                                <?= number_format($row['skor_saw_akhir'], 3); ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="rekam_medis.php?kunjungan_id=<?= $row['id']; ?>"
                                   class="text-xs font-semibold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-2.5 py-1.5 rounded transition">
                                    Periksa
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                Tidak ada antrean aktif saat ini.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 flex justify-between items-center">
                <span class="text-xs text-gray-400">P1 ≥ 0.80 &nbsp;·&nbsp; P2 0.50–0.79 &nbsp;·&nbsp; P3 &lt; 0.50</span>
                <a href="triase.php" class="text-xs text-[#16845B] font-semibold hover:underline">Lihat detail SAW →</a>
            </div>
        </div>

        <!-- TABEL PASIEN SEMBUH & SELESAI DITANGANI (PENETAPAN DOKTER) -->
        <div class="card">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-[#16845B] flex items-center justify-center font-black text-xs">
                        ✓
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-[#0B3D2E]">Santri Sembuh &amp; Selesai Ditangani</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Penetapan klinis resmi oleh Dokter Poskestren Al-Bahjah</p>
                    </div>
                </div>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Total: <?= $sembuh_total; ?> Santri Sembuh
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 font-semibold border-b border-gray-100">
                            <th class="px-4 py-3 text-left">Waktu Selesai</th>
                            <th class="px-4 py-3 text-left">Santri</th>
                            <th class="px-4 py-3 text-left">Diagnosa Terakhir</th>
                            <th class="px-4 py-3 text-left">Dokter Pemeriksa</th>
                            <th class="px-4 py-3 text-left">Catatan Evaluasi Pemulihan</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($res_sembuh && $res_sembuh->num_rows > 0):
                            while ($sb = $res_sembuh->fetch_assoc()):
                                $waktu_selesai_txt = !empty($sb['tanggal_selesai']) ? tgl_indo($sb['tanggal_selesai'], true) : tgl_indo($sb['tanggal_berobat'], true);
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                <?= $waktu_selesai_txt; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-800 text-xs"><?= htmlspecialchars($sb['nama_lengkap']); ?></div>
                                <div class="text-[11px] text-gray-400"><?= htmlspecialchars($sb['nis']); ?> &bull; <?= htmlspecialchars($sb['kamar_asrama']); ?></div>
                            </td>
                            <td class="px-4 py-3 font-medium text-xs text-[#0B3D2E]">
                                <?= htmlspecialchars($sb['diagnosa_dokter'] ?: 'Pemeriksaan Klinis'); ?>
                            </td>
                            <td class="px-4 py-3 text-xs font-semibold text-[#16845B]">
                                <?= htmlspecialchars($sb['dokter_pemeriksa'] ?: 'Dokter Jaga'); ?>
                            </td>
                            <td class="px-4 py-3 max-w-[240px]">
                                <p class="text-xs text-gray-600 line-clamp-2" title="<?= htmlspecialchars($sb['catatan_dokter'] ?? '-'); ?>">
                                    <?= htmlspecialchars($sb['catatan_dokter'] ?: 'Kondisi stabil, dinyatakan sembuh.'); ?>
                                </p>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Sembuh &amp; KBM</span>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                                Belum ada data santri yang dinyatakan sembuh.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SIDEBAR KANAN (1/3) -->
    <div class="space-y-4">

        <!-- Chart Donut -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-[#0B3D2E]">Distribusi Triase</h3>
                <span class="text-xs text-gray-400">Hari ini</span>
            </div>
            <div class="relative h-40">
                <canvas id="triaseChart"></canvas>
            </div>
            <div class="grid grid-cols-3 text-center text-xs mt-4 pt-3 border-t border-gray-100">
                <div><div class="font-bold text-red-600"><?= $p1_count; ?></div><div class="text-gray-400">P1</div></div>
                <div><div class="font-bold text-amber-600"><?= $p2_count; ?></div><div class="text-gray-400">P2</div></div>
                <div><div class="font-bold text-green-600"><?= $p3_count; ?></div><div class="text-gray-400">P3</div></div>
            </div>
        </div>

        <!-- Stok Obat Kritis -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-[#0B3D2E]">Peringatan Stok Obat</h3>
                <a href="farmasi.php" class="text-xs text-[#16845B] font-semibold hover:underline">Kelola →</a>
            </div>
            <div class="space-y-2">
                <?php if ($res_obat && $res_obat->num_rows > 0):
                    while ($ob = $res_obat->fetch_assoc()):
                        $kritis = ($ob['stok_sekarang'] <= $ob['stok_minimal']);
                ?>
                <div class="p-2.5 rounded border <?= $kritis ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'; ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-gray-800"><?= htmlspecialchars($ob['nama_obat']); ?></div>
                            <div class="text-[10px] text-gray-400">Exp: <?= date('d M Y', strtotime($ob['exp_date'])); ?></div>
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded
                            <?= $kritis ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'; ?>">
                            <?= $ob['stok_sekarang']; ?> <?= htmlspecialchars($ob['satuan']); ?>
                        </span>
                    </div>
                    <?php if (!empty($ob['keterangan'])): ?>
                    <div class="mt-1.5 pt-1.5 border-t border-amber-200/60 text-[10px] text-amber-900 bg-amber-100/40 p-1 rounded">
                        <strong class="font-semibold">Catatan Apoteker:</strong> <?= htmlspecialchars($ob['keterangan']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; else: ?>
                <p class="text-xs text-gray-400 text-center py-3">Semua stok aman.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- PANEL PESAN MASUK CS -->
<div class="mt-5">
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-[#0B3D2E]">Pesan Masuk dari Pengunjung</h2>
                <span id="badge-unread" class="hidden px-2 py-0.5 rounded-full bg-red-500 text-white text-[10px] font-bold">0</span>
            </div>
            <button onclick="refreshChats()" class="text-xs text-[#16845B] font-semibold hover:underline flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
        <div class="flex" style="height:460px;">
            <!-- Daftar Percakapan -->
            <div id="chat-list" class="w-52 flex-shrink-0 border-r border-gray-100 overflow-y-auto bg-gray-50/50">
                <div class="p-3 text-xs text-gray-400 text-center">Memuat percakapan...</div>
            </div>

            <!-- Area Percakapan -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Header thread + tombol profil -->
                <div id="chat-thread-header" class="px-4 py-2.5 border-b border-gray-100 hidden items-center justify-between gap-2" style="display:none;">
                    <div class="min-w-0">
                        <div class="font-semibold text-sm text-[#0B3D2E] truncate" id="thread-nama">-</div>
                        <div class="text-xs text-gray-400 truncate" id="thread-session">-</div>
                    </div>
                    <button onclick="toggleProfilePanel()"
                            class="flex-shrink-0 px-2.5 py-1.5 text-[11px] font-semibold text-[#16845B] border border-[#d8e6de] rounded hover:bg-emerald-50 transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profil
                    </button>
                </div>

                <div class="flex flex-1 min-h-0">
                    <!-- Kolom pesan -->
                    <div class="flex-1 flex flex-col min-w-0">
                        <div id="chat-thread-empty" class="flex-1 flex items-center justify-center text-sm text-gray-400 p-6 text-center">
                            Pilih percakapan di kiri untuk membalas
                        </div>
                        <div id="chat-thread-messages" class="hidden flex-1 overflow-y-auto p-4 space-y-3 text-xs" style="background:#f8faf9;"></div>
                        <div id="chat-reply-form" class="hidden p-3 border-t border-gray-100 bg-white flex gap-2">
                            <input type="text" id="reply-input" placeholder="Tulis balasan... (Enter = kirim)"
                                   class="flex-1 text-xs px-3 py-2 border border-gray-200 rounded bg-gray-50 focus:outline-none focus:border-[#16845B] focus:bg-white transition">
                            <button onclick="sendReply()"
                                    class="px-4 py-2 bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-semibold rounded transition whitespace-nowrap">
                                Kirim
                            </button>
                        </div>
                    </div>

                    <!-- Panel Profil -->
                    <div id="profile-panel" class="hidden w-52 flex-shrink-0 border-l border-gray-100 overflow-y-auto bg-white">
                        <div class="p-4 space-y-3 text-xs">
                            <div class="text-center pt-1">
                                <div id="profile-avatar" class="w-11 h-11 rounded-full bg-[#16845B] text-white flex items-center justify-center font-bold text-base mx-auto mb-2">?</div>
                                <div id="profile-nama" class="font-bold text-sm text-[#0B3D2E] break-words">-</div>
                                <span id="profile-tipe-badge" class="mt-1 inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500">-</span>
                            </div>
                            <div id="profile-detail" class="space-y-2 pt-2 border-t border-gray-100"></div>
                            <div id="profile-stats" class="pt-2 border-t border-gray-100 space-y-1.5">
                                <div class="font-semibold text-gray-400 uppercase tracking-wide text-[10px] mb-1">Statistik Chat</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── Chart Triase ────────────────────────────────
new Chart(document.getElementById('triaseChart'), {
    type: 'doughnut',
    data: {
        labels: ['P1 Darurat', 'P2 Mendesak', 'P3 Biasa'],
        datasets: [{ data: [<?= $p1_count; ?>, <?= $p2_count; ?>, <?= $p3_count; ?>], backgroundColor: ['#D64545','#F4C542','#16845B'], borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, color: '#555' } } }, cutout: '68%' }
});
</script>

<script>
// ── Chat CS Panel ───────────────────────────────────────────
let activeSession = null;

function refreshChats() {
    fetch('api_chat.php?action=list')
    .then(r => r.json())
    .then(data => {
        if (!data.ok) return;
        const list  = document.getElementById('chat-list');
        const badge = document.getElementById('badge-unread');
        let totalUnread = 0;

        if (data.chats.length === 0) {
            list.innerHTML = '<div class="p-4 text-xs text-gray-400 text-center">Belum ada pesan masuk.</div>';
            badge.classList.add('hidden');
            return;
        }

        list.innerHTML = data.chats.map(c => {
            totalUnread += parseInt(c.unread || 0);
            const unreadBadge = c.unread > 0
                ? `<span class="ml-1 px-1.5 py-0.5 bg-red-500 text-white text-[9px] rounded-full font-bold">${c.unread}</span>` : '';
            const isActive = activeSession === c.session_key ? 'bg-emerald-50 border-l-2 border-[#16845B]' : 'hover:bg-gray-100';
            const lastMsg  = c.last_msg ? c.last_msg.substring(0, 42) + (c.last_msg.length > 42 ? '…' : '') : '';
            const time     = new Date(c.last_time).toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});
            return `<div onclick="openThread('${cssEsc(c.session_key)}', '${cssEsc(c.nama_pengirim)}')"
                        class="px-3 py-3 cursor-pointer border-b border-gray-100 text-xs ${isActive}">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="font-semibold text-[#0B3D2E]">${htmlEsc(c.nama_pengirim)}${unreadBadge}</span>
                            <span class="text-[10px] text-gray-400">${time}</span>
                        </div>
                        <div class="text-[11px] text-gray-500 truncate">${htmlEsc(lastMsg)}</div>
                    </div>`;
        }).join('');

        if (totalUnread > 0) {
            badge.textContent = totalUnread;
            badge.classList.remove('hidden');
            document.title = document.title.replace(/^\(\d+\) /, '');
            document.title = '(' + totalUnread + ') ' + document.title;
        } else {
            badge.classList.add('hidden');
            document.title = document.title.replace(/^\(\d+\) /, '');
        }
    });
}

function openThread(sessionKey, nama) {
    activeSession = sessionKey;
    document.getElementById('chat-thread-empty').classList.add('hidden');
    document.getElementById('chat-thread-messages').classList.remove('hidden');
    document.getElementById('chat-reply-form').classList.remove('hidden');
    document.getElementById('chat-thread-header').classList.remove('hidden');
    document.getElementById('chat-thread-header').style.display = 'flex';
    document.getElementById('thread-nama').textContent = nama;
    document.getElementById('thread-session').textContent = 'ID Sesi: ' + sessionKey.substring(0, 24) + '…';

    loadProfile(sessionKey);

    fetch(`api_chat.php?action=thread&session_key=${encodeURIComponent(sessionKey)}`)
    .then(r => r.json())
    .then(data => {
        if (!data.ok) return;
        const container = document.getElementById('chat-thread-messages');
        container.innerHTML = '';
        data.messages.forEach(m => {
            const time = new Date(m.created_at).toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});
            if (m.dari_cs == 1) {
                container.insertAdjacentHTML('beforeend', `
                    <div class="flex justify-end">
                        <div class="bg-[#16845B] text-white p-2.5 rounded-2xl rounded-tr-none max-w-[80%]">
                            <p class="font-semibold text-[10px] mb-0.5 text-emerald-100">${htmlEsc(m.nama_pengirim)}</p>
                            <p>${htmlEsc(m.pesan)}</p>
                            <span class="block text-[9px] text-emerald-200 mt-0.5 text-right">${time}</span>
                        </div>
                    </div>`);
            } else {
                container.insertAdjacentHTML('beforeend', `
                    <div class="flex items-start gap-2">
                        <div class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center font-bold text-[10px] text-gray-600 flex-shrink-0">${htmlEsc(m.nama_pengirim.charAt(0).toUpperCase())}</div>
                        <div class="bg-white border border-gray-200 p-2.5 rounded-2xl rounded-tl-none max-w-[80%]">
                            <p class="font-semibold text-[10px] text-[#0B3D2E] mb-0.5">${htmlEsc(m.nama_pengirim)}</p>
                            <p>${htmlEsc(m.pesan)}</p>
                            <span class="block text-[9px] text-gray-400 mt-0.5">${time}</span>
                        </div>
                    </div>`);
            }
        });
        container.scrollTop = container.scrollHeight;
        refreshChats();
    });

    document.getElementById('reply-input').focus();
}

function toggleProfilePanel() {
    const p = document.getElementById('profile-panel');
    p.classList.toggle('hidden');
}

function loadProfile(sessionKey) {
    fetch(`api_chat.php?action=profile&session_key=${encodeURIComponent(sessionKey)}`)
    .then(r => r.json())
    .then(data => {
        if (!data.ok || !data.profile) return;
        const prof = data.profile;
        document.getElementById('profile-nama').textContent = prof.nama || 'Pengunjung';
        document.getElementById('profile-avatar').textContent = (prof.nama || 'P').charAt(0).toUpperCase();
        
        const badgeEl = document.getElementById('profile-tipe-badge');
        if (prof.tipe === 'wali_santri') {
            badgeEl.textContent = 'Wali Santri';
            badgeEl.className = 'mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800';
        } else {
            badgeEl.textContent = 'Pengunjung';
            badgeEl.className = 'mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600';
        }

        const detEl = document.getElementById('profile-detail');
        detEl.innerHTML = '';
        if (prof.detail) {
            for (const [k, v] of Object.entries(prof.detail)) {
                detEl.innerHTML += `<div class="flex justify-between gap-1 text-[11px]"><span class="text-gray-400">${htmlEsc(k)}</span><span class="font-medium text-gray-700 text-right truncate">${htmlEsc(v)}</span></div>`;
            }
        }

        const statsEl = document.getElementById('profile-stats');
        if (prof.stats) {
            statsEl.innerHTML = `
                <div class="font-semibold text-gray-500 uppercase text-[10px] mb-1">Statistik Pesan</div>
                <div class="flex justify-between text-[11px]"><span class="text-gray-400">Total Pesan</span><span class="font-semibold">${prof.stats.total || 0}</span></div>
                <div class="flex justify-between text-[11px]"><span class="text-gray-400">Dari Pengguna</span><span class="font-semibold">${prof.stats.dari_user || 0}</span></div>
            `;
        }
    });
}

function sendReply() {
    const input = document.getElementById('reply-input');
    const text  = input.value.trim();
    if (!text || !activeSession) return;

    const currentSession = activeSession;
    fetch('api_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'reply', session_key: currentSession, pesan: text })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            input.value = '';
            openThread(currentSession, document.getElementById('thread-nama').textContent);
        }
    });
}

document.getElementById('reply-input').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
});

function htmlEsc(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function cssEsc(str) {
    return (str || '').replace(/'/g, "\\'").replace(/\\/g, '\\\\');
}

// Auto-refresh setiap 8 detik
refreshChats();
setInterval(refreshChats, 8000);
</script>

<?php require_once 'footer.php'; ?>
