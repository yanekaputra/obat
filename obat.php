<?php
require_once 'conf.php';
//require_once 'auth_check.php';  // Proteksi halaman

// Function untuk mendapatkan data resep
function getResepData($status = null, $search = '', $tanggal_dari = '', $tanggal_sampai = '', $kategori = '', $golongan = '') {
    $whereClause = "WHERE 1=1";
    
    if ($status) {
        $whereClause .= " AND ro.status = '" . validTeks($status) . "'";
    }
    
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (ro.no_resep LIKE '%$search%' OR d.nm_dokter LIKE '%$search%' OR db.nama_brng LIKE '%$search%')";
    }
    
    if ($tanggal_dari && $tanggal_sampai) {
        $tanggal_dari = validTeks($tanggal_dari);
        $tanggal_sampai = validTeks($tanggal_sampai);
        $whereClause .= " AND ro.tgl_peresepan BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
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
                -- Perbaikan: Gunakan status dari resep_obat untuk menentukan jenis rawat
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
            ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC";
    
    return bukaquery($sql);
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

// Ambil parameter filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : '';
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : '';
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_golongan = isset($_GET['golongan']) ? $_GET['golongan'] : '';

// Ambil data untuk dropdown
$kategori_obat = getKategoriObat();
$golongan_obat = getGolonganObat();

// Ambil data resep
$resep_data = getResepData($filter_status, $search, $tanggal_dari, $tanggal_sampai, $filter_kategori, $filter_golongan);
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header ">
                        <h4 class="mb-0">
                            <i class="fas fa-prescription-bottle-alt me-2"></i>
                            Data Resep Obat - Rawat Jalan & Rawat Inap
                        </h4>
                    </div>
                </div>

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
                        <div class="col-md-2">
                            <label class="form-label">Pencarian</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="No Resep, Dokter..." value="<?= $search ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
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
                                <button type="button" class="btn btn-info" onclick="exportData()">
                                    <i class="fas fa-download"></i> Export Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Data Resep -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                    $no = 1;
                                    if (mysqli_num_rows($resep_data) > 0) {
                                        while ($row = mysqli_fetch_array($resep_data)) {
                                            $detail_obat = getDetailObat($row['no_resep']);
                                            $total_resep = getTotalResep($row['no_resep']);
                                            
                                            // Perbaikan: Tentukan class badge dan icon berdasarkan jenis rawat yang benar
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
                                            <span class="text-muted">Tidak ada data resep yang ditemukan</span>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Total Resep</h6>
                                        <h4><?= mysqli_num_rows($resep_data) ?></h4>
                                    </div>
                                    <i class="fas fa-prescription-bottle-alt fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Reset pointer untuk menghitung statistik
                    mysqli_data_seek($resep_data, 0);
                    $total_ralan = 0;
                    $total_ranap = 0;
                    $total_diserahkan = 0;
                    $total_menunggu = 0;
                    
                    while ($row = mysqli_fetch_array($resep_data)) {
                        // Perbaikan: Gunakan jenis_rawat bukan status untuk menghitung
                        if ($row['jenis_rawat'] == 'Rawat Jalan') $total_ralan++;
                        else $total_ranap++;
                        
                        if ($row['tgl_penyerahan'] != '0000-00-00') $total_diserahkan++;
                        else $total_menunggu++;
                    }
                    ?>
                    
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Rawat Jalan</h6>
                                        <h4><?= $total_ralan ?></h4>
                                    </div>
                                    <i class="fas fa-user-md fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Rawat Inap</h6>
                                        <h4><?= $total_ranap ?></h4>
                                    </div>
                                    <i class="fas fa-bed fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Diserahkan</h6>
                                        <h4><?= $total_diserahkan ?></h4>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6>Menunggu</h6>
                                        <h4><?= $total_menunggu ?></h4>
                                    </div>
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Status Info -->
                    <?php if ($filter_kategori || $filter_golongan) { ?>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="text-center">
                                    <h6>Filter Aktif</h6>
                                    <?php if ($filter_kategori) { 
                                        $nama_kat = getOne("SELECT nama FROM kategori_barang WHERE kode = '$filter_kategori'");
                                    ?>
                                    <small><i class="fas fa-tag"></i> <?= $nama_kat ?></small><br>
                                    <?php } ?>
                                    <?php if ($filter_golongan) { 
                                        $nama_gol = getOne("SELECT nama FROM golongan_barang WHERE kode = '$filter_golongan'");
                                    ?>
                                    <small><i class="fas fa-pills"></i> <?= $nama_gol ?></small>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh setiap 5 menit
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Print function
        function printResep(no_resep) {
            window.open('print_resep.php?no_resep=' + no_resep, '_blank');
        }
        
        // Export data function
        function exportData() {
            // Ambil parameter filter saat ini
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            
            // Redirect ke halaman export
            window.open('export_resep.php?' + params.toString(), '_blank');
        }
        
        // Quick filter functions
        function filterByKategori(kode_kategori) {
            const url = new URL(window.location.href);
            url.searchParams.set('kategori', kode_kategori);
            url.searchParams.delete('golongan'); // Reset golongan filter
            window.location.href = url.toString();
        }
        
        function filterByGolongan(kode_golongan) {
            const url = new URL(window.location.href);
            url.searchParams.set('golongan', kode_golongan);
            url.searchParams.delete('kategori'); // Reset kategori filter
            window.location.href = url.toString();
        }
        
        // Show tooltip for long text
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    boundary: 'viewport',
                    placement: 'top',
                    trigger: 'hover focus'
                });
            });
            
            // Debug: Check if tooltips are initialized
            console.log('Tooltips initialized:', tooltipList.length);
        });
    </script>
</body>
</html>