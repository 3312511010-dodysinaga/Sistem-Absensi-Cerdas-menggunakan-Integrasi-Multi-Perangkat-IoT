<?php

session_start();

require_once "koneksi.php";


/* =========================================================
   JIKA SUDAH LOGIN
========================================================= */

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role'])
) {

    if ($_SESSION['role'] === 'admin') {

        header("Location: admin/index.php");
        exit;
    }

    if ($_SESSION['role'] === 'karyawan') {

        header("Location: karyawan/index.php");
        exit;
    }
}


/* =========================================================
   LOGIN
========================================================= */

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    /* =====================================================
       VALIDASI INPUT
    ===================================================== */

    if (
        $username === '' ||
        $password === ''
    ) {

        $error =
            "Username dan password wajib diisi.";

    } else {


        /* =================================================
           CARI USER
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                username,
                password,
                nama,
                role,
                pegawai_id,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");


        /* =================================================
           CEK PREPARE
        ================================================= */

        if ($stmt === false) {

            $error =
                "Terjadi kesalahan database: " .
                $conn->error;

        } else {


            /* =============================================
               BIND USERNAME
            ============================================= */

            $stmt->bind_param(
                "s",
                $username
            );


            /* =============================================
               EXECUTE
            ============================================= */

            if (!$stmt->execute()) {

                $error =
                    "Gagal menjalankan proses login.";

            } else {


                /* =========================================
                   AMBIL HASIL
                ========================================= */

                $result =
                    $stmt->get_result();


                if (
                    $result &&
                    $result->num_rows === 1
                ) {

                    $user =
                        $result->fetch_assoc();


                    /* =====================================
                       CEK STATUS AKUN
                    ===================================== */

                    if (
                        isset($user['status']) &&
                        $user['status'] !== 'aktif'
                    ) {

                        $error =
                            "Akun Anda sedang tidak aktif.";

                    } else {


                        /* =================================
                           CEK PASSWORD
                        ================================= */

                        if (
                            password_verify(
                                $password,
                                $user['password']
                            )
                        ) {


                            /* =============================
                               SESSION
                            ============================= */

                            session_regenerate_id(true);


                            $_SESSION['user_id'] =
                                $user['id'];

                            $_SESSION['username'] =
                                $user['username'];

                            $_SESSION['nama'] =
                                $user['nama'];

                            $_SESSION['role'] =
                                $user['role'];

                            $_SESSION['pegawai_id'] =
                                $user['pegawai_id'];


                            /* =============================
                               REDIRECT ADMIN
                            ============================= */

                            if (
                                $user['role'] === 'admin'
                            ) {

                                header(
                                    "Location: admin/index.php"
                                );

                                exit;
                            }


                            /* =============================
                               REDIRECT KARYAWAN
                            ============================= */

                            if (
                                $user['role'] === 'karyawan'
                            ) {

                                header(
                                    "Location: karyawan/index.php"
                                );

                                exit;
                            }


                            /* =============================
                               ROLE TIDAK VALID
                            ============================= */

                            $error =
                                "Role pengguna tidak valid.";

                        } else {

                            $error =
                                "Username atau password salah.";
                        }
                    }

                } else {

                    $error =
                        "Username atau password salah.";
                }
            }


            $stmt->close();
        }
    }
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

<title>Absensi System</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #111827,
            #312e81,
            #4c1d95
        );
}


.login-wrapper {

    width: 420px;

    max-width:
        calc(100% - 30px);
}


.login-card {

    background: white;

    border-radius: 20px;

    padding: 35px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.3);
}


.logo {

    width: 65px;

    height: 65px;

    margin:
        0 auto 18px;

    border-radius: 17px;

    display: flex;

    justify-content: center;

    align-items: center;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #7c3aed
        );

    color: white;

    font-size: 30px;

    font-weight: bold;
}


h1 {

    text-align: center;

    margin: 0;

    color: #111827;

    font-size: 25px;
}


.subtitle {

    text-align: center;

    margin:
        8px 0 28px;

    color: #6b7280;

    font-size: 13px;
}


.form-group {

    margin-bottom: 16px;
}


label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #374151;
}


input {

    width: 100%;

    height: 45px;

    padding: 0 13px;

    border:
        1px solid #d1d5db;

    border-radius: 9px;

    outline: none;

    font-size: 13px;
}


input:focus {

    border-color: #6366f1;

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,.1);
}


.btn-admin {

    width: 100%;

    height: 45px;

    border: none;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #7c3aed
        );

    color: white;

    font-weight: 600;

    cursor: pointer;
}


.btn-admin:hover {

    opacity: .92;
}


.error {

    margin-bottom: 16px;

    padding: 12px;

    border-radius: 8px;

    background: #fee2e2;

    color: #b91c1c;

    font-size: 12px;
}


.divider {

    display: flex;

    align-items: center;

    gap: 12px;

    margin: 25px 0;

    color: #9ca3af;

    font-size: 11px;
}


.divider::before,
.divider::after {

    content: "";

    flex: 1;

    height: 1px;

    background: #e5e7eb;
}


.employee-login {

    padding: 20px;

    border-radius: 13px;

    background: #f8fafc;

    border:
        1px solid #e5e7eb;

    text-align: center;
}


.employee-login-title {

    font-size: 15px;

    font-weight: 700;

    color: #111827;
}


.employee-login-text {

    margin:
        6px 0 15px;

    color: #64748b;

    font-size: 11px;
}


.btn-fingerprint {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    width: 100%;

    height: 45px;

    border-radius: 9px;

    background: #111827;

    color: white;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;
}


.btn-fingerprint:hover {

    background: #1f2937;
}


.footer {

    margin-top: 22px;

    text-align: center;

    color: #9ca3af;

    font-size: 10px;
}

</style>

</head>


<body>


<div class="login-wrapper">


    <div class="login-card">


        <div class="logo">
            P
        </div>


        <h1>
            ABSENSI SYSTEM
        </h1>


        <div class="subtitle">
            Sistem Absensi Fingerprint
        </div>


        <!-- =================================================
             LOGIN ADMIN
        ================================================== -->

        <form method="POST">


            <?php if ($error !== ''): ?>

                <div class="error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <div class="form-group">

                <label>
                    Username Admin
                </label>

                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan username"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn-admin"
            >

                Login Admin

            </button>


        </form>


        <!-- =================================================
             PEMISAH
        ================================================== -->

        <div class="divider">

            ATAU

        </div>


        <!-- =================================================
             LOGIN KARYAWAN
        ================================================== -->

        <div class="employee-login">


            <div class="employee-login-title">

                Login Karyawan

            </div>


            <div class="employee-login-text">

                Masuk menggunakan fingerprint

            </div>


            <a
                href="karyawan/login.php"
                class="btn-fingerprint"
            >

                ☝
                Masuk dengan Fingerprint

            </a>


        </div>


        <div class="footer">

            © <?= date('Y') ?>
            Absensi System

        </div>


    </div>


</div>


</body>

</html>