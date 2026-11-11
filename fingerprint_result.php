<?php

require_once "../koneksi.php";

header("Content-Type: application/json; charset=UTF-8");


// ============================================================
// CEK METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        "success" => false,
        "message" => "Method harus POST"
    ]);

    exit;
}


// ============================================================
// AMBIL DATA
// ============================================================

$commandID = intval(
    $_POST['command_id'] ?? 0
);

$pegawaiID = intval(
    $_POST['pegawai_id'] ?? 0
);

$fingerprintID = intval(
    $_POST['fingerprint_id'] ?? 0
);

$status = strtolower(
    trim($_POST['status'] ?? '')
);

$message = trim(
    $_POST['message'] ?? ''
);


// ============================================================
// VALIDASI
// ============================================================

if ($commandID <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Command ID tidak valid"
    ]);

    exit;
}


if (
    $status !== 'success' &&
    $status !== 'failed'
) {

    echo json_encode([
        "success" => false,
        "message" => "Status tidak valid"
    ]);

    exit;
}


// ============================================================
// CEK COMMAND
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id,
        command_type,
        command,
        pegawai_id,
        fingerprint_id,
        status
    FROM fingerprint_commands
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare SELECT gagal: " . $conn->error
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
        "message" => "Gagal membaca command: " . $stmt->error
    ]);

    $stmt->close();

    exit;
}


$result = $stmt->get_result();


if (!$result || $result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Command tidak ditemukan"
    ]);

    exit;
}


$commandData = $result->fetch_assoc();

$stmt->close();


// Gunakan command_type sebagai sumber utama.
// Kolom command dipakai sebagai fallback untuk data lama.

$commandType = strtoupper(
    trim($commandData['command_type'] ?? '')
);

if (!in_array(
    $commandType,
    ['ENROLL', 'DELETE', 'DELETE_ALL'],
    true
)) {

    $commandType = strtoupper(
        trim($commandData['command'] ?? '')
    );
}


$pegawaiID = (int) (
    $commandData['pegawai_id'] ?? $pegawaiID
);

$fingerprintID = (int) (
    $commandData['fingerprint_id'] ?? $fingerprintID
);


// ============================================================
// MULAI TRANSAKSI
// ============================================================

$conn->begin_transaction();


// ============================================================
// UPDATE STATUS COMMAND
// ============================================================

$stmt = $conn->prepare("
    UPDATE fingerprint_commands
    SET
        status = ?,
        message = ?
    WHERE id = ?
");

if (!$stmt) {

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => "Prepare UPDATE gagal: " . $conn->error
    ]);

    exit;
}


$stmt->bind_param(
    "ssi",
    $status,
    $message,
    $commandID
);


if (!$stmt->execute()) {

    $errorMessage = $stmt->error;

    $stmt->close();

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => "Gagal update command: " . $errorMessage
    ]);

    exit;
}


$stmt->close();


// ============================================================
// JIKA DELETE BERHASIL
// KOSONGKAN FINGERPRINT ID DARI PEGAWAI
// ============================================================

if (
    $status === 'success' &&
    $commandType === 'DELETE'
) {

    $stmt = $conn->prepare("
        UPDATE pegawai
        SET fingerprint_id = NULL
        WHERE id = ?
          AND fingerprint_id = ?
    ");

    if (!$stmt) {

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" =>
                "Prepare hapus fingerprint pegawai gagal: "
                . $conn->error
        ]);

        exit;
    }


    $stmt->bind_param(
        "ii",
        $pegawaiID,
        $fingerprintID
    );


    if (!$stmt->execute()) {

        $errorMessage = $stmt->error;

        $stmt->close();

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" =>
                "Gagal mengosongkan fingerprint pegawai: "
                . $errorMessage
        ]);

        exit;
    }


    $stmt->close();
}


// ============================================================
// JIKA ENROLL BERHASIL
// SIMPAN FINGERPRINT ID KE PEGAWAI
// ============================================================

if (
    $status === 'success' &&
    $commandType === 'ENROLL'
) {

    $stmt = $conn->prepare("
        UPDATE pegawai
        SET fingerprint_id = ?
        WHERE id = ?
    ");

    if (!$stmt) {

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" =>
                "Prepare update fingerprint pegawai gagal: "
                . $conn->error
        ]);

        exit;
    }


    $stmt->bind_param(
        "ii",
        $fingerprintID,
        $pegawaiID
    );


    if (!$stmt->execute()) {

        $errorMessage = $stmt->error;

        $stmt->close();

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" =>
                "Fingerprint tersimpan di sensor, "
                . "tetapi database gagal diperbarui: "
                . $errorMessage
        ]);

        exit;
    }


    $stmt->close();
}


// ============================================================
// SELESAIKAN TRANSAKSI
// ============================================================

if (!$conn->commit()) {

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => "Gagal menyimpan perubahan database"
    ]);

    exit;
}


// ============================================================
// RESPONSE
// ============================================================

echo json_encode([
    "success" => true,
    "message" => "Hasil fingerprint berhasil diproses",
    "command_id" => $commandID,
    "status" => $status,
    "command" => $commandType
]);

exit;