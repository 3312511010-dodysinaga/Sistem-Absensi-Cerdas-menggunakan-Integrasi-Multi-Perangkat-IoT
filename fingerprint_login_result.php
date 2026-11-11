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
// AMBIL DATA DARI ESP32
// ============================================================

$token = trim(
    $_POST['token'] ?? ''
);

$fingerprint_id = intval(
    $_POST['fingerprint_id'] ?? 0
);


// ============================================================
// VALIDASI TOKEN
// ============================================================

if ($token === '') {

    echo json_encode([
        "success" => false,
        "message" => "Token kosong"
    ]);

    exit;
}


// ============================================================
// CEK REQUEST LOGIN
// ============================================================

$stmt = $conn->prepare("
    SELECT id, status
    FROM fingerprint_login
    WHERE token = ?
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare cek token gagal: " . $conn->error
    ]);

    exit;
}

$stmt->bind_param("s", $token);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Gagal memeriksa token: " . $stmt->error
    ]);

    $stmt->close();
    exit;
}

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Request login tidak ditemukan"
    ]);

    exit;
}

$loginData = $result->fetch_assoc();

$stmt->close();


// ============================================================
// FINGERPRINT TIDAK DITEMUKAN
// ============================================================

if ($fingerprint_id <= 0) {

    $stmt = $conn->prepare("
        UPDATE fingerprint_login
        SET
            status = 'FAILED',
            message = 'Fingerprint tidak terdaftar'
        WHERE token = ?
    ");

    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "message" => "Prepare update gagal: " . $conn->error
        ]);

        exit;
    }

    $stmt->bind_param("s", $token);

    if (!$stmt->execute()) {

        echo json_encode([
            "success" => false,
            "message" => "Gagal menyimpan status gagal: " . $stmt->error
        ]);

        $stmt->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Fingerprint tidak terdaftar"
    ]);

    exit;
}


// ============================================================
// CARI PEGAWAI BERDASARKAN FINGERPRINT ID
// ============================================================

$stmt = $conn->prepare("
    SELECT
        id,
        nik,
        nama,
        jabatan,
        divisi,
        fingerprint_id
    FROM pegawai
    WHERE fingerprint_id = ?
      AND status = 'aktif'
    LIMIT 1
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare pencarian pegawai gagal: " . $conn->error
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $fingerprint_id
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" => "Gagal mencari pegawai: " . $stmt->error
    ]);

    $stmt->close();
    exit;
}

$result = $stmt->get_result();


// ============================================================
// FINGERPRINT TIDAK TERDAFTAR
// ============================================================

if (!$result || $result->num_rows === 0) {

    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE fingerprint_login
        SET
            fingerprint_id = ?,
            status = 'FAILED',
            message = 'Fingerprint tidak terdaftar atau pegawai tidak aktif'
        WHERE token = ?
    ");

    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "message" => "Prepare update gagal: " . $conn->error
        ]);

        exit;
    }

    $stmt->bind_param(
        "is",
        $fingerprint_id,
        $token
    );

    if (!$stmt->execute()) {

        echo json_encode([
            "success" => false,
            "message" => "Gagal menyimpan hasil gagal: " . $stmt->error
        ]);

        $stmt->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" =>
            "Fingerprint ID " .
            $fingerprint_id .
            " tidak terdaftar atau pegawai tidak aktif"
    ]);

    exit;
}


// ============================================================
// DATA PEGAWAI DITEMUKAN
// ============================================================

$pegawai = $result->fetch_assoc();

$stmt->close();

$pegawai_id = intval($pegawai['id']);

$nama = $pegawai['nama'];

$nik = $pegawai['nik'];


// ============================================================
// SIMPAN LOGIN SUCCESS
// ============================================================

$stmt = $conn->prepare("
    UPDATE fingerprint_login
    SET
        fingerprint_id = ?,
        pegawai_id = ?,
        status = 'SUCCESS',
        message = ?
    WHERE token = ?
");

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Prepare simpan login gagal: " . $conn->error
    ]);

    exit;
}

$stmt->bind_param(
    "iiss",
    $fingerprint_id,
    $pegawai_id,
    $nama,
    $token
);

if (!$stmt->execute()) {

    echo json_encode([
        "success" => false,
        "message" =>
            "Gagal menyimpan login: " .
            $stmt->error
    ]);

    $stmt->close();
    exit;
}

$stmt->close();


// ============================================================
// RESPONSE KE ESP32
// ============================================================

echo json_encode([

    "success" => true,

    "status" => "SUCCESS",

    "pegawai_id" => $pegawai_id,

    "fingerprint_id" => $fingerprint_id,

    "nama" => $nama,

    "nik" => $nik,

    "jabatan" => $pegawai['jabatan'],

    "message" => "Login berhasil"

]);

exit;
?>