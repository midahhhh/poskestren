<?php
// kriteria_saw.php - Pengaturan Kriteria, Bobot, dan Skala Sub-Kriteria SAW
$page_title = "Konfigurasi Kriteria & Bobot SAW";
$page_desc  = "Pengaturan 5 kriteria klinis triase, bobot preferensi (W), dan skala konversi crisp sub-kriteria.";
require_once 'header.php';

// Hak Akses: Admin & Petugas Kesehatan
if (!in_array($user_role, ['admin', 'petugas_kesehatan'])) {
    set_flash('error', 'Akses Ditolak: Hanya Dokter atau Admin yang berwenang mengubah parameter SAW.'); header("Location: dashboard.php");
    exit();
}

$err = "";

// UPDATE BOBOT KRITERIA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_bobot') {
    $bobot_inputs = $_POST['bobot'] ?? [];
    $total_bobot = array_sum($bobot_inputs);

    // Validasi ketat total bobot wajib 100%
    if (abs($total_bobot - 100.0) > 0.01) {
        $err = "Total bobot persentase harus tepat 100.00%! Saat ini total bernilai " . number_format($total_bobot, 2) . "%.";
    } else {
        foreach ($bobot_inputs as $id_k => $b_val) {
            $id_k = (int)$id_k;
            $b_val = floatval($b_val);
            $stmt = $conn->prepare("UPDATE saw_kriteria SET bobot_persen = ? WHERE id = ?");
            $stmt->bind_param("di", $b_val, $id_k);
            $stmt->execute();
        }
        set_flash('success', "Bobot kriteria SAW berhasil diperbarui (Total 100.00%).");
        header("Location: kriteria_saw.php");
        exit();
    }
}

// TAMBAH / UPDATE SUB-KRITERIA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'tambah_sub') {
    $kriteria_id = (int)$_POST['kriteria_id'];
    $deskripsi   = trim($_POST['deskripsi']);
    $nilai_crisp = (int)$_POST['nilai_crisp'];

    if (!empty($deskripsi) && $nilai_crisp >= 1 && $nilai_crisp <= 5) {
        $stmt = $conn->prepare("INSERT INTO saw_sub_kriteria (kriteria_id, deskripsi, nilai_crisp) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $kriteria_id, $deskripsi, $nilai_crisp);
        $stmt->execute();
        set_flash('success', "Sub-kriteria baru berhasil ditambahkan.");
        header("Location: kriteria_saw.php");
        exit();
    }
}

// HAPUS SUB-KRITERIA
if (isset($_GET['hapus_sub_id'])) {
    $hapus_sub_id = (int)$_GET['hapus_sub_id'];
    $conn->query("DELETE FROM saw_sub_kriteria WHERE id = $hapus_sub_id");
    set_flash('success', "Sub-kriteria berhasil dihapus.");
    header("Location: kriteria_saw.php");
    exit();
}

// Ambil Kriteria SAW
$kriteria_list = [];
$res_k = $conn->query("SELECT * FROM saw_kriteria ORDER BY id ASC");
$total_bobot_db = 0;
while ($k = $res_k->fetch_assoc()) {
    $total_bobot_db += $k['bobot_persen'];
    $kriteria_list[] = $k;
}

// Ambil Sub-Kriteria per Kriteria
$sub_map = [];
$res_sub = $conn->query("SELECT * FROM saw_sub_kriteria ORDER BY kriteria_id ASC, nilai_crisp DESC");
while ($s = $res_sub->fetch_assoc()) {
    $sub_map[$s['kriteria_id']][] = $s;
}
?>

<?php if (!empty($err)): ?>
    <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <span><?= $err; ?></span>
    </div>
<?php endif; ?>

<!-- INFORMASI SOT METODOLOGI SAW -->
<div class="card p-4 mb-6 bg-white border border-[#DCE7E0]">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded bg-[#16845B] text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
            W
        </div>
        <div>
            <h3 class="font-bold text-[#0B3D2E] text-xs sm:text-sm">Standar Metodologi Simple Additive Weighting (SAW)</h3>
            <p class="text-xs text-[#64756D] mt-0.5 leading-relaxed">
                Metode SAW mencari penjumlahan terbobot dari rating kinerja alternatif. Seluruh kriteria triase klinis di bawah ini bertipe <strong>Benefit</strong> (semakin tinggi skor/suhu/nyeri, semakin darurat prioritas antrean santri). Total bobot persentase $\sum W_j = 100\%$.
            </p>
        </div>
    </div>
</div>

<!-- SECTION 1: FORM PENGATURAN BOBOT 5 KRITERIA -->
<div class="card p-5 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#DCE7E0] gap-3">
        <div>
            <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base">Pengaturan Bobot Kriteria (W1 – W5)</h3>
            <p class="text-xs text-[#64756D]">Pastikan penjumlahan seluruh bobot bernilai tepat 100.00%.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-[#64756D]">Total Bobot Saat Ini:</span>
            <span id="labelTotalBobot" class="px-3 py-1 rounded-full text-xs font-black <?= abs($total_bobot_db - 100.0) < 0.01 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-red-100 text-red-800 border border-red-300'; ?>">
                <?= number_format($total_bobot_db, 2); ?>%
            </span>
        </div>
    </div>

    <form method="POST" action="kriteria_saw.php" class="mt-4">
        <input type="hidden" name="action" value="update_bobot">

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-[#f0f4f2] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                        <th class="py-3 px-4">Kode</th>
                        <th class="py-3 px-4">Nama Kriteria Klinis</th>
                        <th class="py-3 px-4">Sifat Kriteria</th>
                        <th class="py-3 px-4">Keterangan Medis</th>
                        <th class="py-3 px-4 text-center w-36">Bobot (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php foreach ($kriteria_list as $k): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="py-3 px-4 font-mono font-bold text-[#16845B] text-sm"><?= htmlspecialchars($k['kode_kriteria']); ?></td>
                        <td class="py-3 px-4 font-bold text-[#0B3D2E]"><?= htmlspecialchars($k['nama_kriteria']); ?></td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase">
                                <?= $k['tipe_kriteria']; ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-[#64756D]"><?= htmlspecialchars($k['keterangan']); ?></td>
                        <td class="py-3 px-4 text-center">
                            <div class="inline-flex items-center gap-1">
                                <input type="number" step="0.5" min="0" max="100" name="bobot[<?= $k['id']; ?>]" value="<?= $k['bobot_persen']; ?>" 
                                       oninput="hitungUlangBobot()" class="input-bobot w-20 px-2 py-1.5 rounded-lg border border-[#DCE7E0] text-center font-bold text-xs bg-[#f0f4f2] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#16845B]">
                                <span class="font-bold text-[#64756D]">%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-[#DCE7E0] flex items-center justify-between">
            <span class="text-[11px] text-[#64756D]">* Bobot menentukan signifikansi setiap parameter klinis dalam kalkulasi SAW.</span>
            <button type="submit" id="btnSimpanBobot" class="px-5 py-2 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold text-xs shadow-xs transition">
                Simpan Perubahan Bobot
            </button>
        </div>
    </form>
</div>

<!-- SECTION 2: SKALA KONVERSI SUB-KRITERIA (CRISP 1–5) -->
<div class="card p-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-[#DCE7E0] gap-3">
        <div>
            <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base">Skala Konversi Sub-Kriteria (Nilai Crisp 1 – 5)</h3>
            <p class="text-xs text-[#64756D]">Pemetaan kondisi klinis santri ke dalam bobot angka matematis untuk normalisasi matriks SAW.</p>
        </div>
        <button type="button" onclick="document.getElementById('modalTambahSub').classList.remove('hidden')" 
                class="px-3.5 py-2 rounded-xl bg-[#0B3D2E] hover:bg-[#16845B] text-white font-bold text-xs transition flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Tambah Sub-Kriteria</span>
        </button>
    </div>

    <!-- Grid 5 Kriteria & Sub-Kriteria Masing-Masing -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-5">
        <?php foreach ($kriteria_list as $k): ?>
        <div class="p-4 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-[#DCE7E0] mb-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-[#16845B] text-white font-mono font-bold text-xs"><?= htmlspecialchars($k['kode_kriteria']); ?></span>
                        <h4 class="font-extrabold text-[#0B3D2E] text-xs"><?= htmlspecialchars($k['nama_kriteria']); ?></h4>
                    </div>
                    <span class="text-[11px] font-bold text-[#64756D]"><?= $k['bobot_persen']; ?>%</span>
                </div>

                <div class="space-y-2">
                    <?php 
                    $subs = $sub_map[$k['id']] ?? [];
                    if (!empty($subs)):
                        foreach ($subs as $s):
                            // Styling badge crisp
                            $crisp_badge = 'bg-slate-200 text-slate-800';
                            if ($s['nilai_crisp'] >= 5) $crisp_badge = 'bg-red-100 text-red-800 border-red-300 font-extrabold';
                            else if ($s['nilai_crisp'] >= 4) $crisp_badge = 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
                            else if ($s['nilai_crisp'] >= 3) $crisp_badge = 'bg-emerald-100 text-emerald-800 font-medium';
                    ?>
                    <div class="p-2 rounded-lg bg-white border border-[#DCE7E0] flex items-center justify-between text-xs hover:border-[#16845B] transition">
                        <span class="text-[#182923] text-[11px] leading-tight mr-2"><?= htmlspecialchars($s['deskripsi']); ?></span>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="px-2 py-0.5 rounded border text-[10px] <?= $crisp_badge; ?>">
                                Nilai: <?= $s['nilai_crisp']; ?>
                            </span>
                            <a href="kriteria_saw.php?hapus_sub_id=<?= $s['id']; ?>" onclick="return confirm('Hapus sub-kriteria ini?')" class="text-slate-400 hover:text-red-600 font-bold text-sm leading-none">&times;</a>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p class="text-[11px] text-[#64756D] italic">Belum ada sub-kriteria.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODAL TAMBAH SUB-KRITERIA -->
<div id="modalTambahSub" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card max-w-md w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-[#DCE7E0] mb-4">
            <h3 class="font-extrabold text-[#0B3D2E] text-base">Tambah Skala Sub-Kriteria</h3>
            <button type="button" onclick="document.getElementById('modalTambahSub').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="kriteria_saw.php" class="space-y-3.5">
            <input type="hidden" name="action" value="tambah_sub">

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Pilih Kriteria Induk</label>
                <select name="kriteria_id" required class="input-field">
                    <?php foreach ($kriteria_list as $k): ?>
                        <option value="<?= $k['id']; ?>"><?= $k['kode_kriteria']; ?> - <?= htmlspecialchars($k['nama_kriteria']); ?> (<?= $k['bobot_persen']; ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Deskripsi Kondisi Klinis / Rentang</label>
                <input type="text" name="deskripsi" required placeholder="Contoh: Demam Sangat Tinggi (> 39.5 °C)" class="input-field">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Nilai Bobot Crisp (Skala 1 – 5)</label>
                <select name="nilai_crisp" required class="input-field">
                    <option value="5">5 - Sangat Tinggi / Kritis (Emergency Prioritas)</option>
                    <option value="4">4 - Tinggi / Berat (Urgent)</option>
                    <option value="3">3 - Sedang / Menengah</option>
                    <option value="2">2 - Rendah / Ringan</option>
                    <option value="1">1 - Sangat Rendah / Kondisi Normal</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
                <button type="button" onclick="document.getElementById('modalTambahSub').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-[#DCE7E0] text-xs font-bold text-[#64756D] hover:bg-slate-100">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition">Simpan Sub-Kriteria</button>
            </div>
        </form>
    </div>
</div>

<script>
    function hitungUlangBobot() {
        const inputs = document.querySelectorAll('.input-bobot');
        let total = 0;
        inputs.forEach(inp => {
            total += parseFloat(inp.value) || 0;
        });

        const label = document.getElementById('labelTotalBobot');
        const btn = document.getElementById('btnSimpanBobot');
        label.innerText = total.toFixed(2) + '%';

        if (Math.abs(total - 100.0) < 0.01) {
            label.className = "px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300";
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            label.className = "px-3 py-1 rounded-full text-xs font-black bg-red-100 text-red-800 border border-red-300";
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
</script>

<?php require_once 'footer.php'; ?>


