<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekAdmin();


// ============================================================
// PROSES SETUJUI / TOLAK IZIN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    if ($id > 0) {

        if ($aksi === 'setujui') {

            $statusBaru = 'disetujui';

        } elseif ($aksi === 'tolak') {

            $statusBaru = 'ditolak';

        } else {

            $statusBaru = '';
        }


        if ($statusBaru !== '') {

            $stmt = $conn->prepare("
                UPDATE izin
                SET status = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $statusBaru,
                $id
            );

            if ($stmt->execute()) {

                if ($statusBaru === 'disetujui') {

                    $pesan =
                        "Pengajuan izin berhasil disetujui";

                } else {

                    $pesan =
                        "Pengajuan izin berhasil ditolak";
                }

                header(
                    "Location: izin.php?success=" .
                    urlencode($pesan)
                );

                exit;

            } else {

                header(
                    "Location: izin.php?error=" .
                    urlencode(
                        "Gagal memproses pengajuan izin"
                    )
                );

                exit;
            }
        }
    }
}


// ============================================================
// STATISTIK IZIN
// ============================================================

$qMenunggu = $conn->query("
    SELECT COUNT(*) AS total
    FROM izin
    WHERE status = 'menunggu'
");

$totalMenunggu =
    $qMenunggu->fetch_assoc()['total'];


$qDisetujui = $conn->query("
    SELECT COUNT(*) AS total
    FROM izin
    WHERE status = 'disetujui'
");

$totalDisetujui =
    $qDisetujui->fetch_assoc()['total'];


$qDitolak = $conn->query("
    SELECT COUNT(*) AS total
    FROM izin
    WHERE status = 'ditolak'
");

$totalDitolak =
    $qDitolak->fetch_assoc()['total'];


// ============================================================
// DATA PENGAJUAN IZIN
// ============================================================

$result = $conn->query("
    SELECT
        izin.id,
        izin.tanggal_mulai,
        izin.tanggal_selesai,
        izin.alasan,
        izin.status,
        izin.created_at,

        pegawai.nama,
        pegawai.nik,
        pegawai.jabatan,
        pegawai.divisi

    FROM izin

    INNER JOIN pegawai
        ON pegawai.id = izin.pegawai_id

    ORDER BY
        CASE
            WHEN izin.status = 'menunggu'
            THEN 1
            ELSE 2
        END,

        izin.created_at DESC
");

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
    Pengajuan Izin
</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>


<style>

/* ============================================================
   HEADER
============================================================ */

.izin-heading {

    margin-bottom: 25px;
}

.izin-heading h1 {

    font-size: 28px;

    font-weight: 700;

    color: #111827;

    margin-bottom: 5px;
}

.izin-heading p {

    color: #64748b;

    font-size: 13px;
}


/* ============================================================
   STATISTIK
============================================================ */

.izin-stat {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}

.izin-stat-card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.izin-stat-label {

    color: #64748b;

    font-size: 12px;

    margin-bottom: 8px;
}

.izin-stat-value {

    font-size: 28px;

    font-weight: 700;

    color: #111827;
}


/* WARNA STAT */

.izin-warning .izin-stat-value {

    color: #d97706;
}

.izin-success .izin-stat-value {

    color: #16a34a;
}

.izin-danger .izin-stat-value {

    color: #dc2626;
}


/* ============================================================
   CARD TABEL
============================================================ */

.izin-card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 25px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.izin-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}

.izin-card-header h2 {

    margin: 0;

    font-size: 18px;

    color: #111827;
}

.izin-card-header p {

    margin-top: 4px;

    color: #64748b;

    font-size: 12px;
}


/* ============================================================
   NAMA PEGAWAI
============================================================ */

.izin-nama {

    color: #111827;

    font-size: 13px;

    font-weight: 700;
}

.izin-nik {

    margin-top: 3px;

    color: #94a3b8;

    font-size: 10px;
}


/* ============================================================
   TANGGAL
============================================================ */

.izin-tanggal {

    white-space: nowrap;

    color: #374151;

    font-size: 12px;

    line-height: 1.6;
}


/* ============================================================
   ALASAN
============================================================ */

.izin-alasan {

    max-width: 220px;

    color: #475569;

    font-size: 12px;

    line-height: 1.5;
}


/* ============================================================
   STATUS
============================================================ */

.izin-status {

    display: inline-flex;

    align-items: center;

    padding:
        5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;
}

.izin-status-menunggu {

    background: #fef3c7;

    color: #92400e;
}

.izin-status-disetujui {

    background: #dcfce7;

    color: #166534;
}

.izin-status-ditolak {

    background: #fee2e2;

    color: #991b1b;
}


/* ============================================================
   ACTION
============================================================ */

.izin-action {

    display: flex;

    gap: 5px;

    align-items: center;
}

.izin-btn {

    border: none;

    padding:
        7px 10px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 10px;

    font-weight: 600;
}

.izin-btn-setuju {

    background: #dcfce7;

    color: #166534;
}

.izin-btn-setuju:hover {

    background: #bbf7d0;
}

.izin-btn-tolak {

    background: #fee2e2;

    color: #991b1b;
}

.izin-btn-tolak:hover {

    background: #fecaca;
}


/* ============================================================
   ALERT
============================================================ */

.izin-alert {

    padding:
        12px 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 12px;
}

.izin-alert-success {

    background: #dcfce7;

    border:
        1px solid #bbf7d0;

    color: #166534;
}

.izin-alert-error {

    background: #fee2e2;

    border:
        1px solid #fecaca;

    color: #991b1b;
}


/* ============================================================
   EMPTY
============================================================ */

.izin-empty {

    padding: 50px 20px;

    text-align: center;

    color: #94a3b8;

    font-size: 13px;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 850px) {

    .izin-stat {

        grid-template-columns: 1fr;
    }

    .izin-card {

        padding: 18px;
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

    <div class="izin-heading">

        <h1>
            Pengajuan Izin
        </h1>

        <p>
            Kelola pengajuan izin karyawan
        </p>

    </div>


    <!-- ========================================================
         ALERT SUCCESS
    ========================================================= -->

    <?php if (isset($_GET['success'])): ?>

        <div class="izin-alert izin-alert-success">

            ✓
            <?= htmlspecialchars(
                $_GET['success']
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         ALERT ERROR
    ========================================================= -->

    <?php if (isset($_GET['error'])): ?>

        <div class="izin-alert izin-alert-error">

            ✕
            <?= htmlspecialchars(
                $_GET['error']
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         STATISTIK
    ======================================================== -->

    <div class="izin-stat">


        <!-- MENUNGGU -->

        <div class="izin-stat-card izin-warning">

            <div class="izin-stat-label">

                Menunggu Persetujuan

            </div>

            <div class="izin-stat-value">

                <?= $totalMenunggu ?>

            </div>

        </div>


        <!-- DISETUJUI -->

        <div class="izin-stat-card izin-success">

            <div class="izin-stat-label">

                Disetujui

            </div>

            <div class="izin-stat-value">

                <?= $totalDisetujui ?>

            </div>

        </div>


        <!-- DITOLAK -->

        <div class="izin-stat-card izin-danger">

            <div class="izin-stat-label">

                Ditolak

            </div>

            <div class="izin-stat-value">

                <?= $totalDitolak ?>

            </div>

        </div>


    </div>


    <!-- ========================================================
         TABEL PENGAJUAN
    ======================================================== -->

    <div class="izin-card">


        <div class="izin-card-header">

            <div>

                <h2>
                    Daftar Pengajuan Izin
                </h2>

                <p>
                    Periksa dan proses pengajuan izin karyawan
                </p>

            </div>

        </div>


        <div class="table-responsive">


            <?php if (
                $result &&
                $result->num_rows > 0
            ): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            Karyawan
                        </th>

                        <th>
                            Jabatan
                        </th>

                        <th>
                            Tanggal Mulai
                        </th>

                        <th>
                            Tanggal Selesai
                        </th>

                        <th>
                            Alasan
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


                <?php while (
                    $row =
                    $result->fetch_assoc()
                ): ?>


                    <tr>


                        <!-- KARYAWAN -->

                        <td>

                            <div class="izin-nama">

                                <?= htmlspecialchars(
                                    $row['nama']
                                ) ?>

                            </div>

                            <div class="izin-nik">

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


                        <!-- MULAI -->

                        <td>

                            <div class="izin-tanggal">

                                <?= date(
                                    "d-m-Y",
                                    strtotime(
                                        $row['tanggal_mulai']
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- SELESAI -->

                        <td>

                            <div class="izin-tanggal">

                                <?= date(
                                    "d-m-Y",
                                    strtotime(
                                        $row['tanggal_selesai']
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- ALASAN -->

                        <td>

                            <div class="izin-alasan">

                                <?= nl2br(
                                    htmlspecialchars(
                                        $row['alasan']
                                    )
                                ) ?>

                            </div>

                        </td>


                        <!-- STATUS -->

                        <td>


                            <?php if (
                                $row['status']
                                === 'menunggu'
                            ): ?>

                                <span
                                    class="
                                    izin-status
                                    izin-status-menunggu
                                    "
                                >
                                    Menunggu
                                </span>


                            <?php elseif (
                                $row['status']
                                === 'disetujui'
                            ): ?>

                                <span
                                    class="
                                    izin-status
                                    izin-status-disetujui
                                    "
                                >
                                    Disetujui
                                </span>


                            <?php else: ?>

                                <span
                                    class="
                                    izin-status
                                    izin-status-ditolak
                                    "
                                >
                                    Ditolak
                                </span>

                            <?php endif; ?>


                        </td>


                        <!-- AKSI -->

                        <td>


                            <?php if (
                                $row['status']
                                === 'menunggu'
                            ): ?>


                                <div
                                    class="izin-action"
                                >


                                    <!-- SETUJUI -->

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Yakin ingin menyetujui izin ini?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $row['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="setujui"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                            izin-btn
                                            izin-btn-setuju
                                            "
                                        >
                                            ✓ Setujui
                                        </button>

                                    </form>


                                    <!-- TOLAK -->

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Yakin ingin menolak izin ini?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $row['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="tolak"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                            izin-btn
                                            izin-btn-tolak
                                            "
                                        >
                                            ✕ Tolak
                                        </button>

                                    </form>


                                </div>


                            <?php else: ?>


                                <span
                                    style="
                                    color:#94a3b8;
                                    font-size:10px;
                                    "
                                >
                                    Sudah diproses
                                </span>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>

            </table>


            <?php else: ?>


                <div class="izin-empty">

                    Belum ada pengajuan izin dari karyawan.

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>