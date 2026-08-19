<?php

$logPath = __DIR__ . '/../storage/logs/laravel.log';
if (!file_exists($logPath)) {
    echo "No log file found." . PHP_EOL;
    exit(0);
}

$lines = file($logPath);
$tail = array_slice($lines, -250);
echo implode('', $tail);
