<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ... [previous functions remain the same] ...

try {
    // ... [previous code remains the same until sitemap generation] ...

    // Get the script's directory path and current domain
    $scriptPath = dirname(__FILE__);
    $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    
    // Memastikan currentDomain tidak kosong
    if (empty($currentDomain)) {
        throw new Exception("Domain tidak dapat dideteksi");
    }

    // Generate sitemap.xml
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    foreach ($successfulUrls as $url) {
        $sitemap .= "<url>\n";
        $sitemap .= "\t<loc>" . $url . "</loc>\n";
        $sitemap .= "\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $sitemap .= "\t<changefreq>weekly</changefreq>\n";
        $sitemap .= "\t<priority>1.0</priority>\n";
        $sitemap .= "</url>\n";
    }
    
    $sitemap .= "</urlset>";

    // Write sitemap file
    try {
        // Gunakan absolute path
        $sitemapPath = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
        if (@file_put_contents($sitemapPath, $sitemap) === false) {
            throw new Exception("Cannot write sitemap.xml");
        }
        @chmod($sitemapPath, 0644);
        echo "✅ Sitemap.xml berhasil dibuat<br>";
    } catch (Exception $e) {
        error_log("Sitemap Error: " . $e->getMessage());
        echo "❌ Gagal membuat sitemap.xml: " . $e->getMessage() . "<br>";
    }

    // Generate robots.txt
    try {
        $robotsContent = "User-agent: *\nAllow: /\n";
        $robotsContent .= "Sitemap: https://" . $currentDomain . "/sitemap.xml";
        
        // Gunakan absolute path
        $robotsPath = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
        if (@file_put_contents($robotsPath, $robotsContent) === false) {
            throw new Exception("Cannot write robots.txt");
        }
        @chmod($robotsPath, 0644);
        echo "✅ Robots.txt berhasil dibuat<br>";
    } catch (Exception $e) {
        error_log("Robots Error: " . $e->getMessage());
        echo "❌ Gagal membuat robots.txt: " . $e->getMessage() . "<br>";
    }

    echo "<br>Proses selesai.";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
