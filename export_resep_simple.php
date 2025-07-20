<?php
require_once 'session_config.php';
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'conf.php';

// Function untuk mendapatkan data resep (copy dari obat.php)
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

// Ambil parameter filter yang sama dengan obat.php
$today = date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tanggal_dari = isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] != '' ? $_GET['tanggal_dari'] : $today;
$tanggal_sampai = isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != '' ? $_GET['tanggal_sampai'] : $today;
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter_golongan = isset($_GET['golongan']) ? $_GET['golongan'] : '';

$show_all = isset($_GET['show_all']) ? true : false;
if ($show_all) {
    $tanggal_dari = '';
    $tanggal_sampai = '';
}

// Ambil data resep
$resep_data = getResepData($filter_status, $search, $tanggal_dari, $tanggal_sampai, $filter_kategori, $filter_golongan);

// Set header untuk download CSV yang kompatibel dengan Excel
$filename = "Data_Resep_Obat_" . date('Y-m-d_H-i-s') . ".csv";
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output BOM untuk UTF-8 agar Excel bisa baca karakter Indonesia
echo "\xEF\xBB\xBF";

// Output CSV headers
echo "No,No Resep,Tanggal Peresepan,Jam,Dokter,Pasien,No RM,Jenis Rawat,Nama Obat,Jumlah,Harga Satuan,Total Harga,Kategori,Golongan,Aturan Pakai\n";

// Output data
$no = 1;
$grand_total = 0;

if (mysqli_num_rows($resep_data) > 0) {
    while ($row = mysqli_fetch_array($resep_data)) {
        $detail_obat = getDetailObat($row['no_resep']);
        
        if (mysqli_num_rows($detail_obat) > 0) {
            while ($obat = mysqli_fetch_array($detail_obat)) {
                $total_obat = $obat['jml'] * $obat['harga'];
                $grand_total += $total_obat;
                
                // Escape dan clean data untuk CSV
                $data = array(
                    $no,
                    '"' . str_replace('"', '""', $row['no_resep']) . '"',
                    '"' . konversiTanggal($row['tgl_peresepan']) . '"',
                    '"' . $row['jam_peresepan'] . '"',
                    '"' . str_replace('"', '""', $row['nm_dokter']) . '"',
                    '"' . str_replace('"', '""', $row['p_jawab']) . '"',
                    '"' . $row['no_rkm_medis'] . '"',
                    '"' . $row['jenis_rawat'] . '"',
                    '"' . str_replace('"', '""', $obat['nama_brng']) . '"',
                    $obat['jml'],
                    $obat['harga'],
                    $total_obat,
                    '"' . str_replace('"', '""', $obat['nama_kategori']) . '"',
                    '"' . str_replace('"', '""', $obat['nama_golongan']) . '"',
                    '"' . str_replace('"', '""', $obat['aturan_pakai']) . '"'
                );
                
                echo implode(',', $data) . "\n";
            }
        } else {
            // Jika tidak ada detail obat
            $data = array(
                $no,
                '"' . str_replace('"', '""', $row['no_resep']) . '"',
                '"' . konversiTanggal($row['tgl_peresepan']) . '"',
                '"' . $row['jam_peresepan'] . '"',
                '"' . str_replace('"', '""', $row['nm_dokter']) . '"',
                '"' . str_replace('"', '""', $row['p_jawab']) . '"',
                '"' . $row['no_rkm_medis'] . '"',
                '"' . $row['jenis_rawat'] . '"',
                '"Tidak ada detail obat"',
                '0',
                '0',
                '0',
                '""',
                '""',
                '""'
            );
            
            echo implode(',', $data) . "\n";
        }
        
        $no++;
    }
}

// Summary di akhir file
echo "\n";
echo "RINGKASAN\n";
echo "Total Resep," . mysqli_num_rows($resep_data) . "\n";
echo "Grand Total Nilai,Rp " . number_format($grand_total, 0, ',', '.') . "\n";
echo "Dicetak pada," . date('d F Y H:i:s') . "\n";
echo "Oleh," . $_SESSION['username'] . "\n";
?>