<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekAdmin();

date_default_timezone_set("Asia/Jakarta");

$message = "";
$messageType = "";


/* =========================================================
   PASTIKAN TABEL SHIFTS ADA
========================================================= */

$conn->query("
    CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_shift VARCHAR(100) NOT NULL,
        jam_masuk TIME NOT NULL,
        jam_pulang TIME NOT NULL,
        toleransi INT NOT NULL DEFAULT 5,
        status ENUM('aktif','nonaktif') DEFAULT 'aktif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");


/* =========================================================
   TAMBAH SHIFT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['tambah_shift'])) {

    $nama_shift = trim($_POST['nama_shift'] ?? '');
    $jam_masuk = trim($_POST['jam_masuk'] ?? '');
    $jam_pulang = trim($_POST['jam_pulang'] ?? '');
    $toleransi = intval($_POST['toleransi'] ?? 5);

    if ($nama_shift === '') {

        $message = "Nama shift wajib diisi.";
        $messageType = "error";

    } elseif ($jam_masuk === '' || $jam_pulang === '') {

        $message = "Jam masuk dan jam pulang wajib diisi.";
        $messageType = "error";

    } elseif ($toleransi < 0 || $toleransi > 300) {

        $message = "Toleransi harus antara 0 sampai 300 menit.";
        $messageType = "error";

    } else {

        $jam_masuk_db = strlen($jam_masuk) === 5
            ? $jam_masuk . ":00"
            : $jam_masuk;

        $jam_pulang_db = strlen($jam_pulang) === 5
            ? $jam_pulang . ":00"
            : $jam_pulang;


        $stmt = $conn->prepare("
            INSERT INTO shifts
            (
                nama_shift,
                jam_masuk,
                jam_pulang,
                toleransi,
                status
            )
            VALUES (?, ?, ?, ?, 'aktif')
        ");

        $stmt->bind_param(
            "sssi",
            $nama_shift,
            $jam_masuk_db,
            $jam_pulang_db,
            $toleransi
        );

        if ($stmt->execute()) {

            $message = "Shift berhasil ditambahkan.";
            $messageType = "success";

        } else {

            $message = "Gagal menambahkan shift.";
            $messageType = "error";
        }
    }
}


/* =========================================================
   EDIT SHIFT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['edit_shift'])) {

    $id = intval($_POST['id'] ?? 0);

    $nama_shift = trim($_POST['nama_shift'] ?? '');
    $jam_masuk = trim($_POST['jam_masuk'] ?? '');
    $jam_pulang = trim($_POST['jam_pulang'] ?? '');
    $toleransi = intval($_POST['toleransi'] ?? 5);

    if ($id <= 0) {

        $message = "ID shift tidak valid.";
        $messageType = "error";

    } elseif ($nama_shift === '') {

        $message = "Nama shift wajib diisi.";
        $messageType = "error";

    } elseif ($jam_masuk === '' || $jam_pulang === '') {

        $message = "Jam masuk dan jam pulang wajib diisi.";
        $messageType = "error";

    } elseif ($toleransi < 0 || $toleransi > 300) {

        $message = "Toleransi harus antara 0 sampai 300 menit.";
        $messageType = "error";

    } else {

        $jam_masuk_db = strlen($jam_masuk) === 5
            ? $jam_masuk . ":00"
            : $jam_masuk;

        $jam_pulang_db = strlen($jam_pulang) === 5
            ? $jam_pulang . ":00"
            : $jam_pulang;


        $stmt = $conn->prepare("
            UPDATE shifts
            SET
                nama_shift = ?,
                jam_masuk = ?,
                jam_pulang = ?,
                toleransi = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssii",
            $nama_shift,
            $jam_masuk_db,
            $jam_pulang_db,
            $toleransi,
            $id
        );

        if ($stmt->execute()) {

            $message = "Shift berhasil diperbarui.";
            $messageType = "success";

        } else {

            $message = "Gagal memperbarui shift.";
            $messageType = "error";
        }
    }
}


/* =========================================================
   AKTIF / NONAKTIF SHIFT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['toggle_shift'])) {

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {

        $stmt = $conn->prepare("
            SELECT status
            FROM shifts
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $row = $result->fetch_assoc();

            $newStatus =
                $row['status'] === 'aktif'
                ? 'nonaktif'
                : 'aktif';


            $stmt = $conn->prepare("
                UPDATE shifts
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $newStatus,
                $id
            );

            if ($stmt->execute()) {

                $message =
                    $newStatus === 'aktif'
                    ? "Shift berhasil diaktifkan."
                    : "Shift berhasil dinonaktifkan.";

                $messageType = "success";

            } else {

                $message = "Gagal mengubah status shift.";
                $messageType = "error";
            }
        }
    }
}


/* =========================================================
   HAPUS SHIFT
   HANYA JIKA BELUM DIGUNAKAN SCHEDULE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['hapus_shift'])) {

    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {

        /* Cek apakah masih dipakai schedule */

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM schedule
            WHERE shift_id = ?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $totalSchedule = intval($data['total']);

        if ($totalSchedule > 0) {

            $message =
                "Shift tidak dapat dihapus karena masih digunakan oleh "
                . $totalSchedule
                . " schedule. Gunakan Nonaktifkan.";

            $messageType = "error";

        } else {

            $stmt = $conn->prepare("
                DELETE FROM shifts
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {

                $message = "Shift berhasil dihapus.";
                $messageType = "success";

            } else {

                $message = "Gagal menghapus shift.";
                $messageType = "error";
            }
        }
    }
}


/* =========================================================
   DATA SHIFT
========================================================= */

$shiftResult = $conn->query("
    SELECT
        id,
        nama_shift,
        jam_masuk,
        jam_pulang,
        toleransi,
        status,
        created_at
    FROM shifts
    ORDER BY
        status = 'nonaktif',
        jam_masuk ASC,
        id ASC
");


/* =========================================================
   DATA EDIT
========================================================= */

$editShift = null;

if (isset($_GET['edit'])) {

    $editId = intval($_GET['edit']);

    if ($editId > 0) {

        $stmt = $conn->prepare("
            SELECT
                id,
                nama_shift,
                jam_masuk,
                jam_pulang,
                toleransi,
                status
            FROM shifts
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $editShift = $result->fetch_assoc();
        }
    }
}


/* =========================================================
   DATA SISTEM
========================================================= */

$totalPegawai = 0;
$totalShiftAktif = 0;
$totalSchedule = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM pegawai
    WHERE status = 'aktif'
");

if ($result) {
    $totalPegawai =
        intval($result->fetch_assoc()['total']);
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM shifts
    WHERE status = 'aktif'
");

if ($result) {
    $totalShiftAktif =
        intval($result->fetch_assoc()['total']);
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM schedule
");

if ($result) {
    $totalSchedule =
        intval($result->fetch_assoc()['total']);
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Pengaturan - ABSENSI</title>

<link rel="stylesheet"
      href="../assets/css/style.css">


<style>

/* =========================================================
   PENGATURAN - COMPACT
========================================================= */

.main-content {
    margin-left: 240px;
    width: calc(100% - 240px);
    min-height: 100vh;
}

.settings-container {
    max-width: 1500px;
    margin: auto;
    padding: 22px 24px;
}


/* HEADER */

.settings-header {
    margin-bottom: 18px;
}

.settings-header h1 {
    margin: 0;
    font-size: 23px;
    font-weight: 700;
    color: #111827;
}

.settings-header p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
}


/* ALERT */

.settings-alert {
    padding: 10px 13px;
    border-radius: 7px;
    margin-bottom: 15px;
    font-size: 12px;
}

.settings-alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.settings-alert.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}


/* CARD */

.settings-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 17px;
    margin-bottom: 16px;
    box-shadow: 0 2px 7px rgba(0,0,0,.035);
}


/* CARD HEADER */

.settings-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 14px;
}

.settings-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}

.settings-card-header p {
    margin: 3px 0 0;
    font-size: 11px;
    color: #6b7280;
}


/* FORM */

.shift-form {
    display: grid;
    grid-template-columns:
        1.3fr
        1fr
        1fr
        .7fr
        auto;

    gap: 10px;
    align-items: end;
}

.form-field {
    min-width: 0;
}

.form-field label {
    display: block;
    margin-bottom: 5px;
    color: #374151;
    font-size: 11px;
    font-weight: 600;
}

.form-field input {
    width: 100%;
    height: 37px;
    padding: 0 10px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #ffffff;
    color: #374151;
    font-size: 12px;
    outline: none;
}

.form-field input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79,70,229,.08);
}


/* BUTTON */

.btn-save {
    height: 37px;
    padding: 0 17px;
    border: none;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.btn-save:hover {
    background: #1d4ed8;
}


.btn-cancel {
    height: 37px;
    padding: 0 14px;
    border-radius: 7px;
    background: #f3f4f6;
    color: #374151;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
}


/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.shift-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

.shift-table thead {
    background: #f8fafc;
}

.shift-table th {
    padding: 10px 9px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    color: #374151;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.shift-table td {
    padding: 9px;
    border-bottom: 1px solid #f0f0f0;
    color: #374151;
    font-size: 11px;
    vertical-align: middle;
}

.shift-table tbody tr:hover {
    background: #fafafa;
}


/* SHIFT NAME */

.shift-name {
    font-size: 12px;
    font-weight: 700;
    color: #111827;
}


/* BADGE */

.status-badge {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.status-aktif {
    background: #dcfce7;
    color: #15803d;
}

.status-nonaktif {
    background: #f3f4f6;
    color: #6b7280;
}


/* ACTION */

.action-group {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-edit,
.btn-toggle,
.btn-delete {
    height: 29px;
    padding: 0 9px;
    border: none;
    border-radius: 5px;
    color: white;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-edit {
    background: #2563eb;
}

.btn-edit:hover {
    background: #1d4ed8;
}

.btn-toggle {
    background: #64748b;
}

.btn-toggle:hover {
    background: #475569;
}

.btn-delete {
    background: #ef4444;
}

.btn-delete:hover {
    background: #dc2626;
}


/* INFO GRID */

.info-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;
}

.info-box {
    padding: 13px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f9fafb;
}

.info-label {
    font-size: 10px;
    color: #6b7280;
    margin-bottom: 4px;
}

.info-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}


/* EMPTY */

.empty-row {
    text-align: center !important;
    padding: 25px !important;
    color: #9ca3af !important;
}


/* RESPONSIVE */

@media (max-width: 1100px) {

    .shift-form {
        grid-template-columns:
            1fr 1fr;
    }

    .btn-save,
    .btn-cancel {
        width: 100%;
    }

}

@media (max-width: 900px) {

    .main-content {
        margin-left: 0;
        width: 100%;
    }

    .settings-container {
        padding: 16px;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .shift-form {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<?php include "sidebar.php"; ?>


<main class="main-content">


<div class="settings-container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="settings-header">

        <h1>
            Pengaturan
        </h1>

        <p>
            Kelola shift kerja dan konfigurasi sistem ABSENSI.
        </p>

    </div>


    <!-- ALERT -->

    <?php if ($message !== ''): ?>

        <div class="settings-alert <?= $messageType ?>">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         MASTER SHIFT
    ================================================== -->

    <div class="settings-card">

        <div class="settings-card-header">

            <div>

                <h3>
                    Master Shift
                </h3>

                <p>
                    Atur sendiri nama shift, jam masuk, jam pulang,
                    dan toleransi keterlambatan.
                </p>

            </div>

        </div>


        <form method="POST">


            <div class="shift-form">


                <!-- NAMA SHIFT -->

                <div class="form-field">

                    <label>
                        Nama Shift
                    </label>

                    <input
                        type="text"
                        name="nama_shift"
                        placeholder="Contoh: Shift Pagi"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- JAM MASUK -->

                <div class="form-field">

                    <label>
                        Jam Masuk
                    </label>

                    <input
                        type="time"
                        name="jam_masuk"
                        required
                    >

                </div>


                <!-- JAM PULANG -->

                <div class="form-field">

                    <label>
                        Jam Pulang
                    </label>

                    <input
                        type="time"
                        name="jam_pulang"
                        required
                    >

                </div>


                <!-- TOLERANSI -->

                <div class="form-field">

                    <label>
                        Toleransi (menit)
                    </label>

                    <input
                        type="number"
                        name="toleransi"
                        value="15"
                        min="0"
                        max="300"
                        required
                    >

                </div>


                <!-- BUTTON -->

                <div>

                    <button
                        type="submit"
                        name="tambah_shift"
                        class="btn-save"
                    >

                        + Tambah Shift

                    </button>

                </div>


            </div>

        </form>

    </div>


    <!-- =================================================
         EDIT SHIFT
    ================================================== -->

    <?php if ($editShift !== null): ?>

    <div class="settings-card">

        <div class="settings-card-header">

            <div>

                <h3>
                    Edit Shift
                </h3>

                <p>
                    Ubah jam shift sesuai kebutuhan.
                </p>

            </div>

            <a
                href="pengaturan.php"
                class="btn-cancel"
            >
                Batal
            </a>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $editShift['id'] ?>"
            >


            <div class="shift-form">


                <div class="form-field">

                    <label>
                        Nama Shift
                    </label>

                    <input
                        type="text"
                        name="nama_shift"
                        value="<?= htmlspecialchars(
                            $editShift['nama_shift']
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="form-field">

                    <label>
                        Jam Masuk
                    </label>

                    <input
                        type="time"
                        name="jam_masuk"
                        value="<?= substr(
                            $editShift['jam_masuk'],
                            0,
                            5
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-field">

                    <label>
                        Jam Pulang
                    </label>

                    <input
                        type="time"
                        name="jam_pulang"
                        value="<?= substr(
                            $editShift['jam_pulang'],
                            0,
                            5
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-field">

                    <label>
                        Toleransi (menit)
                    </label>

                    <input
                        type="number"
                        name="toleransi"
                        value="<?= intval(
                            $editShift['toleransi']
                        ) ?>"
                        min="0"
                        max="300"
                        required
                    >

                </div>


                <div>

                    <button
                        type="submit"
                        name="edit_shift"
                        class="btn-save"
                    >

                        Simpan Perubahan

                    </button>

                </div>

            </div>

        </form>

    </div>

    <?php endif; ?>


    <!-- =================================================
         DAFTAR SHIFT
    ================================================== -->

    <div class="settings-card">

        <div class="settings-card-header">

            <div>

                <h3>
                    Daftar Shift
                </h3>

                <p>
                    Shift yang tersedia untuk digunakan pada Schedule Karyawan.
                </p>

            </div>

        </div>


        <div class="table-wrapper">

            <table class="shift-table">

                <thead>

                    <tr>

                        <th>
                            No
                        </th>

                        <th>
                            Nama Shift
                        </th>

                        <th>
                            Jam Masuk
                        </th>

                        <th>
                            Jam Pulang
                        </th>

                        <th>
                            Toleransi
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $no = 1;

                if ($shiftResult
                    && $shiftResult->num_rows > 0):

                    while (
                        $shift =
                        $shiftResult->fetch_assoc()
                    ):

                ?>

                    <tr>


                        <td>
                            <?= $no++ ?>
                        </td>


                        <td>

                            <span class="shift-name">

                                <?= htmlspecialchars(
                                    $shift['nama_shift']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= substr(
                                $shift['jam_masuk'],
                                0,
                                5
                            ) ?>

                        </td>


                        <td>

                            <?= substr(
                                $shift['jam_pulang'],
                                0,
                                5
                            ) ?>

                        </td>


                        <td>

                            <?= intval(
                                $shift['toleransi']
                            ) ?>

                            menit

                        </td>


                        <td>

                            <span
                                class="
                                    status-badge
                                    status-<?= $shift['status'] ?>
                                "
                            >

                                <?= ucfirst(
                                    $shift['status']
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <div class="action-group">


                                <!-- EDIT -->

                                <a
                                    href="?edit=<?= $shift['id'] ?>"
                                    class="btn-edit"
                                >

                                    Edit

                                </a>


                                <!-- AKTIF / NONAKTIF -->

                                <form
                                    method="POST"
                                    style="margin:0;"
                                    onsubmit="
                                        return confirm(
                                            'Ubah status shift ini?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $shift['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="toggle_shift"
                                        class="btn-toggle"
                                    >

                                        <?= $shift['status']
                                            === 'aktif'
                                            ? 'Nonaktif'
                                            : 'Aktif'
                                        ?>

                                    </button>

                                </form>


                                <!-- HAPUS -->

                                <form
                                    method="POST"
                                    style="margin:0;"
                                    onsubmit="
                                        return confirm(
                                            'Hapus shift ini?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $shift['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="hapus_shift"
                                        class="btn-delete"
                                    >

                                        Hapus

                                    </button>

                                </form>


                            </div>

                        </td>


                    </tr>

                <?php

                    endwhile;

                else:

                ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty-row"
                        >

                            Belum ada shift.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =================================================
         INFORMASI SISTEM
    ================================================== -->

    <div class="settings-card">

        <div class="settings-card-header">

            <div>

                <h3>
                    Informasi Sistem
                </h3>

                <p>
                    Ringkasan konfigurasi ABSENSI.
                </p>

            </div>

        </div>


        <div class="info-grid">


            <div class="info-box">

                <div class="info-label">
                    Pegawai Aktif
                </div>

                <div class="info-value">
                    <?= $totalPegawai ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Shift Aktif
                </div>

                <div class="info-value">
                    <?= $totalShiftAktif ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Total Schedule
                </div>

                <div class="info-value">
                    <?= $totalSchedule ?>
                </div>

            </div>


        </div>

    </div>


</div>

</main>


<script src="../assets/js/app.js"></script>

</body>

</html>