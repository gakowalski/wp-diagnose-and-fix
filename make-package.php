<?php

// Script should run only in CLI mode
if (php_sapi_name() !== 'cli') {
    die('This script can only be run in CLI mode.');
}

function zipDirectory($source, $destination, $exclude = []) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        die('Rozszerzenie ZIP nie jest włączone lub ścieżka źródłowa nie istnieje.');
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        die('Nie można utworzyć pliku ZIP.');
    }

    $source = realpath($source);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($source) + 1);
        
        // POPRAWKA: Konwertuj separatory ścieżek na Unix (/)
        // Zamień wszystkie \ na / dla kompatybilności między systemami
        $relativePath = str_replace('\\', '/', $relativePath);

        // Sprawdź, czy plik lub folder znajduje się na liście wykluczeń
        $shouldExclude = false;
        foreach ($exclude as $excludedItem) {
            // Normalizuj również wzorce wykluczeń
            $normalizedExclude = str_replace('\\', '/', $excludedItem);
            if (strpos($relativePath, $normalizedExclude) === 0) {
                $shouldExclude = true;
                break;
            }
        }
        
        if ($shouldExclude) {
            continue;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
            echo "Dodano folder: $relativePath\n";
        } else {
            $zip->addFile($filePath, $relativePath);
            echo "Dodano plik: $relativePath\n";
        }
    }

    $result = $zip->close();
    if ($result) {
        echo "\n✅ Plik ZIP został pomyślnie utworzony: $destination\n";
        echo "📦 Rozmiar: " . formatBytes(filesize($destination)) . "\n";
        echo "📁 Liczba plików: " . $zip->numFiles . "\n";
    } else {
        echo "\n❌ Błąd podczas tworzenia pliku ZIP!\n";
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Ścieżki
$currentDirectory = __DIR__;
$zipFileName = basename($currentDirectory) . '.zip';
$destination = dirname($currentDirectory) . DIRECTORY_SEPARATOR . $zipFileName;

echo "🔧 WordPress Diagnostics - Package Creator\n";
echo "==========================================\n";
echo "📂 Źródło: $currentDirectory\n";
echo "📦 Cel: $destination\n\n";

// Foldery lub pliki do wykluczenia
$exclude = [
    '.git', 
    '.gitignore', 
    '.vscode',
    'node_modules',
    'vendor',
    'composer.lock',
    'package-lock.json',
    'make-package.php', 
    'publish.php', 
    'secrets.php',
    'README.md',
    'DEPLOYMENT_CHECKLIST.md',
    'PLUGIN_README.md',
    'index.php',  // Stary monolityczny plik - używamy wp-diagnostics.php
    'css',        // Stary folder css - używamy assets/css
    'js',         // Stary folder js - używamy assets/js
    'logs',
    'tmp',
    'temp',
    '.DS_Store',
    'Thumbs.db',
    '*.log',
    '*.tmp'
];

echo "🚫 Wykluczenia:\n";
foreach ($exclude as $item) {
    echo "   - $item\n";
}
echo "\n📋 Przetwarzanie plików:\n";

zipDirectory($currentDirectory, $destination, $exclude);

echo "\n🎉 Zakończono!\n";