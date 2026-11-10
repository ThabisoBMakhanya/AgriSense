<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$weatherPlaces = WEATHER_PLACES;
$allWeatherLocations = array_values(array_unique(array_merge(...array_values($weatherPlaces))));
$userRegion = $user['region'] ?? 'Manzini';
$profileLocations = $weatherPlaces[$userRegion] ?? $weatherPlaces['Manzini'];
$defaultWeatherLocation = ['Hhohho'=>'Mbabane', 'Manzini'=>'Manzini', 'Shiselweni'=>'Nhlangano', 'Lubombo'=>'Siteki'][$user['region'] ?? ''] ?? 'Manzini';
$currentWeatherLocation = ($user['weather_location'] ?? '') ?: $defaultWeatherLocation;
$enterprises = get_user_enterprises((string)$user['email']);
$enterpriseFormRows = $enterprises ?: [['enterprise_type'=>'', 'name'=>'', 'quantity'=>'', 'unit'=>'', 'growth_stage'=>'', 'notes'=>'', 'weather_location'=>'']];
$flash = get_flash();
$message = $flash['message'] ?? '';
$recoveryCode = (string)($_SESSION['new_recovery_code'] ?? '');
unset($_SESSION['new_recovery_code']);
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid()) $errors[] = 'This profile form expired. Reload the page and try again.';
    $farmSize = filter_var($_POST['farm_size'] ?? null, FILTER_VALIDATE_FLOAT);
    $soil = $_POST['soil_quality'] ?? '';
    $farmerType = $_POST['farmer_type'] ?? 'crop';
    $growthStage = $_POST['growth_stage'] ?? 'vegetative';
    $soilType = $_POST['soil_type'] ?? 'loamy';
    $smsOptIn = isset($_POST['sms_opt_in']);
    $weatherLocation = $_POST['weather_location'] ?? '';
    $enterpriseFormRows = [];
    $postedNames = $_POST['enterprise_name'] ?? [];
    if (!is_array($postedNames) || count($postedNames) > 30) {
        $errors[] = 'Add no more than 30 crop, animal, or other enterprise records.';
        $postedNames = [];
    }
    foreach ($postedNames as $index => $rawName) {
        $name = trim((string)$rawName);
        $type = (string)($_POST['enterprise_type'][$index] ?? '');
        $quantityValue = trim((string)($_POST['enterprise_quantity'][$index] ?? ''));
        $quantity = $quantityValue === '' ? 0.0 : filter_var($quantityValue, FILTER_VALIDATE_FLOAT);
        $unit = trim((string)($_POST['enterprise_unit'][$index] ?? ''));
        $stage = (string)($_POST['enterprise_stage'][$index] ?? '');
        $notes = trim((string)($_POST['enterprise_notes'][$index] ?? ''));
        $siteLocation = trim((string)($_POST['enterprise_location'][$index] ?? ''));
        if ($type === '' && $name === '') continue;
        if (!in_array($type, ['crop','animal','other'], true) || $name === '' || strlen($name) > 150 || $quantity === false || $quantity < 0 || $quantity > 1000000000 || strlen($unit) > 30 || strlen($notes) > 2000 || ($siteLocation !== '' && !in_array($siteLocation, $allWeatherLocations, true)) || ($type === 'crop' && $stage !== '' && !in_array($stage, ['planting','vegetative','flowering','harvesting'], true))) {
            $errors[] = 'Complete each crop, animal, or other enterprise with a valid name and quantity.';
            continue;
        }
        $enterpriseFormRows[] = ['enterprise_type'=>$type,'name'=>$name,'quantity'=>$quantity,'unit'=>$unit,'growth_stage'=>$type === 'crop' ? $stage : '','notes'=>$notes,'weather_location'=>$siteLocation];
    }
    if (!$enterpriseFormRows) $errors[] = 'Add at least one crop, animal group, or other farm enterprise.';
    if ($farmSize === false || $farmSize < 0.5 || $farmSize > 100) $errors[] = 'Farm size must be between 0.5 and 100 hectares.';
    if (!in_array($weatherLocation, $profileLocations, true)) $errors[] = 'Choose a valid weather location for your region.';
    if (!in_array($farmerType, ['crop','livestock','poultry','fisheries','beekeeping'], true)) $errors[] = 'Choose a valid primary farming activity.';
    if ($farmerType === 'crop' && (!in_array($soil, ['Poor', 'Moderate', 'Good', 'Excellent'], true) || !in_array($growthStage, ['planting','vegetative','flowering','harvesting'], true) || !in_array($soilType, ['sandy','loamy','clay'], true))) $errors[] = 'Complete your crop and soil details.';
    $requiredEnterpriseType = $farmerType === 'crop' ? 'crop' : (in_array($farmerType, ['livestock','poultry'], true) ? 'animal' : 'other');
    if ($enterpriseFormRows && !in_array($requiredEnterpriseType, array_column($enterpriseFormRows, 'enterprise_type'), true)) $errors[] = 'Add an enterprise matching your primary farming activity.';
    if (!$errors) {
        $primaryCrop = '';
        foreach ($enterpriseFormRows as $enterprise) if ($enterprise['enterprise_type'] === 'crop') { $primaryCrop = substr($enterprise['name'], 0, 60); break; }
        try {
            $connection = db();
            $connection->beginTransaction();
            update_user_profile($_SESSION['user_email'], (float)$farmSize, $primaryCrop, soil_value($soil), $farmerType, $growthStage, $soilType, $smsOptIn, $weatherLocation);
            save_user_enterprises((string)$user['email'], $enterpriseFormRows);
            $connection->commit();
            $message = 'Profile updated.';
            $user = current_user();
            $enterprises = get_user_enterprises((string)$user['email']);
            $enterpriseFormRows = $enterprises ?: [['enterprise_type'=>'', 'name'=>'', 'quantity'=>'', 'unit'=>'', 'growth_stage'=>'', 'notes'=>'', 'weather_location'=>'']];
        } catch (Throwable $exception) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('Farm profile update failed: ' . $exception->getMessage());
            $errors[] = 'Profile could not be saved. Please try again.';
        }
    }
}
$cropNames = array_values(array_map(static fn(array $row): string => $row['name'], array_filter($enterprises, static fn(array $row): bool => $row['enterprise_type'] === 'crop')));
$animalNames = array_values(array_map(static fn(array $row): string => $row['name'], array_filter($enterprises, static fn(array $row): bool => $row['enterprise_type'] === 'animal')));
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Profile | agriSense</title>
</head>
<body class="farmer-dashboard-page" data-farmer-key="<?= e(strtolower((string)$user['email'])) ?>">
<header class="topbar farmer-topbar">
    <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation"><a class="text-link" href="dashboard.php">My farm</a><a class="text-link" href="farm_journal.php">Farm journal</a><a class="text-link" href="about.php">Guidance</a><a class="text-link active" href="profile.php">Farm profile</a><a class="text-link" href="logout.php">Log out</a></nav>
</header>
<main class="narrow profile-page">
    <p class="kicker">Farm details · Keep your advice relevant</p>
    <h1><?= e($user['name']) ?>’s farm profile</h1>
    <p class="profile-lead">Register every crop, animal group, and other enterprise on your farm so your profile reflects the whole operation.</p>
    <div class="profile-snapshot">
        <article><span>Crops and vegetables</span><strong><?= e($cropNames ? implode(', ', $cropNames) : 'None added') ?></strong></article>
        <article><span>Animal groups</span><strong><?= e($animalNames ? implode(', ', $animalNames) : 'None added') ?></strong></article>
        <article><span>Farm size</span><strong><?= e($user['farm_size'] ?? '—') ?> ha</strong></article>
        <article><span>Weather location</span><strong><?= e($currentWeatherLocation) ?></strong></article>
    </div>
    <?php if ($message): ?><div class="alert <?= $message === 'Profile updated.' ? 'success' : 'error' ?>"><?= e($message) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert error" id="form-errors" tabindex="-1" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="profile-form profile-form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="profile-form-heading"><p class="section-eyebrow">Farm location and scale</p><p>Keep your farm’s size and primary activity up to date.</p></div>
        <label>Region<small>Used for regional crop guidance</small><input value="<?= e($user['region'] ?? '') ?>" disabled></label>
        <label>Nearest weather location<select name="weather_location" required>
            <?php foreach ($profileLocations as $place): ?>
                <option value="<?= e($place) ?>" <?= $currentWeatherLocation === $place ? 'selected' : '' ?>><?= e($place) ?></option>
            <?php endforeach; ?>
        </select><small>Choose the closest town to improve local weather estimates.</small></label>
        <label>Farm size (hectares)<input type="number" name="farm_size" min="0.5" max="100" step="0.1" value="<?= e($user['farm_size'] ?? '') ?>" required></label>
        <section class="farm-enterprise-editor profile-enterprise-editor" aria-labelledby="profile-enterprise-heading">
            <div class="enterprise-heading"><div><p class="section-eyebrow">Your farm enterprises</p><h2 id="profile-enterprise-heading">Crops, animals, and other enterprises</h2><p>Add as many crops, livestock groups, or other activities as you need.</p></div><button class="button button-light" type="button" data-add-enterprise>Add another</button></div>
            <div class="farm-enterprise-list" data-enterprise-list>
                <?php foreach ($enterpriseFormRows as $enterprise): ?>
                    <div class="farm-enterprise-row">
                        <label>Type<select name="enterprise_type[]" required><option value="" disabled <?= empty($enterprise['enterprise_type']) ? 'selected' : '' ?>></option><?php foreach (['crop'=>'Crop or vegetable','animal'=>'Animal group','other'=>'Other enterprise'] as $typeValue=>$typeLabel): ?><option value="<?= e($typeValue) ?>" <?= ($enterprise['enterprise_type'] ?? '') === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option><?php endforeach; ?></select></label>
                        <label>Name<input name="enterprise_name[]" value="<?= e($enterprise['name'] ?? '') ?>" maxlength="150" required></label>
                        <label>Area or number<input type="number" name="enterprise_quantity[]" min="0" step="0.001" value="<?= e($enterprise['quantity'] ?? '') ?>"></label>
                        <label>Unit<input name="enterprise_unit[]" value="<?= e($enterprise['unit'] ?? '') ?>" maxlength="30"></label>
                        <label>Weather place<select name="enterprise_location[]"><option value="">Use my main farm place</option><?php foreach ($allWeatherLocations as $place): ?><option value="<?= e($place) ?>" <?= ($enterprise['weather_location'] ?? '') === $place ? 'selected' : '' ?>><?= e($place) ?></option><?php endforeach; ?></select></label>
                        <label data-crop-stage>Growth stage<select name="enterprise_stage[]"><option value="" <?= empty($enterprise['growth_stage']) ? 'selected' : '' ?>></option><?php foreach (['planting','vegetative','flowering','harvesting'] as $stage): ?><option value="<?= e($stage) ?>" <?= ($enterprise['growth_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(ucfirst($stage)) ?></option><?php endforeach; ?></select></label>
                        <label>Notes<input name="enterprise_notes[]" value="<?= e($enterprise['notes'] ?? '') ?>" maxlength="2000"></label>
                        <button class="button button-light enterprise-remove" type="button" data-remove-enterprise>Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template id="farm-enterprise-template"><div class="farm-enterprise-row">
                <label>Type<select name="enterprise_type[]" required><option value="" disabled selected></option><option value="crop">Crop or vegetable</option><option value="animal">Animal group</option><option value="other">Other enterprise</option></select></label>
                <label>Name<input name="enterprise_name[]" maxlength="150" required></label>
                <label>Area or number<input type="number" name="enterprise_quantity[]" min="0" step="0.001"></label>
                <label>Unit<input name="enterprise_unit[]" maxlength="30"></label>
                <label>Weather place<select name="enterprise_location[]"><option value="">Use my main farm place</option><?php foreach ($allWeatherLocations as $place): ?><option value="<?= e($place) ?>"><?= e($place) ?></option><?php endforeach; ?></select></label>
                <label data-crop-stage>Growth stage<select name="enterprise_stage[]"><option value=""></option><option value="planting">Planting</option><option value="vegetative">Vegetative</option><option value="flowering">Flowering</option><option value="harvesting">Harvesting</option></select></label>
                <label>Notes<input name="enterprise_notes[]" maxlength="2000"></label>
                <button class="button button-light enterprise-remove" type="button" data-remove-enterprise>Remove</button>
            </div></template>
        </section>
        <div class="profile-form-heading"><p class="section-eyebrow">Farm activity</p><p>Only crop farms need crop stage and soil details.</p></div>
        <label>Primary farming activity<select name="farmer_type"><?php foreach (['crop','livestock','poultry','fisheries','beekeeping'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['farmer_type'] ?? 'crop') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <div class="profile-form-heading" data-crop-only><p class="section-eyebrow">Crop and soil details</p><p>These details help tailor crop guidance.</p></div>
        <div class="profile-crop-fields" data-crop-only>
        <label>Soil quality<select name="soil_quality">
            <?php foreach (['Poor', 'Moderate', 'Good', 'Excellent'] as $soil): ?>
                <option <?= soil_label($user['soil_quality'] ?? 2) === $soil ? 'selected' : '' ?>><?= e($soil) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label>Growth stage<select name="growth_stage"><?php foreach (['planting','vegetative','flowering','harvesting'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['growth_stage'] ?? 'vegetative') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        <label>Soil type<select name="soil_type"><?php foreach (['sandy','loamy','clay'] as $value): ?><option value="<?= e($value) ?>" <?= ($user['soil_type'] ?? 'loamy') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="sms-consent-panel"><label class="check"><input type="checkbox" name="sms_opt_in" value="1" <?= !empty($user['sms_opt_in']) ? 'checked' : '' ?>> Send me urgent weather-risk alerts by SMS.</label><p>Alerts go to <?= e($user['phone'] ?? 'your saved phone number') ?> only when high-risk weather is detected. Provider credits may be charged. Uncheck this at any time to stop alerts.</p></div>
        <button class="button" type="submit">Save profile <span>→</span></button>
    </form>
    <section class="recovery-code-panel" id="recovery-code">
        <div class="profile-form-heading"><p class="section-eyebrow">Account safety</p><p>Use a private recovery code to reset your password without email or SMS.</p></div>
        <?php if ($recoveryCode !== ''): ?><div class="recovery-code-notice" role="alert"><strong>Save your new recovery code now</strong><p>It is shown once. Creating a replacement invalidates your previous code.</p><code><?= e($recoveryCode) ?></code></div><?php endif; ?>
        <form method="post" action="rotate_recovery_code.php" class="recovery-code-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-light" type="submit">Generate new recovery code</button></form>
    </section>
</main>
<script src="assets/js/farm-enterprises.js?v=20261025-enterprises-1" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('form-errors')?.focus();
    const activity = document.querySelector('select[name="farmer_type"]');
    if (!activity) return;
    const update = () => document.querySelectorAll('[data-crop-only]').forEach((section) => {
        const show = activity.value === 'crop';
        section.hidden = !show;
        section.querySelectorAll('select,input').forEach((field) => { field.disabled = !show; });
    });
    activity.addEventListener('change', update);
    update();
});
</script>
</body>
</html>
