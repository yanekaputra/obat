<?php
session_start();

// Jika sudah login, redirect ke halaman utama
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: obat.php');
    exit();
}

// Jika belum login, redirect ke halaman login
header('Location: login.php');
exit();
?>