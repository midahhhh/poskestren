// routes/nakesRoutes.js
const express = require("express");
const router = express.Router();
const bcrypt = require("bcryptjs");
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const adminOnly = [requireAuth("petugas"), requireRole(["admin"])];

router.get("/", adminOnly, async (req, res) => {
    const nakesList = await query("SELECT * FROM users WHERE role != 'santri_wali' ORDER BY nama_lengkap").catch(()=>[]);
    res.render("nakes", { title:"Master Data Tenaga Kesehatan", nakesList, currentPage:"nakes" });
});

router.post("/simpan", adminOnly, async (req, res) => {
    const { id, username, nama_lengkap, role, no_hp, no_str, password } = req.body;
    try {
        if (id) {
            if (password) {
                const hashed = bcrypt.hashSync(password, 10);
                await query("UPDATE users SET username=?,nama_lengkap=?,role=?,no_hp=?,no_str=?,password_hash=? WHERE id=?",
                    [username,nama_lengkap,role,no_hp,no_str||"",hashed,id]);
            } else {
                await query("UPDATE users SET username=?,nama_lengkap=?,role=?,no_hp=?,no_str=? WHERE id=?",
                    [username,nama_lengkap,role,no_hp,no_str||"",id]);
            }
        } else {
            const hashed = bcrypt.hashSync(password || "admin123", 10);
            await query("INSERT INTO users (username,password_hash,nama_lengkap,role,no_hp,no_str,is_active) VALUES (?,?,?,?,?,?,1)",
                [username,hashed,nama_lengkap,role,no_hp,no_str||""]);
        }
        req.session.flashSuccess = "Data tenaga kesehatan berhasil disimpan.";
    } catch(e) { req.session.flashError = "Gagal: " + e.message; }
    res.redirect("/nakes");
});

router.post("/hapus/:id", adminOnly, async (req, res) => {
    if (req.params.id == req.session.userId) { req.session.flashError = "Tidak dapat menghapus akun yang sedang aktif."; return res.redirect("/nakes"); }
    await query("DELETE FROM users WHERE id=?", [req.params.id]).catch(()=>{});
    req.session.flashSuccess = "Akun nakes berhasil dihapus.";
    res.redirect("/nakes");
});

module.exports = router;
