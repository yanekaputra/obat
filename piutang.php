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

// Function untuk mendapatkan data piutang
// Function untuk mendapatkan data piutang
function getPiutangData($search = '', $tanggal_dari = '', $tanggal_sampai = '', $jns_jual = '', $penjab = '') {
    $whereClause = "WHERE 1=1";
    
    // Filter pencarian
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (p.nota_piutang LIKE '%$search%' OR p.no_rkm_medis LIKE '%$search%' OR p.nm_pasien LIKE '%$search%')";
    }
    
    // Filter tanggal
    if ($tanggal_dari && $tanggal_sampai) {
        $tanggal_dari = validTeks($tanggal_dari);
        $tanggal_sampai = validTeks($tanggal_sampai);
        $whereClause .= " AND p.tgl_piutang BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
    }
    
    // Filter jenis jual
    if ($jns_jual) {
        $jns_jual = validTeks($jns_jual);
        $whereClause .= " AND p.jns_jual = '$jns_jual'";
    }
    
    // Filter penanggung jawab berdasarkan no_rkm_medis
    if ($penjab) {
        $penjab = validTeks($penjab);
        $whereClause .= " AND EXISTS (
            SELECT 1 FROM reg_periksa rp 
            LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj 
            WHERE rp.no_rkm_medis = p.no_rkm_medis AND pj.png_jawab = '$penjab'
        )";
    }
    
    $sql = "SELECT 
                p.nota_piutang,
                p.tgl_piutang,
                p.no_rkm_medis,
                p.nm_pasien,
                p.catatan,
                p.jns_jual,
                p.sisapiutang,
                p.status,
                p.tgltempo,
                p.uangmuka,
                p.ongkir,
                -- Ambil penanggung jawab dari reg_periksa terbaru untuk pasien ini
                (SELECT pj.png_jawab 
                 FROM reg_periksa rp 
                 LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj 
                 WHERE rp.no_rkm_medis = p.no_rkm_medis 
                 ORDER BY rp.tgl_registrasi DESC 
                 LIMIT 1) as png_jawab,
                CASE 
                    WHEN p.tgltempo < CURDATE() THEN 'LEWAT TEMPO'
                    WHEN p.tgltempo = CURDATE() THEN 'JATUH TEMPO HARI INI'
                    ELSE 'BELUM TEMPO'
                END as status_tempo,
                DATEDIFF(p.tgltempo, CURDATE()) as hari_tempo
            FROM piutang p
            $whereClause
            ORDER BY p.tgl_piutang DESC, p.nota_piutang DESC";
    
    return bukaquery($sql);
}

// Function untuk mendapatkan detail piutang (obat-obat)
function getDetailPiutang($nota_piutang) {
    $sql = "SELECT 
                dp.nota_piutang,
                dp.kode_brng,
                db.nama_brng,
                dp.jumlah,
                dp.h_jual,
                dp.subtotal,
                dp.dis,
                dp.bsr_dis,
                dp.total,
                dp.aturan_pakai,
                dp.no_batch,
                dp.no_faktur
            FROM detailpiutang dp
            LEFT JOIN databarang db ON dp.kode_brng = db.kode_brng
            WHERE dp.nota_piutang = '" . validTeks($nota_piutang) . "'
            ORDER BY db.nama_brng";
    
    return bukaquery($sql);
}

// Function untuk mendapatkan total piutang
function getTotalPiutang($nota_piutang) {
    $sql = "SELECT SUM(dp.total) as total_piutang
            FROM detailpiutang dp
            WHERE dp.nota_piutang = '" . validTeks($nota_piutang) . "'";
    
    $result = bukaquery($sql);
    $row = mysqli_fetch_array($result);
    return $row['total_piutang'] ? $row['total_piutang'] : 0;
}

// Function untuk mendapatkan statistik piutang
function getPiutangStatistics() {
    $today = date('Y-m-d');
    
    $stats = array();
    
    // Total piutang
    $stats['total'] = getOne("SELECT COUNT(*) FROM piutang");
    
    // Total nilai piutang
    $stats['total_nilai'] = getOne("SELECT SUM(sisapiutang) FROM piutang") ?: 0;
    
    // Piutang lewat tempo
    $stats['lewat_tempo'] = getOne("SELECT COUNT(*) FROM piutang WHERE tgltempo < '$today'");
    
    // Piutang jatuh tempo hari ini
    $stats['tempo_hari_ini'] = getOne("SELECT COUNT(*) FROM piutang WHERE tgltempo = '$today'");
    
    // Piutang belum tempo
    $stats['belum_tempo'] = getOne("SELECT COUNT(*) FROM piutang WHERE tgltempo > '$today'");
    
    // Nilai piutang lewat tempo
    $stats['nilai_lewat_tempo'] = getOne("SELECT SUM(sisapiutang) FROM piutang WHERE tgltempo < '$today'") ?: 0;
    
    return $stats;
}

// Function untuk mendapatkan penanggung jawab dari tabel penjab
function getPenanggungJawab() {
    $sql = "SELECT DISTINCT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY png_jawab";
    return bukaquery($sql);
}

// Function untuk mendapatkan jenis jual unik
function getJenisJual() {
    $sql = "SELECT DISTINCT jns_jual FROM piutang WHERE jns_jual IS NOT NULL ORDER BY jns_jual";
    return bukaquery($sql);
}

// Set default tanggal ke 3 bulan kebelakang sampai hari ini
$today = date('Y-m-d');
$three_months_ago = date('Y-m-d', strtotime('-3 months'));

// Ambil parameter filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tanggal_dari = isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] != '' ? $_GET['tanggal_dari'] : $three_months_ago;
$tanggal_sampai = isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != '' ? $_GET['tanggal_sampai'] : $today;
$filter_jns_jual = isset($_GET['jns_jual']) ? $_GET['jns_jual'] : '';
$filter_penjab = isset($_GET['penjab']) ? $_GET['penjab'] : ''; // ✨ GANTI dari filter_status

// Check jika ada parameter "show_all" untuk menampilkan semua data
$show_all = isset($_GET['show_all']) ? true : false;
if ($show_all) {
    $tanggal_dari = '';
    $tanggal_sampai = '';
}

// Ambil data
$jenis_jual = getJenisJual();
$penanggung_jawab = getPenanggungJawab(); // ✨ TAMBAHKAN INI
$piutang_data = getPiutangData($search, $tanggal_dari, $tanggal_sampai, $filter_jns_jual, $filter_penjab);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Piutang - Sistem Informasi Kesehatan</title>
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
        
        .piutang-item {
            border-left: 3px solid #007bff;
            padding-left: 10px;
            margin-bottom: 8px;
        }
        
        /* Status tempo badges */
        .tempo-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .tempo-lewat {
            background-color: #dc3545;
            color: white;
            animation: blink 2s infinite;
        }
        
        .tempo-hari-ini {
            background-color: #ffc107;
            color: #000;
            animation: pulse 2s infinite;
        }
        
        .tempo-belum {
            background-color: #28a745;
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
        
        /* Date info styling */
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
        
        
        
        /* Table row highlighting */
        .table-row-lewat-tempo {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .table-row-tempo-hari-ini {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .table-row-belum-tempo {
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        /* Jenis jual badges */
        .jns-jual-badge {
            font-size: 0.75em;
            padding: 0.3em 0.6em;
            border-radius: 15px;
        }
        
        .jns-rawat-jalan { background-color: #28a745; color: white; }
        .jns-rawat-inap { background-color: #dc3545; color: white; }
        .jns-karyawan { background-color: #6f42c1; color: white; }
        .jns-jual-bebas { background-color: #17a2b8; color: white; }
        .jns-beli-luar { background-color: #fd7e14; color: white; }
        .jns-default { background-color: #6c757d; color: white; }
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
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            Data Piutang Obat & Farmasi
                        </h4>
                        <div>
                            <a href="obat.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-prescription-bottle-alt"></i> Data Resep
                            </a>
                            <a href="data_obat.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-pills"></i> Data Obat
                            </a>
                            <a href="piutang.php?logout" class="btn btn-danger">
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
                                    Menampilkan Semua Data Piutang
                                <?php elseif ($tanggal_dari == $tanggal_sampai): ?>
                                    Data Piutang: <?= konversiTanggal($tanggal_dari) ?>
                                <?php else: ?>
                                    Data Piutang: <?= konversiTanggal($tanggal_dari) ?> s/d <?= konversiTanggal($tanggal_sampai) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="quick-date-buttons">
                            <a href="piutang.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-calendar-day"></i> 3 Bulan Terakhir
                            </a>
                            
                            <a href="piutang.php?tanggal_dari=<?= date('Y-m-d', strtotime('-1 months')) ?>&tanggal_sampai=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-calendar-alt"></i> 1 Bulan Terakhir
                            </a>
                            
                            <a href="piutang.php?tanggal_dari=<?= date('Y-m-01') ?>&tanggal_sampai=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-calendar-week"></i> Bulan Ini
                            </a>
                            
                            <?php if (!$show_all): ?>
                            <a href="piutang.php?show_all=1" class="btn btn-sm btn-warning">
                                <i class="fas fa-list"></i> Semua Data
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Jenis Jual</label>
                            <select name="jns_jual" class="form-select">
                                <option value="">Semua Jenis</option>
                                <?php 
                                mysqli_data_seek($jenis_jual, 0);
                                while($jns = mysqli_fetch_array($jenis_jual)) { 
                                ?>
                                <option value="<?= $jns['jns_jual'] ?>" <?= $filter_jns_jual == $jns['jns_jual'] ? 'selected' : '' ?>>
                                    <?= $jns['jns_jual'] ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Penanggung Jawab</label>
                            <select name="penjab" class="form-select">
                                <option value="">Semua Penjab</option>
                                <?php 
                                mysqli_data_seek($penanggung_jawab, 0);
                                while($pj = mysqli_fetch_array($penanggung_jawab)) { 
                                ?>
                                <option value="<?= htmlspecialchars($pj['png_jawab']) ?>" <?= $filter_penjab == $pj['png_jawab'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pj['png_jawab']) ?>
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
                        
                        <div class="col-md-4">
                            <label class="form-label">Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Nota, No RM, Nama Pasien..." value="<?= htmlspecialchars($search) ?>">
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
                                    <i class="fas fa-refresh"></i> Reset ke 3 Bulan Terakhir
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

                <!-- Data Piutang Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="12%">Nota Piutang</th>
                                        <th width="10%">Tanggal</th>
                                        <th width="18%">Pasien</th>
                                        <th width="10%">No. RM</th>
                                        <th width="12%">Penanggung Jawab</th> <!-- ✨ TAMBAHKAN -->
                                        <th width="33%">Detail Obat</th>
                                    </tr>
                                </thead>                           
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    if (mysqli_num_rows($piutang_data) > 0) {
                                        while ($row = mysqli_fetch_array($piutang_data)) {
                                            $detail_obat = getDetailPiutang($row['nota_piutang']);
                                            $total_piutang = getTotalPiutang($row['nota_piutang']);
                                            
                                            // Tentukan class untuk row berdasarkan status tempo
                                            $row_class = '';
                                            switch ($row['status_tempo']) {
                                                case 'LEWAT TEMPO':
                                                    $row_class = 'table-row-lewat-tempo';
                                                    break;
                                                case 'JATUH TEMPO HARI INI':
                                                    $row_class = 'table-row-tempo-hari-ini';
                                                    break;
                                                case 'BELUM TEMPO':
                                                    $row_class = 'table-row-belum-tempo';
                                                    break;
                                            }
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nota_piutang']) ?></strong><br>
                                            <small class="text-muted">
                                                <?= konversiTanggal($row['tgl_piutang']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div>Piutang:</div>
                                            <small><?= konversiTanggal($row['tgl_piutang']) ?></small><br>
                                            <div class="mt-1">Tempo:</div>
                                            <small><?= konversiTanggal($row['tgltempo']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nm_pasien']) ?></strong>
                                            <?php if ($row['catatan']) { ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars($row['catatan']) ?>
                                            </small>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $row['no_rkm_medis'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($row['png_jawab']) { ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($row['png_jawab']) ?>
                                            </span>
                                            <?php } else { ?>
                                            <span class="text-muted">-</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $obat_count = 0;
                                            while ($obat = mysqli_fetch_array($detail_obat)) { 
                                                $obat_count++;
                                            ?>
                                            <div class="piutang-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div style="flex: 1;">
                                                        <strong><?= htmlspecialchars($obat['nama_brng']) ?></strong>
                                                        <?php if ($obat['no_batch']) { ?>
                                                        <span class="badge bg-info ms-1">
                                                            <i class="fas fa-barcode"></i> <?= htmlspecialchars($obat['no_batch']) ?>
                                                        </span>
                                                        <?php } ?>
                                                    </div>
                                                    <small class="text-end">
                                                        <div>Qty: <?= number_format($obat['jumlah'], 0, ',', '.') ?></div>
                                                        <div>@<?= formatDuit($obat['h_jual']) ?></div>
                                                        <div><strong><?= formatDuit($obat['total']) ?></strong></div>
                                                    </small>
                                                </div>
                                                <?php if ($obat['aturan_pakai']) { ?>
                                                <small class="text-info">
                                                    <i class="fas fa-pills"></i> <?= htmlspecialchars($obat['aturan_pakai']) ?>
                                                </small>
                                                <?php } ?>
                                            </div>
                                            <?php } ?>
                                            
                                            <?php if ($obat_count == 0) { ?>
                                            <span class="text-muted"><i>Tidak ada detail obat</i></span>
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
                                                <?php if (!$show_all && $tanggal_dari == $three_months_ago): ?>
                                                    Tidak ada data piutang dalam 3 bulan terakhir
                                                <?php else: ?>
                                                    Tidak ada data piutang yang ditemukan
                                                <?php endif; ?>
                                            </span>
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
                                    <div class="col-md-4">
                                        <i class="fas fa-info-circle text-primary"></i>
                                        <strong>Data Ditampilkan:</strong> <?= mysqli_num_rows($piutang_data) ?> piutang
                                    </div>
                                    <div class="col-md-4">
                                        <i class="fas fa-calendar text-info"></i>
                                        <strong>Update:</strong> <?= date('d/m/Y H:i') ?>
                                    </div>
                                    <div class="col-md-4">
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
        // Export data function dengan pilihan format
        function exportData(format = 'csv') {
            // Ambil parameter filter saat ini
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            // Tentukan file export berdasarkan format
            let exportFile = '';
            if (format === 'excel') {
                exportFile = 'export_piutang.php';
            } else {
                exportFile = 'export_piutang_simple.php';
            }
            
            // Redirect ke halaman export
            window.open(exportFile + '?' + params.toString(), '_blank');
        }
        
        // Auto refresh setiap 10 menit
        setTimeout(function() {
            location.reload();
        }, 600000);
        
        // Quick date navigation functions
        function goToToday() {
            const url = new URL(window.location.href);
            url.searchParams.delete('tanggal_dari');
            url.searchParams.delete('tanggal_sampai');
            url.searchParams.delete('show_all');
            window.location.href = url.toString();
        }
        
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
            
            // Show piutang notifications
            const lewatTempo = <?= $statistics['lewat_tempo'] ?>;
            const tempoHariIni = <?= $statistics['tempo_hari_ini'] ?>;
            
            if (lewatTempo > 0) {
                console.warn('⚠️ PERINGATAN: ' + lewatTempo + ' piutang sudah lewat tempo!');
            }
            
            if (tempoHariIni > 0) {
                console.log('🔔 REMINDER: ' + tempoHariIni + ' piutang jatuh tempo hari ini!');
            }
            
            // Highlight rows based on tempo status
            const lewatTempoRows = document.querySelectorAll('.table-row-lewat-tempo');
            lewatTempoRows.forEach(function(row) {
                row.style.borderLeft = '4px solid #dc3545';
            });
            
            const tempoHariIniRows = document.querySelectorAll('.table-row-tempo-hari-ini');
            tempoHariIniRows.forEach(function(row) {
                row.style.borderLeft = '4px solid #ffc107';
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + L = Show Lewat Tempo
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                // Filter untuk lewat tempo (bisa dikembangkan lebih lanjut)
                console.log('Filter lewat tempo');
            }
            
            // Ctrl + T = Today
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                goToToday();
            }
        });
        
        // Real-time tempo counter untuk item kritis
        function updateTempoCounters() {
            const tempoBadges = document.querySelectorAll('.tempo-lewat, .tempo-hari-ini');
            tempoBadges.forEach(function(badge) {
                if (badge.classList.contains('tempo-lewat')) {
                    // Add blinking effect for overdue items
                    badge.style.animation = 'blink 1s infinite';
                } else if (badge.classList.contains('tempo-hari-ini')) {
                    // Add warning pulse for due today
                    badge.style.animation = 'pulse 2s infinite';
                }
            });
        }
        
        // Run tempo counter update
        updateTempoCounters();
        
        // Periodic notification for critical items
        setInterval(function() {
            const lewatTempo = <?= $statistics['lewat_tempo'] ?>;
            const tempoHariIni = <?= $statistics['tempo_hari_ini'] ?>;
            
            if (lewatTempo > 0 || tempoHariIni > 0) {
                // Flash title for attention
                let originalTitle = document.title;
                document.title = '🚨 PIUTANG ALERT - ' + originalTitle;
                
                setTimeout(function() {
                    document.title = originalTitle;
                }, 3000);
            }
        }, 300000); // Every 5 minutes
        
        // Enhanced visual feedback untuk stat cards
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
        console.log('💰 Data Piutang System Loaded');
        console.log('📊 Total Piutang: ' + <?= $statistics['total'] ?>);
        console.log('⚠️ Critical: ' + <?= $statistics['lewat_tempo'] ?> + ' lewat tempo, ' + <?= $statistics['tempo_hari_ini'] ?> + ' tempo hari ini');
        console.log('💵 Total Nilai: Rp ' + <?= number_format($statistics['total_nilai'], 0, ',', '.') ?>);
        console.log('⌨️ Shortcuts: Ctrl+T (Today), Ctrl+L (Lewat Tempo)');
    </script>
</body>
</html>