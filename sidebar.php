<?php

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<aside class="sidebar">

    <!-- =====================================================
         LOGO / BRAND
    ====================================================== -->

    <div class="sidebar-logo">

        <div class="logo-icon">
            P
        </div>

        <div>

            <div class="logo-title">
                ABSENSI
            </div>

            <div class="logo-subtitle">
                Fingerprint System
            </div>

        </div>

    </div>


    <!-- =====================================================
         MENU TITLE
    ====================================================== -->

    <div class="menu-title">
        MENU UTAMA
    </div>


    <!-- =====================================================
         MENU
    ====================================================== -->

    <nav class="sidebar-menu">


        <!-- DASHBOARD -->

        <a
            href="index.php"
            class="<?= $currentPage == 'index.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <!-- DATA PEGAWAI -->

        <a
            href="pegawai.php"
            class="<?= $currentPage == 'pegawai.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Data Pegawai
            </span>

        </a>


        <!-- FINGERPRINT -->

        <a
            href="fingerprint.php"
            class="<?= $currentPage == 'fingerprint.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Fingerprint
            </span>

        </a>


        <!-- SCHEDULE KARYAWAN -->

        <a
            href="schedule.php"
            class="<?= $currentPage == 'schedule.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Schedule Karyawan
            </span>

        </a>


        <!-- PENGAJUAN IZIN -->

        <a
            href="izin.php"
            class="<?= $currentPage == 'izin.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Pengajuan Izin
            </span>

        </a>


        <!-- LAPORAN -->

        <a
            href="laporan.php"
            class="<?= $currentPage == 'laporan.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Laporan
            </span>

        </a>


        <!-- PENGATURAN -->

        <a
            href="pengaturan.php"
            class="<?= $currentPage == 'pengaturan.php' ? 'active' : '' ?>"
        >

            <span class="menu-icon">
                
            </span>

            <span>
                Pengaturan
            </span>

        </a>

    </nav>


    <!-- =====================================================
         SIDEBAR BOTTOM
    ====================================================== -->

    <div class="sidebar-bottom">


        <!-- ADMIN -->

        <div class="admin-box">

            <div class="admin-avatar">
                A
            </div>

            <div class="admin-info">

                <strong>
                    Administrator
                </strong>

                <small>
                    Admin
                </small>

            </div>

        </div>


        <!-- LOGOUT -->

        <a
            href="../logout.php"
            class="logout-btn"
        >

            <span>
                ⇥
            </span>

            Keluar

        </a>

    </div>

</aside>