<?php
class ContentManager {
    private $baseDir;

    public function __construct() {
        $this->baseDir = __DIR__;
    }

    private function getUrlIndex($brand) {
        return crc32($brand) % 100; 
    }

    private function getLine($filename, $index) {
        $filePath = $this->baseDir . '/content/' . $filename;
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filename");
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            throw new Exception("File is empty: $filename");
        }
        return $lines[$index % count($lines)] ?? '';
    }

    private function isValidBrand($brand) {
        $gasFiles = glob($this->baseDir . '/gas/*.txt');
        foreach ($gasFiles as $file) {
            $brands = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (in_array($brand, $brands)) {
                return true;
            }
        }
        return false;
    }

    private function formatBrandName($brand) {
        return str_replace('-', ' ', $brand);
    }

    private function formatBrandUrl($brand) {
        return strtolower(str_replace(' ', '-', $this->formatBrandName($brand)));
    }

    public function getBrandContent($brand) {
        if (!$this->isValidBrand($brand)) {
            return false;
        }

        $formattedBrand = $this->formatBrandName($brand);
        $brandUrl = $this->formatBrandUrl($brand);
        $index = $this->getUrlIndex($brand);

        return [
            'title' => str_replace('{brand_name}', $formattedBrand, $this->getLine('title.txt', $index)),
            'deskripsi' => str_replace('{brand_name}', $formattedBrand, $this->getLine('deskripsi.txt', $index)),
            'artikel' => $this->getLine('artikel.txt', $index),
            'urlgambar' => str_replace('{brand_name}', $formattedBrand, $this->getLine('urlgambar.txt', $index)),
            'urlpath' => "https://www.theuerkaufstails.com/{$brand}/",
            'brandname' => $formattedBrand,
            'brandurl' => $brandUrl
        ];
    }

    public function replacePlaceholders($template, $data) {
        $replacements = [
            '{title}' => $data['title'],
            '{deskripsi}' => $data['deskripsi'],
            '{artikel}' => $data['artikel'],
            '{urlgambar}' => $data['urlgambar'],
            '{urlpath}' => $data['urlpath'],
            '{brand_name}' => $data['brandname'],
            '{brands_name}' => strtoupper($data['brandname']),
            '{brand_url}' => $data['brandurl']
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}

// Skrip utama
try {
    $brand = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $manager = new ContentManager();

    if ($content = $manager->getBrandContent($brand)) {
        if (file_exists(__DIR__ . '/template.php')) {
            ob_start();
            include(__DIR__ . '/template.php');
            echo $manager->replacePlaceholders(ob_get_clean(), $content);
        } else {
            throw new Exception('Template not found');
        }
    } else {
        throw new Exception('HTTP/1.0 404 Not Found');
    }
} catch (Exception $e) {
    header("HTTP/1.0 404 Not Found");
    echo "Error: " . $e->getMessage();
}
