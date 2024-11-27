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

// Fungsi untuk menggantikan placeholders
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
    // [Kode pembacaan file tetap sama...]

    foreach ($lines as $line) {
        $folderName = str_replace(' ', '-', trim($line));
        $folderPath = "$mainDir/$folderName";
        
        $folderURL = ensureTrailingSlash("https://$currentDomain/$folderName");
        $ampURL = ensureTrailingSlash("https://ampmasal.xyz/$folderName");

        // Definisi replacements untuk semua placeholder
        $replacements = [
            '{{BRAND_NAME}}' => strtoupper($folderName),
            '{{URL_PATH}}' => $folderURL,
            '{{AMP_URL}}' => $ampURL,
            '{{BRANDS_NAME}}' => strtolower($folderName)
        ];
        
        // Proses title dengan placeholder
        $title = isset($titles[$titleIndex]) ? replacePlaceholders($titles[$titleIndex], $replacements) : replacePlaceholders($titles[0], $replacements);
        
        // Proses description dengan placeholder
        $description = isset($descriptions[$descriptionIndex]) ? replacePlaceholders($descriptions[$descriptionIndex], $replacements) : replacePlaceholders($descriptions[0], $replacements);
        
        // Proses article dengan placeholder
        $article = isset($articles[$articleIndex]) ? formatArticle(replacePlaceholders($articles[$articleIndex], $replacements)) : formatArticle(replacePlaceholders($articles[0], $replacements));

        // Update replacements dengan konten yang sudah diproses
        $replacements['{{TITLE}}'] = $title;
        $replacements['{{DESCRIPTION}}'] = $description;
        $replacements['{{ARTICLE}}'] = $article;
        $replacements['{{ARTICLE_CONTENT}}'] = $article;

        // Create directory if not exists
        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

        // Proses template dengan semua replacements
        $customContent = replacePlaceholders($templateContent, $replacements);

        // Write index.php
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

    // [Kode .htaccess, sitemap.xml, dan robots.txt tetap sama...]

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
