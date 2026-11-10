<?php
require_once __DIR__ . '/includes/auth.php';
redirect_logged_in();

$errors = [];
$old = $_POST;
$regions = ['Hhohho', 'Manzini', 'Shiselweni', 'Lubombo'];
$weatherPlaces = WEATHER_PLACES;
$allWeatherLocations = array_values(array_unique(array_merge(...array_values($weatherPlaces))));
$crops = ['Maize', 'Sorghum', 'Beans', 'Sweet Potatoes', 'Cabbage', 'Groundnuts', 'Cassava', 'Citrus', 'Sugarcane', 'Cotton', 'Pineapple', 'Mango'];
$enterpriseRows = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid()) $errors[] = 'This registration form expired. Reload the page and try again.';
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $region = $_POST['region'] ?? '';
    $weatherLocation = $_POST['weather_location'] ?? '';
    $farmSize = (float)($_POST['farm_size'] ?? 0);
    $soil = $_POST['soil_quality'] ?? '';
    $farmerType = $_POST['farmer_type'] ?? 'crop';
    $growthStage = $_POST['growth_stage'] ?? 'vegetative';
    $soilType = $_POST['soil_type'] ?? 'loamy';

    if ($name === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if (!preg_match('/^7[6-8]\d{6}$/', $phone)) $errors[] = 'Use an Eswatini mobile number such as 76123456.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== ($_POST['confirm_password'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!in_array($region, $regions, true)) $errors[] = 'Choose your region.';
    if (!in_array($weatherLocation, $weatherPlaces[$region] ?? [], true)) $errors[] = 'Choose a weather location in your region.';
    if ($farmSize < 0.5 || $farmSize > 100) $errors[] = 'Farm size must be between 0.5 and 100 hectares.';
    $enterpriseTypes = $_POST['enterprise_type'] ?? [];
    $enterpriseNames = $_POST['enterprise_name'] ?? [];
    $enterpriseQuantities = $_POST['enterprise_quantity'] ?? [];
    $enterpriseUnits = $_POST['enterprise_unit'] ?? [];
    $enterpriseStages = $_POST['enterprise_stage'] ?? [];
    if (!is_array($enterpriseNames) || count($enterpriseNames) > 30) {
        $errors[] = 'Add no more than 30 crop, animal, or other enterprise records.';
    } else {
        foreach ($enterpriseNames as $index => $rawName) {
            $name = trim((string)$rawName);
            $type = is_array($enterpriseTypes) ? (string)($enterpriseTypes[$index] ?? '') : '';
            $siteLocation = is_array($_POST['enterprise_location'] ?? null) ? trim((string)($_POST['enterprise_location'][$index] ?? '')) : '';
            $quantityValue = trim((string)($enterpriseQuantities[$index] ?? ''));
            $unit = trim((string)($enterpriseUnits[$index] ?? ''));
            $stage = (string)($enterpriseStages[$index] ?? '');
            if ($name === '' && $type === '') continue;
            $quantity = $quantityValue === '' ? 0.0 : filter_var($quantityValue, FILTER_VALIDATE_FLOAT);
            if (!in_array($type, ['crop', 'animal', 'other'], true) || $name === '' || strlen($name) > 150 || $quantity === false || $quantity < 0 || $quantity > 1000000000 || strlen($unit) > 30 || ($siteLocation !== '' && !in_array($siteLocation, $allWeatherLocations, true)) || ($type === 'crop' && $stage !== '' && !in_array($stage, ['planting','vegetative','flowering','harvesting'], true))) {
                $errors[] = 'Complete each crop, animal, or other farm enterprise with a valid name and quantity.';
                continue;
            }
            $enterpriseRows[] = ['enterprise_type'=>$type,'name'=>$name,'quantity'=>$quantity,'unit'=>$unit,'growth_stage'=>$type === 'crop' ? $stage : '','notes'=>'','weather_location'=>$siteLocation];
        }
    }
    if (!$enterpriseRows) $errors[] = 'Add at least one crop, animal group, or other farm enterprise.';
    $requiredEnterpriseType = $farmerType === 'crop' ? 'crop' : (in_array($farmerType, ['livestock','poultry'], true) ? 'animal' : 'other');
    if ($enterpriseRows && !in_array($requiredEnterpriseType, array_column($enterpriseRows, 'enterprise_type'), true)) $errors[] = 'Add an enterprise matching your primary farming activity.';
    if (!in_array($farmerType, ['crop','livestock','poultry','fisheries','beekeeping'], true)) $errors[] = 'Choose a valid primary farming activity.';
    if ($farmerType === 'crop' && (!in_array($soil, ['Poor', 'Moderate', 'Good', 'Excellent'], true) || !in_array($growthStage, ['planting','vegetative','flowering','harvesting'], true) || !in_array($soilType, ['sandy','loamy','clay'], true))) $errors[] = 'Complete your crop and soil details.';
    if (!isset($_POST['terms'])) $errors[] = 'You must accept the terms.';

    if (!$errors) {
        try {
            if (find_user_by_email($email) !== null) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $recoveryCode = create_recovery_code();
                create_user([
                    'email' => $email,
                    'name' => $name,
                    'phone' => normalise_phone($phone),
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'recovery_code_hash' => password_hash($recoveryCode, PASSWORD_DEFAULT),
                    'region' => $region,
                    'weather_location' => $weatherLocation,
                    'farm_size' => $farmSize,
                    'crop_type' => substr((array_values(array_filter($enterpriseRows, static fn(array $row): bool => $row['enterprise_type'] === 'crop'))[0]['name'] ?? ''), 0, 60),
                    'farm_enterprises' => $enterpriseRows,
                    'soil_quality' => soil_value($soil),
                    'farmer_type' => $farmerType,
                    'growth_stage' => $growthStage,
                    'soil_type' => $soilType,
                    'sms_opt_in' => isset($_POST['sms_opt_in']),
                    'registered_at' => date('Y-m-d H:i:s'),
                    'last_login' => null,
                ]);
                $_SESSION['new_recovery_code'] = $recoveryCode;
                flash('success', 'Account created. Sign in to view your first field briefing.');
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $errors[] = 'An account with this email already exists.';
            } else {
                error_log('Registration database error: ' . $exception->getMessage());
                $errors[] = 'Registration is temporarily unavailable. Please try again later.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>agriSense | Register</title>
</head>
<body class="auth-page">
<main class="auth-layout">
    <section class="auth-hero compact">
        <p class="kicker">A better start to every season</p>
        <h1>Bring your field into focus.</h1>
        <p>Build a farm profile so weather and crop guidance can reflect the way you actually farm.</p>
        <div class="registration-points">
            <div><strong>01</strong><span>Choose your region and nearest weather location</span></div>
            <div><strong>02</strong><span>Register your crops, animals, and other farm activities</span></div>
            <div><strong>03</strong><span>Review practical advice on your dashboard</span></div>
        </div>
        <p class="registration-regions">Supporting farmers in Hhohho, Manzini, Shiselweni, and Lubombo.</p>
    </section>
    <section class="auth-panel">
        <a class="brand auth-brand" href="index.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
        <p class="kicker">Farmer registration</p>
        <h2>Create your profile</h2>
        <?php if ($errors): ?>
            <div class="alert error" id="form-errors" tabindex="-1" role="alert"><ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <form method="post" class="registration-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Full name<input name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required></label>
            <label>Email address<input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></label>
            <label>Phone number <small>Eswatini mobile: +268 76XX XXXX</small>
                <input name="phone" inputmode="numeric" value="<?= e($old['phone'] ?? '') ?>" required>
            </label>
            <div class="form-grid">
                <label>Region<select name="region" required>
                    <option value="" disabled hidden <?= empty($old['region']) ? 'selected' : '' ?>></option>
                    <?php foreach ($regions as $value): ?>
                        <option <?= ($old['region'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label>Nearest weather location<select name="weather_location" required>
                    <option value="" disabled hidden <?= empty($old['weather_location']) ? 'selected' : '' ?>></option>
                    <?php foreach ($weatherPlaces as $regionName => $places): ?>
                        <optgroup label="<?= e($regionName) ?>" data-region="<?= e($regionName) ?>">
                            <?php foreach ($places as $place): ?>
                                <option value="<?= e($place) ?>" <?= ($old['weather_location'] ?? '') === $place ? 'selected' : '' ?>><?= e($place) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select><small>Select the closest town for more local weather.</small></label>
                <label>Farm size (ha)<input type="number" name="farm_size" min="0.5" max="100" step="0.1" value="<?= e($old['farm_size'] ?? '') ?>" required></label>
            </div>
            <div class="form-grid">
                <label>Primary farming activity<select name="farmer_type" required><?php foreach (['crop','livestock','poultry','fisheries','beekeeping'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['farmer_type'] ?? 'crop') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="form-grid crop-profile-fields" data-crop-only>
                <label>Growth stage<select name="growth_stage" required><?php foreach (['planting','vegetative','flowering','harvesting'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['growth_stage'] ?? 'vegetative') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
                <label>Soil type<select name="soil_type" required><?php foreach (['sandy','loamy','clay'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['soil_type'] ?? 'loamy') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
                <label>Soil quality<select name="soil_quality" required>
                    <option value="" disabled hidden <?= empty($old['soil_quality']) ? 'selected' : '' ?>></option>
                    <?php foreach (['Poor', 'Moderate', 'Good', 'Excellent'] as $value): ?>
                        <option <?= ($old['soil_quality'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <?php
            $formEnterprises = [];
            if (is_array($old['enterprise_name'] ?? null)) {
                foreach ($old['enterprise_name'] as $index => $oldName) $formEnterprises[] = ['enterprise_type'=>is_array($old['enterprise_type'] ?? null) ? ($old['enterprise_type'][$index] ?? '') : '', 'name'=>$oldName, 'quantity'=>is_array($old['enterprise_quantity'] ?? null) ? ($old['enterprise_quantity'][$index] ?? '') : '', 'unit'=>is_array($old['enterprise_unit'] ?? null) ? ($old['enterprise_unit'][$index] ?? '') : '', 'growth_stage'=>is_array($old['enterprise_stage'] ?? null) ? ($old['enterprise_stage'][$index] ?? '') : '', 'weather_location'=>is_array($old['enterprise_location'] ?? null) ? ($old['enterprise_location'][$index] ?? '') : ''];
            }
            if (!$formEnterprises) $formEnterprises = [['enterprise_type'=>'', 'name'=>'', 'quantity'=>'', 'unit'=>'', 'growth_stage'=>'']];
            ?>
            <section class="farm-enterprise-editor" aria-labelledby="enterprise-heading">
                <div class="enterprise-heading"><div><p class="section-eyebrow">What you farm</p><h3 id="enterprise-heading">Crops, animals, and other enterprises</h3><p>Add every crop, livestock group, or other farm activity. You can add more than one.</p></div><button class="button button-light" type="button" data-add-enterprise>Add another</button></div>
                <div class="farm-enterprise-list" data-enterprise-list>
                    <?php foreach ($formEnterprises as $enterprise): ?>
                        <div class="farm-enterprise-row">
                            <label>Type<select name="enterprise_type[]" required><option value="" disabled <?= empty($enterprise['enterprise_type']) ? 'selected' : '' ?>></option><?php foreach (['crop'=>'Crop or vegetable','animal'=>'Animal group','other'=>'Other enterprise'] as $typeValue=>$typeLabel): ?><option value="<?= e($typeValue) ?>" <?= ($enterprise['enterprise_type'] ?? '') === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option><?php endforeach; ?></select></label>
                            <label>Name<input name="enterprise_name[]" value="<?= e($enterprise['name'] ?? '') ?>" maxlength="150" required></label>
                            <label>Area or number<input type="number" name="enterprise_quantity[]" min="0" step="0.001" value="<?= e($enterprise['quantity'] ?? '') ?>"></label>
                            <label>Unit<input name="enterprise_unit[]" value="<?= e($enterprise['unit'] ?? '') ?>" maxlength="30"></label>
                            <label>Weather place<select name="enterprise_location[]"><option value="">Use my main farm place</option><?php foreach ($allWeatherLocations as $place): ?><option value="<?= e($place) ?>" <?= ($enterprise['weather_location'] ?? '') === $place ? 'selected' : '' ?>><?= e($place) ?></option><?php endforeach; ?></select></label>
                            <label data-crop-stage>Growth stage<select name="enterprise_stage[]"><option value="" <?= empty($enterprise['growth_stage']) ? 'selected' : '' ?>></option><?php foreach (['planting','vegetative','flowering','harvesting'] as $stage): ?><option value="<?= e($stage) ?>" <?= ($enterprise['growth_stage'] ?? '') === $stage ? 'selected' : '' ?>><?= e(ucfirst($stage)) ?></option><?php endforeach; ?></select></label>
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
                    <button class="button button-light enterprise-remove" type="button" data-remove-enterprise>Remove</button>
                </div></template>
            </section>
            <div class="form-grid">
                <label>Password<span class="password-field"><input type="password" name="password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">Show</button></span></label>
                <label>Confirm password<span class="password-field"><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">Show</button></span></label>
            </div>
            <label class="check sms-consent"><input type="checkbox" name="sms_opt_in" value="1" <?= isset($old['sms_opt_in']) ? 'checked' : '' ?>> Send me urgent weather-risk alerts by SMS. Alerts use provider credits and I can switch them off in my farm profile.</label>
            <label class="check"><input type="checkbox" name="terms" required> I agree to the Terms &amp; Conditions.</label>
            <button class="button" type="submit">Create my account <span>→</span></button>
        </form>
        <p class="auth-switch">Already registered? <a href="index.php">Sign in</a></p>
    </section>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => document.getElementById('form-errors')?.focus());
const regionSelect = document.querySelector('select[name="region"]');
const weatherSelect = document.querySelector('select[name="weather_location"]');
const farmerTypeSelect = document.querySelector('select[name="farmer_type"]');
function filterWeatherLocations() {
    for (const group of weatherSelect.querySelectorAll('optgroup')) {
        group.hidden = group.dataset.region !== regionSelect.value;
    }
    if (weatherSelect.selectedOptions[0]?.parentElement?.hidden) weatherSelect.value = '';
}
regionSelect.addEventListener('change', filterWeatherLocations);
filterWeatherLocations();
function updateCropFields() {
    const show = farmerTypeSelect.value === 'crop';
    document.querySelectorAll('[data-crop-only]').forEach((section) => {
        section.hidden = !show;
        section.querySelectorAll('select,input').forEach((field) => { field.disabled = !show; field.required = show; });
    });
}
farmerTypeSelect.addEventListener('change', updateCropFields);
updateCropFields();
</script>
<script src="assets/js/farm-enterprises.js?v=20261025-enterprises-1" defer></script>
</body>
</html>
