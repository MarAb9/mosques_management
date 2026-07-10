<?php
/**
 * Flash Message Helper
 *
 * Simple wrappers around $_SESSION['success'] and $_SESSION['error']
 * to provide a consistent flash message API without changing existing
 * page behavior.
 *
 * Existing code that reads/writes $_SESSION['success'] and $_SESSION['error']
 * directly continues to work — these helpers use the same session keys.
 */

/**
 * Store a flash message in the session.
 *
 * @param string $type  Message type: 'success' or 'error'
 * @param string $message  The message text
 */
function set_flash($type, $message) {
    $_SESSION[$type] = $message;
}

/**
 * Retrieve a flash message without clearing it.
 *
 * @param string $type  Message type: 'success' or 'error'
 * @return string|null  The message, or null if not set
 */
function get_flash($type) {
    return $_SESSION[$type] ?? null;
}

/**
 * Check whether a flash message exists.
 *
 * @param string $type  Message type: 'success' or 'error'
 * @return bool
 */
function has_flash($type) {
    return isset($_SESSION[$type]) && $_SESSION[$type] !== '';
}

/**
 * Clear a flash message from the session.
 *
 * @param string $type  Message type: 'success' or 'error'
 */
function clear_flash($type) {
    unset($_SESSION[$type]);
}

/**
 * Render a Bootstrap alert for a flash message and clear it.
 *
 * Returns empty string if no message exists for the given type.
 *
 * @param string $type   Message type: 'success' or 'error'
 * @param string|null $class  Bootstrap alert class override (default: auto from type)
 * @return string  HTML string
 */
function flash_message($type, $class = null) {
    if (!has_flash($type)) {
        return '';
    }

    if ($class === null) {
        $class = ($type === 'success') ? 'alert-success' : 'alert-danger';
    }

    $message = htmlspecialchars(get_flash($type), ENT_QUOTES, 'UTF-8');
    clear_flash($type);

    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
         . $message
         . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>'
         . '</div>';
}
