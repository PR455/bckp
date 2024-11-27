<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
clearstatcache();

function getFileContent($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File tidak ditemukan di: $filePath");
    }
    // Clear stat cache for specific file
    clearstatcache(true, $filePath);
    
    // Add file modification time check
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

// Function to check if file needs update
function needsRefresh($filePath) {
    $cacheFile = sys_get_temp_dir() . '/cache/' . md5($filePath) . '.meta';
    
    if (!file_exists($cacheFile)) {
        return true;
    }
    
    $cachedMeta = json_decode(file_get_contents($cacheFile), true);
    $currentMeta = [
        'mtime' => filemtime($filePath),
        'size' => filesize($filePath)
    ];
    
    return $cachedMeta['mtime'] !== $currentMeta['mtime'] || 
           $cachedMeta['size'] !== $currentMeta['size'];
}

// Function to update file metadata cache
function updateFileCache($filePath) {
    $cacheDir = sys_get_temp_dir() . '/cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $meta = [
        'mtime' => filemtime($filePath),
        'size' => filesize($filePath)
    ];
    
    file_put_contents(
        $cacheDir . md5($filePath) . '.meta',
        json_encode($meta)
    );
}

$filename = $gas_txt;
$templateFile = $template_php;
$mainDir = "gas";
$successfulUrls = [];
$titlesFile = $title_txt;
$descriptionsFile = $descriptions_txt;
$artikelFile = $artikel_txt;

try {
    // Check if any file needs refresh
    $filesToCheck = [$filename, $templateFile, $titlesFile, $descriptionsFile, $artikelFile];
    $needsUpdate = false;
    
    foreach ($filesToCheck as $file) {
        if (needsRefresh($file)) {
            $needsUpdate = true;
            updateFileCache($file);
        }
    }
    
    if (!$needsUpdate && !isset($_GET['force'])) {
        echo "No changes detected in source files. Add ?force=1 to force update.<br>";
    }

    // Rest of your existing code remains the same
    $titleContent = getFileContent($titlesFile);
    $titles = array_filter(array_map('trim', explode("\n", $titleContent)));
    
    if (empty($titles)) {
        throw new Exception("File title kosong atau tidak valid");
    }
    
    // ... (rest of the existing code remains unchanged)
    
    // Add version tracking to generated files
    $versionFile = $mainDir . '/version.txt';
    $newVersion = time();
    file_put_contents($versionFile, $newVersion);
    
    // Add version to sitemap and robots
    $sitemap = str_replace(
        '</urlset>',
        "<!-- Version: $newVersion -->\n</urlset>",
        $sitemap
    );
    
    $robotsContent .= "\n# Version: $newVersion";
    
    // Force update .htaccess with additional cache control
    $htaccess .= "\n\n# Version: $newVersion\n";
    $htaccess .= "# Force cache refresh for PHP files\n";
    $htaccess .= "<FilesMatch \"\.(php)$\">\n";
    $htaccess .= "    Header set Cache-Control \"no-cache, no-store, must-revalidate\"\n";
    $htaccess .= "    Header set Pragma \"no-cache\"\n";
    $htaccess .= "    Header set Expires 0\n";
    $htaccess .= "</FilesMatch>\n";

    if (@file_put_contents($rootPath . '/.htaccess', $htaccess) === false) {
        error_log("Gagal menulis .htaccess ke: " . $rootPath . '/.htaccess');
    } else {
        @chmod($rootPath . '/.htaccess', 0644);
        echo "✅ .htaccess diperbarui dengan pengaturan cache<br>";
    }

    echo "<br>Proses selesai. Version: $newVersion";

} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
    error_log("Create Folders Error: " . $e->getMessage());
}
?>
