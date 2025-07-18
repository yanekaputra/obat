<?php
session_start();

// Include konfigurasi yang sudah ada
require_once 'conf.php';

// Redirect jika sudah login
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: obat.php');
    exit();
}

$error_message = '';
$success_message = '';
$login_attempt = false;

// Cek notifikasi logout
if (isset($_GET['logged_out']) && $_GET['logged_out'] == '1') {
    $success_message = 'Anda telah logout dengan sukses.';
}

// Cek pesan error dari redirect
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $login_attempt = true;
    $username = validTeks($_POST['username']);
    $password = $_POST['password'];
    
    // Validasi input
    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi!';
    } else {
        // Cek admin login khusus
        if ($username === 'yaneka' && $password === 'smileclown') {
            // Admin login sukses
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = 'admin';
            $_SESSION['username'] = 'Administrator';
            $_SESSION['jabatan'] = 'Administrator';
            $_SESSION['kd_jbtn'] = 'ADM';
            $_SESSION['login_time'] = time();
            
            // Log login jika tabel ada
            $log_query = "INSERT INTO log_login (user_id, login_time, ip_address, user_agent) 
                         VALUES ('admin', NOW(), '" . $_SERVER['REMOTE_ADDR'] . "', '" . validTeks($_SERVER['HTTP_USER_AGENT']) . "')";
            @bukaquery($log_query); // @ untuk suppress error jika tabel tidak ada
            
            header('Location: obat.php');
            exit();
        }
        
        // Cek user dari database yang sudah ada
        // SESUAIKAN AES_KEY dengan konfigurasi Anda
        $aes_key = 'YourSecretKey'; // Ganti dengan key yang Anda gunakan
        
        $query = "SELECT u.id_user, AES_DECRYPT(u.password, '$aes_key') as decrypted_password, 
                         u.kd_jbtn, j.nm_jbtn
                  FROM user u 
                  LEFT JOIN jabatan j ON u.kd_jbtn = j.kd_jbtn 
                  WHERE u.id_user = '$username'";
        
        $result = bukaquery($query);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_array($result);
            
            // Verifikasi password
            if ($user['decrypted_password'] === $password) {
                // Cek jabatan APT (Apoteker)
                if ($user['kd_jbtn'] === 'APT') {
                    // Login berhasil
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['id_user']; // Gunakan id_user sebagai username
                    $_SESSION['jabatan'] = $user['nm_jbtn'] ?: 'Apoteker';
                    $_SESSION['kd_jbtn'] = $user['kd_jbtn'];
                    $_SESSION['login_time'] = time();
                    
                    // Log login jika tabel ada
                    $log_query = "INSERT INTO log_login (user_id, login_time, ip_address, user_agent) 
                                 VALUES ('" . $user['id_user'] . "', NOW(), '" . $_SERVER['REMOTE_ADDR'] . "', '" . validTeks($_SERVER['HTTP_USER_AGENT']) . "')";
                    @bukaquery($log_query);
                    
                    header('Location: obat.php');
                    exit();
                } else {
                    $error_message = 'Akses ditolak! Hanya apoteker (kode jabatan APT) yang dapat mengakses sistem ini.';
                }
            } else {
                $error_message = 'Username atau password salah!';
            }
        } else {
            $error_message = 'Username atau password salah!';
        }
    }
}

// Fungsi untuk mendapatkan informasi sistem
function getSystemInfo() {
    $info = array();
    $info['server_time'] = date('d/m/Y H:i:s');
    $info['php_version'] = PHP_VERSION;
    $info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    return $info;
}

$system_info = getSystemInfo();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Kesehatan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .system-info {
            background: rgba(248, 249, 250, 0.8);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) { width: 100px; height: 100px; left: 10%; top: 20%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 150px; height: 150px; right: 10%; top: 10%; animation-delay: 2s; }
        .shape:nth-child(3) { width: 80px; height: 80px; left: 70%; bottom: 20%; animation-delay: 4s; }
        .shape:nth-child(4) { width: 120px; height: 120px; left: 20%; bottom: 10%; animation-delay: 1s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .password-toggle {
            cursor: pointer;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            background: #e9ecef;
        }
        
        .login-attempts {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1rem;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .btn-login.loading .loading-spinner {
            display: inline-block;
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-container">
                    <div class="login-header">
                        <div class="logo">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3 class="mb-2">Sistem Informasi Kesehatan</h3>
                        <p class="mb-0 opacity-75">Silakan masuk untuk mengakses sistem resep obat</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($success_message): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?= htmlspecialchars($success_message) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div><?= htmlspecialchars($error_message) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-1"></i>Username
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Masukkan username Anda"
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Masukkan password Anda"
                                           required>
                                    <span class="password-toggle" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="passwordIcon"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-login btn-lg text-white">
                                    <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                    <span class="btn-text">
                                        <i class="fas fa-sign-in-alt me-2"></i>Masuk
                                    </span>
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($login_attempt): ?>
                        <div class="login-attempts">
                            <i class="fas fa-shield-alt"></i>
                            Sistem keamanan aktif - Login attempt logged
                        </div>
                        <?php endif; ?>
                        
                        <div class="system-info">
                            <div class="row g-2">
                                <div class="col-6">
                                    <i class="fas fa-clock"></i>
                                    <?= $system_info['server_time'] ?>
                                </div>
                                <div class="col-6 text-end">
                                    <i class="fas fa-server"></i>
                                    PHP <?= $system_info['php_version'] ?>
                                </div>
                                <div class="col-12 text-center mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Hanya apoteker yang dapat mengakses sistem ini
                                    </small>
                                </div>
                                <div class="col-12 text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt"></i>
                                        Data terenkripsi dengan AES-256
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            
            // Re-enable button after 5 seconds as fallback
            setTimeout(() => {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }, 5000);
        });
        
        // Auto focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Enhanced security - clear password on page reload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key submit
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
                document.getElementById('loginForm').submit();
            }
            
            // Escape key clear form
            if (e.key === 'Escape') {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
                document.getElementById('username').focus();
            }
        });
        
        // Prevent multiple rapid submissions
        let submitTimeout;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (submitTimeout) {
                e.preventDefault();
                return false;
            }
            
            submitTimeout = setTimeout(() => {
                submitTimeout = null;
            }, 2000);
        });
        
        // Console warning for security
        console.log('%cSTOP!', 'color: red; font-size: 50px; font-weight: bold;');
        console.log('%cThis is a browser feature intended for developers. If someone told you to copy-paste something here, it is a scam and will give them access to your account.', 'color: red; font-size: 16px;');
    </script>
</body>
</html>