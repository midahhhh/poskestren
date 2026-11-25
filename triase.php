<?php
// triase.php - Form Skrining, Mesin Kalkulasi SAW, dan Antrean Triase Klinis
$page_title = "Skrining & Triase Medis SAW";
$page_desc  = "Pemeriksaan awal tanda vital santri dan kalkulasi prioritas antrean berbasis Simple Additive Weighting (SAW).";
require_once 'header.php';

// Hak akses: Admin & Petugas Kesehatan
if (!in_array($user_role, ['admin', 'petugas_kesehatan'])) {
    set_flash('error', 'Akses Ditolak: Halaman triase khusus tenaga medis/kesehatan.'); header("Location: dashboard.php");
    exit();
}

$err = "";
$santri_dipilih = (int)($_GET['santri_id'] ?? 0);

// PROSES TAMBAH KUNJUNGAN & SKRINING TRIASE BARU
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'simpan_triase') {
    $santri_id     = (int)$_POST['santri_id'];
    $keluhan_utama = trim($_POST['keluhan_utama']);
    $is_override   = isset($_POST['emergency_override']) && $_POST['emergency_override'] == '1';

    // Ambil nilai crisp kriteria 1 - 5
    $c1_sub = (int)$_POST['kriteria_c1'];
    $c2_sub = (int)$_POST['kriteria_c2'];
    $c3_sub = (int)$_POST['kriteria_c3'];
    $c4_sub = (int)$_POST['kriteria_c4'];
    $c5_sub = (int)$_POST['kriteria_c5'];

    if ($santri_id <= 0 || empty($keluhan_utama)) {
        $err = "Silakan pilih santri dan isi keluhan utama santri!";
    } else {
        // Generate Nomor Antrean Baru
        $tgl_today = date('Y-m-d');
        $q_count = $conn->query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) = '$tgl_today'")->fetch_assoc()['c'];
        $no_antrean = "ANT-" . str_pad($q_count + 1, 3, '0', STR_PAD_LEFT);

        $prioritas_awal = $is_override ? 'P1_Gawat_Darurat' : 'P3_Biasa';
        $override_int   = $is_override ? 1 : 0;

        // Simpan Kunjungan Sakit dengan status emergency override yang valid
        $stmt_k = $conn->prepare("INSERT INTO kunjungan_sakit (no_antrean, santri_id, keluhan_utama, status_antrean, skor_saw_akhir, tingkat_prioritas, is_emergency_override) VALUES (?, ?, ?, 'menunggu_triase', 0.0000, ?, ?)");
        $stmt_k->bind_param("sissi", $no_antrean, $santri_id, $keluhan_utama, $prioritas_awal, $override_int);
        
        if ($stmt_k->execute()) {
            $kunjungan_id = $conn->insert_id;

            // Simpan detail nilai pemeriksaan saw
            $arr_subs = [1 => $c1_sub, 2 => $c2_sub, 3 => $c3_sub, 4 => $c4_sub, 5 => $c5_sub];
            $stmt_np = $conn->prepare("INSERT INTO saw_nilai_pemeriksaan (kunjungan_id, kriteria_id, sub_kriteria_id, nilai_raw, nilai_normalisasi, nilai_terbobot) VALUES (?, ?, ?, ?, 0.0000, 0.0000)");

            foreach ($arr_subs as $crit_id => $sub_id) {
                // Ambil nilai crisp dari sub kriteria
                $crisp_val = $conn->query("SELECT nilai_crisp FROM saw_sub_kriteria WHERE id = $sub_id")->fetch_assoc()['nilai_crisp'] ?? 1;
                $stmt_np->bind_param("iiid", $kunjungan_id, $crit_id, $sub_id, $crisp_val);
                $stmt_np->execute();
            }

            // Jalankan Kalkulasi Ulang SAW untuk seluruh antrean aktif
            jalankan_kalkulasi_saw($conn);

            set_flash('success', "Skrining triase untuk no antrean {$no_antrean} berhasil disimpan dan dikalkulasi.");
            header("Location: triase.php");
            exit();
        } else {
            $err = "Gagal menyimpan pemeriksaan triase: " . $conn->error;
        }
    }
}

// PROSES TRIGGER HITUNG ULANG SAW MANUAL
if (isset($_GET['recalculate'])) {
    jalankan_kalkulasi_saw($conn);
    set_flash('success', "Kalkulasi matriks SAW berhasil diperbarui secara real-time.");
    header("Location: triase.php");
    exit();
}

// FUNGSI INTI KALKULASI SAW (Simple Additive Weighting)
if (!function_exists('jalankan_kalkulasi_saw')) {
    function jalankan_kalkulasi_saw($conn) {
    // 1. Ambil seluruh kunjungan yang sedang menunggu triase / pemeriksaan
    $res_antrean = $conn->query("SELECT id, is_emergency_override FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase', 'pemeriksaan')");
    $antrean_ids = [];
    $is_override_map = [];
    while ($r = $res_antrean->fetch_assoc()) {
        $antrean_ids[] = (int)$r['id'];
        $is_override_map[$r['id']] = ((int)($r['is_emergency_override'] ?? 0) === 1);
    }

    if (empty($antrean_ids)) return;

    $id_list_str = implode(',', $antrean_ids);

    // 2. Ambil nilai raw untuk setiap alternatif dan kriteria
    $res_vals = $conn->query("SELECT kunjungan_id, kriteria_id, nilai_raw FROM saw_nilai_pemeriksaan WHERE kunjungan_id IN ($id_list_str)");
    $matriks_x = [];
    while ($v = $res_vals->fetch_assoc()) {
        $kid = (int)$v['kunjungan_id'];
        $crit = (int)$v['kriteria_id'];
        $matriks_x[$kid][$crit] = (float)$v['nilai_raw'];
    }

    // Ambil nilai skala crisp maksimum dari saw_sub_kriteria (skala standar triase 1–5)
    $res_max = $conn->query("SELECT kriteria_id, MAX(nilai_crisp) as max_c FROM saw_sub_kriteria GROUP BY kriteria_id");
    $max_kriteria = [];
    while ($mx = $res_max->fetch_assoc()) {
        $max_kriteria[(int)$mx['kriteria_id']] = (float)$mx['max_c'];
    }
    // Fallback jika belum ada data sub kriteria
    for ($c = 1; $c <= 5; $c++) {
        if (!isset($max_kriteria[$c]) || $max_kriteria[$c] <= 0) {
            $max_kriteria[$c] = 5.0;
        }
    }

    // 3. Ambil bobot kriteria W dari database saw_kriteria
    $res_w = $conn->query("SELECT id, bobot_persen FROM saw_kriteria");
    $bobot_w = [];
    while ($w = $res_w->fetch_assoc()) {
        $bobot_w[(int)$w['id']] = ((float)$w['bobot_persen']) / 100.0;
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
            $stmt_u->bind_param("ddii", $r_ij, $terbobot, $kid, $c);
            $stmt_u->execute();
        }

        // Tentukan Tingkat Prioritas berdasarkan Skor SAW atau Emergency Override
        if (!empty($is_override_map[$kid])) {
            $prioritas = 'P1_Gawat_Darurat';
            // Pastikan skor minimal 1.0000 untuk emergency override agar selalu di posisi teratas
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

        // Simpan skor SAW akhir dan tingkat prioritas ke tabel kunjungan_sakit
        $stmt_k_up = $conn->prepare("UPDATE kunjungan_sakit SET skor_saw_akhir = ?, tingkat_prioritas = ? WHERE id = ?");
        $stmt_k_up->bind_param("dsi", $skor_akhir, $prioritas, $kid);
        $stmt_k_up->execute();
    }
}
}

// AMBIL DATA UNTUK FORM INPUT
$santri_list = $conn->query("SELECT id, nis, nama_lengkap, kamar_asrama FROM santri ORDER BY nama_lengkap ASC");
$sub_kriteria_c1 = $conn->query("SELECT * FROM saw_sub_kriteria WHERE kriteria_id = 1 ORDER BY nilai_crisp DESC");
$sub_kriteria_c2 = $conn->query("SELECT * FROM saw_sub_kriteria WHERE kriteria_id = 2 ORDER BY nilai_crisp DESC");
$sub_kriteria_c3 = $conn->query("SELECT * FROM saw_sub_kriteria WHERE kriteria_id = 3 ORDER BY nilai_crisp DESC");
$sub_kriteria_c4 = $conn->query("SELECT * FROM saw_sub_kriteria WHERE kriteria_id = 4 ORDER BY nilai_crisp DESC");
$sub_kriteria_c5 = $conn->query("SELECT * FROM saw_sub_kriteria WHERE kriteria_id = 5 ORDER BY nilai_crisp DESC");

// AMBIL HASIL KALKULASI UNTUK TABEL JEJAK SAW & RANKING
$sql_hasil = "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama 
              FROM kunjungan_sakit k 
              JOIN santri s ON k.santri_id = s.id 
              WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan') 
              ORDER BY 
                  CASE WHEN k.tingkat_prioritas = 'P1_Gawat_Darurat' THEN 1 ELSE 2 END,
                  k.skor_saw_akhir DESC, 
                  k.id ASC";
$res_hasil_saw = $conn->query($sql_hasil);
?>

<?php if (!empty($err)): ?>
    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
        <?= htmlspecialchars($err); ?>
    </div>
<?php endif; ?>

<!-- GRID UTAMA: Form Input Skrining (Kiri) & Antrean SAW Terurut (Kanan) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- KOLOM KIRI: FORM SKRINING TRIASE SANTRI (5 Cols) -->
    <div class="lg:col-span-5 space-y-6">
        <div class="card p-5">
            <div class="pb-3 border-b border-[#DCE7E0] mb-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#16845B] block">Formulir Skrining Klinis</span>
                <h3 class="font-extrabold text-[#0B3D2E] text-base">Input Pemeriksaan Triase Santri</h3>
                <p class="text-xs text-[#64756D]">Sistem akan memetakan parameter ke skala crisp dan menghitung skor SAW.</p>
            </div>

            <form method="POST" action="triase.php" class="space-y-4">
                <input type="hidden" name="action" value="simpan_triase">

                <!-- Pilih Santri -->
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Pilih Santri yang Sakit</label>
                    <select name="santri_id" required class="input-field">
                        <option value="">-- Pilih Santri Berdasarkan Nama / NIS --</option>
                        <?php while ($st = $santri_list->fetch_assoc()): ?>
                            <option value="<?= $st['id']; ?>" <?= $santri_dipilih == $st['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($st['nama_lengkap']); ?> (NIS: <?= $st['nis']; ?> - <?= $st['kamar_asrama']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Keluhan Utama -->
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Keluhan Utama & Observasi Awal</label>
                    <textarea name="keluhan_utama" required rows="2" placeholder="Contoh: Demam menggigil sejak sore, mengeluh pusing dan sesak napas..."
                              class="input-field"></textarea>
                </div>

                <!-- Accordion 5 Kriteria SAW -->
                <div class="space-y-2.5 pt-2 border-t border-[#DCE7E0]">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-[#0B3D2E] block">Parameter Kriteria SAW (C1–C5):</span>

                    <!-- C1 Suhu Tubuh -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#182923] mb-1">C1: Suhu Tubuh (Bobot 30%)</label>
                        <select name="kriteria_c1" required class="input-field">
                            <?php while ($c1 = $sub_kriteria_c1->fetch_assoc()): ?>
                                <option value="<?= $c1['id']; ?>"><?= htmlspecialchars($c1['deskripsi']); ?> (Crisp: <?= $c1['nilai_crisp']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- C2 Skala Nyeri -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#182923] mb-1">C2: Skala Nyeri / Keparahan (Bobot 25%)</label>
                        <select name="kriteria_c2" required class="input-field">
                            <?php while ($c2 = $sub_kriteria_c2->fetch_assoc()): ?>
                                <option value="<?= $c2['id']; ?>"><?= htmlspecialchars($c2['deskripsi']); ?> (Crisp: <?= $c2['nilai_crisp']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- C3 Riwayat Komorbid -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#182923] mb-1">C3: Riwayat Komorbid / Alergi (Bobot 20%)</label>
                        <select name="kriteria_c3" required class="input-field">
                            <?php while ($c3 = $sub_kriteria_c3->fetch_assoc()): ?>
                                <option value="<?= $c3['id']; ?>"><?= htmlspecialchars($c3['deskripsi']); ?> (Crisp: <?= $c3['nilai_crisp']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- C4 Durasi Sakit -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#182923] mb-1">C4: Durasi Sakit Berlangsung (Bobot 15%)</label>
                        <select name="kriteria_c4" required class="input-field">
                            <?php while ($c4 = $sub_kriteria_c4->fetch_assoc()): ?>
                                <option value="<?= $c4['id']; ?>"><?= htmlspecialchars($c4['deskripsi']); ?> (Crisp: <?= $c4['nilai_crisp']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- C5 Kesadaran & TTV -->
                    <div>
                        <label class="block text-[11px] font-bold text-[#182923] mb-1">C5: Status Kesadaran & Tanda Vital (Bobot 10%)</label>
                        <select name="kriteria_c5" required class="input-field">
                            <?php while ($c5 = $sub_kriteria_c5->fetch_assoc()): ?>
                                <option value="<?= $c5['id']; ?>"><?= htmlspecialchars($c5['deskripsi']); ?> (Crisp: <?= $c5['nilai_crisp']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- EMERGENCY OVERRIDE TOGGLE (BEBAS HAMBATAN) -->
                <div class="p-3.5 rounded-lg border border-red-200 bg-red-50/60 flex items-start gap-3">
                    <input type="checkbox" name="emergency_override" value="1" id="chkOverride" 
                           class="w-4 h-4 mt-0.5 text-red-600 rounded border-gray-300 focus:ring-red-500">
                    <label for="chkOverride" class="text-xs">
                        <span class="font-bold text-[#D64545] block uppercase text-[11px] tracking-wide">Emergency Override (Bypass SAW)</span>
                        <span class="text-[#64756D] text-[11px] leading-tight block">
                            Centang bila santri dalam kondisi kritis yang memerlukan tindakan darurat instan tanpa menunggu antrean ranking.
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-2.5 rounded-lg bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold text-xs transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Simpan & Kalkulasi Antrean SAW</span>
                </button>
            </form>
        </div>
    </div>

    <!-- KOLOM KANAN: ANTREN HASIL KALKULASI & JEJAK MATRIKS (7 Cols) -->
    <div class="lg:col-span-7 space-y-6">
        
        <!-- Kartu Antrean Ranking Triase Live -->
        <div class="card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#DCE7E0] gap-3">
                <div>
                    <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base">Hasil Perankingan Antrean Triase</h3>
                    <p class="text-xs text-[#64756D]">Urutan otomatis teratas mendapatkan penanganan medis pertama.</p>
                </div>
                <a href="triase.php?recalculate=1" class="px-3 py-1.5 rounded-xl border border-[#DCE7E0] hover:bg-slate-50 text-xs font-bold text-[#0B3D2E] transition flex items-center gap-1.5 self-start">
                    <svg class="w-3.5 h-3.5 text-[#16845B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Hitung Ulang SAW</span>
                </a>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-[#f0f4f2] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                            <th class="py-2.5 px-3 text-center">Rank</th>
                            <th class="py-2.5 px-3">Identitas Santri</th>
                            <th class="py-2.5 px-3">Keluhan</th>
                            <th class="py-2.5 px-3 text-center">Status Triase</th>
                            <th class="py-2.5 px-3 text-center">Skor V</th>
                            <th class="py-2.5 px-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#DCE7E0]">
                        <?php 
                        if ($res_hasil_saw && $res_hasil_saw->num_rows > 0):
                            $pos = 1;
                            while ($h = $res_hasil_saw->fetch_assoc()):
                                $is_top = ($pos === 1);
                                $is_darurat = ($h['tingkat_prioritas'] === 'P1_Gawat_Darurat');
                        ?>
                        <tr class="hover:bg-slate-50 transition <?= $is_darurat ? 'bg-red-50/30' : ''; ?>">
                            <td class="py-3 px-3 text-center">
                                <span class="w-6 h-6 rounded-full inline-flex items-center justify-center font-bold text-xs <?= $is_top ? 'bg-[#D64545] text-white shadow-xs' : 'bg-slate-100 text-[#0B3D2E]'; ?>">
                                    #<?= $pos++; ?>
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-bold text-[#182923] block text-xs"><?= htmlspecialchars($h['nama_lengkap']); ?></span>
                                <span class="text-[10px] text-[#64756D] font-mono"><?= htmlspecialchars($h['no_antrean']); ?> • <?= htmlspecialchars($h['kamar_asrama']); ?></span>
                            </td>
                            <td class="py-3 px-3 max-w-[180px]">
                                <span class="text-[11px] text-[#182923] line-clamp-2"><?= htmlspecialchars($h['keluhan_utama']); ?></span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <?= badge_triase($h['tingkat_prioritas'], $h['skor_saw_akhir']); ?>
                            </td>
                            <td class="py-3 px-3 text-center font-black text-sm <?= $h['skor_saw_akhir'] >= 0.80 ? 'text-[#D64545]' : 'text-[#16845B]'; ?>">
                                <?= number_format($h['skor_saw_akhir'], 3); ?>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?php $rm_target = ($user_role === 'admin') ? "rekam_medis_admin.php?detail_id={$h['id']}" : "rekam_medis.php?kunjungan_id={$h['id']}"; ?>
                                <a href="<?= $rm_target; ?>" class="px-2.5 py-1.5 rounded-lg bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold text-[10px] transition shadow-2xs">
                                    <?= ($user_role === 'admin') ? 'Rekam Medis →' : 'Pemeriksaan →'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="py-6 text-center text-[#64756D]">
                                Tidak ada antrean santri aktif yang menunggu.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu Jejak Transparansi Perhitungan Matematis SAW -->
        <div class="card p-5">
            <div class="pb-3 border-b border-[#DCE7E0] mb-3">
                <h4 class="font-bold text-[#0B3D2E] text-xs uppercase tracking-wider">Jejak Rumus & Matriks Normalisasi SAW</h4>
                <p class="text-[11px] text-[#64756D] mt-0.5">Rumus: $R_{ij} = \frac{X_{ij}}{\max(X_j)}$ dan Skor Preferensi $V_i = \sum (W_j \times R_{ij})$.</p>
            </div>

            <div class="p-3 bg-[#f0f4f2] rounded-xl text-[11px] text-[#182923] space-y-2 border border-[#DCE7E0]">
                <div class="flex items-center justify-between border-b border-[#DCE7E0]/60 pb-1.5">
                    <span class="font-bold text-[#0B3D2E]">Matriks Keputusan (X):</span>
                    <span class="text-[#64756D]">Nilai Crisp 1–5 dari pemeriksaan C1–C5</span>
                </div>
                <div class="flex items-center justify-between border-b border-[#DCE7E0]/60 pb-1.5">
                    <span class="font-bold text-[#0B3D2E]">Matriks Ternormalisasi (R):</span>
                    <span class="text-[#64756D]">Nilai relatif terhadap pasien dengan kondisi tertinggi</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-bold text-[#0B3D2E]">Nilai Preferensi (V):</span>
                    <span class="font-mono text-[#16845B] font-extrabold">V = (0.30×R1) + (0.25×R2) + (0.20×R3) + (0.15×R4) + (0.10×R5)</span>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once 'footer.php'; ?>


