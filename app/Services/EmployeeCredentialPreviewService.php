<?php

namespace App\Services;

use App\Models\EmployeeCredential;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class EmployeeCredentialPreviewService
{
    public function __construct(
        private readonly LibreOfficeDocumentConverter $libreOffice,
    ) {}

    public function respond(EmployeeCredential $credential): Response|BinaryFileResponse
    {
        if ($credential->stored_path === '' || ! Storage::disk('local')->exists($credential->stored_path)) {
            abort(404, 'Credential file not found.');
        }

        $absolutePath = Storage::disk('local')->path($credential->stored_path);
        $extension = strtolower((string) pathinfo($credential->original_filename, PATHINFO_EXTENSION));
        $mime = $this->resolveMime($credential, $absolutePath);

        // Always return HTML for the preview iframe so Electron/NativePHP never
        // navigates the frame to a raw attachment/binary document.
        if ($this->isImage($mime, $extension)) {
            return $this->imageHtml($credential);
        }

        if ($this->isPdf($mime, $extension)) {
            return $this->pdfHtml($credential);
        }

        if ($this->isAudio($mime, $extension) || $this->isVideo($mime, $extension)) {
            return $this->mediaHtml($credential, $mime, $this->isAudio($mime, $extension) ? 'audio' : 'video');
        }

        if ($this->isSpreadsheet($extension)) {
            $pdfPreview = $this->officePdfPreviewHtml($credential, $absolutePath);
            if ($pdfPreview !== null) {
                return $pdfPreview;
            }

            return $this->spreadsheetHtml($credential, $absolutePath);
        }

        if ($this->isWord($extension)) {
            $pdfPreview = $this->officePdfPreviewHtml($credential, $absolutePath);
            if ($pdfPreview !== null) {
                return $pdfPreview;
            }

            return $this->wordHtml($credential, $absolutePath);
        }

        if ($this->isText($mime, $extension)) {
            return $this->textHtml($credential, $absolutePath);
        }

        return $this->fallbackHtml($credential, $mime, 'This file type cannot be rendered inline. Download the file to open it.');
    }

    /**
     * @return 'image'|'pdf'|'audio'|'video'|'spreadsheet'|'word'|'text'|'other'
     */
    public function kind(EmployeeCredential $credential): string
    {
        $extension = strtolower((string) pathinfo($credential->original_filename, PATHINFO_EXTENSION));
        $absolutePath = ($credential->stored_path !== '' && Storage::disk('local')->exists($credential->stored_path))
            ? Storage::disk('local')->path($credential->stored_path)
            : '';
        $mime = $absolutePath !== ''
            ? $this->resolveMime($credential, $absolutePath)
            : (string) $credential->mime_type;

        // Desktop JS treats "pdf" as blob-embeddable. LibreOffice PDF renders as pdf.
        if (
            $absolutePath !== ''
            && ($this->isWord($extension) || $this->isSpreadsheet($extension))
            && $this->libreOffice->isAvailable()
        ) {
            return 'pdf';
        }

        if ($this->isImage($mime, $extension)) {
            return 'image';
        }
        if ($this->isPdf($mime, $extension)) {
            return 'pdf';
        }
        if ($this->isAudio($mime, $extension)) {
            return 'audio';
        }
        if ($this->isVideo($mime, $extension)) {
            return 'video';
        }
        if ($this->isSpreadsheet($extension)) {
            return 'spreadsheet';
        }
        if ($this->isWord($extension)) {
            return 'word';
        }
        if ($this->isText($mime, $extension)) {
            return 'text';
        }

        return 'other';
    }

    public function content(EmployeeCredential $credential): BinaryFileResponse
    {
        if ($credential->stored_path === '' || ! Storage::disk('local')->exists($credential->stored_path)) {
            abort(404, 'Credential file not found.');
        }

        $absolutePath = Storage::disk('local')->path($credential->stored_path);
        $extension = strtolower((string) pathinfo($credential->original_filename, PATHINFO_EXTENSION));

        // When LibreOffice is available, stream the converted PDF for Word/Excel so
        // desktop blob preview (kind=pdf) can show charts/images.
        if ($this->isWord($extension) || $this->isSpreadsheet($extension)) {
            $pdfPath = $this->ensureConvertedPdf($credential, $absolutePath);
            if ($pdfPath !== null) {
                return $this->inlineFile($pdfPath, 'application/pdf', pathinfo($credential->original_filename, PATHINFO_FILENAME).'.pdf');
            }
        }

        $mime = $this->resolveMime($credential, $absolutePath);

        return $this->inlineFile($absolutePath, $mime, $credential->original_filename);
    }

    private function inlineFile(string $absolutePath, string $mime, string $filename): BinaryFileResponse
    {
        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($filename).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function spreadsheetHtml(EmployeeCredential $credential, string $absolutePath): Response
    {
        try {
            $spreadsheet = SpreadsheetIOFactory::load($absolutePath);
            $writer = new SpreadsheetHtmlWriter($spreadsheet);
            $writer->writeAllSheets();
            $writer->setEmbedImages(true);

            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $this->wrappedHtml($credential, $html !== '' ? $html : '<p>Empty spreadsheet.</p>');
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackHtml($credential, $credential->mime_type, 'Could not convert this spreadsheet for preview. Download the file to open it.');
        }
    }

    private function wordHtml(EmployeeCredential $credential, string $absolutePath): Response
    {
        $extension = strtolower((string) pathinfo($credential->original_filename, PATHINFO_EXTENSION));
        $readers = $this->wordReadersFor($extension, $absolutePath);

        $lastException = null;

        foreach ($readers as $readerName) {
            try {
                $phpWord = WordIOFactory::load($absolutePath, $readerName);
                $writer = WordIOFactory::createWriter($phpWord, 'HTML');

                ob_start();
                $writer->save('php://output');
                $html = (string) ob_get_clean();

                if (trim(strip_tags($html)) === '') {
                    continue;
                }

                $html = $this->normalizePhpWordHtml($html);
                $embedded = $this->extractEmbeddedRasterImagesHtml($absolutePath);
                if ($embedded !== '') {
                    $html .= $embedded;
                }

                return $this->wrappedHtml($credential, $html);
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException !== null) {
            report($lastException);
        }

        // Last-resort text scrape for stubborn OLE .doc binaries.
        if (in_array($extension, ['doc', 'dot'], true)) {
            $extracted = $this->extractTextFromOleWord($absolutePath);
            if ($extracted !== '') {
                return $this->wrappedHtml(
                    $credential,
                    '<pre style="white-space:pre-wrap;word-break:break-word;margin:0;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">'
                    .e($extracted)
                    .'</pre>'
                );
            }
        }

        return $this->fallbackHtml($credential, $credential->mime_type, 'Could not convert this Word document for preview. Download the file to open it.');
    }

    /**
     * @return list<string>
     */
    private function wordReadersFor(string $extension, string $absolutePath): array
    {
        $readers = match ($extension) {
            'doc', 'dot' => ['MsDoc'],
            'docx', 'docm', 'dotx', 'dotm' => ['Word2007'],
            'odt' => ['ODText'],
            'rtf' => ['RTF'],
            default => ['Word2007', 'MsDoc', 'ODText', 'RTF'],
        };

        $header = @file_get_contents($absolutePath, false, null, 0, 8);
        $isOle = is_string($header) && str_starts_with($header, "\xD0\xCF\x11\xE0");
        $isZip = is_string($header) && str_starts_with($header, 'PK');

        if ($isOle && ! in_array('MsDoc', $readers, true)) {
            array_unshift($readers, 'MsDoc');
        }

        if ($isZip && ! in_array('Word2007', $readers, true)) {
            array_unshift($readers, 'Word2007');
        }

        return array_values(array_unique($readers));
    }

    private function extractTextFromOleWord(string $absolutePath): string
    {
        $raw = (string) @file_get_contents($absolutePath);
        if ($raw === '') {
            return '';
        }

        // Prefer UTF-16LE runs (common in Word97 binary), then printable Latin-1.
        $chunks = [];
        if (preg_match_all('/(?:[\x20-\x7E\x09\x0A\x0D]\x00){20,}/', $raw, $utf16Matches)) {
            foreach ($utf16Matches[0] as $match) {
                $decoded = @mb_convert_encoding($match, 'UTF-8', 'UTF-16LE');
                if (is_string($decoded) && trim($decoded) !== '') {
                    $chunks[] = trim($decoded);
                }
            }
        }

        if ($chunks === [] && preg_match_all('/[\x20-\x7E\x09\x0A\x0D]{40,}/', $raw, $asciiMatches)) {
            foreach ($asciiMatches[0] as $match) {
                $chunks[] = trim($match);
            }
        }

        $text = trim(implode("\n\n", array_unique($chunks)));

        return mb_strlen($text) >= 40 ? $text : '';
    }

    private function textHtml(EmployeeCredential $credential, string $absolutePath): Response
    {
        $raw = (string) file_get_contents($absolutePath);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        $escaped = e($raw);

        return $this->wrappedHtml(
            $credential,
            '<pre style="white-space:pre-wrap;word-break:break-word;margin:0;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">'.$escaped.'</pre>'
        );
    }

    private function officePdfPreviewHtml(EmployeeCredential $credential, string $absolutePath): ?Response
    {
        $pdfPath = $this->ensureConvertedPdf($credential, $absolutePath);
        if ($pdfPath === null) {
            return null;
        }

        // Re-use content route — streams the converted PDF when LibreOffice is available.
        $src = e($this->contentUrl($credential));

        return $this->wrappedHtml(
            $credential,
            '<embed src="'.$src.'" type="application/pdf" style="width:100%;height:calc(100vh - 2rem);border:0;">'
        );
    }

    private function ensureConvertedPdf(EmployeeCredential $credential, string $absolutePath): ?string
    {
        if (! $this->libreOffice->isAvailable()) {
            return null;
        }

        $hash = @hash_file('sha256', $absolutePath) ?: (string) filemtime($absolutePath);
        $relative = 'employee-credential-previews/'
            .$credential->employee_credential_id
            .'-'
            .substr($hash, 0, 24)
            .'.pdf';
        $destination = Storage::disk('local')->path($relative);

        if (is_file($destination) && filesize($destination) > 0) {
            return $destination;
        }

        return $this->libreOffice->convertToPdf($absolutePath, $destination);
    }

    private function normalizePhpWordHtml(string $html): string
    {
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '<br>', $html) ?? $html;
        $html = preg_replace(
            '/<p[^>]*>\s*(<(?:span|a)\b[^>]*>.*?<\/(?:span|a)>)\s*<\/p>/is',
            '$1 ',
            $html
        ) ?? $html;

        return '<div class="pulse-doc-preview" style="line-height:1.55;font-size:14px;max-width:48rem;">'
            .$html
            .'</div>';
    }

    private function extractEmbeddedRasterImagesHtml(string $absolutePath): string
    {
        $raw = (string) @file_get_contents($absolutePath);
        if ($raw === '') {
            return '';
        }

        $parts = [];
        if (preg_match_all('/\xFF\xD8\xFF.{100,}?\xFF\xD9/s', $raw, $jpegMatches)) {
            foreach ($jpegMatches[0] as $index => $bytes) {
                if (strlen($bytes) < 1024) {
                    continue;
                }
                $parts[] = '<figure style="margin:1rem 0;text-align:center;">'
                    .'<img src="data:image/jpeg;base64,'.base64_encode($bytes).'" '
                    .'alt="Embedded image '.($index + 1).'" style="max-width:100%;height:auto;">'
                    .'</figure>';
            }
        }

        if (preg_match_all('/\x89PNG\x0D\x0A\x1A\x0A.{100,}?IEND\xAE\x42\x60\x82/s', $raw, $pngMatches)) {
            foreach ($pngMatches[0] as $index => $bytes) {
                if (strlen($bytes) < 1024) {
                    continue;
                }
                $parts[] = '<figure style="margin:1rem 0;text-align:center;">'
                    .'<img src="data:image/png;base64,'.base64_encode($bytes).'" '
                    .'alt="Embedded image '.($index + 1).'" style="max-width:100%;height:auto;">'
                    .'</figure>';
            }
        }

        if ($parts === []) {
            return '';
        }

        return '<hr style="margin:1.5rem 0;border:none;border-top:1px solid #e5e7eb;">'
            .'<p style="font:13px/1.4 system-ui,sans-serif;color:#6b7280;">Embedded images found in file:</p>'
            .implode('', $parts);
    }

    private function imageHtml(EmployeeCredential $credential): Response
    {
        $src = e($this->contentUrl($credential));
        $alt = e($credential->original_filename);

        return $this->wrappedHtml(
            $credential,
            '<div style="display:flex;justify-content:center;padding:1rem;">'
            .'<img src="'.$src.'" alt="'.$alt.'" style="max-width:100%;height:auto;">'
            .'</div>'
        );
    }

    private function pdfHtml(EmployeeCredential $credential): Response
    {
        $src = e($this->contentUrl($credential));

        return $this->wrappedHtml(
            $credential,
            '<embed src="'.$src.'" type="application/pdf" style="width:100%;height:calc(100vh - 2rem);border:0;">'
            .'<p style="font:14px/1.4 system-ui,sans-serif;color:#4b5563;margin-top:0.75rem;">'
            .'If the PDF does not appear, <a href="'.e(route('employees.credentials.download', [
                'employee' => $credential->employee_id,
                'credential' => $credential->employee_credential_id,
            ])).'">download the file</a>.'
            .'</p>'
        );
    }

    private function contentUrl(EmployeeCredential $credential): string
    {
        return route('employees.credentials.content', [
            'employee' => $credential->employee_id,
            'credential' => $credential->employee_credential_id,
        ]);
    }

    private function mediaHtml(EmployeeCredential $credential, string $mime, string $tag): Response
    {
        $src = e($this->contentUrl($credential));
        $safeMime = e($mime);

        $player = $tag === 'audio'
            ? '<audio controls style="width:100%;max-width:720px;" src="'.$src.'" type="'.$safeMime.'"></audio>'
            : '<video controls style="width:100%;max-height:80vh;background:#000;" src="'.$src.'" type="'.$safeMime.'"></video>';

        return $this->wrappedHtml($credential, '<div style="display:flex;justify-content:center;padding:1.5rem;">'.$player.'</div>');
    }

    private function fallbackHtml(EmployeeCredential $credential, ?string $mime, string $message): Response
    {
        $downloadUrl = route('employees.credentials.download', [
            'employee' => $credential->employee_id,
            'credential' => $credential->employee_credential_id,
        ]);

        $body = '<div style="max-width:28rem;margin:3rem auto;padding:1.5rem;border:1px solid #e5e7eb;border-radius:0.75rem;text-align:center;font-family:system-ui,sans-serif;">'
            .'<p style="margin:0 0 0.5rem;font-weight:600;color:#111827;">'.e($credential->original_filename).'</p>'
            .'<p style="margin:0 0 0.25rem;font-size:0.875rem;color:#6b7280;">'.e($credential->humanFileSize()).($mime ? ' · '.e($mime) : '').'</p>'
            .'<p style="margin:1rem 0;font-size:0.875rem;color:#4b5563;">'.e($message).'</p>'
            .'<a href="'.e($downloadUrl).'" style="display:inline-block;padding:0.5rem 1rem;border-radius:0.5rem;background:#0B318F;color:#fff;text-decoration:none;font-size:0.875rem;">Download file</a>'
            .'</div>';

        return $this->wrappedHtml($credential, $body);
    }

    private function wrappedHtml(EmployeeCredential $credential, string $body): Response
    {
        $title = e($credential->description.' — '.$credential->original_filename);
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>html,body{margin:0;padding:0;background:#fff;color:#111827;} '
            .'img,table{max-width:100%;} body{padding:1rem;box-sizing:border-box;}</style>'
            .'</head><body>'.$body.'</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveMime(EmployeeCredential $credential, string $absolutePath): string
    {
        $mime = trim((string) $credential->mime_type);
        if ($mime !== '' && $mime !== 'application/octet-stream') {
            return $mime;
        }

        $detected = @mime_content_type($absolutePath);

        return is_string($detected) && $detected !== ''
            ? $detected
            : 'application/octet-stream';
    }

    private function safeFilename(string $filename): string
    {
        return str_replace(['"', "\r", "\n"], '', $filename);
    }

    private function isImage(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    private function isPdf(string $mime, string $extension): bool
    {
        return $mime === 'application/pdf' || $extension === 'pdf';
    }

    private function isAudio(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'audio/')
            || in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'], true);
    }

    private function isVideo(string $mime, string $extension): bool
    {
        return str_starts_with($mime, 'video/')
            || in_array($extension, ['mp4', 'webm', 'ogv', 'mov'], true);
    }

    private function isSpreadsheet(string $extension): bool
    {
        return in_array($extension, ['xls', 'xlsx', 'xlsm', 'ods', 'csv'], true);
    }

    private function isWord(string $extension): bool
    {
        return in_array($extension, ['doc', 'docx', 'odt', 'rtf'], true);
    }

    private function isText(string $mime, string $extension): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        return in_array($extension, ['txt', 'log', 'json', 'xml', 'md', 'html', 'htm'], true);
    }
}
