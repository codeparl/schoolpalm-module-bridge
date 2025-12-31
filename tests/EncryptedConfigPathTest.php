<?php

use PHPUnit\Framework\TestCase;
use SchoolPalm\ModuleBridge\Support\EncryptedConfig;

class EncryptedConfigPathTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = __DIR__ . '/fixtures';
        if (!is_dir($this->fixturesDir)) {
            mkdir($this->fixturesDir, 0777, true);
        }

        EncryptedConfig::init($this->fixturesDir);
    }

    public function testBasePathIsSetCorrectly(): void
    {
        $reflection = new ReflectionClass(EncryptedConfig::class);
        $property = $reflection->getProperty('basePath');
        $property->setAccessible(true);

        $basePath = $property->getValue();
        $this->assertEquals(
            $this->fixturesDir,
            $basePath,
            'EncryptedConfig basePath should match the initialized fixtures directory'
        );
    }

public function testResolveFilePath(): void
{
    $key = 'academic_levels';
    $filePath = EncryptedConfig::resolveFilePath($key);

    $expectedFileName = md5($key) . '.enc';
    
    $this->assertStringEndsWith($expectedFileName, $filePath, 
        'Resolved file path should end with hashed key filename');
}
}
