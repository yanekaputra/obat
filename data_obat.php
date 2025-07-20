<?php
ob_start();

// Include session config SEBELUM session_start()
require_once 'session_config.php';
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'conf.php';

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

function logout() {
    // Hancurkan semua data session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

// Function untuk mendapatkan data obat dengan filter expire
function getDataObat($search = '', $expire_filter = 'all', $kategori = '', $golongan = '', $status = '') {
    $whereClause = "WHERE 1=1";
    
    // Filter pencarian
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (db.kode_brng LIKE '%$search%' OR db.nama_brng LIKE '%$search%')";
    }
    
    // Filter kategori
    if ($kategori) {
        $kategori = validTeks($kategori);
        $whereClause .= " AND db.kode_kategori = '$kategori'";
    }
    
    // Filter golongan
    if ($golongan) {
        $golongan = validTeks($golongan);
        $whereClause .= " AND db.kode_golongan = '$golongan'";
    }
    
    // Filter status
    if ($status !== '') {
        $status = validTeks($status);
        $whereClause .= " AND db.status = '$status'";
    }
    
    // Filter expire
    $today = date('Y-m-d');
    $oneMonthFromNow = date('Y-m-d', strtotime('+1 month'));
    
    switch ($expire_filter) {
        case 'expired':
            $whereClause .= " AND db.expire < '$today' AND db.expire != '0000-00-00'";
            break;
        case 'expiring_soon':
            $whereClause .= " AND db.expire BETWEEN '$today' AND '$oneMonthFromNow' AND db.expire != '0000-00-00'";
            break;
        case 'valid':
            $whereClause .= " AND db.expire > '$oneMonthFromNow' AND db.expire != '0000-00-00'";
            break;
        case 'no_expire':
            $whereClause .= " AND (db.expire = '0000-00-00' OR db.expire IS NULL)";
            break;
        case 'all':
        default:
            // Tampilkan semua
            break;
    }
    
    $sql = "SELECT 
                db.kode_brng,
                db.nama_brng,
                db.expire,
                db.status,
                db.stokminimal,
                kb.nama as nama_kategori,
                gg.nama as nama_golongan,
                CASE 
                    WHEN db.expire = '0000-00-00' OR db.expire IS NULL THEN 'no_expire'
                    WHEN db.expire < '$today' THEN 'expired'
                    WHEN db.expire BETWEEN '$today' AND '$oneMonthFromNow' THEN 'expiring_soon'
                    ELSE 'valid'
                END as expire_status,
                DATEDIFF(db.expire, '$today') as days_to_expire
            FROM databarang db
            LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
            LEFT JOIN golongan_barang gg ON db.kode_golongan = gg.kode
            $whereClause
            ORDER BY 
                CASE 
                    WHEN db.expire = '0000-00-00' OR db.expire IS NULL THEN 3
                    WHEN db.expire < '$today' THEN 1
                    WHEN db.expire BETWEEN '$today' AND '$oneMonthFromNow' THEN 2
                    ELSE 4
                END,
                db.expire ASC,
                db.nama_brng ASC";
    
    return bukaquery($sql);
}

// Function untuk mendapatkan statistik expire
function getExpireStatistics() {
    $today = date('Y-m-d');
    $oneMonthFromNow = date('Y-m-d', strtotime('+1 month'));
    
    $stats = array();
    
    // Total obat
    $stats['total'] = getOne("SELECT COUNT(*) FROM databarang WHERE status = '1'");
    
    // Expired
    $stats['expired'] = getOne("SELECT COUNT(*) FROM databarang WHERE expire < '$today' AND expire != '0000-00-00' AND status = '1'");
    
    // Akan expire dalam 1 bulan
    $stats['expiring_soon'] = getOne("SELECT COUNT(*) FROM databarang WHERE expire BETWEEN '$today' AND '$oneMonthFromNow' AND expire != '0000-00-00' AND status = '1'");
    
    // Masih valid (expire > 1 bulan)
    $stats['valid'] = getOne("SELECT COUNT(*) FROM databarang WHERE expire > '$oneMonthFromNow' AND expire != '0000-00-00' AND status = '1'");
    
    // Tidak ada tanggal expire
    $stats['no_expire'] = getOne("SELECT COUNT(*) FROM databarang WHERE (expire = '0000-00-00' OR expire IS NULL) AND status = '1'");
    
    return $stats;
}

// Function untuk mendapatkan kategori obat
function getKategoriObat() {
    $sql = "SELECT DISTINCT kode, nama FROM kategori_barang ORDER BY nama";
    return bukaquery($sql);
}

// Function untuk mendapatkan golongan obat
function getGolonganObat() {
    $sql = "SELECT DISTINCT kode, nama FROM golongan_barang ORDER BY nama";
    return bukaquery($sql);
}

// Ambil parameter filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$expire_filter = isset($_GET['expire_filter']) ? $_GET['expire_filter'] : 'all';
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_golongan = isset($_GET['golongan']) ? $_GET['golongan'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '1'; // Default aktif

// Ambil data
$kategori_obat = getKategoriObat();
$golongan_obat = getGolonganObat();
$obat_data = getDataObat($search, $expire_filter, $filter_kategori, $filter_golongan, $filter_status);
$statistics = getExpireStatistics();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Obat - Sistem Informasi Kesehatan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        
        /* Expire Status Badges */
        .expire-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
            border-radius: 20px;
            font-weight: bold;
            cursor: help;
        }
        
        .expire-expired {
            background-color: #dc3545;
            color: white;
            animation: blink 2s infinite;
        }
        
        .expire-expiring-soon {
            background-color: #ffc107;
            color: #000;
            animation: pulse 2s infinite;
        }
        
        .expire-valid {
            background-color: #28a745;
            color: white;
        }
        
        .expire-no-expire {
            background-color: #6c757d;
            color: white;
        }
        
        /* Animations */
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.5; }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Alert Styles */
        .alert-expire {
            background: linear-gradient(135deg, #fff3cd 0%, #fef3bd 100%);
            border: 1px solid #ffc107;
            color: #856404;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-danger-custom {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #dc3545;
            color: #721c24;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        /* Status indicators */
        .status-active {
            color: #28a745;
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        /* Quick filter buttons */
        .quick-filter {
            margin-bottom: 15px;
        }
        
        .quick-filter .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        /* Statistics cards */
        .stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        /* Table row highlighting */
        .table-row-expired {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .table-row-expiring {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .table-row-valid {
            background-color: rgba(40, 167, 69, 0.05);
        }

        /* Export dropdown styling */
        .btn-group .dropdown-menu {
            min-width: 200px;
        }

        .btn-group .dropdown-item {
            padding: 8px 16px;
            transition: all 0.3s ease;
        }

        .btn-group .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .btn-group .dropdown-item i {
            width: 20px;
            margin-right: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-pills me-2"></i>
                            Data Obat & Monitoring Expire
                        </h4>
                        <<div>
                            <a href="obat.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-prescription-bottle-alt"></i> Data Resep
                            </a>
                            <a href="piutang.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-file-invoice-dollar"></i> Data Piutang
                            </a>
                            <a href="data_obat.php?logout" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white stat-card" onclick="filterByExpire('all')">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Total Obat</h6>
                                        <h4><?= number_format($statistics['total']) ?></h4>
                                    </div>
                                    <i class="fas fa-pills fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-danger text-white stat-card" onclick="filterByExpire('expired')">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Expired</h6>
                                        <h4><?= number_format($statistics['expired']) ?></h4>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark stat-card" onclick="filterByExpire('expiring_soon')">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Expire < 1 Bulan</h6>
                                        <h4><?= number_format($statistics['expiring_soon']) ?></h4>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-success text-white stat-card" onclick="filterByExpire('valid')">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Masih Valid</h6>
                                        <h4><?= number_format($statistics['valid']) ?></h4>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white stat-card" onclick="filterByExpire('no_expire')">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Tanpa Expire</h6>
                                        <h4><?= number_format($statistics['no_expire']) ?></h4>
                                    </div>
                                    <i class="fas fa-infinity fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="text-center">
                                    <h6>Persentase Expire</h6>
                                    <h4>
                                        <?php 
                                        $total_with_expire = $statistics['expired'] + $statistics['expiring_soon'] + $statistics['valid'];
                                        $percentage = $total_with_expire > 0 ? round(($statistics['expired'] + $statistics['expiring_soon']) / $total_with_expire * 100, 1) : 0;
                                        echo $percentage; 
                                        ?>%
                                    </h4>
                                    <small>Butuh Perhatian</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert untuk obat expired dan akan expire -->
                <?php if ($statistics['expired'] > 0): ?>
                <div class="alert alert-danger-custom" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>PERINGATAN:</strong> Terdapat <strong><?= $statistics['expired'] ?></strong> obat yang sudah expired! 
                    <a href="javascript:filterByExpire('expired')" class="alert-link">Lihat daftar obat expired</a>
                </div>
                <?php endif; ?>

                <?php if ($statistics['expiring_soon'] > 0): ?>
                <div class="alert alert-expire" role="alert">
                    <i class="fas fa-clock me-2"></i>
                    <strong>REMINDER:</strong> Terdapat <strong><?= $statistics['expiring_soon'] ?></strong> obat yang akan expire dalam 1 bulan ke depan. 
                    <a href="javascript:filterByExpire('expiring_soon')" class="alert-link">Lihat daftar obat yang akan expire</a>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status Expire</label>
                            <select name="expire_filter" class="form-select">
                                <option value="all" <?= $expire_filter == 'all' ? 'selected' : '' ?>>Semua</option>
                                <option value="expired" <?= $expire_filter == 'expired' ? 'selected' : '' ?>>Sudah Expired</option>
                                <option value="expiring_soon" <?= $expire_filter == 'expiring_soon' ? 'selected' : '' ?>>Expire < 1 Bulan</option>
                                <option value="valid" <?= $expire_filter == 'valid' ? 'selected' : '' ?>>Masih Valid</option>
                                <option value="no_expire" <?= $expire_filter == 'no_expire' ? 'selected' : '' ?>>Tanpa Expire</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Status Obat</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="1" <?= $filter_status == '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $filter_status == '0' ? 'selected' : '' ?>>Non-Aktif</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-select">
                                <option value="">Semua Kategori</option>
                                <?php 
                                mysqli_data_seek($kategori_obat, 0);
                                while($kat = mysqli_fetch_array($kategori_obat)) { 
                                ?>
                                <option value="<?= $kat['kode'] ?>" <?= $filter_kategori == $kat['kode'] ? 'selected' : '' ?>>
                                    <?= $kat['nama'] ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Golongan</label>
                            <select name="golongan" class="form-select">
                                <option value="">Semua Golongan</option>
                                <?php 
                                mysqli_data_seek($golongan_obat, 0);
                                while($gol = mysqli_fetch_array($golongan_obat)) { 
                                ?>
                                <option value="<?= $gol['kode'] ?>" <?= $filter_golongan == $gol['kode'] ? 'selected' : '' ?>>
                                    <?= $gol['nama'] ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Kode atau Nama Obat..." value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-filter"></i> Filter Data
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> Reset Filter
                                </a>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-download"></i> Export Data
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:exportData('excel')">
                                            <i class="fas fa-file-excel text-success"></i> Export Excel (.xls)
                                        </a></li>
                                        <li><a class="dropdown-item" href="javascript:exportData('csv')">
                                            <i class="fas fa-file-csv text-primary"></i> Export CSV (.csv)
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Data Obat Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="15%">Kode Obat</th>
                                        <th width="40%">Nama Obat</th>
                                        <th width="12%">Tanggal Expire</th>
                                        <th width="10%">Status Expire</th>
                                        <th width="8%">Kategori</th>
                                        <th width="8%">Golongan</th>
                                        <th width="7%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if (mysqli_num_rows($obat_data) > 0) {
                                        while ($row = mysqli_fetch_array($obat_data)) {
                                            // Tentukan class untuk row berdasarkan status expire
                                            $row_class = '';
                                            switch ($row['expire_status']) {
                                                case 'expired':
                                                    $row_class = 'table-row-expired';
                                                    break;
                                                case 'expiring_soon':
                                                    $row_class = 'table-row-expiring';
                                                    break;
                                                case 'valid':
                                                    $row_class = 'table-row-valid';
                                                    break;
                                            }
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['kode_brng']) ?></strong>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_brng']) ?></strong>
                                            <?php if ($row['nama_kategori']) { ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-tag"></i> <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </small>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($row['expire'] && $row['expire'] != '0000-00-00') { ?>
                                                <div><?= konversiTanggal($row['expire']) ?></div>
                                                <?php if ($row['expire_status'] == 'expiring_soon' || $row['expire_status'] == 'expired') { ?>
                                                <small class="text-muted">
                                                    <?= $row['days_to_expire'] > 0 ? $row['days_to_expire'] . ' hari lagi' : abs($row['days_to_expire']) . ' hari yang lalu' ?>
                                                </small>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            $badge_text = '';
                                            $badge_icon = '';
                                            
                                            switch ($row['expire_status']) {
                                                case 'expired':
                                                    $badge_class = 'expire-expired';
                                                    $badge_text = 'EXPIRED';
                                                    $badge_icon = 'fa-times-circle';
                                                    break;
                                                case 'expiring_soon':
                                                    $badge_class = 'expire-expiring-soon';
                                                    $badge_text = 'SEGERA EXPIRE';
                                                    $badge_icon = 'fa-exclamation-triangle';
                                                    break;
                                                case 'valid':
                                                    $badge_class = 'expire-valid';
                                                    $badge_text = 'VALID';
                                                    $badge_icon = 'fa-check-circle';
                                                    break;
                                                case 'no_expire':
                                                    $badge_class = 'expire-no-expire';
                                                    $badge_text = 'TANPA EXPIRE';
                                                    $badge_icon = 'fa-infinity';
                                                    break;
                                            }
                                            ?>
                                            <span class="expire-badge <?= $badge_class ?>" 
                                                title="<?= $row['expire_status'] == 'expiring_soon' ? 'Expire dalam ' . $row['days_to_expire'] . ' hari' : '' ?>">
                                                <i class="fas <?= $badge_icon ?>"></i> <?= $badge_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['nama_kategori']) { ?>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </span>
                                            <?php } else { ?>
                                            <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($row['nama_golongan']) { ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($row['nama_golongan']) ?>
                                            </span>
                                            <?php } else { ?>
                                            <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == '1') { ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Aktif
                                            </span>
                                            <?php } else { ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times"></i> Non-Aktif
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        }
                                    } else {
                                    ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-search fa-2x text-muted mb-2"></i><br>
                                            <span class="text-muted">Tidak ada data obat yang ditemukan</span>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Info Footer -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <i class="fas fa-info-circle text-primary"></i>
                                        <strong>Total Data:</strong> <?= mysqli_num_rows($obat_data) ?> obat
                                    </div>
                                    <div class="col-md-3">
                                        <i class="fas fa-clock text-warning"></i>
                                        <strong>Monitoring:</strong> Expire 1 bulan ke depan
                                    </div>
                                    <div class="col-md-3">
                                        <i class="fas fa-calendar text-info"></i>
                                        <strong>Update:</strong> <?= date('d/m/Y H:i') ?>
                                    </div>
                                    <div class="col-md-3">
                                        <i class="fas fa-shield-alt text-success"></i>
                                        <strong>Status:</strong> Sistem Aktif
                                    </div>
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
        // Filter by expire status
        function filterByExpire(status) {
            const url = new URL(window.location.href);
            url.searchParams.set('expire_filter', status);
            window.location.href = url.toString();
        }
        
        // Export data function dengan pilihan format
        function exportData(format = 'csv') {
            // Ambil parameter filter saat ini
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            // Tentukan file export berdasarkan format
            let exportFile = '';
            if (format === 'excel') {
                exportFile = 'export_obat.php';
            } else {
                exportFile = 'export_obat_simple.php';
            }
            
            // Redirect ke halaman export
            window.open(exportFile + '?' + params.toString(), '_blank');
        }
        
        // Auto refresh setiap 10 menit untuk monitoring expire
        setTimeout(function() {
            location.reload();
        }, 600000);
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    boundary: 'viewport',
                    placement: 'top',
                    trigger: 'hover focus'
                });
            });
            
            // Show expire notifications
            const expiredCount = <?= $statistics['expired'] ?>;
            const expiringSoonCount = <?= $statistics['expiring_soon'] ?>;
            
            if (expiredCount > 0) {
                console.warn('⚠️ PERINGATAN: ' + expiredCount + ' obat sudah expired!');
            }
            
            if (expiringSoonCount > 0) {
                console.log('🔔 REMINDER: ' + expiringSoonCount + ' obat akan expire dalam 1 bulan!');
            }
            
            // Highlight expired rows for attention
            const expiredRows = document.querySelectorAll('.table-row-expired');
            expiredRows.forEach(function(row) {
                row.style.borderLeft = '4px solid #dc3545';
            });
            
            const expiringRows = document.querySelectorAll('.table-row-expiring');
            expiringRows.forEach(function(row) {
                row.style.borderLeft = '4px solid #ffc107';
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + E = Show Expired
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                filterByExpire('expired');
            }
            
            // Ctrl + W = Show Expiring Soon (Warning)
            if (e.ctrlKey && e.key === 'w') {
                e.preventDefault();
                filterByExpire('expiring_soon');
            }
            
            // Ctrl + A = Show All
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                filterByExpire('all');
            }
            
            // Ctrl + V = Show Valid only
            if (e.ctrlKey && e.key === 'v') {
                e.preventDefault();
                filterByExpire('valid');
            }
        });
        
        // Search enhancement
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
        
        // Real-time expire counter for critical items
        function updateExpireCounters() {
            const expireBadges = document.querySelectorAll('.expire-expiring-soon, .expire-expired');
            expireBadges.forEach(function(badge) {
                if (badge.classList.contains('expire-expired')) {
                    // Add pulsing effect for expired items
                    badge.style.animation = 'blink 1s infinite';
                } else if (badge.classList.contains('expire-expiring-soon')) {
                    // Add warning pulse for expiring soon
                    badge.style.animation = 'pulse 2s infinite';
                }
            });
        }
        
        // Run expire counter update
        updateExpireCounters();
        
        // Periodic notification for critical items
        setInterval(function() {
            const expiredCount = <?= $statistics['expired'] ?>;
            const expiringSoonCount = <?= $statistics['expiring_soon'] ?>;
            
            if (expiredCount > 0 || expiringSoonCount > 0) {
                // Flash title for attention
                let originalTitle = document.title;
                document.title = '🚨 EXPIRE ALERT - ' + originalTitle;
                
                setTimeout(function() {
                    document.title = originalTitle;
                }, 3000);
            }
        }, 300000); // Every 5 minutes
        
        // Print specific expire report
        function printExpireReport(type) {
            let url = 'print_expire_report.php?type=' + type;
            const params = new URLSearchParams(window.location.search);
            
            // Add current filters to print
            if (params.get('kategori')) url += '&kategori=' + params.get('kategori');
            if (params.get('golongan')) url += '&golongan=' + params.get('golongan');
            if (params.get('search')) url += '&search=' + params.get('search');
            
            window.open(url, '_blank');
        }
        
        // Enhanced visual feedback
        document.querySelectorAll('.stat-card').forEach(function(card) {
            card.addEventListener('click', function() {
                // Add clicked effect
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-3px)';
                }, 150);
            });
        });
        
        // Log system info
        console.log('📊 Data Obat System Loaded');
        console.log('🔍 Monitoring: ' + <?= $statistics['total'] ?> + ' total obat');
        console.log('⚠️ Critical: ' + <?= $statistics['expired'] ?> + ' expired, ' + <?= $statistics['expiring_soon'] ?> + ' expiring soon');
        console.log('⌨️ Shortcuts: Ctrl+E (Expired), Ctrl+W (Warning), Ctrl+A (All), Ctrl+V (Valid)');
    </script>
</body>
</html>