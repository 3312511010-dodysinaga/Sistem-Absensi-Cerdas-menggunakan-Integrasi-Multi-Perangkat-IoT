<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekAdmin();

date_default_timezone_set("Asia/Jakarta");

$message = "";
$messageType = "";


/* =========================================================
   SIMPAN JADWAL
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['simpan_schedule'])) {

    $pegawai_id = intval($_POST['pegawai_id'] ?? 0);
    $tanggal_mulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($pegawai_id <= 0) {

        $message = "Karyawan belum dipilih.";
        $messageType = "error";

    } elseif ($tanggal_mulai === ''
        || $tanggal_selesai === '') {

        $message = "Tanggal wajib diisi.";
        $messageType = "error";

    } elseif ($tanggal_mulai > $tanggal_selesai) {

        $message = "Tanggal mulai tidak boleh lebih besar dari tanggal selesai.";
        $messageType = "error";

    } elseif ($shift_id <= 0) {

        $message = "Shift belum dipilih.";
        $messageType = "error";

    } else {

        $start = new DateTime($tanggal_mulai);
        $end = new DateTime($tanggal_selesai);

        $jumlahHari = $start->diff($end)->days + 1;

        if ($jumlahHari > 31) {

            $message = "Maksimal periode 31 hari.";
            $messageType = "error";

        } else {

            /* CEK PEGAWAI */

            $stmt = $conn->prepare("
                SELECT id, nama
                FROM pegawai
                WHERE id = ?
                AND status = 'aktif'
                LIMIT 1
            ");

            $stmt->bind_param("i", $pegawai_id);
            $stmt->execute();

            $pegawaiResult = $stmt->get_result();

            if ($pegawaiResult->num_rows === 0) {

                $message = "Karyawan tidak ditemukan.";
                $messageType = "error";

            } else {

                /* CEK SHIFT */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM shifts
                    WHERE id = ?
                    AND status = 'aktif'
                    LIMIT 1
                ");

                $stmt->bind_param("i", $shift_id);
                $stmt->execute();

                $shiftResult = $stmt->get_result();

                if ($shiftResult->num_rows === 0) {

                    $message = "Shift tidak ditemukan.";
                    $messageType = "error";

                } else {

                    $conn->begin_transaction();

                    try {

                        $stmt = $conn->prepare("
                            INSERT INTO schedule
                            (
                                pegawai_id,
                                tanggal,
                                shift_id,
                                keterangan
                            )
                            VALUES (?, ?, ?, ?)

                            ON DUPLICATE KEY UPDATE
                                shift_id = VALUES(shift_id),
                                keterangan = VALUES(keterangan)
                        ");

                        for (
                            $tanggal = clone $start;
                            $tanggal <= $end;
                            $tanggal->modify('+1 day')
                        ) {

                            $tanggalDB =
                                $tanggal->format('Y-m-d');

                            $stmt->bind_param(
                                "isis",
                                $pegawai_id,
                                $tanggalDB,
                                $shift_id,
                                $keterangan
                            );

                            if (!$stmt->execute()) {
                                throw new Exception(
                                    $stmt->error
                                );
                            }
                        }

                        $conn->commit();

                        $message =
                            "Schedule berhasil disimpan.";

                        $messageType = "success";

                    } catch (Exception $e) {

                        $conn->rollback();

                        $message =
                            "Gagal menyimpan schedule.";

                        $messageType = "error";
                    }
                }
            }
        }
    }
}


/* =========================================================
   HAPUS JADWAL
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['hapus_schedule'])) {

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM schedule
            WHERE id = ?
        ");

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            $message = "Schedule berhasil dihapus.";
            $messageType = "success";

        } else {

            $message = "Gagal menghapus schedule.";
            $messageType = "error";
        }
    }
}


/* =========================================================
   DATA PEGAWAI
========================================================= */

$pegawaiList = $conn->query("
    SELECT
        id,
        nik,
        nama
    FROM pegawai
    WHERE status = 'aktif'
    ORDER BY nama ASC
");


/* =========================================================
   DATA SHIFT
========================================================= */

$shiftList = $conn->query("
    SELECT
        id,
        nama_shift,
        jam_masuk,
        jam_pulang
    FROM shifts
    WHERE status = 'aktif'
    ORDER BY jam_masuk ASC
");


/* =========================================================
   FILTER
========================================================= */

$filterTanggal =
    trim($_GET['tanggal'] ?? '');

$filterPegawai =
    intval($_GET['pegawai_id'] ?? 0);


/* =========================================================
   DATA SCHEDULE
========================================================= */

$sql = "
    SELECT
        sc.id,
        sc.tanggal,
        sc.keterangan,

        p.nik,
        p.nama,

        s.nama_shift,
        s.jam_masuk,
        s.jam_pulang

    FROM schedule sc

    INNER JOIN pegawai p
        ON p.id = sc.pegawai_id

    INNER JOIN shifts s
        ON s.id = sc.shift_id

    WHERE 1=1
";

$params = [];
$types = "";

if ($filterTanggal !== '') {

    $sql .= " AND sc.tanggal = ? ";

    $params[] = $filterTanggal;
    $types .= "s";
}

if ($filterPegawai > 0) {

    $sql .= " AND sc.pegawai_id = ? ";

    $params[] = $filterPegawai;
    $types .= "i";
}

$sql .= "
    ORDER BY
        sc.tanggal DESC,
        p.nama ASC
    LIMIT 200
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$scheduleResult = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Schedule Karyawan - ABSENSI</title>

<link rel="stylesheet"
      href="../assets/css/style.css">

<style>

/* =========================================================
   SCHEDULE PAGE - COMPACT
========================================================= */

.main-content {
    margin-left: 240px;
    width: calc(100% - 240px);
    min-height: 100vh;
}

.page-container {
    padding: 22px 24px;
    max-width: 1500px;
    margin: auto;
}


/* HEADER */

.page-header {
    margin-bottom: 18px;
}

.page-header h1 {
    margin: 0;
    font-size: 23px;
    font-weight: 700;
}

.page-header p {
    margin: 4px 0 0;
    font-size: 12px;
    color: #6b7280;
}


/* ALERT */

.schedule-alert {
    padding: 10px 13px;
    border-radius: 7px;
    margin-bottom: 15px;
    font-size: 12px;
}

.schedule-alert.success {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
}

.schedule-alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
}


/* CARD */

.schedule-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 17px;
    margin-bottom: 16px;
    box-shadow: 0 2px 7px rgba(0,0,0,.035);
}


/* CARD HEADER */

.schedule-card-header {
    margin-bottom: 14px;
}

.schedule-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
}

.schedule-card-header p {
    margin: 3px 0 0;
    color: #6b7280;
    font-size: 11px;
}


/* FORM */

.schedule-form-grid {
    display: grid;
    grid-template-columns:
        1.25fr
        1.15fr
        .85fr
        .85fr
        1fr
        auto;

    gap: 10px;
    align-items: end;
}

.schedule-field {
    min-width: 0;
}

.schedule-field label {
    display: block;
    margin-bottom: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #374151;
}

.schedule-field input,
.schedule-field select {
    width: 100%;
    height: 37px;
    padding: 0 10px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #fff;
    color: #374151;
    font-size: 12px;
    outline: none;
}

.schedule-field input:focus,
.schedule-field select:focus {
    border-color: #4f46e5;
}


.schedule-save {
    height: 37px;
    padding: 0 17px;
    border: 0;
    border-radius: 7px;
    background: #2563eb;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.schedule-save:hover {
    background: #1d4ed8;
}


/* FILTER */

.filter-grid {
    display: grid;
    grid-template-columns:
        260px
        minmax(250px, 1fr)
        auto
        auto;

    gap: 10px;
    align-items: end;
}

.filter-button {
    height: 36px;
    padding: 0 17px;
    border: 0;
    border-radius: 7px;
    background: #111827;
    color: white;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
}

.reset-button {
    height: 36px;
    padding: 0 17px;
    border-radius: 7px;
    background: #f3f4f6;
    color: #374151;
    font-size: 11px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}


/* TABLE */

.schedule-table-wrapper {
    overflow-x: auto;
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.schedule-table thead {
    background: #f8fafc;
}

.schedule-table th {
    padding: 10px 9px;
    border-bottom: 1px solid #e5e7eb;
    color: #374151;
    font-size: 10px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.schedule-table td {
    padding: 9px;
    border-bottom: 1px solid #f0f0f0;
    color: #374151;
    font-size: 11px;
    vertical-align: middle;
}

.schedule-table tbody tr:hover {
    background: #fafafa;
}


/* EMPLOYEE */

.employee-name {
    font-size: 12px;
    font-weight: 700;
    color: #111827;
}

.employee-nik {
    display: block;
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}


/* SHIFT */

.shift-badge {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 5px;
    background: #dbeafe;
    color: #2563eb;
    font-size: 10px;
    font-weight: 700;
}


/* DELETE */

.delete-button {
    height: 31px;
    padding: 0 12px;
    border: 0;
    border-radius: 6px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.delete-button:hover {
    background: #dc2626;
}


/* EMPTY */

.schedule-empty {
    padding: 28px !important;
    text-align: center;
    color: #9ca3af !important;
}


/* RESPONSIVE */

@media (max-width: 1200px) {

    .schedule-form-grid {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .schedule-save {
        width: 100%;
    }

}

@media (max-width: 900px) {

    .main-content {
        margin-left: 0;
        width: 100%;
    }

    .page-container {
        padding: 16px;
    }

    .schedule-form-grid {
        grid-template-columns: 1fr 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr 1fr;
    }

}

@media (max-width: 600px) {

    .schedule-form-grid,
    .filter-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<?php include "sidebar.php"; ?>


<main class="main-content">

<div class="page-container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="page-header">

        <h1>
            Schedule Karyawan
        </h1>

        <p>
            Atur jadwal kerja karyawan berdasarkan tanggal dan shift.
        </p>

    </div>


    <!-- ALERT -->

    <?php if ($message !== ''): ?>

        <div class="schedule-alert <?= $messageType ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         TAMBAH SCHEDULE
    ================================================== -->

    <div class="schedule-card">

        <div class="schedule-card-header">

            <h3>
                Tambah Schedule
            </h3>

            <p>
                Pilih karyawan, periode tanggal, dan shift kerja.
            </p>

        </div>


        <form method="POST">

            <div class="schedule-form-grid">


                <!-- KARYAWAN -->

                <div class="schedule-field">

                    <label>
                        Karyawan
                    </label>

                    <select
                        name="pegawai_id"
                        required
                    >

                        <option value="">
                            -- Pilih Karyawan --
                        </option>

                        <?php

                        while (
                            $pegawai =
                            $pegawaiList->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= $pegawai['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $pegawai['nama']
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- SHIFT -->

                <div class="schedule-field">

                    <label>
                        Shift
                    </label>

                    <select
                        name="shift_id"
                        required
                    >

                        <option value="">
                            -- Pilih Shift --
                        </option>

                        <?php

                        while (
                            $shift =
                            $shiftList->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= $shift['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $shift['nama_shift']
                                ) ?>

                                &nbsp;

                                (
                                <?= substr(
                                    $shift['jam_masuk'],
                                    0,
                                    5
                                ) ?>

                                -

                                <?= substr(
                                    $shift['jam_pulang'],
                                    0,
                                    5
                                ) ?>
                                )

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- TANGGAL MULAI -->

                <div class="schedule-field">

                    <label>
                        Tanggal Mulai
                    </label>

                    <input
                        type="date"
                        name="tanggal_mulai"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- TANGGAL SELESAI -->

                <div class="schedule-field">

                    <label>
                        Tanggal Selesai
                    </label>

                    <input
                        type="date"
                        name="tanggal_selesai"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- KETERANGAN -->

                <div class="schedule-field">

                    <label>
                        Keterangan
                    </label>

                    <input
                        type="text"
                        name="keterangan"
                        placeholder="Opsional"
                        maxlength="255"
                    >

                </div>


                <!-- BUTTON -->

                <div>

                    <button
                        type="submit"
                        name="simpan_schedule"
                        class="schedule-save"
                    >

                        + Simpan Jadwal

                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="schedule-card">

        <div class="schedule-card-header">

            <h3>
                Filter Schedule
            </h3>

        </div>


        <form method="GET">

            <div class="filter-grid">


                <div class="schedule-field">

                    <label>
                        Tanggal
                    </label>

                    <input
                        type="date"
                        name="tanggal"
                        value="<?= htmlspecialchars(
                            $filterTanggal
                        ) ?>"
                    >

                </div>


                <div class="schedule-field">

                    <label>
                        Karyawan
                    </label>

                    <select name="pegawai_id">

                        <option value="">
                            Semua Karyawan
                        </option>

                        <?php

                        $filterList =
                            $conn->query("
                                SELECT
                                    id,
                                    nama,
                                    nik
                                FROM pegawai
                                WHERE status = 'aktif'
                                ORDER BY nama ASC
                            ");

                        while (
                            $p =
                            $filterList->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= $p['id'] ?>"
                                <?= $filterPegawai ==
                                    $p['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $p['nama']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $p['nik']
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-button"
                >

                    Filter

                </button>


                <a
                    href="schedule.php"
                    class="reset-button"
                >

                    Reset

                </a>

            </div>

        </form>

    </div>


    <!-- =================================================
         DAFTAR SCHEDULE
    ================================================== -->

    <div class="schedule-card">

        <div class="schedule-card-header">

            <h3>
                Daftar Schedule
            </h3>

            <p>
                Jadwal kerja karyawan berdasarkan tanggal.
            </p>

        </div>


        <div class="schedule-table-wrapper">

            <table class="schedule-table">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Tanggal</th>

                        <th>Karyawan</th>

                        <th>Shift</th>

                        <th>Jam Kerja</th>

                        <th>Keterangan</th>

                        <th>Aksi</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $no = 1;

                if ($scheduleResult->num_rows > 0):

                    while (
                        $row =
                        $scheduleResult->fetch_assoc()
                    ):

                ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>


                        <td>

                            <?= date(
                                'd-m-Y',
                                strtotime(
                                    $row['tanggal']
                                )
                            ) ?>

                        </td>


                        <td>

                            <span class="employee-name">

                                <?= htmlspecialchars(
                                    $row['nama']
                                ) ?>

                            </span>

                            <span class="employee-nik">

                                <?= htmlspecialchars(
                                    $row['nik']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span class="shift-badge">

                                <?= htmlspecialchars(
                                    $row['nama_shift']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= substr(
                                $row['jam_masuk'],
                                0,
                                5
                            ) ?>

                            -

                            <?= substr(
                                $row['jam_pulang'],
                                0,
                                5
                            ) ?>

                        </td>


                        <td>

                            <?= $row['keterangan']
                                !== ''
                                ? htmlspecialchars(
                                    $row['keterangan']
                                )
                                : '-'
                            ?>

                        </td>


                        <td>

                            <form
                                method="POST"
                                onsubmit="
                                    return confirm(
                                        'Hapus schedule ini?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $row['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="hapus_schedule"
                                    class="delete-button"
                                >

                                    Hapus

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php

                    endwhile;

                else:

                ?>

                    <tr>

                        <td
                            colspan="7"
                            class="schedule-empty"
                        >

                            Belum ada schedule.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</main>


<script src="../assets/js/app.js"></script>

</body>

</html>