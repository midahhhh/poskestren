<?php
// api_chat.php - Handler AJAX untuk fitur chat CS Poskestren
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Buat tabel jika belum ada
if ($db_connected && $conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS cs_messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        session_key VARCHAR(64)  NOT NULL,
        nama_pengirim VARCHAR(100) DEFAULT 'Pengunjung',
        pesan       TEXT         NOT NULL,
        balasan     TEXT         DEFAULT NULL,
        dari_cs     TINYINT(1)   DEFAULT 0,
        is_read     TINYINT(1)   DEFAULT 0,
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_key),
        INDEX idx_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ════════════════════════════════════════════════════════════════════
// MESIN ASISTEN VIRTUAL AI CERDAS POSKESTREN AL-BAHJAH BOGOR
// ════════════════════════════════════════════════════════════════════
function get_ai_poskestren_reply($pesan, $conn) {
    $pesan_lower = strtolower(trim($pesan));

    // 1. Integrasi Google Gemini API jika Environment Variable tersedia
    $api_key = getenv('GEMINI_API_KEY') ?: (getenv('GOOGLE_API_KEY') ?: '');
    if (!empty($api_key) && function_exists('curl_init')) {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key);
        $system_prompt = "Anda adalah Asisten Virtual Medis & Layanan Poskestren Al-Bahjah Cabang Bogor. Berikan respon yang sopan, ramah, menenangkan, islami, dan akurat dalam bahasa Indonesia (ringkas 2-3 paragraf). Informasi kunci: Poskestren Al-Bahjah Bogor melayani triase SPK SAW (P1 Darurat, P2 Mendesak, P3 Biasa), dokter umum, rawat inap santri, obat farmasi, dan izin sakit belajar. Kontak darurat 24 jam: 0811-2233-4455.";
        $payload = json_encode([
            "contents" => [
                ["role" => "user", "parts" => [["text" => "$system_prompt\n\nPertanyaan Pengguna: $pesan"]]]
            ],
            "generationConfig" => ["maxOutputTokens" => 350, "temperature" => 0.3]
        ]);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
            CURLOPT_TIMEOUT        => 4
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $res) {
            $data_gemini = json_decode($res, true);
            $candidate = $data_gemini['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!empty($candidate)) {
                return trim($candidate);
            }
        }
    }

    // 2. Natural Language Processing & Knowledge Engine Poskestren Al-Bahjah
    // Cek Antrean Pasien Real-time Hari Ini
    if (preg_match('/(antre|antrian|berapa pasien|antrean saat ini|ada berapa santri|pasien hari ini)/i', $pesan_lower)) {
        try {
            if ($conn && @$conn->ping()) {
                $q_antrean = @$conn->query("SELECT COUNT(*) as tot, 
                                                  SUM(CASE WHEN tingkat_prioritas='P1_Gawat_Darurat' OR skor_saw_akhir>=0.80 THEN 1 ELSE 0 END) as p1,
                                                  SUM(CASE WHEN tingkat_prioritas='P2_Mendesak' OR (skor_saw_akhir>=0.50 AND skor_saw_akhir<0.80) THEN 1 ELSE 0 END) as p2,
                                                  SUM(CASE WHEN tingkat_prioritas='P3_Biasa' AND skor_saw_akhir<0.50 THEN 1 ELSE 0 END) as p3
                                           FROM kunjungan_sakit 
                                           WHERE status_antrean IN ('menunggu_triase','pemeriksaan')");
                if ($q_antrean) {
                    $data_a = $q_antrean->fetch_assoc();
                    $tot = (int)($data_a['tot'] ?? 0);
                    $p1  = (int)($data_a['p1'] ?? 0);
                    $p2  = (int)($data_a['p2'] ?? 0);
                    $p3  = (int)($data_a['p3'] ?? 0);

                    if ($tot > 0) {
                        return "Saat ini di Poskestren Al-Bahjah Bogor terdapat **{$tot} santri** dalam antrean pemeriksaan aktif:\n" .
                               "• **P1 (Gawat Darurat):** {$p1} santri\n" .
                               "• **P2 (Mendesak):** {$p2} santri\n" .
                               "• **P3 (Kondisi Biasa):** {$p3} santri\n\n" .
                               "Bagi santri yang merasa kurang sehat, dipersilakan melapor ke musyrif asrama dan segera menuju ruang triase poskestren.";
                    }
                }
            }
        } catch (Throwable $e) {
            // Abaikan error koneksi fallback ke pesan default
        }
        return "Alhamdulillah, antrean layanan Poskestren Al-Bahjah Bogor saat ini kondusif dan lancar. Petugas triase SAW dan dokter siaga melayani santri setiap hari.";
    }

    // Cek Jam Operasional
    if (preg_match('/(jam|buka|tutup|operasional|jadwal|hari apa|kapan)/i', $pesan_lower)) {
        return "Poskestren Al-Bahjah Cabang Bogor beroperasi **24 Jam Siaga (Senin – Ahad)** untuk pelayanan kesehatan santri.\n\n" .
               "Rincian pelayanan:\n" .
               "• **Triase & Gawat Darurat:** 24 Jam Non-Stop\n" .
               "• **Pemeriksaan Poli Dokter:** 08.00 – 21.00 WIB setiap hari\n" .
               "• **Rawat Inap Observasi:** 24 Jam dalam pengawasan nakes\n" .
               "• **Unit Farmasi Obat:** 24 Jam mengikuti resep dokter\n" .
               "• **Hotline Darurat:** 0811-2233-4455";
    }

    // Cek Kontak & Darurat
    if (preg_match('/(kontak|telepon|nomor hp|\bwa\b|whatsapp|darurat|call center|hubungi|telp|alamat|lokasi)/i', $pesan_lower)) {
        return "Informasi kontak dan lokasi resmi Poskestren Al-Bahjah Cabang Bogor:\n\n" .
               "📞 **Hotline Darurat / CS:** 0811-2233-4455\n" .
               "📍 **Alamat:** Kompleks Kampus Pondok Pesantren Al-Bahjah Cabang Bogor, Jawa Barat\n" .
               "⏱️ **Waktu Pelayanan:** 24 Jam Siaga Medis Santri\n\n" .
               "Untuk santri yang mengalami keluhan sakit di asrama, musyrif atau teman sekamar dapat segera menghubungi nomor darurat di atas.";
    }

    // Cek Metode SPK SAW & Triase
    if (preg_match('/(\bsaw\b|triase|kriteria|metode|spk|bobot|prioritas|p1|p2|p3|ranking|override)/i', $pesan_lower)) {
        return "Sistem Triase Poskestren Al-Bahjah menggunakan metode **Simple Additive Weighting (SAW)** dengan 5 kriteria klinis terbobot:\n\n" .
               "1. **C1 - Suhu Tubuh (25%):** Deteksi demam dan hipertermia santri.\n" .
               "2. **C2 - Skala Nyeri 1–10 (20%):** Evaluasi intensitas rasa sakit fisik.\n" .
               "3. **C3 - Riwayat Komorbid (15%):** Riwayat komplikasi/penyakit bawaan santri.\n" .
               "4. **C4 - Durasi Sakit (15%):** Berapa lama santri telah merasakan gejala.\n" .
               "5. **C5 - Tanda Vital & Kesadaran (25%):** Tensi darah, nadi, respirasi, SpO2, dan kesadaran (GCS).\n\n" .
               "Klasifikasi antrean:\n" .
               "• **P1 (Gawat Darurat, Skor ≥ 0.80):** Tindakan segera tanpa menunggu.\n" .
               "• **P2 (Mendesak, Skor 0.50–0.79):** Pengawasan dan antrean prioritas.\n" .
               "• **P3 (Biasa, Skor < 0.50):** Penanganan reguler rawat jalan.\n" .
               "*(Dilengkapi tombol **Emergency Override** jika kondisi santri kritis dan butuh bypass antrean langsung ke meja tindakan).*";
    }

    // Cek Portal Wali Santri & Surat Izin Sakit
    if (preg_match('/(wali|orang tua|portal|surat|izin|istirahat|login|resep|kbm|pantau)/i', $pesan_lower)) {
        return "Orang tua / wali santri dapat memantau kesehatan ananda melalui **Portal Wali Santri**:\n\n" .
               "1. Masuk ke halaman login melalui tombol **Portal Wali** di menu utama.\n" .
               "2. Login menggunakan **Nomor WhatsApp terdaftar** atau **NIS Santri**.\n" .
               "3. Melalui portal, wali santri dapat:\n" .
               "   • Memantau diagnosis dokter dan riwayat kunjungan medis.\n" .
               "   • Melihat status observasi jika santri dirawat inap di poskestren.\n" .
               "   • Mengunduh **Surat Keterangan Istirahat Belajar (Surat Sakit)** resmi untuk kurikulum/asrama.";
    }

    // Gejala: Demam / Panas
    if (preg_match('/(demam|panas|menggigil|suhu tinggi|meriang)/i', $pesan_lower)) {
        return "Pertolongan pertama untuk santri yang mengalami **demam / panas**:\n\n" .
               "1. **Kompres Hangat:** Berikan kompres air hangat suam-suam kuku pada dahi, ketiak, atau lipatan paha santri.\n" .
               "2. **Perbanyak Minum:** Anjurkan minum air putih hangat secara teratur untuk mencegah dehidrasi.\n" .
               "3. **Pakaian Ringan:** Gunakan pakaian berbahan katun yang menyerap keringat.\n" .
               "4. **Pemeriksaan Dokter:** Segera bawa santri ke Poskestren Al-Bahjah untuk pengukuran suhu klinis dan pemberian parasetamol sesuai dosis dokter.";
    }

    // Gejala: Batuk / Pilek / Flu
    if (preg_match('/(batuk|pilek|flu|bersin|tenggorokan|radang|ingus)/i', $pesan_lower)) {
        return "Anjuran perawatan santri dengan keluhan **batuk, pilek, atau flu**:\n\n" .
               "1. Istirahat yang cukup dan hindari minuman dingin/es serta gorengan berminyak.\n" .
               "2. Perbanyak minum air hangat atau air perasan lemon/madu.\n" .
               "3. Gunakan masker medis di asrama agar tidak menularkan ke santri sekamar.\n" .
               "4. Segera periksakan ke Poskestren Al-Bahjah agar dokter dapat memberikan obat batuk/sirup ekspektoran yang tepat.";
    }

    // Gejala: Pusing / Sakit Kepala
    if (preg_match('/(pusing|sakit kepala|migrain|kliyengan|vertigo)/i', $pesan_lower)) {
        return "Panduan santri yang mengeluhkan **pusing atau sakit kepala**:\n\n" .
               "1. Baringkan santri di tempat yang tenang, teduh, dan berventilasi baik.\n" .
               "2. Pastikan santri sudah makan dan tidak terlambat jadwal santap asrama.\n" .
               "3. Berikan minum air putih yang cukup.\n" .
               "4. Antar ke Poskestren Al-Bahjah untuk pemeriksaan tekanan darah (tensi) dan pemeriksaan oleh dokter jaga.";
    }

    // Gejala: Sakit Perut / Maag / Diare
    if (preg_match('/(perut|lambung|maag|mual|muntah|diare|mencret|begah)/i', $pesan_lower)) {
        return "Pertolongan pertama keluhan **sakit perut / maag / diare** santri:\n\n" .
               "1. **Diare/Muntah:** Berikan oralit atau perbanyak minum air putih agar tidak lemas atau dehidrasi.\n" .
               "2. **Maag/Nyeri Lambung:** Kompres hangat perut yang kram dan hindari makanan pedas atau asam.\n" .
               "3. **Pemeriksaan Segera:** Bawa santri ke Poskestren Al-Bahjah untuk evaluasi dokter dan pengambilan antasida atau obat pencernaan dari unit farmasi.";
    }

    // Gejala: Gatal / Scabies / Alergi Kulit
    if (preg_match('/(gatal|alergi|scabies|gudik|biduran|bintik|kulit)/i', $pesan_lower)) {
        return "Penanganan santri dengan keluhan **gatal-gatal / scabies / alergi kulit** di asrama:\n\n" .
               "1. Cuci pakaian, handuk, dan sprei santri secara bersih, lalu jemur kasur di bawah terik matahari.\n" .
               "2. Hindari kebiasaan saling meminjam pakaian atau handuk antar-santri.\n" .
               "3. Kunjungi Poskestren Al-Bahjah untuk mendapatkan salep antiskabies (permetrin) dan obat antihistamin pereda gatal dari dokter.";
    }

    // Gejala: Sesak Napas / Asma
    if (preg_match('/(sesak|asma|napas|mengi|oksigen|sesek)/i', $pesan_lower)) {
        return "⚠️ **PERHATIAN DARURAT (P1_Gawat_Darurat):**\n\n" .
               "Keluhan **sesak napas atau kambuh asma** membutuhkan pertolongan cepat:\n" .
               "1. Posisikan santri duduk tegak dan rileks, jangan biarkan santri berkerumun.\n" .
               "2. Longgarkan pakaian yang ketat dan pastikan sirkulasi udara bebas.\n" .
               "3. **SEGERA antarkan santri ke Poskestren Al-Bahjah** untuk pemberian bantuan terapi oksigen medis (SpO2) dan nebulizer oleh dokter/nakes siaga.";
    }

    // Salam & Ucapan Terima Kasih
    if (preg_match('/(assalamu|halo|hai|pagi|siang|sore|malam|permisi|terima kasih|syukron|makasih)/i', $pesan_lower)) {
        return "Wa'alaikumussalam Warahmatullahi Wabarakatuh.\n\n" .
               "Selamat datang di Layanan Asisten Virtual AI Poskestren Al-Bahjah Cabang Bogor. Saya siap membantu menjawab pertanyaan seputar jam operasional, antrean hari ini, sistem triase SAW, atau saran pertolongan pertama santri sakit.";
    }

    // Jawaban Umum (Default Cerdas)
    return "Terima kasih atas pesan Anda kepada Layanan Poskestren Al-Bahjah Cabang Bogor.\n\n" .
           "Asisten AI kami siap memberikan informasi seputar:\n" .
           "• **Jam Pelayanan & Dokter:** Siaga 24 Jam setiap hari.\n" .
           "• **Kondisi Antrean:** Informasi antrean santri aktif hari ini.\n" .
           "• **Triase Medis SAW:** Penjelasan kriteria prioritas P1, P2, dan P3.\n" .
           "• **Portal Wali Santri:** Cara memantau kesehatan anak dan surat izin sakit.\n" .
           "• **Pertolongan Pertama:** Panduan demam, batuk, pusing, sakit perut, scabies, dsb.\n\n" .
           "Jika membutuhkan bantuan darurat santri di asrama, silakan hubungi hotline siaga kami di **0811-2233-4455**.";
}

// ── Pengunjung kirim pesan ──────────────────────────────────
if ($action === 'send') {
    $pesan       = trim($_POST['pesan'] ?? '');
    $session_key = trim($_POST['session_key'] ?? session_id());
    $nama        = trim($_POST['nama'] ?? 'Pengunjung');

    if (empty($pesan)) {
        echo json_encode(['ok' => false, 'msg' => 'Pesan kosong']);
        exit;
    }

    // 1. Simpan pesan pengunjung
    $stmt = $conn->prepare("INSERT INTO cs_messages (session_key, nama_pengirim, pesan, dari_cs) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("sss", $session_key, $nama, $pesan);
    $stmt->execute();
    $user_msg_id = $conn->insert_id;

    // 2. Buat balasan cerdas otomatis dengan Asisten AI Poskestren
    $ai_reply = get_ai_poskestren_reply($pesan, $conn);
    $ai_sender = 'Asisten AI Poskestren';

    // 3. Simpan balasan AI ke database agar terekam dalam log obrolan
    $stmt_ai = $conn->prepare("INSERT INTO cs_messages (session_key, nama_pengirim, pesan, dari_cs, is_read) VALUES (?, ?, ?, 1, 1)");
    $stmt_ai->bind_param("sss", $session_key, $ai_sender, $ai_reply);
    $stmt_ai->execute();
    $ai_msg_id = $conn->insert_id;

    echo json_encode([
        'ok'       => true,
        'id'       => $ai_msg_id,
        'user_id'  => $user_msg_id,
        'reply'    => $ai_reply,
        'sender'   => $ai_sender,
        'time'     => date('H:i')
    ]);
    exit;
}

// ── Pengunjung ambil balasan baru ───────────────────────────
if ($action === 'poll') {
    $session_key = trim($_GET['session_key'] ?? '');
    $last_id     = (int)($_GET['last_id'] ?? 0);

    if (empty($session_key)) {
        echo json_encode(['ok' => false, 'messages' => []]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, pesan, dari_cs, created_at FROM cs_messages
                            WHERE session_key = ? AND id > ? AND dari_cs = 1
                            ORDER BY id ASC");
    $stmt->bind_param("si", $session_key, $last_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $messages = [];
    while ($r = $res->fetch_assoc()) {
        $messages[] = [
            'id'    => $r['id'],
            'pesan' => $r['pesan'],
            'time'  => date('H:i', strtotime($r['created_at']))
        ];
    }
    echo json_encode(['ok' => true, 'messages' => $messages]);
    exit;
}

// ── CS: ambil semua percakapan (dashboard) ──────────────────
if ($action === 'list') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false]); exit;
    }
    // Kelompokkan per session_key, ambil pesan terakhir
    $res = $conn->query("SELECT m.session_key, m.nama_pengirim,
                                MAX(m.created_at) as last_time,
                                SUM(CASE WHEN m.is_read=0 AND m.dari_cs=0 THEN 1 ELSE 0 END) as unread,
                                (SELECT pesan FROM cs_messages m2
                                 WHERE m2.session_key = m.session_key
                                 ORDER BY m2.id DESC LIMIT 1) as last_msg
                         FROM cs_messages m
                         GROUP BY m.session_key, m.nama_pengirim
                         ORDER BY last_time DESC
                         LIMIT 30");
    $chats = [];
    while ($r = $res->fetch_assoc()) $chats[] = $r;
    echo json_encode(['ok' => true, 'chats' => $chats]);
    exit;
}

// ── CS: ambil profil pengirim dari session_key ──────────────
if ($action === 'profile') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false]); exit;
    }
    $session_key = trim($_GET['session_key'] ?? '');
    $profile     = ['tipe' => 'pengunjung', 'nama' => 'Pengunjung Anonim', 'detail' => []];

    // Cek apakah session_key punya format wali_{id}_ (dari wali.php)
    if (preg_match('/^wali_(\d+)_/i', $session_key, $m)) {
        $uid  = (int)$m[1];
        $stmt = $conn->prepare(
            "SELECT u.id, u.nama_lengkap, u.username, u.no_hp, u.role,
                    s.nis, s.nama_lengkap as nama_santri,
                    s.kamar_asrama, s.kompleks_asrama
             FROM users u
             LEFT JOIN santri s ON s.user_id_wali = u.id
             WHERE u.id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $profile = [
                'tipe'   => 'wali_santri',
                'nama'   => $row['nama_lengkap'],
                'detail' => [
                    'Username'    => '@' . $row['username'],
                    'No. HP/WA'   => $row['no_hp'] ?: '-',
                    'NIS Santri'  => $row['nis'] ?: '-',
                    'Nama Santri' => $row['nama_santri'] ?: '-',
                    'Asrama'      => ($row['kamar_asrama'] && $row['kompleks_asrama'])
                                     ? $row['kamar_asrama'] . ' — ' . $row['kompleks_asrama'] : '-',
                ]
            ];
        }
    }

    // Statistik pesan sesi ini
    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) as total, SUM(dari_cs=0) as dari_user,
                MIN(created_at) as first_msg, MAX(created_at) as last_msg
         FROM cs_messages WHERE session_key = ?"
    );
    $stmt2->bind_param("s", $session_key);
    $stmt2->execute();
    $profile['stats'] = $stmt2->get_result()->fetch_assoc();

    echo json_encode(['ok' => true, 'profile' => $profile]);
    exit;
}
if ($action === 'thread') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false]); exit;
    }
    $session_key = trim($_GET['session_key'] ?? '');
    // Tandai sudah dibaca
    $stmt = $conn->prepare("UPDATE cs_messages SET is_read=1 WHERE session_key=? AND dari_cs=0");
    $stmt->bind_param("s", $session_key);
    $stmt->execute();

    $stmt2 = $conn->prepare("SELECT id, pesan, dari_cs, nama_pengirim, created_at FROM cs_messages
                              WHERE session_key=? ORDER BY id ASC");
    $stmt2->bind_param("s", $session_key);
    $stmt2->execute();
    $res  = $stmt2->get_result();
    $msgs = [];
    while ($r = $res->fetch_assoc()) $msgs[] = $r;
    echo json_encode(['ok' => true, 'messages' => $msgs]);
    exit;
}

// ── CS: kirim balasan ──────────────────────────────────────
if ($action === 'reply') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false]); exit;
    }
    $session_key = trim($_POST['session_key'] ?? '');
    $pesan       = trim($_POST['pesan'] ?? '');
    $nama_cs     = $_SESSION['nama_lengkap'] ?? 'CS Poskestren';

    if (empty($pesan) || empty($session_key)) {
        echo json_encode(['ok' => false]); exit;
    }

    $stmt = $conn->prepare("INSERT INTO cs_messages (session_key, nama_pengirim, pesan, dari_cs, is_read) VALUES (?, ?, ?, 1, 1)");
    $stmt->bind_param("sss", $session_key, $nama_cs, $pesan);
    $stmt->execute();
    echo json_encode(['ok' => true, 'id' => $conn->insert_id, 'time' => date('H:i'), 'nama' => $nama_cs]);
    exit;
}

// ── CS: hitung badge unread ────────────────────────────────
if ($action === 'unread_count') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['count' => 0]); exit;
    }
    $res = $conn->query("SELECT COUNT(*) as c FROM cs_messages WHERE is_read=0 AND dari_cs=0");
    echo json_encode(['count' => (int)$res->fetch_assoc()['c']]);
    exit;
}

if (!empty($action)) {
    echo json_encode(['ok' => false, 'msg' => 'Action tidak dikenal']);
}
