// services/sawEngine.js - Mesin Kalkulasi SPK SAW (Simple Additive Weighting) Terpusat
const { pool } = require('../config/database');

/**
 * Menjalankan kalkulasi ulang SAW untuk semua antrean yang berstatus 'menunggu_triase' atau 'pemeriksaan'
 */
async function jalankanKalkulasiSaw() {
    // 1. Ambil seluruh kunjungan yang sedang menunggu triase / pemeriksaan
    const [antreanList] = await pool.query(
        "SELECT id, tingkat_prioritas FROM kunjungan_sakit WHERE status_antrean IN ('menunggu_triase', 'pemeriksaan')"
    );

    if (!antreanList || antreanList.length === 0) {
        return;
    }

    const antreanIds = antreanList.map(r => r.id);
    const isOverrideMap = {};
    antreanList.forEach(r => {
        isOverrideMap[r.id] = (r.tingkat_prioritas === 'P1_Gawat_Darurat');
    });

    const placeholders = antreanIds.map(() => '?').join(',');

    // 2. Ambil nilai raw untuk setiap alternatif dan kriteria
    const [valRows] = await pool.query(
        `SELECT kunjungan_id, kriteria_id, nilai_raw FROM saw_nilai_pemeriksaan WHERE kunjungan_id IN (${placeholders})`,
        antreanIds
    );

    const matriksX = {};
    const maxKriteria = { 1: 1, 2: 1, 3: 1, 4: 1, 5: 1 };

    valRows.forEach(v => {
        const kid = v.kunjungan_id;
        const crit = v.kriteria_id;
        const raw = parseFloat(v.nilai_raw) || 1.0;

        if (!matriksX[kid]) matriksX[kid] = {};
        matriksX[kid][crit] = raw;

        if (raw > maxKriteria[crit]) {
            maxKriteria[crit] = raw;
        }
    });

    // 3. Ambil bobot kriteria W
    const [kriteriaRows] = await pool.query("SELECT id, bobot_persen FROM saw_kriteria");
    const bobotW = {};
    kriteriaRows.forEach(w => {
        bobotW[w.id] = (parseFloat(w.bobot_persen) || 0) / 100.0;
    });

    // 4. Hitung Normalisasi (R) dan Nilai Terbobot (V)
    for (const kid of antreanIds) {
        let skorAkhir = 0.0;

        for (let c = 1; c <= 5; c++) {
            const valX = (matriksX[kid] && matriksX[kid][c]) ? matriksX[kid][c] : 1.0;
            const maxVal = maxKriteria[c] > 0 ? maxKriteria[c] : 1.0;

            // Kriteria Benefit: R_ij = X_ij / Max(X_j)
            const rIj = valX / maxVal;
            const wVal = bobotW[c] !== undefined ? bobotW[c] : 0.2;
            const terbobot = rIj * wVal;
            skorAkhir += terbobot;

            // Update ke tabel saw_nilai_pemeriksaan
            await pool.query(
                "UPDATE saw_nilai_pemeriksaan SET nilai_normalisasi = ?, nilai_terbobot = ? WHERE kunjungan_id = ? AND kriteria_id = ?",
                [rIj, terbobot, kid, c]
            );
        }

        // Tentukan Tingkat Prioritas berdasarkan Skor SAW (kecuali jika ada override medis)
        let prioritas = 'P3_Biasa';
        if (isOverrideMap[kid]) {
            prioritas = 'P1_Gawat_Darurat';
            if (skorAkhir < 0.95) skorAkhir = 0.9500;
        } else {
            if (skorAkhir >= 0.80) {
                prioritas = 'P1_Gawat_Darurat';
            } else if (skorAkhir >= 0.50) {
                prioritas = 'P2_Mendesak';
            } else {
                prioritas = 'P3_Biasa';
            }
        }

        // Update ke kunjungan_sakit
        await pool.query(
            "UPDATE kunjungan_sakit SET skor_saw_akhir = ?, tingkat_prioritas = ? WHERE id = ?",
            [skorAkhir, prioritas, kid]
        );
    }
}

module.exports = {
    jalankanKalkulasiSaw
};
