<?php
require_once __DIR__ . '/includes/auth.php';
redirect_logged_in();

$errors = [];
$old = $_POST;
$regions = ['Hhohho', 'Manzini', 'Shiselweni', 'Lubombo'];
$weatherPlaces = WEATHER_PLACES;
$crops = ['Maize', 'Sorghum', 'Beans', 'Sweet Potatoes', 'Cabbage', 'Groundnuts', 'Cassava', 'Citrus', 'Sugarcane', 'Cotton', 'Pineapple', 'Mango'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $region = $_POST['region'] ?? '';
    $weatherLocation = $_POST['weather_location'] ?? '';
    $farmSize = (float)($_POST['farm_size'] ?? 0);
    $crop = $_POST['crop_type'] ?? '';
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
    if (($farmerType === 'crop' && !in_array($crop, $crops, true)) || ($crop !== '' && !in_array($crop, $crops, true)) || !in_array($soil, ['Poor', 'Moderate', 'Good', 'Excellent'], true)) $errors[] = 'Complete your farm details.';
    if (!in_array($farmerType, ['crop','livestock','poultry','fisheries','beekeeping'], true) || !in_array($growthStage, ['planting','vegetative','flowering','harvesting'], true) || !in_array($soilType, ['sandy','loamy','clay'], true)) $errors[] = 'Choose valid farming, growth, and soil types.';
    if (!isset($_POST['terms'])) $errors[] = 'You must accept the terms.';

    if (!$errors) {
        try {
            if (find_user_by_email($email) !== null) {
                $errors[] = 'An account with this email already exists.';
            } else {
                create_user([
                    'email' => $email,
                    'name' => $name,
                    'phone' => normalise_phone($phone),
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'region' => $region,
                    'weather_location' => $weatherLocation,
                    'farm_size' => $farmSize,
                    'crop_type' => $farmerType === 'crop' ? $crop : null,
                    'soil_quality' => soil_value($soil),
                    'farmer_type' => $farmerType,
                    'growth_stage' => $growthStage,
                    'soil_type' => $soilType,
                    'sms_opt_in' => isset($_POST['sms_opt_in']),
                    'registered_at' => date('Y-m-d H:i:s'),
                    'last_login' => null,
                ]);
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
            <div><strong>02</strong><span>Add crop, soil, and growth-stage details</span></div>
            <div><strong>03</strong><span>Review practical advice on your dashboard</span></div>
        </div>
        <p class="registration-regions">Supporting farmers in Hhohho, Manzini, Shiselweni, and Lubombo.</p>
    </section>
    <section class="auth-panel">
        <a class="brand auth-brand" href="index.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
        <p class="kicker">Farmer registration</p>
        <h2>Create your profile</h2>
        <?php if ($errors): ?>
            <div class="alert error"><ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <form method="post" class="registration-form">
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
                <label>Farmer category<select name="farmer_type" required><?php foreach (['crop','livestock','poultry','fisheries','beekeeping'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['farmer_type'] ?? 'crop') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
                <label>Growth stage<select name="growth_stage" required><?php foreach (['planting','vegetative','flowering','harvesting'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['growth_stage'] ?? 'vegetative') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
                <label>Soil type<select name="soil_type" required><?php foreach (['sandy','loamy','clay'] as $value): ?><option value="<?= e($value) ?>" <?= ($old['soil_type'] ?? 'loamy') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="form-grid">
                <label>Primary crop<select name="crop_type" <?= ($old['farmer_type'] ?? 'crop') === 'crop' ? 'required' : '' ?>>
                    <option value="" <?= empty($old['crop_type']) ? 'selected' : '' ?>>Not applicable</option>
                    <?php foreach ($crops as $value): ?>
                        <option <?= ($old['crop_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label>Soil quality<select name="soil_quality" required>
                    <option value="" disabled hidden <?= empty($old['soil_quality']) ? 'selected' : '' ?>></option>
                    <?php foreach (['Poor', 'Moderate', 'Good', 'Excellent'] as $value): ?>
                        <option <?= ($old['soil_quality'] ?? '') === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <div class="form-grid">
                <label>Password<input type="password" name="password" minlength="8" required></label>
                <label>Confirm password<input type="password" name="confirm_password" minlength="8" required></label>
            </div>
            <label class="check sms-consent"><input type="checkbox" name="sms_opt_in" value="1" <?= isset($old['sms_opt_in']) ? 'checked' : '' ?>> I want automatic farm advice by SMS about every two hours. SMS delivery uses provider credits and I can switch it off in my profile.</label>
            <label class="check"><input type="checkbox" name="terms" required> I agree to the Terms &amp; Conditions.</label>
            <button class="button" type="submit">Create my account <span>→</span></button>
        </form>
        <p class="auth-switch">Already registered? <a href="index.php">Sign in</a></p>
    </section>
</main>
<script>
const regionSelect = document.querySelector('select[name="region"]');
const weatherSelect = document.querySelector('select[name="weather_location"]');
const farmerTypeSelect = document.querySelector('select[name="farmer_type"]');
const cropSelect = document.querySelector('select[name="crop_type"]');
function filterWeatherLocations() {
    for (const group of weatherSelect.querySelectorAll('optgroup')) {
        group.hidden = group.dataset.region !== regionSelect.value;
    }
    if (weatherSelect.selectedOptions[0]?.parentElement?.hidden) weatherSelect.value = '';
}
regionSelect.addEventListener('change', filterWeatherLocations);
filterWeatherLocations();
function updateCropRequirement() {
    const cropFarmer = farmerTypeSelect.value === 'crop';
    cropSelect.required = cropFarmer;
    if (!cropFarmer) cropSelect.value = '';
}
farmerTypeSelect.addEventListener('change', updateCropRequirement);
updateCropRequirement();
</script>
</body>
</html>
