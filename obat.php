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

error_log("obat.php - Session data: " . print_r($_SESSION, true));

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

// Function untuk mendapatkan daftar dokter aktif
function getDokterList() {
    $sql = "SELECT DISTINCT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter";
    return bukaquery($sql);
}

// Function untuk mendapatkan total data resep (untuk statistik)
function getTotalResepCount($status = null, $search = '', $tanggal_dari = '', $tanggal_sampai = '', $kategori = '', $golongan = '', $dokter = '') {
    $whereClause = "WHERE 1=1";
    
    if ($status) {
        $whereClause .= " AND ro.status = '" . validTeks($status) . "'";
    }
    
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (ro.no_resep LIKE '%$search%' OR d.nm_dokter LIKE '%$search%' OR rp.p_jawab LIKE '%$search%' OR rp.no_rkm_medis LIKE '%$search%')";
    }
    
    if ($tanggal_dari && $tanggal_sampai) {
        $tanggal_dari = validTeks($tanggal_dari);
        $tanggal_sampai = validTeks($tanggal_sampai);
        $whereClause .= " AND ro.tgl_peresepan BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
    }
    
    if ($dokter) {
        $dokter = validTeks($dokter);
        $whereClause .= " AND ro.kd_dokter = '$dokter'";
    }
    
    if ($kategori) {
        $kategori = validTeks($kategori);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd2 
            LEFT JOIN databarang db2 ON rd2.kode_brng = db2.kode_brng 
            WHERE rd2.no_resep = ro.no_resep AND db2.kode_kategori = '$kategori'
        )";
    }
    
    if ($golongan) {
        $golongan = validTeks($golongan);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd3 
            LEFT JOIN databarang db3 ON rd3.kode_brng = db3.kode_brng 
            WHERE rd3.no_resep = ro.no_resep AND db3.kode_golongan = '$golongan'
        )";
    }
    
    $sql = "SELECT COUNT(DISTINCT ro.no_resep) as total
            FROM resep_obat ro
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            LEFT JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
            LEFT JOIN kamar_inap ki ON ro.no_rawat = ki.no_rawat
            $whereClause";
    
    $result = bukaquery($sql);
    $row = mysqli_fetch_array($result);
    return $row['total'];
}

// Function untuk mendapatkan data resep dengan pagination
function getResepData($status = null, $search = '', $tanggal_dari = '', $tanggal_sampai = '', $kategori = '', $golongan = '', $dokter = '', $limit = 25, $offset = 0) {
    $whereClause = "WHERE 1=1";
    
    if ($status) {
        $whereClause .= " AND ro.status = '" . validTeks($status) . "'";
    }
    
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (ro.no_resep LIKE '%$search%' OR d.nm_dokter LIKE '%$search%' OR rp.p_jawab LIKE '%$search%' OR rp.no_rkm_medis LIKE '%$search%')";
    }
    
    if ($tanggal_dari && $tanggal_sampai) {
        $tanggal_dari = validTeks($tanggal_dari);
        $tanggal_sampai = validTeks($tanggal_sampai);
        $whereClause .= " AND ro.tgl_peresepan BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
    }
    
    if ($dokter) {
        $dokter = validTeks($dokter);
        $whereClause .= " AND ro.kd_dokter = '$dokter'";
    }
    
    if ($kategori) {
        $kategori = validTeks($kategori);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd2 
            LEFT JOIN databarang db2 ON rd2.kode_brng = db2.kode_brng 
            WHERE rd2.no_resep = ro.no_resep AND db2.kode_kategori = '$kategori'
        )";
    }
    
    if ($golongan) {
        $golongan = validTeks($golongan);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd3 
            LEFT JOIN databarang db3 ON rd3.kode_brng = db3.kode_brng 
            WHERE rd3.no_resep = ro.no_resep AND db3.kode_golongan = '$golongan'
        )";
    }
    
    $sql = "SELECT 
                ro.no_resep,
                ro.tgl_perawatan,
                ro.jam,
                ro.no_rawat,
                ro.kd_dokter,
                d.nm_dokter,
                ro.tgl_peresepan,
                ro.jam_peresepan,
                ro.status,
                ro.tgl_penyerahan,
                ro.jam_penyerahan,
                rp.no_reg,
                rp.no_rkm_medis,
                rp.p_jawab,
                CASE 
                    WHEN ro.status = 'ranap' THEN 'Rawat Inap'
                    WHEN ro.status = 'ralan' THEN 'Rawat Jalan'
                    WHEN ki.no_rawat IS NOT NULL THEN 'Rawat Inap'
                    ELSE 'Rawat Jalan'
                END as jenis_rawat
            FROM resep_obat ro
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            LEFT JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
            LEFT JOIN kamar_inap ki ON ro.no_rawat = ki.no_rawat
            $whereClause
            GROUP BY ro.no_resep
            ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC
            LIMIT $limit OFFSET $offset";
    
    return bukaquery($sql);
}

// Function untuk mendapatkan statistik detail
function getDetailStatistics($status = null, $search = '', $tanggal_dari = '', $tanggal_sampai = '', $kategori = '', $golongan = '', $dokter = '') {
    $whereClause = "WHERE 1=1";
    
    if ($status) {
        $whereClause .= " AND ro.status = '" . validTeks($status) . "'";
    }
    
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (ro.no_resep LIKE '%$search%' OR d.nm_dokter LIKE '%$search%' OR rp.p_jawab LIKE '%$search%' OR rp.no_rkm_medis LIKE '%$search%')";
    }
    
    if ($tanggal_dari && $tanggal_sampai) {
        $tanggal_dari = validTeks($tanggal_dari);
        $tanggal_sampai = validTeks($tanggal_sampai);
        $whereClause .= " AND ro.tgl_peresepan BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
    }
    
    if ($dokter) {
        $dokter = validTeks($dokter);
        $whereClause .= " AND ro.kd_dokter = '$dokter'";
    }
    
    if ($kategori) {
        $kategori = validTeks($kategori);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd2 
            LEFT JOIN databarang db2 ON rd2.kode_brng = db2.kode_brng 
            WHERE rd2.no_resep = ro.no_resep AND db2.kode_kategori = '$kategori'
        )";
    }
    
    if ($golongan) {
        $golongan = validTeks($golongan);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM resep_dokter rd3 
            LEFT JOIN databarang db3 ON rd3.kode_brng = db3.kode_brng 
            WHERE rd3.no_resep = ro.no_resep AND db3.kode_golongan = '$golongan'
        )";
    }
    
    $sql = "SELECT 
                COUNT(DISTINCT ro.no_resep) as total_resep,
                COUNT(DISTINCT CASE 
                    WHEN ro.status = 'ralan' OR (ro.status IS NULL AND ki.no_rawat IS NULL) OR (ro.status NOT IN ('ralan','ranap') AND ki.no_rawat IS NULL)
                    THEN ro.no_resep 
                    ELSE NULL 
                END) as total_ralan,
                COUNT(DISTINCT CASE 
                    WHEN ro.status = 'ranap' OR (ro.status IS NULL AND ki.no_rawat IS NOT NULL) OR (ro.status NOT IN ('ralan','ranap') AND ki.no_rawat IS NOT NULL)
                    THEN ro.no_resep 
                    ELSE NULL 
                END) as total_ranap,
                COUNT(DISTINCT CASE 
                    WHEN ro.tgl_penyerahan != '0000-00-00' AND ro.tgl_penyerahan IS NOT NULL AND ro.tgl_penyerahan != ''
                    THEN ro.no_resep 
                    ELSE NULL 
                END) as total_diserahkan,
                COUNT(DISTINCT CASE 
                    WHEN ro.tgl_penyerahan = '0000-00-00' OR ro.tgl_penyerahan IS NULL OR ro.tgl_penyerahan = ''
                    THEN ro.no_resep 
                    ELSE NULL 
                END) as total_menunggu
            FROM resep_obat ro
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            LEFT JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
            LEFT JOIN kamar_inap ki ON ro.no_rawat = ki.no_rawat
            $whereClause";
    
    $result = bukaquery($sql);
    return mysqli_fetch_array($result);
}

// Function untuk mendapatkan daftar kategori obat
function getKategoriObat() {
    $sql = "SELECT DISTINCT kode, nama FROM kategori_barang ORDER BY nama";
    return bukaquery($sql);
}

// Function untuk mendapatkan daftar golongan obat
function getGolonganObat() {
    $sql = "SELECT DISTINCT kode, nama FROM golongan_barang ORDER BY nama";
    return bukaquery($sql);
}

// Function untuk mendapatkan detail obat dari resep
function getDetailObat($no_resep) {
    $sql = "SELECT 
                rd.kode_brng,
                db.nama_brng,
                rd.jml,
                rd.aturan_pakai,
                db.kode_kategori,
                kb.nama as nama_kategori,
                db.kode_golongan,
                gg.nama as nama_golongan,
                db.ralan as harga,
                db.expire
            FROM resep_dokter rd
            LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
            LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
            LEFT JOIN golongan_barang gg ON db.kode_golongan = gg.kode
            WHERE rd.no_resep = '" . validTeks($no_resep) . "'
            ORDER BY db.nama_brng";
    
    return bukaquery($sql);
}

// Function untuk menghitung total nilai resep
function getTotalResep($no_resep) {
    $sql = "SELECT SUM(rd.jml * db.ralan) as total
            FROM resep_dokter rd
            LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
            WHERE rd.no_resep = '" . validTeks($no_resep) . "'";
    
    $result = bukaquery($sql);
    $row = mysqli_fetch_array($result);
    return $row['total'] ? $row['total'] : 0;
}

// ===== PAGINATION SETUP =====
$today = date('Y-m-d');

// Ambil parameter filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tanggal_dari = isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] != '' ? $_GET['tanggal_dari'] : $today;
$tanggal_sampai = isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != '' ? $_GET['tanggal_sampai'] : $today;
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_golongan = isset($_GET['golongan']) ? $_GET['golongan'] : '';
$filter_dokter = isset($_GET['dokter']) ? $_GET['dokter'] : '';

// Pagination parameters
$items_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Validasi items per page
$allowed_per_page = [10, 25, 50, 100, 250];
if (!in_array($items_per_page, $allowed_per_page)) {
    $items_per_page = 25;
}

// Validasi current page
if ($current_page < 1) {
    $current_page = 1;
}

// Check jika ada parameter "show_all" untuk menampilkan semua data
$show_all = isset($_GET['show_all']) ? true : false;
if ($show_all) {
    $tanggal_dari = '';
    $tanggal_sampai = '';
}

// Hitung offset untuk query
$offset = ($current_page - 1) * $items_per_page;

// Ambil total data untuk statistik dan pagination
$total_records = getTotalResepCount($filter_status, $search, $tanggal_dari, $tanggal_sampai, $filter_kategori, $filter_golongan, $filter_dokter);
$total_pages = ceil($total_records / $items_per_page);

// Validasi halaman tidak melebihi total
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// Ambil data untuk dropdown
$kategori_obat = getKategoriObat();
$golongan_obat = getGolonganObat();
$dokter_list = getDokterList();

// Ambil data resep dengan pagination
$resep_data = getResepData($filter_status, $search, $tanggal_dari, $tanggal_sampai, $filter_kategori, $filter_golongan, $filter_dokter, $items_per_page, $offset);

// Ambil statistik lengkap (semua data, bukan hanya halaman ini)
$statistics = getDetailStatistics($filter_status, $search, $tanggal_dari, $tanggal_sampai, $filter_kategori, $filter_golongan, $filter_dokter);

// Function untuk membuat URL pagination
function buildPaginationUrl($page, $per_page = null) {
    $params = $_GET;
    $params['page'] = $page;
    if ($per_page !== null) {
        $params['per_page'] = $per_page;
    }
    return '?' . http_build_query($params);
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Resep Obat - Sistem Informasi Kesehatan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .badge-ralan { background-color: #28a745; }
        .badge-ranap { background-color: #dc3545; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .resep-detail { background-color: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .obat-item { border-left: 3px solid #007bff; padding-left: 10px; margin-bottom: 8px; }
        .filter-section { background-color: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .jenis-rawat-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
            border-radius: 20px;
            font-weight: bold;
            cursor: help;
            transition: all 0.3s ease;
        }
        .jenis-rawat-badge:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .jenis-rawat-badge.rawat-jalan {
            background-color: #28a745;
            color: white;
        }
        .jenis-rawat-badge.rawat-inap {
            background-color: #dc3545;
            color: white;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-pending {
            background-color: #ffc107;
        }
        .status-completed {
            background-color: #28a745;
        }
        
        /* Custom tooltip styling */
        .tooltip-inner {
            max-width: 300px;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-line;
            text-align: left;
        }
        
        .jenis-rawat-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* Style untuk info tanggal */
        .date-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        .date-info .date-icon {
            color: #1976d2;
            font-size: 1.2em;
        }
        
        .quick-date-buttons {
            margin-top: 10px;
        }
        
        .quick-date-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .alert-daily {
            background: linear-gradient(135deg, #fff3cd 0%, #fef3bd 100%);
            border: 1px solid #ffc107;
            color: #856404;
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

        /* Pagination styling */
        .pagination-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-info {
            color: #6c757d;
            font-size: 0.9em;
        }

        .pagination .page-link {
            color: #667eea;
        }

        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }

        .pagination .page-link:hover {
            color: #764ba2;
        }

        .pagination-size-sm .page-link {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .table-pagination-info {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
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
                            <i class="fas fa-prescription-bottle-alt me-2"></i>
                            Data Resep Obat - Rawat Jalan & Rawat Inap
                        </h4>
                        <div>
                            <a href="data_obat.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-pills"></i> Data Obat
                            </a>
                            <a href="piutang.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-file-invoice-dollar"></i> Data Piutang
                            </a>
                            <a href="obat.php?logout" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Info Tanggal yang Ditampilkan -->
                <div class="date-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-calendar-day date-icon me-2"></i>
                            <strong>
                                <?php if ($show_all): ?>
                                    Menampilkan Semua Data Resep
                                <?php elseif ($tanggal_dari == $tanggal_sampai): ?>
                                    Data Resep Hari Ini: <?= konversiTanggal($tanggal_dari) ?>
                                <?php else: ?>
                                    Data Resep: <?= konversiTanggal($tanggal_dari) ?> s/d <?= konversiTanggal($tanggal_sampai) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="quick-date-buttons">
                            <?php if ($show_all || ($tanggal_dari != $today || $tanggal_sampai != $today)): ?>
                            <a href="obat.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-calendar-day"></i> Hari Ini
                            </a>
                            <?php endif; ?>
                            
                            <a href="obat.php?tanggal_dari=<?= date('Y-m-d', strtotime('-1 days')) ?>&tanggal_sampai=<?= date('Y-m-d', strtotime('-1 days')) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar-minus"></i> Kemarin
                            </a>
                            
                            <a href="obat.php?tanggal_dari=<?= date('Y-m-d', strtotime('monday this week')) ?>&tanggal_sampai=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-calendar-week"></i> Minggu Ini
                            </a>
                            
                            <a href="obat.php?tanggal_dari=<?= date('Y-m-01') ?>&tanggal_sampai=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-calendar-alt"></i> Bulan Ini
                            </a>
                            
                            <?php if (!$show_all): ?>
                            <a href="obat.php?show_all=1" class="btn btn-sm btn-warning">
                                <i class="fas fa-list"></i> Semua Data
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Alert untuk mode tampilan harian -->
                <?php if (!$show_all && $tanggal_dari == $today && $tanggal_sampai == $today): ?>
                <div class="alert alert-daily" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Mode Tampilan Harian:</strong> Sistem menampilkan data resep hari ini secara default. 
                    Gunakan filter tanggal atau tombol di atas untuk melihat data periode lain.
                </div>
                <?php endif; ?>

                <!-- Debug Section -->
                <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-bug"></i> Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Parameter Filter:</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Status:</strong> <?= $filter_status ?: 'Semua' ?></li>
                                    <li><strong>Dokter:</strong> <?= $filter_dokter ?: 'Semua' ?></li>
                                    <li><strong>Kategori:</strong> <?= $filter_kategori ?: 'Semua' ?></li>
                                    <li><strong>Golongan:</strong> <?= $filter_golongan ?: 'Semua' ?></li>
                                    <li><strong>Tanggal:</strong> <?= $tanggal_dari ?> s/d <?= $tanggal_sampai ?></li>
                                    <li><strong>Search:</strong> <?= $search ?: 'Kosong' ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Hasil Query:</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Total Records:</strong> <?= $total_records ?></li>
                                    <li><strong>Total Pages:</strong> <?= $total_pages ?></li>
                                    <li><strong>Current Page:</strong> <?= $current_page ?></li>
                                    <li><strong>Items Per Page:</strong> <?= $items_per_page ?></li>
                                    <li><strong>Offset:</strong> <?= $offset ?></li>
                                    <li><strong>Rows Returned:</strong> <?= mysqli_num_rows($resep_data) ?></li>
                                </ul>
                            </div>
                        </div>

                        <?php
                        // Test query sederhana tanpa filter
                        $test_sql = "SELECT COUNT(*) as total FROM resep_obat WHERE tgl_peresepan = '$today'";
                        $test_result = bukaquery($test_sql);
                        $test_row = mysqli_fetch_array($test_result);
                        ?>
                        
                        <div class="alert alert-info">
                            <strong>Test Query:</strong> Total resep hari ini tanpa filter: <?= $test_row['total'] ?>
                        </div>
                        
                        <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>?<?= http_build_query(array_merge($_GET, ['debug' => '0'])) ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-eye-slash"></i> Sembunyikan Debug
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Jika data tidak tampil atau statistik tidak sesuai, klik 
                    <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>?<?= http_build_query(array_merge($_GET, ['debug' => '1'])) ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-bug"></i> Debug Mode
                    </a> 
                    untuk melihat detail data.
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status Rawat</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="ralan" <?= $filter_status == 'ralan' ? 'selected' : '' ?>>Rawat Jalan</option>
                                <option value="ranap" <?= $filter_status == 'ranap' ? 'selected' : '' ?>>Rawat Inap</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Dokter</label>
                            <select name="dokter" class="form-select">
                                <option value="">Semua Dokter</option>
                                <?php 
                                mysqli_data_seek($dokter_list, 0);
                                while($dok = mysqli_fetch_array($dokter_list)) { 
                                ?>
                                <option value="<?= $dok['kd_dokter'] ?>" <?= $filter_dokter == $dok['kd_dokter'] ? 'selected' : '' ?>>
                                    <?= $dok['nm_dokter'] ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Kategori Obat</label>
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
                            <label class="form-label">Golongan Obat</label>
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
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Dari</label>
                            <input type="date" name="tanggal_dari" class="form-control" value="<?= $tanggal_dari ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tanggal Sampai</label>
                            <input type="date" name="tanggal_sampai" class="form-control" value="<?= $tanggal_sampai ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="No Resep, Dokter, Pasien, No RM..." value="<?= $search ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Pencarian: No Resep, Nama Dokter, Nama Pasien, No Rekam Medis
                            </small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-refresh"></i> Reset
                                </a>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:exportData('excel')">
                                            <i class="fas fa-file-excel text-success"></i> Excel
                                        </a></li>
                                        <li><a class="dropdown-item" href="javascript:exportData('csv')">
                                            <i class="fas fa-file-csv text-primary"></i> CSV
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Pagination Info -->
                <div class="pagination-info">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="pagination-controls">
                            <div class="per-page-selector">
                                <label class="form-label mb-0">Tampilkan:</label>
                                <select class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                    <?php foreach ($allowed_per_page as $value): ?>
                                    <option value="<?= $value ?>" <?= $items_per_page == $value ? 'selected' : '' ?>>
                                        <?= $value ?> data
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="page-info">per halaman</span>
                            </div>
                            
                            <div class="page-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Menampilkan 
                                <strong><?= number_format(min($offset + 1, $total_records)) ?></strong> 
                                sampai 
                                <strong><?= number_format(min($offset + $items_per_page, $total_records)) ?></strong> 
                                dari 
                                <strong><?= number_format($total_records) ?></strong> 
                                total data
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($total_records > 0): ?>
                            <div class="page-info">
                                Halaman <strong><?= $current_page ?></strong> dari <strong><?= $total_pages ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Quick Jump -->
                            <?php if ($total_pages > 1): ?>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0 small">Ke halaman:</label>
                                <input type="number" class="form-control form-control-sm" style="width: 80px;" 
                                       min="1" max="<?= $total_pages ?>" value="<?= $current_page ?>"
                                       onchange="jumpToPage(this.value)">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Data Resep -->
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="12%">No. Resep</th>
                                        <th width="10%">Tanggal</th>
                                        <th width="15%">Dokter</th>
                                        <th width="12%">Pasien</th>
                                        <th width="8%">Jenis Rawat</th>
                                        <th width="25%">Detail Obat</th>
                                        <th width="8%">Total</th>
                                        <th width="5%">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = $offset + 1;
                                    if (mysqli_num_rows($resep_data) > 0) {
                                        while ($row = mysqli_fetch_array($resep_data)) {
                                            $detail_obat = getDetailObat($row['no_resep']);
                                            $total_resep = getTotalResep($row['no_resep']);
                                            
                                            // Tentukan class badge dan icon berdasarkan jenis rawat yang benar
                                            $badge_class = ($row['jenis_rawat'] == 'Rawat Inap') ? 'rawat-inap' : 'rawat-jalan';
                                            $icon_class = ($row['jenis_rawat'] == 'Rawat Inap') ? 'fa-bed' : 'fa-user-md';
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= $row['no_resep'] ?></strong><br>
                                            <small class="text-muted">
                                                <?= konversiTanggal($row['tgl_peresepan']) ?><br>
                                                <?= $row['jam_peresepan'] ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div>Perawatan:</div>
                                            <small><?= konversiTanggal($row['tgl_perawatan']) ?></small><br>
                                            <?php if ($row['tgl_penyerahan'] != '0000-00-00') { ?>
                                            <div class="mt-1">Diserahkan:</div>
                                            <small class="text-success"><?= konversiTanggal($row['tgl_penyerahan']) ?></small>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <strong><?= $row['nm_dokter'] ?></strong><br>
                                            <small class="text-muted"><?= $row['kd_dokter'] ?></small>
                                        </td>
                                        <td>
                                            <div><?= $row['p_jawab'] ?></div>
                                            <small class="text-muted">
                                                RM: <?= $row['no_rkm_medis'] ?><br>
                                                Reg: <?= $row['no_reg'] ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="jenis-rawat-badge <?= $badge_class ?>" 
                                                title="No Rawat: <?= $row['no_rawat'] ?>">
                                                <i class="fas <?= $icon_class ?>"></i>
                                                <?= $row['jenis_rawat'] ?>  
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $obat_count = 0;
                                            while ($obat = mysqli_fetch_array($detail_obat)) { 
                                                $obat_count++;
                                                $badge_class_obat = 'bg-primary';
                                                $icon_class_obat = 'fa-pills';
                                                
                                                // Tentukan warna badge berdasarkan nama golongan
                                                if (stripos($obat['nama_golongan'], 'bebas') !== false) {
                                                    $badge_class_obat = 'bg-success';
                                                    $icon_class_obat = 'fa-capsules';
                                                } elseif (stripos($obat['nama_golongan'], 'keras') !== false) {
                                                    $badge_class_obat = 'bg-danger';
                                                    $icon_class_obat = 'fa-exclamation-triangle';
                                                } elseif (stripos($obat['nama_golongan'], 'narkotika') !== false) {
                                                    $badge_class_obat = 'bg-dark';
                                                    $icon_class_obat = 'fa-ban';
                                                } elseif (stripos($obat['nama_golongan'], 'psikotropika') !== false) {
                                                    $badge_class_obat = 'bg-warning';
                                                    $icon_class_obat = 'fa-brain';
                                                }
                                            ?>
                                            <div class="obat-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div style="flex: 1;">
                                                        <strong><?= $obat['nama_brng'] ?></strong>
                                                        <?php if ($obat['nama_golongan']) { ?>
                                                        <span class="badge <?= $badge_class_obat ?> ms-1">
                                                            <i class="fas <?= $icon_class_obat ?>"></i> <?= $obat['nama_golongan'] ?>
                                                        </span>
                                                        <?php } ?>
                                                        <?php if ($obat['nama_kategori']) { ?>
                                                        <span class="badge bg-info ms-1">
                                                            <i class="fas fa-tag"></i> <?= $obat['nama_kategori'] ?>
                                                        </span>
                                                        <?php } ?>
                                                    </div>
                                                    <small class="text-end">
                                                        <div>Qty: <?= formatDec($obat['jml']) ?></div>
                                                        <div><?= formatDuit($obat['harga']) ?></div>
                                                    </small>
                                                </div>
                                                <?php if ($obat['aturan_pakai']) { ?>
                                                <small class="text-info">
                                                    <i class="fas fa-pills"></i> <?= $obat['aturan_pakai'] ?>
                                                </small>
                                                <?php } ?>
                                            </div>
                                            <?php } ?>
                                            
                                            <?php if ($obat_count == 0) { ?>
                                            <span class="text-muted"><i>Tidak ada detail obat</i></span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success"><?= formatDuit($total_resep) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($row['tgl_penyerahan'] != '0000-00-00') { ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Diserahkan
                                            </span>
                                            <?php } else { ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock"></i> Menunggu
                                            </span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        }
                                    } else {
                                    ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-search fa-2x text-muted mb-2"></i><br>
                                            <span class="text-muted">
                                                <?php if (!$show_all && $tanggal_dari == $today): ?>
                                                    Tidak ada resep obat hari ini
                                                <?php else: ?>
                                                    Tidak ada data resep yang ditemukan
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Table Footer with Pagination Info -->
                        <?php if ($total_records > 0): ?>
                        <div class="table-pagination-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-list me-1"></i>
                                    Menampilkan <?= mysqli_num_rows($resep_data) ?> dari <?= number_format($total_records) ?> total resep
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Terakhir diperbarui: <?= date('d/m/Y H:i:s') ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-lg">
                            <!-- First Page -->
                            <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl(1) ?>" title="Halaman Pertama">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Previous Page -->
                            <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($current_page - 1) ?>" title="Halaman Sebelumnya">
                                    <i class="fas fa-angle-left"></i> Sebelumnya
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            // Ensure we show at least 5 pages if available
                            if ($end_page - $start_page < 4) {
                                if ($start_page == 1) {
                                    $end_page = min($total_pages, $start_page + 4);
                                } else {
                                    $start_page = max(1, $end_page - 4);
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($current_page + 1) ?>" title="Halaman Selanjutnya">
                                    Selanjutnya <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Last Page -->
                            <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($total_pages) ?>" title="Halaman Terakhir">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Total Resep</h6>
                                        <h4><?= number_format($statistics['total_resep']) ?></h4>
                                    </div>
                                    <i class="fas fa-prescription-bottle-alt fa-2x opacity-75"></i>
                                </div>
                                <small class="opacity-75">
                                    <i class="fas fa-info-circle me-1"></i>Semua data periode
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Rawat Jalan</h6>
                                        <h4><?= number_format($statistics['total_ralan']) ?></h4>
                                    </div>
                                    <i class="fas fa-user-md fa-2x opacity-75"></i>
                                </div>
                                <small class="opacity-75">
                                    <?= $statistics['total_resep'] > 0 ? round(($statistics['total_ralan'] / $statistics['total_resep']) * 100, 1) : 0 ?>% dari total
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Rawat Inap</h6>
                                        <h4><?= number_format($statistics['total_ranap']) ?></h4>
                                    </div>
                                    <i class="fas fa-bed fa-2x opacity-75"></i>
                                </div>
                                <small class="opacity-75">
                                    <?= $statistics['total_resep'] > 0 ? round(($statistics['total_ranap'] / $statistics['total_resep']) * 100, 1) : 0 ?>% dari total
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Diserahkan</h6>
                                        <h4><?= number_format($statistics['total_diserahkan']) ?></h4>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                                <small class="opacity-75">
                                    <?= $statistics['total_resep'] > 0 ? round(($statistics['total_diserahkan'] / $statistics['total_resep']) * 100, 1) : 0 ?>% dari total
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Menunggu</h6>
                                        <h4><?= number_format($statistics['total_menunggu']) ?></h4>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                                <small class="opacity-75">
                                    <?= $statistics['total_resep'] > 0 ? round(($statistics['total_menunggu'] / $statistics['total_resep']) * 100, 1) : 0 ?>% dari total
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Status Info -->
                    <?php if ($filter_kategori || $filter_golongan || $filter_dokter): ?>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="text-center">
                                    <h6>Filter Aktif</h6>
                                    <?php if ($filter_dokter): 
                                        $nama_dokter = getOne("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$filter_dokter'");
                                    ?>
                                    <small><i class="fas fa-user-md"></i> <?= $nama_dokter ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($filter_kategori): 
                                        $nama_kat = getOne("SELECT nama FROM kategori_barang WHERE kode = '$filter_kategori'");
                                    ?>
                                    <small><i class="fas fa-tag"></i> <?= $nama_kat ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($filter_golongan): 
                                        $nama_gol = getOne("SELECT nama FROM golongan_barang WHERE kode = '$filter_golongan'");
                                    ?>
                                    <small><i class="fas fa-pills"></i> <?= $nama_gol ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh setiap 5 menit (hanya jika di halaman pertama)
        setTimeout(function() {
            if (<?= $current_page ?> === 1) {
                location.reload();
            }
        }, 300000);
        
        // Print function
        function printResep(no_resep) {
            window.open('print_resep.php?no_resep=' + no_resep, '_blank');
        }
        
        // Export data function dengan pilihan format
        function exportData(format = 'csv') {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            params.delete('page');
            params.delete('per_page');
            
            let exportFile = '';
            if (format === 'excel') {
                exportFile = 'export_resep.php';
            } else {
                exportFile = 'export_resep_simple.php';
            }
            
            window.open(exportFile + '?' + params.toString(), '_blank');
        }
        
        // Change items per page
        function changePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }
        
        // Jump to specific page
        function jumpToPage(page) {
            const totalPages = <?= $total_pages ?>;
            page = parseInt(page);
            
            if (page >= 1 && page <= totalPages) {
                const url = new URL(window.location.href);
                url.searchParams.set('page', page);
                window.location.href = url.toString();
            } else {
                alert('Halaman tidak valid. Masukkan nomor halaman antara 1 dan ' + totalPages);
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    boundary: 'viewport',
                    placement: 'top',
                    trigger: 'hover focus'
                });
            });
            
            console.log('Pagination info:', {
                currentPage: <?= $current_page ?>,
                totalPages: <?= $total_pages ?>,
                itemsPerPage: <?= $items_per_page ?>,
                totalRecords: <?= $total_records ?>,
                offset: <?= $offset ?>
            });
        });
        
        // Keyboard shortcuts untuk navigasi cepat
        document.addEventListener('keydown', function(e) {
            // Ctrl + T = Today
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                window.location.href = 'obat.php';
            }
            
            // Ctrl + Y = Yesterday  
            if (e.ctrlKey && e.key === 'y') {
                e.preventDefault();
                const yesterday = new Date();
                yesterday.setDate(yesterday.getDate() - 1);
                const dateStr = yesterday.toISOString().split('T')[0];
                window.location.href = `obat.php?tanggal_dari=${dateStr}&tanggal_sampai=${dateStr}`;
            }
            
            // Ctrl + W = This Week
            if (e.ctrlKey && e.key === 'w') {
                e.preventDefault();
                const today = new Date();
                const monday = new Date(today.setDate(today.getDate() - today.getDay() + 1));
                const now = new Date();
                const mondayStr = monday.toISOString().split('T')[0];
                const todayStr = now.toISOString().split('T')[0];
                window.location.href = `obat.php?tanggal_dari=${mondayStr}&tanggal_sampai=${todayStr}`;
            }
            
            // Ctrl + M = This Month
            if (e.ctrlKey && e.key === 'm') {
                e.preventDefault();
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const firstDayStr = firstDay.toISOString().split('T')[0];
                const todayStr = today.toISOString().split('T')[0];
                window.location.href = `obat.php?tanggal_dari=${firstDayStr}&tanggal_sampai=${todayStr}`;
            }
            
            // Arrow keys for pagination
            if (e.altKey && e.key === 'ArrowLeft' && <?= $current_page ?> > 1) {
                e.preventDefault();
                window.location.href = '<?= buildPaginationUrl($current_page - 1) ?>';
            }
            
            if (e.altKey && e.key === 'ArrowRight' && <?= $current_page ?> < <?= $total_pages ?>) {
                e.preventDefault();
                window.location.href = '<?= buildPaginationUrl($current_page + 1) ?>';
            }
        });
        
        // Auto-refresh dengan konfirmasi jika ada perubahan data
        let initialDataCount = <?= $total_records ?>;
        
        function smartRefresh() {
            const hasFilters = window.location.search.includes('status=') || 
                              window.location.search.includes('search=') || 
                              window.location.search.includes('kategori=') || 
                              window.location.search.includes('golongan=') ||
                              window.location.search.includes('dokter=');
            
            if (!hasFilters && <?= $current_page ?> === 1) {
                location.reload();
            } else {
                console.log('Auto-refresh paused due to active filters or not on first page. Press F5 to refresh manually.');
            }
        }
        
        // Update refresh timer
        setTimeout(smartRefresh, 300000); // 5 minutes
        
        // Show loading state when navigating
        function showLoading() {
            const buttons = document.querySelectorAll('a, button');
            buttons.forEach(button => {
                if (button.href && button.href.includes('obat.php')) {
                    button.addEventListener('click', function() {
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        button.disabled = true;
                    });
                }
            });
        }
        
        // Initialize loading states
        showLoading();
        
        // Enhanced pagination with keyboard support
        document.addEventListener('keydown', function(e) {
            // Page navigation with Ctrl + Number
            if (e.ctrlKey && e.key >= '1' && e.key <= '9') {
                e.preventDefault();
                const targetPage = parseInt(e.key);
                if (targetPage <= <?= $total_pages ?>) {
                    jumpToPage(targetPage);
                }
            }
            
            // Home and End keys for first/last page
            if (e.ctrlKey && e.key === 'Home') {
                e.preventDefault();
                jumpToPage(1);
            }
            
            if (e.ctrlKey && e.key === 'End') {
                e.preventDefault();
                jumpToPage(<?= $total_pages ?>);
            }
        });
        
        // Smooth scroll to top when changing pages
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Add click handlers to pagination links
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', function() {
                setTimeout(scrollToTop, 100);
            });
        });
        
        // Quick pagination info display
        function displayPaginationShortcuts() {
            console.log('=== PAGINATION SHORTCUTS ===');
            console.log('Ctrl + T: Hari Ini');
            console.log('Ctrl + Y: Kemarin');
            console.log('Ctrl + W: Minggu Ini');
            console.log('Ctrl + M: Bulan Ini');
            console.log('Alt + ←: Halaman Sebelumnya');
            console.log('Alt + →: Halaman Selanjutnya');
            console.log('Ctrl + 1-9: Langsung ke halaman 1-9');
            console.log('Ctrl + Home: Halaman Pertama');
            console.log('Ctrl + End: Halaman Terakhir');
            console.log('=============================');
        }
        
        // Display shortcuts on load
        displayPaginationShortcuts();
    </script>
</body>
</html>