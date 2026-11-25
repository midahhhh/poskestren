<?php
// dokter.php — Monitoring Klinis & Pasien Rawat Inap (Khusus Admin)
$page_title = "Monitoring Pasien & Dokter (Admin)";
$page_desc  = "Supervisi kondisi santri sakit, perkembangan rawat inap, dan antrean triase SAW.";
require_once 'header.php';

// Hak akses: KHUSUS ADMIN
if ($user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Halaman dokter.php ini khusus untuk Admin. Dokter diarahkan ke Dashboard Dokter.");
    header("Location: dashboard_dokter.php"); exit;
}

// ── Aksi: Update catatan perkembangan rawat inap ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_catatan') {
    $ri_id   = (int)$_POST['rawat_inap_id'];
    $catatan = trim($_POST['catatan'] ?? '');
    if ($ri_id > 0 && !empty($catatan)) {
        $stmt = $conn->prepare("UPDATE rawat_inap SET catatan_perkembangan = ? WHERE id = ?");
        $stmt->bind_param("si", $catatan, $ri_id);
        $stmt->execute();
        set_flash('success', "Catatan perkembangan pasien berhasil diperbarui.");
    }
    header("Location: dokter.php#rawat-inap"); exit;
}

// ── Aksi: Tandai selesai rawat inap ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'selesai_rawat') {
    $k_id = (int)$_POST['kunjungan_id'];
    if ($k_id > 0) {
        $conn->query("UPDATE rawat_inap SET status_rawat='Selesai' WHERE kunjungan_id=$k_id");
        $conn->query("UPDATE kunjungan_sakit SET status_antrean='selesai' WHERE id=$k_id");
        set_flash('success', "Pasien rawat inap berhasil diselesaikan.");
    }
    header("Location: dokter.php#rawat-inap"); exit;
}

// ── Hitung statistik ─────────────────────────────────────────
$hari_ini   = date('Y-m-d');
$tot_hari   = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE DATE(tanggal_berobat)='$hari_ini'")->fetch_assoc()['c'];
$p1_aktif   = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND tingkat_prioritas='P1_Gawat_Darurat'")->fetch_assoc()['c'];
$p2_aktif   = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND tingkat_prioritas='P2_Mendesak'")->fetch_assoc()['c'];
$p3_aktif   = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND tingkat_prioritas='P3_Biasa'")->fetch_assoc()['c'];
$rawat_inap = $conn->query("SELECT COUNT(*) c FROM rawat_inap WHERE status_rawat='Dirawat'")->fetch_assoc()['c'];
$blm_rekam  = $conn->query("SELECT COUNT(*) c FROM kunjungan_sakit k LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id=k.id WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan') AND pk.id IS NULL")->fetch_assoc()['c'];

// ── Antrean aktif + detail SAW ────────────────────────────────
$res_antrean = $conn->query(
    "SELECT k.id, k.no_antrean, k.keluhan_utama, k.tanggal_berobat,
            k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas,
            s.id as santri_id, s.nama_lengkap, s.nis, s.jenis_kelamin,
            s.kamar_asrama, s.kompleks_asrama, s.tanggal_lahir,
            s.nama_wali, s.no_hp_wali,
            pk.id as rekam_id, pk.tekanan_darah, pk.suhu_tubuh, pk.nadi,
            pk.laju_respirasi, pk.saturasi_oksigen, pk.tingkat_kesadaran,
            pk.diagnosa_dokter, pk.tindakan_pertama, pk.rekomendasi_rawat
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan')
     ORDER BY
         CASE k.tingkat_prioritas WHEN 'P1_Gawat_Darurat' THEN 1
                                  WHEN 'P2_Mendesak' THEN 2 ELSE 3 END,
         k.skor_saw_akhir DESC, k.id ASC"
);

// ── Kumpulkan nilai SAW per kunjungan ─────────────────────────
$saw_detail = [];
if ($res_antrean && $res_antrean->num_rows > 0) {
    // Kumpulkan semua id kunjungan aktif
    $ids = [];
    while ($r = $res_antrean->fetch_assoc()) $ids[] = $r['id'];
    $res_antrean->data_seek(0);

    if (!empty($ids)) {
        $id_str = implode(',', $ids);
        $res_saw = $conn->query(
            "SELECT snp.kunjungan_id, sk.nama_kriteria, snp.nilai_raw,
                    snp.nilai_normalisasi, snp.nilai_terbobot,
                    sk.bobot_persen, ssk.deskripsi
             FROM saw_nilai_pemeriksaan snp
             JOIN saw_kriteria sk ON sk.id = snp.kriteria_id
             LEFT JOIN saw_sub_kriteria ssk ON ssk.id = snp.sub_kriteria_id
             WHERE snp.kunjungan_id IN ($id_str)
             ORDER BY snp.kunjungan_id, snp.kriteria_id"
        );
        if ($res_saw) {
            while ($sv = $res_saw->fetch_assoc()) {
                $saw_detail[$sv['kunjungan_id']][] = $sv;
            }
        }
    }
}

// ── Rawat inap aktif ──────────────────────────────────────────
$res_rawat = $conn->query(
    "SELECT ri.id as ri_id, ri.kunjungan_id, ri.nomor_bed,
            ri.status_rawat, ri.catatan_perkembangan,
            k.no_antrean, k.tanggal_berobat, k.keluhan_utama,
            k.skor_saw_akhir, k.tingkat_prioritas,
            s.nama_lengkap, s.nis, s.jenis_kelamin,
            s.kamar_asrama, s.nama_wali, s.no_hp_wali,
            pk.tekanan_darah, pk.suhu_tubuh, pk.nadi,
            pk.laju_respirasi, pk.saturasi_oksigen,
            pk.diagnosa_dokter, pk.rekomendasi_rawat
     FROM rawat_inap ri
     JOIN kunjungan_sakit k ON k.id = ri.kunjungan_id
     JOIN santri s ON s.id = k.santri_id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     WHERE ri.status_rawat = 'Dirawat'
     ORDER BY k.id DESC"
);

// ── Riwayat 7 hari terakhir ───────────────────────────────────
$res_riwayat = $conn->query(
    "SELECT k.id, k.no_antrean, k.tanggal_berobat,
            k.keluhan_utama, k.status_antrean, k.skor_saw_akhir, k.tingkat_prioritas,
            s.nama_lengkap, s.nis, s.kamar_asrama, s.jenis_kelamin,
            pk.suhu_tubuh, pk.nadi, pk.saturasi_oksigen,
            pk.diagnosa_dokter, pk.rekomendasi_rawat,
            u.nama_lengkap as nama_dokter
     FROM kunjungan_sakit k
     JOIN santri s ON k.santri_id = s.id
     LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
     LEFT JOIN users u ON u.id = pk.petugas_id
     WHERE k.tanggal_berobat >= DATE_SUB(NOW(), INTERVAL 7 DAY)
       AND k.status_antrean NOT IN ('menunggu_triase','pemeriksaan')
     ORDER BY k.id DESC
     LIMIT 40"
);

// ── Distribusi penyakit terbanyak 30 hari ────────────────────
$res_diagnosa = $conn->query(
    "SELECT pk.diagnosa_dokter, COUNT(*) as total
     FROM pemeriksaan_klinis pk
     JOIN kunjungan_sakit k ON k.id = pk.kunjungan_id
     WHERE k.tanggal_berobat >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND pk.diagnosa_dokter IS NOT NULL AND pk.diagnosa_dokter != ''
     GROUP BY pk.diagnosa_dokter
     ORDER BY total DESC LIMIT 8"
);
?>

<!-- ── STAT CARDS ─────────────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <?php
    $cards = [
        ['Kunjungan Hari Ini', $tot_hari,  'santri',        '#0B3D2E', ''],
        ['P1 — Darurat',       $p1_aktif,  'butuh segera',  '#D64545', 'border-red-200 bg-red-50/30'],
        ['P2 — Mendesak',      $p2_aktif,  'dalam antri',   '#b45309', 'border-amber-200 bg-amber-50/30'],
        ['P3 — Ringan',        $p3_aktif,  'kondisi stabil','#16845B', ''],
        ['Rawat Inap',         $rawat_inap,'bed aktif',     '#1d4ed8', ''],
        ['Belum Diperiksa',    $blm_rekam, 'perlu tindakan',$blm_rekam > 0 ? '#D64545':'#16845B', $blm_rekam > 0 ? 'border-red-200' : ''],
    ];
    foreach ($cards as [$label, $val, $sub, $color, $extra]):
    ?>
    <div class="card p-4 <?= $extra; ?>">
        <div class="text-xs text-gray-500 mb-1"><?= $label; ?></div>
        <div class="text-2xl font-bold" style="color:<?= $color; ?>"><?= $val; ?></div>
        <div class="text-xs text-gray-400 mt-0.5"><?= $sub; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── GRID UTAMA ─────────────────────────────────────────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">

    <!-- Distribusi Penyakit -->
    <div class="card p-5">
        <div class="font-bold text-sm text-[#0B3D2E] mb-4">Penyakit Terbanyak <span class="text-xs font-normal text-gray-400">(30 hari)</span></div>
        <div class="space-y-2.5">
            <?php
            $max_diag = 1;
            $diag_arr = [];
            if ($res_diagnosa) while ($d = $res_diagnosa->fetch_assoc()) {
                $diag_arr[] = $d;
                $max_diag = max($max_diag, $d['total']);
            }
            if (empty($diag_arr)):
            ?>
            <p class="text-xs text-gray-400 text-center py-4">Belum ada data diagnosa.</p>
            <?php else: foreach ($diag_arr as $i => $d):
                $pct   = round(($d['total'] / $max_diag) * 100);
                $colors = ['#D64545','#f97316','#eab308','#16845B','#3b82f6','#8b5cf6','#ec4899','#64748b'];
                $clr   = $colors[$i % count($colors)];
            ?>
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="text-gray-700 font-medium truncate max-w-[70%]" title="<?= htmlspecialchars($d['diagnosa_dokter']); ?>"><?= htmlspecialchars($d['diagnosa_dokter']); ?></span>
                    <span class="font-bold text-gray-600"><?= $d['total']; ?> kasus</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full">
                    <div class="h-1.5 rounded-full transition-all" style="width:<?= $pct; ?>%; background:<?= $clr; ?>;"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Ringkasan Cepat Dokter -->
    <div class="card p-5 xl:col-span-2">
        <div class="font-bold text-sm text-[#0B3D2E] mb-4">Ringkasan Klinis Hari Ini</div>
        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 bg-red-50 border border-red-100 rounded-xl">
                <div class="font-bold text-red-700 text-base"><?= $p1_aktif; ?></div>
                <div class="text-red-600 font-semibold">Gawat Darurat (P1)</div>
                <div class="text-gray-400 mt-0.5">Perlu tindakan segera</div>
            </div>
            <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl">
                <div class="font-bold text-amber-700 text-base"><?= $p2_aktif; ?></div>
                <div class="text-amber-600 font-semibold">Mendesak (P2)</div>
                <div class="text-gray-400 mt-0.5">Antri &lt; 30 menit</div>
            </div>
            <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl">
                <div class="font-bold text-blue-700 text-base"><?= $rawat_inap; ?></div>
                <div class="text-blue-600 font-semibold">Rawat Inap Aktif</div>
                <div class="text-gray-400 mt-0.5">Perlu monitoring</div>
            </div>
            <div class="p-3 bg-<?= $blm_rekam > 0 ? 'red' : 'green'; ?>-50 border border-<?= $blm_rekam > 0 ? 'red' : 'green'; ?>-100 rounded-xl">
                <div class="font-bold text-<?= $blm_rekam > 0 ? 'red' : 'green'; ?>-700 text-base"><?= $blm_rekam; ?></div>
                <div class="text-<?= $blm_rekam > 0 ? 'red' : 'green'; ?>-600 font-semibold"><?= $blm_rekam > 0 ? 'Belum Diperiksa' : 'Semua Tertangani'; ?></div>
                <div class="text-gray-400 mt-0.5"><?= $blm_rekam > 0 ? 'Segera lakukan anamnesis' : 'Tidak ada antrian tertunda'; ?></div>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="triase.php" class="text-xs font-semibold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-3 py-2 rounded-lg transition">+ Input Triase</a>
            <a href="rekam_medis_admin.php" class="text-xs font-semibold text-[#0B3D2E] bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg transition">Rekam Medis Pasien</a>
            <a href="#rawat-inap" class="text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-lg transition">Monitor Rawat Inap</a>
        </div>
    </div>
</div>

<!-- ── ANTREAN AKTIF + DETAIL GEJALA ─────────────────────────── -->
<div class="card mb-6" id="antrean">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-bold text-[#0B3D2E]">Antrean Aktif — Kondisi & Gejala Santri</h2>
            <p class="text-xs text-gray-400 mt-0.5">Klik baris untuk melihat detail klinis, skor SAW, dan parameter gejala.</p>
        </div>
        <a href="triase.php" class="text-xs font-semibold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-3 py-1.5 rounded-lg transition">+ Input Triase</a>
    </div>

    <?php if (!$res_antrean || $res_antrean->num_rows === 0): ?>
    <div class="p-10 text-center text-sm text-gray-400">Tidak ada antrean aktif saat ini. Semua santri sudah tertangani.</div>
    <?php else: $rank = 0; while ($ant = $res_antrean->fetch_assoc()):
        $rank++;
        $darurat   = ($ant['tingkat_prioritas'] === 'P1_Gawat_Darurat');
        $sedang    = ($ant['tingkat_prioritas'] === 'P2_Mendesak');
        $detail_id = 'detail_' . $ant['id'];
        $saw_rows  = $saw_detail[$ant['id']] ?? [];
        $umur      = $ant['tanggal_lahir'] ? (int)((time() - strtotime($ant['tanggal_lahir'])) / 31536000) : null;
        $sudah_rekam = !empty($ant['rekam_id']);
    ?>
    <!-- Baris Ringkasan -->
    <div class="border-b border-gray-100 last:border-0">
        <div class="px-5 py-3.5 flex items-center gap-4 cursor-pointer hover:bg-gray-50 transition"
             onclick="toggleDetail('<?= $detail_id; ?>')">

            <!-- Rank badge -->
            <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                <?= $darurat ? 'bg-red-500 text-white' : ($sedang ? 'bg-amber-400 text-white' : 'bg-gray-100 text-gray-600'); ?>">
                <?= $rank; ?>
            </div>

            <!-- Identitas -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-sm text-[#0B3D2E]"><?= htmlspecialchars($ant['nama_lengkap']); ?></span>
                    <span class="text-xs text-gray-400"><?= $ant['nis']; ?></span>
                    <?php if ($umur): ?><span class="text-xs text-gray-400"><?= $umur; ?> th</span><?php endif; ?>
                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500"><?= $ant['jenis_kelamin'] === 'L' ? '♂ Ikhwan' : '♀ Akhwat'; ?></span>
                </div>
                <div class="text-xs text-gray-500 truncate mt-0.5">
                    📍 <?= htmlspecialchars($ant['kamar_asrama']); ?> — <?= htmlspecialchars($ant['kompleks_asrama']); ?>
                </div>
            </div>

            <!-- Keluhan -->
            <div class="hidden sm:block flex-1 min-w-0">
                <div class="text-xs text-gray-700 font-medium truncate"><?= htmlspecialchars($ant['keluhan_utama']); ?></div>
                <div class="text-xs text-gray-400 mt-0.5"><?= tgl_indo($ant['tanggal_berobat'], true); ?></div>
            </div>

            <!-- Skor SAW -->
            <div class="flex-shrink-0 text-center">
                <div class="text-base font-black font-mono <?= $darurat ? 'text-red-600' : ($sedang ? 'text-amber-600' : 'text-green-600'); ?>">
                    <?= number_format($ant['skor_saw_akhir'], 3); ?>
                </div>
                <div class="text-[10px] text-gray-400">Skor SAW</div>
            </div>

            <!-- Prioritas & status rekam -->
            <div class="flex-shrink-0 flex flex-col items-end gap-1">
                <?= badge_triase($ant['tingkat_prioritas'], $ant['skor_saw_akhir']); ?>
                <?php if ($sudah_rekam): ?>
                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">✓ Sudah Diperiksa</span>
                <?php else: ?>
                <span class="text-[10px] px-2 py-0.5 rounded bg-red-100 text-red-700 font-semibold animate-pulse">⚑ Belum Diperiksa</span>
                <?php endif; ?>
            </div>

            <!-- Toggle icon -->
            <div class="flex-shrink-0 text-gray-300">
                <svg id="icon_<?= $detail_id; ?>" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <!-- Detail Panel (collapsible) -->
        <div id="<?= $detail_id; ?>" class="hidden border-t border-gray-100" style="background:#fafcfb;">
            <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-5">

                <!-- Kolom 1: Data Santri & Wali -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Identitas & Wali</h4>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-500">NIS</span>
                            <span class="font-mono font-bold text-[#0B3D2E]"><?= htmlspecialchars($ant['nis']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kelamin</span>
                            <span class="font-semibold"><?= $ant['jenis_kelamin'] === 'L' ? 'Ikhwan (L)' : 'Akhwat (P)'; ?></span>
                        </div>
                        <?php if ($umur): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Usia</span>
                            <span class="font-semibold"><?= $umur; ?> tahun</span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Asrama</span>
                            <span class="font-semibold text-right max-w-[55%]"><?= htmlspecialchars($ant['kamar_asrama']); ?></span>
                        </div>
                        <hr class="border-gray-100">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Wali</span>
                            <span class="font-semibold text-right max-w-[55%]"><?= htmlspecialchars($ant['nama_wali'] ?: '-'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">No. HP Wali</span>
                            <?php if (!empty($ant['no_hp_wali'])): ?>
                            <a href="https://wa.me/62<?= ltrim($ant['no_hp_wali'], '0'); ?>" target="_blank"
                               class="font-mono font-semibold text-[#16845B] hover:underline">
                                <?= htmlspecialchars($ant['no_hp_wali']); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Keluhan & Status -->
                    <div class="p-3 bg-white rounded-xl border border-gray-200">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Keluhan Utama</div>
                        <p class="text-xs text-gray-700 leading-relaxed"><?= htmlspecialchars($ant['keluhan_utama']); ?></p>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="flex flex-col gap-2 pt-1">
                        <a href="rekam_medis_admin.php?detail_id=<?= $ant['id']; ?>"
                           class="text-center text-xs font-bold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-3 py-2 rounded-lg transition">
                            <?= $sudah_rekam ? 'Lihat Rekam Medis' : 'Detail Rekam Medis'; ?>
                        </a>
                        <a href="triase.php?santri_id=<?= $ant['santri_id']; ?>"
                           class="text-center text-xs font-semibold text-[#0B3D2E] bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg transition">
                            Lihat Detail Triase
                        </a>
                    </div>
                </div>

                <!-- Kolom 2: Parameter SAW / Gejala Klinis -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Parameter Gejala (SAW)</h4>
                    <?php if (empty($saw_rows)): ?>
                    <p class="text-xs text-gray-400 italic">Data parameter SAW belum tersedia.</p>
                    <?php else: ?>
                    <div class="space-y-2.5">
                        <?php
                        $icons = ['🌡','😣','🏥','⏱','💓'];
                        $c_colors = ['text-red-600','text-orange-600','text-purple-600','text-blue-600','text-green-600'];
                        foreach ($saw_rows as $ci => $sv):
                            $crisp = (int)$sv['nilai_raw'];
                            $bar_w = ($crisp / 5) * 100;
                            $bar_c = $crisp >= 4 ? '#D64545' : ($crisp >= 3 ? '#f97316' : '#16845B');
                        ?>
                        <div class="p-2.5 bg-white rounded-xl border border-gray-100">
                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <span class="text-[11px] font-bold text-gray-700 leading-tight">
                                    <?= $icons[$ci] ?? '•'; ?> <?= htmlspecialchars($sv['nama_kriteria']); ?>
                                    <span class="text-gray-400 font-normal">(<?= number_format($sv['bobot_persen'],0); ?>%)</span>
                                </span>
                                <span class="text-xs font-black <?= $c_colors[$ci % count($c_colors)]; ?> flex-shrink-0"><?= $crisp; ?>/5</span>
                            </div>
                            <!-- Progress bar -->
                            <div class="h-1.5 bg-gray-100 rounded-full mb-1.5">
                                <div class="h-1.5 rounded-full" style="width:<?= $bar_w; ?>%; background:<?= $bar_c; ?>;"></div>
                            </div>
                            <div class="text-[10px] text-gray-500 leading-snug"><?= htmlspecialchars($sv['deskripsi'] ?? '-'); ?></div>
                            <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                                <span>Bobot: <?= number_format($sv['nilai_terbobot'], 4); ?></span>
                                <span>Norm: <?= number_format($sv['nilai_normalisasi'], 4); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Total skor -->
                    <div class="p-3 rounded-xl border-2 <?= $darurat ? 'border-red-300 bg-red-50' : ($sedang ? 'border-amber-300 bg-amber-50' : 'border-green-200 bg-green-50'); ?>">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-gray-600">Total Skor V (SAW):</span>
                            <span class="text-xl font-black font-mono <?= $darurat ? 'text-red-600' : ($sedang ? 'text-amber-600' : 'text-green-600'); ?>">
                                <?= number_format($ant['skor_saw_akhir'], 4); ?>
                            </span>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-0.5 text-right">
                            <?= $darurat ? 'P1 ≥ 0.80 — Gawat Darurat' : ($sedang ? 'P2 ≥ 0.50 — Mendesak' : 'P3 < 0.50 — Ringan'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Kolom 3: TTV & Rekam Medis -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Tanda Vital & Diagnosa</h4>
                    <?php if ($sudah_rekam): ?>
                    <div class="grid grid-cols-2 gap-2">
                        <?php
                        $vitalData = [
                            ['Tekanan Darah', $ant['tekanan_darah'] ?? '-', 'mmHg', '#1d4ed8'],
                            ['Suhu Tubuh',    $ant['suhu_tubuh'] ? number_format($ant['suhu_tubuh'],1).' °C' : '-', '', ($ant['suhu_tubuh'] >= 39 ? '#D64545' : '#16845B')],
                            ['Nadi',          $ant['nadi'] ? $ant['nadi'].' x/mnt' : '-', '', '#0B3D2E'],
                            ['Laju Napas',    $ant['laju_respirasi'] ? $ant['laju_respirasi'].' x/mnt' : '-', '', ($ant['laju_respirasi'] > 24 ? '#D64545' : '#0B3D2E')],
                            ['SpO2',          $ant['saturasi_oksigen'] ? $ant['saturasi_oksigen'].'%' : '-', '', ($ant['saturasi_oksigen'] < 95 ? '#D64545' : '#16845B')],
                            ['Kesadaran',     $ant['tingkat_kesadaran'] ?? '-', '', '#374151'],
                        ];
                        foreach ($vitalData as [$lbl, $val, $unit, $clr]):
                        ?>
                        <div class="p-2 bg-white rounded-lg border border-gray-100 text-center">
                            <div class="text-[10px] text-gray-400 mb-0.5"><?= $lbl; ?></div>
                            <div class="text-sm font-bold" style="color:<?= $clr; ?>"><?= htmlspecialchars($val); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Diagnosa -->
                    <div class="p-3 bg-white rounded-xl border border-gray-200">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Diagnosa</div>
                        <p class="text-xs text-gray-800 font-semibold"><?= htmlspecialchars($ant['diagnosa_dokter'] ?? '-'); ?></p>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-gray-200">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1">Tindakan & Rekomendasi</div>
                        <p class="text-xs text-gray-700"><?= htmlspecialchars($ant['tindakan_pertama'] ?? '-'); ?></p>
                        <?php if (!empty($ant['rekomendasi_rawat'])): ?>
                        <span class="mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-bold
                            <?= $ant['rekomendasi_rawat'] === 'Rawat Inap Poskestren' ? 'bg-blue-100 text-blue-800'
                               : ($ant['rekomendasi_rawat'] === 'Rujuk RS' ? 'bg-red-100 text-red-800'
                               : 'bg-green-100 text-green-800'); ?>">
                            <?= htmlspecialchars($ant['rekomendasi_rawat']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="flex flex-col items-center justify-center h-40 bg-red-50 border border-red-100 rounded-xl text-center p-4">
                        <div class="text-2xl mb-2">⚑</div>
                        <p class="text-xs font-bold text-red-700 mb-1">Belum ada rekam medis</p>
                        <p class="text-xs text-gray-500 mb-3">Santri ini belum mendapatkan pemeriksaan dokter.</p>
                        <a href="rekam_medis_admin.php?detail_id=<?= $ant['id']; ?>"
                           class="text-xs font-bold text-white bg-[#0B3D2E] hover:bg-[#16845B] px-4 py-2 rounded-lg transition">
                            Lihat Rekam Medis →
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- ── RAWAT INAP AKTIF ────────────────────────────────────────── -->
<div class="card mb-6" id="rawat-inap">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-bold text-[#0B3D2E]">Monitor Rawat Inap Aktif</h2>
            <p class="text-xs text-gray-400 mt-0.5">Pasien yang sedang diobservasi di ruang rawat inap poskestren.</p>
        </div>
        <span class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-full">
            <?= $rawat_inap; ?> Bed Terisi
        </span>
    </div>

    <?php if (!$res_rawat || $res_rawat->num_rows === 0): ?>
    <div class="p-8 text-center text-sm text-gray-400">Tidak ada pasien rawat inap saat ini.</div>
    <?php else: while ($ri = $res_rawat->fetch_assoc()):
        $ri_darurat = ($ri['tingkat_prioritas'] === 'P1_Gawat_Darurat');
    ?>
    <div class="border-b border-gray-100 last:border-0 p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

            <!-- Info Pasien -->
            <div>
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-base flex-shrink-0 <?= $ri_darurat ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'; ?>">
                        <?= strtoupper(mb_substr($ri['nama_lengkap'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-[#0B3D2E]"><?= htmlspecialchars($ri['nama_lengkap']); ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($ri['nis']); ?> &bull; <?= htmlspecialchars($ri['kamar_asrama']); ?></div>
                        <div class="text-xs text-gray-400"><?= $ri['jenis_kelamin'] === 'L' ? '♂ Ikhwan' : '♀ Akhwat'; ?></div>
                    </div>
                </div>
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">No. Bed</span>
                        <span class="font-bold text-blue-700"><?= htmlspecialchars($ri['nomor_bed']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Masuk</span>
                        <span class="font-semibold"><?= tgl_indo($ri['tanggal_berobat'], true); ?></span>
                    </div>
                    <?= badge_triase($ri['tingkat_prioritas'], $ri['skor_saw_akhir']); ?>
                </div>
                <!-- Kontak wali -->
                <?php if (!empty($ri['no_hp_wali'])): ?>
                <a href="https://wa.me/62<?= ltrim($ri['no_hp_wali'], '0'); ?>" target="_blank"
                   class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 px-3 py-1.5 rounded-lg transition w-fit">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.532 5.845L.064 23.5l5.815-1.522A11.93 11.93 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.693-.506-5.234-1.392l-.374-.222-3.879 1.016 1.035-3.77-.24-.386A9.956 9.956 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                    WA: <?= htmlspecialchars($ri['nama_wali'] ?: $ri['no_hp_wali']); ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- TTV & Diagnosa -->
            <div class="space-y-2 text-xs">
                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Tanda Vital Terakhir</div>
                <div class="grid grid-cols-2 gap-1.5">
                    <?php
                    $vitals = [
                        ['TD', $ri['tekanan_darah'] ?? '-', '#1d4ed8'],
                        ['Suhu', $ri['suhu_tubuh'] ? $ri['suhu_tubuh'].'°C' : '-', ($ri['suhu_tubuh'] >= 39 ? '#D64545' : '#16845B')],
                        ['Nadi', $ri['nadi'] ? $ri['nadi'].'x/m' : '-', '#374151'],
                        ['SpO2', $ri['saturasi_oksigen'] ? $ri['saturasi_oksigen'].'%' : '-', ($ri['saturasi_oksigen'] < 95 ? '#D64545' : '#16845B')],
                    ];
                    foreach ($vitals as [$lbl, $val, $clr]):
                    ?>
                    <div class="p-2 bg-gray-50 rounded-lg text-center">
                        <div class="text-[10px] text-gray-400"><?= $lbl; ?></div>
                        <div class="font-bold text-sm" style="color:<?= $clr; ?>"><?= htmlspecialchars($val); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-2.5 bg-white rounded-lg border border-gray-100 mt-2">
                    <div class="text-[10px] text-gray-400 mb-0.5">Diagnosa</div>
                    <div class="font-semibold text-xs"><?= htmlspecialchars($ri['diagnosa_dokter'] ?? '-'); ?></div>
                </div>
                <div class="p-2.5 bg-white rounded-lg border border-gray-100">
                    <div class="text-[10px] text-gray-400 mb-0.5">Keluhan Awal</div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($ri['keluhan_utama']); ?></div>
                </div>
            </div>

            <!-- Catatan perkembangan -->
            <div class="space-y-2">
                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Catatan Perkembangan</div>
                <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-900 min-h-[60px]">
                    <?= nl2br(htmlspecialchars($ri['catatan_perkembangan'] ?? '-')); ?>
                </div>
                <!-- Update catatan -->
                <form method="POST" action="dokter.php#rawat-inap" class="space-y-2">
                    <input type="hidden" name="action" value="update_catatan">
                    <input type="hidden" name="rawat_inap_id" value="<?= $ri['ri_id']; ?>">
                    <textarea name="catatan" rows="2" placeholder="Tambah catatan perkembangan..."
                              class="w-full text-xs px-3 py-2 border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-[#16845B] resize-none"></textarea>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="flex-1 text-xs font-semibold text-white bg-[#16845B] hover:bg-[#0B3D2E] px-3 py-2 rounded-lg transition">
                            Simpan Catatan
                        </button>
                        <form method="POST" action="dokter.php#rawat-inap" class="inline">
                            <input type="hidden" name="action" value="selesai_rawat">
                            <input type="hidden" name="kunjungan_id" value="<?= $ri['kunjungan_id']; ?>">
                            <button type="submit"
                                    onclick="return confirm('Tandai pasien <?= addslashes($ri['nama_lengkap']); ?> sudah selesai rawat inap?')"
                                    class="text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-2 rounded-lg transition whitespace-nowrap">
                                ✓ Selesai Rawat
                            </button>
                        </form>
                    </div>
                </form>
            </div>

        </div>
    </div>
    <?php endwhile; endif; ?>
</div>

<!-- ── RIWAYAT 7 HARI TERAKHIR ───────────────────────────────── -->
<div class="card" id="riwayat">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-bold text-[#0B3D2E]">Riwayat Kunjungan 7 Hari Terakhir</h2>
        <p class="text-xs text-gray-400 mt-0.5">Semua pasien yang sudah selesai ditangani.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 text-gray-500 font-semibold uppercase tracking-wide border-b border-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-left">Santri</th>
                    <th class="px-4 py-3 text-left">Keluhan</th>
                    <th class="px-4 py-3 text-left">TTV Kunci</th>
                    <th class="px-4 py-3 text-left">Diagnosa</th>
                    <th class="px-4 py-3 text-center">Prioritas</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Dokter</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$res_riwayat || $res_riwayat->num_rows === 0): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Belum ada riwayat kunjungan.</td></tr>
                <?php else: while ($rv = $res_riwayat->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 whitespace-nowrap"><?= tgl_indo($rv['tanggal_berobat'], false); ?></td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-[#0B3D2E]"><?= htmlspecialchars($rv['nama_lengkap']); ?></div>
                        <div class="text-gray-400 font-mono"><?= htmlspecialchars($rv['nis']); ?></div>
                    </td>
                    <td class="px-4 py-3 max-w-[160px]">
                        <p class="truncate text-gray-700"><?= htmlspecialchars($rv['keluhan_utama']); ?></p>
                    </td>
                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                        <?php if ($rv['suhu_tubuh']): ?>
                        <span class="<?= $rv['suhu_tubuh'] >= 39 ? 'text-red-600 font-bold' : 'text-gray-600'; ?>">🌡<?= number_format($rv['suhu_tubuh'],1); ?>°C</span>
                        <?php endif; ?>
                        <?php if ($rv['saturasi_oksigen']): ?>
                        <span class="ml-1 <?= $rv['saturasi_oksigen'] < 95 ? 'text-red-600 font-bold' : 'text-gray-600'; ?>">💓<?= $rv['saturasi_oksigen']; ?>%</span>
                        <?php endif; ?>
                        <?php if (!$rv['suhu_tubuh'] && !$rv['saturasi_oksigen']): ?><span class="text-gray-400">—</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 max-w-[160px]">
                        <p class="truncate font-medium"><?= htmlspecialchars($rv['diagnosa_dokter'] ?? '—'); ?></p>
                        <?php if (!empty($rv['rekomendasi_rawat'])): ?>
                        <span class="text-[10px] text-gray-500"><?= htmlspecialchars($rv['rekomendasi_rawat']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?= badge_triase($rv['tingkat_prioritas'], $rv['skor_saw_akhir']); ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $st_class = [
                            'selesai'      => 'bg-emerald-100 text-emerald-800',
                            'rawat_jalan'  => 'bg-blue-100 text-blue-800',
                            'rawat_inap'   => 'bg-purple-100 text-purple-800',
                            'rujuk_faskes' => 'bg-red-100 text-red-800',
                        ][$rv['status_antrean']] ?? 'bg-gray-100 text-gray-600';
                        $st_label = [
                            'selesai'      => 'Selesai',
                            'rawat_jalan'  => 'Rawat Jalan',
                            'rawat_inap'   => 'Rawat Inap',
                            'rujuk_faskes' => 'Rujuk RS',
                        ][$rv['status_antrean']] ?? ucfirst($rv['status_antrean']);
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $st_class; ?>"><?= $st_label; ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($rv['nama_dokter'] ?? '-'); ?></td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleDetail(id) {
    const el   = document.getElementById(id);
    const icon = document.getElementById('icon_' + id);
    const open = !el.classList.contains('hidden');
    el.classList.toggle('hidden');
    if (icon) icon.style.transform = open ? '' : 'rotate(180deg)';
}

// Auto-buka pasien darurat P1
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[id^="detail_"]').forEach(el => {
        const row  = el.previousElementSibling;
        const badge = row ? row.querySelector('.bg-red-500') : null;
        if (badge) {
            el.classList.remove('hidden');
            const icon = document.getElementById('icon_' + el.id);
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>

