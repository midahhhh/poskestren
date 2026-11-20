<?php
// santri.php - Master Data Santri Poskestren Al-Bahjah
$page_title = "Master Data Santri";
$page_desc  = "Pengelolaan data induk santri, kamar asrama, riwayat komorbid, dan kontak wali santri.";
require_once 'header.php';

// Otorisasi role: Admin & Petugas Kesehatan dapat mengelola
if (!in_array($user_role, ['admin', 'petugas_kesehatan', 'petugas_farmasi'])) {
    set_flash('error', 'Akses Ditolak.'); header("Location: dashboard.php");
    exit();
}

$msg = "";
$err = "";

// PROSES TAMBAH SANTRI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $nis             = trim($_POST['nis']);
    $nama_lengkap    = trim($_POST['nama_lengkap']);
    $jenis_kelamin   = $_POST['jenis_kelamin'];
    $kompleks_asrama = $_POST['kompleks_asrama'];
    $kamar_asrama    = trim($_POST['kamar_asrama']);
    $tanggal_lahir   = $_POST['tanggal_lahir'];
    $nama_wali       = trim($_POST['nama_wali']);
    $no_hp_wali      = trim($_POST['no_hp_wali']);

    if (empty($nis) || empty($nama_lengkap) || empty($kamar_asrama)) {
        $err = "NIS, Nama Lengkap, dan Kamar Asrama wajib diisi!";
    } else {
        $stmt_c = $conn->prepare("SELECT id FROM santri WHERE nis = ?");
        $stmt_c->bind_param("s", $nis);
        $stmt_c->execute();
        if ($stmt_c->get_result()->num_rows > 0) {
            $err = "Santri dengan NIS '{$nis}' sudah terdaftar!";
        } else {
            $stmt = $conn->prepare("INSERT INTO santri (nis, nama_lengkap, jenis_kelamin, kompleks_asrama, kamar_asrama, tanggal_lahir, nama_wali, no_hp_wali) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $nis, $nama_lengkap, $jenis_kelamin, $kompleks_asrama, $kamar_asrama, $tanggal_lahir, $nama_wali, $no_hp_wali);
            if ($stmt->execute()) {
                set_flash('success', "Santri {$nama_lengkap} (NIS: {$nis}) berhasil ditambahkan.");
                header("Location: santri.php");
                exit();
            } else {
                $err = "Gagal menyimpan data santri ke database.";
            }
        }
    }
}

// PROSES EDIT SANTRI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id              = (int)$_POST['santri_id'];
    $nama_lengkap    = trim($_POST['nama_lengkap']);
    $jenis_kelamin   = $_POST['jenis_kelamin'];
    $kompleks_asrama = $_POST['kompleks_asrama'];
    $kamar_asrama    = trim($_POST['kamar_asrama']);
    $tanggal_lahir   = $_POST['tanggal_lahir'];
    $nama_wali       = trim($_POST['nama_wali']);
    $no_hp_wali      = trim($_POST['no_hp_wali']);

    $stmt = $conn->prepare("UPDATE santri SET nama_lengkap = ?, jenis_kelamin = ?, kompleks_asrama = ?, kamar_asrama = ?, tanggal_lahir = ?, nama_wali = ?, no_hp_wali = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $nama_lengkap, $jenis_kelamin, $kompleks_asrama, $kamar_asrama, $tanggal_lahir, $nama_wali, $no_hp_wali, $id);
    if ($stmt->execute()) {
        set_flash('success', "Data santri berhasil diperbarui.");
        header("Location: santri.php");
        exit();
    } else {
        $err = "Gagal memperbarui data santri.";
    }
}

// PROSES HAPUS SANTRI
if (isset($_GET['hapus_id'])) {
    $hapus_id = (int)$_GET['hapus_id'];
    $stmt = $conn->prepare("DELETE FROM santri WHERE id = ?");
    $stmt->bind_param("i", $hapus_id);
    if ($stmt->execute()) {
        set_flash('success', "Data santri berhasil dihapus.");
    } else {
        set_flash('error', "Gagal menghapus santri (mungkin masih memiliki riwayat rekam medis).");
    }
    header("Location: santri.php");
    exit();
}

// FILTER & PENCARIAN
$keyword = trim($_GET['q'] ?? '');
$filter_kompleks = $_GET['kompleks'] ?? '';

$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM kunjungan_sakit k WHERE k.santri_id = s.id) as total_kunjungan,
        (SELECT MAX(tanggal_berobat) FROM kunjungan_sakit k WHERE k.santri_id = s.id) as kunjungan_terakhir
        FROM santri s WHERE 1=1";

if (!empty($keyword)) {
    $sql .= " AND (s.nama_lengkap LIKE '%" . $conn->real_escape_string($keyword) . "%' OR s.nis LIKE '%" . $conn->real_escape_string($keyword) . "%' OR s.kamar_asrama LIKE '%" . $conn->real_escape_string($keyword) . "%')";
}
if (!empty($filter_kompleks)) {
    $sql .= " AND s.kompleks_asrama = '" . $conn->real_escape_string($filter_kompleks) . "'";
}

$sql .= " ORDER BY s.nama_lengkap ASC";
$res_santri = $conn->query($sql);
?>

<?php if (!empty($err)): ?>
    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-900 text-xs font-medium">
        <?= $err; ?>
    </div>
<?php endif; ?>

<!-- HEADER AKSI: Pencarian, Filter & Tombol Tambah -->
<div class="card p-5 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Form Search & Filter -->
        <form method="GET" action="santri.php" class="flex flex-wrap items-center gap-2.5 flex-1">
            <div class="relative flex-1 min-w-[200px]">
                <input type="text" name="q" value="<?= htmlspecialchars($keyword); ?>" placeholder="Cari santri berdasarkan nama, NIS, atau kamar..."
                       class="w-full pl-9 pr-3.5 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-medium text-[#182923] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#16845B]">
                <svg class="w-4 h-4 text-[#64756D] absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select name="kompleks" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-[#DCE7E0] bg-[#f0f4f2] text-xs font-medium text-[#182923] focus:bg-white focus:outline-none">
                <option value="">Semua Asrama</option>
                <option value="Putra" <?= $filter_kompleks === 'Putra' ? 'selected' : ''; ?>>Asrama Putra</option>
                <option value="Putri" <?= $filter_kompleks === 'Putri' ? 'selected' : ''; ?>>Asrama Putri</option>
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-[#0B3D2E] text-white text-xs font-bold hover:bg-[#16845B] transition">
                Cari
            </button>
            <?php if (!empty($keyword) || !empty($filter_kompleks)): ?>
                <a href="santri.php" class="px-3 py-2 rounded-xl border border-[#DCE7E0] text-xs font-semibold text-[#64756D] hover:bg-slate-50 transition">
                    Reset
                </a>
            <?php endif; ?>
        </form>

        <!-- Tombol Tambah Santri Modal -->
        <div>
            <button type="button" onclick="document.getElementById('modalTambahSantri').classList.remove('hidden')" 
                    class="w-full md:w-auto px-4 py-2.5 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Santri Baru</span>
            </button>
        </div>
    </div>
</div>

<!-- TABEL DATA MASTER SANTRI -->
<div class="card overflow-hidden">
    <div class="p-4 sm:p-5 border-b border-[#DCE7E0] flex items-center justify-between">
        <div>
            <h3 class="font-extrabold text-[#0B3D2E] text-sm sm:text-base">Daftar Santri Terdaftar Poskestren</h3>
            <p class="text-xs text-[#64756D]">Total <?= $res_santri ? $res_santri->num_rows : 0; ?> santri ditemukan.</p>
        </div>
        <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
            Terverifikasi Pesantren
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-[#f0f4f2] text-[#0B3D2E] font-bold border-b border-[#DCE7E0]">
                    <th class="py-3 px-4">No.</th>
                    <th class="py-3 px-4">NIS Santri</th>
                    <th class="py-3 px-4">Nama Lengkap & Gender</th>
                    <th class="py-3 px-4">Asrama & Kamar</th>
                    <th class="py-3 px-4">Wali Santri & Kontak</th>
                    <th class="py-3 px-4 text-center">Riwayat Sakit</th>
                    <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#DCE7E0] text-[#182923]">
                <?php 
                if ($res_santri && $res_santri->num_rows > 0):
                    $no = 1;
                    while ($s = $res_santri->fetch_assoc()):
                        // Masking no hp wali untuk privasi UX
                        $hp_mask = !empty($s['no_hp_wali']) ? substr($s['no_hp_wali'], 0, 4) . '****' . substr($s['no_hp_wali'], -3) : '-';
                ?>
                <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-3.5 px-4 font-semibold text-[#64756D]"><?= $no++; ?></td>
                    <td class="py-3.5 px-4 font-mono font-bold text-[#16845B]"><?= htmlspecialchars($s['nis']); ?></td>
                    <td class="py-3.5 px-4">
                        <span class="font-bold text-sm text-[#0B3D2E] block"><?= htmlspecialchars($s['nama_lengkap']); ?></span>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold <?= $s['jenis_kelamin'] === 'L' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                                <?= $s['jenis_kelamin'] === 'L' ? 'Ikhwan (L)' : 'Akhwat (P)'; ?>
                            </span>
                            <span class="text-[10px] text-[#64756D]">Lahir: <?= date('d/m/Y', strtotime($s['tanggal_lahir'])); ?></span>
                        </div>
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="font-semibold block text-[#182923]"><?= htmlspecialchars($s['kamar_asrama']); ?></span>
                        <span class="text-[10px] font-medium text-[#64756D]">Kompleks <?= htmlspecialchars($s['kompleks_asrama']); ?></span>
                    </td>
                    <td class="py-3.5 px-4">
                        <span class="font-bold text-[#182923] block"><?= htmlspecialchars($s['nama_wali'] ?: '-'); ?></span>
                        <span class="text-[10px] text-[#64756D] font-mono">WA: <?= $hp_mask; ?></span>
                    </td>
                    <td class="py-3.5 px-4 text-center">
                        <span class="inline-block px-2.5 py-0.5 rounded-full font-bold text-[11px] <?= $s['total_kunjungan'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'; ?>">
                            <?= $s['total_kunjungan']; ?> kali
                        </span>
                        <?php if ($s['kunjungan_terakhir']): ?>
                            <span class="block text-[9px] text-[#64756D] mt-0.5"><?= date('d/m/y', strtotime($s['kunjungan_terakhir'])); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3.5 px-4 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <!-- Tombol Skrining Triase Cepat -->
                            <a href="triase.php?santri_id=<?= $s['id']; ?>" title="Buat Triase Baru" class="p-1.5 rounded-lg bg-emerald-50 text-[#16845B] hover:bg-[#16845B] hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </a>
                            <!-- Tombol Edit Modal -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($s)); ?>)" title="Ubah Data" class="p-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-[#0B3D2E] hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <!-- Tombol Hapus -->
                            <button type="button" onclick="konfirmasiHapus('Yakin ingin menghapus santri <?= addslashes($s['nama_lengkap']); ?>?', 'santri.php?hapus_id=<?= $s['id']; ?>')" title="Hapus Data" class="p-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="7" class="py-8 text-center text-[#64756D]">
                        Tidak ada data santri yang sesuai kriteria pencarian.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH SANTRI -->
<div id="modalTambahSantri" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card max-w-lg w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-[#DCE7E0] mb-4">
            <h3 class="font-extrabold text-[#0B3D2E] text-base">Tambah Data Santri Baru</h3>
            <button type="button" onclick="document.getElementById('modalTambahSantri').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="santri.php" class="space-y-3.5">
            <input type="hidden" name="action" value="tambah">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">NIS (Nomor Induk)</label>
                    <input type="text" name="nis" required placeholder="Contoh: 2024-AB-0410" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Jenis Kelamin</label>
                    <select name="jenis_kelamin" required class="input-field">
                        <option value="L">Laki-laki (Ikhwan)</option>
                        <option value="P">Perempuan (Akhwat)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Nama Lengkap Santri</label>
                <input type="text" name="nama_lengkap" required placeholder="Nama lengkap sesuai data pesantren" class="input-field">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Kompleks Asrama</label>
                    <select name="kompleks_asrama" required class="input-field">
                        <option value="Putra">Asrama Putra</option>
                        <option value="Putri">Asrama Putri</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Kamar Asrama</label>
                    <input type="text" name="kamar_asrama" required placeholder="Contoh: Kamar 104 (Hamzah)" class="input-field">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" required value="2008-01-01" class="input-field">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-[#DCE7E0]">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Nama Wali Santri</label>
                    <input type="text" name="nama_wali" placeholder="Nama ayah/ibu/wali" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Nomor WhatsApp Wali</label>
                    <input type="text" name="no_hp_wali" placeholder="08xxxxxxxxxx" class="input-field">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
                <button type="button" onclick="document.getElementById('modalTambahSantri').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-[#DCE7E0] text-xs font-bold text-[#64756D] hover:bg-slate-100">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#16845B] hover:bg-[#0B3D2E] text-white text-xs font-bold transition">Simpan Santri</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT SANTRI -->
<div id="modalEditSantri" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card max-w-lg w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-[#DCE7E0] mb-4">
            <h3 class="font-extrabold text-[#0B3D2E] text-base">Ubah Data Santri</h3>
            <button type="button" onclick="document.getElementById('modalEditSantri').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
        </div>

        <form method="POST" action="santri.php" class="space-y-3.5">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="santri_id" id="edit_santri_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">NIS (Tidak Dapat Diubah)</label>
                    <input type="text" id="edit_nis" readonly class="w-full px-3 py-2 rounded-xl border border-[#DCE7E0] bg-slate-100 text-xs font-mono font-bold text-slate-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="edit_jk" required class="input-field">
                        <option value="L">Laki-laki (Ikhwan)</option>
                        <option value="P">Perempuan (Akhwat)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Nama Lengkap Santri</label>
                <input type="text" name="nama_lengkap" id="edit_nama" required class="input-field">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Kompleks Asrama</label>
                    <select name="kompleks_asrama" id="edit_kompleks" required class="input-field">
                        <option value="Putra">Asrama Putra</option>
                        <option value="Putri">Asrama Putri</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Kamar Asrama</label>
                    <input type="text" name="kamar_asrama" id="edit_kamar" required class="input-field">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#182923] mb-1">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" id="edit_tgl" required class="input-field">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-[#DCE7E0]">
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Nama Wali Santri</label>
                    <input type="text" name="nama_wali" id="edit_wali" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#182923] mb-1">Nomor WhatsApp Wali</label>
                    <input type="text" name="no_hp_wali" id="edit_hp" class="input-field">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
                <button type="button" onclick="document.getElementById('modalEditSantri').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-[#DCE7E0] text-xs font-bold text-[#64756D] hover:bg-slate-100">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-[#0B3D2E] hover:bg-[#16845B] text-white text-xs font-bold transition">Perbarui Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaModalEdit(data) {
        document.getElementById('edit_santri_id').value = data.id;
        document.getElementById('edit_nis').value = data.nis;
        document.getElementById('edit_nama').value = data.nama_lengkap;
        document.getElementById('edit_jk').value = data.jenis_kelamin;
        document.getElementById('edit_kompleks').value = data.kompleks_asrama;
        document.getElementById('edit_kamar').value = data.kamar_asrama;
        document.getElementById('edit_tgl').value = data.tanggal_lahir;
        document.getElementById('edit_wali').value = data.nama_wali || '';
        document.getElementById('edit_hp').value = data.no_hp_wali || '';
        document.getElementById('modalEditSantri').classList.remove('hidden');
    }
</script>

<?php require_once 'footer.php'; ?>



