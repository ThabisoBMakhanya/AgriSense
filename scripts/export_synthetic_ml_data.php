<?php
declare(strict_types=1);

$outputDir = __DIR__ . '/../exports';
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create exports directory.\n");
    exit(1);
}

$writeRows = static function (string $name, array $headers, iterable $rows) use ($outputDir): int {
    $handle = fopen($outputDir . '/' . $name, 'wb');
    if ($handle === false) throw new RuntimeException("Could not write {$name}.");
    fputcsv($handle, $headers);
    $count = 0;
    foreach ($rows as $row) {
        fputcsv($handle, $row);
        $count++;
    }
    fclose($handle);
    echo "Exported {$count} rows to exports/{$name}.\n";
    return $count;
};

$weatherRows = (static function (): Generator {
    $classes = ['poor', 'moderate', 'good', 'excellent'];
    foreach (range(10, 40, 3) as $temperature) {
        foreach ([25, 45, 65, 85] as $humidity) {
            foreach ([0, 5, 15, 30] as $rainfall) {
                $windSpeed = 4 + ($temperature % 7);
                $score = (int)($temperature >= 15 && $temperature <= 32)
                    + (int)($humidity >= 40)
                    + (int)($rainfall > 2)
                    + (int)($windSpeed < 15);
                yield [$temperature, $humidity, $rainfall, $windSpeed, $classes[$score - 1]];
            }
        }
    }
})();

$cropRanges = [
    'Maize'=>[18,32,50,120], 'Sorghum'=>[22,35,30,80], 'Beans'=>[18,28,60,100],
    'Sweet Potatoes'=>[20,30,80,150], 'Cabbage'=>[15,28,70,120], 'Groundnuts'=>[25,35,30,70],
    'Cassava'=>[22,34,70,140], 'Citrus'=>[18,30,40,90], 'Sugarcane'=>[25,35,80,150],
    'Cotton'=>[20,35,50,100], 'Pineapple'=>[22,32,60,120], 'Mango'=>[20,35,40,100],
];
$cropRows = (static function () use ($cropRanges): Generator {
    foreach ($cropRanges as $crop => [$tempMin, $tempMax, $rainMin, $rainMax]) {
        for ($index = 0; $index < 8; $index++) {
            yield [
                ($tempMin + $tempMax) / 2 + $index - 3,
                ($rainMin + $rainMax) / 2 + ($index - 3) * 3,
                55 + $index * 3,
                $index % 4,
                $crop,
            ];
        }
    }
})();

$yieldRows = (static function (): Generator {
    for ($temperature = 15; $temperature <= 35; $temperature += 2) {
        for ($rainfall = 30; $rainfall <= 150; $rainfall += 20) {
            foreach ([40, 60, 80] as $humidity) {
                for ($soilQuality = 1; $soilQuality <= 4; $soilQuality++) {
                    $yield = $soilQuality * 1150 + max(0, 180 - abs($rainfall - 90) * 4) - abs($temperature - 25) * 35;
                    yield [$temperature, $rainfall, $humidity, $soilQuality, 1, $yield];
                }
            }
        }
    }
})();

try {
    $writeRows('synthetic_weather_training.csv', ['temperature_c','humidity_pct','rainfall_mm','wind_speed','label_class'], $weatherRows);
    $writeRows('synthetic_crop_training.csv', ['temperature_c','rainfall_mm','humidity_pct','season_index','crop_label'], $cropRows);
    $writeRows('synthetic_yield_training.csv', ['temperature_c','rainfall_mm','humidity_pct','soil_quality_index','farm_size_ha','yield_kg_ha'], $yieldRows);
} catch (Throwable $error) {
    fwrite(STDERR, "Synthetic model data export failed.\n");
    exit(1);
}
