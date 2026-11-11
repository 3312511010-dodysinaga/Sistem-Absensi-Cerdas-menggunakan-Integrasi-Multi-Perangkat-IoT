<?php

require_once "koneksi.php";

$username = "admin";
$passwordBaru = "admin123";

$hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    UPDATE users
    SET password = ?,
        role = 'admin',
        status = 'aktif'
    WHERE username = ?
");

$stmt->bind_param("ss", $hash, $username);

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {
        echo "Password admin berhasil direset.<br><br>";
        echo "Username: <b>admin</b><br>";
        echo "Password: <b>admin123</b>";
    } else {
        echo "Akun admin tidak ditemukan.";
    }

} else {
    echo "Gagal reset password: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>