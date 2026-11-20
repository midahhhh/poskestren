<?php
// farmasi.php - Pelayanan Resep Farmasi & Manajemen Stok Obat (Khusus Admin)
$page_title = "Manajemen Stok & Farmasi";
$page_desc  = "Kelola persediaan obat, tambah obat baru, restock pasokan, dan audit mutasi.";
require_once 'header.php';

// Hak akses: KHUSUS ADMIN
if ($user_role !== 'admin') {
    set_flash('error', "Akses Ditolak: Modul Farmasi ini khusus untuk Admin. Apoteker diarahkan ke Dashboard Farmasi.");
    header("Location: dashboard_farmasi.php"); exit;
}

$active_tab = $_GET['tab'] ?? 'resep';

// ── Serahkan Resep (atomic transaction) ─────────────────────
if (isset($_GET['serahkan_resep_id'])) {
    $resep_id  = (int)$_GET['serahkan_resep_id'];
    $petugas_id = $_SESSION['user_id'];
    $conn->begin_transaction();
    try {
        $q = $conn->query("SELECT rd.*, o.nama_obat, o.stok_sekarang
                           FROM resep_obat_detail rd JOIN obat o ON rd.obat_id=o.id
                           WHERE rd.resep_id=$resep_id FOR UPDATE");
        while ($it = $q->fetch_assoc()) {
            if ($it['stok_sekarang'] < $it['jumlah'])
                throw new Exception("Stok '{$it['nama_obat']}' tidak cukup (ada {$it['stok_sekarang']}, diminta {$it['jumlah']}).");
            $conn->query("UPDATE obat SET stok_sekarang=stok_sekarang-{$it['jumlah']} WHERE id={$it['obat_id']}");
            $ket = "Penyerahan resep #$resep_id";
            $stmt = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
            $mut  = 'keluar';
            $stmt->bind_param("isisi", $it['obat_id'], $mut, $it['jumlah'], $ket, $petugas_id);
            $stmt->execute();
        }
        $conn->query("UPDATE resep_obat SET status_resep='selesai', petugas_farmasi_id=$petugas_id WHERE id=$resep_id");
        $conn->commit();
        set_flash('success', "Resep #$resep_id berhasil diserahkan. Stok terpotong otomatis.");
    } catch (Exception $e) {
        $conn->rollback();
        set_flash('error', "Gagal: " . $e->getMessage());
    }
    header("Location: farmasi.php?tab=resep"); exit;
}

// ── Proses resep ─────────────────────────────────────────────
if (isset($_GET['proses_resep_id'])) {
    $rid = (int)$_GET['proses_resep_id'];
    $conn->query("UPDATE resep_obat SET status_resep='diproses' WHERE id=$rid");
    set_flash('success', "Status resep diperbarui ke 'Sedang Disiapkan'.");
    header("Location: farmasi.php?tab=resep"); exit;
}

// ── Tambah obat baru ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='tambah_obat') {
    $kode  = trim($_POST['kode_obat']);
    $nama  = trim($_POST['nama_obat']);
    $kat   = (int)$_POST['kategori_id'];
    $sat   = trim($_POST['satuan']);
    $stok  = (int)$_POST['stok_sekarang'];
    $min   = (int)$_POST['stok_minimal'];
    $exp   = $_POST['exp_date'];
    $ket   = trim($_POST['keterangan']??'');
    $stmt  = $conn->prepare("INSERT INTO obat (kategori_id,kode_obat,nama_obat,satuan,stok_minimal,stok_sekarang,exp_date,keterangan) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("isssiiss", $kat,$kode,$nama,$sat,$min,$stok,$exp,$ket);
    if ($stmt->execute()) {
        $oid = $conn->insert_id;
        $uid = $_SESSION['user_id'];
        $jm  = 'masuk'; $kt = "Stok awal";
        $s2  = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
        $s2->bind_param("isisi", $oid,$jm,$stok,$kt,$uid);
        $s2->execute();
        set_flash('success', "Obat '$nama' berhasil ditambahkan.");
        header("Location: farmasi.php?tab=inventaris"); exit;
    } else {
        set_flash('error', "Gagal menambah obat. Kode mungkin sudah ada.");
        header("Location: farmasi.php?tab=inventaris"); exit;
    }
}

// ── Restock ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='restock_obat') {
    $oid  = (int)$_POST['obat_id'];
    $jml  = (int)$_POST['jumlah'];
    $ket  = trim($_POST['keterangan']??'Penerimaan pasokan');
    $uid  = $_SESSION['user_id'];
    if ($oid>0 && $jml>0) {
        $conn->begin_transaction();
        $conn->query("UPDATE obat SET stok_sekarang=stok_sekarang+$jml WHERE id=$oid");
        $jm='masuk';
        $stmt = $conn->prepare("INSERT INTO obat_mutasi_stok (obat_id,jenis_mutasi,jumlah,keterangan,user_id) VALUES (?,?,?,?,?)");
        $stmt->bind_param("isisi", $oid,$jm,$jml,$ket,$uid);
        $stmt->execute();
        $conn->commit();
        set_flash('success', "Stok berhasil ditambah (+$jml).");
    }
    header("Location: farmasi.php?tab=inventaris"); exit;
}

// ── Ambil Data ───────────────────────────────────────────────
$res_resep = $conn->query(
    "SELECT r.*, k.no_antrean, k.keluhan_utama, k.tanggal_berobat,
            s.nama_lengkap, s.nis, s.kamar_asrama,
            u.nama_lengkap as nama_apoteker
     FROM resep_obat r
     JOIN kunjungan_sakit k ON r.kunjungan_id=k.id
     JOIN santri s ON k.santri_id=s.id
     LEFT JOIN users u ON r.petugas_farmasi_id=u.id
     ORDER BY FIELD(r.status_resep,'menunggu_proses','diproses','selesai'), r.id DESC"
);
$res_obat = $conn->query(
    "SELECT o.*, ok.nama_kategori FROM obat o
     LEFT JOIN obat_kategori ok ON o.kategori_id=ok.id
     ORDER BY (o.stok_sekarang<=o.stok_minimal) DESC, o.stok_sekarang ASC"
);
$res_kat    = $conn->query("SELECT * FROM obat_kategori ORDER BY nama_kategori ASC");
$res_mutasi = $conn->query(
    "SELECT m.*, o.nama_obat, o.satuan, u.nama_lengkap as nama_user
     FROM obat_mutasi_stok m
     JOIN obat o ON m.obat_id=o.id
     LEFT JOIN users u ON m.user_id=u.id
     ORDER BY m.id DESC LIMIT 20"
);
// Hitung statistik
$total_resep   = $conn->query("SELECT COUNT(*) c FROM resep_obat WHERE status_resep='menunggu_proses'")->fetch_assoc()['c'];
$diproses      = $conn->query("SELECT COUNT(*) c FROM resep_obat WHERE status_resep='diproses'")->fetch_assoc()['c'];
$stok_kritis   = $conn->query("SELECT COUNT(*) c FROM obat WHERE stok_sekarang<=stok_minimal")->fetch_assoc()['c'];
$hampir_exp    = $conn->query("SELECT COUNT(*) c FROM obat WHERE exp_date<=DATE_ADD(CURDATE(),INTERVAL 60 DAY)")->fetch_assoc()['c'];
?>

<div style="display:flex;flex-direction:column;gap:18px;max-width:1200px;width:100%;margin:0 auto;">

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <?php
        $stats = [
            ['label' => 'Menunggu Diproses', 'value' => $total_resep, 'color' => '#D64545', 'border' => $total_resep > 0 ? 'border-red-200' : ''],
            ['label' => 'Sedang Disiapkan',  'value' => $diproses,    'color' => '#1d4ed8', 'border' => ''],
            ['label' => 'Stok Kritis',       'value' => $stok_kritis, 'color' => '#f97316', 'border' => $stok_kritis > 0 ? 'border-amber-200' : ''],
            ['label' => 'Hampir Expired',    'value' => $hampir_exp,  'color' => '#b45309', 'border' => $hampir_exp > 0 ? 'border-amber-200' : ''],
        ];
        foreach ($stats as $s):
        ?>
        <div class="card p-4 <?= $s['border']; ?>">
            <div class="text-xs text-gray-500 mb-1"><?= $s['label']; ?></div>
            <div class="text-2xl font-bold" style="color:<?= $s['color']; ?>"><?= $s['value']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab bar -->
    <div class="bg-white border border-[#d8e6de] rounded p-1.5 flex gap-1 no-print overflow-x-auto whitespace-nowrap">
        <a href="farmasi.php?tab=resep" class="px-4 py-2 rounded text-xs font-semibold transition shrink-0 <?= $active_tab==='resep' ? 'bg-[#16845B] text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            Antrean Resep <?php if ($total_resep>0): ?><span class="bg-red-500 text-white rounded px-1.5 py-0.5 text-[10px] ml-1"><?=$total_resep?></span><?php endif; ?>
        </a>
        <a href="farmasi.php?tab=inventaris" class="px-4 py-2 rounded text-xs font-semibold transition shrink-0 <?= $active_tab==='inventaris' ? 'bg-[#16845B] text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            Inventaris Obat <?php if ($stok_kritis>0): ?><span class="bg-amber-500 text-white rounded px-1.5 py-0.5 text-[10px] ml-1"><?=$stok_kritis?> kritis</span><?php endif; ?>
        </a>
        <a href="farmasi.php?tab=mutasi" class="px-4 py-2 rounded text-xs font-semibold transition shrink-0 <?= $active_tab==='mutasi' ? 'bg-[#16845B] text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            Log Mutasi Stok
        </a>
    </div>

            <!-- ══════════ TAB 1: ANTREAN RESEP ══════════ -->
            <?php if ($active_tab === 'resep'): ?>
            <div class="card" style="overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #e8f0eb;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-weight:700;font-size:14px;color:#0B3D2E;">Antrean Resep Digital</div>
                        <div style="font-size:12px;color:#64756D;margin-top:2px;">Resep dari pemeriksaan dokter langsung masuk ke sini.</div>
                    </div>
                    <span style="font-size:11px;font-weight:600;padding:4px 12px;border-radius:999px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;">Atomic Transaction</span>
                </div>

                <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px;">
                    <?php if (!$res_resep || $res_resep->num_rows===0): ?>
                    <div style="text-align:center;padding:40px;color:#9ca3af;font-size:13px;">Belum ada antrean resep.</div>
                    <?php else: while ($r = $res_resep->fetch_assoc()):
                        $rid    = $r['id'];
                        $items  = $conn->query("SELECT rd.*,o.nama_obat,o.satuan,o.stok_sekarang FROM resep_obat_detail rd JOIN obat o ON rd.obat_id=o.id WHERE rd.resep_id=$rid");
                        $st     = $r['status_resep'];
                        $st_bg  = $st==='selesai'?'#f0fdf4':($st==='diproses'?'#eff6ff':'#fffbeb');
                        $st_bd  = $st==='selesai'?'#bbf7d0':($st==='diproses'?'#bfdbfe':'#fde68a');
                        $st_cl  = $st==='selesai'?'#15803d':($st==='diproses'?'#1d4ed8':'#92400e');
                        $st_lbl = $st==='selesai'?'✓ Diserahkan':($st==='diproses'?'⚙ Sedang Disiapkan':'⏳ Menunggu');
                    ?>
                    <div style="border:1px solid #d8e6de;border-radius:12px;overflow:hidden;<?=$st==='selesai'?'opacity:.75':'';?>">
                        <!-- Header resep -->
                        <div style="background:<?=$st_bg?>;border-bottom:1px solid <?=$st_bd?>;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;background:#0B3D2E;border-radius:9px;display:flex;align-items:center;justify-content:center;font-family:monospace;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">R<?=$r['id']?></div>
                                <div>
                                    <div style="font-weight:700;font-size:13px;color:#0B3D2E;"><?=htmlspecialchars($r['nama_lengkap'])?></div>
                                    <div style="font-size:11px;color:#64756D;">NIS: <?=$r['nis']?> &bull; <?=htmlspecialchars($r['kamar_asrama'])?> &bull; <?=$r['no_antrean']?></div>
                                </div>
                            </div>
                            <span style="padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;background:<?=$st_bg?>;color:<?=$st_cl?>;border:1px solid <?=$st_bd?>;"><?=$st_lbl?></span>
                        </div>

                        <!-- Item obat -->
                        <div style="padding:14px 16px;">
                            <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Item Obat & Dosis</div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;">
                                <?php while ($it=$items->fetch_assoc()):
                                    $stok_ok = $it['stok_sekarang'] >= $it['jumlah'];
                                ?>
                                <div style="padding:10px 12px;background:<?=$stok_ok?'#f8faf9':'#fef2f2'?>;border:1px solid <?=$stok_ok?'#d8e6de':'#fecaca'?>;border-radius:9px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                    <div>
                                        <div style="font-weight:600;font-size:12px;color:#0B3D2E;"><?=htmlspecialchars($it['nama_obat'])?></div>
                                        <div style="font-size:11px;color:#16845B;margin-top:2px;"><?=htmlspecialchars($it['dosis_instruksi'])?></div>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0;">
                                        <div style="font-weight:700;font-size:13px;color:#0B3D2E;"><?=$it['jumlah']?> <?=$it['satuan']?></div>
                                        <div style="font-size:10px;color:<?=$stok_ok?'#64756D':'#D64545'?>;font-weight:600;">Sisa: <?=$it['stok_sekarang']?></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php if (!empty($r['catatan_resep'])): ?>
                            <div style="margin-top:10px;font-size:11px;color:#64756D;font-style:italic;">📝 "<?=htmlspecialchars($r['catatan_resep'])?>"</div>
                            <?php endif; ?>
                        </div>

                        <!-- Aksi -->
                        <?php if ($st !== 'selesai'): ?>
                        <div style="padding:12px 16px;border-top:1px solid #e8f0eb;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:#f8faf9;">
                            <div style="font-size:11px;color:#64756D;">Verifikasi stok fisik sebelum penyerahan.</div>
                            <div style="display:flex;gap:8px;">
                                <?php if ($st==='menunggu_proses'): ?>
                                <a href="farmasi.php?proses_resep_id=<?=$r['id']?>" class="btn" style="background:#1d4ed8;color:#fff;font-size:12px;padding:7px 14px;">
                                    <i data-lucide="settings" style="width:13px;height:13px;"></i> Siapkan
                                </a>
                                <?php endif; ?>
                                <button onclick="serahkan(<?=$r['id']?>, '<?=addslashes($r['nama_lengkap'])?>')"
                                        class="btn btn-green" style="font-size:12px;padding:7px 14px;">
                                    <i data-lucide="check" style="width:13px;height:13px;"></i> Serahkan ke Pasien
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="padding:10px 16px;border-top:1px solid #d1fae5;font-size:11px;color:#15803d;background:#f0fdf4;">
                            ✓ Diserahkan oleh <?=htmlspecialchars($r['nama_apoteker']??'Apoteker')?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>

            <!-- ══════════ TAB 2: INVENTARIS ══════════ -->
            <?php elseif ($active_tab === 'inventaris'): ?>
            <div class="card" style="overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #e8f0eb;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-weight:700;font-size:14px;color:#0B3D2E;">Inventaris Stok Obat</div>
                        <div style="font-size:12px;color:#64756D;margin-top:2px;">Merah = stok kritis. Kuning = mendekati expired.</div>
                    </div>
                    <button onclick="document.getElementById('modalTambah').style.display='flex'" class="btn btn-green" style="font-size:12px;padding:7px 14px;" id="btn-tambah-obat">
                        <i data-lucide="plus" style="width:13px;height:13px;"></i> Tambah Obat
                    </button>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8faf9;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64756D;border-bottom:1px solid #d8e6de;">
                                <th style="padding:10px 16px;text-align:left;">Kode & Nama</th>
                                <th style="padding:10px 16px;text-align:left;">Kategori</th>
                                <th style="padding:10px 16px;text-align:left;">Satuan</th>
                                <th style="padding:10px 16px;text-align:center;">Stok</th>
                                <th style="padding:10px 16px;text-align:center;">Min</th>
                                <th style="padding:10px 16px;text-align:left;">Expired</th>
                                <th style="padding:10px 16px;text-align:right;" class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_obat) while ($ob=$res_obat->fetch_assoc()):
                                $kritis = $ob['stok_sekarang'] <= $ob['stok_minimal'];
                                $exp60  = strtotime($ob['exp_date']) <= strtotime('+60 days');
                                $row_bg = $kritis ? '#fef2f2' : '#fff';
                            ?>
                            <tr style="border-bottom:1px solid #e8f0eb;background:<?=$row_bg?>;" onmouseover="this.style.background='#f8faf9'" onmouseout="this.style.background='<?=$row_bg?>'">
                                <td style="padding:12px 16px;">
                                    <div style="font-weight:600;font-size:13px;color:#0B3D2E;"><?=htmlspecialchars($ob['nama_obat'])?></div>
                                    <div style="font-family:monospace;font-size:10px;color:#64756D;"><?=$ob['kode_obat']?></div>
                                    <?php if (!empty($ob['keterangan'])): ?>
                                    <div style="margin-top:4px;padding:3px 8px;border-radius:6px;background:#fffbeb;border:1px solid #fde68a;font-size:10px;color:#92400e;max-width:260px;">
                                        <strong>Catatan Apoteker:</strong> <?=htmlspecialchars($ob['keterangan'])?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 16px;font-size:12px;color:#64756D;"><?=htmlspecialchars($ob['nama_kategori']??'-')?></td>
                                <td style="padding:12px 16px;font-size:12px;"><?=$ob['satuan']?></td>
                                <td style="padding:12px 16px;text-align:center;">
                                    <span style="padding:3px 12px;border-radius:999px;font-size:12px;font-weight:700;background:<?=$kritis?'#fef2f2':'#f0fdf4'?>;color:<?=$kritis?'#D64545':'#16845B'?>;border:1px solid <?=$kritis?'#fecaca':'#bbf7d0'?>;"><?=$ob['stok_sekarang']?></span>
                                </td>
                                <td style="padding:12px 16px;text-align:center;font-size:12px;font-weight:600;color:#64756D;"><?=$ob['stok_minimal']?></td>
                                <td style="padding:12px 16px;">
                                    <div style="font-size:12px;font-weight:600;color:<?=$exp60?'#D64545':'#182923'?>;"><?=date('d/m/Y',strtotime($ob['exp_date']))?></div>
                                    <?php if ($exp60): ?><div style="font-size:10px;color:#D64545;font-weight:600;">⚠ Mendekati ED!</div><?php endif; ?>
                                </td>
                                <td style="padding:12px 16px;text-align:right;" class="no-print">
                                    <button onclick="bukaRestock(<?=$ob['id']?>,'<?=addslashes($ob['nama_obat'])?>','<?=$ob['satuan']?>')"
                                            class="btn btn-dark" style="font-size:11px;padding:5px 12px;">
                                        + Restock
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════ TAB 3: LOG MUTASI ══════════ -->
            <?php elseif ($active_tab === 'mutasi'): ?>
            <div class="card" style="overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #e8f0eb;">
                    <div style="font-weight:700;font-size:14px;color:#0B3D2E;">Audit Trail Mutasi Stok</div>
                    <div style="font-size:12px;color:#64756D;margin-top:2px;">Riwayat semua perubahan stok masuk & keluar (20 terkini).</div>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8faf9;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#64756D;border-bottom:1px solid #d8e6de;">
                                <th style="padding:10px 16px;text-align:left;">Waktu</th>
                                <th style="padding:10px 16px;text-align:left;">Nama Obat</th>
                                <th style="padding:10px 16px;text-align:center;">Jenis</th>
                                <th style="padding:10px 16px;text-align:center;">Jumlah</th>
                                <th style="padding:10px 16px;text-align:left;">Keterangan</th>
                                <th style="padding:10px 16px;text-align:right;">Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$res_mutasi || $res_mutasi->num_rows===0): ?>
                            <tr><td colspan="6" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px;">Belum ada data mutasi.</td></tr>
                            <?php else: while ($m=$res_mutasi->fetch_assoc()):
                                $masuk = $m['jenis_mutasi']==='masuk';
                            ?>
                            <tr style="border-bottom:1px solid #e8f0eb;" onmouseover="this.style.background='#f8faf9'" onmouseout="this.style.background=''">
                                <td style="padding:11px 16px;font-size:12px;font-family:monospace;color:#64756D;"><?=tgl_indo($m['created_at'],true)?></td>
                                <td style="padding:11px 16px;font-size:13px;font-weight:600;color:#0B3D2E;"><?=htmlspecialchars($m['nama_obat'])?></td>
                                <td style="padding:11px 16px;text-align:center;">
                                    <span style="padding:3px 10px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;background:<?=$masuk?'#f0fdf4':'#fef2f2'?>;color:<?=$masuk?'#16845B':'#D64545'?>;border:1px solid <?=$masuk?'#bbf7d0':'#fecaca'?>;"><?=$m['jenis_mutasi']?></span>
                                </td>
                                <td style="padding:11px 16px;text-align:center;font-size:13px;font-weight:700;color:<?=$masuk?'#16845B':'#D64545'?>;">
                                    <?=$masuk?'+':'-'?><?=$m['jumlah']?> <?=$m['satuan']?>
                                </td>
                                <td style="padding:11px 16px;font-size:12px;color:#64756D;"><?=htmlspecialchars($m['keterangan'])?></td>
                                <td style="padding:11px 16px;font-size:12px;text-align:right;color:#182923;"><?=htmlspecialchars($m['nama_user']??'Sistem')?></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /container -->

    <!-- ── MODAL TAMBAH OBAT ─────────────────────────────────── -->
    <div id="modalTambah" style="display:none;position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:16px;">
        <div class="card" style="max-width:520px;width:100%;padding:24px;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #d8e6de;">
                <div style="font-weight:700;font-size:14px;color:#0B3D2E;">Tambah Obat Baru</div>
                <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">&times;</button>
            </div>
            <form method="POST" action="farmasi.php" style="display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="action" value="tambah_obat">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Kode Obat</label>
                        <input type="text" name="kode_obat" required placeholder="OBT-011" class="input-field">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Kategori</label>
                        <select name="kategori_id" required class="input-field">
                            <?php
                            // Reset pointer kategori
                            if ($res_kat) {
                                $res_kat->data_seek(0);
                                while ($kat=$res_kat->fetch_assoc()):
                            ?>
                            <option value="<?=$kat['id']?>"><?=htmlspecialchars($kat['nama_kategori'])?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Nama Lengkap Obat & Dosis</label>
                    <input type="text" name="nama_obat" required placeholder="Contoh: Amoxicillin 500mg Kapsul" class="input-field">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Satuan</label>
                        <input type="text" name="satuan" required placeholder="Tablet" class="input-field">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Stok Awal</label>
                        <input type="number" name="stok_sekarang" required value="50" min="0" class="input-field">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Stok Min</label>
                        <input type="number" name="stok_minimal" required value="10" min="1" class="input-field">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Tanggal Expired</label>
                    <input type="date" name="exp_date" required value="<?=date('Y-m-d',strtotime('+1 year'))?>" class="input-field">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Keterangan / Indikasi</label>
                    <input type="text" name="keterangan" placeholder="Kegunaan umum obat..." class="input-field">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:4px;">
                    <button type="button" onclick="document.getElementById('modalTambah').style.display='none'" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-green">Simpan Obat</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── MODAL RESTOCK ─────────────────────────────────────── -->
    <div id="modalRestock" style="display:none;position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:16px;">
        <div class="card" style="max-width:400px;width:100%;padding:24px;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #d8e6de;">
                <div style="font-weight:700;font-size:14px;color:#0B3D2E;">Restock Pasokan Obat</div>
                <button onclick="document.getElementById('modalRestock').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">&times;</button>
            </div>
            <form method="POST" action="farmasi.php" style="display:flex;flex-direction:column;gap:14px;">
                <input type="hidden" name="action" value="restock_obat">
                <input type="hidden" name="obat_id" id="rs_id">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Nama Obat</label>
                    <input type="text" id="rs_nama" readonly class="input-field" style="background:#f0f4f2;cursor:not-allowed;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Jumlah Tambahan</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="jumlah" required value="20" min="1" class="input-field" style="flex:1;">
                        <span id="rs_sat" style="font-size:12px;font-weight:600;color:#64756D;white-space:nowrap;"></span>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px;">Keterangan Sumber</label>
                    <input type="text" name="keterangan" value="Pengadaan rutin logistik poskestren" class="input-field">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:4px;">
                    <button type="button" onclick="document.getElementById('modalRestock').style.display='none'" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-green">Tambah Stok</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function serahkan(id, nama) {
            if (confirm('Serahkan obat kepada ' + nama + ' dan potong stok inventaris?')) {
                window.location = 'farmasi.php?serahkan_resep_id=' + id;
            }
        }

        function bukaRestock(id, nama, sat) {
            document.getElementById('rs_id').value   = id;
            document.getElementById('rs_nama').value = nama;
            document.getElementById('rs_sat').textContent = sat;
            document.getElementById('modalRestock').style.display = 'flex';
        }

        // Tutup modal klik backdrop
        ['modalTambah','modalRestock'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('click', function(e) {
                    if (e.target === this) this.style.display = 'none';
                });
            }
        });
    </script>

<?php require_once 'footer.php'; ?>
