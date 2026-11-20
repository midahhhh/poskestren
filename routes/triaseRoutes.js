// routes/triaseRoutes.js
const express = require("express");
const router = express.Router();
const { query, pool } = require("../config/database");
const { jalankanKalkulasiSaw } = require("../services/sawEngine");
const { requireAuth, requireRole } = require("../middleware/auth");

const protect = [requireAuth("petugas"), requireRole(["admin","petugas_kesehatan"])];

router.get("/", protect, async (req, res) => {
    const santriList = await query("SELECT id, nis, nama_lengkap, kompleks_asrama, kamar_asrama FROM santri ORDER BY nama_lengkap").catch(()=>[]);
    const kriteria   = await query("SELECT * FROM saw_kriteria ORDER BY id").catch(()=>[]);
    const subK       = await query("SELECT * FROM saw_sub_kriteria ORDER BY kriteria_id, nilai_crisp DESC").catch(()=>[]);
    const antrean    = await query(`SELECT k.*, s.nama_lengkap, s.nis, s.kamar_asrama FROM kunjungan_sakit k JOIN santri s ON k.santri_id=s.id WHERE k.status_antrean IN ('menunggu_triase','pemeriksaan') ORDER BY CASE WHEN k.tingkat_prioritas='P1_Gawat_Darurat' THEN 1 ELSE 2 END, k.skor_saw_akhir DESC, k.id ASC`).catch(()=>[]);
    res.render("triase", { title:"Skrining & Kalkulasi SAW", santriList, kriteria, subK, antrean, currentPage:"triase" });
});

router.post("/submit", protect, async (req, res) => {
    const { santri_id, keluhan_utama, is_override, suhu_tubuh, tekanan_darah, nadi, rr, gcs, skala_nyeri } = req.body;
    const nilai = req.body.nilai || {};
    const conn = await pool.getConnection();
    try {
        await conn.beginTransaction();
        const tgl = new Date().toISOString().slice(0,10);
        const noAntrean = `TRS-${tgl.replace(/-/g,"")}-${Date.now().toString().slice(-4)}`;
        const override = is_override === "1" ? 1 : 0;
        const prioritasAwal = override ? "P1_Gawat_Darurat" : "P3_Biasa";

        const [ins] = await conn.execute(
            "INSERT INTO kunjungan_sakit (santri_id,no_antrean,tanggal_berobat,keluhan_utama,status_antrean,tingkat_prioritas,suhu_tubuh,tekanan_darah,nadi,rr,gcs,skala_nyeri,is_emergency_override) VALUES (?,?,?,?,'menunggu_triase',?,?,?,?,?,?,?,?)",
            [santri_id,noAntrean,tgl,keluhan_utama,prioritasAwal,suhu_tubuh||null,tekanan_darah||"",nadi||null,rr||null,gcs||null,skala_nyeri||null,override]
        );
        const kunjId = ins.insertId;

        for (const [kId, nilaiRaw] of Object.entries(nilai)) {
            await conn.execute(
                "INSERT INTO saw_nilai_pemeriksaan (kunjungan_id,kriteria_id,nilai_raw,nilai_normalisasi,nilai_terbobot) VALUES (?,?,?,0,0) ON DUPLICATE KEY UPDATE nilai_raw=?",
                [kunjId, kId, parseFloat(nilaiRaw)||1, parseFloat(nilaiRaw)||1]
            );
        }
        await conn.commit();
        await jalankanKalkulasiSaw();
        req.session.flashSuccess = `Triase berhasil disimpan (No. Antrean: ${noAntrean}).`;
    } catch(e) {
        await conn.rollback();
        req.session.flashError = "Gagal menyimpan triase: " + e.message;
    } finally { conn.release(); }
    res.redirect("/triase");
});

router.post("/hapus/:id", protect, async (req, res) => {
    await query("DELETE FROM kunjungan_sakit WHERE id=?", [req.params.id]).catch(()=>{});
    req.session.flashSuccess = "Data kunjungan dihapus.";
    res.redirect("/triase");
});

module.exports = router;
