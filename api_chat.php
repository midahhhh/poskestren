<?php
// api_chat.php - Handler AJAX untuk fitur chat CS Poskestren
header('Content-Type: application/json');
session_start();
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

// ── Pengunjung kirim pesan ──────────────────────────────────
if ($action === 'send') {
    $pesan       = trim($_POST['pesan'] ?? '');
    $session_key = trim($_POST['session_key'] ?? session_id());
    $nama        = trim($_POST['nama'] ?? 'Pengunjung');

    if (empty($pesan)) {
        echo json_encode(['ok' => false, 'msg' => 'Pesan kosong']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO cs_messages (session_key, nama_pengirim, pesan, dari_cs) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("sss", $session_key, $nama, $pesan);
    $stmt->execute();
    $new_id = $conn->insert_id;

    echo json_encode(['ok' => true, 'id' => $new_id, 'time' => date('H:i')]);
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

echo json_encode(['ok' => false, 'msg' => 'Action tidak dikenal']);
