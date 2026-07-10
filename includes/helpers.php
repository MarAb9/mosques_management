<?php
/**
 * Shared Output and Utility Helpers
 *
 * General-purpose helpers for output escaping, form helpers, and
 * string utilities. These complement the domain-specific helpers
 * in mosque_functions.php.
 */

/**
 * HTML-escape a value for safe output in HTML context.
 *
 * Short alias for htmlspecialchars with UTF-8 and ENT_QUOTES.
 *
 * @param string|null $value  The value to escape
 * @return string  Escaped string (empty string for null)
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Trim a value safely, returning empty string for null.
 *
 * @param string|null $value
 * @return string
 */
function safe_trim($value) {
    return trim((string)($value ?? ''));
}

/**
 * Return ' selected' attribute if $value matches $expected.
 *
 * Usage: <option value="x" <?= selected($row['field'], 'x') ?>>
 *
 * @param mixed $value     Current value
 * @param mixed $expected  Expected value to match
 * @return string  ' selected' or empty string
 */
function selected($value, $expected) {
    return ((string)$value === (string)$expected) ? ' selected' : '';
}

/**
 * Return ' checked' attribute if $value matches $expected.
 *
 * Usage: <input type="checkbox" <?= checked($row['flag'], '1') ?>>
 *
 * @param mixed $value     Current value
 * @param mixed $expected  Expected value to match
 * @return string  ' checked' or empty string
 */
function checked($value, $expected) {
    return ((string)$value === (string)$expected) ? ' checked' : '';
}
