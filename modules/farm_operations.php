<?php

function farm_operations_advice(array $weather, array $farmer): array
{
    $tasks = [];
    $type = $farmer['farmer_type'] ?? 'crop';
    $stage = $farmer['growth_stage'] ?? 'vegetative';
    $temperature = (float)($weather['temperature'] ?? 0);
    $rainProbability = (float)($weather['rain_probability'] ?? 0);
    $rainfall = (float)($weather['rainfall'] ?? 0);
    $wind = (float)($weather['wind_kmh'] ?? ((float)($weather['wind_speed'] ?? 0) * 3.6));
    $condition = strtolower((string)($weather['condition'] ?? ''));

    if ($type === 'crop') {
        if ($rainProbability >= 0.65 || $rainfall >= 10 || str_contains($condition, 'storm')) {
            $tasks[] = ['title'=>'Drainage and field access', 'text'=>'Check field drains and low spots after rain. Keep machinery off saturated ground and postpone irrigation until you check soil moisture.'];
        } elseif ($temperature >= 32 && $rainProbability < 0.25) {
            $tasks[] = ['title'=>'Water and heat', 'text'=>'Check moisture in the crop root zone. If it is dry, prioritize recently planted and flowering crops and water during cooler hours.'];
        } else {
            $tasks[] = ['title'=>'Water planning', 'text'=>'Check soil moisture before irrigation and adjust the schedule for crop stage, soil type, and the local forecast.'];
        }
        $soil = $farmer['soil_type'] ?? 'loamy';
        $crop = $farmer['crop_type'] ?? 'your crop';
        $tasks[] = ['title'=>'Weeding', 'text'=>'Walk the rows and remove young weeds before they set seed. Work carefully around crop roots; mulch bare soil where practical.'];
        $fertilizerText = 'For ' . $crop . ', use a soil test and crop-stage guidance to decide whether fertilizer is due. Follow the locally recommended product and rate, and avoid applying ahead of heavy rain.';
        if ($rainProbability >= 0.65 || $rainfall >= 10) $fertilizerText = 'Hold fertilizer application while heavy rain is expected or fields are saturated. Check the soil-test plan and resume at the crop-appropriate time.';
        $tasks[] = ['title'=>'Soil and fertilizer', 'text'=>$fertilizerText . ' Your profile lists ' . $soil . ' soil.'];
        $tasks[] = ['title'=>'Pest and disease scouting', 'text'=>'Inspect several plants in each field section, including leaf undersides and stems. Record symptoms and locations; confirm the cause with an extension officer before choosing a treatment.'];

        $stageTasks = [
            'planting'=>'Check emergence, missing stands, and seedbed moisture. Replant gaps only when the crop calendar and soil conditions support it.',
            'vegetative'=>'Check plant spacing, early weed pressure, and signs of nutrient or water stress.',
            'flowering'=>'Protect flowering crops from avoidable water stress and inspect for pest or disease symptoms.',
            'harvesting'=>'Harvest mature produce in a suitable dry window, handle it gently, and keep storage areas clean and dry.',
        ];
        $tasks[] = ['title'=>'Crop stage: ' . ucfirst($stage), 'text'=>$stageTasks[$stage] ?? $stageTasks['vegetative']];
    } elseif ($type === 'livestock') {
        if ($temperature >= 32) $tasks[] = ['title'=>'Weather and water', 'text'=>'Check clean drinking water and shade first. Provide ventilation and avoid unnecessary handling during the hottest hours.'];
        elseif ($wind > 45 || str_contains($condition, 'storm') || $rainProbability >= 0.65) $tasks[] = ['title'=>'Weather and shelter', 'text'=>'Check dry shelter, secure fences, and access to clean water and feed. Keep animals away from flooded or damaged areas.'];
        else $tasks[] = ['title'=>'Water and grazing', 'text'=>'Check water points and pasture access. Note any damaged troughs or fences before moving animals.'];
        $tasks[] = ['title'=>'Herd health', 'text'=>'Observe appetite, movement, and signs of illness or injury. Record concerns and contact a veterinary or extension officer when an animal is unwell.'];
        $tasks[] = ['title'=>'Pasture, soil, and feed', 'text'=>'Check pasture cover and feed stocks, rotate grazing where possible, and keep feed dry. Base pasture fertilizer decisions on a soil test and local extension advice.'];
    } elseif ($type === 'poultry') {
        if ($temperature >= 30) $tasks[] = ['title'=>'Weather and ventilation', 'text'=>'Check cool, clean drinking water and airflow. Reduce heat buildup and watch the flock for panting or unusual inactivity.'];
        elseif (str_contains($condition, 'storm') || $rainProbability >= 0.65) $tasks[] = ['title'=>'Weather and housing', 'text'=>'Check that housing stays dry, ventilated, and secure. Protect feed from moisture and check drainage around the poultry house.'];
        else $tasks[] = ['title'=>'Water and house check', 'text'=>'Check drinkers, airflow, and the house for leaks before beginning routine flock care.'];
        $tasks[] = ['title'=>'Flock health and litter', 'text'=>'Refresh drinking water, check feed, inspect birds for changes in appetite or activity, and keep litter dry.'];
        $tasks[] = ['title'=>'Biosecurity', 'text'=>'Check footbaths and entry routines, clean drinkers, and separate unwell birds while you contact a poultry extension or veterinary officer.'];
    } elseif ($type === 'fisheries') {
        if ($temperature >= 30) $tasks[] = ['title'=>'Heat and water conditions', 'text'=>'Check pond or tank water level, fish behaviour, and aeration during warm conditions. Follow your normal water-quality monitoring plan.'];
        elseif ($rainProbability >= 0.65 || $rainfall >= 10 || str_contains($condition, 'storm')) $tasks[] = ['title'=>'Rain and pond security', 'text'=>'Check pond levels, screens, inlets, outlets, and overflow paths after rain. Secure equipment against runoff and debris.'];
        else $tasks[] = ['title'=>'Water system check', 'text'=>'Check pond or tank level, water movement, screens, and aeration before feeding.'];
        $tasks[] = ['title'=>'Stock health and feeding', 'text'=>'Observe fish behaviour and feeding response, remove uneaten feed when practical, and record unusual colour, activity, or mortalities for fisheries extension follow-up.'];
    } elseif ($type === 'beekeeping') {
        if ($wind > 35 || str_contains($condition, 'storm') || $rainProbability >= 0.65) $tasks[] = ['title'=>'Weather and hives', 'text'=>'Secure hive stands and equipment. Avoid opening hives during rain or strong wind; check them when conditions are suitable.'];
        elseif ($temperature >= 32) $tasks[] = ['title'=>'Heat and water', 'text'=>'Check that bees can reach clean water and that hives have suitable shade and ventilation.'];
        else $tasks[] = ['title'=>'Apiary check', 'text'=>'Check hive entrances, stands, and access to clean water before any planned inspection.'];
        $tasks[] = ['title'=>'Colony health', 'text'=>'Observe entrance activity and signs of damage or pests. Record unusual changes and consult a local beekeeping officer before treating a colony.'];
        $tasks[] = ['title'=>'Apiary safety', 'text'=>'Check hive stands, shade, and surrounding vegetation. Record unusual colony activity and consult a local beekeeping officer before treating a colony.'];
    }

    if ($wind > 40) $tasks[] = ['title'=>'Structures and equipment', 'text'=>'Secure shade cloth, fencing, irrigation lines, and loose tools before strong winds.'];
    else $tasks[] = ['title'=>'Records and supplies', 'text'=>'Record today’s rainfall, work completed, and input use. Check that essential tools and supplies are ready for the next operation.'];

    return $tasks;
}

/** Weather checks for the animal groups recorded on this farmer's profile. */
function farm_animal_weather_advice(array $weather, array $animalGroups): array
{
    if (!is_numeric($weather['temperature'] ?? null) || !$animalGroups) return [];

    $temperature = (float)$weather['temperature'];
    $rainProbability = (float)($weather['rain_probability'] ?? 0);
    $rainfall = (float)($weather['rainfall'] ?? 0);
    $condition = strtolower((string)($weather['condition'] ?? ''));
    $stormRisk = str_contains($condition, 'storm') || $rainProbability >= 0.65 || $rainfall >= 10;
    $groups = array_values(array_unique(array_filter(array_map('trim', $animalGroups))));
    $poultry = array_values(array_filter($groups, static fn(string $name): bool => preg_match('/chicken|poultry|broiler|layer|hen|rooster/i', $name) === 1));
    $pigs = array_values(array_filter($groups, static fn(string $name): bool => preg_match('/pig|swine|hog/i', $name) === 1));
    $other = array_values(array_diff($groups, $poultry, $pigs));
    $advice = [];

    if ($temperature >= 30 && $poultry) {
        $advice[] = ['title'=>'Hot weather: ' . implode(', ', $poultry), 'text'=>'Check that chickens have plenty of cool, clean water. Keep the coop shaded and well ventilated, and check the birds more often during the hottest hours.'];
    }
    if ($temperature >= 32 && $pigs) {
        $advice[] = ['title'=>'Hot weather: ' . implode(', ', $pigs), 'text'=>'Check pigs have constant access to clean drinking water and a cool, shaded, well-ventilated area. Avoid moving or handling them during the hottest part of the day.'];
    }
    if ($temperature >= 32 && $other) {
        $advice[] = ['title'=>'Heat check: ' . implode(', ', $other), 'text'=>'Check these animals have clean water, shade, and suitable airflow. Do routine handling in cooler hours and watch for unusual distress.'];
    }
    if ($stormRisk) {
        $advice[] = ['title'=>'Rain and shelter: ' . implode(', ', $groups), 'text'=>'Check animal housing is dry and secure, keep feed out of the rain, and make sure animals can reach clean water. Check drains and keep animals away from flooded areas.'];
    }
    if (!$advice) {
        $advice[] = ['title'=>'Animal water check: ' . implode(', ', $groups), 'text'=>'Check that your animals can reach clean drinking water and suitable shelter. Weather advice uses conditions for ' . (string)($weather['location'] ?? 'your selected place') . '.'];
    }
    return $advice;
}

function farm_operations_sms_text(array $tasks): string
{
    $highlights = array_slice($tasks, 0, 3);
    $parts = array_map(fn($task) => $task['title'] . ': ' . $task['text'], $highlights);
    return implode(' | ', $parts);
}

function weather_risk_alert(array $weather): ?array
{
    $rainProbability = (float)($weather['rain_probability'] ?? 0);
    $rainfall = (float)($weather['rainfall'] ?? 0);
    $temperature = (float)($weather['temperature'] ?? 0);
    $wind = (float)($weather['wind_kmh'] ?? ((float)($weather['wind_speed'] ?? 0) * 3.6));
    $condition = strtolower((string)($weather['condition'] ?? ''));

    if (str_contains($condition, 'storm') || $rainProbability >= 0.75 || $rainfall >= 15) {
        return ['code'=>'heavy-rain','title'=>'Heavy rain risk','text'=>'Check field drains and low spots. Keep machinery off saturated ground and delay fertilizer until the heavy-rain risk passes.'];
    }
    if ($wind >= 50) {
        return ['code'=>'strong-wind','title'=>'Strong wind risk','text'=>'Secure shade cloth, fencing, irrigation lines, and loose tools. Check supports and vulnerable structures.'];
    }
    if ($temperature >= 35) {
        return ['code'=>'extreme-heat','title'=>'Extreme heat risk','text'=>'Check water and shade. Prioritize young or flowering crops and livestock during cooler hours.'];
    }
    if ($temperature <= 5) {
        return ['code'=>'cold-risk','title'=>'Very cold conditions','text'=>'Protect sensitive seedlings and check that animals have dry shelter and clean water.'];
    }
    return null;
}
