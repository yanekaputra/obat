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

// Set header untuk download Excel
$filename = "Data_Resep_Obat_" . date('Y-m-d_H-i-s') . ".xls";
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
</style>
</head>
<body>
    <table border="1" cellpadding="3" cellspacing="0">
        <!-- Header Info -->
        <tr>
            <td colspan="12" class="header">
                <h2>LAPORAN DATA RESEP OBAT</h2>
                <h3>RSCM - Rumah Sakit Cahaya Medika</h3>
            </td>
        </tr>
        <tr>
            <td colspan="12" class="center">
                <strong>Periode: 
                <?php if ($show_all): ?>
                    Semua Data
                <?php elseif ($tanggal_dari == $tanggal_sampai): ?>
                    <?= konversiTanggal($tanggal_dari) ?>
                <?php else: ?>
                    <?= konversiTanggal($tanggal_dari) ?> s/d <?= konversiTanggal($tanggal_sampai) ?>
                <?php endif; ?>
                </strong><br>
                Dicetak pada: <?= date('d F Y H:i:s') ?> oleh <?= $_SESSION['username'] ?>
            </td>
        </tr>
        <tr><td colspan="12"></td></tr>
        
        <!-- Filter Info -->
        <?php if ($filter_status || $search || $filter_kategori || $filter_golongan): ?>
        <tr>
            <td colspan="12" class="subheader">
                <strong>Filter yang Diterapkan:</strong>
                <?php if ($filter_status): ?>Status: <?= ucfirst($filter_status) ?> | <?php endif; ?>
                <?php if ($search): ?>Pencarian: <?= htmlspecialchars($search) ?> | <?php endif; ?>
                <?php if ($filter_kategori): ?>
                    <?php $nama_kat = getOne("SELECT nama FROM kategori_barang WHERE kode = '$filter_kategori'"); ?>
                    Kategori: <?= $nama_kat ?> | 
                <?php endif; ?>
                <?php if ($filter_golongan): ?>
                    <?php $nama_gol = getOne("SELECT nama FROM golongan_barang WHERE kode = '$filter_golongan'"); ?>
                    Golongan: <?= $nama_gol ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr><td colspan="12"></td></tr>
        <?php endif; ?>
        
        <!-- Header Tabel -->
        <tr class="subheader">
            <td><strong>No</strong></td>
            <td><strong>No. Resep</strong></td>
            <td><strong>Tanggal Peresepan</strong></td>
            <td><strong>Jam</strong></td>
            <td><strong>Dokter</strong></td>
            <td><strong>Pasien</strong></td>
            <td><strong>No. RM</strong></td>
            <td><strong>Jenis Rawat</strong></td>
            <td><strong>Nama Obat</strong></td>
            <td><strong>Jumlah</strong></td>
            <td><strong>Harga Satuan</strong></td>
            <td><strong>Total Harga</strong></td>
        </tr>
        
        <!-- Data -->
        <?php 
        $no = 1;
        $grand_total = 0;
        $total_resep = 0;
        
        if (mysqli_num_rows($resep_data) > 0) {
            while ($row = mysqli_fetch_array($resep_data)) {
                $detail_obat = getDetailObat($row['no_resep']);
                $total_nilai_resep = getTotalResep($row['no_resep']);
                $grand_total += $total_nilai_resep;
                $total_resep++;
                
                $obat_count = mysqli_num_rows($detail_obat);
                
                if ($obat_count > 0) {
                    mysqli_data_seek($detail_obat, 0);
                    $first_row = true;
                    
                    while ($obat = mysqli_fetch_array($detail_obat)) {
                        echo "<tr>";
                        
                        if ($first_row) {
                            // Data resep (hanya di baris pertama)
                            echo "<td rowspan='$obat_count' class='center'>$no</td>";
                            echo "<td rowspan='$obat_count'>" . htmlspecialchars($row['no_resep']) . "</td>";
                            echo "<td rowspan='$obat_count' class='center'>" . konversiTanggal($row['tgl_peresepan']) . "</td>";
                            echo "<td rowspan='$obat_count' class='center'>" . $row['jam_peresepan'] . "</td>";
                            echo "<td rowspan='$obat_count'>" . htmlspecialchars($row['nm_dokter']) . "</td>";
                            echo "<td rowspan='$obat_count'>" . htmlspecialchars($row['p_jawab']) . "</td>";
                            echo "<td rowspan='$obat_count' class='center'>" . $row['no_rkm_medis'] . "</td>";
                            echo "<td rowspan='$obat_count' class='center'>" . $row['jenis_rawat'] . "</td>";
                            $first_row = false;
                        }
                        
                        // Data obat
                        echo "<td>" . htmlspecialchars($obat['nama_brng']);
                        if ($obat['aturan_pakai']) {
                            echo "<br><small>Aturan: " . htmlspecialchars($obat['aturan_pakai']) . "</small>";
                        }
                        if ($obat['nama_golongan']) {
                            echo "<br><small>Golongan: " . htmlspecialchars($obat['nama_golongan']) . "</small>";
                        }
                        echo "</td>";
                        echo "<td class='number'>" . number_format($obat['jml'], 0, ',', '.') . "</td>";
                        echo "<td class='currency'>Rp " . number_format($obat['harga'], 0, ',', '.') . "</td>";
                        echo "<td class='currency'>Rp " . number_format($obat['jml'] * $obat['harga'], 0, ',', '.') . "</td>";
                        
                        echo "</tr>";
                    }
                } else {
                    // Jika tidak ada detail obat
                    echo "<tr>";
                    echo "<td class='center'>$no</td>";
                    echo "<td>" . htmlspecialchars($row['no_resep']) . "</td>";
                    echo "<td class='center'>" . konversiTanggal($row['tgl_peresepan']) . "</td>";
                    echo "<td class='center'>" . $row['jam_peresepan'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['nm_dokter']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['p_jawab']) . "</td>";
                    echo "<td class='center'>" . $row['no_rkm_medis'] . "</td>";
                    echo "<td class='center'>" . $row['jenis_rawat'] . "</td>";
                    echo "<td colspan='4' class='center'><em>Tidak ada detail obat</em></td>";
                    echo "</tr>";
                }
                
                $no++;
            }
        } else {
            echo "<tr><td colspan='12' class='center'><em>Tidak ada data yang ditemukan</em></td></tr>";
        }
        ?>
        
        <!-- Summary -->
        <tr><td colspan="12"></td></tr>
        <tr class="subheader">
            <td colspan="8"><strong>RINGKASAN</strong></td>
            <td colspan="4"></td>
        </tr>
        <tr>
            <td colspan="8"><strong>Total Resep:</strong></td>
            <td colspan="4" class="number"><strong><?= number_format($total_resep, 0, ',', '.') ?> resep</strong></td>
        </tr>
        <tr>
            <td colspan="8"><strong>Grand Total Nilai:</strong></td>
            <td colspan="4" class="currency"><strong>Rp <?= number_format($grand_total, 0, ',', '.') ?></strong></td>
        </tr>
        
        <!-- Statistik Additional -->
        <?php
        mysqli_data_seek($resep_data, 0);
        $stat_ralan = 0;
        $stat_ranap = 0;
        $stat_diserahkan = 0;
        $stat_menunggu = 0;
        
        while ($row = mysqli_fetch_array($resep_data)) {
            if ($row['jenis_rawat'] == 'Rawat Jalan') $stat_ralan++;
            else $stat_ranap++;
            
            if ($row['tgl_penyerahan'] != '0000-00-00') $stat_diserahkan++;
            else $stat_menunggu++;
        }
        ?>
        
        <tr><td colspan="12"></td></tr>
        <tr class="subheader">
            <td colspan="12"><strong>STATISTIK DETAIL</strong></td>
        </tr>
        <tr>
            <td colspan="3"><strong>Rawat Jalan:</strong></td>
            <td class="number"><?= $stat_ralan ?> resep</td>
            <td colspan="3"><strong>Rawat Inap:</strong></td>
            <td class="number"><?= $stat_ranap ?> resep</td>
            <td colspan="4"></td>
        </tr>
        <tr>
            <td colspan="3"><strong>Sudah Diserahkan:</strong></td>
            <td class="number"><?= $stat_diserahkan ?> resep</td>
            <td colspan="3"><strong>Belum Diserahkan:</strong></td>
            <td class="number"><?= $stat_menunggu ?> resep</td>
            <td colspan="4"></td>
        </tr>
        
        <!-- Footer -->
        <tr><td colspan="12"></td></tr>
        <tr>
            <td colspan="12" class="center">
                <small>
                    <em>Laporan ini digenerate otomatis oleh Sistem Informasi Obat RSCM<br>
                    Dicetak pada: <?= date('d F Y H:i:s') ?> oleh User: <?= $_SESSION['username'] ?></em>
                </small>
            </td>
        </tr>
    </table>
</body>
</html>