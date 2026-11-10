<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../modules/weather_service.php';

function region_weather(string $region, bool $force = false): array
{
    try {
        return fetch_weather_location($region, $force);
    } catch (Throwable $exception) {
        try { $place = weather_location($region); }
        catch (Throwable) {
            return ['location'=>$region, 'temperature'=>null, 'humidity'=>null, 'rain_probability'=>null,
                'rainfall'=>null, 'wind_speed'=>null, 'wind_kmh'=>null, 'cloud_pct'=>null,
                'condition'=>'Unavailable', 'icon'=>'weather', 'raw'=>[], 'cached_at'=>null,
                'weather_source'=>'Unavailable', 'weather_error'=>'Choose a supported weather place in your farm profile.'];
        }
        $cache = read_json(WEATHER_CACHE_FILE, []);
        $entry = $cache[$place[0]] ?? $cache[$region] ?? null;
        if (is_array($entry['data'] ?? null) && time() - (int)($entry['cached_at'] ?? 0) < WEATHER_CACHE_SECONDS) {
            $cached = $entry['data'];
            $cached['weather_source'] = !empty($cached['raw']) ? 'OpenWeatherMap cache' : 'Cached estimate';
            $cached['weather_error'] = $exception->getMessage();
            return $cached;
        }

        return [
            'location'=>$place[0], 'temperature'=>null, 'humidity'=>null,
            'rain_probability'=>null, 'rainfall'=>null, 'wind_speed'=>null, 'wind_kmh'=>null,
            'cloud_pct'=>null, 'condition'=>'Unavailable', 'icon'=>'weather', 'raw'=>[], 'cached_at'=>null,
            'weather_source'=>'Unavailable', 'weather_error'=>$exception->getMessage(),
        ];
    }
}
