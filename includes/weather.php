<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../modules/weather_service.php';

function region_weather(string $region): array
{
    try {
        return fetch_weather_location($region);
    } catch (Throwable $exception) {
        $place = weather_location($region);
        $cache = read_json(WEATHER_CACHE_FILE, []);
        $entry = $cache[$place[0]] ?? $cache[$region] ?? null;
        if (is_array($entry['data'] ?? null) && time() - (int)($entry['cached_at'] ?? 0) < WEATHER_CACHE_SECONDS) {
            $cached = $entry['data'];
            $cached['weather_source'] = !empty($cached['raw']) ? 'OpenWeatherMap cache' : 'Cached estimate';
            $cached['weather_error'] = $exception->getMessage();
            return $cached;
        }

        $wet = (int)date('n') >= 10 || (int)date('n') <= 3;
        return [
            'location'=>$place[0], 'temperature'=>$wet ? 26.4 : 23.1,
            'humidity'=>$wet ? 68 : 47, 'rain_probability'=>$wet ? 0.45 : 0.08,
            'rainfall'=>$wet ? 8.6 : 1.2, 'wind_speed'=>4.8, 'wind_kmh'=>17.3,
            'cloud_pct'=>$wet ? 65 : 20, 'condition'=>$wet ? 'Clouds' : 'Clear',
            'icon'=>'weather', 'raw'=>[], 'cached_at'=>time(),
            'weather_source'=>'Seasonal estimate', 'weather_error'=>$exception->getMessage(),
        ];
    }
}
