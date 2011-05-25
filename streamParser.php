#!/usr/bin/php -q
<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

require __DIR__ . '/Stream/Parser.php';
require __DIR__ . '/Stream/Parser/Log.php';

$parser = new Stream_Parser;

// Put other parsers here...

new Stream_Parser_Log($parser);

$parser->parseStream(STDIN);
