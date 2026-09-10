<?php
// logout.php - Session destruction and redirect
require_once __DIR__ . '/includes/auth.php';

logout_user();

header("Location: login.php?logged_out=1");
exit;
