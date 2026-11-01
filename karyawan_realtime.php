<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekKaryawan();

header("Content-Type: application/json; charset=utf-8");

date_default_timezone_set("Asia/Jakarta");


/* =========================================================
   PEGAWAI LOGIN
========================================================= */

$pegawai_id = intval($_SESSION['pegawai_id'] ?? 0);

if ($pegawai_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Session karyawan tidak valid."
    ]);

    exit;
}


/* =========================================================
   WAKTU SEKARANG
========================================================= */

$now = new DateTime();

$today = $now->format("Y-m-d");
$yesterday = (clone $now)
    ->modify("-1 day")
    ->format("Y-m-d");

$currentTime = $now->format("H:i:s");


/* =========================================================
   DATA PEGAWAI
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nik,
        nama,
        jabatan,
        divisi,
        no_hp,
        fingerprint_id,
        status
    FROM pegawai
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $pegawai_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Data karyawan tidak ditemukan."
    ]);

    exit;
}


$pegawai = $result->fetch_assoc();


/* =========================================================
   SCHEDULE HARI INI
========================================================= */

$stmt = $conn->prepare("
    SELECT
        sc.id AS schedule_id,
        sc.tanggal,
        sc.keterangan,

        s.id AS shift_id,
        s.nama_shift,
        s.jam_masuk,
        s.jam_pulang,
        s.toleransi

    FROM schedule sc

    INNER JOIN shifts s
        ON s.id = sc.shift_id

    WHERE sc.pegawai_id = ?
      AND sc.tanggal = ?
      AND s.status = 'aktif'

    LIMIT 1
");

$stmt->bind_param(
    "is",
    $pegawai_id,
    $today
);

$stmt->execute();

$scheduleResult = $stmt->get_result();

$schedule = null;


if ($scheduleResult->num_rows > 0) {

    $schedule = $scheduleResult->fetch_assoc();
}


/* =========================================================
   ABSENSI HARI INI
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        tanggal,
        jam_tap_in,
        jam_tap_out,
        status
    FROM attendance
    WHERE pegawai_id = ?
      AND tanggal = ?
    LIMIT 1
");

$stmt->bind_param(
    "is",
    $pegawai_id,
    $today
);

$stmt->execute();

$attendanceResult = $stmt->get_result();

$attendance = null;


if ($attendanceResult->num_rows > 0) {

    $attendance =
        $attendanceResult->fetch_assoc();
}


/* =========================================================
   CEK SHIFT MALAM KEMARIN
========================================================= */

if ($attendance === null) {

    $stmt = $conn->prepare("
        SELECT
            a.id,
            a.tanggal,
            a.jam_tap_in,
            a.jam_tap_out,
            a.status,

            s.nama_shift,
            s.jam_masuk,
            s.jam_pulang,
            s.toleransi

        FROM attendance a

        INNER JOIN schedule sc
            ON sc.pegawai_id = a.pegawai_id
            AND sc.tanggal = a.tanggal

        INNER JOIN shifts s
            ON s.id = sc.shift_id

        WHERE a.pegawai_id = ?
          AND a.tanggal = ?
          AND a.jam_tap_in IS NOT NULL
          AND a.jam_tap_out IS NULL
          AND s.status = 'aktif'

        LIMIT 1
    ");

    $stmt->bind_param(
        "is",
        $pegawai_id,
        $yesterday
    );

    $stmt->execute();

    $yesterdayResult =
        $stmt->get_result();


    if ($yesterdayResult->num_rows > 0) {

        $yesterdayAttendance =
            $yesterdayResult->fetch_assoc();


        /*
         * Hanya gunakan jika shift malam.
         */

        if (
            $yesterdayAttendance['jam_pulang']
            <=
            $yesterdayAttendance['jam_masuk']
        ) {

            $attendance =
                $yesterdayAttendance;
        }
    }
}


/* =========================================================
   FORMAT SCHEDULE
========================================================= */

$scheduleData = null;


if ($schedule !== null) {

    $scheduleData = [

        "id" =>
            intval($schedule['schedule_id']),

        "tanggal" =>
            $schedule['tanggal'],

        "shift_id" =>
            intval($schedule['shift_id']),

        "nama_shift" =>
            $schedule['nama_shift'],

        "jam_masuk" =>
            substr(
                $schedule['jam_masuk'],
                0,
                5
            ),

        "jam_pulang" =>
            substr(
                $schedule['jam_pulang'],
                0,
                5
            ),

        "toleransi" =>
            intval($schedule['toleransi']),

        "keterangan" =>
            $schedule['keterangan']
    ];
}


/* =========================================================
   FORMAT ABSENSI
========================================================= */

$attendanceData = null;


if ($attendance !== null) {

    $attendanceData = [

        "id" =>
            intval($attendance['id']),

        "tanggal" =>
            $attendance['tanggal'],

        "jam_tap_in" =>
            $attendance['jam_tap_in']
            ? substr(
                $attendance['jam_tap_in'],
                0,
                8
            )
            : null,

        "jam_tap_out" =>
            $attendance['jam_tap_out']
            ? substr(
                $attendance['jam_tap_out'],
                0,
                8
            )
            : null,

        "status" =>
            $attendance['status']
    ];
}


/* =========================================================
   RESPONSE
========================================================= */

echo json_encode([

    "success" => true,

    "server_time" =>
        $now->format(
            "Y-m-d H:i:s"
        ),

    "tanggal" =>
        $today,

    "jam_sekarang" =>
        $currentTime,

    "pegawai" => [

        "id" =>
            intval($pegawai['id']),

        "nik" =>
            $pegawai['nik'],

        "nama" =>
            $pegawai['nama'],

        "jabatan" =>
            $pegawai['jabatan'],

        "divisi" =>
            $pegawai['divisi'],

        "no_hp" =>
            $pegawai['no_hp'],

        "fingerprint_id" =>
            $pegawai['fingerprint_id']
            ? intval(
                $pegawai['fingerprint_id']
            )
            : null,

        "status" =>
            $pegawai['status']
    ],

    "schedule" =>
        $scheduleData,

    "attendance" =>
        $attendanceData

], JSON_UNESCAPED_UNICODE);

?>