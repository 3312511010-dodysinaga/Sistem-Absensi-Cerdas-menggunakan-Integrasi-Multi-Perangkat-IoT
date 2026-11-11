<?php

require_once "../koneksi.php";

header("Content-Type: application/json; charset=UTF-8");


// ============================================================
// CARI LOGIN WAITING
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id,
        token
    FROM fingerprint_login
    WHERE status = 'WAITING'
    ORDER BY id ASC
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Prepare SELECT gagal: " . $conn->error,
        "command" => null
    ]);

    exit;
}

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Query login gagal: " . $stmt->error,
        "command" => null
    ]);

    $stmt->close();
    exit;
}

$result = $stmt->get_result();


// ============================================================
// TIDAK ADA REQUEST
// ============================================================

if (!$result || $result->num_rows === 0) {

    echo json_encode([
        "success" => true,
        "command" => null
    ]);

    $stmt->close();
    exit;
}


// ============================================================
// AMBIL REQUEST
// ============================================================

$data = $result->fetch_assoc();

$id = (int) $data["id"];

$token = $data["token"];

$stmt->close();


// ============================================================
// UBAH WAITING -> PROCESSING
// ============================================================

$stmt = $conn->prepare("
    UPDATE fingerprint_login
    SET status = 'PROCESSING'
    WHERE id = ?
      AND status = 'WAITING'
");

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Prepare UPDATE gagal: " . $conn->error,
        "command" => null
    ]);

    exit;
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengubah status login: " . $stmt->error,
        "command" => null
    ]);

    $stmt->close();
    exit;
}

// Pastikan request benar-benar berhasil diambil.
if ($stmt->affected_rows !== 1) {
    echo json_encode([
        "success" => true,
        "command" => null,
        "message" => "Request login sudah diproses atau tidak tersedia"
    ]);

    $stmt->close();
    exit;
}

$stmt->close();


// ============================================================
// KIRIM COMMAND KE ESP32
// ============================================================

echo json_encode([
    "success" => true,

    "command" => [
        "id" => $id,
        "token" => $token,
        "status" => "PROCESSING"
    ]
]);

exit;