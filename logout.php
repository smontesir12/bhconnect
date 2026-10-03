<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
session_destroy();
header("Location: " . BASE . "/index.php");
exit;
?>