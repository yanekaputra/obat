<?php
require_once 'session_config.php';
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'conf.php';

// Function untuk mendapatkan data obat (copy dari data_obat.php)
function getDataObat($search = '', $expire_filter = 'all', $kategori = '', $golongan = '', $status = '') {
    $whereClause = "WHERE 1=1";
    
    if ($search) {
        $search = validTeks($search);
        $whereClause .= " AND (db.kode_brng LIKE '%$search%' OR db.nama_brng LIKE '%$search%')";
    }
    
    if ($kategori) {
        $kategori = validTeks($kategori);
        $whereClause .= " AND db.kode_kategori = '$kategori'";
    }
    
    if ($golongan) {
        $golongan = validTeks($golongan);
        $whereClause .= " AND db.kode_golongan = '$golongan'";
    }
    
    if ($status !== '') {
        $status = validTeks($status);
        $whereClause .= " AND db.status = '$status'";
    }
    
    $today = date('Y-m-d');
    $oneMonthFromNow = date('Y-m-d', strtotime('+1 month'));
    
    switch ($expire_filter) {
        case 'expired':
            $whereClause .= " AND db.expire IS NOT NULL AND db.expire != '0000-00-00' AND db.expire < '$today'";
            break;
        case 'expiring_soon':
            $whereClause .= " AND db.expire IS NOT NULL AND db.expire != '0000-00-00' AND db.expire >= '$today' AND db.expire <= '$oneMonthFromNow'";
            break;
        case 'valid':
            $whereClause .= " AND db.expire IS NOT NULL AND db.expire != '0000-00-00' AND db.expire > '$oneMonthFromNow'";
            break;
        case 'no_expire':
            $whereClause .= " AND (db.expire IS NULL OR db.expire = '0000-00-00')";
            break;
    }
    
    $sql = "SELECT 
                db.kode_brng,
                db.nama_brng,
                CASE 
                    WHEN db.expire = '0000-00-00' OR db.expire IS NULL THEN NULL
                    ELSE db.expire
                END as expire,
                db.status,
                db.stokminimal,
                db.ralan,
                db.h_beli,
                kb.nama as nama_kategori,
                gg.nama as nama_golongan,
                CASE 
                    WHEN db.expire IS NULL OR db.expire = '0000-00-00' THEN 'no_expire'
                    WHEN db.expire < '$today' THEN 'expired'
                    WHEN db.expire >= '$today' AND db.expire <= '$oneMonthFromNow' THEN 'expiring_soon'
                    ELSE 'valid'
                END as expire_status,
                CASE 
                    WHEN db.expire IS NULL OR db.expire = '0000-00-00' THEN NULL
                    ELSE DATEDIFF(db.expire, '$today')
                END as days_to_expire
            FROM databarang db
            LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
            LEFT JOIN golongan_barang gg ON db.kode_golongan = gg.kode
            $whereClause
            ORDER BY 
                CASE 
                    WHEN db.expire IS NULL OR db.expire = '0000-00-00' THEN 4
                    WHEN db.expire < '$today' THEN 1
                    WHEN db.expire >= '$today' AND db.expire <= '$oneMonthFromNow' THEN 2
                    ELSE 3
                END,
                db.expire ASC,
                db.nama_brng ASC";
    
    return bukaquery($sql);
}

// Ambil parameter filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$expire_filter = isset($_GET['expire_filter']) ? $_GET['expire_filter'] : 'all';
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_golongan = isset($_GET['golongan']) ? $_GET['golongan'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '1';

// Ambil data obat
$obat_data = getDataObat($search, $expire_filter, $filter_kategori, $filter_golongan, $filter_status);

// Set header untuk download Excel
$filename = "Data_Obat_" . date('Y-m-d_H-i-s') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output BOM untuk UTF-8
echo "\xEF\xBB\xBF";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
.header { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }
.subheader { background-color: #D9E2F3; font-weight: bold; }
.number { text-align: right; }
.center { text-align: center; }
.currency { text-align: right; }
.expired { background-color: #FFCCCC; }
.expiring { background-color: #FFFFCC; }
.valid { background-color: #CCFFCC; }
.no-expire { background-color: #F0F0F0; }
</style>
</head>
<body>
    <table border="1" cellpadding="3" cellspacing="0">
        <!-- Header Info -->
        <tr>
            <td colspan="10" class="header">
                <h2>LAPORAN DATA OBAT & MONITORING EXPIRE</h2>
                <h3>RSCM - Rumah Sakit Cahaya Medika</h3>
            </td>
        </tr>
        <tr>
            <td colspan="10" class="center">
                <strong>Filter: 
                <?php 
                $filter_text = [];
                if ($expire_filter != 'all') {
                    switch($expire_filter) {
                        case 'expired': $filter_text[] = "Status: Sudah Expired"; break;
                        case 'expiring_soon': $filter_text[] = "Status: Akan Expire < 1 Bulan"; break;
                        case 'valid': $filter_text[] = "Status: Masih Valid"; break;
                        case 'no_expire': $filter_text[] = "Status: Tanpa Expire"; break;
                    }
                }
                if ($search) $filter_text[] = "Pencarian: " . htmlspecialchars($search);
                if ($filter_kategori) {
                    $nama_kat = getOne("SELECT nama FROM kategori_barang WHERE kode = '$filter_kategori'");
                    $filter_text[] = "Kategori: " . $nama_kat;
                }
                if ($filter_golongan) {
                    $nama_gol = getOne("SELECT nama FROM golongan_barang WHERE kode = '$filter_golongan'");
                    $filter_text[] = "Golongan: " . $nama_gol;
                }
                if ($filter_status !== '') {
                    $filter_text[] = "Status Obat: " . ($filter_status == '1' ? 'Aktif' : 'Non-Aktif');
                }
                
                echo empty($filter_text) ? "Semua Data" : implode(" | ", $filter_text);
                ?>
                </strong><br>
                Dicetak pada: <?= date('d F Y H:i:s') ?> oleh <?= $_SESSION['username'] ?>
            </td>
        </tr>
        <tr><td colspan="10"></td></tr>
        
        <!-- Header Tabel -->
        <tr class="subheader">
            <td><strong>No</strong></td>
            <td><strong>Kode Obat</strong></td>
            <td><strong>Nama Obat</strong></td>
            <td><strong>Kategori</strong></td>
            <td><strong>Golongan</strong></td>
            <td><strong>Tanggal Expire</strong></td>
            <td><strong>Status Expire</strong></td>
            <td><strong>Hari ke Expire</strong></td>
            <td><strong>Harga Jual</strong></td>
            <td><strong>Status</strong></td>
        </tr>
        
        <!-- Data -->
        <?php 
        $no = 1;
        $total_obat = 0;
        $stat_expired = 0;
        $stat_expiring = 0;
        $stat_valid = 0;
        $stat_no_expire = 0;
        
        if (mysqli_num_rows($obat_data) > 0) {
            while ($row = mysqli_fetch_array($obat_data)) {
                $total_obat++;
                
                // Statistik
                switch($row['expire_status']) {
                    case 'expired': $stat_expired++; break;
                    case 'expiring_soon': $stat_expiring++; break;
                    case 'valid': $stat_valid++; break;
                    case 'no_expire': $stat_no_expire++; break;
                }
                
                // Tentukan class CSS berdasarkan status expire
                $row_class = '';
                switch($row['expire_status']) {
                    case 'expired': $row_class = 'expired'; break;
                    case 'expiring_soon': $row_class = 'expiring'; break;
                    case 'valid': $row_class = 'valid'; break;
                    case 'no_expire': $row_class = 'no-expire'; break;
                }
                
                echo "<tr class='$row_class'>";
                echo "<td class='center'>$no</td>";
                echo "<td>" . htmlspecialchars($row['kode_brng']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_brng']) . "</td>";
                echo "<td>" . ($row['nama_kategori'] ? htmlspecialchars($row['nama_kategori']) : '-') . "</td>";
                echo "<td>" . ($row['nama_golongan'] ? htmlspecialchars($row['nama_golongan']) : '-') . "</td>";
                
                // Tanggal expire
                if ($row['expire']) {
                    echo "<td class='center'>" . konversiTanggal($row['expire']) . "</td>";
                } else {
                    echo "<td class='center'>-</td>";
                }
                
                // Status expire
                $status_text = '';
                switch($row['expire_status']) {
                    case 'expired': $status_text = 'EXPIRED'; break;
                    case 'expiring_soon': $status_text = 'SEGERA EXPIRE'; break;
                    case 'valid': $status_text = 'VALID'; break;
                    case 'no_expire': $status_text = 'TANPA EXPIRE'; break;
                }
                echo "<td class='center'><strong>$status_text</strong></td>";
                
                // Hari ke expire
                if ($row['days_to_expire'] !== NULL) {
                    if ($row['days_to_expire'] > 0) {
                        echo "<td class='number'>" . $row['days_to_expire'] . " hari lagi</td>";
                    } else {
                        echo "<td class='number'>" . abs($row['days_to_expire']) . " hari lalu</td>";
                    }
                } else {
                    echo "<td class='center'>-</td>";
                }
                
                // Harga
                echo "<td class='currency'>Rp " . number_format($row['ralan'], 0, ',', '.') . "</td>";
                
                // Status aktif
                echo "<td class='center'>" . ($row['status'] == '1' ? 'AKTIF' : 'NON-AKTIF') . "</td>";
                
                echo "</tr>";
                $no++;
            }
        } else {
            echo "<tr><td colspan='10' class='center'><em>Tidak ada data yang ditemukan</em></td></tr>";
        }
        ?>
        
        <!-- Summary -->
        <tr><td colspan="10"></td></tr>
        <tr class="subheader">
            <td colspan="10"><strong>RINGKASAN DATA OBAT</strong></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Total Obat:</strong></td>
            <td class="number"><strong><?= number_format($total_obat, 0, ',', '.') ?></strong></td>
            <td colspan="7"></td>
        </tr>
        
        <!-- Statistik Expire -->
        <tr><td colspan="10"></td></tr>
        <tr class="subheader">
            <td colspan="10"><strong>STATISTIK STATUS EXPIRE</strong></td>
        </tr>
        <tr class="expired">
            <td colspan="2"><strong>Sudah Expired:</strong></td>
            <td class="number"><strong><?= number_format($stat_expired, 0, ',', '.') ?></strong></td>
            <td colspan="2">
                <?php if ($total_obat > 0): ?>
                    (<?= number_format(($stat_expired / $total_obat) * 100, 1) ?>%)
                <?php endif; ?>
            </td>
            <td colspan="5"></td>
        </tr>
        <tr class="expiring">
            <td colspan="2"><strong>Akan Expire < 1 Bulan:</strong></td>
            <td class="number"><strong><?= number_format($stat_expiring, 0, ',', '.') ?></strong></td>
            <td colspan="2">
                <?php if ($total_obat > 0): ?>
                    (<?= number_format(($stat_expiring / $total_obat) * 100, 1) ?>%)
                <?php endif; ?>
            </td>
            <td colspan="5"></td>
        </tr>
        <tr class="valid">
            <td colspan="2"><strong>Masih Valid:</strong></td>
            <td class="number"><strong><?= number_format($stat_valid, 0, ',', '.') ?></strong></td>
            <td colspan="2">
                <?php if ($total_obat > 0): ?>
                    (<?= number_format(($stat_valid / $total_obat) * 100, 1) ?>%)
                <?php endif; ?>
            </td>
            <td colspan="5"></td>
        </tr>
        <tr class="no-expire">
            <td colspan="2"><strong>Tanpa Expire:</strong></td>
            <td class="number"><strong><?= number_format($stat_no_expire, 0, ',', '.') ?></strong></td>
            <td colspan="2">
                <?php if ($total_obat > 0): ?>
                    (<?= number_format(($stat_no_expire / $total_obat) * 100, 1) ?>%)
                <?php endif; ?>
            </td>
            <td colspan="5"></td>
        </tr>
        
        <!-- Alert Summary -->
        <tr><td colspan="10"></td></tr>
        <tr class="subheader">
            <td colspan="10"><strong>RINGKASAN PERINGATAN</strong></td>
        </tr>
        <tr>
            <td colspan="2"><strong>Butuh Perhatian Segera:</strong></td>
            <td class="number"><strong><?= number_format($stat_expired + $stat_expiring, 0, ',', '.') ?></strong></td>
            <td colspan="2">
                <?php if ($total_obat > 0): ?>
                    (<?= number_format((($stat_expired + $stat_expiring) / $total_obat) * 100, 1) ?>%)
                <?php endif; ?>
            </td>
            <td colspan="5">
                <?php if ($stat_expired > 0): ?>
                    <strong style="color: red;">⚠️ <?= $stat_expired ?> obat sudah EXPIRED!</strong><br>
                <?php endif; ?>
                <?php if ($stat_expiring > 0): ?>
                    <strong style="color: orange;">🔔 <?= $stat_expiring ?> obat akan expire dalam 1 bulan!</strong>
                <?php endif; ?>
            </td>
        </tr>
        
        <!-- Rekomendasi -->
        <tr><td colspan="10"></td></tr>
        <tr class="subheader">
            <td colspan="10"><strong>REKOMENDASI TINDAKAN</strong></td>
        </tr>
        <?php if ($stat_expired > 0): ?>
        <tr>
            <td colspan="10">
                <strong>1. EXPIRED (<?= $stat_expired ?> obat):</strong><br>
                • Segera pisahkan obat yang sudah expired<br>
                • Lakukan pemusnahan sesuai prosedur<br>
                • Update sistem inventory<br>
                • Laporkan ke supervisor
            </td>
        </tr>
        <?php endif; ?>
        
        <?php if ($stat_expiring > 0): ?>
        <tr>
            <td colspan="10">
                <strong>2. AKAN EXPIRE (<?= $stat_expiring ?> obat):</strong><br>
                • Prioritaskan penggunaan obat yang akan expire<br>
                • Koordinasi dengan dokter untuk penggantian jika perlu<br>
                • Pertimbangkan program diskon untuk mempercepat penggunaan<br>
                • Evaluasi pola pemesanan untuk menghindari overstocking
            </td>
        </tr>
        <?php endif; ?>
        
        <?php if ($stat_expired == 0 && $stat_expiring == 0): ?>
        <tr>
            <td colspan="10">
                <strong>✅ KONDISI BAIK:</strong><br>
                • Tidak ada obat expired atau akan expire dalam waktu dekat<br>
                • Pertahankan monitoring rutin<br>
                • Lanjutkan sistem FIFO (First In, First Out)<br>
                • Update berkala tanggal expire untuk obat baru
            </td>
        </tr>
        <?php endif; ?>
        
        <!-- Footer -->
        <tr><td colspan="10"></td></tr>
        <tr>
            <td colspan="10" class="center">
                <small>
                    <em>Laporan ini digenerate otomatis oleh Sistem Monitoring Expire Obat RSCM<br>
                    Dicetak pada: <?= date('d F Y H:i:s') ?> oleh User: <?= $_SESSION['username'] ?><br>
                    <strong>PENTING:</strong> Lakukan verifikasi fisik untuk obat yang expired atau akan expire!</em>
                </small>
            </td>
        </tr>
        
        <!-- Legend -->
        <tr><td colspan="10"></td></tr>
        <tr class="subheader">
            <td colspan="10"><strong>KETERANGAN WARNA</strong></td>
        </tr>
        <tr>
            <td colspan="2" class="expired">Merah</td>
            <td colspan="8">Obat sudah EXPIRED - Segera pisahkan dan musnahkan</td>
        </tr>
        <tr>
            <td colspan="2" class="expiring">Kuning</td>
            <td colspan="8">Obat akan EXPIRE dalam 1 bulan - Prioritaskan penggunaan</td>
        </tr>
        <tr>
            <td colspan="2" class="valid">Hijau</td>
            <td colspan="8">Obat masih VALID - Aman untuk digunakan</td>
        </tr>
        <tr>
            <td colspan="2" class="no-expire">Abu-abu</td>
            <td colspan="8">Tanpa tanggal expire - Periksa kemasan fisik</td>
        </tr>
    </table>
</body>
</html>