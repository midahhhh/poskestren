// routes/laporanRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan"])];

router.get("/", protect, async (req, res) => {
    const tglAwal = req.query.tgl_awal || new Date().toISOString().slice(0,8) + "01";
    const tglAkhir = req.query.tgl_akhir || new Date().toISOString().slice(0,10);

    const kunjungan = await query("SELECT k.*,s.nama_lengkap,s.nis,s.kompleks_asrama FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE DATE(k.tanggal_berobat) BETWEEN ? AND ? ORDER BY k.tanggal_berobat DESC", [tglAwal,tglAkhir]).catch(()=>[]);
    const diagDist = await query("SELECT diagnosis, COUNT(*) as jml FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN ? AND ? AND diagnosis IS NOT NULL AND diagnosis != '' GROUP BY diagnosis ORDER BY jml DESC LIMIT 10", [tglAwal,tglAkhir]).catch(()=>[]);
    const triaseDist = await query("SELECT tingkat_prioritas, COUNT(*) as jml FROM kunjungan_sakit WHERE DATE(tanggal_berobat) BETWEEN ? AND ? GROUP BY tingkat_prioritas", [tglAwal,tglAkhir]).catch(()=>[]);
    const mutasiObat = await query("SELECT o.nama_obat, o.satuan, o.stok_sekarang, o.stok_minimal FROM obat o ORDER BY o.stok_sekarang ASC LIMIT 15").catch(()=>[]);

    res.render("laporan", {
        title: "Laporan & Rekapitulasi Data",
        kunjungan,
        diagDist,
        triaseDist,
        mutasiObat,
        tglAwal,
        tglAkhir,
        currentPage: "laporan"
    });
});

module.exports = router;
