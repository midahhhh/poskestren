// routes/rekamMedisRoutes.js
const express = require("express");
const router = express.Router();
const { query } = require("../config/database");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan"])];

router.get("/", protect, async (req, res) => {
    const kunjId = req.query.kunjungan_id || null;
    const kunjungan = kunjId ? (await query("SELECT k.*,s.nama_lengkap,s.nis,s.kamar_asrama,s.kompleks_asrama,s.riwayat_alergi,s.no_hp_wali FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE k.id=?", [kunjId]).catch(()=>[]))[0] || null : null;
    const antrean = await query("SELECT k.*,s.nama_lengkap,s.nis FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan') ORDER BY k.skor_saw_akhir DESC").catch(()=>[]);
    res.render("rekam_medis", { title:"Rekam Medis & Keputusan Klinis", kunjungan, antrean, kunjId, currentPage:"rekam_medis" });
});

router.post("/simpan", protect, async (req, res) => {
    const { kunjungan_id, anamnesis, diagnosis, keputusan_tindakan, obat_resep, catatan_dokter } = req.body;
    try {
        const statusBaru = keputusan_tindakan === "rawat_inap" ? "rawat_inap" : (keputusan_tindakan === "rujukan" ? "dirujuk" : "selesai");
        await query("UPDATE kunjungan_sakit SET anamnesis=?,diagnosis=?,keputusan_tindakan=?,catatan_dokter=?,status_antrean=? WHERE id=?",
            [anamnesis,diagnosis,keputusan_tindakan,catatan_dokter||"",statusBaru,kunjungan_id]);
        if (obat_resep && obat_resep.trim()) {
            await query("INSERT INTO resep_obat (kunjungan_id,detail_resep,status_resep,dibuat_pada) VALUES (?,?,'menunggu',NOW())",
                [kunjungan_id, obat_resep]).catch(()=>{});
        }
        req.session.flashSuccess = "Rekam medis berhasil disimpan.";
    } catch(e) { req.session.flashError = "Gagal: " + e.message; }
    res.redirect("/rekam-medis");
});

module.exports = router;
