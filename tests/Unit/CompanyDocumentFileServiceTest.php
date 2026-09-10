<?php

namespace Tests\Unit;

use App\Services\CompanyDocumentFileService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyDocumentFileServiceTest extends TestCase
{
    public function test_store_signature_from_data_url(): void
    {
        Storage::fake('local');

        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $stored = (new CompanyDocumentFileService)->storeSignatureFromDataUrl($dataUrl, 99);

        $this->assertStringStartsWith('company-documents/submissions/99/signature_', $stored['file_path']);
        $this->assertSame('image/png', $stored['mime_type']);
        Storage::disk('local')->assertExists($stored['file_path']);
    }
}
