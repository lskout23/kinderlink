<?php

$GLOBALS['APP_ENV_FILE_SOURCES'] = [];

function app_load_env_file(string $filePath): void {
	if (!is_file($filePath) || !is_readable($filePath)) {
		return;
	}

	$lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return;
	}

	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#')) {
			continue;
		}

		$pos = strpos($line, '=');
		if ($pos === false) {
			continue;
		}

		$key = trim(substr($line, 0, $pos));
		$val = trim(substr($line, $pos + 1));
		if ($key === '') {
			continue;
		}

		if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
			$val = substr($val, 1, -1);
		}

		if (getenv($key) === false) {
			putenv($key . '=' . $val);
			$GLOBALS['APP_ENV_FILE_SOURCES'][$key] = basename($filePath);
		}
		if (!array_key_exists($key, $_ENV)) {
			$_ENV[$key] = $val;
		}
		if (!array_key_exists($key, $_SERVER)) {
			$_SERVER[$key] = $val;
		}
	}
}

app_load_env_file(dirname(__DIR__) . '/.env');
app_load_env_file(dirname(__DIR__) . '/.env.local');

function app_env(string $key, mixed $default = null): mixed {
	$value = getenv($key);
	if ($value !== false && $value !== null) {
		return $value;
	}

	$redirectKey = 'REDIRECT_' . $key;
	$value = getenv($redirectKey);
	if ($value !== false && $value !== null) {
		return $value;
	}

	if (array_key_exists($key, $_ENV)) {
		return $_ENV[$key];
	}
	if (array_key_exists($redirectKey, $_ENV)) {
		return $_ENV[$redirectKey];
	}

	if (array_key_exists($key, $_SERVER)) {
		return $_SERVER[$key];
	}
	if (array_key_exists($redirectKey, $_SERVER)) {
		return $_SERVER[$redirectKey];
	}

	$upperKey = strtoupper($key);
	$upperRedirectKey = 'REDIRECT_' . $upperKey;
	if (array_key_exists($upperKey, $_ENV)) {
		return $_ENV[$upperKey];
	}
	if (array_key_exists($upperRedirectKey, $_ENV)) {
		return $_ENV[$upperRedirectKey];
	}
	if (array_key_exists($upperKey, $_SERVER)) {
		return $_SERVER[$upperKey];
	}
	if (array_key_exists($upperRedirectKey, $_SERVER)) {
		return $_SERVER[$upperRedirectKey];
	}

	if (function_exists('apache_getenv')) {
		$apacheValue = apache_getenv($key);
		if ($apacheValue !== false && $apacheValue !== null) {
			return $apacheValue;
		}

		$apacheRedirectValue = apache_getenv($redirectKey);
		if ($apacheRedirectValue !== false && $apacheRedirectValue !== null) {
			return $apacheRedirectValue;
		}
	}

	return $default;
}

function app_env_source(string $key): string {
	$redirectKey = 'REDIRECT_' . $key;
	$upperKey = strtoupper($key);
	$upperRedirectKey = 'REDIRECT_' . $upperKey;

	$candidates = [$key, $redirectKey, $upperKey, $upperRedirectKey];
	foreach ($candidates as $candidate) {
		$value = getenv($candidate);
		if ($value !== false && $value !== null) {
			if (isset($GLOBALS['APP_ENV_FILE_SOURCES'][$candidate])) {
				return $GLOBALS['APP_ENV_FILE_SOURCES'][$candidate];
			}
			return 'environment';
		}

		if (array_key_exists($candidate, $_ENV) || array_key_exists($candidate, $_SERVER)) {
			if (isset($GLOBALS['APP_ENV_FILE_SOURCES'][$candidate])) {
				return $GLOBALS['APP_ENV_FILE_SOURCES'][$candidate];
			}
			return 'environment';
		}
	}

	return 'default';
}

function app_bool_env(string $key, bool $default = false): bool {
	$value = getenv($key);
	if ($value === false) {
		return $default;
	}
	return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
}

function app_base_url(): string {
	$base = app_env('APP_BASE_URL');
	if ($base !== null && $base !== '') {
		$base = trim(str_replace('\\', '/', $base), '/');
		return $base === '' ? '' : '/' . $base;
	}

	$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
	$basePath = rtrim(str_replace('/index.php', '', dirname($scriptName)), '/');
	if ($basePath === '/' || $basePath === '.' || $basePath === '') {
		return '';
	}

	return $basePath;
}

function app_origin(): string {
	$scheme = app_env('APP_SCHEME');
	if ($scheme === null || $scheme === '') {
		$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
		$scheme = $https ? 'https' : 'http';
	}

	$host = app_env('APP_HOST', $_SERVER['HTTP_HOST'] ?? 'localhost');
	$port = app_env('APP_PORT');

	if ($port !== null && $port !== '' && !in_array((string)$port, ['80', '443'], true)) {
		return $scheme . '://' . $host . ':' . $port;
	}

	return $scheme . '://' . $host;
}

function app_url(): string {
	return rtrim(app_origin() . app_base_url(), '/');
}

define('APP_NAME', app_env('APP_NAME', 'KinderLink'));
define('APP_NAME_GR', app_env('APP_NAME_GR', 'KinderLink'));
define('APP_VERSION', app_env('APP_VERSION', '1.0.0'));
define('APP_ENV', app_env('APP_ENV', 'production'));
define('APP_DEBUG', app_bool_env('APP_DEBUG', APP_ENV !== 'production'));
define('BASE_URL', app_base_url());
define('APP_URL', app_env('APP_URL', app_url()));
define('BASE_PATH', dirname(__DIR__));

// Database
define('DB_HOST', app_env('DB_HOST', 'localhost'));
define('DB_NAME', app_env('DB_NAME', 'kinderlink'));
define('DB_USER', app_env('DB_USER', 'kinderlink_app'));
define('DB_PASS', app_env('DB_PASS', ''));
define('DB_CHARSET', app_env('DB_CHARSET', 'utf8mb4'));

// Session
define('SESSION_NAME', app_env('SESSION_NAME', 'kinderlink_session'));
define('SESSION_LIFETIME', (int)app_env('SESSION_LIFETIME', 3600 * 8)); // 8 hours

// Email (SMTP)
define('SMTP_HOST', app_env('SMTP_HOST', ''));
define('SMTP_PORT', (int)app_env('SMTP_PORT', 465));
define('SMTP_USER', app_env('SMTP_USER', ''));
define('SMTP_PASS', app_env('SMTP_PASS', ''));
define('SMTP_FROM', app_env('SMTP_FROM', app_env('SMTP_USER', '')));
define('SMTP_FROM_NAME', app_env('SMTP_FROM_NAME', APP_NAME_GR));
define('SMTP_SECURE', app_env('SMTP_SECURE', 'ssl')); // tls or ssl

// Email provider dispatcher: 'smtp' (default) or 'brevo' (HTTPS API)
define('EMAIL_PROVIDER', strtolower(trim((string)app_env('EMAIL_PROVIDER', 'smtp'))));
define('BREVO_API_KEY', trim((string)app_env('BREVO_API_KEY', '')));
define('BREVO_API_ENDPOINT', app_env('BREVO_API_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'));

// Timezone
date_default_timezone_set('Europe/Athens');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
