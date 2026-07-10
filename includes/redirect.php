<?php
/**
 * Redirect Helper
 *
 * Provides consistent redirect behavior with optional flash messages.
 * Uses header("Location: ...") + exit() pattern already used throughout
 * the application.
 */

/**
 * Redirect to a URL and stop execution.
 *
 * @param string $url  Target URL (relative or absolute)
 */
function redirect_to($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Set a flash message and redirect.
 *
 * @param string $url      Target URL
 * @param string $type     Flash type: 'success' or 'error'
 * @param string $message  Flash message text
 */
function redirect_with_flash($url, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: " . $url);
    exit();
}
