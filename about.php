<?php
require_once __DIR__ . '/includes/auth.php';
$signedIn = current_user() !== null;
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>How it works | agriSense</title>
</head>
<body class="farmer-dashboard-page">
<header class="topbar farmer-topbar">
    <a class="brand" href="<?= $signedIn ? 'dashboard.php' : 'index.php' ?>"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation">
        <?php if ($signedIn): ?><a class="text-link" href="dashboard.php">My farm</a><a class="text-link" href="farm_journal.php">Farm journal</a><a class="text-link" href="profile.php">Farm profile</a><?php endif; ?>
        <a class="text-link active" href="about.php">How it works</a>
        <a class="text-link" href="<?= $signedIn ? 'logout.php' : 'register.php' ?>"><?= $signedIn ? 'Log out' : 'Join the network' ?></a>
    </nav>
</header>

<main class="about-page">
    <section class="about-hero">
        <p class="welcome-kicker">agriSense <span></span> Eswatini</p>
        <h1>Local knowledge,<br>made useful in the field.</h1>
        <p>One clear place to check regional weather, understand crop fit, and decide what to do next on your farm.</p>
        <a class="button" href="<?= $signedIn ? 'dashboard.php' : 'register.php' ?>"><?= $signedIn ? 'Return to my farm' : 'Create your farm profile' ?> <span>→</span></a>
    </section>

    <section class="about-section">
        <div class="about-section-title"><p class="section-eyebrow">What you can use</p><h2>Built around everyday farm decisions</h2><p>Advice becomes more relevant when local conditions and your own farm details are considered together.</p></div>
        <div class="about-feature-grid">
            <article><span class="feature-number">01</span><h3>Regional weather</h3><p>Current conditions and forecast rain probability for the supported Eswatini regions, refreshed through a 15-minute cache.</p></article>
            <article><span class="feature-number">02</span><h3>Your farm profile</h3><p>Keep your crop, farm size, farmer category, soil type, and growth stage together in one saved profile.</p></article>
            <article><span class="feature-number">03</span><h3>Weather-aware actions</h3><p>The advisory model combines weather and profile features to suggest irrigation, planting, harvest, disease, or livestock actions.</p></article>
            <article><span class="feature-number">04</span><h3>Crop and harvest outlook</h3><p>Review crop-fit suggestions and a yield estimate alongside your daily conditions and saved soil information.</p></article>
        </div>
    </section>

    <section class="about-process">
        <div class="about-section-title"><p class="section-eyebrow">A simple field workflow</p><h2>From profile to practical advice</h2></div>
        <div class="process-steps">
            <article><span>1</span><div><h3>Describe your farm</h3><p>Add your region, crop, soil, farm size, and current growth stage.</p></div></article>
            <article><span>2</span><div><h3>Check the conditions</h3><p>The dashboard uses the latest cached weather and upcoming rain outlook for your region.</p></div></article>
            <article><span>3</span><div><h3>Choose your next step</h3><p>Read the recommended action and confidence. Farmers who opt in receive an SMS when high-risk weather is detected at their saved town.</p></div></article>
        </div>
    </section>

    <section class="regions-section">
        <div><p class="section-eyebrow">Made for local conditions</p><h2>Four regions, one farm briefing</h2><p>Weather locations are mapped to the nearest supported town for the selected region.</p></div>
        <div class="region-tags"><span>Hhohho · Mbabane</span><span>Manzini · Manzini</span><span>Shiselweni · Nhlangano</span><span>Lubombo · Siteki</span></div>
    </section>

    <p class="about-note">The ML model is trained on synthetic examples to demonstrate the advisory workflow. Treat its output as decision support and use local agricultural guidance for high-impact farm decisions.</p>
</main>
</body>
</html>
