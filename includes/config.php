<?php
const DATA_DIR = __DIR__ . '/../data';
const LOG_DIR = __DIR__ . '/../logs';
const USERS_FILE = DATA_DIR . '/users.json';
const MESSAGES_FILE = DATA_DIR . '/messages.json';
const WEATHER_CACHE_FILE = DATA_DIR . '/weather_cache.json';
const ML_DIR = __DIR__ . '/../ml_models';
const WEATHER_API_KEY = ''; // Set your OpenWeatherMap key here.
const WEATHER_CACHE_SECONDS = 300;

foreach ([DATA_DIR, LOG_DIR] as $directory) {
    if (!is_dir($directory)) mkdir($directory, 0755, true);
}
foreach ([USERS_FILE => '{}', MESSAGES_FILE => '[]', WEATHER_CACHE_FILE => '{}'] as $file => $empty) {
    if (!is_file($file)) file_put_contents($file, $empty, LOCK_EX);
}
