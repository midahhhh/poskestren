// routes/farmasiRoutes.js
const express = require("express");
const router = express.Router();
const { query, pool } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan","petugas_farmasi"])];

router.get("/", protect, async (req, res) => {
    const resep = await query("SELECT r.*,s.nama_lengkap,s.nis,k.diagnosis,k.no_antrean FROM resep_obat r JOIN kunjungan_sakit k ON r.kunjungan_id=k.id JOIN santri s ON k.santri_id=s.id ORDER BY FIELD(r.status_resep,'menunggu','diproses','siap_diambil','selesai'), r.dibuat_pada ASC").catch(()=>[]);
    const obatList = await query("SELECT * FROM obat ORDER BY nama_obat").catch(()=>[]);
    const obatAlert = await query("SELECT * FROM obat WHERE stok_sekarang<=stok_minimal OR exp_date<=DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY stok_sekarang ASC").catch(()=>[]);
    res.render("farmasi", { title:"Pelayanan Farmasi & Stok Obat", resep, obatList, obatAlert, currentPage:"farmasi" });
});

router.post("/update-status/:id", protect, async (req, res) => {
    const { status } = req.body;
    await query("UPDATE resep_obat SET status_resep=? WHERE id=?", [status, req.params.id]).catch(()=>{});
    req.session.flashSuccess = "Status resep diperbarui.";
    res.redirect("/farmasi");
});

router.post("/obat/simpan", [requireAuth("petugas"), requireRole(["admin","petugas_farmasi"])], async (req, res) => {
    const { id, nama_obat, satuan, stok_sekarang, stok_minimal, exp_date, harga_satuan } = req.body;
    try {
        if (id) {
            await query("UPDATE obat SET nama_obat=?,satuan=?,stok_sekarang=?,stok_minimal=?,exp_date=?,harga_satuan=? WHERE id=?",
                [nama_obat,satuan,stok_sekarang,stok_minimal,exp_date||null,harga_satuan||0,id]);
        } else {
            await query("INSERT INTO obat (nama_obat,satuan,stok_sekarang,stok_minimal,exp_date,harga_satuan) VALUES (?,?,?,?,?,?)",
                [nama_obat,satuan,stok_sekarang,stok_minimal,exp_date||null,harga_satuan||0]);
        }
        req.session.flashSuccess = "Data obat berhasil disimpan.";
    } catch(e) { req.session.flashError = "Gagal: " + e.message; }
    res.redirect("/farmasi");
});

module.exports = router;
