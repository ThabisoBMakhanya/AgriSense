<?php
session_start();

if (isset($_SESSION['user_email'])) {
    header('Location: dashboard.php');
    exit;
}

$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$usersFile = $dataDirectory . DIRECTORY_SEPARATOR . 'users.json';
$defaultUsers = [
    'thabisomakhanya@gmail.com' => [
        'name' => 'Maize Farmer',
        'phone' => '+268 7612 3456',
        'password' => 'makhanya'
    ]
];
$countries = [
    'SZ' => ['name' => 'Eswatini', 'code' => '+268'],
    'ZA' => ['name' => 'South Africa', 'code' => '+27'],
    'BW' => ['name' => 'Botswana', 'code' => '+267'],
    'LS' => ['name' => 'Lesotho', 'code' => '+266'],
    'MZ' => ['name' => 'Mozambique', 'code' => '+258'],
    'ZW' => ['name' => 'Zimbabwe', 'code' => '+263'],
    'ZM' => ['name' => 'Zambia', 'code' => '+260'],
    'NG' => ['name' => 'Nigeria', 'code' => '+234'],
    'KE' => ['name' => 'Kenya', 'code' => '+254'],
    'GH' => ['name' => 'Ghana', 'code' => '+233'],
    'TZ' => ['name' => 'Tanzania', 'code' => '+255'],
    'UG' => ['name' => 'Uganda', 'code' => '+256'],
    'NA' => ['name' => 'Namibia', 'code' => '+264'],
    'US' => ['name' => 'United States', 'code' => '+1'],
    'GB' => ['name' => 'United Kingdom', 'code' => '+44'],
    'IN' => ['name' => 'India', 'code' => '+91']
];

if (!is_dir($dataDirectory)) {
    mkdir($dataDirectory, 0755, true);
}

$users = $defaultUsers;
if (is_file($usersFile)) {
    $storedUsers = json_decode(file_get_contents($usersFile), true);
    if (is_array($storedUsers)) {
        $users = array_merge($defaultUsers, $storedUsers);
    }
}

$message = '';
$messageType = '';
$activeForm = 'login';
$selectedCountry = 'SZ';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'login';
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: ''));

    if ($formType === 'register') {
        $activeForm = 'register';
        $fullName = trim($_POST['full_name'] ?? '');
        $selectedCountry = $_POST['country'] ?? 'SZ';
        $phoneNumber = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid name and email address.';
            $messageType = 'error';
        } elseif (!isset($countries[$selectedCountry])) {
            $message = 'Please select your country.';
            $messageType = 'error';
        } elseif (strlen($phoneNumber) < 6 || strlen($phoneNumber) > 15) {
            $message = 'Enter a valid phone number for your selected country.';
            $messageType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'Your password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } elseif (isset($users[$email])) {
            $message = 'User already registered.';
            $messageType = 'error';
        } else {
            $phone = $countries[$selectedCountry]['code'] . ' ' . $phoneNumber;
            $users[$email] = [
                'name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                'phone' => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'),
                'password' => $password
            ];
            file_put_contents($usersFile, json_encode(array_diff_key($users, $defaultUsers), JSON_PRETTY_PRINT), LOCK_EX);
            $message = 'Registration successful. You can now sign in.';
            $messageType = 'success';
            $activeForm = 'login';
        }
    } else {
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);

        if (isset($users[$email]) && hash_equals($users[$email]['password'], $password)) {
            session_regenerate_id(true);
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $users[$email]['name'];
            if ($rememberMe) {
                ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
            }
            header('Location: dashboard.php');
            exit;
        }

        $message = 'Invalid Email or Password.';
        $messageType = 'error';
    }
}

if (!isset($countries[$selectedCountry])) {
    $selectedCountry = 'SZ';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in or register for Kudla Kwelive agricultural market and advisory services.">
    <title>Kudla Kwelive | Welcome</title>
    <style>
        :root { --green: #176b45; --deep-green: #0b432d; --yellow: #f5c542; --ink: #18352a; --muted: #63766c; --paper: #f8f6ee; --line: #d8e1d9; --danger: #a43d32; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: var(--ink); background: var(--paper); font-family: Georgia, 'Times New Roman', serif; }
        .page { height: 100vh; display: grid; grid-template-columns: minmax(280px, .9fr) minmax(320px, 1.1fr); }
        .intro { position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: clamp(28px, 6vw, 76px); color: white; background: linear-gradient(rgba(11,67,45,.68), rgba(11,67,45,.78)), url('Images/farm_image.jpeg') center / cover no-repeat; }
        .intro:after { content: ''; position: absolute; width: 380px; height: 380px; right: -150px; bottom: -130px; border: 70px solid var(--yellow); border-radius: 50%; opacity: .9; }
        .brand { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: bold; letter-spacing: .04em; }
        .intro-copy { position: relative; z-index: 1; max-width: 480px; margin: auto 0; }
        .eyebrow { color: var(--yellow); font: 700 .76rem/1.2 Arial, sans-serif; letter-spacing: .15em; text-transform: uppercase; }
        h1 { max-width: 550px; margin: 16px 0; font-size: clamp(2.5rem, 6vw, 5.2rem); line-height: .94; font-weight: 500; }
        .intro p { max-width: 420px; color: #d7e8d8; font: 1.05rem/1.6 Arial, sans-serif; }
        .intro-foot { position: relative; z-index: 1; color: #b8bfba; font: .78rem Arial, sans-serif; }
        .content { display: flex; align-items: center; justify-content: center; padding: clamp(20px, 4vw, 48px); }
        .auth-shell { width: min(100%, 500px); }
        .auth-header { margin-bottom: 18px; }
        h2 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 500; }
        .auth-header p { margin: 0; color: var(--muted); font: .95rem/1.5 Arial, sans-serif; }
        .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; padding: 5px; margin-bottom: 16px; background: #e6ece4; border-radius: 8px; }
        .tab { border: 0; border-radius: 5px; padding: 12px; color: var(--muted); background: transparent; font: 700 .9rem Arial, sans-serif; cursor: pointer; }
        .tab.active { color: var(--deep-green); background: white; box-shadow: 0 2px 8px rgba(24,53,42,.1); }
        form { display: none; } form.active { display: block; animation: appear .25s ease-out; }
        .field { margin-bottom: 10px; } label { display: block; margin-bottom: 5px; font: 700 .78rem Arial, sans-serif; letter-spacing: .03em; }
        input[type='email'], input[type='password'], input[type='text'], input[type='tel'], select { width: 100%; border: 1px solid var(--line); border-radius: 5px; padding: 11px 15px; color: var(--ink); background: white; font: 1rem Arial, sans-serif; outline: none; }
        input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(23,107,69,.12); }
        .check { display: flex; align-items: flex-start; gap: 9px; margin: 7px 0 14px; color: var(--muted); font: .84rem/1.4 Arial, sans-serif; } .check input { accent-color: var(--green); margin-top: 3px; }
        .submit { width: 100%; border: 0; border-radius: 5px; padding: 12px; color: white; background: var(--green); font: 700 .94rem Arial, sans-serif; cursor: pointer; } .submit:hover { background: var(--deep-green); }
        .message { border-left: 4px solid; border-radius: 4px; padding: 12px 14px; margin-bottom: 20px; font: .88rem/1.4 Arial, sans-serif; } .message.error { color: var(--danger); background: #fcecea; border-color: var(--danger); } .message.success { color: var(--green); background: #e6f3e6; border-color: var(--green); }
        .helper { margin-top: 22px; color: var(--muted); text-align: center; font: .76rem Arial, sans-serif; }
        @keyframes appear { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 760px) { .page { min-height: 100vh; height: auto; display: block; } .intro { min-height: 310px; padding: 27px 24px; } .intro-copy { margin: 48px 0 0; } h1 { font-size: 3.2rem; } .intro p { margin-bottom: 0; font-size: .92rem; } .intro-foot { display: none; } .content { padding: 38px 24px 50px; } }
    </style>
</head>
<body>
    <main class="page">
        <section class="intro" aria-label="About Kudla Kwelive">
            <div class="brand">KUDLA KWELIVE</div>
            <div class="intro-copy"><div class="eyebrow">Grow together</div><h1>Better markets. Stronger harvests.</h1><p>A simple home for Eswatini's farmers, buyers, and agricultural support network.</p></div>
            <div class="intro-foot">Agricultural Market &amp; Advisory System · Eswatini</div>
        </section>
        <section class="content">
            <div class="auth-shell">
                <div class="auth-header"><h2>Welcome back</h2><p>Access market prices, weather alerts, and your farming network.</p></div>
                <?php if ($message !== ''): ?><div class="message <?= $messageType ?>" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <div class="tabs" role="tablist" aria-label="Account access"><button class="tab <?= $activeForm === 'login' ? 'active' : '' ?>" type="button" data-form="login" role="tab">Log in</button><button class="tab <?= $activeForm === 'register' ? 'active' : '' ?>" type="button" data-form="register" role="tab">Register</button></div>
                <form id="login" class="<?= $activeForm === 'login' ? 'active' : '' ?>" method="post" novalidate><input type="hidden" name="form_type" value="login"><div class="field"><label for="login-email">Email address</label><input id="login-email" name="email" type="email" autocomplete="email" required></div><div class="field"><label for="login-password">Password</label><input id="login-password" name="password" type="password" autocomplete="current-password" required></div><label class="check"><input type="checkbox" name="remember_me"> Remember me on this device</label><button class="submit" type="submit">Login </button></form>
                <form id="register" class="<?= $activeForm === 'register' ? 'active' : '' ?>" method="post" novalidate><input type="hidden" name="form_type" value="register"><div class="field"><label for="full-name">Full name</label><input id="full-name" name="full_name" type="text" autocomplete="name" required></div><div class="field"><label for="register-email">Email address</label><input id="register-email" name="email" type="email" autocomplete="email" required></div><div class="field"><label for="country">Country of origin</label><select id="country" name="country" autocomplete="country" aria-describedby="phone-help" required><?php foreach ($countries as $countryId => $country): ?><option value="<?= $countryId ?>" data-code="<?= htmlspecialchars($country['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedCountry === $countryId ? 'selected' : '' ?>><?= htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($country['code'], ENT_QUOTES, 'UTF-8') ?>)</option><?php endforeach; ?></select></div><div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" placeholder="Enter your phone number" autocomplete="tel-national" aria-describedby="phone-help" required><small id="phone-help" style="display: block; margin-top: 6px; color: var(--muted); font: .76rem Arial, sans-serif;">Select your country to see the dialing code suggestion.</small></div><div class="field"><label for="register-password">Password</label><input id="register-password" name="password" type="password" minlength="8" autocomplete="new-password" required></div><div class="field"><label for="confirm-password">Confirm password</label><input id="confirm-password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required></div><label class="check"><input id="terms" type="checkbox" required> I agree to the Terms &amp; Conditions.</label><button class="submit" type="submit">Create my account</button></form>
            </div>
        </section>
    </main>
    <script>
        const tabs = document.querySelectorAll('.tab');
        const forms = document.querySelectorAll('form');
        const phone = document.getElementById('phone');
        const country = document.getElementById('country');
        const phoneHelp = document.getElementById('phone-help');
        const updatePhoneSuggestion = () => {
            const code = country.selectedOptions[0].dataset.code;
            phoneHelp.textContent = `Dialing code suggestion: ${code}`;
        };
        country.addEventListener('change', updatePhoneSuggestion);
        phone.addEventListener('input', () => {
            phone.value = phone.value.replace(/\D/g, '').slice(0, 15);
        });
        tabs.forEach((tab) => tab.addEventListener('click', () => { tabs.forEach((item) => item.classList.remove('active')); forms.forEach((form) => form.classList.remove('active')); tab.classList.add('active'); document.getElementById(tab.dataset.form).classList.add('active'); }));
        document.getElementById('register').addEventListener('submit', (event) => { const form = event.currentTarget; const password = form.elements.password; const confirmPassword = form.elements.confirm_password; if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; } if (password.value !== confirmPassword.value) { event.preventDefault(); confirmPassword.setCustomValidity('Passwords do not match.'); form.reportValidity(); confirmPassword.setCustomValidity(''); } });
        document.getElementById('login').addEventListener('submit', (event) => { if (!event.currentTarget.checkValidity()) { event.preventDefault(); event.currentTarget.reportValidity(); } });
    </script>
</body>
</html>
