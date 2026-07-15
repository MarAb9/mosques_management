#!/bin/bash
set -euo pipefail

COOKIE_JAR=/tmp/mosques-smoke-cookie
DASHBOARD_HTML=/tmp/mosques-smoke-dashboard
LOGIN_HTML=/tmp/mosques-smoke-login
HEADERS=/tmp/mosques-smoke-headers
trap 'rm -f "$COOKIE_JAR" "$DASHBOARD_HTML" "$LOGIN_HTML" "$HEADERS"' EXIT

assert_status() {
    local label="$1"
    local path="$2"
    local expected="$3"
    local actual
    actual=$(curl -sS -b "$COOKIE_JAR" -o /dev/null -w "%{http_code}" "http://localhost${path}")

    if [[ "$actual" != "$expected" ]]; then
        echo "${label}: expected ${expected}, got ${actual}"
        exit 1
    fi

    echo "${label}: ${actual}"
}

# Fetch a session-bound CSRF token, then login and save the authenticated cookie.
curl -sS --fail -c "$COOKIE_JAR" -o "$LOGIN_HTML" http://localhost/login.php
LOGIN_CSRF=$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]\{64\}\)".*/\1/p' "$LOGIN_HTML" | head -n 1)
test -n "$LOGIN_CSRF"
curl -sS --fail -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    --data-urlencode "csrf_token=$LOGIN_CSRF" \
    --data-urlencode 'username=admin' \
    --data-urlencode 'password=admin123' \
    --data-urlencode 'login=1' \
    -o "$DASHBOARD_HTML" http://localhost/login.php
SIZE=$(wc -c < "$DASHBOARD_HTML")
echo "Login+Dashboard: size=${SIZE}b"
if grep -q "لوحة تحكم المسؤول" "$DASHBOARD_HTML"; then
    echo "Dashboard content: OK"
else
    echo "Dashboard content: MISSING"
    exit 1
fi

# Use saved cookies for subsequent requests
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "Mosques:      %{http_code}  %{size_download}b\n" http://localhost/mosques.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "AddMosque:    %{http_code}  %{size_download}b\n" http://localhost/add_mosque.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "EditMosque:   %{http_code}  %{size_download}b\n" 'http://localhost/edit_mosque.php?id=1'
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "QuranList:    %{http_code}  %{size_download}b\n" http://localhost/quran_mosques.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "AddQuran:     %{http_code}  %{size_download}b\n" http://localhost/add_quran_mosque.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "ImportExport: %{http_code}  %{size_download}b\n" http://localhost/import_export.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "MapPage:      %{http_code}  %{size_download}b\n" http://localhost/mosque_maps.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "AJAXStats:    %{http_code}  %{size_download}b\n" http://localhost/ajax/get_mosque_stats.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "AJAXSearch:   %{http_code}  %{size_download}b\n" 'http://localhost/ajax/search_mosques.php?q=test'
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "AJAXMap:      %{http_code}  %{size_download}b\n" http://localhost/ajax/get_mosques_for_map.php
curl -sS --fail -b "$COOKIE_JAR" -o /dev/null -w "CleanRoute:   %{http_code}  %{size_download}b\n" http://localhost/mosques
curl -sS --fail -b "$COOKIE_JAR" -D "$HEADERS" -o /dev/null http://localhost/mosques.php
CSP=$(tr -d '\r' < "$HEADERS" | awk 'BEGIN{IGNORECASE=1} /^Content-Security-Policy:/{sub(/^[^:]+: /, ""); print; exit}')
if [[ "$CSP" == *"script-src"* && "$CSP" == *"'nonce-"* && "$CSP" != *"script-src 'self' 'unsafe-inline'"* ]]; then
    echo "CSP nonce: OK"
else
    echo "CSP nonce: MISSING_OR_WEAK"
    echo "$CSP"
    exit 1
fi

# Public/private boundary
assert_status "PrivateApp" "/app/Core/App.php" "404"
assert_status "PrivateConfig" "/config/database.php" "404"
assert_status "PrivateScripts" "/scripts/fix_coordinates.php" "404"
assert_status "PrivateVendor" "/vendor/autoload.php" "404"
assert_status "AuditRemoved" "/audit.php" "404"
assert_status "UploadScript" "/uploads/test.php" "403"
assert_status "PublicAsset" "/assets/css/style.css" "200"

# Logout is state-changing and therefore POST-only with CSRF protection.
LOGOUT_CSRF=$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]\{64\}\)".*/\1/p' "$DASHBOARD_HTML" | head -n 1)
test -n "$LOGOUT_CSRF"
curl -sS --fail -b "$COOKIE_JAR" -L \
    --data-urlencode "csrf_token=$LOGOUT_CSRF" \
    -o /dev/null -w "Logout:       %{http_code}  %{size_download}b\n" http://localhost/logout.php

echo "DONE"
