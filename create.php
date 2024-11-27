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

function replacePlaceholders($content, $replacements) {
    return str_replace(
        array_keys($replacements),
        array_values($replacements),
        $content
    );
}

$filename = $gas_txt;
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$artikelFile = $artikel_txt;

$titles = [];
$descriptions = [];
$articles = [];

try {
    // [Previous code remains exactly the same until the loop ends]
    
    // After the loop ends, create sitemap and robots
    
    // Get the script's directory path
    $scriptPath = dirname(__FILE__);
    
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
        $sitemapPath = $scriptPath . '/sitemap.xml';
        if (file_put_contents($sitemapPath, $sitemap) === false) {
            throw new Exception("Cannot write sitemap.xml");
        }
        chmod($sitemapPath, 0644);
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
    } catch (Exception $e) {
        error_log("Sitemap Error: " . $e->getMessage());
        echo "<br>❌ Gagal membuat sitemap.xml<br>";
    }

    // Generate robots.txt
    try {
        $robotsContent = "User-agent: *\n";
        $robotsContent .= "Sitemap: " . ensureTrailingSlash("https://" . $currentDomain) . "sitemap.xml";
        
        $robotsPath = $scriptPath . '/robots.txt';
        if (file_put_contents($robotsPath, $robotsContent) === false) {
            throw new Exception("Cannot write robots.txt");
        }
        chmod($robotsPath, 0644);
        echo "✅ Robots.txt berhasil dibuat<br>";
    } catch (Exception $e) {
        error_log("Robots Error: " . $e->getMessage());
        echo "❌ Gagal membuat robots.txt<br>";
    }

    echo "<br>Proses selesai.";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
