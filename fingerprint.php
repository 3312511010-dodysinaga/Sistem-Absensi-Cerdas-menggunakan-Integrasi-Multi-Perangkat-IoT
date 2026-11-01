<?php

require_once "../koneksi.php";

header("Content-Type: text/plain; charset=utf-8");

date_default_timezone_set("Asia/Jakarta");


/* =========================================================
   CEK METHOD
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo "ERROR|METHOD_TIDAK_VALID";
    exit;
}


/* =========================================================
   AMBIL FINGERPRINT ID
========================================================= */

$fingerprint_id = intval(
    $_POST['fingerprint_id'] ?? 0
);

if ($fingerprint_id <= 0) {

    echo "ERROR|FINGERPRINT_ID_TIDAK_VALID";
    exit;
}


/* =========================================================
   WAKTU SEKARANG
========================================================= */

$now = new DateTime();

$tanggalHariIni = $now->format("Y-m-d");
$jamSekarang    = $now->format("H:i:s");


/* =========================================================
   CARI PEGAWAI
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        nik,
        nama,
        jabatan,
        divisi,
        fingerprint_id,
        status
    FROM pegawai
    WHERE fingerprint_id = ?
      AND status = 'aktif'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $fingerprint_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows === 0) {

    echo "ERROR|PEGAWAI_TIDAK_DITEMUKAN";
    exit;
}


$pegawai = $result->fetch_assoc();

$pegawai_id = intval($pegawai['id']);
$nama       = $pegawai['nama'];


/* =========================================================
   FUNGSI SIMPAN ABNORMAL
========================================================= */

function simpanAbnormal(
    $conn,
    $pegawai_id,
    $tanggal,
    $jenis,
    $keterangan
) {

    /*
     * Cek apakah abnormal yang sama
     * sudah pernah dibuat.
     */

    $cek = $conn->prepare("
        SELECT id
        FROM abnormal_attendance
        WHERE pegawai_id = ?
          AND tanggal = ?
          AND jenis_abnormal = ?
        LIMIT 1
    ");

    $cek->bind_param(
        "iss",
        $pegawai_id,
        $tanggal,
        $jenis
    );

    $cek->execute();

    $hasil = $cek->get_result();


    if ($hasil->num_rows > 0) {
        return;
    }


    $insert = $conn->prepare("
        INSERT INTO abnormal_attendance
        (
            pegawai_id,
            tanggal,
            jenis_abnormal,
            keterangan,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'OPEN'
        )
    ");

    $insert->bind_param(
        "isss",
        $pegawai_id,
        $tanggal,
        $jenis,
        $keterangan
    );

    $insert->execute();
}


/* =========================================================
   CARI SCHEDULE HARI INI
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
    $tanggalHariIni
);

$stmt->execute();

$scheduleResult =
    $stmt->get_result();

$scheduleHariIni = null;


if ($scheduleResult->num_rows > 0) {

    $scheduleHariIni =
        $scheduleResult->fetch_assoc();
}


/* =========================================================
   CARI ABSENSI HARI INI
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
    $tanggalHariIni
);

$stmt->execute();

$attendanceResult =
    $stmt->get_result();

$attendanceHariIni = null;


if ($attendanceResult->num_rows > 0) {

    $attendanceHariIni =
        $attendanceResult->fetch_assoc();
}


/* =========================================================
   CEK SHIFT MALAM DARI HARI SEBELUMNYA
========================================================= */

$attendanceOvernight = null;
$scheduleOvernight   = null;
$tanggalAttendance   = $tanggalHariIni;


if ($attendanceHariIni === null) {

    $tanggalKemarin = (clone $now)
        ->modify("-1 day")
        ->format("Y-m-d");


    /* =====================================================
       CARI SCHEDULE KEMARIN
    ===================================================== */

    $stmt = $conn->prepare("
        SELECT
            sc.id AS schedule_id,
            sc.tanggal,

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
        $tanggalKemarin
    );

    $stmt->execute();

    $overnightScheduleResult =
        $stmt->get_result();


    if (
        $overnightScheduleResult->num_rows > 0
    ) {

        $scheduleOvernight =
            $overnightScheduleResult->fetch_assoc();


        /*
         * Shift malam:
         *
         * 23:00 - 07:00
         *
         * Jam pulang lebih kecil
         * daripada jam masuk.
         */

        $isOvernight =
            $scheduleOvernight['jam_pulang']
            <=
            $scheduleOvernight['jam_masuk'];


        if ($isOvernight) {

            /* =============================================
               CARI ABSENSI KEMARIN YANG BELUM TAP OUT
            ============================================= */

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
                  AND jam_tap_in IS NOT NULL
                  AND jam_tap_out IS NULL

                LIMIT 1
            ");

            $stmt->bind_param(
                "is",
                $pegawai_id,
                $tanggalKemarin
            );

            $stmt->execute();

            $overnightAttendanceResult =
                $stmt->get_result();


            if (
                $overnightAttendanceResult->num_rows > 0
            ) {

                $attendanceOvernight =
                    $overnightAttendanceResult
                    ->fetch_assoc();

                $tanggalAttendance =
                    $tanggalKemarin;
            }
        }
    }
}


/* =========================================================
   TENTUKAN ABSENSI AKTIF
========================================================= */

$attendance = null;
$schedule   = null;


if ($attendanceHariIni !== null) {

    $attendance = $attendanceHariIni;
    $schedule   = $scheduleHariIni;

}
elseif ($attendanceOvernight !== null) {

    $attendance = $attendanceOvernight;
    $schedule   = $scheduleOvernight;
}


/* =========================================================
   TENTUKAN SCHEDULE UNTUK TAP IN
========================================================= */

if ($attendance === null) {

    /*
     * Untuk Tap In harus menggunakan
     * schedule hari ini.
     */

    if ($scheduleHariIni === null) {

        echo
            "ERROR|JADWAL_BELUM_DIATUR|"
            . $nama;

        exit;
    }

    $schedule =
        $scheduleHariIni;
}


/* =========================================================
   DATA SHIFT
========================================================= */

$namaShift =
    $schedule['nama_shift'];

$jamMasuk =
    $schedule['jam_masuk'];

$jamPulang =
    $schedule['jam_pulang'];


/* =========================================================
   CEK SHIFT MALAM
========================================================= */

$isOvernight =
    $jamPulang <= $jamMasuk;


/* =========================================================
   TAP IN
========================================================= */

if ($attendance === null) {

    /*
     * =====================================================
     * TIDAK ADA TOLERANSI
     * =====================================================
     *
     * Contoh:
     *
     * Jam masuk 07:00:00
     *
     * 06:59:59 = HADIR
     * 07:00:00 = HADIR
     * 07:00:01 = TERLAMBAT
     *
     * Kolom toleransi TIDAK digunakan.
     */


    $status = "HADIR";


    /* =====================================================
       BUAT WAKTU JADWAL MASUK
    ===================================================== */

    $scheduleStart =
        new DateTime(
            $tanggalHariIni
            . " "
            . $jamMasuk
        );


    /* =====================================================
       WAKTU FINGERPRINT
    ===================================================== */

    $currentDateTime =
        new DateTime();


    /* =====================================================
       CEK KETERLAMBATAN
    ===================================================== */

    if (
        $currentDateTime >
        $scheduleStart
    ) {

        /*
         * Lewat satu detik saja
         * langsung TERLAMBAT.
         */

        $status =
            "TERLAMBAT";


        simpanAbnormal(
            $conn,
            $pegawai_id,
            $tanggalHariIni,
            "KETERLAMBATAN",
            "Tap In terlambat. "
            . "Jam masuk "
            . $jamMasuk
            . ", Tap In "
            . $jamSekarang
        );
    }


    /* =====================================================
       SIMPAN ABSENSI
    ===================================================== */

    $stmt = $conn->prepare("
        INSERT INTO attendance
        (
            pegawai_id,
            tanggal,
            jam_tap_in,
            jam_tap_out,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            NULL,
            ?
        )
    ");

    $stmt->bind_param(
        "isss",
        $pegawai_id,
        $tanggalHariIni,
        $jamSekarang,
        $status
    );


    if (!$stmt->execute()) {

        echo
            "ERROR|GAGAL_SIMPAN_TAP_IN|"
            . $nama;

        exit;
    }


    /* =====================================================
       RESPONSE ESP32
    ===================================================== */

    echo
        "OK|TAP_IN|"
        . $nama
        . "|"
        . $jamSekarang
        . "|"
        . $namaShift
        . "|"
        . $status;

    exit;
}


/* =========================================================
   SUDAH TAP IN, BELUM TAP OUT
========================================================= */

if (
    !empty($attendance['jam_tap_in'])
    &&
    empty($attendance['jam_tap_out'])
) {

    /*
     * Fingerprint berikutnya dianggap
     * sebagai Tap Out.
     */

    $attendanceId =
        intval($attendance['id']);


    /* =====================================================
       TANGGAL JAM PULANG
    ===================================================== */

    $tanggalJamPulang =
        $tanggalAttendance;


    /*
     * Shift malam:
     *
     * 23:00 - 07:00
     *
     * Jam pulang berada di hari berikutnya.
     */

    if ($isOvernight) {

        $tanggalJamPulang =
            (new DateTime(
                $tanggalAttendance
            ))
            ->modify("+1 day")
            ->format("Y-m-d");
    }


    /* =====================================================
       WAKTU JADWAL PULANG
    ===================================================== */

    $scheduleEnd =
        new DateTime(
            $tanggalJamPulang
            . " "
            . $jamPulang
        );


    /* =====================================================
       WAKTU TAP OUT
    ===================================================== */

    $currentDateTime =
        new DateTime();


    /* =====================================================
       TENTUKAN STATUS TAP OUT
    ===================================================== */

    if (
        $currentDateTime <
        $scheduleEnd
    ) {

        /*
         * Pulang sebelum jam pulang.
         */

        $status =
            "PULANG_CEPAT";


        simpanAbnormal(
            $conn,
            $pegawai_id,
            $tanggalAttendance,
            "PULANG_CEPAT",
            "Tap Out sebelum jam pulang. "
            . "Jam pulang "
            . $jamPulang
            . ", Tap Out "
            . $jamSekarang
        );

    }
    else {

        /*
         * Tepat atau setelah jam pulang
         * tetap HADIR.
         *
         * Tidak menghitung overtime.
         */

        $status =
            "HADIR";
    }


    /* =====================================================
       UPDATE ABSENSI
    ===================================================== */

    $stmt = $conn->prepare("
        UPDATE attendance

        SET
            jam_tap_out = ?,
            status = ?

        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssi",
        $jamSekarang,
        $status,
        $attendanceId
    );


    if (!$stmt->execute()) {

        echo
            "ERROR|GAGAL_SIMPAN_TAP_OUT|"
            . $nama;

        exit;
    }


    /* =====================================================
       RESPONSE ESP32
    ===================================================== */

    echo
        "OK|TAP_OUT|"
        . $nama
        . "|"
        . $jamSekarang
        . "|"
        . $namaShift
        . "|"
        . $status;

    exit;
}


/* =========================================================
   SUDAH TAP IN DAN TAP OUT
========================================================= */

if (
    !empty($attendance['jam_tap_in'])
    &&
    !empty($attendance['jam_tap_out'])
) {

    echo
        "ERROR|SUDAH_TAP_IN_OUT|"
        . $nama;

    exit;
}


/* =========================================================
   ERROR UMUM
========================================================= */

echo
    "ERROR|PROSES_ABSENSI_GAGAL|"
    . $nama;

exit;

?>