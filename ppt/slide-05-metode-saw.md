# Slide 5 — Metode SAW

---

## Simple Additive Weighting (SAW)

Metode pengambilan keputusan multikriteria yang digunakan untuk menentukan **urutan prioritas penanganan** santri sakit berdasarkan skor akhir tertinggi.

---

## 5 Kriteria Klinis

| Kode | Kriteria | Bobot | Skala |
|------|----------|:-----:|:-----:|
| C1 | Suhu Tubuh | **30%** | 1–5 |
| C2 | Skala Nyeri / Keparahan | **25%** | 1–5 |
| C3 | Riwayat Komorbid / Alergi | **20%** | 1–5 |
| C4 | Durasi Sakit | **15%** | 1–5 |
| C5 | Vitalitas & Tanda-Tanda Vital (TTV) | **10%** | 1–5 |
| | **Total** | **100%** | |

---

## Formula Perhitungan

**Normalisasi:**
```
R(ij) = X(ij) / max(X(j))
```

**Skor Preferensi Akhir:**
```
V(i) = 0.30×R1 + 0.25×R2 + 0.20×R3 + 0.15×R4 + 0.10×R5
```

---

## Klasifikasi Prioritas Hasil SAW

| Kode | Label | Skor SAW | Tindakan |
|------|-------|:--------:|---------|
| 🔴 P1 | **Gawat Darurat** | V ≥ 0.80 | Tangani segera |
| 🟡 P2 | **Mendesak** | 0.50 ≤ V < 0.80 | Dalam 30 menit |
| 🟢 P3 | **Biasa / Ringan** | V < 0.50 | Antrian normal |

---

## Emergency Override

> Dokter dapat menetapkan **P1 secara manual** tanpa menunggu kalkulasi SAW untuk kondisi yang mengancam jiwa santri secara langsung (syok, kejang, tidak sadar).
