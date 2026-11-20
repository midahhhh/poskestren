// routes/santriRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan"])];

router.get("/", protect, async (req, res) => {
    const search = (req.query.search || "").trim();
    const kompleks = req.query.kompleks || "";
    let sql = "SELECT * FROM santri WHERE 1=1";
    const params = [];
    if (search) { sql += " AND (nama_lengkap LIKE ? OR nis LIKE ?)"; params.push(`%${search}%`,`%${search}%`); }
    if (kompleks) { sql += " AND kompleks_asrama = ?"; params.push(kompleks); }
    sql += " ORDER BY nama_lengkap ASC";
    const santriList = await query(sql, params).catch(()=>[]);
    const kompleksList = await query("SELECT DISTINCT kompleks_asrama FROM santri ORDER BY kompleks_asrama").catch(()=>[]);
    res.render("santri", { title:"Master Data Santri", santriList, search, kompleks, kompleksList, currentPage:"santri" });
});

router.post("/simpan", protect, async (req, res) => {
    const { id, nis, nama_lengkap, jenis_kelamin, tanggal_lahir, kompleks_asrama, kamar_asrama, nama_wali, no_hp_wali, riwayat_alergi } = req.body;
    try {
        if (id) {
            await query("UPDATE santri SET nis=?,nama_lengkap=?,jenis_kelamin=?,tanggal_lahir=?,kompleks_asrama=?,kamar_asrama=?,nama_wali=?,no_hp_wali=?,riwayat_alergi=? WHERE id=?",
                [nis,nama_lengkap,jenis_kelamin,tanggal_lahir||null,kompleks_asrama,kamar_asrama,nama_wali,no_hp_wali,riwayat_alergi||"",id]);
        } else {
            await query("INSERT INTO santri (nis,nama_lengkap,jenis_kelamin,tanggal_lahir,kompleks_asrama,kamar_asrama,nama_wali,no_hp_wali,riwayat_alergi) VALUES (?,?,?,?,?,?,?,?,?)",
                [nis,nama_lengkap,jenis_kelamin,tanggal_lahir||null,kompleks_asrama,kamar_asrama,nama_wali,no_hp_wali,riwayat_alergi||""]);
        }
        req.session.flashSuccess = id ? "Data santri berhasil diperbarui." : "Santri baru berhasil didaftarkan.";
    } catch(e) {
        req.session.flashError = "Gagal menyimpan data: " + e.message;
    }
    res.redirect("/santri");
});

router.post("/hapus/:id", [requireAuth("petugas"), requireRole(["admin"])], async (req, res) => {
    await query("DELETE FROM santri WHERE id=?", [req.params.id]).catch(()=>{});
    req.session.flashSuccess = "Data santri berhasil dihapus.";
    res.redirect("/santri");
});

module.exports = router;
