<?php
// portal.php - Portal Khusus Santri & Wali Santri Poskestren Al-Bahjah
$page_title = "Portal Pemantauan Ananda";
$page_desc  = "Pantau status antrean triase, perkembangan rawat santri, resep obat, dan telepon otomatis ke WhatsApp Nakes.";
require_once 'header.php';

// HELPER: Link Telepon Otomatis ke WhatsApp Poskestren Al-Bahjah
function build_portal_wa_url($nama, $nis, $kamar, $keluhan, $prioritas, $skor, $status) {
    $prioritas_txt = str_replace('_', ' ', $prioritas);
    $status_txt = str_replace('_', ' ', $status);
    $skor_txt = number_format((float)$skor, 3);

    $pesan = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
           . "Yth. Petugas Siaga Medis Poskestren Al-Bahjah Bogor,\n\n"
           . "Saya adalah orang tua / wali dari ananda:\n"
           . "• Nama Santri : " . $nama . "\n"
           . "• NIS : " . $nis . "\n"
           . "• Asrama : " . $kamar . "\n\n"
           . "Berdasarkan pemantauan pada Portal Poskestren, ananda saat ini sedang dalam penanganan sakit:\n"
           . "• Keluhan Utama : " . $keluhan . "\n"
           . "• Tingkat Triase : " . $prioritas_txt . " (Skor SAW: " . $skor_txt . ")\n"
           . "• Status Penanganan : " . $status_txt . "\n\n"
           . "Mohon informasi perkembangan kondisi ananda terkini dan apakah kami diperkenankan untuk melakukan panggilan telepon / video call dengan ananda. Terima kasih banyak atas dedikasi dan perawatannya.\n\n"
           . "Wassalamu'alaikum Wr. Wb.";

    return "https://api.whatsapp.com/send?phone=6281122334455&text=" . rawurlencode($pesan);
}

// Identifikasi santri yang berhak diakses
$santri_id_aktif = null;
$nama_santri = "";
$santri_data = null;
$wali_santri_ids = [];

if ($user_role === 'santri_wali') {
    // Jika login sebagai wali, cari seluruh santri yang ditautkan ke akun ini
    $u_id = $_SESSION['user_id'] ?? 0;
    $stmt_sw = $conn->prepare("SELECT * FROM santri WHERE user_id_wali = ? OR nis = ?");
    $stmt_sw->bind_param("is", $u_id, $_SESSION['username']);
    $stmt_sw->execute();
    $res_sw = $stmt_sw->get_result();
    while ($row = $res_sw->fetch_assoc()) {
        $wali_santri_ids[] = $row['id'];
        if (!$santri_data) {
            $santri_data = $row;
            $santri_id_aktif = $row['id'];
        }
    }
}

// Fitur Pencarian Cepat Berdasarkan NIS bagi Wali yang ingin memantau
$nis_cari = trim($_GET['nis_cari'] ?? '');
if (!empty($nis_cari)) {
    $stmt_c = $conn->prepare("SELECT * FROM santri WHERE nis = ? LIMIT 1");
    $stmt_c->bind_param("s", $nis_cari);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    if ($res_c->num_rows > 0) {
        $santri_data = $res_c->fetch_assoc();
        $santri_id_aktif = $santri_data['id'];
    }
}

// Jika belum ada santri terpilih, ambil santri pertama untuk display awal jika admin
if (!$santri_id_aktif && in_array($user_role, ['admin', 'petugas_kesehatan', 'petugas_farmasi'])) {
    $santri_data = $conn->query("SELECT * FROM santri ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($santri_data) $santri_id_aktif = $santri_data['id'];
}

// Ambil Kunjungan & Antrean Terkini Santri Terpilih
$antrean_aktif = null;
$riwayat_kunjungan = [];
if ($santri_id_aktif) {
    // Antrean aktif terbaru
    $stmt_a = $conn->prepare("SELECT * FROM kunjungan_sakit WHERE santri_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_a->bind_param("i", $santri_id_aktif);
    $stmt_a->execute();
    $antrean_aktif = $stmt_a->get_result()->fetch_assoc();

    // Riwayat seluruh kunjungan santri ini
    $stmt_r = $conn->prepare("SELECT k.*, pk.diagnosa_dokter, pk.rekomendasi_rawat, r.status_resep 
                              FROM kunjungan_sakit k 
                              LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id 
                              LEFT JOIN resep_obat r ON r.kunjungan_id = k.id 
                              WHERE k.santri_id = ? 
                              ORDER BY k.id DESC LIMIT 5");
    $stmt_r->bind_param("i", $santri_id_aktif);
    $stmt_r->execute();
    $res_r = $stmt_r->get_result();
    while ($row = $res_r->fetch_assoc()) {
        $riwayat_kunjungan[] = $row;
    }
}

// QUERY KHUSUS PANEL ANANDA YANG SEDANG SAKIT (HANYA BERISI ANANDA YANG SAKIT)
$daftar_semua_sakit = [];
$sql_sakit = "SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama, s.nama_wali, s.no_hp_wali,
                     pk.diagnosa_dokter, pk.rekomendasi_rawat, pk.suhu_tubuh, pk.tensi_darah, pk.keluhan_fisik,
                     r.status_resep
              FROM kunjungan_sakit k
              JOIN santri s ON s.id = k.santri_id
              LEFT JOIN pemeriksaan_klinis pk ON pk.kunjungan_id = k.id
              LEFT JOIN resep_obat r ON r.kunjungan_id = k.id
              WHERE k.status_antrean IN ('menunggu_triase', 'pemeriksaan', 'observasi', 'rawat_inap')
              ORDER BY CASE k.tingkat_prioritas 
                    WHEN 'P1_Gawat_Darurat' THEN 1 
                    WHEN 'P2_Mendesak' THEN 2 
                    ELSE 3 END ASC, k.skor_saw_akhir DESC, k.id DESC";
$res_semua_sakit = $conn->query($sql_sakit);
if ($res_semua_sakit) {
    while ($row_sk = $res_semua_sakit->fetch_assoc()) {
        $daftar_semua_sakit[] = $row_sk;
    }
}
?>

<!-- BANNER PORTAL RAMAH KELUARGA SANTRI -->
<div class="card-custom p-6 mb-6 bg-gradient-to-r from-emerald-900 to-[#0B3D2E] text-white border-0 shadow-lg relative overflow-hidden">
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-amber-400 text-slate-950 uppercase tracking-wider">
                    Panel Santri & Wali
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white animate-pulse">
                    <?= count($daftar_semua_sakit); ?> Ananda Sedang Sakit
                </span>
            </div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-white leading-tight">
                Layanan Informasi Kesehatan & Pemantauan Ananda
            </h2>
            <p class="text-xs text-emerald-200/90 max-w-xl mt-1 leading-relaxed">
                Pantau perkembangan medis santri secara transparan dan hubungi nakes siaga langsung melalui tombol Telepon WhatsApp otomatis.
            </p>
        </div>

        <!-- Form Cari NIS Santri -->
        <form method="GET" action="portal.php" class="flex items-center gap-2 bg-white/10 backdrop-blur-md p-2 rounded-2xl border border-white/20">
            <input type="text" name="nis_cari" value="<?= htmlspecialchars($nis_cari); ?>" placeholder="Masukkan NIS Santri..."
                   class="px-3.5 py-2 rounded-xl bg-white text-xs font-mono font-bold text-[#182923] placeholder-slate-400 focus:outline-none w-44">
            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs transition">
                Cek Santri
            </button>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- SECTION UTAMA: PANEL ANANDA YANG SEDANG SAKIT SAAT INI -->
<!-- ============================================================== -->
<div class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-base sm:text-lg font-bold text-[#0B3D2E] flex items-center gap-2">
                <span>Panel Ananda dalam Penanganan Medis</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-900 border border-red-200">
                    <?= count($daftar_semua_sakit); ?> Pasien
                </span>
            </h3>
            <p class="text-xs text-[#64756D]">Hanya memuat ananda yang tercatat aktif dalam antrean triase medis atau rawat inap poskestren.</p>
        </div>
        <a href="wali.php" class="text-xs font-bold text-[#16845B] hover:underline flex items-center gap-1">
            <span>&larr; Kembali ke Portal Wali</span>
        </a>
    </div>

    <?php if (!empty($daftar_semua_sakit)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($daftar_semua_sakit as $ps): ?>
            <div class="card-custom p-5 border-l-4 <?= $ps['tingkat_prioritas'] === 'P1_Gawat_Darurat' ? 'border-l-red-500' : ($ps['tingkat_prioritas'] === 'P2_Mendesak' ? 'border-l-amber-500' : 'border-l-emerald-500'); ?> flex flex-col justify-between">
                
                <div>
                    <!-- Header Kartu -->
                    <div class="flex items-start justify-between gap-3 pb-3 border-b border-[#DCE7E0]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded bg-[#0B3D2E] text-white font-bold text-sm flex items-center justify-center flex-shrink-0">
                                <?= substr($ps['nama_lengkap'], 0, 1); ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-[#0B3D2E] leading-tight"><?= htmlspecialchars($ps['nama_lengkap']); ?></h4>
                                <span class="font-mono text-[11px] font-bold text-[#16845B]">NIS: <?= $ps['nis']; ?></span>
                                <span class="text-[11px] text-[#64756D] ml-1.5">• Asrama: <?= $ps['kamar_asrama']; ?></span>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <?= badge_triase($ps['tingkat_prioritas'], $ps['skor_saw_akhir']); ?>
                        </div>
                    </div>

                    <!-- Ringkasan Keluhan & Tanda Vital -->
                    <div class="py-3 space-y-2 text-xs">
                        <div class="bg-[#F5F8F6] p-2.5 rounded border border-[#DCE7E0]">
                            <span class="text-[10px] uppercase font-bold text-[#64756D] block">Keluhan yang Dilaporkan:</span>
                            <span class="font-semibold text-[#182923]"><?= htmlspecialchars($ps['keluhan_utama']); ?></span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-[#64756D] px-1">
                            <span>Suhu: <strong class="text-rose-700"><?= $ps['suhu_tubuh'] ? $ps['suhu_tubuh'] . ' °C' : 'Dalam pantauan'; ?></strong></span>
                            <span>Status: <strong class="uppercase text-blue-700"><?= str_replace('_', ' ', $ps['status_antrean']); ?></strong></span>
                            <span>Waktu: <strong><?= tgl_indo($ps['tanggal_berobat'], false); ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- TOMBOL TELEPON OTOMATIS KE WHATSAPP -->
                <div class="pt-3 border-t border-[#DCE7E0] flex items-center justify-between gap-2">
                    <a href="tel:081122334455" class="px-3 py-2 rounded bg-slate-100 hover:bg-slate-200 text-[#0B3D2E] font-bold text-xs transition" title="Panggilan Suara">
                        <span>Panggilan Medis</span>
                    </a>
                    <a href="<?= build_portal_wa_url($ps['nama_lengkap'], $ps['nis'], $ps['kamar_asrama'], $ps['keluhan_utama'], $ps['tingkat_prioritas'], $ps['skor_saw_akhir'], $ps['status_antrean']); ?>" 
                       target="_blank" 
                       class="flex-1 py-2 px-3 rounded bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span>Konfirmasi Perkembangan ke WA</span>
                    </a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card-custom p-6 text-center text-[#64756D]">
            <h4 class="font-bold text-sm text-[#0B3D2E]">Alhamdulillah, Tidak Ada Santri dalam Penanganan Darurat</h4>
            <p class="text-xs max-w-md mx-auto mt-1">
                Seluruh santri saat ini berada dalam kondisi sehat dan tidak ada yang sedang berada di antrean rawat atau triase poskestren.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if ($santri_data): ?>
    <!-- PROFIL SANTRI YANG SEDANG DITELUSURI -->
    <div class="card-custom p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-[#16845B] font-black text-2xl flex items-center justify-center border border-emerald-200">
                    <?= substr($santri_data['nama_lengkap'], 0, 1); ?>
                </div>
                <div>
                    <h3 class="font-extrabold text-lg text-[#0B3D2E] leading-tight"><?= htmlspecialchars($santri_data['nama_lengkap']); ?></h3>
                    <p class="text-xs text-[#64756D] mt-0.5">
                        NIS: <span class="font-mono font-bold text-[#16845B]"><?= $santri_data['nis']; ?></span> • Asrama: <?= $santri_data['kamar_asrama']; ?> (<?= $santri_data['kompleks_asrama']; ?>)
                    </p>
                    <p class="text-[11px] text-[#64756D]">
                        Wali Santri: <strong><?= htmlspecialchars($santri_data['nama_wali'] ?: 'Orang Tua / Wali'); ?></strong>
                    </p>
                </div>
            </div>

            <!-- Status Terkini -->
            <div class="text-left sm:text-right">
                <span class="text-[10px] uppercase font-bold text-[#64756D] block">Status Penanganan Saat Ini:</span>
                <?php if ($antrean_aktif): ?>
                    <div class="mt-1">
                        <?= badge_triase($antrean_aktif['tingkat_prioritas'], $antrean_aktif['skor_saw_akhir']); ?>
                    </div>
                <?php else: ?>
                    <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        Alhamdulillah Sehat / Bebas Rawat
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- STATUS KUNJUNGAN AKTIF TERBARU DENGAN TOMBOL TELEPON WA -->
    <?php if ($antrean_aktif): ?>
        <div class="card-custom p-6 mb-6 border-emerald-300 bg-white">
            <div class="flex items-center justify-between pb-3 border-b border-[#DCE7E0] mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-[#16845B] animate-pulse"></span>
                    <h3 class="font-extrabold text-[#0B3D2E] text-base">Pantauan Kunjungan Terkini</h3>
                </div>
                <span class="text-xs font-mono font-bold text-[#16845B] bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    No. Antrean: <?= $antrean_aktif['no_antrean']; ?>
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="p-3.5 rounded-xl bg-[#F5F8F6] border border-[#DCE7E0]">
                    <span class="text-[10px] uppercase font-bold text-[#64756D] block mb-1">Keluhan yang Dilaporkan</span>
                    <p class="text-xs font-semibold text-[#182923] leading-relaxed">
                        <?= htmlspecialchars($antrean_aktif['keluhan_utama']); ?>
                    </p>
                    <span class="text-[10px] text-[#64756D] block mt-2">Waktu lapor: <?= tgl_indo($antrean_aktif['tanggal_berobat']); ?></span>
                </div>

                <div class="p-3.5 rounded-xl bg-[#F5F8F6] border border-[#DCE7E0]">
                    <span class="text-[10px] uppercase font-bold text-[#64756D] block mb-1">Prioritas Triase SAW</span>
                    <div class="mt-1">
                        <?= badge_triase($antrean_aktif['tingkat_prioritas'], $antrean_aktif['skor_saw_akhir']); ?>
                    </div>
                    <p class="text-[11px] text-[#64756D] mt-2">
                        Skor prioritas klinis terhitung: <strong class="text-[#0B3D2E]"><?= number_format($antrean_aktif['skor_saw_akhir'], 3); ?></strong>. Petugas menangani secara terukur.
                    </p>
                </div>

                <div class="p-3.5 rounded-xl bg-[#F5F8F6] border border-[#DCE7E0]">
                    <span class="text-[10px] uppercase font-bold text-[#64756D] block mb-1">Status Alur Pelayanan</span>
                    <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase bg-blue-100 text-blue-800 border border-blue-200 mt-1">
                        <?= str_replace('_', ' ', $antrean_aktif['status_antrean']); ?>
                    </span>
                    <p class="text-[11px] text-[#64756D] mt-2">
                        Poskestren Al-Bahjah siap sedia 24 jam untuk pemulihan ananda.
                    </p>
                </div>
            </div>

            <!-- FITUR TELEPON / HUBUNGI OTOMATIS KE WA -->
            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-300 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#25D366] text-white flex items-center justify-center flex-shrink-0 shadow-sm">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-[#0B3D2E] text-xs">Ingin Bertanya Langsung atau Minta Telepon Ananda?</h4>
                        <p class="text-[11px] text-[#64756D]">Kirim pesan WhatsApp otomatis ke Nakes Siaga Poskestren Al-Bahjah Bogor.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <a href="tel:081122334455" class="px-3 py-2 rounded-xl bg-white border border-[#DCE7E0] hover:bg-slate-50 text-[#0B3D2E] font-bold text-xs transition">
                        📞 Telepon
                    </a>
                    <a href="<?= build_portal_wa_url($santri_data['nama_lengkap'], $santri_data['nis'], $santri_data['kamar_asrama'], $antrean_aktif['keluhan_utama'], $antrean_aktif['tingkat_prioritas'], $antrean_aktif['skor_saw_akhir'], $antrean_aktif['status_antrean']); ?>" 
                       target="_blank" 
                       class="flex-1 sm:flex-none px-4 py-2 rounded-xl bg-[#25D366] hover:bg-[#128C7E] text-white font-extrabold text-xs transition shadow-xs flex items-center justify-center gap-2">
                        <span>Telepon Otomatis ke WA</span>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- TABEL RIWAYAT KESEHATAN SANTRI PRIBADI -->
    <div class="card-custom p-6">
        <div class="pb-3 border-b border-[#DCE7E0] mb-4">
            <h3 class="font-extrabold text-[#0B3D2E] text-base">Riwayat Kunjungan Kesehatan & Obat Ananda</h3>
            <p class="text-xs text-[#64756D]">Rekam riwayat kunjungan medis santri di Poskestren Al-Bahjah Bogor.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-[#F5F8F6] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                        <th class="py-3 px-4">Tanggal Berobat</th>
                        <th class="py-3 px-4">Keluhan yang Dirasakan</th>
                        <th class="py-3 px-4">Diagnosis Nakes</th>
                        <th class="py-3 px-4 text-center">Tindakan / Rawat</th>
                        <th class="py-3 px-4 text-center">Resep Obat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                    <?php if (!empty($riwayat_kunjungan)): ?>
                        <?php foreach ($riwayat_kunjungan as $rk): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 font-mono text-[#64756D]"><?= tgl_indo($rk['tanggal_berobat']); ?></td>
                            <td class="py-3 px-4 font-medium"><?= htmlspecialchars($rk['keluhan_utama']); ?></td>
                            <td class="py-3 px-4 font-semibold text-[#0B3D2E]"><?= htmlspecialchars($rk['diagnosa_dokter'] ?: 'Dalam observasi'); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                    <?= htmlspecialchars($rk['rekomendasi_rawat'] ?: 'Rawat Jalan'); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                    <?= $rk['status_resep'] ? ucfirst(str_replace('_', ' ', $rk['status_resep'])) : 'Selesai'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-6 text-center text-[#64756D]">
                                Belum ada riwayat berobat untuk santri ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'footer.php'; ?>
