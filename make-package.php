<?php

// script should run only in CLI mode
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

        // Sprawdź, czy plik lub folder znajduje się na liście wykluczeń
        foreach ($exclude as $excludedItem) {
            if (strpos($relativePath, $excludedItem) === 0) {
                continue 2;
            }
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
    echo "Plik ZIP został utworzony: $destination";
}

// Ścieżki
$currentDirectory = __DIR__;
$zipFileName = basename($currentDirectory) . '.zip';
$destination = dirname($currentDirectory) . DIRECTORY_SEPARATOR . $zipFileName;

// Foldery lub pliki do wykluczenia
$exclude = [
    '.git', '.gitignore', 
    'make-package.php', 'publish.php', 'secrets.php', 
];

zipDirectory($currentDirectory, $destination, $exclude);