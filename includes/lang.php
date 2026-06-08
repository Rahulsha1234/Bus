<?php
/**
 * Centralized Translation and Language Selection System
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$supported_languages = ['en', 'hi', 'ne'];
$default_language = 'en';

// Determine language to use
$current_lang = $default_language;

if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
    $current_lang = $_GET['lang'];
    $_SESSION['lang'] = $current_lang;
    
    // Set cookie (httponly = false for potential JS access, secure is dynamic)
    $is_secure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        $is_secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $is_secure = true;
    }
    
    if (PHP_VERSION_ID >= 70300) {
        setcookie('lang', $current_lang, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'secure' => $is_secure,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    } else {
        // For PHP < 7.3 the setcookie signature doesn't support options array or SameSite flag.
        // Use a safe call with explicit parameters (domain set to empty string instead of null).
        setcookie('lang', $current_lang, time() + (86400 * 30), '/', '', $is_secure, false);
    }

    // Redirect to the same page without the 'lang' query parameter to clean the URL
    $uri = $_SERVER['REQUEST_URI'];
    $parsed_url = parse_url($uri);
    $path = $parsed_url['path'] ?? '';
    $query = $parsed_url['query'] ?? '';
    
    if ($query !== '') {
        parse_str($query, $query_params);
        unset($query_params['lang']);
        $new_query = http_build_query($query_params);
        $redirect_to = $path . ($new_query !== '' ? '?' . $new_query : '');
    } else {
        $redirect_to = $path;
    }
    
    header("Location: " . $redirect_to);
    exit();
} elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $supported_languages)) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $supported_languages)) {
    $current_lang = $_COOKIE['lang'];
    $_SESSION['lang'] = $current_lang;
}

// Store in a global variable / constant for access
if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', $current_lang);
}

// Load the appropriate language file
$lang_file = __DIR__ . '/../languages/' . CURRENT_LANG . '.php';
if (file_exists($lang_file)) {
    $translations = include $lang_file;
} else {
    $translations = [];
}

// Global translation function
if (!function_exists('__')) {
    function __($key, $default = null) {
        global $translations;
        if (isset($translations[$key])) {
            return $translations[$key];
        }
        return $default !== null ? $default : $key;
    }
}
