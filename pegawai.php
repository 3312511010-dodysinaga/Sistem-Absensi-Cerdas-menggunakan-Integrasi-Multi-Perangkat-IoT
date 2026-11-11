<?php

require_once "../koneksi.php";
require_once "../config/auth.php";

cekAdmin();


// ============================================================
// PROSES HAPUS PEGAWAI
// ============================================================

if (isset($_GET['hapus'])) {

    $id = intval($_GET['hapus']);

    if ($id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM pegawai
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: pegawai.php");
    exit;
}


// ============================================================
// PROSES TAMBAH / EDIT PEGAWAI
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    $id = intval($_POST['id'] ?? 0);

    $nik = trim($_POST['nik'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $divisi = trim($_POST['divisi'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $status = $_POST['status'] ?? 'aktif';

    $fingerprint_id_raw =
        trim($_POST['fingerprint_id'] ?? '');


    // ========================================================
    // FINGERPRINT ID
    // ========================================================

    if ($fingerprint_id_raw === '') {

        $fingerprint_id = null;

    } else {

        $fingerprint_id =
            intval($fingerprint_id_raw);
    }


    // ========================================================
    // VALIDASI
    // ========================================================

    if (
        $nik === '' ||
        $nama === ''
    ) {

        header(
            "Location: pegawai.php?error=" .
            urlencode("NIK dan nama wajib diisi")
        );

        exit;
    }


    // ========================================================
    // VALIDASI STATUS
    // ========================================================

    if (
        $status !== 'aktif' &&
        $status !== 'nonaktif'
    ) {

        $status = 'aktif';
    }


    // ========================================================
    // TAMBAH
    // ========================================================

    if ($aksi === 'tambah') {

        $stmt = $conn->prepare("
            INSERT INTO pegawai
            (
                nik,
                nama,
                jabatan,
                divisi,
                no_hp,
                fingerprint_id,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ");


        if ($stmt === false) {

            header(
                "Location: pegawai.php?error=" .
                urlencode(
                    "Query tambah pegawai gagal: " .
                    $conn->error
                )
            );

            exit;
        }


        $stmt->bind_param(
            "sssssis",
            $nik,
            $nama,
            $jabatan,
            $divisi,
            $no_hp,
            $fingerprint_id,
            $status
        );


        if (!$stmt->execute()) {

            $errorCode = $stmt->errno;

            if ($errorCode == 1062) {

                $pesan =
                    "NIK atau Fingerprint ID sudah digunakan";

            } else {

                $pesan =
                    "Gagal menambahkan pegawai: " .
                    $stmt->error;
            }


            $stmt->close();


            header(
                "Location: pegawai.php?error=" .
                urlencode($pesan)
            );

            exit;
        }


        $stmt->close();


        header(
            "Location: pegawai.php?success=" .
            urlencode(
                "Pegawai berhasil ditambahkan"
            )
        );

        exit;
    }


    // ========================================================
    // EDIT
    // ========================================================

    if ($aksi === 'edit' && $id > 0) {

        $stmt = $conn->prepare("
            UPDATE pegawai
            SET
                nik = ?,
                nama = ?,
                jabatan = ?,
                divisi = ?,
                no_hp = ?,
                fingerprint_id = ?,
                status = ?
            WHERE id = ?
        ");


        if ($stmt === false) {

            header(
                "Location: pegawai.php?error=" .
                urlencode(
                    "Query edit pegawai gagal: " .
                    $conn->error
                )
            );

            exit;
        }


        /*
         * s = nik
         * s = nama
         * s = jabatan
         * s = divisi
         * s = no_hp
         * i = fingerprint_id
         * s = status
         * i = id
         */

        $stmt->bind_param(
            "sssssisi",
            $nik,
            $nama,
            $jabatan,
            $divisi,
            $no_hp,
            $fingerprint_id,
            $status,
            $id
        );


        if (!$stmt->execute()) {

            $pesan =
                "Gagal mengubah data pegawai: " .
                $stmt->error;


            $stmt->close();


            header(
                "Location: pegawai.php?error=" .
                urlencode($pesan)
            );

            exit;
        }


        $stmt->close();


        header(
            "Location: pegawai.php?success=" .
            urlencode(
                "Data pegawai berhasil diperbarui"
            )
        );

        exit;
    }
}


// ============================================================
// MODE EDIT
// ============================================================

$editData = null;

if (isset($_GET['edit'])) {

    $editId = intval($_GET['edit']);

    if ($editId > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM pegawai
            WHERE id = ?
            LIMIT 1
        ");


        if ($stmt) {

            $stmt->bind_param(
                "i",
                $editId
            );

            $stmt->execute();

            $resultEdit =
                $stmt->get_result();

            if (
                $resultEdit &&
                $resultEdit->num_rows > 0
            ) {

                $editData =
                    $resultEdit->fetch_assoc();
            }

            $stmt->close();
        }
    }
}


// ============================================================
// DATA PEGAWAI
// ============================================================

$result = $conn->query("
    SELECT *
    FROM pegawai
    ORDER BY id DESC
");


// ============================================================
// TOTAL
// ============================================================

$qTotal = $conn->query("
    SELECT COUNT(*) AS total
    FROM pegawai
");

$totalPegawai = 0;

if ($qTotal) {

    $totalPegawai =
        $qTotal->fetch_assoc()['total'];
}


$qAktif = $conn->query("
    SELECT COUNT(*) AS total
    FROM pegawai
    WHERE status = 'aktif'
");

$totalAktif = 0;

if ($qAktif) {

    $totalAktif =
        $qAktif->fetch_assoc()['total'];
}


$qFingerprint = $conn->query("
    SELECT COUNT(*) AS total
    FROM pegawai
    WHERE fingerprint_id IS NOT NULL
");

$totalFingerprint = 0;

if ($qFingerprint) {

    $totalFingerprint =
        $qFingerprint->fetch_assoc()['total'];
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

<title>Data Pegawai</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<style>

/* ============================================================
   DATA PEGAWAI
============================================================ */

.page-heading {

    margin-bottom: 25px;
}

.page-heading h1 {

    font-size: 28px;

    color: #111827;

    margin-bottom: 5px;
}

.page-heading p {

    color: #64748b;

    font-size: 13px;
}


/* ============================================================
   STAT
============================================================ */

.pegawai-stat {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 22px;
}

.pegawai-stat-card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 20px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.pegawai-stat-card small {

    display: block;

    color: #64748b;

    font-size: 12px;

    margin-bottom: 8px;
}

.pegawai-stat-card strong {

    display: block;

    color: #111827;

    font-size: 27px;
}


/* ============================================================
   HEADER CARD
============================================================ */

.pegawai-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 20px;
}

.pegawai-card-header h2 {

    margin: 0;

    font-size: 18px;
}

.pegawai-card-header p {

    margin-top: 4px;

    color: #64748b;

    font-size: 12px;
}


/* ============================================================
   FORM TAMBAH / EDIT
============================================================ */

.pegawai-form {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 25px;

    margin-bottom: 22px;

    box-shadow:
        0 4px 15px
        rgba(15,23,42,.04);
}

.pegawai-form h2 {

    margin-bottom: 4px;

    font-size: 18px;
}

.pegawai-form > p {

    color: #64748b;

    font-size: 12px;

    margin-bottom: 20px;
}

.pegawai-form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;
}

.pegawai-form-group {

    margin-bottom: 3px;
}

.pegawai-form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #374151;
}

.pegawai-form-group input,
.pegawai-form-group select {

    width: 100%;

    height: 44px;

    padding: 0 13px;

    border:
        1px solid #d1d5db;

    border-radius: 9px;

    outline: none;

    background: white;

    font-size: 13px;
}

.pegawai-form-group input:focus,
.pegawai-form-group select:focus {

    border-color: #4f46e5;

    box-shadow:
        0 0 0 3px
        rgba(79,70,229,.10);
}

.pegawai-help {

    display: block;

    margin-top: 5px;

    color: #94a3b8;

    font-size: 10px;
}

.pegawai-form-actions {

    margin-top: 20px;

    display: flex;

    gap: 8px;
}


/* ============================================================
   BUTTON TAMBAH
============================================================ */

.btn-add {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        10px 15px;

    border-radius: 9px;

    background: #4f46e5;

    color: white;

    border: none;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;
}

.btn-add:hover {

    background: #4338ca;
}


/* ============================================================
   BUTTON TABLE
============================================================ */

.btn-edit {

    display: inline-block;

    padding:
        7px 10px;

    border-radius: 7px;

    background: #dbeafe;

    color: #1d4ed8;

    font-size: 10px;

    font-weight: 600;
}

.btn-edit:hover {

    background: #bfdbfe;
}

.btn-delete {

    display: inline-block;

    padding:
        7px 10px;

    border-radius: 7px;

    background: #fee2e2;

    color: #dc2626;

    font-size: 10px;

    font-weight: 600;
}

.btn-delete:hover {

    background: #fecaca;
}


/* ============================================================
   NAMA PEGAWAI
============================================================ */

.nama-pegawai {

    font-weight: 700;

    color: #111827;
}

.nik-pegawai {

    margin-top: 3px;

    color: #94a3b8;

    font-size: 10px;
}


/* ============================================================
   FINGERPRINT
============================================================ */

.fingerprint-id {

    display: inline-flex;

    align-items: center;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #dcfce7;

    color: #15803d;

    font-size: 10px;

    font-weight: 700;
}

.fingerprint-none {

    display: inline-flex;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #f1f5f9;

    color: #64748b;

    font-size: 10px;
}


/* ============================================================
   STATUS
============================================================ */

.status-aktif {

    display: inline-flex;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #dcfce7;

    color: #15803d;

    font-size: 10px;

    font-weight: 700;
}

.status-nonaktif {

    display: inline-flex;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #fee2e2;

    color: #dc2626;

    font-size: 10px;

    font-weight: 700;
}


/* ============================================================
   ALERT
============================================================ */

.pegawai-alert {

    padding:
        12px 15px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 12px;
}

.pegawai-alert.success {

    background: #dcfce7;

    color: #166534;

    border:
        1px solid #bbf7d0;
}

.pegawai-alert.error {

    background: #fee2e2;

    color: #991b1b;

    border:
        1px solid #fecaca;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 800px) {

    .pegawai-stat {

        grid-template-columns: 1fr;
    }

    .pegawai-form-grid {

        grid-template-columns: 1fr;
    }

    .pegawai-card-header {

        align-items: flex-start;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<?php include "sidebar.php"; ?>


<div class="main">


    <!-- ========================================================
         HEADER
    ========================================================= -->

    <div class="page-heading">

        <h1>
            Data Pegawai
        </h1>

        <p>
            Kelola data karyawan dan identitas fingerprint
        </p>

    </div>


    <!-- ========================================================
         ALERT
    ========================================================= -->

    <?php if (isset($_GET['success'])): ?>

        <div class="pegawai-alert success">

            ✓
            <?= htmlspecialchars(
                $_GET['success']
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['error'])): ?>

        <div class="pegawai-alert error">

            ✕
            <?= htmlspecialchars(
                $_GET['error']
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ========================================================
         STATISTIK
    ========================================================= -->

    <div class="pegawai-stat">


        <div class="pegawai-stat-card">

            <small>
                Total Pegawai
            </small>

            <strong>
                <?= $totalPegawai ?>
            </strong>

        </div>


        <div class="pegawai-stat-card">

            <small>
                Pegawai Aktif
            </small>

            <strong>
                <?= $totalAktif ?>
            </strong>

        </div>


        <div class="pegawai-stat-card">

            <small>
                Sudah Terdaftar Fingerprint
            </small>

            <strong>
                <?= $totalFingerprint ?>
            </strong>

        </div>


    </div>


    <!-- ========================================================
         FORM TAMBAH / EDIT
    ========================================================= -->

    <?php if ($editData): ?>

    <div class="pegawai-form">

        <h2>
            Edit Data Pegawai
        </h2>

        <p>
            Perbarui informasi pegawai
        </p>


        <form
            method="POST"
            action="pegawai.php"
        >

            <input
                type="hidden"
                name="aksi"
                value="edit"
            >

            <input
                type="hidden"
                name="id"
                value="<?= $editData['id'] ?>"
            >


            <div class="pegawai-form-grid">


                <div class="pegawai-form-group">

                    <label>
                        NIK
                    </label>

                    <input
                        type="text"
                        name="nik"
                        required
                        value="<?= htmlspecialchars(
                            $editData['nik']
                        ) ?>"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Nama Lengkap
                    </label>

                    <input
                        type="text"
                        name="nama"
                        required
                        value="<?= htmlspecialchars(
                            $editData['nama']
                        ) ?>"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Jabatan
                    </label>

                    <input
                        type="text"
                        name="jabatan"
                        value="<?= htmlspecialchars(
                            $editData['jabatan'] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Divisi
                    </label>

                    <input
                        type="text"
                        name="divisi"
                        value="<?= htmlspecialchars(
                            $editData['divisi'] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        No. HP
                    </label>

                    <input
                        type="text"
                        name="no_hp"
                        value="<?= htmlspecialchars(
                            $editData['no_hp'] ?? ''
                        ) ?>"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Fingerprint ID
                    </label>

                    <input
                        type="number"
                        name="fingerprint_id"
                        min="1"
                        value="<?= htmlspecialchars(
                            $editData['fingerprint_id'] ?? ''
                        ) ?>"
                    >

                    <small class="pegawai-help">
                        ID fingerprint diisi manual.
                    </small>

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option
                            value="aktif"
                            <?= ($editData['status'] ?? '') === 'aktif'
                                ? 'selected'
                                : '' ?>
                        >
                            Aktif
                        </option>

                        <option
                            value="nonaktif"
                            <?= ($editData['status'] ?? '') === 'nonaktif'
                                ? 'selected'
                                : '' ?>
                        >
                            Nonaktif
                        </option>

                    </select>

                </div>


            </div>


            <div class="pegawai-form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

                <a
                    href="pegawai.php"
                    class="btn btn-danger"
                >
                    Batal
                </a>

            </div>


        </form>

    </div>


    <?php else: ?>


    <div class="pegawai-form">

        <h2>
            Tambah Pegawai
        </h2>

        <p>
            Masukkan data pegawai baru
        </p>


        <form
            method="POST"
            action="pegawai.php"
        >

            <input
                type="hidden"
                name="aksi"
                value="tambah"
            >


            <div class="pegawai-form-grid">


                <div class="pegawai-form-group">

                    <label>
                        NIK *
                    </label>

                    <input
                        type="text"
                        name="nik"
                        required
                        placeholder="Masukkan NIK"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Nama Lengkap *
                    </label>

                    <input
                        type="text"
                        name="nama"
                        required
                        placeholder="Masukkan nama lengkap"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Jabatan
                    </label>

                    <input
                        type="text"
                        name="jabatan"
                        placeholder="Contoh: Staff"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Divisi
                    </label>

                    <input
                        type="text"
                        name="divisi"
                        placeholder="Contoh: Operasional"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        No. HP
                    </label>

                    <input
                        type="text"
                        name="no_hp"
                        placeholder="08xxxxxxxxxx"
                    >

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Fingerprint ID
                    </label>

                    <input
                        type="number"
                        name="fingerprint_id"
                        min="1"
                        placeholder="Contoh: 5"
                    >

                    <small class="pegawai-help">
                        Isi manual sesuai slot fingerprint sensor.
                    </small>

                </div>


                <div class="pegawai-form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="aktif">
                            Aktif
                        </option>

                        <option value="nonaktif">
                            Nonaktif
                        </option>

                    </select>

                </div>


            </div>


            <div class="pegawai-form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    + Tambah Pegawai
                </button>

            </div>


        </form>

    </div>


    <?php endif; ?>


    <!-- ========================================================
         DAFTAR PEGAWAI
    ========================================================= -->

    <div class="table-card">


        <div class="pegawai-card-header">

            <div>

                <h2>
                    Daftar Pegawai
                </h2>

                <p>
                    Data seluruh pegawai yang terdaftar
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
                            No
                        </th>

                        <th>
                            Pegawai
                        </th>

                        <th>
                            Jabatan
                        </th>

                        <th>
                            Divisi
                        </th>

                        <th>
                            No. HP
                        </th>

                        <th>
                            Fingerprint
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

                while (
                    $row =
                    $result->fetch_assoc()
                ):

                ?>


                <tr>


                    <!-- NO -->

                    <td>
                        <?= $no++ ?>
                    </td>


                    <!-- PEGAWAI -->

                    <td>

                        <div class="nama-pegawai">

                            <?= htmlspecialchars(
                                $row['nama']
                            ) ?>

                        </div>

                        <div class="nik-pegawai">

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


                    <!-- DIVISI -->

                    <td>

                        <?= htmlspecialchars(
                            $row['divisi'] ?? '-'
                        ) ?>

                    </td>


                    <!-- NO HP -->

                    <td>

                        <?= htmlspecialchars(
                            $row['no_hp'] ?? '-'
                        ) ?>

                    </td>


                    <!-- FINGERPRINT -->

                    <td>

                        <?php if (
                            !empty(
                                $row['fingerprint_id']
                            )
                        ): ?>

                            <span
                                class="fingerprint-id"
                            >

                                ID
                                <?= htmlspecialchars(
                                    $row['fingerprint_id']
                                ) ?>

                            </span>

                        <?php else: ?>

                            <span
                                class="fingerprint-none"
                            >

                                Belum

                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- STATUS -->

                    <td>

                        <?php if (
                            $row['status']
                            === 'aktif'
                        ): ?>

                            <span
                                class="status-aktif"
                            >

                                Aktif

                            </span>

                        <?php else: ?>

                            <span
                                class="status-nonaktif"
                            >

                                Nonaktif

                            </span>

                        <?php endif; ?>

                    </td>


                    <!-- AKSI -->

                    <td>

                        <a
                            href="pegawai.php?edit=<?= $row['id'] ?>"
                            class="btn-edit"
                        >

                            Edit

                        </a>


                        <a
                            href="pegawai.php?hapus=<?= $row['id'] ?>"
                            class="btn-delete"
                            onclick="
                                return confirm(
                                    'Yakin ingin menghapus pegawai ini?'
                                );
                            "
                        >

                            Hapus

                        </a>

                    </td>


                </tr>


                <?php endwhile; ?>


                </tbody>

            </table>


            <?php else: ?>


                <div class="empty-data">

                    Belum ada data pegawai.

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>