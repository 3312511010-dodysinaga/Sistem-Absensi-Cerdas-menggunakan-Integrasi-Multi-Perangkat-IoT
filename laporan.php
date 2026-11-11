<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekAdmin();

date_default_timezone_set("Asia/Jakarta");


// ============================================================
// FILTER
// ============================================================

$tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');
$pegawaiId = intval($_GET['pegawai_id'] ?? 0);

$errorDatabase = "";


// ============================================================
// VALIDASI TANGGAL
// ============================================================

if ($tanggalMulai === '') {
    $tanggalMulai = date('Y-m-01');
}

if ($tanggalSelesai === '') {
    $tanggalSelesai = date('Y-m-d');
}

if ($tanggalMulai > $tanggalSelesai) {

    $temp = $tanggalMulai;
    $tanggalMulai = $tanggalSelesai;
    $tanggalSelesai = $temp;
}


// ============================================================
// DATA PEGAWAI UNTUK FILTER
// ============================================================

$pegawaiList = $conn->query("
    SELECT
        id,
        nik,
        nama
    FROM pegawai
    ORDER BY nama ASC
");

if ($pegawaiList === false) {

    $errorDatabase =
        "Gagal mengambil data pegawai: " .
        $conn->error;
}


// ============================================================
// QUERY LAPORAN
// ============================================================

$sql = "
    SELECT
        attendance.id,
        attendance.tanggal,
        attendance.jam_tap_in,
        attendance.jam_tap_out,
        attendance.status,

        pegawai.nama,
        pegawai.nik,
        pegawai.jabatan

    FROM attendance

    INNER JOIN pegawai
        ON pegawai.id = attendance.pegawai_id

    WHERE attendance.tanggal BETWEEN ? AND ?
";


$params = [
    $tanggalMulai,
    $tanggalSelesai
];

$types = "ss";


// ============================================================
// FILTER PEGAWAI
// ============================================================

if ($pegawaiId > 0) {

    $sql .= "
        AND attendance.pegawai_id = ?
    ";

    $params[] = $pegawaiId;

    $types .= "i";
}


// ============================================================
// ORDER
// ============================================================

$sql .= "
    ORDER BY
        attendance.tanggal DESC,
        attendance.jam_tap_in DESC
";


// ============================================================
// PREPARE QUERY
// ============================================================

$stmt = $conn->prepare($sql);


// ============================================================
// DATA & STATISTIK DEFAULT
// ============================================================

$totalData = 0;
$totalHadir = 0;
$totalSelesai = 0;
$totalBelumOut = 0;

$dataLaporan = [];


// ============================================================
// CEK QUERY
// ============================================================

if ($stmt === false) {

    $errorDatabase =
        "Query laporan gagal: " .
        $conn->error;

} else {


    // ========================================================
    // BIND PARAMETER
    // ========================================================

    $stmt->bind_param(
        $types,
        ...$params
    );


    // ========================================================
    // EXECUTE
    // ========================================================

    if (!$stmt->execute()) {

        $errorDatabase =
            "Gagal menjalankan laporan: " .
            $stmt->error;

    } else {

        $result = $stmt->get_result();


        // ====================================================
        // DATA LAPORAN
        // ====================================================

        while ($row = $result->fetch_assoc()) {

            $dataLaporan[] = $row;

            $totalData++;


            // ================================================
            // TOTAL HADIR
            // ================================================

            if (!empty($row['jam_tap_in'])) {

                $totalHadir++;
            }


            // ================================================
            // TOTAL SELESAI
            // ================================================

            if (
                !empty($row['jam_tap_in']) &&
                !empty($row['jam_tap_out'])
            ) {

                $totalSelesai++;
            }


            // ================================================
            // TOTAL BELUM TAP OUT
            // ================================================

            elseif (
                !empty($row['jam_tap_in']) &&
                empty($row['jam_tap_out'])
            ) {

                $totalBelumOut++;
            }
        }
    }


    $stmt->close();
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Laporan Absensi
</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<style>

/* ============================================================
   HEADER
============================================================ */

.laporan-heading {
    margin-bottom: 25px;
}

.laporan-heading h1 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 5px;
}

.laporan-heading p {
    color: #64748b;
    font-size: 13px;
}


/* ============================================================
   FILTER
============================================================ */

.laporan-filter {
    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 22px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.laporan-filter-title {
    font-size: 16px;

    font-weight: 700;

    color: #111827;

    margin-bottom: 17px;
}

.laporan-filter-grid {
    display: grid;

    grid-template-columns:
        1fr
        1fr
        1.5fr
        auto;

    gap: 15px;

    align-items: end;
}

.laporan-field label {
    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #374151;
}

.laporan-field input,
.laporan-field select {

    width: 100%;

    height: 44px;

    padding: 0 12px;

    border:
        1px solid #d1d5db;

    border-radius: 9px;

    background: white;

    outline: none;

    font-size: 13px;
}

.laporan-field input:focus,
.laporan-field select:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px
        rgba(79,70,229,.10);
}

.laporan-filter-buttons {

    display: flex;

    gap: 7px;
}


/* ============================================================
   STATISTIK
============================================================ */

.laporan-stat {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}

.laporan-stat-card {

    background: white;

    border:
        1px solid #e5e7eb;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.laporan-stat-label {

    color: #64748b;

    font-size: 12px;

    margin-bottom: 8px;
}

.laporan-stat-value {

    font-size: 27px;

    font-weight: 700;

    color: #111827;
}

.stat-blue .laporan-stat-value {
    color: #2563eb;
}

.stat-green .laporan-stat-value {
    color: #16a34a;
}

.stat-orange .laporan-stat-value {
    color: #d97706;
}


/* ============================================================
   TABLE CARD
============================================================ */

.laporan-card {

    background: white;

    border:
        1px solid #e5e7eb;

    border-radius: 14px;

    padding: 25px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.laporan-card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 20px;
}

.laporan-card-header h2 {

    margin: 0;

    font-size: 18px;

    color: #111827;
}

.laporan-card-header p {

    margin-top: 4px;

    color: #64748b;

    font-size: 12px;
}


/* ============================================================
   BUTTON
============================================================ */

.btn-print {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        10px 14px;

    border-radius: 9px;

    border: none;

    background: #111827;

    color: white;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;
}

.btn-print:hover {

    background: #1f2937;
}


/* ============================================================
   DATA
============================================================ */

.laporan-nama {

    font-size: 13px;

    font-weight: 700;

    color: #111827;
}

.laporan-nik {

    margin-top: 3px;

    font-size: 10px;

    color: #94a3b8;
}

.laporan-jam {

    font-size: 12px;

    font-weight: 600;
}

.laporan-date {

    white-space: nowrap;

    font-size: 12px;

    color: #374151;
}


/* ============================================================
   STATUS
============================================================ */

.laporan-status {

    display: inline-flex;

    padding:
        5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;
}

.laporan-status-selesai {

    background: #dcfce7;

    color: #166534;
}

.laporan-status-bekerja {

    background: #dbeafe;

    color: #1d4ed8;
}

.laporan-status-belum {

    background: #fef3c7;

    color: #92400e;
}


/* ============================================================
   EMPTY
============================================================ */

.laporan-empty {

    padding: 50px 20px;

    text-align: center;

    color: #94a3b8;

    font-size: 13px;
}


/* ============================================================
   ERROR DATABASE
============================================================ */

.laporan-error {

    background: #fef2f2;

    border:
        1px solid #fecaca;

    color: #b91c1c;

    padding: 14px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 13px;
}


/* ============================================================
   PRINT
============================================================ */

@media print {

    body {
        background: white;
    }

    .sidebar,
    .laporan-filter,
    .laporan-heading,
    .btn-print,
    .laporan-error {
        display: none !important;
    }

    .main,
    .main-content {
        margin: 0 !important;

        width: 100% !important;

        padding: 0 !important;
    }

    .laporan-stat {
        grid-template-columns:
            repeat(4, 1fr);
    }

    .laporan-card {
        border: none;

        box-shadow: none;

        padding: 0;
    }

    table th,
    table td {
        border: 1px solid #ddd;
    }
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 900px) {

    .laporan-filter-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .laporan-stat {
        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 600px) {

    .laporan-filter-grid {
        grid-template-columns: 1fr;
    }

    .laporan-stat {
        grid-template-columns: 1fr;
    }

    .laporan-card-header {
        align-items: flex-start;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<!-- ============================================================
     SIDEBAR
============================================================ -->

<?php include "sidebar.php"; ?>


<!-- ============================================================
     MAIN
============================================================ -->

<div class="main">


    <!-- ========================================================
         HEADER
    ========================================================= -->

    <div class="laporan-heading">

        <h1>
            Laporan Absensi
        </h1>

        <p>
            Rekap data kehadiran karyawan
        </p>

    </div>


    <!-- ========================================================
         ERROR DATABASE
    ========================================================= -->

    <?php if ($errorDatabase !== ''): ?>

        <div class="laporan-error">

            <strong>
                Terjadi kesalahan database:
            </strong>

            <br>

            <?= htmlspecialchars(
                $errorDatabase
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         FILTER
    ========================================================= -->

    <div class="laporan-filter">

        <div class="laporan-filter-title">

            Filter Laporan

        </div>


        <form
            method="GET"
            action="laporan.php"
        >


            <div class="laporan-filter-grid">


                <!-- TANGGAL MULAI -->

                <div class="laporan-field">

                    <label>
                        Tanggal Mulai
                    </label>

                    <input
                        type="date"
                        name="tanggal_mulai"
                        value="<?= htmlspecialchars(
                            $tanggalMulai
                        ) ?>"
                    >

                </div>


                <!-- TANGGAL SELESAI -->

                <div class="laporan-field">

                    <label>
                        Tanggal Selesai
                    </label>

                    <input
                        type="date"
                        name="tanggal_selesai"
                        value="<?= htmlspecialchars(
                            $tanggalSelesai
                        ) ?>"
                    >

                </div>


                <!-- PEGAWAI -->

                <div class="laporan-field">

                    <label>
                        Pegawai
                    </label>

                    <select
                        name="pegawai_id"
                    >

                        <option value="0">
                            Semua Pegawai
                        </option>


                        <?php if ($pegawaiList): ?>


                        <?php while (
                            $pegawai =
                            $pegawaiList->fetch_assoc()
                        ): ?>


                            <option
                                value="<?= $pegawai['id'] ?>"
                                <?= $pegawaiId ==
                                    $pegawai['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $pegawai['nama']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $pegawai['nik']
                                ) ?>

                            </option>


                        <?php endwhile; ?>


                        <?php endif; ?>


                    </select>

                </div>


                <!-- BUTTON -->

                <div
                    class="laporan-filter-buttons"
                >

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        Tampilkan

                    </button>


                    <a
                        href="laporan.php"
                        class="btn btn-danger"
                    >

                        Reset

                    </a>

                </div>


            </div>


        </form>

    </div>


    <!-- ========================================================
         STATISTIK
    ========================================================= -->

    <div class="laporan-stat">


        <div class="laporan-stat-card">

            <div class="laporan-stat-label">

                Total Data

            </div>

            <div class="laporan-stat-value">

                <?= $totalData ?>

            </div>

        </div>


        <div class="
            laporan-stat-card
            stat-blue
        ">

            <div class="laporan-stat-label">

                Hadir

            </div>

            <div class="laporan-stat-value">

                <?= $totalHadir ?>

            </div>

        </div>


        <div class="
            laporan-stat-card
            stat-green
        ">

            <div class="laporan-stat-label">

                Absensi Selesai

            </div>

            <div class="laporan-stat-value">

                <?= $totalSelesai ?>

            </div>

        </div>


        <div class="
            laporan-stat-card
            stat-orange
        ">

            <div class="laporan-stat-label">

                Belum Tap Out

            </div>

            <div class="laporan-stat-value">

                <?= $totalBelumOut ?>

            </div>

        </div>


    </div>


    <!-- ========================================================
         TABEL
    ========================================================= -->

    <div class="laporan-card">


        <div class="laporan-card-header">


            <div>

                <h2>

                    Data Absensi

                </h2>

                <p>

                    Periode

                    <?= date(
                        "d-m-Y",
                        strtotime(
                            $tanggalMulai
                        )
                    ) ?>

                    sampai

                    <?= date(
                        "d-m-Y",
                        strtotime(
                            $tanggalSelesai
                        )
                    ) ?>

                </p>

            </div>


            <button
                type="button"
                class="btn-print"
                onclick="window.print()"
            >

                🖨 Cetak Laporan

            </button>


        </div>


        <div class="table-responsive">


            <?php if (
                count($dataLaporan) > 0
            ): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            No
                        </th>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Karyawan
                        </th>

                        <th>
                            Jabatan
                        </th>

                        <th>
                            Tap In
                        </th>

                        <th>
                            Tap Out
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php

                $no = 1;

                foreach (
                    $dataLaporan
                    as $row
                ):

                ?>


                    <tr>


                        <!-- NO -->

                        <td>

                            <?= $no++ ?>

                        </td>


                        <!-- TANGGAL -->

                        <td>

                            <div
                                class="laporan-date"
                            >

                                <?= date(
                                    "d-m-Y",
                                    strtotime(
                                        $row['tanggal']
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- KARYAWAN -->

                        <td>

                            <div
                                class="laporan-nama"
                            >

                                <?= htmlspecialchars(
                                    $row['nama']
                                ) ?>

                            </div>

                            <div
                                class="laporan-nik"
                            >

                                NIK:

                                <?= htmlspecialchars(
                                    $row['nik']
                                ) ?>

                            </div>

                        </td>


                        <!-- JABATAN -->

                        <td>

                            <?= htmlspecialchars(
                                $row['jabatan'] ?? '-'
                            ) ?>

                        </td>


                        <!-- TAP IN -->

                        <td>

                            <?php if (
                                !empty(
                                    $row['jam_tap_in']
                                )
                            ): ?>

                                <span
                                    class="laporan-jam"
                                >

                                    <?= date(
                                        "H:i",
                                        strtotime(
                                            $row['jam_tap_in']
                                        )
                                    ) ?>

                                </span>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <!-- TAP OUT -->

                        <td>

                            <?php if (
                                !empty(
                                    $row['jam_tap_out']
                                )
                            ): ?>

                                <span
                                    class="laporan-jam"
                                >

                                    <?= date(
                                        "H:i",
                                        strtotime(
                                            $row['jam_tap_out']
                                        )
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#94a3b8;
                                    "
                                >

                                    -

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- STATUS -->

                        <td>


                            <?php if (
                                !empty(
                                    $row['jam_tap_in']
                                ) &&
                                !empty(
                                    $row['jam_tap_out']
                                )
                            ): ?>


                                <span
                                    class="
                                        laporan-status
                                        laporan-status-selesai
                                    "
                                >

                                    Selesai

                                </span>


                            <?php elseif (
                                !empty(
                                    $row['jam_tap_in']
                                )
                            ): ?>


                                <span
                                    class="
                                        laporan-status
                                        laporan-status-bekerja
                                    "
                                >

                                    Bekerja

                                </span>


                            <?php else: ?>


                                <span
                                    class="
                                        laporan-status
                                        laporan-status-belum
                                    "
                                >

                                    Belum Hadir

                                </span>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


            <?php else: ?>


                <div class="laporan-empty">

                    Tidak ada data absensi
                    pada periode yang dipilih.

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>