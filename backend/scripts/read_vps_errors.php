<?php

$logPath = __DIR__ . '/../storage/logs/laravel.log';
if (!file_exists($logPath)) {
    echo "No log file found." . PHP_EOL;
    exit(0);
}

$content = file_get_contents($logPath);
preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] [^\n]+ERROR: [^\n]+/', $content, $matches);

foreach (array_slice($matches[0], -30) as $err) {
    echo $err . PHP_EOL;
}
