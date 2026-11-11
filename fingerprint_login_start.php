<?php

require_once "../koneksi.php";

header("Content-Type: application/json; charset=UTF-8");


// ============================================================
// CEK METHOD
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        "success" => false,
        "message" => "Method tidak valid"
    ]);

    exit;
}


// ============================================================
// AMBIL TOKEN
// ============================================================

$token = trim(
    $_POST['token'] ?? ''
);


if ($token === '') {

    echo json_encode([
        "success" => false,
        "message" => "Token kosong"
    ]);

    exit;
}


// ============================================================
// HAPUS LOGIN LAMA
// ============================================================

$hapusLama = $conn->query("
    DELETE FROM fingerprint_login
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
      AND status IN ('SUCCESS', 'FAILED')
");

if (!$hapusLama) {

    echo json_encode([
        "success" => false,
        "message" => "Gagal membersihkan login lama: " . $conn->error
    ]);

    exit;
}


// ============================================================
// CEK TOKEN SUDAH ADA ATAU BELUM
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id,
        status
    FROM fingerprint_login
    WHERE token = ?
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
    "s",
    $token
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Query SELECT gagal: " . $stmt->error
    ]);

    $stmt->close();
    exit;
}

$result = $stmt->get_result();


if ($result && $result->num_rows > 0) {

    $login = $result->fetch_assoc();

    $statusLama = strtoupper(trim($login['status'] ?? ''));

    $stmt->close();

    // Token lama yang sudah selesai tidak digunakan kembali.
    if (
        $statusLama === 'SUCCESS' ||
        $statusLama === 'FAILED'
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Token login sudah selesai. Muat ulang halaman login."
        ]);

        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Login session sudah tersedia",
        "status" => $statusLama
    ]);

    exit;
}

$stmt->close();


// ============================================================
// BUAT REQUEST LOGIN
// ============================================================

$stmt = $conn->prepare("
    INSERT INTO fingerprint_login
    (
        token,
        fingerprint_id,
        pegawai_id,
        status,
        message
    )
    VALUES
    (
        ?,
        NULL,
        NULL,
        'WAITING',
        'Menunggu fingerprint'
    )
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare INSERT gagal: " . $conn->error
    ]);

    exit;
}

$stmt->bind_param(
    "s",
    $token
);


if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Menunggu fingerprint",
        "status" => "WAITING"
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Gagal membuat request login: " . $stmt->error
    ]);

}

$stmt->close();

exit;
?>