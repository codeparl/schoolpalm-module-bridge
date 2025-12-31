<?php

use PHPUnit\Framework\TestCase;
use SchoolPalm\ModuleBridge\Support\EncryptedConfig;

class EncryptedConfigTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a dedicated fixtures folder
        $this->fixturesDir = __DIR__ . '/fixtures';
        if (!is_dir($this->fixturesDir)) {
            mkdir($this->fixturesDir, 0777, true);
        }

        EncryptedConfig::init($this->fixturesDir);
    }

    protected function tearDown(): void
    {
        // Cleanup fixtures folder after each test
        $files = glob($this->fixturesDir . '/*.enc');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private function tempFile(string $name = 'test.enc'): string
    {
        return  $name;
    }

    public function testWriteAndRead(): void
    {
        $file = $this->tempFile('write_read.enc');
        $data = [
            'academic_levels' => [
                1 => ['label' => 'Early Childhood Education', 'code' => 'ECE'],
                2 => ['label' => 'Primary Education', 'code' => 'PRI'],
            ]
        ];

        $written = EncryptedConfig::write($file, $data);
        $this->assertTrue($written, 'Encrypted file should be written successfully');

        $readData = EncryptedConfig::read($file);
        $this->assertIsArray($readData, 'Read data should be an array');
        $this->assertEquals($data, $readData, 'Read data should match the written data');
    }

    public function testReadNonExistingFileReturnsEmptyArray(): void
    {
        $file = $this->tempFile('non_existing.enc');
        $readData = EncryptedConfig::read($file);

        $this->assertEquals([], $readData, 'Reading non-existing file should return empty array');
    }

    public function testReadCorruptedFileReturnsEmptyArray(): void
    {
        $file = $this->tempFile('corrupted.enc');
        file_put_contents($file, 'corrupted content');

        $readData = EncryptedConfig::read($file);
        
        $this->assertEquals([], $readData, 'Reading corrupted file should return empty array');
    }



}
