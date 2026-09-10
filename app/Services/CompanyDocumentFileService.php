<?php

namespace App\Services;

use App\Models\CompanyDocumentElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyDocumentFileService
{
    public function storeSignature(UploadedFile $file, int $submissionId): string
    {
        $this->validateSignature($file);

        $filename = 'signature_'.Str::uuid().'.png';

        return Storage::disk('local')->putFileAs(
            $this->submissionDirectory($submissionId),
            $file,
            $filename,
        );
    }

    /**
     * @return array{file_path: string, original_filename: string, mime_type: string}
     */
    public function storeSignatureFromDataUrl(string $dataUrl, int $submissionId): array
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('Invalid signature data URL.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Invalid signature encoding.');
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Signature file is too large.');
        }

        $extension = match ($matches[1]) {
            'jpeg' => 'jpg',
            default => $matches[1],
        };
        $mimeType = 'image/'.$matches[1];
        $filename = 'signature_'.Str::uuid().'.'.$extension;
        $path = $this->submissionDirectory($submissionId).'/'.$filename;

        Storage::disk('local')->put($path, $binary);

        return [
            'file_path' => $path,
            'original_filename' => 'signature.'.$extension,
            'mime_type' => $mimeType,
        ];
    }

    public function storeSubmissionFile(UploadedFile $file, int $submissionId, CompanyDocumentElement $element): array
    {
        $error = $this->validateUploadedFile($file, $element);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $filename = Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $path = Storage::disk('local')->putFileAs(
            $this->submissionDirectory($submissionId),
            $file,
            $filename,
        );

        return [
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    public function validateSignature(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Signature must be a PNG, JPEG, or WebP image.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Signature file is too large.');
        }
    }

    public function validateUploadedFile(UploadedFile $file, CompanyDocumentElement $element): ?string
    {
        $maxKb = (int) config('uploads.max_file_kb', 5120);
        if ($file->getSize() > $maxKb * 1024) {
            return 'File is too large.';
        }

        return null;
    }

    public function storeApproverSignature(UploadedFile $file, int $submissionId): string
    {
        return $this->storeSignature($file, $submissionId);
    }

    public function storeDesignerImage(UploadedFile $file, int $formId): string
    {
        if (! in_array($file->getMimeType(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Image must be a PNG, JPEG, or WebP file.');
        }

        $maxKb = (int) config('uploads.max_file_kb', 5120);
        if ($file->getSize() > $maxKb * 1024) {
            throw new \InvalidArgumentException('Image file is too large.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $directory = $this->designerAssetDirectory($formId);
        $normalizedPng = $this->truecolorPngWithAlpha($file);

        if ($normalizedPng !== null) {
            $path = $directory.'/'.Str::uuid().'.png';
            Storage::disk('local')->put($path, $normalizedPng);

            return $path;
        }

        $filename = Str::uuid().'.'.$extension;

        return Storage::disk('local')->putFileAs(
            $directory,
            $file,
            $filename,
        );
    }

    /**
     * Convert paletted / tRNS PNGs to truecolor RGBA so transparency survives in the designer.
     */
    private function truecolorPngWithAlpha(UploadedFile $file): ?string
    {
        if ($file->getMimeType() !== 'image/png' || ! function_exists('imagecreatefrompng')) {
            return null;
        }

        $image = @imagecreatefrompng($file->getPathname());
        if ($image === false) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor') && ! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagepng($image, null, 6);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        if (! $ok || $binary === '') {
            return null;
        }

        return $binary;
    }

    public function assertDesignerAssetPath(int $formId, string $path): void
    {
        $prefix = $this->designerAssetDirectory($formId).'/';
        if (! Str::startsWith($path, $prefix)) {
            throw new \InvalidArgumentException('Invalid designer asset path.');
        }
    }

    private function designerAssetDirectory(int $formId): string
    {
        return 'company-documents/templates/'.$formId;
    }

    private function submissionDirectory(int $submissionId): string
    {
        return 'company-documents/submissions/'.$submissionId;
    }
}
