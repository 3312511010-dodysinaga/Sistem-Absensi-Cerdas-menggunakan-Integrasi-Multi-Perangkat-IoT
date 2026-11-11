<?php

require_once "../koneksi.php";

header("Content-Type: application/json; charset=UTF-8");


// ============================================================
// AMBIL TOKEN
// ============================================================

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    echo json_encode([
        "success" => false,
        "status" => "FAILED",
        "message" => "Token kosong"
    ]);
    exit;
}


// ============================================================
// CARI STATUS LOGIN
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id,
        pegawai_id,
        fingerprint_id,
        status,
        message
    FROM fingerprint_login
    WHERE token = ?
    ORDER BY id DESC
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "status" => "FAILED",
        "message" => "Prepare SELECT gagal: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $token);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "status" => "FAILED",
        "message" => "Gagal membaca status login: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();


// ============================================================
// SESSION TIDAK DITEMUKAN
// ============================================================

if (!$result || $result->num_rows === 0) {
    $stmt->close();

    echo json_encode([
        "success" => false,
        "status" => "FAILED",
        "message" => "Session tidak ditemukan"
    ]);
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();


// ============================================================
// NORMALISASI STATUS
// ============================================================

$status = strtoupper(trim($data['status'] ?? ''));

if ($status === '') {
    $status = "WAITING";
}


// ============================================================
// RESPONSE
// ============================================================

echo json_encode([
    "success" => true,
    "status" => $status,
    "message" => $data['message'] ?? "Menunggu fingerprint",
    "pegawai_id" => (int)($data['pegawai_id'] ?? 0),
    "fingerprint_id" => (int)($data['fingerprint_id'] ?? 0)
]);

exit;