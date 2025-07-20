<?php
require_once 'session_config.php';
session_start();

// Jika sudah login, redirect ke obat.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: obat.php');
    exit;
}

// Proses login
$error = '';
$login_attempt = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_attempt = true;
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === 'lalaapoteker' && $password === 'apotekerrscm') {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['user_type'] = 'apoteker';
        header('Location: obat.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}

// Fungsi untuk mendapatkan waktu saat ini
function getCurrentTime() {
    return [
        'date' => date('d F Y'),
        'time' => date('H:i:s'),
        'day' => date('l')
    ];
}

$current_time = getCurrentTime();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Informasi Obat RSCM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            left: 10%;
            top: 20%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            right: 10%;
            top: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            left: 70%;
            bottom: 20%;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            left: 20%;
            bottom: 10%;
            animation-delay: 1s;
        }

        .shape:nth-child(5) {
            width: 90px;
            height: 90px;
            right: 20%;
            top: 60%;
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(20px) rotate(240deg); }
        }

        /* Main Container */
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .login-wrapper:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        /* Header Section */
        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
        }

        .hospital-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .login-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Time Display */
        .time-display {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.8rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .time-display .time {
            font-weight: 500;
            font-size: 1rem;
        }

        /* Body Section */
        .login-body {
            padding: 2.5rem 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .input-group-text {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-right: none;
            border-radius: 12px 0 0 12px;
            padding: 1rem;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-loading {
            opacity: 0.8;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: none;
        }

        .btn-loading .loading-spinner {
            display: inline-block;
        }

        .btn-loading .btn-text {
            display: none;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        /* Footer Section */
        .login-footer {
            background: #f8fafc;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .version-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }

        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 10px;
            }
            
            .login-wrapper {
                margin: 10px;
            }
            
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .footer-info {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .password-wrapper {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>
    </div>

    <div class="main-container">
        <div class="login-wrapper">
            <!-- Header -->
            <div class="login-header">
                <div class="hospital-logo">
                    <i class="fas fa-hospital-alt"></i>
                </div>
                <h1 class="login-title">RSCM APOTEK</h1>
                <p class="login-subtitle">DRUGS REPORT</p>
                
                <div class="time-display">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?= $current_time['day'] ?>, <?= $current_time['date'] ?>
                        </div>
                        <div class="time" id="current-time">
                            <i class="fas fa-clock me-1"></i>
                            <?= $current_time['time'] ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="form-floating mb-3">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" 
                                   name="username" 
                                   id="username" 
                                   class="form-control" 
                                   placeholder=""
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   required 
                                   autofocus>
                        </div>
                        <label for="username" class="ms-5">Username Apoteker</label>
                    </div>

                    <div class="form-floating mb-4">
                        <div class="input-group password-wrapper">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control" 
                                   placeholder=""
                                   required>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="passwordIcon"></i>
                            </span>
                        </div>
                        <label for="password" class="ms-5">Password</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-login btn-lg text-white" id="loginBtn">
                            <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                            <span class="btn-text">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Masuk ke Sistem
                            </span>
                        </button>
                    </div>
                </form>

                
            <!-- Footer -->
            <div class="login-footer">
                <div class="footer-info">
                    <div class="version-info">
                        <span class="status-indicator"></span>
                        <span>Sistem Online</span>
                    </div>
                    <div>
                        <i class="fas fa-server me-1"></i>
                        PHP <?= PHP_VERSION ?>
                    </div>
                    <div>
                        <i class="fas fa-shield-alt me-1"></i>
                        SSL Secured
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        © <?= date('Y') ?> Yan Eka Putra | RSCM - Rumah Sakit Cahaya Medika. All rights reserved.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time clock
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').innerHTML = '<i class="fas fa-clock me-1"></i>' + timeString;
        }

        // Update time every second
        setInterval(updateTime, 1000);

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
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.classList.add('btn-loading');
            loginBtn.disabled = true;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                loginBtn.classList.remove('btn-loading');
                loginBtn.disabled = false;
            }, 5000);
        });

        // Enhanced form interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input
            document.getElementById('username').focus();
            
            // Add floating label effect
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Enter key anywhere submits form
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    e.preventDefault();
                    document.getElementById('loginForm').submit();
                }
                
                // Escape key clears form
                if (e.key === 'Escape') {
                    document.getElementById('username').value = '';
                    document.getElementById('password').value = '';
                    document.getElementById('username').focus();
                }
            });
        });

        // Prevent multiple form submissions
        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
            
            // Reset after 3 seconds
            setTimeout(() => {
                formSubmitted = false;
            }, 3000);
        });

        // Enhanced security warnings
        console.log('%cSTOP!', 'color: red; font-size: 50px; font-weight: bold;');
        console.log('%cJangan masukkan kode atau perintah di sini kecuali Anda tahu persis apa yang Anda lakukan. Ini bisa membahayakan keamanan sistem.', 'color: red; font-size: 16px;');

        // Page loaded successfully
        console.log('✅ RSCM Login System loaded successfully');
        console.log('🔒 Security features enabled');
        console.log('⚡ Enhanced UI active');
    </script>
</body>
</html>