<?php
/**
 * Production configuration for the digital commerce platform.
 *
 * This file defines the fixed database credentials supplied by the operator so
 * the application can connect without relying on the installer flow.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'digital_platform');
define('DB_USER', 'digital_platform');
define('DB_PASSWORD', 'j.5LpgvX90tfHe[6');

// Optional integrations can be populated later if needed.
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

define('DEFAULT_LANGUAGE', 'tr');
