<?php
// nakes.php - Papan Shift Petugas Poskestren Al-Bahjah Bogor
$page_title = "Petugas Jaga Shift";
$page_desc  = "Dokter, perawat, dan apoteker yang bertugas saat ini.";
require_once 'header.php';

// Hanya admin & petugas kesehatan yang bisa akses
if (!in_array($user_role, ['admin', 'petugas_kesehatan'])) {
    set_flash('error', "Akses Ditolak.");
    header("Location: dashboard.php"); exit;
}

// ── Buat tabel shift_jaga jika belum ada ─────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS shift_jaga (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    posisi      VARCHAR(50)  NOT NULL UNIQUE,  -- 'dokter', 'perawat', 'farmasi'
    nama        VARCHAR(150) NOT NULL,
    catatan     VARCHAR(255) DEFAULT '',
    updated_by  INT DEFAULT NULL,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Isi default jika kosong
$cek = $conn->query("SELECT COUNT(*) c FROM shift_jaga")->fetch_assoc()['c'];
if ($cek == 0) {
    $conn->query("INSERT INTO shift_jaga (posisi, nama, catatan) VALUES
        ('dokter',  'dr. H. Ahmad Fauzan, Sp.A',   'Dokter Penanggung Jawab'),
        ('perawat', 'Ns. Hendra Pratama, S.Kep',    'Perawat Triase'),
        ('farmasi', 'Apt. Rahmat Hidayat, S.Farm',  'Apoteker Jaga')");
}

// ── Proses update shift (hanya admin) ───────────────────────
$flash_ok  = '';
$flash_err = '';
if ($f = get_flash('success')) $flash_ok  = $f;
if ($f = get_flash('error'))   $flash_err = $f;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_shift') {
    if ($user_role !== 'admin') {
        set_flash('error', "Hanya Admin yang dapat mengubah data petugas shift.");
        header("Location: nakes.php"); exit;
    }

    $posisi  = trim($_POST['posisi']  ?? '');
    $nama    = trim($_POST['nama']    ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $uid     = $_SESSION['user_id'];

    if (empty($posisi) || empty($nama)) {
        set_flash('error', "Posisi dan nama wajib diisi.");
        header("Location: nakes.php"); exit;
    }

    // Upsert — update jika sudah ada, insert jika belum
    $stmt = $conn->prepare("INSERT INTO shift_jaga (posisi, nama, catatan, updated_by) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE nama=VALUES(nama), catatan=VALUES(catatan), updated_by=VALUES(updated_by)");
    $stmt->bind_param("sssi", $posisi, $nama, $catatan, $uid);
    if ($stmt->execute()) {
        set_flash('success', "Data petugas shift berhasil diperbarui.");
    } else {
        set_flash('error', "Gagal menyimpan. Coba lagi.");
    }
    header("Location: nakes.php"); exit;
}

// ── Ambil data shift saat ini ────────────────────────────────
$shift = [];
$res   = $conn->query("SELECT sj.*, u.nama_lengkap as diubah_oleh
                        FROM shift_jaga sj
                        LEFT JOIN users u ON u.id = sj.updated_by
                        ORDER BY FIELD(posisi,'dokter','perawat','farmasi')");
while ($r = $res->fetch_assoc()) $shift[$r['posisi']] = $r;

// ── Ambil semua user aktif per role untuk dropdown ───────────
$dokter_list  = [];
$perawat_list = [];
$farmasi_list = [];
$res_users = $conn->query("SELECT id, nama_lengkap, role FROM users WHERE is_active=1 AND role != 'santri_wali' ORDER BY role, nama_lengkap");
while ($u = $res_users->fetch_assoc()) {
    if ($u['role'] === 'admin' || $u['role'] === 'petugas_kesehatan') {
        // Cek apakah nama mengandung prefix dokter
        $nama_low = strtolower($u['nama_lengkap']);
        $is_dr = strpos($nama_low,'dr.') !== false || strpos($nama_low,'sp.') !== false;
        if ($is_dr) $dokter_list[]  = $u;
        else        $perawat_list[] = $u;
    } elseif ($u['role'] === 'petugas_farmasi') {
        $farmasi_list[] = $u;
    }
}

// Konfigurasi posisi
$posisi_config = [
    'dokter'  => ['label'=>'Dokter Jaga',   'icon'=>'🩺', 'color'=>'#16845B', 'bg'=>'#f0fdf4', 'border'=>'#bbf7d0', 'list'=>$dokter_list],
    'perawat' => ['label'=>'Perawat Triase', 'icon'=>'💉', 'color'=>'#1d4ed8', 'bg'=>'#eff6ff', 'border'=>'#bfdbfe', 'list'=>$perawat_list],
    'farmasi' => ['label'=>'Apoteker Jaga',  'icon'=>'💊', 'color'=>'#7c3aed', 'bg'=>'#f5f3ff', 'border'=>'#ddd6fe', 'list'=>$farmasi_list],
];
?>

<!-- Flash messages -->
<?php if (!empty($flash_ok)): ?>
<div class="mb-5 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
    <span>✓ <?= htmlspecialchars($flash_ok); ?></span>
    <button onclick="this.parentElement.remove()" class="text-emerald-600 font-bold ml-3">×</button>
</div>
<?php endif; ?>
<?php if (!empty($flash_err)): ?>
<div class="mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between">
    <span>⚠ <?= htmlspecialchars($flash_err); ?></span>
    <button onclick="this.parentElement.remove()" class="text-red-600 font-bold ml-3">×</button>
</div>
<?php endif; ?>

<!-- Header info -->
<div class="flex items-center justify-between mb-6">
    <div>
        <p class="text-sm text-gray-500 mt-0.5">
            Shift aktif saat ini &mdash;
            <strong class="text-[#0B3D2E]"><?= date('l, d F Y'); ?></strong> &bull;
            <strong class="text-[#0B3D2E]"><?= date('H:i'); ?> WIB</strong>
        </p>
    </div>
    <?php if ($user_role === 'admin'): ?>
    <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-purple-100 text-purple-700 border border-purple-200">
        ✏ Mode Admin — Bisa Edit Shift
    </span>
    <?php endif; ?>
</div>

<!-- ── PAPAN NAMA SHIFT (3 kartu besar) ────────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <?php foreach ($posisi_config as $posisi => $cfg):
        $data = $shift[$posisi] ?? null;
        $nama_jaga   = $data['nama']    ?? '(Belum diatur)';
        $catatan_jaga = $data['catatan'] ?? '';
        $diubah_oleh  = $data['diubah_oleh'] ?? null;
        $diubah_saat  = $data['updated_at']  ?? null;
    ?>
    <div style="background:<?= $cfg['bg']; ?>; border:2px solid <?= $cfg['border']; ?>; border-radius:16px; padding:24px; display:flex; flex-direction:column; gap:12px;">

        <!-- Badge posisi -->
        <div class="flex items-center justify-between">
            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?= $cfg['color']; ?>;">
                <?= $cfg['label']; ?>
            </span>
            <span style="font-size:24px;"><?= $cfg['icon']; ?></span>
        </div>

        <!-- Nama petugas -->
        <div>
            <div style="font-size:20px;font-weight:700;color:#0B3D2E;line-height:1.3;">
                <?= htmlspecialchars($nama_jaga); ?>
            </div>
            <?php if (!empty($catatan_jaga)): ?>
            <div style="font-size:12px;color:#64756D;margin-top:4px;">
                <?= htmlspecialchars($catatan_jaga); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info update -->
        <?php if ($diubah_oleh && $diubah_saat): ?>
        <div style="font-size:11px;color:#9ca3af;border-top:1px solid <?= $cfg['border']; ?>;padding-top:10px;">
            Diperbarui oleh <strong><?= htmlspecialchars($diubah_oleh); ?></strong><br>
            <?= tgl_indo($diubah_saat, true); ?>
        </div>
        <?php endif; ?>

        <!-- Tombol edit (hanya admin) -->
        <?php if ($user_role === 'admin'): ?>
        <button onclick="bukaEdit('<?= $posisi; ?>', '<?= addslashes($nama_jaga); ?>', '<?= addslashes($catatan_jaga); ?>', '<?= $cfg['label']; ?>')"
                style="margin-top:auto;padding:9px 0;background:<?= $cfg['color']; ?>;color:#fff;border:none;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;transition:opacity .15s;width:100%;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            ✏ Ganti Petugas Shift
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── DAFTAR SEMUA AKUN AKTIF (read-only, info saja) ────── -->
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="text-sm font-bold text-[#0B3D2E]">Direktori Akun Petugas Aktif</h2>
            <p class="text-xs text-gray-400 mt-0.5">Semua akun yang terdaftar di sistem. Untuk menambah/nonaktifkan akun hubungi admin.</p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs text-gray-500 font-semibold border-b border-gray-100 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left">Nama Lengkap</th>
                    <th class="px-5 py-3 text-left">Username</th>
                    <th class="px-5 py-3 text-left">Role</th>
                    <th class="px-5 py-3 text-left">No. HP</th>
                    <th class="px-5 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $res_all = $conn->query("SELECT * FROM users WHERE role != 'santri_wali' ORDER BY role, nama_lengkap ASC");
                $role_label = [
                    'admin'             => ['label'=>'Dokter / Admin',  'bg'=>'bg-purple-100 text-purple-700'],
                    'petugas_kesehatan' => ['label'=>'Tenaga Medis',    'bg'=>'bg-emerald-100 text-emerald-700'],
                    'petugas_farmasi'   => ['label'=>'Farmasi',         'bg'=>'bg-blue-100 text-blue-700'],
                ];
                if ($res_all) while ($u = $res_all->fetch_assoc()):
                    $rl = $role_label[$u['role']] ?? ['label'=>ucfirst($u['role']),'bg'=>'bg-gray-100 text-gray-600'];
                ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-[#0B3D2E] text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                <?= strtoupper(mb_substr($u['nama_lengkap'], 0, 1)); ?>
                            </div>
                            <span class="font-semibold text-[#0B3D2E]"><?= htmlspecialchars($u['nama_lengkap']); ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-gray-500 text-xs">@<?= htmlspecialchars($u['username']); ?></td>
                    <td class="px-5 py-3.5">
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full <?= $rl['bg']; ?>">
                            <?= $rl['label']; ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-gray-500"><?= htmlspecialchars($u['no_hp'] ?: '-'); ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <?php if ($u['is_active']): ?>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">● Aktif</span>
                        <?php else: ?>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-500">○ Nonaktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── MODAL EDIT SHIFT (hanya admin) ───────────────────────── -->
<?php if ($user_role === 'admin'): ?>
<div id="modal-shift" style="display:none;position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border:1px solid #d8e6de;border-radius:16px;padding:28px;width:100%;max-width:460px;max-height:92vh;overflow-y:auto;font-family:'Inter',sans-serif;">

        <!-- Header modal -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #d8e6de;">
            <div>
                <div id="modal-title" style="font-weight:700;font-size:15px;color:#0B3D2E;">Ganti Petugas Shift</div>
                <div style="font-size:12px;color:#64756D;margin-top:2px;">Masukkan nama petugas yang bertugas shift ini.</div>
            </div>
            <button onclick="tutupModal()" style="background:none;border:none;cursor:pointer;font-size:22px;color:#9ca3af;line-height:1;">&times;</button>
        </div>

        <form method="POST" action="nakes.php" style="display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" name="action" value="update_shift">
            <input type="hidden" name="posisi" id="modal-posisi">

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#182923;margin-bottom:6px;">Nama Petugas Jaga</label>
                <!-- Input manual -->
                <input type="text" name="nama" id="modal-nama" required
                       placeholder="Ketik nama lengkap beserta gelar..."
                       style="width:100%;padding:10px 14px;border:1px solid #d1ddd7;border-radius:8px;font-size:13px;background:#f8faf9;outline:none;font-family:'Inter',sans-serif;"
                       onfocus="this.style.borderColor='#16845B';this.style.background='#fff'"
                       onblur="this.style.borderColor='#d1ddd7';this.style.background='#f8faf9'">
                <!-- Atau pilih dari akun terdaftar -->
                <div id="dropdown-container" style="margin-top:8px;">
                    <label style="display:block;font-size:11px;color:#64756D;margin-bottom:4px;">— atau pilih dari akun terdaftar —</label>
                    <select id="modal-dropdown" onchange="pilihDariDropdown(this.value)"
                            style="width:100%;padding:9px 13px;border:1px solid #d1ddd7;border-radius:8px;font-size:13px;background:#f8faf9;outline:none;font-family:'Inter',sans-serif;">
                        <option value="">Pilih nama dari sistem...</option>
                    </select>
                </div>
                <!-- Data akun per posisi (hidden JSON) -->
                <script id="data-dokter"  type="application/json"><?= json_encode(array_map(fn($u)=>$u['nama_lengkap'], $dokter_list));  ?></script>
                <script id="data-perawat" type="application/json"><?= json_encode(array_map(fn($u)=>$u['nama_lengkap'], $perawat_list)); ?></script>
                <script id="data-farmasi" type="application/json"><?= json_encode(array_map(fn($u)=>$u['nama_lengkap'], $farmasi_list)); ?></script>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:600;color:#182923;margin-bottom:6px;">Keterangan / Jabatan</label>
                <input type="text" name="catatan" id="modal-catatan"
                       placeholder="Contoh: Dokter Penanggung Jawab, Shift Pagi..."
                       style="width:100%;padding:10px 14px;border:1px solid #d1ddd7;border-radius:8px;font-size:13px;background:#f8faf9;outline:none;font-family:'Inter',sans-serif;"
                       onfocus="this.style.borderColor='#16845B';this.style.background='#fff'"
                       onblur="this.style.borderColor='#d1ddd7';this.style.background='#f8faf9'">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:4px;">
                <button type="button" onclick="tutupModal()"
                        style="padding:9px 18px;background:#fff;border:1px solid #d1ddd7;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:#182923;">
                    Batal
                </button>
                <button type="submit"
                        style="padding:9px 20px;background:#16845B;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s;"
                        onmouseover="this.style.background='#0B3D2E'" onmouseout="this.style.background='#16845B'">
                    💾 Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaEdit(posisi, nama, catatan, label) {
        document.getElementById('modal-shift').style.display = 'flex';
        document.getElementById('modal-title').textContent   = 'Ganti Petugas: ' + label;
        document.getElementById('modal-posisi').value        = posisi;
        document.getElementById('modal-nama').value          = nama;
        document.getElementById('modal-catatan').value       = catatan;

        // Isi dropdown sesuai posisi
        const dataEl = document.getElementById('data-' + posisi);
        const dropdown = document.getElementById('modal-dropdown');
        dropdown.innerHTML = '<option value="">Pilih nama dari sistem...</option>';
        if (dataEl) {
            const names = JSON.parse(dataEl.textContent);
            names.forEach(n => {
                const opt = document.createElement('option');
                opt.value = n;
                opt.textContent = n;
                if (n === nama) opt.selected = true;
                dropdown.appendChild(opt);
            });
        }
        document.getElementById('modal-nama').focus();
    }

    function tutupModal() {
        document.getElementById('modal-shift').style.display = 'none';
    }

    function pilihDariDropdown(val) {
        if (val) document.getElementById('modal-nama').value = val;
    }

    // Tutup klik backdrop
    document.getElementById('modal-shift').addEventListener('click', function(e) {
        if (e.target === this) tutupModal();
    });
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
