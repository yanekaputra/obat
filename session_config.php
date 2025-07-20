<?php
/**
 * File: session_config.php
 * Konfigurasi session untuk obat.php
 * Mengatasi error include session_config.php
 */

// Pastikan session belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    // Konfigurasi session basic
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set 1 jika HTTPS
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 28800); // 8 jam
    
    // Set session name
    session_name('SISOBAT_SESSION');
}

// Flag bahwa config sudah dimuat
define('SESSION_CONFIG_LOADED', true);
?>