<?php

namespace App\Support;

class CompanyDocumentMemoFonts
{
    /**
     * Times New Roman and Arial as installed on macOS and Windows.
     * Dompdf does not use those faces unless the files are loaded.
     *
     * @return array<string, string>
     */
    public static function paths(): array
    {
        $sets = [
            [
                'times' => '/System/Library/Fonts/Supplemental/Times New Roman.ttf',
                'times-bold' => '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf',
                'times-italic' => '/System/Library/Fonts/Supplemental/Times New Roman Italic.ttf',
                'times-bold-italic' => '/System/Library/Fonts/Supplemental/Times New Roman Bold Italic.ttf',
                'arial' => '/System/Library/Fonts/Supplemental/Arial.ttf',
                'arial-bold' => '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                'arial-black' => '/System/Library/Fonts/Supplemental/Arial Black.ttf',
            ],
            [
                'times' => 'C:/Windows/Fonts/times.ttf',
                'times-bold' => 'C:/Windows/Fonts/timesbd.ttf',
                'times-italic' => 'C:/Windows/Fonts/timesi.ttf',
                'times-bold-italic' => 'C:/Windows/Fonts/timesbi.ttf',
                'arial' => 'C:/Windows/Fonts/arial.ttf',
                'arial-bold' => 'C:/Windows/Fonts/arialbd.ttf',
                'arial-black' => 'C:/Windows/Fonts/ariblk.ttf',
            ],
        ];

        foreach ($sets as $set) {
            if (is_file($set['times']) && is_file($set['arial-black'])) {
                return $set;
            }
        }

        return [];
    }

    /**
     * Copies the faces into the Dompdf chroot under short filenames.
     *
     * @return array<string, string>
     */
    public static function copyInto(string $directory): array
    {
        $copied = [];

        foreach (self::paths() as $key => $path) {
            $filename = $key.'.ttf';
            if (! @copy($path, $directory.DIRECTORY_SEPARATOR.$filename)) {
                return [];
            }
            $copied[$key] = $filename;
        }

        return $copied;
    }
}
