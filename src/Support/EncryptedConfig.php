<?php

namespace SchoolPalm\ModuleBridge\Support;

final class EncryptedConfig
{
    // Encryption key (must be the same for SDK and SchoolPalm)
    private const KEY = 'schoolpalm-secret-key-32chars!'; 

    // Cipher method
    private const CIPHER = 'AES-256-CBC';

    // Base folder for encrypted config files
    private static string $basePath = '';

    /**
     * Initialize the base path for encrypted config files.
     * Typically points to: ModuleBridge/Support/config/
     */
    public static function init(?string $basePath = null): void
    {
        self::$basePath = $basePath 
            ?? __DIR__ . DIRECTORY_SEPARATOR . 'config';
        
        if (!is_dir(self::$basePath)) {
            mkdir(self::$basePath, 0777, true);
        }
    }

    /**
     * Resolve the full file path from a key.
     * The filename is hashed to obfuscate it.
     *
     * @param string $key Logical config name (e.g., 'academic_levels')
     * @return string
     */
   public static function resolveFilePath(string $key): string
{
    $hashedFile = md5($key) . '.enc';
    return rtrim(self::$basePath, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $hashedFile;
}


    /**
     * Read and decrypt an encrypted file.
     *
     * @param string $key Logical config name
     * @return array<mixed>
     */
    public static function read(string $key): array
    {
        $filePath = self::resolveFilePath($key);

        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if (!$content) {
            return [];
        }

        $iv = substr($content, 0, 16);
        $ciphertext = substr($content, 16);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, self::KEY, OPENSSL_RAW_DATA, $iv);
        $data = json_decode($decrypted, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Encrypt and write data to a file.
     *
     * Internal use only (SchoolPalm). Vendors never call this.
     *
     * @param string $key Logical config name
     * @param array $data
     * @return bool
     */
    public static function write(string $key, array $data): bool
    {
        $filePath = self::resolveFilePath($key);

        // Ensure the directory exists
        $dir = dirname($filePath); 
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                return false; 
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!$json) {
            return false;
        }

        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($json, self::CIPHER, self::KEY, OPENSSL_RAW_DATA, $iv);

        if (!$ciphertext) {
            return false;
        }

        $content = $iv . $ciphertext;
        return file_put_contents($filePath, $content) !== false;
    }
}
