<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Parses an uploaded language file into a flat [label_key => label_value] array.
 *
 * Replaces a previous implementation that did `include($uploadedFile->getRealPath())` on the raw upload -
 * i.e. executed whatever PHP code the uploader supplied. This only ever json_decode()s the content, so an
 * uploaded file can never run as code.
 */
class LanguageJsonImportService
{
    /**
     * @return array<string, string>|null Null when the file isn't a valid flat JSON object of string values.
     */
    public function parse(UploadedFile $file): ?array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'json') {
            return null;
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        $labels = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || $key === '' || !is_scalar($value)) {
                return null;
            }
            $labels[$key] = (string) $value;
        }

        return $labels;
    }
}
