<?php
session_start();

if (!isset($_SESSION['user_email'])) {
    header('Location: index.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email'], ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kudla Kwelive | Dashboard</title>
    <style>
        :root { --green: #176b45; --deep-green: #0b432d; --yellow: #f5c542; --paper: #f8f6ee; --ink: #18352a; --muted: #63766c; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: var(--ink); background: var(--paper); font-family: Arial, sans-serif; }
        header { padding: 22px clamp(24px, 7vw, 90px); display: flex; align-items: center; justify-content: space-between; color: white; background: var(--deep-green); }
        .brand { font-family: Georgia, serif; font-weight: bold; letter-spacing: .05em; } .logout { color: white; font-size: .85rem; }
        main { width: min(100% - 48px, 1000px); margin: 0 auto; padding: 70px 0; } .eyebrow { color: var(--green); font-size: .75rem; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; } h1 { margin: 12px 0 8px; font: 500 clamp(2.2rem, 6vw, 4.3rem) Georgia, serif; } p { color: var(--muted); } .welcome { margin-bottom: 48px; }
        .modules { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; } .module { min-height: 150px; padding: 24px; border-top: 4px solid var(--yellow); background: white; box-shadow: 0 5px 20px rgba(24,53,42,.06); } .module h2 { margin: 0 0 10px; font: 500 1.3rem Georgia, serif; } .module p { margin: 0; font-size: .9rem; line-height: 1.5; }
        @media (max-width: 680px) { header { padding: 18px 24px; } main { padding-top: 48px; } .modules { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header><div class="brand">KUDLA KWELIVE</div><a class="logout" href="logout.php">Log out</a></header>
    <main><div class="welcome"><div class="eyebrow">Farmer dashboard</div><h1>Welcome, <?php echo $userName; ?>.</h1><p>You are signed in as <?php echo $userName; ?>.</p></div><section class="modules" aria-label="Kudla Kwelive services"><article class="module"><h2>Market prices</h2><p>Compare current crop and livestock prices across Eswatini markets.</p></article><article class="module"><h2>Weather &amp; advice</h2><p>Prepare for the week with localized forecasts and agricultural guidance.</p></article><article class="module"><h2>Bulk buying groups</h2><p>Connect with cooperatives and buyers ready for your harvest.</p></article></section></main>
</body>
</html>
