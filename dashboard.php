<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/weather.php';
require_once __DIR__ . '/includes/ml_integration.php';
require_once __DIR__ . '/modules/farm_operations.php';

require_login();
$user = current_user();
$isCropFarmer = ($user['farmer_type'] ?? 'crop') === 'crop';
$weather = region_weather(($user['weather_location'] ?? '') ?: ($user['region'] ?? 'Manzini'));
$classification = classify_weather($weather);
$recommendations = $isCropFarmer ? recommend_crops($weather, $user['region'] ?? 'Manzini') : [];
$yield = $isCropFarmer ? predict_yield($weather, $user) : null;
$operations = farm_operations_advice($weather, $user);
$todayFocus = $operations[0] ?? ['title'=>'Check current conditions', 'text'=>'Review the forecast and your farm before choosing today’s work.'];
$operationsChecklist = array_slice($operations, 1);
$allMessages = read_json(MESSAGES_FILE, []);
$messages = array_values(array_filter($allMessages, fn($message) => ($message['email'] ?? '') === ($user['email'] ?? '')));
$flash = get_flash();
$smsConfigured = LUNYAZI_SMS_API_KEY !== '';
$smsRecipient = preg_replace('/[^\d+]/', '', (string)($user['phone'] ?? ''));
$soilNames = ['sandy'=>'Sandy soil', 'loamy'=>'Loamy soil', 'clay'=>'Clay soil'];
$soilName = $soilNames[$user['soil_type'] ?? ''] ?? 'Soil profile needed';
$weatherUpdated = !empty($weather['cached_at']) ? date('H:i', (int)$weather['cached_at']) : '—';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Farm dashboard | agriSense</title>
</head>
<body class="farmer-dashboard-page">
<header class="topbar farmer-topbar">
    <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation">
        <a class="text-link active" href="dashboard.php">My farm</a>
        <a class="text-link" href="about.php">Guidance</a>
        <a class="text-link" href="profile.php">Farm profile</a>
        <a class="text-link" href="logout.php">Log out</a>
    </nav>
</header>

<main class="dashboard farmer-dashboard">
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <section class="farm-welcome">
        <div class="welcome-copy">
            <p class="welcome-kicker">Your field journal <span></span> <?= e(date('j F Y')) ?></p>
            <h1>Good day, <?= e($user['name']) ?>.</h1>
            <p>Here’s what’s happening across your farm today.</p>
            <div class="farm-tags">
                <span><?= e($user['region'] ?? 'Eswatini') ?></span>
                <span><?= e($user['crop_type'] ?? 'Your crops') ?></span>
                <span><?= e($user['farm_size'] ?? '—') ?> hectares</span>
            </div>
        </div>
    </section>

    <div class="farm-dashboard-grid">
        <section class="panel weather-panel">
            <div class="section-heading">
                <div>
                    <p class="section-eyebrow">Weather on your farm</p>
                    <h2><?= e($weather['location']) ?></h2>
                </div>
                <div class="weather-source"><span class="source-dot <?= ($weather['weather_source'] ?? '') === 'OpenWeatherMap live' ? 'is-live' : '' ?>"></span><?= e($weather['weather_source'] ?? 'Weather') ?> <span>· <?= e($weatherUpdated) ?></span></div>
            </div>
            <?php if (!empty($weather['weather_error'])): ?><p class="weather-warning">Live weather unavailable: <?= e($weather['weather_error']) ?></p><?php endif; ?>
            <div class="weather-summary">
                <div class="weather-temperature">
                    <span class="weather-icon" aria-hidden="true">☁</span>
                    <div><strong><?= e(number_format((float)$weather['temperature'], 1)) ?>°</strong><span><?= e(ucfirst((string)$weather['condition'])) ?></span></div>
                </div>
                <div class="weather-context"><span>Field conditions</span><strong><?= e($classification['classification']) ?></strong><small><?= e($classification['confidence'] ?? 78) ?>% confidence</small></div>
            </div>
            <div class="weather-metrics">
                <div><span>Humidity</span><strong><?= e($weather['humidity']) ?><small>%</small></strong></div>
                <div><span>Rain chance · 24h</span><strong><?= e(round((float)($weather['rain_probability'] ?? 0) * 100)) ?><small>%</small></strong></div>
                <div><span>Wind</span><strong><?= e(number_format((float)($weather['wind_kmh'] ?? ((float)$weather['wind_speed'] * 3.6)), 1)) ?><small> km/h</small></strong></div>
                <div><span>Cloud cover</span><strong><?= e($weather['cloud_pct'] ?? '—') ?><small>%</small></strong></div>
            </div>
        </section>

        <section class="panel action-panel">
            <div class="action-heading"><p class="section-eyebrow">Today’s first check</p></div>
            <h2><?= e($todayFocus['title']) ?></h2>
            <p class="action-copy"><?= e($todayFocus['text']) ?></p>
            <div class="action-meta"><span class="action-marker"></span>Based on today’s weather and farm activity</div>
            <?php if (!empty($user['sms_opt_in'])): ?>
                <div class="automatic-sms-state"><span class="action-marker"></span><div><strong>Automatic SMS is on</strong><p>Advice goes to <?= e($smsRecipient ?: 'your saved number') ?> about every two hours.</p></div></div>
                <?php if (!$smsConfigured): ?><p class="sms-setup-note">Delivery is paused until a Lunyazi API key is added to the local secrets file.</p><?php endif; ?>
            <?php else: ?>
                <div class="automatic-sms-state is-off"><span class="action-marker"></span><div><strong>Automatic SMS is off</strong><p>Enable it from your <a href="profile.php">farm profile</a> to receive advice automatically.</p></div></div>
            <?php endif; ?>
        </section>

        <section class="panel crop-profile-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Your farm</p><h2>Farm profile</h2></div><a href="profile.php">Edit</a></div>
            <?php if ($isCropFarmer): ?>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Main crop</small><strong><?= e($user['crop_type'] ?? 'Add your crop') ?></strong></div></div>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Soil type</small><strong><?= e($soilName) ?></strong></div></div>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Growth stage</small><strong><?= e(ucfirst($user['growth_stage'] ?? 'Vegetative')) ?></strong></div></div>
            <?php else: ?>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Weather location</small><strong><?= e(($user['weather_location'] ?? '') ?: ($user['region'] ?? 'Eswatini')) ?></strong></div></div>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Farm area</small><strong><?= e($user['farm_size'] ?? '—') ?> hectares</strong></div></div>
            <?php endif; ?>
            <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Farm activity</small><strong><?= e(ucfirst($user['farmer_type'] ?? 'Crop')) ?></strong></div></div>
        </section>

        <?php if ($isCropFarmer): ?>
        <section class="panel yield-panel">
            <p class="section-eyebrow">Harvest outlook</p>
            <h2><?= e(number_format((float)$yield['yield_kg_ha'])) ?><small> kg/ha</small></h2>
            <span class="yield-status"><?= e($yield['status']) ?> outlook</span>
            <div class="yield-rule"><span style="width:<?= e(min(100, max(8, (int)$yield['yield_kg_ha'] / 70))) ?>%"></span></div>
            <p>Estimated farm harvest <strong><?= e(number_format((float)$yield['total_yield_kg'])) ?> kg</strong> across <?= e($user['farm_size'] ?? '—') ?> hectares.</p>
            <small class="quiet-note">Estimate based on current weather and your saved soil profile.</small>
        </section>

        <section class="panel crop-fit-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Planting ideas</p><h2>Crops that fit</h2></div></div>
            <?php foreach ($recommendations as $item): ?>
                <div class="crop-fit-row"><div class="crop-fit-top"><strong><?= e($item['crop']) ?></strong><span><?= e($item['confidence']) ?>%</span></div><p><?= e($item['reason']) ?></p><div class="crop-fit-track"><span style="width:<?= e(min(100, (int)$item['confidence'])) ?>%"></span></div></div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="panel field-notes-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Daily checklist</p><h2>Farm operations for today</h2><p class="operations-intro">Practical tasks for water, soil, crop care, and your selected farm activity.</p></div></div>
            <div class="field-notes-list">
                <?php foreach ($operationsChecklist as $item): ?>
                    <article class="field-note"><span class="operation-check" aria-hidden="true">•</span><div><h3><?= e($item['title']) ?></h3><p><?= e($item['text']) ?></p></div></article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($messages): ?>
            <section class="panel message-history-panel">
                <div class="section-heading compact-heading"><div><p class="section-eyebrow">Saved communications</p><h2>Advice history</h2></div></div>
                <?php foreach (array_slice(array_reverse($messages), 0, 4) as $message): ?><p class="history-entry"><strong><?= e($message['sent_at']) ?></strong><span><?= e($message['message']) ?></span></p><?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
