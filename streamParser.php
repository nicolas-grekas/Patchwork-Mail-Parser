#!/usr/bin/php -q
<?php // vi: set encoding=utf-8 expandtab shiftwidth=4 tabstop=4:

require __DIR__ . '/Stream/Parser.php';
require __DIR__ . '/Stream/Parser/Echo.php';

$parser = new Stream_Parser;
$parser = new Stream_Parser_Echo($parser);
$parser->parseStream(STDIN);
