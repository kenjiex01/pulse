<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Language;

$source = __DIR__.'/Pulse-and-Collector-Progress-and-Target-Report-2026-08-11-to-08-22.md';
$target = __DIR__.'/Pulse-and-Collector-Progress-and-Target-Report-2026-08-11-to-08-22.docx';

$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new Language(Language::EN_US));
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(11);

$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16, 'color' => '1F4E79'], ['spaceAfter' => 200]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 13, 'color' => '2E75B6'], ['spaceBefore' => 240, 'spaceAfter' => 120]);

$section = $phpWord->addSection([
    'marginTop' => 720,
    'marginBottom' => 720,
    'marginLeft' => 900,
    'marginRight' => 900,
]);

$lines = file($source, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Unable to read {$source}\n");
    exit(1);
}

$paragraph = ['spaceAfter' => 120, 'lineHeight' => 1.15];

foreach ($lines as $line) {
    $trimmed = rtrim($line);

    if ($trimmed === '') {
        continue;
    }

    if (str_starts_with($trimmed, '# ')) {
        $section->addTitle(substr($trimmed, 2), 1);
        continue;
    }

    if (str_starts_with($trimmed, '## ')) {
        $section->addTitle(substr($trimmed, 3), 2);
        continue;
    }

    if ($trimmed === '---') {
        $section->addTextBreak(1);
        continue;
    }

    $run = $section->addTextRun($paragraph);
    addMarkdownRuns($run, $trimmed);
}

function addMarkdownRuns($run, string $text): void
{
    if (preg_match('/^(\d+\.\s+)(.+)$/', $text, $m)) {
        $run->addText($m[1], ['size' => 11]);
        addInline($run, $m[2]);

        return;
    }

    addInline($run, $text);
}

function addInline($run, string $text): void
{
    $parts = preg_split('/(\*\*.*?\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
            $run->addText(substr($part, 2, -2), ['bold' => true, 'size' => 11]);
            continue;
        }

        $run->addText($part, ['size' => 11]);
    }
}

$phpWord->save($target);

echo "Wrote {$target}\n";
