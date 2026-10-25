<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$weatherPlaces = WEATHER_PLACES;
$userRegion = $user['region'] ?? 'Manzini';
$profileLocations = $weatherPlaces[$userRegion] ?? $weatherPlaces['Manzini'];
$defaultWeatherLocation = ['Hhohho'=>'Mbabane', 'Manzini'=>'Manzini', 'Shiselweni'=>'Nhlangano', 'Lubombo'=>'Siteki'][$user['region'] ?? ''] ?? 'Manzini';
$currentWeatherLocation = ($user['weather_location'] ?? '') ?: $defaultWeatherLocation;
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $farmSize = filter_var($_POST['farm_size'] ?? null, FILTER_VALIDATE_FLOAT);
    $crop = trim($_POST['crop_type'] ?? '');
    $soil = $_POST['soil_quality'] ?? '';
    $farmerType = $_POST['farmer_type'] ?? 'crop';
    $growthStage = $_POST['growth_stage'] ?? 'vegetative';
    $soilType = $_POST['soil_type'] ?? 'loamy';
    $smsOptIn = isset($_POST['sms_opt_in']);
    $weatherLocation = $_POST['weather_location'] ?? '';

    if ($farmSize === false || $farmSize < 0.5 || $farmSize > 100 || ($farmerType === 'crop' && $crop === '') || !in_array($weatherLocation, $profileLocations, true) || !in_array($soil, ['Poor', 'Moderate', 'Good', 'Excellent'], true) || !in_array($farmerType, ['crop','livestock','poultry','fisheries','beekeeping'], true) || !in_array($growthStage, ['planting','vegetative','flowering','harvesting'], true) || !in_array($soilType, ['sandy','loamy','clay'], true)) {
        $message = 'Enter valid farm details before saving.';
    } else {
        update_user_profile($_SESSION['user_email'], $farmSize, $crop, soil_value($soil), $farmerType, $growthStage, $soilType, $smsOptIn, $weatherLocation);
        $message = 'Profile updated.';
        $user = current_user();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Profile | agriSense</title>
</head>
<body class="farmer-dashboard-page">
<header class="topbar farmer-topbar">
    <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation"><a class="text-link" href="dashboard.php">My farm</a><a class="text-link" href="about.php">Guidance</a><a class="text-link active" href="profile.php">Farm profile</a><a class="text-link" href="logout.php">Log out</a></nav>
</header>
<main class="narrow profile-page">
    <p class="kicker">Farm details · Keep your advice relevant</p>
    <h1><?= e($user['name']) ?>’s farm profile</h1>
    <p class="profile-lead">Your saved farm information helps tailor weather recommendations, crop suggestions, and seasonal planning.</p>
    <div class="profile-snapshot">
        <article><span>Primary crop</span><strong><?= e($user['crop_type'] ?? 'Not set') ?></strong></article>
        <article><span>Farm size</span><strong><?= e($user['farm_size'] ?? '—') ?> ha</strong></article>
        <article><span>Weather location</span><strong><?= e($currentWeatherLocation) ?></strong></article>
    </div>
    <?php if ($message): ?><div class="alert <?= $message === 'Profile updated.' ? 'success' : 'error' ?>"><?= e($message) ?></div><?php endif; ?>
    <form method="post" class="profile-form profile-form-grid">
        <div class="profile-form-heading"><p class="section-eyebrow">Farm location and scale</p><p>Keep your farm’s size and primary crop up to date.</p></div>
        <label>Region<small>Used for regional crop guidance</small><input value="<?= e($user['region'] ?? '') ?>" disabled></label>
        <label>Nearest weather location<select name="weather_location" required>
            <?php foreach ($profileLocations as $place): ?>
                <option value="<?= e($place) ?>" <?= $currentWeatherLocation === $place ? 'selected' : '' ?>><?= e($place) ?></option>
            <?php endforeach; ?>
        </select><small>Choose the closest town to improve local weather estimates.</small></label>
        <label>Farm size (hectares)<input type="number" name="farm_size" min="0.5" max="100" step="0.1" value="<?= e($user['farm_size'] ?? '') ?>" required></label>
        <label>Primary crop<input name="crop_type" value="<?= e($user['crop_type'] ?? '') ?>" <?= ($user['farmer_type'] ?? 'crop') === 'crop' ? 'required' : '' ?>></label>
        <div class="profile-form-heading"><p class="section-eyebrow">Growing conditions</p><p>These details help the advisory reflect your farm activity and soil.</p></div>
        <label>Soil quality<select name="soil_quality">
            <?php foreach (['Poor', 'Moderate', 'Good', 'Excellent'] as $soil): ?>
                <option <?= soil_label($user['soil_quality'] ?? 2) === $soil ? 'selected' : '' ?>><?= e($soil) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label>Farmer category<select name="farmer_type"><?php foreach (['crop','livestock','poultry','fisheries','beekeeping'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['farmer_type'] ?? 'crop') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label>Growth stage<select name="growth_stage"><?php foreach (['planting','vegetative','flowering','harvesting'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['growth_stage'] ?? 'vegetative') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label>Soil type<select name="soil_type"><?php foreach (['sandy','loamy','clay'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['soil_type'] ?? 'loamy') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <div class="sms-consent-panel"><label class="check"><input type="checkbox" name="sms_opt_in" value="1" <?= !empty($user['sms_opt_in']) ? 'checked' : '' ?>> Automatically send my farm advice by SMS about every two hours.</label><p>Messages are sent to <?= e($user['phone'] ?? 'your saved phone number') ?>. Provider credits may be charged. Uncheck this at any time to stop automatic messages.</p></div>
        <button class="button" type="submit">Save profile <span>→</span></button>
    </form>
</main>
<script>
const activitySelect = document.querySelector('select[name="farmer_type"]');
const cropInput = document.querySelector('input[name="crop_type"]');
function syncCropRequirement() {
    cropInput.required = activitySelect.value === 'crop';
}
activitySelect.addEventListener('change', syncCropRequirement);
syncCropRequirement();
</script>
</body>
</html>
