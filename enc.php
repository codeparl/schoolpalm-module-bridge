<?php

require __DIR__ . '/vendor/autoload.php';

use SchoolPalm\ModuleBridge\Support\EncryptedConfig;

// Paths
$supportPath = __DIR__ . '/src/Support';
$configPath  = $supportPath . '/config';
$rawFile     = $configPath . '/academic_levels.json'; 

// Exit if raw file does not exist
if (!file_exists($rawFile)) {
    echo "Raw file not found: {$rawFile}\n";
    exit(1);
}

// Encrypt and clean
$success = EncryptedConfig::encryptAndClean($configPath, $rawFile, 'academic_levels');

if ($success) {
    echo "File encrypted and original removed successfully.\n";
} else {
    echo "Failed to encrypt or remove the raw file.\n";
}
