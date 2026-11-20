// routes/waliRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth } = require("../middleware/auth");
const { tglIndo, badgeTriase, buildWaUrl } = require("../helpers/formatters");

const protect = requireAuth("wali");

router.get("/", protect, async (req, res) => {
    const userId = req.session.userId;
    const namaWali = req.session.namaLengkap;
    // Cari santri yang terhubung ke wali ini
    const santriList = await query("SELECT * FROM santri WHERE user_id_wali=?", [userId]).catch(()=>[]);
    const santriIds = santriList.map(s => s.id);

    let antreanSakit = [];
    let riwayatKunjungan = [];

    if (santriIds.length > 0) {
        const ph = santriIds.map(()=>"?").join(",");
        antreanSakit = await query(
            `SELECT k.*,s.nama_lengkap,s.nis,s.kamar_asrama,s.kompleks_asrama FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE k.santri_id IN (${ph}) AND k.status_antrean IN ('menunggu_triase','pemeriksaan','rawat_inap') ORDER BY k.skor_saw_akhir DESC`,
            santriIds
        ).catch(()=>[]);

        riwayatKunjungan = await query(
            `SELECT k.*,s.nama_lengkap FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE k.santri_id IN (${ph}) ORDER BY k.tanggal_berobat DESC LIMIT 20`,
            santriIds
        ).catch(()=>[]);
    }

    res.render("wali", {
        title: "Portal Keluarga & Wali Santri",
        namaWali,
        santriList,
        antreanSakit,
        riwayatKunjungan,
        helpers: { tglIndo, badgeTriase, buildWaUrl },
        currentPage: "wali"
    });
});

module.exports = router;
