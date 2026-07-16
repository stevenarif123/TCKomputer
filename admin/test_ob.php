<?php
require_once __DIR__ . '/../config/security.php';
applySecurityHeaders();
echo "Hello from buffer";
