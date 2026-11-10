<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Farm journal | agriSense</title>
</head>
<body class="farmer-dashboard-page" data-farmer-key="<?= e(strtolower((string)$user['email'])) ?>">
<header class="topbar farmer-topbar">
    <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <nav aria-label="Main navigation">
        <a class="text-link" href="dashboard.php">My farm</a>
        <a class="text-link active" href="farm_journal.php">Farm journal</a>
        <a class="text-link" href="about.php">Guidance</a>
        <a class="text-link" href="profile.php">Farm profile</a>
        <a class="text-link" href="logout.php">Log out</a>
    </nav>
</header>
<main class="dashboard farmer-dashboard farm-journal-page">
    <div class="journal-page-heading"><p class="section-eyebrow">Plan, record, learn</p><h1>Farm journal</h1><p>Keep farm tasks, costs, harvests, crop problems, and animal records together.</p></div>
    <div id="farm-journal-app" aria-live="polite"><p class="journal-loading">Loading your farm journal…</p></div>
</main>
<script src="assets/js/farm-journal.js?v=20261101-journal-unified-2" defer></script>
</body>
</html>
