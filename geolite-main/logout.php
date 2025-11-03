<?php
// Start output buffering to prevent header issues
ob_start();


// Include authentication
require_once 'incl/Auth.php';

// Perform logout
logout();

// Clear output buffer and redirect to login page
ob_end_clean();
header('Location: login.php?logout=1');
exit;
