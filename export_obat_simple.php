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
                    WHEN db.expire IS NULL OR db.expire = '0000-00-00' THEN 'Tanpa Expire'
                    WHEN db.expire < '$today' THEN 'EXPIRED'
                    WHEN db.expire >= '$today' AND db.expire <= '$oneMonthFromNow' THEN 'SEGERA EXPIRE'
                    ELSE 'VALID'
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

// Set header untuk download CSV yang kompatibel dengan Excel
$filename = "Data_Obat_" . date('Y-m-d_H-i-s') . ".csv";
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output BOM untuk UTF-8 agar Excel bisa baca karakter Indonesia
echo "\xEF\xBB\xBF";

// Output CSV headers
echo "No,Kode Obat,Nama Obat,Kategori,Golongan,Tanggal Expire,Status Expire,Hari ke Expire,Harga Jual,Status Obat\n";

// Output data
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
            case 'EXPIRED': $stat_expired++; break;
            case 'SEGERA EXPIRE': $stat_expiring++; break;
            case 'VALID': $stat_valid++; break;
            case 'Tanpa Expire': $stat_no_expire++; break;
        }
        
        // Format tanggal expire
        $tanggal_expire = '';
        if ($row['expire']) {
            $tanggal_expire = konversiTanggal($row['expire']);
        } else {
            $tanggal_expire = '-';
        }
        
        // Format hari ke expire
        $hari_expire = '';
        if ($row['days_to_expire'] !== NULL) {
            if ($row['days_to_expire'] > 0) {
                $hari_expire = $row['days_to_expire'] . ' hari lagi';
            } else {
                $hari_expire = abs($row['days_to_expire']) . ' hari lalu';
            }
        } else {
            $hari_expire = '-';
        }
        
        // Escape dan clean data untuk CSV
        $data = array(
            $no,
            '"' . str_replace('"', '""', $row['kode_brng']) . '"',
            '"' . str_replace('"', '""', $row['nama_brng']) . '"',
            '"' . str_replace('"', '""', $row['nama_kategori'] ?: '-') . '"',
            '"' . str_replace('"', '""', $row['nama_golongan'] ?: '-') . '"',
            '"' . $tanggal_expire . '"',
            '"' . $row['expire_status'] . '"',
            '"' . $hari_expire . '"',
            '"Rp ' . number_format($row['ralan'], 0, ',', '.') . '"',
            '"' . ($row['status'] == '1' ? 'AKTIF' : 'NON-AKTIF') . '"'
        );
        
        echo implode(',', $data) . "\n";
        $no++;
    }
}

// Summary di akhir file
echo "\n";
echo "RINGKASAN DATA OBAT\n";
echo "Total Obat," . $total_obat . "\n";
echo "\n";
echo "STATISTIK EXPIRE\n";
echo "Sudah Expired," . $stat_expired . "\n";
echo "Akan Expire < 1 Bulan," . $stat_expiring . "\n";
echo "Masih Valid," . $stat_valid . "\n";
echo "Tanpa Expire," . $stat_no_expire . "\n";
echo "\n";
echo "PERINGATAN\n";
if ($stat_expired > 0) {
    echo "BAHAYA," . $stat_expired . " obat sudah EXPIRED!\n";
}
if ($stat_expiring > 0) {
    echo "PERHATIAN," . $stat_expiring . " obat akan expire dalam 1 bulan!\n";
}
if ($stat_expired == 0 && $stat_expiring == 0) {
    echo "STATUS,Semua obat dalam kondisi baik\n";
}
echo "\n";
echo "Dicetak pada," . date('d F Y H:i:s') . "\n";
echo "Oleh," . $_SESSION['username'] . "\n";
?>