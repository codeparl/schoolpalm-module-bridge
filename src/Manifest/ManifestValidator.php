<?php

namespace SchoolPalm\ModuleBridge\Manifest;

use Illuminate\Console\Command;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SchoolPalm\ModuleBridge\Support\Helper;

class ManifestValidator
{
    public static function validate(array $manifest, $command = null): array
{
    $schemaPath = Helper::schemaPath();

    if (!file_exists($schemaPath)) {
        throw new RuntimeException('Module manifest schema not found at: ' . $schemaPath);
    }

    $schemaJson = Helper::loadJson($schemaPath);

    if (!$schemaJson) {
        throw new RuntimeException('Invalid schema JSON');
    }

    ManifestFactory::normalizeJson($manifest, $schemaJson);

    $validator = new Validator();
    $validator->setMaxErrors(100);

    $validator->resolver()->registerFile(
        'https://schoolpalm.dev/schemas/module-manifest.json',
        $schemaPath
    );

    // Convert array to object
    $data = json_decode(json_encode($manifest));

    /** @var ValidationResult $result */
    $result = $validator->validate(
        $data,
        'https://schoolpalm.dev/schemas/module-manifest.json'
    );

    $formatter = new ErrorFormatter();

    if ($result->isValid()) {
        return [];
    }

    /**
     * Custom formatter with proper "required fields" handling
     */
    $custom = function (ValidationError $error) {

        $dataInfo = $error->data();
        $schemaInfo = $error->schema()->info()->data();
        $keyword = $error->keyword();

        $message = $error->message();

        // ----------------------------
        // FIX: REQUIRED FIELD MISSING
        // ----------------------------
        if ($keyword === 'required') {

            $missing = $error->args()['missing'] ?? null;

            if (is_array($missing)) {
                $missing = implode(', ', $missing);
            }

            $message = "Missing required field(s): {$missing}";
        }

        // ----------------------------
        // OPTIONAL: schema custom messages
        // ----------------------------
        $customMessage = isset($schemaInfo->{'$error'}->{$keyword})
            ? $schemaInfo->{'$error'}->{$keyword}
            : null;

        return [
            'path' => implode('.', $dataInfo->fullPath()) ?: '',
            'message' => $customMessage ?: $message,
            'keyword' => $keyword,
        ];
    };

    $errors = $formatter->format($result->error(), true, $custom);

    // ----------------------------
    // OUTPUT HANDLING
    // ----------------------------
    if ($command instanceof \Illuminate\Console\Command) {
        $command->error("❌ Manifest validation failed.");
    } 

    $errorBug = [];

    foreach ($errors as $errorGroup) {
        foreach ($errorGroup as $error) {

            $path = $error['path'] ?? '';
            $message = $error['message'] ?? 'Unknown error';
            $errorBug[] = "{$path}: {$message}";
        }
    }

    return $errorBug;
}
}
