<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/weather.php';
require_once __DIR__ . '/includes/ml_integration.php';
require_once __DIR__ . '/modules/farm_operations.php';

require_login();
$user = current_user();
$recoveryCode = (string)($_SESSION['new_recovery_code'] ?? '');
unset($_SESSION['new_recovery_code']);
$farmEnterprises = get_user_enterprises((string)$user['email']);
$registeredCrops = array_values(array_map(static fn(array $row): string => $row['name'], array_filter($farmEnterprises, static fn(array $row): bool => $row['enterprise_type'] === 'crop')));
$registeredAnimals = array_values(array_map(static fn(array $row): string => $row['name'], array_filter($farmEnterprises, static fn(array $row): bool => $row['enterprise_type'] === 'animal')));
$registeredOther = array_values(array_map(static fn(array $row): string => $row['name'], array_filter($farmEnterprises, static fn(array $row): bool => $row['enterprise_type'] === 'other')));
$isCropFarmer = ($user['farmer_type'] ?? 'crop') === 'crop';
$mainWeatherPlace = ($user['weather_location'] ?? '') ?: ($user['region'] ?? 'Manzini');
$refreshWeather = ($_GET['refresh_weather'] ?? '') === '1';
$weather = region_weather($mainWeatherPlace, $refreshWeather);
$siteLocations = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['weather_location'] ?? '')) ?: $mainWeatherPlace, $farmEnterprises))));
$siteWeather = [];
foreach ($siteLocations as $siteLocation) {
    if ($siteLocation !== $mainWeatherPlace) $siteWeather[$siteLocation] = region_weather($siteLocation);
}
$hasCurrentWeather = is_numeric($weather['temperature'] ?? null);
$animalWeatherAdvice = $hasCurrentWeather ? farm_animal_weather_advice($weather, $registeredAnimals) : [];
if ($refreshWeather) {
    if (!empty($weather['weather_error'])) {
        flash('error', 'Could not refresh live weather: ' . $weather['weather_error']);
    } else {
        flash('success', 'Weather refreshed from OpenWeatherMap.');
    }
    header('Location: dashboard.php');
    exit;
}
$weatherAlert = $hasCurrentWeather ? weather_risk_alert($weather) : null;
$classification = $hasCurrentWeather ? classify_weather($weather) : ['classification'=>'Unavailable', 'confidence'=>null];
$recommendations = $isCropFarmer && $hasCurrentWeather ? recommend_crops($weather, $user['region'] ?? 'Manzini') : [];
$operations = farm_operations_advice($weather, $user);
$todayFocus = $operations[0] ?? ['title'=>'Check current conditions', 'text'=>'Review the forecast and your farm before choosing today’s work.'];
if (!$hasCurrentWeather) $todayFocus = ['title'=>'Check local conditions first', 'text'=>'Current weather for ' . ($weather['location'] ?? 'your selected place') . ' is unavailable. Check the field before making weather-based decisions.'];
$operationsChecklist = array_slice($operations, 1);
$allMessages = read_json(MESSAGES_FILE, []);
$messages = array_values(array_filter($allMessages, fn($message) => ($message['email'] ?? '') === ($user['email'] ?? '')));
$flash = get_flash();
$smsConfigured = LUNYAZI_SMS_API_KEY !== '';
$smsRecipient = preg_replace('/[^\d+]/', '', (string)($user['phone'] ?? ''));
$soilNames = ['sandy'=>'Sandy soil', 'loamy'=>'Loamy soil', 'clay'=>'Clay soil'];
$soilName = $soilNames[$user['soil_type'] ?? ''] ?? 'Soil profile needed';
$weatherUpdated = !empty($weather['cached_at'])
    ? (new DateTimeImmutable('@' . (int)$weather['cached_at']))->setTimezone(new DateTimeZone('Africa/Mbabane'))->format('H:i')
    : '—';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Farm dashboard | agriSense</title>
</head>
<body class="farmer-dashboard-page" data-farmer-key="<?= e(strtolower((string)$user['email'])) ?>" data-weather-location="<?= e($weather['location'] ?? '') ?>" data-weather-temperature="<?= e($weather['temperature'] ?? '') ?>" data-weather-condition="<?= e($weather['condition'] ?? '') ?>" data-weather-rain="<?= e(round((float)($weather['rain_probability'] ?? 0) * 100)) ?>" data-weather-advice="<?= e($todayFocus['title'] . ': ' . $todayFocus['text']) ?>">
<header class="topbar farmer-topbar">
    <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation">
        <a class="text-link active" href="dashboard.php">My farm</a>
        <a class="text-link" href="farm_journal.php">Farm journal</a>
        <a class="text-link" href="about.php">Guidance</a>
        <a class="text-link" href="profile.php">Farm profile</a>
        <a class="text-link" href="logout.php">Log out</a>
    </nav>
</header>

<main class="dashboard farmer-dashboard">
    <?php if ($recoveryCode !== ''): ?><div class="recovery-code-notice" role="alert"><strong>Save your account recovery code</strong><p>This private code is shown once. Keep it somewhere safe so you can reset your password without email.</p><code><?= e($recoveryCode) ?></code></div><?php endif; ?>
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <section class="farm-welcome">
        <div class="welcome-copy">
            <p class="welcome-kicker">Your field journal <span></span> <?= e(date('j F Y')) ?></p>
            <h1>Good day, <?= e($user['name']) ?>.</h1>
            <p>Here’s what’s happening across your farm today.</p>
            <div class="farm-tags">
                <span><?= e($user['region'] ?? 'Eswatini') ?></span>
                <span><?= e($registeredCrops ? implode(', ', $registeredCrops) : ($registeredAnimals ? implode(', ', $registeredAnimals) : 'Your farm')) ?></span>
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
            <?php if (!empty($weather['weather_error'])): ?><p class="weather-warning"><?= $hasCurrentWeather ? 'Live weather for ' . e($weather['location']) . ' is unavailable. Showing cached data; check the update time before acting.' : 'Current weather for ' . e($weather['location']) . ' is unavailable. Try refreshing when you have a connection.' ?></p><?php endif; ?>
            <?php if ($weatherAlert): ?><div class="weather-risk-alert"><strong><?= e($weatherAlert['title']) ?></strong><p><?= e($weatherAlert['text']) ?></p><small>Alert for <?= e($weather['location']) ?>. Check local field conditions before acting.</small></div><?php elseif ($hasCurrentWeather): ?><p class="weather-all-clear">No high-risk weather alert for <?= e($weather['location']) ?> right now.</p><?php else: ?><p class="weather-all-clear">No weather-based alert is available for <?= e($weather['location']) ?>.</p><?php endif; ?>
            <a class="weather-refresh weather-refresh-standalone" href="dashboard.php?refresh_weather=1">Refresh weather now</a>
            <div class="weather-summary">
                <div class="weather-temperature">
                    <span class="weather-icon" aria-hidden="true">☁</span>
                    <div><strong><?= $hasCurrentWeather ? e(number_format((float)$weather['temperature'], 1)) . '°' : '—' ?></strong><span><?= $hasCurrentWeather ? e(ucfirst((string)$weather['condition'])) : 'Unavailable' ?></span></div>
                </div>
                <div class="weather-context"><span>Field conditions</span><strong><?= e($classification['classification']) ?></strong><?php if ($hasCurrentWeather): ?><small>Estimate from current conditions</small><?php endif; ?></div>
            </div>
            <div class="weather-metrics">
                <div><span>Humidity</span><strong><?= $hasCurrentWeather ? e($weather['humidity']) . '<small>%</small>' : '—' ?></strong></div>
                <div><span>Rain chance · 24h</span><strong><?= $hasCurrentWeather ? e(round((float)($weather['rain_probability'] ?? 0) * 100)) . '<small>%</small>' : '—' ?></strong></div>
                <div><span>Wind</span><strong><?= $hasCurrentWeather ? e(number_format((float)($weather['wind_kmh'] ?? ((float)$weather['wind_speed'] * 3.6)), 1)) . '<small> km/h</small>' : '—' ?></strong></div>
                <div><span>Cloud cover</span><strong><?= $hasCurrentWeather ? e($weather['cloud_pct'] ?? '—') . '<small>%</small>' : '—' ?></strong></div>
            </div>
        </section>

        <?php if ($registeredAnimals): ?><section class="panel animal-weather-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Animal care</p><h2>Weather check for your animals</h2></div></div>
            <?php if (!$hasCurrentWeather): ?><p>Weather-based animal reminders are paused because current conditions for <?= e($weather['location']) ?> are unavailable.</p>
            <?php else: ?><p class="journal-help">Based on current weather in <?= e($weather['location']) ?> and the animal groups in your farm profile.</p>
                <?php foreach ($animalWeatherAdvice as $item): ?><article class="field-note"><span class="operation-check" aria-hidden="true">•</span><div><h3><?= e($item['title']) ?></h3><p><?= e($item['text']) ?></p><a href="farm_journal.php?new=task&amp;title=<?= rawurlencode($item['title']) ?>&amp;note=<?= rawurlencode($item['text']) ?>">Add this check to my task list</a></div></article><?php endforeach; ?>
            <?php endif; ?>
        </section><?php endif; ?>

        <?php if ($siteWeather): ?><section class="panel site-weather-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Other farm sites</p><h2>Weather by place</h2></div></div>
            <div class="site-weather-list">
                <?php foreach ($siteWeather as $siteLocation => $siteConditions): $siteHasWeather = is_numeric($siteConditions['temperature'] ?? null); ?>
                    <article class="site-weather-card"><strong><?= e($siteLocation) ?></strong><span><?= $siteHasWeather ? e(number_format((float)$siteConditions['temperature'], 1)) . '° · ' . e(ucfirst((string)$siteConditions['condition'])) : 'Current weather unavailable' ?></span><small><?= $siteHasWeather ? 'Rain chance next 24h: ' . e(round((float)$siteConditions['rain_probability'] * 100)) . '%' : 'Check local conditions before acting.' ?></small></article>
                <?php endforeach; ?>
            </div>
        </section><?php endif; ?>

        <section class="panel action-panel">
            <div class="action-heading"><p class="section-eyebrow">Today’s first check</p></div>
            <h2><?= e($todayFocus['title']) ?></h2>
            <p class="action-copy"><?= e($todayFocus['text']) ?></p>
            <div class="action-meta"><span class="action-marker"></span><?= $hasCurrentWeather ? 'Based on weather at ' . e($weather['location']) . ' and your farm activity' : 'Weather guidance is paused until conditions for ' . e($weather['location']) . ' are available' ?></div>
            <a class="weather-refresh action-plan-link" href="farm_journal.php?domain=<?= $isCropFarmer ? 'crops' : 'animals' ?>&amp;new=task&amp;title=<?= rawurlencode($todayFocus['title']) ?>&amp;note=<?= rawurlencode($todayFocus['text']) ?>">Add this check to my task list</a>
            <?php if (!empty($user['sms_opt_in'])): ?>
                <div class="automatic-sms-state <?= $smsConfigured ? '' : 'is-off' ?>"><span class="action-marker"></span><div><strong><?= $smsConfigured ? 'Weather-risk SMS alerts are on' : 'SMS delivery is paused' ?></strong><p><?= $smsConfigured ? 'Urgent weather alerts go to ' . e($smsRecipient ?: 'your saved number') . ' when a high-risk condition is detected.' : 'Your profile is opted in, but the SMS provider is not configured.' ?></p></div></div>
                <?php if (!$smsConfigured): ?><p class="sms-setup-note">Add <code>LUNYAZI_SMS_API_KEY</code> to the Vercel project’s Production Environment Variables to enable delivery.</p><?php endif; ?>
            <?php else: ?>
                <div class="automatic-sms-state is-off"><span class="action-marker"></span><div><strong>Automatic SMS is off</strong><p>Enable it from your <a href="profile.php">farm profile</a> to receive advice automatically.</p></div></div>
            <?php endif; ?>
        </section>

        <section class="panel crop-profile-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Your farm</p><h2>Farm profile</h2></div><a href="profile.php">Edit</a></div>
            <?php if ($registeredCrops): ?>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Crops and vegetables</small><strong><?= e(implode(', ', $registeredCrops)) ?></strong></div></div>
            <?php endif; ?>
            <?php if ($registeredAnimals): ?>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Animal groups</small><strong><?= e(implode(', ', $registeredAnimals)) ?></strong></div></div>
            <?php endif; ?>
            <?php if ($registeredOther): ?>
                <div class="profile-detail"><span class="detail-mark" aria-hidden="true"></span><div><small>Other enterprises</small><strong><?= e(implode(', ', $registeredOther)) ?></strong></div></div>
            <?php endif; ?>
            <?php if ($isCropFarmer): ?>
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
            <p class="section-eyebrow">Harvest records</p>
            <h2>Track actual results</h2>
            <p>Record harvests by field and crop in your farm journal. The dashboard avoids a generic yield estimate that may not match your crop.</p>
            <a class="weather-refresh" href="farm_journal.php?domain=crops&amp;new=harvest">Record a harvest</a>
        </section>

        <section class="panel crop-fit-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Planting ideas</p><h2>Weather-based crop ideas</h2></div></div>
            <p class="crop-fit-disclaimer">These are starting points based on weather at <?= e($weather['location']) ?>, not confirmation that a crop suits your soil, seed, water, or planting calendar.</p>
            <?php if (!$hasCurrentWeather): ?><p>Crop suggestions are paused until weather for <?= e($weather['location']) ?> is available.</p><?php endif; ?>
            <?php foreach ($recommendations as $item): ?>
                <div class="crop-fit-row"><div class="crop-fit-top"><strong><?= e($item['crop']) ?></strong><span>Idea</span></div><p><?= e($item['reason']) ?></p></div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="panel field-notes-panel">
            <div class="section-heading compact-heading"><div><p class="section-eyebrow">Daily checklist</p><h2>Farm operations for today</h2><p class="operations-intro">Practical tasks for water, soil, crop care, and your selected farm activity.</p></div></div>
            <div class="field-notes-list">
                <?php foreach ($operationsChecklist as $item): ?>
                    <article class="field-note"><span class="operation-check" aria-hidden="true">•</span><div><h3><?= e($item['title']) ?></h3><p><?= e($item['text']) ?></p><a href="farm_journal.php?domain=<?= $isCropFarmer ? 'crops' : 'animals' ?>&amp;new=task&amp;title=<?= rawurlencode($item['title']) ?>&amp;note=<?= rawurlencode($item['text']) ?>">Plan this task</a></div></article>
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

