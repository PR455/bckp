<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi baru untuk mengecek perubahan file
function checkFileChanges($filePath) {
    static $fileStates = [];
    $currentState = md5_file($filePath);
    
    if (!isset($fileStates[$filePath])) {
        $fileStates[$filePath] = $currentState;
        return true;
    }
    
    if ($fileStates[$filePath] !== $currentState) {
        $fileStates[$filePath] = $currentState;
        return true;
    }
    
    return false;
}

// Fungsi baru untuk membersihkan cache
function clearFileCache() {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    clearstatcache(true);
}

// Fungsi asli - tidak diubah
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

// Fungsi asli - tidak diubah
function ensureTrailingSlash($url) {
    return rtrim($url, '/') . '/';
}

// Fungsi asli - tidak diubah
function formatArticle($article) {
    $article = trim($article);
    return '<p>' . $article . '</p>';
}

// Fungsi asli - tidak diubah
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

$filename = 'pk.txt';  // Ganti gas.txt menjadi pk.txt
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$artikelFile = $artikel_txt;

try {
    // Tambahkan pengecekan perubahan file
    $filesChanged = false;
    $filesToCheck = [
        $filename,
        $templateFile,
        $titlesFile,
        $descriptionsFile,
        $artikelFile
    ];
    
    foreach ($filesToCheck as $file) {
        if (checkFileChanges($file)) {
            $filesChanged = true;
            break;
        }
    }
    
    if ($filesChanged) {
        clearFileCache();
    }

    // Kode asli dimulai dari sini - tidak diubah
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

        $currentTitle = isset($titles[$titleIndex]) ? processContent($titles[$titleIndex], $replacements) : processContent($titles[0], $replacements);
        $currentDescription = isset($descriptions[$descriptionIndex]) ? processContent($descriptions[$descriptionIndex], $replacements) : processContent($descriptions[0], $replacements);
        $currentArticle = isset($articles[$articleIndex]) ? formatArticle(processContent($articles[$articleIndex], $replacements)) : formatArticle(processContent($articles[0], $replacements));

        $replacements['TITLE'] = $currentTitle;
        $replacements['DESCRIPTION'] = $currentDescription;
        $replacements['ARTICLE'] = $currentArticle;
        $replacements['ARTICLE_CONTENT'] = $currentArticle;

        if (!is_dir($folderPath) && !mkdir($folderPath, 0755, true)) {
            continue;
        }

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

    // Generate .htaccess - kode asli
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

    // Tambahan kode untuk .htaccess
    $htaccessPath = "$mainDir/.htaccess";
    file_put_contents($htaccessPath, $htaccess);

    // Output final
    echo "<br><br><h3>Link Berhasil Dibuat</h3><br>";
    echo implode('<br>', $successfulUrls);
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage();
}
?>
