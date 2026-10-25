<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
flash('error', 'Farm advice is sent automatically to opted-in farmers. Manage SMS delivery in your farm profile.');
header('Location: dashboard.php');
exit;
