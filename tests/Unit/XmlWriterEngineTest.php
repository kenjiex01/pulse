<?php

namespace Tests\Unit;

use App\Support\XmlWriterEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XmlWriterEngineTest extends TestCase
{
    #[Test]
    public function it_matches_native_xmlwriter_for_xlsx_style_documents(): void
    {
        if (! extension_loaded('xmlwriter')) {
            $this->markTestSkipped('Host PHP has no ext-xmlwriter to compare against.');
        }

        $native = new \XMLWriter();
        $native->openMemory();
        $this->writeSample($native);

        $engine = new XmlWriterEngine();
        $engine->openMemory();
        $this->writeSample($engine);

        $this->assertSame($native->outputMemory(true), $engine->outputMemory(true));
    }

    #[Test]
    public function it_writes_to_a_uri_and_flushes_like_the_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xmlw-');
        $this->assertNotFalse($path);

        try {
            $engine = new XmlWriterEngine();
            $this->assertTrue($engine->openUri($path));
            $engine->startDocument('1.0', 'UTF-8', 'yes');
            $engine->startElement('root');
            $engine->writeAttribute('a', '1');
            $engine->text('ok');
            $engine->endElement();
            $engine->flush(true);

            $this->assertSame(
                "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<root a=\"1\">ok</root>",
                file_get_contents($path),
            );
        } finally {
            @unlink($path);
        }
    }

    private function writeSample(\XMLWriter|XmlWriterEngine $writer): void
    {
        $writer->startDocument('1.0', 'UTF-8', 'yes');
        $writer->startElement('worksheet');
        $writer->writeAttribute('xmlns', 'http://example');
        $writer->writeAttribute('mc:Ignorable', 'x14ac');
        $writer->writeElement('v', '1 & 2 <x>');
        $writer->writeElement('empty', null);
        $writer->startElementNs('x14', 'id', null);
        $writer->text('abc');
        $writer->endElement();
        $writer->startElementNs('x14', 'cf', 'http://ns');
        $writer->writeAttributeNs('r', 'id', null, 'rId1');
        $writer->endElement();
        $writer->startElement('root');
        $writer->writeAttributeNs('r', 'id', 'http://rels', 'rId1');
        $writer->startElementNs(null, 'child', 'http://default');
        $writer->writeAttribute('a', "b\"c");
        $writer->endElement();
        $writer->startElement('x');
        $writer->endElement();
        $writer->fullEndElement();
        $writer->writeRaw('<raw>&ok</raw>');
        $writer->endElement();
    }
}
