<?php

namespace App\Support;

class CompanyDocumentElementCatalog
{
    /**
     * @return array<string, array<int, array{type: string, label: string, tag_key?: string}>>
     */
    public static function palette(): array
    {
        return [
            'BASIC' => [
                ['type' => 'heading', 'label' => 'Heading'],
                ['type' => 'paragraph', 'label' => 'Paragraph'],
                ['type' => 'divider', 'label' => 'Divider'],
                ['type' => 'image', 'label' => 'Image'],
                ['type' => 'section', 'label' => 'Section'],
                ['type' => 'page_break', 'label' => 'Page break'],
            ],
            'TEXT' => [
                ['type' => 'short_text', 'label' => 'Short text'],
                ['type' => 'long_text', 'label' => 'Long text'],
                ['type' => 'number', 'label' => 'Number'],
                ['type' => 'email', 'label' => 'Email'],
            ],
            'DATE' => [
                ['type' => 'date', 'label' => 'Date'],
            ],
            'CHOICE' => [
                ['type' => 'dropdown', 'label' => 'Dropdown'],
                ['type' => 'radio', 'label' => 'Radio'],
                ['type' => 'checkbox', 'label' => 'Checkbox'],
                ['type' => 'yes_no', 'label' => 'Yes / No'],
            ],
            'TAGS' => CompanyDocumentMergeTagCatalog::paletteItems(),
            'ADVANCED' => [
                ['type' => 'file_upload', 'label' => 'File upload'],
                ['type' => 'signature', 'label' => 'Signature'],
            ],
        ];
    }

    public static function defaultLabel(string $type): string
    {
        foreach (self::palette() as $items) {
            foreach ($items as $item) {
                if ($item['type'] === $type) {
                    return $item['label'];
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $type));
    }
}
