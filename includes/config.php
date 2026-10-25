<?php
const DATA_DIR = __DIR__ . '/../data';
const LOG_DIR = __DIR__ . '/../logs';
const USERS_FILE = DATA_DIR . '/users.json';
const MESSAGES_FILE = DATA_DIR . '/messages.json';
const WEATHER_CACHE_FILE = DATA_DIR . '/weather_cache.json';
const ML_DIR = __DIR__ . '/../ml_models';
$localSecrets = __DIR__ . '/secrets.local.php';
if (is_file($localSecrets)) require_once $localSecrets;
if (!defined('WEATHER_API_KEY')) define('WEATHER_API_KEY', getenv('WEATHER_API_KEY') ?: ''); // Prefer the local ignored secrets file or environment.
const WEATHER_CACHE_SECONDS = 900;
if (!defined('OPENWEATHER_API_KEY')) define('OPENWEATHER_API_KEY', getenv('OPENWEATHER_API_KEY') ?: WEATHER_API_KEY);
if (!defined('LUNYAZI_SMS_API_KEY')) define('LUNYAZI_SMS_API_KEY', getenv('LUNYAZI_SMS_API_KEY') ?: '');
if (!defined('LUNYAZI_SMS_API_URL')) define('LUNYAZI_SMS_API_URL', 'https://api.lunyazi.com/sms/send');
if (!defined('WEATHER_CACHE_MINUTES')) define('WEATHER_CACHE_MINUTES', 15);
if (!defined('ML_MODEL_PATH')) define('ML_MODEL_PATH', __DIR__ . '/../ml/models/recommendation.model');
if (!defined('ML_CONFIDENCE_THRESHOLD')) define('ML_CONFIDENCE_THRESHOLD', 0.6);
if (!defined('ML_FALLBACK_TO_RULES')) define('ML_FALLBACK_TO_RULES', true);
const SUPPORTED_LOCATIONS = ['Mbabane', 'Manzini', 'Nhlangano', 'Siteki'];
const WEATHER_PLACES = [
    'Hhohho' => ['Mbabane', 'Pigg’s Peak', 'Mankayane'],
    'Manzini' => ['Manzini', 'Matsapha', 'Malkerns', 'Bhunya'],
    'Shiselweni' => ['Nhlangano', 'Hlathikhulu', 'Lavumisa'],
    'Lubombo' => ['Siteki', 'Big Bend', 'Mhlume', 'Simunye'],
];
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'kudla_kwesive');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
$dbPassword = getenv('DB_PASSWORD');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $dbPassword === false ? '' : $dbPassword);

foreach ([DATA_DIR, LOG_DIR] as $directory) {
    if (!is_dir($directory)) mkdir($directory, 0755, true);
}
foreach ([USERS_FILE => '{}', MESSAGES_FILE => '[]', WEATHER_CACHE_FILE => '{}'] as $file => $empty) {
    if (!is_file($file)) file_put_contents($file, $empty, LOCK_EX);
}
