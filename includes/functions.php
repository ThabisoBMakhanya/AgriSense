<?php
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function read_json(string $file, $fallback = []) { $data = json_decode(@file_get_contents($file), true); return is_array($data) ? $data : $fallback; }
function write_json(string $file, $data): bool { return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false; }
function flash(string $type, string $message): void { $_SESSION['flash'] = ['type' => $type, 'message' => $message]; }
function get_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function soil_value($soil): int { if (is_numeric($soil)) return max(1, min(4, (int)$soil)); return ['Poor'=>1, 'Moderate'=>2, 'Good'=>3, 'Excellent'=>4][$soil] ?? 2; }
function soil_label($soil): string { return ['1'=>'Poor','2'=>'Moderate','3'=>'Good','4'=>'Excellent'][(string)soil_value($soil)] ?? 'Moderate'; }
function weather_advice(array $weather, string $classification): array {
    $items = [
        ['icon'=>'weather','text'=>['excellent'=>'Excellent weather! Perfect time for farming activities.','good'=>'Good conditions for farming activities.','moderate'=>'Moderate conditions - proceed with caution.','poor'=>'Poor conditions detected. Consider postponing activities.'][$classification] ?? 'Review conditions before working.'],
        ['icon'=>'weather','text'=>'High temperature alert: ensure crops are well-watered and consider mulching.'],
        ['icon'=>'weather','text'=>'Cold conditions: protect young seedlings with covers.'],
        ['icon'=>'weather','text'=>'Heavy rain expected: good for planting, but ensure proper drainage.'],
        ['icon'=>'weather','text'=>'Moderate rain: ideal for applying fertilizers.'],
        ['icon'=>'weather','text'=>'Low rainfall: consider irrigation planning.'],
        ['icon'=>'weather','text'=>'Strong winds predicted: check supports and harvest vulnerable fruit.'],
        ['icon'=>'weather','text'=>'Calm conditions are favorable.']
    ];
    $selected = [$items[0]]; $temp=(float)$weather['temperature']; $rain=(float)$weather['rainfall']; $wind=(float)$weather['wind_speed'];
    if (!empty($weather['weather_error'])) array_unshift($selected, ['icon'=>'weather','text'=>'Live weather unavailable; showing an estimate. '.$weather['weather_error']]);
    $selected[] = $temp > 32 ? $items[1] : ($temp < 15 ? $items[2] : ['icon'=>'🌡️','text'=>'Ideal temperature range for most crops.']);
    $selected[] = $rain > 10 ? $items[3] : ($rain >= 5 ? $items[4] : $items[5]);
    $selected[] = $wind > 15 ? $items[6] : $items[7];
    return $selected;
}
