<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads Composer's autoloader (SDK + this plugin's classes) but not a full
 * WordPress bootstrap; tests use Brain\Monkey to stub the WP functions
 * our code calls.
 *
 * @package WordPress\LiteLLMAiProvider
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/autoload.php';
