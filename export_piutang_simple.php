<?php
require_once 'session_config.php';
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once 'conf.php';

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

// Function untuk mendapatkan detail piutang
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

// Ambil parameter filter
$today = date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';
$tanggal_dari = isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] != '' ? $_GET['tanggal_dari'] : $today;
$tanggal_sampai = isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != '' ? $_GET['tanggal_sampai'] : $today;
$filter_jns_jual = isset($_GET['jns_jual']) ? $_GET['jns_jual'] : '';
$filter_penjab = isset($_GET['penjab']) ? $_GET['penjab'] : '';

// Update function call
$piutang_data = getPiutangData($search, $tanggal_dari, $tanggal_sampai, $filter_jns_jual, $filter_penjab);

// Tambahkan header CSV
echo "No,Nota Piutang,Tanggal Piutang,No RM,Nama Pasien,Penanggung Jawab,Nama Obat,Jumlah,Harga Jual,Total,Jenis Jual,Tanggal Tempo,Catatan,No Batch,Aturan Pakai\n";

// Update data array dengan penanggung jawab
$data = array(
    $no,
    '"' . str_replace('"', '""', $row['nota_piutang']) . '"',
    '"' . konversiTanggal($row['tgl_piutang']) . '"',
    '"' . $row['no_rkm_medis'] . '"',
    '"' . str_replace('"', '""', $row['nm_pasien']) . '"',
    '"' . str_replace('"', '""', $row['png_jawab']) . '"', // ✨ TAMBAHKAN
    '"' . str_replace('"', '""', $obat['nama_brng']) . '"',
    // ... data lainnya
);

$show_all = isset($_GET['show_all']) ? true : false;
if ($show_all) {
    $tanggal_dari = '';
    $tanggal_sampai = '';
}

// Ambil data piutang
$piutang_data = getPiutangData($search, $tanggal_dari, $tanggal_sampai, $filter_jns_jual, $filter_status);

// Set header untuk download CSV
$filename = "Data_Piutang_" . date('Y-m-d_H-i-s') . ".csv";
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output BOM untuk UTF-8
echo "\xEF\xBB\xBF";

// Output CSV headers
echo "No,Nota Piutang,Tanggal Piutang,No RM,Nama Pasien,Nama Obat,Jumlah,Harga Jual,Total,Jenis Jual,Status,Sisa Piutang,Tanggal Tempo,Status Tempo,Hari ke Tempo,Catatan,No Batch,Aturan Pakai\n";

// Output data
$no = 1;
$total_piutang = 0;
$total_nilai = 0;
$stat_lewat_tempo = 0;
$stat_tempo_hari_ini = 0;
$stat_belum_tempo = 0;

if (mysqli_num_rows($piutang_data) > 0) {
    while ($row = mysqli_fetch_array($piutang_data)) {
        $total_piutang++;
        $total_nilai += $row['sisapiutang'];
        
        // Statistik tempo
        switch($row['status_tempo']) {
            case 'LEWAT TEMPO': $stat_lewat_tempo++; break;
            case 'JATUH TEMPO HARI INI': $stat_tempo_hari_ini++; break;
            case 'BELUM TEMPO': $stat_belum_tempo++; break;
        }
        
        $detail_obat = getDetailPiutang($row['nota_piutang']);
        
        if (mysqli_num_rows($detail_obat) > 0) {
            while ($obat = mysqli_fetch_array($detail_obat)) {
                // Format hari ke tempo
                $hari_tempo_text = '';
                if ($row['hari_tempo'] !== null) {
                    if ($row['hari_tempo'] > 0) {
                        $hari_tempo_text = $row['hari_tempo'] . ' hari lagi';
                    } elseif ($row['hari_tempo'] < 0) {
                        $hari_tempo_text = abs($row['hari_tempo']) . ' hari lewat';
                    } else {
                        $hari_tempo_text = 'Hari ini';
                    }
                }
                
                // Escape dan clean data untuk CSV
                $data = array(
                    $no,
                    '"' . str_replace('"', '""', $row['nota_piutang']) . '"',
                    '"' . konversiTanggal($row['tgl_piutang']) . '"',
                    '"' . $row['no_rkm_medis'] . '"',
                    '"' . str_replace('"', '""', $row['nm_pasien']) . '"',
                    '"' . str_replace('"', '""', $obat['nama_brng']) . '"',
                    number_format($obat['jumlah'], 0, ',', '.'),
                    '"Rp ' . number_format($obat['h_jual'], 0, ',', '.') . '"',
                    '"Rp ' . number_format($obat['total'], 0, ',', '.') . '"',
                    '"' . str_replace('"', '""', $row['jns_jual']) . '"',
                    '"' . str_replace('"', '""', $row['status']) . '"',
                    '"Rp ' . number_format($row['sisapiutang'], 0, ',', '.') . '"',
                    '"' . konversiTanggal($row['tgltempo']) . '"',
                    '"' . $row['status_tempo'] . '"',
                    '"' . $hari_tempo_text . '"',
                    '"' . str_replace('"', '""', $row['catatan']) . '"',
                    '"' . str_replace('"', '""', $obat['no_batch']) . '"',
                    '"' . str_replace('"', '""', $obat['aturan_pakai']) . '"'
                );
                
                echo implode(',', $data) . "\n";
            }
        } else {
            // Jika tidak ada detail obat
            $hari_tempo_text = '';
            if ($row['hari_tempo'] !== null) {
                if ($row['hari_tempo'] > 0) {
                    $hari_tempo_text = $row['hari_tempo'] . ' hari lagi';
                } elseif ($row['hari_tempo'] < 0) {
                    $hari_tempo_text = abs($row['hari_tempo']) . ' hari lewat';
                } else {
                    $hari_tempo_text = 'Hari ini';
                }
            }
            
            $data = array(
                $no,
                '"' . str_replace('"', '""', $row['nota_piutang']) . '"',
                '"' . konversiTanggal($row['tgl_piutang']) . '"',
                '"' . $row['no_rkm_medis'] . '"',
                '"' . str_replace('"', '""', $row['nm_pasien']) . '"',
                '"Tidak ada detail obat"',
                '0',
                '"Rp 0"',
                '"Rp 0"',
                '"' . str_replace('"', '""', $row['jns_jual']) . '"',
                '"' . str_replace('"', '""', $row['status']) . '"',
                '"Rp ' . number_format($row['sisapiutang'], 0, ',', '.') . '"',
                '"' . konversiTanggal($row['tgltempo']) . '"',
                '"' . $row['status_tempo'] . '"',
                '"' . $hari_tempo_text . '"',
                '"' . str_replace('"', '""', $row['catatan']) . '"',
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
echo "RINGKASAN DATA PIUTANG\n";
echo "Total Piutang," . $total_piutang . "\n";
echo "Total Nilai Piutang,Rp " . number_format($total_nilai, 0, ',', '.') . "\n";
echo "\n";
echo "STATISTIK TEMPO\n";
echo "Lewat Tempo," . $stat_lewat_tempo . "\n";
echo "Tempo Hari Ini," . $stat_tempo_hari_ini . "\n";
echo "Belum Tempo," . $stat_belum_tempo . "\n";
echo "\n";
echo "PERINGATAN\n";
if ($stat_lewat_tempo > 0) {
    echo "BAHAYA," . $stat_lewat_tempo . " piutang sudah LEWAT TEMPO!\n";
}
if ($stat_tempo_hari_ini > 0) {
    echo "PERHATIAN," . $stat_tempo_hari_ini . " piutang JATUH TEMPO HARI INI!\n";
}
if ($stat_lewat_tempo == 0 && $stat_tempo_hari_ini == 0) {
    echo "STATUS,Semua piutang masih dalam batas waktu\n";
}
echo "\n";
echo "FILTER DITERAPKAN\n";
if ($search) echo "Pencarian," . $search . "\n";
if ($tanggal_dari && $tanggal_sampai) {
    if ($tanggal_dari == $tanggal_sampai) {
        echo "Tanggal," . konversiTanggal($tanggal_dari) . "\n";
    } else {
        echo "Periode," . konversiTanggal($tanggal_dari) . " s/d " . konversiTanggal($tanggal_sampai) . "\n";
    }
}
if ($filter_jns_jual) echo "Jenis Jual," . $filter_jns_jual . "\n";
if ($filter_status) echo "Status," . $filter_status . "\n";
echo "\n";
echo "Dicetak pada," . date('d F Y H:i:s') . "\n";
echo "Oleh," . $_SESSION['username'] . "\n";
?>