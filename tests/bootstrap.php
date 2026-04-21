<?php

$loader = require dirname(__DIR__, 3) . '/vendor/autoload.php';

$loader->addPsr4('GlpiPlugin\\Timelineticket\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('GlpiPlugin\\Timelineticket\\Tests\\', dirname(__DIR__) . '/tests/');
