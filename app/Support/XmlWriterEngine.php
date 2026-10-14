<?php

namespace App\Support;

/**
 * Userland stand-in for ext-xmlwriter.
 * NativePHP's static PHP includes libxml/xml but not the XMLWriter class that PhpSpreadsheet needs for .xlsx.
 */
class XmlWriterEngine
{
    private string $buffer = '';

    private bool $memory = true;

    /** @var resource|null */
    private $handle = null;

    private bool $inStartTag = false;

    private string $openTag = '';

    /** @var list<array{0: string, 1: string}> */
    private array $pendingNs = [];

    /** @var list<array{name: string, hasChild: bool}> */
    private array $stack = [];

    public function openMemory(): bool
    {
        $this->closeHandle();
        $this->memory = true;
        $this->buffer = '';
        $this->resetDocument();

        return true;
    }

    public function openUri(string $uri): bool
    {
        $handle = @fopen($uri, 'wb');

        if ($handle === false) {
            return false;
        }

        $this->closeHandle();
        $this->handle = $handle;
        $this->memory = false;
        $this->buffer = '';
        $this->resetDocument();

        return true;
    }

    public function setIndent(bool $enable): bool
    {
        return true;
    }

    public function setIndentString(string $indentation): bool
    {
        return true;
    }

    public function startDocument(?string $version = '1.0', ?string $encoding = null, ?string $standalone = null): bool
    {
        $declaration = '<?xml version="'.($version ?? '1.0').'"';

        if ($encoding !== null && $encoding !== '') {
            $declaration .= ' encoding="'.$encoding.'"';
        }

        if ($standalone !== null && $standalone !== '') {
            $declaration .= ' standalone="'.$standalone.'"';
        }

        $this->append($declaration."?>\n");

        return true;
    }

    public function endDocument(): bool
    {
        while ($this->stack !== []) {
            if (! $this->endElement()) {
                return false;
            }
        }

        return true;
    }

    public function startElement(string $name): bool
    {
        return $this->beginElement($name);
    }

    public function startElementNs(?string $prefix, string $name, ?string $namespace): bool
    {
        $qualified = ($prefix !== null && $prefix !== '') ? $prefix.':'.$name : $name;

        if (! $this->beginElement($qualified)) {
            return false;
        }

        if ($namespace !== null) {
            $attr = ($prefix !== null && $prefix !== '') ? 'xmlns:'.$prefix : 'xmlns';
            $this->pendingNs[] = [$attr, $namespace];
        }

        return true;
    }

    public function endElement(): bool
    {
        if ($this->stack === []) {
            return false;
        }

        $current = array_pop($this->stack);

        if ($this->inStartTag && ! $current['hasChild']) {
            $this->finishStartTag(true);

            return true;
        }

        $this->finishStartTag(false);
        $this->append('</'.$current['name'].'>');

        return true;
    }

    public function fullEndElement(): bool
    {
        if ($this->stack === []) {
            return false;
        }

        $current = array_pop($this->stack);
        $this->finishStartTag(false);
        $this->append('</'.$current['name'].'>');

        return true;
    }

    public function writeElement(string $name, ?string $content = null): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();

        if ($content === null) {
            $this->append('<'.$name.'/>');

            return true;
        }

        $this->append('<'.$name.'>'.$this->escape($content).'</'.$name.'>');

        return true;
    }

    public function writeElementNs(?string $prefix, string $name, ?string $namespace, ?string $content = null): bool
    {
        $qualified = ($prefix !== null && $prefix !== '') ? $prefix.':'.$name : $name;
        $this->finishStartTag(false);
        $this->markParentHasChild();

        $xmlns = '';
        if ($namespace !== null) {
            $attr = ($prefix !== null && $prefix !== '') ? 'xmlns:'.$prefix : 'xmlns';
            $xmlns = ' '.$attr.'="'.$this->escape($namespace).'"';
        }

        if ($content === null) {
            $this->append('<'.$qualified.$xmlns.'/>');

            return true;
        }

        $this->append('<'.$qualified.$xmlns.'>'.$this->escape($content).'</'.$qualified.'>');

        return true;
    }

    public function writeAttribute(string $name, string $value): bool
    {
        if (! $this->inStartTag) {
            return false;
        }

        $this->openTag .= ' '.$name.'="'.$this->escape($value).'"';

        return true;
    }

    public function writeAttributeNs(?string $prefix, string $name, ?string $namespace, string $value): bool
    {
        $qualified = ($prefix !== null && $prefix !== '') ? $prefix.':'.$name : $name;

        if (! $this->writeAttribute($qualified, $value)) {
            return false;
        }

        if ($namespace !== null) {
            $attr = ($prefix !== null && $prefix !== '') ? 'xmlns:'.$prefix : 'xmlns';
            $this->pendingNs[] = [$attr, $namespace];
        }

        return true;
    }

    public function text(string $content): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();
        $this->append($this->escape($content));

        return true;
    }

    public function writeRaw(string $content): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();
        $this->append($content);

        return true;
    }

    public function writeCdata(string $content): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();
        $this->append('<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $content).']]>');

        return true;
    }

    public function writeComment(string $content): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();
        $this->append('<!--'.$content.'-->');

        return true;
    }

    public function outputMemory(bool $flush = true): string
    {
        $output = $this->buffer;

        if ($flush) {
            $this->buffer = '';
        }

        return $output;
    }

    public function flush(bool $empty = true): string|int
    {
        if ($this->memory) {
            $output = $this->buffer;

            if ($empty) {
                $this->buffer = '';
            }

            return $output;
        }

        $bytes = strlen($this->buffer);

        if (is_resource($this->handle) && $this->buffer !== '') {
            fwrite($this->handle, $this->buffer);
            fflush($this->handle);
        }

        if ($empty) {
            $this->buffer = '';
        }

        return $bytes;
    }

    private function beginElement(string $name): bool
    {
        $this->finishStartTag(false);
        $this->markParentHasChild();
        $this->stack[] = [
            'name' => $name,
            'hasChild' => false,
        ];
        $this->openTag = '<'.$name;
        $this->inStartTag = true;
        $this->pendingNs = [];

        return true;
    }

    private function finishStartTag(bool $selfClose): void
    {
        if (! $this->inStartTag) {
            return;
        }

        foreach ($this->pendingNs as [$name, $value]) {
            $this->openTag .= ' '.$name.'="'.$this->escape($value).'"';
        }

        $this->pendingNs = [];
        $this->openTag .= $selfClose ? '/>' : '>';
        $this->append($this->openTag);
        $this->openTag = '';
        $this->inStartTag = false;
    }

    private function markParentHasChild(): void
    {
        if ($this->stack === [] || $this->inStartTag) {
            return;
        }

        $index = array_key_last($this->stack);
        $this->stack[$index]['hasChild'] = true;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_COMPAT | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function append(string $chunk): void
    {
        $this->buffer .= $chunk;
    }

    private function resetDocument(): void
    {
        $this->inStartTag = false;
        $this->openTag = '';
        $this->pendingNs = [];
        $this->stack = [];
    }

    private function closeHandle(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }
}
