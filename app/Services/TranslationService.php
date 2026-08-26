<?php

namespace App\Services;

class TranslationService
{
    /**
     * Get dynamic translation from a JSON column of a model.
     *
     * @param string $model Fully-qualified model class name
     * @param string $column     Column name containing JSON translations
     * @param int    $id         Record ID
     * @param string $language   Language key (e.g., 'en', 'fr')
     * @return string|null
     */
    public function getDynamicTranslation($model, $column, $id, $language)
    {
        if (!class_exists($model)) {
            return null;
        }

        $record = $model::find($id);

        if (!$record) {
            return null;
        }

        $translations = json_decode($record->$column, true);

        if (isset($translations[$language])) {
            return $translations[$language];
        }

        if (isset($translations['en'])) {
            return $translations['en'];
        }

        return $record->name ?? null;
    }

    public function getLanguageCode()
    {
        return session()->get('locale', 'en');
    }

}
