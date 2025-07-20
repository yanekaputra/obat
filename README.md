# Sistem Informasi Obat RSCM

Sistem Informasi Obat berbasis web untuk Rumah Sakit Cahaya Medika (RSCM) yang mengelola data resep obat, monitoring expire, dan piutang obat untuk rawat jalan dan rawat inap.

## 🏥 Deskripsi

Sistem terintegrasi yang dirancang untuk membantu apoteker dan staff rumah sakit dalam mengelola:
- **Data Resep Obat** dari dokter untuk pasien rawat jalan dan rawat inap
- **Monitoring Expire Obat** dengan peringatan otomatis untuk obat yang akan/sudah expired
- **Data Piutang Obat** untuk tracking pembayaran dan status tempo

## ✨ Fitur Utama

### 🔐 Autentikasi & Keamanan
- Login sistem dengan validasi username dan password
- Session management dengan timeout otomatis (8 jam)
- Password protection untuk akses sistem
- Validasi input untuk mencegah SQL injection
- Security headers dan session configuration

### 📋 Manajemen Resep Obat (`obat.php`)
- **Dashboard Resep**: Tampilan lengkap daftar resep obat dengan tampilan harian default
- **Filter Lanjutan**: 
  - Filter berdasarkan status rawat (Rawat Jalan/Rawat Inap)
  - Filter berdasarkan kategori obat
  - Filter berdasarkan golongan obat
  - Filter berdasarkan rentang tanggal
  - Pencarian berdasarkan nomor resep atau nama dokter
- **Detail Obat**: Informasi lengkap setiap obat dalam resep dengan harga dan aturan pakai
- **Status Tracking**: Monitoring status penyerahan obat (diserahkan/menunggu)
- **Quick Navigation**: Tombol cepat untuk hari ini, kemarin, minggu ini, bulan ini

### 💊 Data Obat & Monitoring Expire (`data_obat.php`)
- **Dashboard Expire**: Monitoring status expire obat dengan color coding
- **Alert System**: Peringatan otomatis untuk obat expired dan akan expire (1 bulan)
- **Visual Indicators**: 
  - 🔴 Merah (Expired) - dengan animasi berkedip
  - 🟡 Kuning (Expire < 1 bulan) - dengan animasi pulse
  - 🟢 Hijau (Valid) - aman untuk digunakan
  - ⚪ Abu-abu (Tanpa expire) - perlu periksa kemasan
- **Statistik Real-time**: Dashboard dengan persentase dan jumlah obat per kategori
- **Filter Komprehensif**: Status expire, kategori, golongan, status aktif/non-aktif
- **Rekomendasi Tindakan**: Saran otomatis berdasarkan status expire

### 💰 Data Piutang (`piutang.php`)
- **Periode Default**: Menampilkan data 3 bulan terakhir untuk performa optimal
- **Informasi Lengkap**: 
  - Nota piutang dan tanggal
  - Data pasien (nama, no RM)
  - Detail obat dengan harga dan batch
  - Penanggung jawab dari tabel penjab
- **Filter Data**: Jenis jual, penanggung jawab, rentang tanggal, pencarian
- **Detail Obat**: Nama obat, jumlah, harga, total, no batch, aturan pakai
- **Quick Navigation**: 3 bulan terakhir, 1 bulan terakhir, bulan ini

### 📊 Laporan & Export
- **Multiple Format**: Export ke Excel (.xls) dan CSV (.csv)
- **Filter Preserved**: Export sesuai dengan filter yang diterapkan
- **Statistik Lengkap**: Ringkasan data dengan persentase dan rekomendasi
- **Professional Layout**: Header, footer, dan styling yang rapi untuk laporan
- **Real-time Data**: Data selalu update sesuai filter dan periode

### 🎨 Antarmuka Pengguna
- **Modern UI**: Desain responsive dengan Bootstrap 5
- **Dark Theme Headers**: Professional appearance
- **Interactive Elements**: Hover effects, animations, tooltips
- **Mobile-Friendly**: Optimized untuk semua device
- **Real-time Clock**: Display waktu real-time di login page
- **Animated Background**: Gradient background dengan floating shapes
- **Color-Coded Status**: Visual indicators untuk berbagai status

## 🗂️ Struktur File

```
sistem-obat-rscm/
├── index.php                    # Halaman login dengan UI modern
├── obat.php                     # Dashboard resep obat (tampilan harian)
├── data_obat.php               # Data obat & monitoring expire
├── piutang.php                 # Data piutang (3 bulan terakhir)
├── conf.php                    # Konfigurasi database dan fungsi utilitas
├── session_config.php          # Konfigurasi session dan security
├── export_resep.php           # Export Excel untuk data resep
├── export_resep_simple.php    # Export CSV untuk data resep
├── export_obat.php            # Export Excel untuk data obat
├── export_obat_simple.php     # Export CSV untuk data obat
├── export_piutang_simple.php  # Export CSV untuk data piutang
├── mysql_config_fix.php       # Tool untuk fix MySQL strict mode
└── README.md                  # Dokumentasi ini
```

## ⚙️ Konfigurasi Sistem

### Database Configuration
```php
$db_hostname = "localhost";
$db_username = "root"; 
$db_password = "";
$db_name = "sik";
```

### Login Credentials
- **Username**: `lalaapoteker`
- **Password**: `apotekerrscm`
- **User Type**: Apoteker


## 🔧 Fungsi Utama Sistem

### Fungsi Database (`conf.php`)
- `bukakoneksi()` - Membuka koneksi database dengan error handling
- `cleankar()` - Sanitasi input untuk keamanan SQL injection
- `validTeks()` - Validasi dan pembersihan teks input
- `validangka()` - Validasi input angka dengan type checking
- `encrypt_decrypt()` - Enkripsi/dekripsi data sensitive
- `formatDuit()` - Format mata uang Rupiah dengan pemisah ribuan
- `konversiTanggal()` - Konversi format tanggal Indonesia

### Fungsi Resep (`obat.php`)
- `getResepData()` - Mengambil data resep dengan filter kompleks
- `getDetailObat()` - Detail obat dalam resep dengan join table
- `getTotalResep()` - Menghitung total nilai resep
- `getKategoriObat()` - Daftar kategori obat untuk filter
- `getGolonganObat()` - Daftar golongan obat untuk filter

### Fungsi Monitoring Expire (`data_obat.php`)
- `getDataObat()` - Data obat dengan status expire calculation
- `getExpireStatistics()` - Statistik expire dengan persentase
- `checkExpireStatus()` - Pengecekan status expire real-time

### Fungsi Piutang (`piutang.php`)
- `getPiutangData()` - Data piutang dengan join penanggung jawab
- `getDetailPiutang()` - Detail obat dalam piutang
- `getPenanggungJawab()` - Data dari tabel penjab untuk filter

## 🎯 Fitur Keamanan

### Input Validation
- SQL injection protection menggunakan `mysqli_real_escape_string`
- Validasi tipe data dan format input dengan regex
- Sanitasi karakter berbahaya dan special characters
- XSS protection dengan `htmlspecialchars`

### Session Management
- Session timeout otomatis (8 jam)
- HTTP-only cookies untuk keamanan
- Session regeneration untuk mencegah session fixation
- Secure session configuration

### Access Control
- Login wajib untuk mengakses semua halaman sistem
- Role-based access (Apoteker) dengan permission check
- Logout functionality dengan complete session cleanup
- Auto-redirect untuk unauthorized access

### MySQL Security
- Prepared statements untuk query kompleks
- MySQL strict mode configuration
- Connection timeout dan error handling
- Database connection pooling

## 📱 Responsive Design

Sistem dirancang mobile-first dengan:
- **Bootstrap 5 Framework**: Grid system yang flexible
- **Touch-Friendly Interface**: Button size dan spacing optimal
- **Adaptive Navigation**: Menu yang responsive di semua device
- **Flexible Tables**: Horizontal scroll untuk tabel besar
- **Modern CSS**: Flexbox dan CSS Grid untuk layout

## 🔍 Filter & Pencarian

### Filter Tersedia
1. **Status Rawat**: Rawat Jalan/Rawat Inap
2. **Kategori Obat**: Berdasarkan klasifikasi farmasi
3. **Golongan Obat**: Bebas, Keras, Narkotika, Psikotropika
4. **Status Expire**: Expired, akan expire, valid, tanpa expire
5. **Rentang Tanggal**: Custom date range dengan quick buttons
6. **Pencarian Teks**: Multi-field search (nama, kode, dokter)
7. **Penanggung Jawab**: Filter berdasarkan asuransi/penjab

### Export Data
- **Format Excel (.xls)**: Dengan styling dan formula
- **Format CSV (.csv)**: Compatible dengan semua spreadsheet software
- **Data Filtered**: Export sesuai filter yang diterapkan
- **Comprehensive Report**: Header, data, statistik, footer lengkap
- **Multiple Options**: Dropdown selection untuk format export

## 🎨 Kustomisasi UI

### Color Scheme
```css
--primary-color: #2563eb      /* Biru primary untuk header */
--secondary-color: #1e40af    /* Biru secondary untuk accent */
--success-color: #10b981      /* Hijau untuk status positif */
--warning-color: #f59e0b      /* Kuning untuk peringatan */
--error-color: #ef4444        /* Merah untuk error/expired */
--dark-color: #1f2937         /* Dark untuk text */
--light-color: #f8fafc        /* Light untuk background */
```

### Badge System
- **Rawat Jalan**: Badge hijau dengan icon dokter
- **Rawat Inap**: Badge merah dengan icon tempat tidur  
- **Golongan Obat**: Color-coded berdasarkan tingkat risiko
- **Status Expire**: Animated badges dengan visual warning
- **Status Tempo**: Color-coded dengan countdown timer

### Animation System
- **Expire Alert**: Blinking animation untuk obat expired
- **Tempo Warning**: Pulse animation untuk jatuh tempo
- **Hover Effects**: Smooth transitions untuk interactive elements
- **Loading States**: Spinner dan skeleton loading
- **Page Transitions**: Smooth page navigation

## 🔄 Maintenance & Monitoring

### Auto-Refresh
- **Halaman Resep**: Refresh setiap 5 menit
- **Halaman Expire**: Refresh setiap 10 menit untuk monitoring
- **Halaman Piutang**: Refresh setiap 10 menit
- **Session Keepalive**: Untuk user yang aktif
- **Smart Refresh**: Pause jika ada filter aktif

### Logging & Monitoring
- **Error Logging**: PHP error tracking untuk debugging
- **Session Activity**: Login/logout tracking
- **Database Query**: Performance monitoring
- **Export Activity**: Track download dan usage
- **Browser Console**: Real-time alerts dan notifications

### Performance Optimization
- **Default Daily View**: Menampilkan data hari ini untuk performa optimal
- **Pagination**: Untuk dataset besar (future enhancement)
- **Query Optimization**: Indexed columns dan efficient joins
- **Cache Strategy**: Session-based caching untuk dropdown data
- **Asset Minification**: Compressed CSS dan JS

## 👨‍💻 Developer Information

**Author**: Yan Eka Putra  
**Organization**: RSCM - Rumah Sakit Cahaya Medika  
**Version**: v2.0 - Enhanced with Expire Monitoring & Piutang Management  
**Based on**: Kemenkes Report Standards  
**License**: Proprietary - Internal Hospital Use Only  
**Framework**: Pure PHP dengan Bootstrap 5  
**Database**: MySQL/MariaDB dengan strict mode support  

### Update History
- **v1.0**: Basic resep obat management
- **v1.5**: Added expire monitoring dan alerts
- **v2.0**: Added piutang management, enhanced UI, export functionality

## 🆘 Troubleshooting

### Masalah Umum

1. **Error Koneksi Database**
   ```
   Solution:
   - Periksa konfigurasi di conf.php
   - Pastikan MySQL service berjalan
   - Verifikasi credentials database
   - Test koneksi manual
   ```

2. **Session Error/Timeout**
   ```
   Solution:
   - Clear browser cache dan cookies
   - Restart web server
   - Periksa konfigurasi session_config.php
   - Check session directory permissions
   ```

3. **MySQL Strict Mode Error**
   ```
   Solution:
   - Jalankan mysql_config_fix.php
   - Update my.cnf/my.ini configuration
   - Set appropriate sql_mode
   - Restart MySQL service
   ```

4. **Export Excel Error**
   ```
   Solution:
   - Gunakan export CSV sebagai alternatif
   - Check file permissions
   - Verify PHP extensions (mbstring)
   - Clear browser download cache
   ```

5. **Permission Denied**
   ```
   Solution:
   - Set permission file PHP (644)
   - Pastikan direktori writable untuk session
   - Check ownership file dan direktori
   - Verify Apache/Nginx user permissions
   ```

### Debug Mode
Untuk debugging, aktifkan error reporting di `conf.php`:
```php
// Tambahkan di awal file untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
```

### Log Files
- **PHP Error Log**: Check server error logs
- **Session Issues**: Monitor PHP session directory
- **Database Errors**: Enable MySQL query logging
- **Application Log**: Custom logging di file terpisah

## 📞 Support & Maintenance

### Internal Support
- **IT Support**: Hubungi departemen IT RSCM untuk masalah teknis
- **Database Issues**: DBA Team untuk optimasi dan backup
- **UI/UX Issues**: Development Team untuk enhancement
- **Training**: User manual dan training session tersedia

### Backup & Recovery
- **Database Backup**: Automated daily backup recommended
- **File Backup**: Regular backup untuk source code
- **Configuration Backup**: Backup untuk conf.php dan settings
- **Recovery Plan**: Documented disaster recovery procedure

### Performance Monitoring
- **Server Resources**: Monitor CPU, RAM, storage usage
- **Database Performance**: Query optimization dan indexing
- **User Activity**: Monitor concurrent users dan load
- **Response Time**: Track page load dan query execution time

---

© 2025 RSCM - Rumah Sakit Cahaya Medika. All rights reserved.

**Sistem Informasi Obat v2.0** - Enhanced dengan monitoring expire dan manajemen piutang yang komprehensif.