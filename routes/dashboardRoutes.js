// routes/dashboardRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan","petugas_farmasi"])];

router.get("/", protect, async (req, res) => {
    try {
        const hariIni = new Date().toISOString().slice(0,10);
        const [totRow] = await query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE DATE(tanggal_berobat) = ?", [hariIni]);
        const [p1Row]  = await query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P1_Gawat_Darurat' OR skor_saw_akhir>=0.80)");
        const [p2Row]  = await query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P2_Mendesak' OR (skor_saw_akhir>=0.50 AND skor_saw_akhir<0.80))");
        const [p3Row]  = await query("SELECT COUNT(*) as c FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase','pemeriksaan') AND (tingkat_prioritas='P3_Biasa' AND skor_saw_akhir<0.50)");
        const [riRow]  = await query("SELECT COUNT(*) as c FROM rawat_inap WHERE status_rawat='Dirawat'").catch(()=>[{c:0}]);
        const [skRow]  = await query("SELECT COUNT(*) as c FROM obat WHERE stok_sekarang <= stok_minimal").catch(()=>[{c:0}]);

        const antrean = await query(`SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama, s.kompleks_asrama
            FROM kunjungan_sakit k JOIN santri s ON k.santri_id = s.id
            WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan')
            ORDER BY CASE WHEN k.tingkat_prioritas='P1_Gawat_Darurat' THEN 1 ELSE 2 END, k.skor_saw_akhir DESC, k.id ASC`);

        const obatAlert = await query("SELECT * FROM obat WHERE stok_sekarang <= stok_minimal OR exp_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY stok_sekarang ASC LIMIT 4").catch(()=>[]);

        res.render("dashboard", {
            title: "Dashboard Operasional & Triase",
            stats: { total: totRow.c||0, p1: p1Row.c||0, p2: p2Row.c||0, p3: p3Row.c||0, rawatInap: riRow.c||0, stokKritis: skRow.c||0 },
            antrean,
            obatAlert,
            currentPage: "dashboard"
        });
    } catch(e) {
        console.error(e);
        res.render("dashboard", { title:"Dashboard", stats:{total:0,p1:0,p2:0,p3:0,rawatInap:0,stokKritis:0}, antrean:[], obatAlert:[], currentPage:"dashboard" });
    }
});

module.exports = router;
