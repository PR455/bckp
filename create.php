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

// Fungsi baru untuk mengganti semua placeholder
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
    // Baca semua file content
    $titleContent = getFileContent($titlesFile);
    $descriptionContent = getFileContent($descriptionsFile);
    $articleContent = getFileContent($artikelFile);
    $templateContent = getFileContent($templateFile);
    $keywordsContent = getFileContent($filename);

    // Split contents
    $titles = array_filter(array_map('trim', explode("\n", $titleContent)));
    $descriptions = array_filter(array_map('trim', explode("\n", $descriptionContent)));
    $articles = array_filter(array_map('trim', explode("\n", $articleContent)));
    $lines = array_filter(array_map('trim', explode("\n", $keywordsContent)));

    // Validasi contents
    if (empty($titles)) throw new Exception("File title kosong atau tidak valid");
    if (empty($descriptions)) throw new Exception("File description kosong atau tidak valid");
    if (empty($articles)) throw new Exception("File artikel kosong atau tidak valid");

    // Setup directory
    if (!is_dir($mainDir) && !mkdir($mainDir, 0755, true)) {
        throw new Exception("Gagal membuat direktori '$mainDir'");
    }

    $currentDomain = $_SERVER['HTTP_HOST'];
    $titleIndex = $descriptionIndex = $articleIndex = 0;

    foreach ($lines as $line) {
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        // Setup URLs
        $folderURL = ensureTrailingSlash("https://$currentDomain/$folderName");
        $ampURL = ensureTrailingSlash("https://ampmasal.xyz/$folderName");

        // Get current content items
        $currentTitle = isset($titles[$titleIndex]) ? $titles[$titleIndex] : $titles[0];
        $currentDescription = isset($descriptions[$descriptionIndex]) ? $descriptions[$descriptionIndex] : $descriptions[0];
        $currentArticle = isset($articles[$articleIndex]) ? formatArticle($articles[$articleIndex]) : formatArticle($articles[0]);

        // Setup replacements array
        $replacements = [
            '{{BRAND_NAME}}' => strtoupper($folderName),
            '{{URL_PATH}}' => $folderURL,
            '{{AMP_URL}}' => $ampURL,
            '{{BRANDS_NAME}}' => strtolower($folderName),
            '{{TITLE}}' => $currentTitle,
            '{{DESCRIPTION}}' => $currentDescription,
            '{{ARTICLE}}' => $currentArticle,
            '{{ARTICLE_CONTENT}}' => $currentArticle // Support both placeholders
        ];

        // Process title, description, and article with placeholders
        $processedTitle = replacePlaceholders($currentTitle, $replacements);
        $processedDescription = replacePlaceholders($currentDescription, $replacements);
        $processedArticle = replacePlaceholders($currentArticle, $replacements);

        // Update replacements with processed content
        $replacements['{{TITLE}}'] = $processedTitle;
        $replacements['{{DESCRIPTION}}'] = $processedDescription;
        $replacements['{{ARTICLE}}'] = $processedArticle;
        $replacements['{{ARTICLE_CONTENT}}'] = $processedArticle;

        // Create folder
        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

        // Process template with final replacements
        $customContent = replacePlaceholders($templateContent, $replacements);

        // Write file
        $indexPath = "$folderPath/index.php";
        if (file_put_contents($indexPath, $customContent) !== false) {
            echo "🔗 <a href='$folderURL' target='_blank'>$folderURL</a><br>";
            $successfulUrls[] = $folderURL;
            chmod($indexPath, 0644);
        }

        // Update indices
        $titleIndex = ($titleIndex + 1) % count($titles);
        $descriptionIndex = ($descriptionIndex + 1) % count($descriptions);
        $articleIndex = ($articleIndex + 1) % count($articles);
    }

    <?php
    // ... (kode sebelumnya tetap sama sampai bagian generate sitemap) ...
    
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
    
        // Menggunakan document root untuk path absolute
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $sitemapPath = $documentRoot . '/sitemap.xml';
        
        // Cek dan buat sitemap
        if (!@file_put_contents($sitemapPath, $sitemap)) {
            error_log("Gagal menulis sitemap.xml ke: " . $sitemapPath);
            echo "<br>❌ Gagal membuat sitemap.xml - cek error log<br>";
        } else {
            @chmod($sitemapPath, 0644);
            echo "<br>✅ Sitemap.xml berhasil dibuat<br>";
        }
    
        // Generate robots.txt
        $robotsContent = "User-agent: *\n";
        $robotsContent .= "Sitemap: " . ensureTrailingSlash("https://" . $currentDomain) . "sitemap.xml";
    
        // Gunakan document root untuk robots.txt juga
        $robotsPath = $documentRoot . '/robots.txt';
        
        // Cek dan buat robots.txt
        if (!@file_put_contents($robotsPath, $robotsContent)) {
            error_log("Gagal menulis robots.txt ke: " . $robotsPath);
            echo "❌ Gagal membuat robots.txt - cek error log<br>";
        } else {
            @chmod($robotsPath, 0644);
            echo "✅ Robots.txt berhasil dibuat<br>";
        }
    
        echo "<br>Proses selesai.";
    
    } catch (Exception $e) {
        echo "<h2>Error:</h2>";
        echo $e->getMessage();
        error_log("Create Folders Error: " . $e->getMessage());
    }
    ?>
