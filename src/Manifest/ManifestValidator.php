<?php

namespace SchoolPalm\ModuleBridge\Manifest;

use Illuminate\Console\Command;
use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use RuntimeException;
use SchoolPalm\ModuleBridge\Support\Helper;

class ManifestValidator
{
    public static function validate(
        array $manifest,
        $command = null
    ): array {
        $schemaPath = Helper::schemaPath();

        if (!file_exists($schemaPath)) {
            throw new RuntimeException(
                'Module manifest schema not found at: ' . $schemaPath
            );
        }

        $schemaJson = Helper::loadJson($schemaPath);

        if (!$schemaJson) {
            throw new RuntimeException(
                'Invalid schema JSON'
            );
        }

        /*
         * Normalize the manifest in-place.
         */
        ManifestFactory::normalizeJson(
            $manifest,
            $schemaJson
        );

        $validator = new Validator();

        $validator->setMaxErrors(100);

        $validator->resolver()->registerFile(
            'https://schoolpalm.dev/schemas/module-manifest.json',
            $schemaPath
        );

        /*
         * Convert PHP array to JSON object/array structure
         * expected by Opis.
         */
        $data = json_decode(
            json_encode($manifest)
        );

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
         * Custom formatter.
         */
        $custom = function (ValidationError $error) {

            $dataInfo = $error->data();
            $schemaInfo = $error->schema()->info()->data();
            $keyword = $error->keyword();

            $message = $error->message();
            $args = $error->args();

            // -----------------------------------------
            // REQUIRED
            // -----------------------------------------
            if ($keyword === 'required') {

                $missing = $args['missing'] ?? null;

                if (is_array($missing)) {
                    $missing = implode(', ', $missing);
                }

                $message = "Missing required field(s): {$missing}";
            }

            // -----------------------------------------
            // ADDITIONAL PROPERTIES
            // -----------------------------------------
            elseif ($keyword === 'additionalProperties') {

                $properties =
                    $args['properties']
                    ?? $args['property']
                    ?? null;

                if (is_array($properties)) {
                    $properties = implode(', ', $properties);
                }

                if ($properties !== null) {
                    $message = str_replace(
                        '{properties}',
                        $properties,
                        $message
                    );
                }
            }

            // -----------------------------------------
            // CUSTOM SCHEMA MESSAGE
            // -----------------------------------------
            $customMessage = null;

            if (
                isset($schemaInfo->{'$error'}) &&
                isset($schemaInfo->{'$error'}->{$keyword})
            ) {
                $customMessage =
                    $schemaInfo->{'$error'}->{$keyword};
            }

            return [
                'path' => implode(
                    '.',
                    $dataInfo->fullPath()
                ) ?: '',

                'message' => $customMessage ?: $message,

                'keyword' => $keyword,
            ];
        };

        $errors = $formatter->format(
            $result->error(),
            true,
            $custom
        );

        /*
         * Console output.
         */
        if ($command instanceof Command) {
            $command->error(
                '❌ Manifest validation failed.'
            );
        }

        $errorBug = [];

        foreach ($errors as $errorGroup) {

            foreach ($errorGroup as $error) {

                $path = $error['path'] ?? '';

                $message = $error['message']
                    ?? 'Unknown error';

                $errorBug[] =
                    "{$path}: {$message}";
            }
        }

        return $errorBug;
    }
}
