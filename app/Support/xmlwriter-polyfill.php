<?php

use App\Support\XmlWriterEngine;

if (! extension_loaded('xmlwriter') && ! class_exists('XMLWriter', false)) {
    class_alias(XmlWriterEngine::class, 'XMLWriter');
}
