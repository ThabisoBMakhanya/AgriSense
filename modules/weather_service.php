<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

function weather_location(string $regionOrLocation): array
{
    $places = [
        'Mbabane'=>['Mbabane',-26.3054,31.1367], 'Pigg’s Peak'=>['Pigg’s Peak',-25.9608,31.2467], 'Mankayane'=>['Mankayane',-26.6333,31.1833],
        'Manzini'=>['Manzini',-26.4988,31.3800], 'Matsapha'=>['Matsapha',-26.5289,31.3072], 'Malkerns'=>['Malkerns',-26.5667,31.1833], 'Bhunya'=>['Bhunya',-26.5500,31.0167],
        'Nhlangano'=>['Nhlangano',-27.1122,31.1989], 'Hlathikhulu'=>['Hlathikhulu',-27.0000,31.3200], 'Lavumisa'=>['Lavumisa',-27.3104,31.8920],
        'Siteki'=>['Siteki',-26.4525,31.9472], 'Big Bend'=>['Big Bend',-26.8167,31.9333], 'Mhlume'=>['Mhlume',-26.0333,31.8833], 'Simunye'=>['Simunye',-26.2000,31.9000],
    ];
    if (isset($places[$regionOrLocation])) return $places[$regionOrLocation];
    // Select locations may contain typographic apostrophes or legacy mojibake.
    // Resolve those to the same town coordinates instead of falling back to a region.
    $normalise = static fn(string $value): string => strtolower(preg_replace('/[^a-z0-9]/i', '', str_replace(['â€™', '’', '`'], '', $value)));
    $wanted = $normalise($regionOrLocation);
    foreach ($places as $name => $place) {
        if ($normalise($name) === $wanted) return $place;
    }
    $regions = ['Hhohho'=>$places['Mbabane'], 'Manzini'=>$places['Manzini'], 'Shiselweni'=>$places['Nhlangano'], 'Lubombo'=>$places['Siteki']];
    if (isset($regions[$regionOrLocation])) return $regions[$regionOrLocation];
    throw new InvalidArgumentException('Choose a supported town or place for local weather.');
}

function weather_cached(string $location): ?array
{
    try {
        $cutoff = db_driver() === 'pgsql'
            ? "fetched_at >= NOW() - INTERVAL '15 minutes'"
            : 'fetched_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)';
        $q = db()->prepare("SELECT * FROM weather_cache WHERE location = ? AND {$cutoff}");
        $q->execute([$location]);
        $row = $q->fetch();
        if ($row) {
            $raw = json_decode($row['raw_json'], true);
            return ['location'=>$row['location'],'temperature'=>(float)$row['temp_c'],'humidity'=>(int)$row['humidity_pct'],'rain_probability'=>(float)$row['rain_probability'],'rainfall'=>(float)($raw['current']['rain']['1h']??0),'wind_speed'=>(float)$row['wind_kmh']/3.6,'wind_kmh'=>(float)$row['wind_kmh'],'cloud_pct'=>(int)$row['cloud_pct'],'condition'=>$row['condition_text'],'icon'=>'weather','raw'=>$raw,'cached_at'=>strtotime($row['fetched_at']),'weather_source'=>'OpenWeatherMap cache'];
        }
    } catch (Throwable $e) { /* JSON cache remains available until MySQL is configured. */ }
    $cache = read_json(WEATHER_CACHE_FILE, []);
    $entry = $cache[$location] ?? null;
    return is_array($entry) && time() - (int)($entry['cached_at'] ?? 0) < WEATHER_CACHE_SECONDS ? $entry['data'] : null;
}

function weather_http(string $url): array
{
    $status = 0;
    $caBundle = getenv('WEATHER_CA_BUNDLE') ?: '';
    if ($caBundle === '' || !is_file($caBundle)) {
        $configuredBundle = (string)(ini_get('curl.cainfo') ?: ini_get('openssl.cafile'));
        $caCandidates = [$configuredBundle, 'C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/php/extras/ssl/cacert.pem', 'C:/Program Files/Git/usr/ssl/cert.pem'];
        $caBundle = '';
        foreach ($caCandidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) { $caBundle = $candidate; break; }
        }
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $curlOptions = [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10];
        if ($caBundle !== '' && is_file($caBundle)) $curlOptions[CURLOPT_CAINFO] = $caBundle;
        curl_setopt_array($ch, $curlOptions);
        $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $curlError = curl_errno($ch);
        if ($body === false) throw new RuntimeException("Could not connect to OpenWeatherMap over HTTPS (cURL error {$curlError}).");
    } else {
        $sslOptions = ['verify_peer'=>true,'verify_peer_name'=>true];
        if ($caBundle !== '' && is_file($caBundle)) $sslOptions['cafile'] = $caBundle;
        $context = stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true],'ssl'=>$sslOptions]);
        $body = @file_get_contents($url, false, $context);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) { $status=(int)$match[1]; break; }
        }
        if ($body === false) throw new RuntimeException('Could not connect to OpenWeatherMap over HTTPS.');
    }
    $json = json_decode($body, true);
    if (!is_array($json)) throw new RuntimeException('OpenWeatherMap returned invalid JSON.');
    if ($status !== 200) {
        $detail = $json['message'] ?? 'request rejected';
        if ($status === 401) throw new RuntimeException('OpenWeatherMap rejected the API key (HTTP 401). Check that it is correct and activated.');
        throw new RuntimeException("OpenWeatherMap HTTP {$status}: {$detail}");
    }
    return $json;
}

function fetch_weather_location(string $region, bool $force = false): array
{
    $place = weather_location($region); $location = $place[0];
    if (!$force && ($cached = weather_cached($location))) return $cached;
    $key = defined('OPENWEATHER_API_KEY') ? OPENWEATHER_API_KEY : '';
    if ($key === '') throw new RuntimeException('Set OPENWEATHER_API_KEY to fetch live weather.');
    $base = 'https://api.openweathermap.org/data/2.5/';
    $query = 'lat='.$place[1].'&lon='.$place[2].'&units=metric&appid='.rawurlencode($key);
    $current = weather_http($base.'weather?'.$query);
    $forecast = weather_http($base.'forecast?'.$query);
    if (!isset($current['main']['temp'])) throw new RuntimeException('Weather response is missing current conditions.');
    $now = time(); $rainProbability = 0.0;
    foreach (($forecast['list'] ?? []) as $slot) {
        if (($slot['dt'] ?? 0) <= $now + 86400) $rainProbability = max($rainProbability, (float)($slot['pop'] ?? 0));
    }
    $condition = strtolower($current['weather'][0]['main'] ?? 'clear');
    $data = ['location'=>$location,'temperature'=>(float)$current['main']['temp'],'humidity'=>(int)$current['main']['humidity'],'rain_probability'=>$rainProbability,'rainfall'=>(float)($current['rain']['1h']??0),'wind_speed'=>(float)($current['wind']['speed']??0),'wind_kmh'=>(float)($current['wind']['speed']??0)*3.6,'cloud_pct'=>(int)($current['clouds']['all']??0),'condition'=>$current['weather'][0]['description']??$condition,'icon'=>'weather','raw'=>['current'=>$current,'forecast'=>$forecast],'cached_at'=>$now,'weather_source'=>'OpenWeatherMap live'];
    $raw = json_encode($data['raw'], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    try {
        $sql = db_driver() === 'pgsql'
            ? 'INSERT INTO weather_cache (location,temp_c,humidity_pct,rain_probability,wind_kmh,cloud_pct,condition_text,raw_json,fetched_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON CONFLICT (location) DO UPDATE SET temp_c=EXCLUDED.temp_c,humidity_pct=EXCLUDED.humidity_pct,rain_probability=EXCLUDED.rain_probability,wind_kmh=EXCLUDED.wind_kmh,cloud_pct=EXCLUDED.cloud_pct,condition_text=EXCLUDED.condition_text,raw_json=EXCLUDED.raw_json,fetched_at=NOW()'
            : 'INSERT INTO weather_cache (location,temp_c,humidity_pct,rain_probability,wind_kmh,cloud_pct,condition_text,raw_json,fetched_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE temp_c=VALUES(temp_c),humidity_pct=VALUES(humidity_pct),rain_probability=VALUES(rain_probability),wind_kmh=VALUES(wind_kmh),cloud_pct=VALUES(cloud_pct),condition_text=VALUES(condition_text),raw_json=VALUES(raw_json),fetched_at=NOW()';
        $q = db()->prepare($sql);
        $q->execute([$location,$data['temperature'],$data['humidity'],$rainProbability,$data['wind_kmh'],$data['cloud_pct'],$data['condition'],$raw]);
    } catch (Throwable $e) { error_log('Weather cache database write failed: ' . $e->getMessage()); }
    $cache = read_json(WEATHER_CACHE_FILE, []); $cache[$location] = ['cached_at'=>$now,'data'=>$data]; write_json(WEATHER_CACHE_FILE,$cache);
    return $data;
}
