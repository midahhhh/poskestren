// helpers/formatters.js - Formatters untuk tanggal, badge triase SAW, dan URL WhatsApp
function tglIndo(datetime, withTime = true) {
    if (!datetime || datetime === '0000-00-00 00:00:00') return '-';
    const date = new Date(datetime);
    if (isNaN(date.getTime())) return '-';

    const bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    const d = String(date.getDate()).padStart(2, '0');
    const m = bulan[date.getMonth()];
    const y = date.getFullYear();
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');

    return withTime ? `${d} ${m} ${y}, ${h}:${min} WIB` : `${d} ${m} ${y}`;
}

function badgeTriase(tingkatPrioritas, skor = null) {
    const s = skor !== null ? parseFloat(skor) : null;
    if (tingkatPrioritas === 'P1_Gawat_Darurat' || (s !== null && s >= 0.80)) {
        return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    P1 - DARURAT
                </span>`;
    } else if (tingkatPrioritas === 'P2_Mendesak' || (s !== null && s >= 0.50)) {
        return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    P2 - MENDESAK
                </span>`;
    } else {
        return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    P3 - BIASA
                </span>`;
    }
}

function buildWaUrl(nama, nis, kamar, keluhan, prioritas, skor, status) {
    const prioritasTxt = (prioritas || '').replace(/_/g, ' ');
    const statusTxt = (status || '').replace(/_/g, ' ');
    const skorTxt = (parseFloat(skor) || 0).toFixed(3);

    const pesan = `Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n`
        + `Yth. Petugas Siaga Medis Poskestren Al-Bahjah Bogor,\n\n`
        + `Saya adalah orang tua / wali dari ananda:\n`
        + `• Nama Santri : ${nama || '-'}\n`
        + `• NIS : ${nis || '-'}\n`
        + `• Asrama : ${kamar || '-'}\n\n`
        + `Berdasarkan pemantauan pada Sistem SPK Poskestren, ananda saat ini tercatat sedang sakit:\n`
        + `• Keluhan Utama : ${keluhan || '-'}\n`
        + `• Tingkat Triase : ${prioritasTxt} (Skor SAW: ${skorTxt})\n`
        + `• Status Penanganan : ${statusTxt}\n\n`
        + `Mohon informasi perkembangan kondisi ananda terkini dan apakah kami diperkenankan untuk melakukan panggilan telepon / video call dengan ananda. Terima kasih banyak atas perhatian dan perawatannya.\n\n`
        + `Wassalamu'alaikum Wr. Wb.`;

    return `https://api.whatsapp.com/send?phone=6281122334455&text=${encodeURIComponent(pesan)}`;
}

module.exports = {
    tglIndo,
    badgeTriase,
    buildWaUrl
};
