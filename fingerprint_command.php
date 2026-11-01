<?php

require_once "../koneksi.php";

header("Content-Type: application/json; charset=UTF-8");


// ============================================================
// AMBIL COMMAND PENDING
// ============================================================

$sql = "
    SELECT
        id,
        command_type,
        command,
        fingerprint_id,
        pegawai_id,
        status
    FROM fingerprint_commands
    WHERE status = 'pending'
    ORDER BY id ASC
    LIMIT 1
";

$result = $conn->query($sql);

if (!$result) {

    echo json_encode([
        "success" => false,
        "message" => "Query command gagal: " . $conn->error,
        "command" => null
    ]);

    exit;
}


// ============================================================
// TIDAK ADA COMMAND
// ============================================================

if ($result->num_rows === 0) {

    echo json_encode([
        "success" => true,
        "command" => null
    ]);

    exit;
}


$row = $result->fetch_assoc();

$commandID     = (int) $row['id'];
$pegawaiID     = (int) $row['pegawai_id'];
$fingerprintID = (int) $row['fingerprint_id'];

/*
 * Gunakan command_type sebagai sumber perintah utama,
 * karena kolom ini berisi ENROLL atau DELETE.
 *
 * Kolom command tetap diambil agar struktur query lama
 * tidak perlu diubah.
 */
$command = strtoupper(trim($row['command_type']));


// ============================================================
// VALIDASI COMMAND
// ============================================================

if (!in_array(
    $command,
    ['ENROLL', 'DELETE', 'DELETE_ALL'],
    true
)) {

    echo json_encode([
        "success" => false,
        "message" => "Jenis command tidak valid: " . $command,
        "command" => null
    ]);

    exit;
}


// ============================================================
// UBAH PENDING -> PROCESSING
// ============================================================

$stmt = $conn->prepare("
    UPDATE fingerprint_commands
    SET status = 'processing'
    WHERE id = ?
      AND status = 'pending'
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare update gagal: " . $conn->error,
        "command" => null
    ]);

    exit;
}


$stmt->bind_param(
    "i",
    $commandID
);


if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengubah status processing: " . $stmt->error,
        "command" => null
    ]);

    $stmt->close();

    exit;
}


// Pastikan command benar-benar berhasil dikunci
if ($stmt->affected_rows !== 1) {

    $stmt->close();

    echo json_encode([
        "success" => true,
        "command" => null
    ]);

    exit;
}


$stmt->close();


// ============================================================
// KIRIM COMMAND KE ESP32
// ============================================================

echo json_encode([
    "success" => true,

    "command" => [
        "id" => $commandID,

        "type" => $command,

        "pegawai_id" => $pegawaiID,

        "fingerprint_id" => $fingerprintID
    ]
]);

exit;