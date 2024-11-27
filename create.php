<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getFileContent($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File tidak ditemukan di: $filePath");
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Gagal membaca konten file: $filePath");
    }
    return $content;
}

function ensureTrailingSlash($url) {
    return rtrim($url, '/') . '/';
}

function formatArticle($article) {
    $article = trim($article);
    return '<p>' . $article . '</p>';
}

$filename = $gas_txt;
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$artikelFile = $artikel_txt;

try {
    // Baca file titles
    $titleContent = getFileContent($titlesFile);
    $titles = array_filter(array_map('trim', explode("\n", $titleContent)));
    
    if (empty($titles)) {
        throw new Exception("File title kosong atau tidak valid");
    }
    
    // Baca file descriptions
    $descriptionContent = getFileContent($descriptionsFile);
    $descriptions = array_filter(array_map('trim', explode("\n", $descriptionContent)));
    
    if (empty($descriptions)) {
        throw new Exception("File description kosong atau tidak valid");
    }

    // Baca file artikel jika ada
    if (isset($artikelFile)) {
        $articleContent = getFileContent($artikelFile);
        $articles = array_filter(array_map('trim', explode("\n", $articleContent)));
        if (empty($articles)) {
            throw new Exception("File artikel kosong atau tidak valid");
        }
    }

    // [Rest of the code remains the same until sitemap generation]

    // Write sitemap file
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

    $sitemapPath = dirname(__FILE__) . '/sitemap.xml';
    if (file_put_contents($sitemapPath, $sitemap) !== false) {
        chmod($sitemapPath, 0644);
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
    }

    // Generate robots.txt
    $currentDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $robotsContent = "User-agent: *\nAllow: /\n";
    $robotsContent .= "Sitemap: https://" . $currentDomain . "/sitemap.xml";

    $robotsPath = dirname(__FILE__) . '/robots.txt';
    if (file_put_contents($robotsPath, $robotsContent) !== false) {
        chmod($robotsPath, 0644);
        echo "✅ Robots.txt berhasil dibuat<br>";
    }

    echo "<br>Proses selesai.";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
