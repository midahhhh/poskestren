// routes/kriteriaRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const adminNakes = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan"])];

router.get("/", adminNakes, async (req, res) => {
    const kriteria = await query("SELECT * FROM saw_kriteria ORDER BY id").catch(()=>[]);
    const subKriteria = await query("SELECT * FROM saw_sub_kriteria ORDER BY kriteria_id, nilai_crisp DESC").catch(()=>[]);
    res.render("kriteria", { title:"Konfigurasi Kriteria & Bobot SAW", kriteria, subKriteria, currentPage:"kriteria" });
});

router.post("/update-bobot", adminNakes, async (req, res) => {
    const bobotArr = req.body.bobot || {};
    let total = 0;
    Object.values(bobotArr).forEach(v => total += parseFloat(v)||0);
    if (Math.abs(total - 100) > 0.01) {
        req.session.flashError = `Total bobot harus 100%. Saat ini: ${total.toFixed(1)}%`;
        return res.redirect("/kriteria");
    }
    try {
        for (const [id, bobot] of Object.entries(bobotArr)) {
            await query("UPDATE saw_kriteria SET bobot_persen=? WHERE id=?", [parseFloat(bobot), id]);
        }
        req.session.flashSuccess = "Bobot kriteria SAW berhasil diperbarui.";
    } catch(e) { req.session.flashError = "Gagal: " + e.message; }
    res.redirect("/kriteria");
});

module.exports = router;
