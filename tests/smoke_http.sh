#!/bin/bash
set -euo pipefail

COOKIE_JAR=/tmp/mosques-smoke-cookie
DASHBOARD_HTML=/tmp/mosques-smoke-dashboard
trap 'rm -f "$COOKIE_JAR" "$DASHBOARD_HTML"' EXIT

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

# Login with -L to follow redirect, -c to save final cookie state
curl -sS --fail -L -c "$COOKIE_JAR" -d 'username=admin&password=admin123&login=1' -o "$DASHBOARD_HTML" http://localhost/login.php
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

# Public/private boundary
assert_status "PrivateApp" "/app/Core/App.php" "404"
assert_status "PrivateConfig" "/config/database.php" "404"
assert_status "PrivateScripts" "/scripts/fix_coordinates.php" "404"
assert_status "PrivateVendor" "/vendor/autoload.php" "404"
assert_status "UploadScript" "/uploads/test.php" "403"
assert_status "PublicAsset" "/assets/css/style.css" "200"

# Logout test
curl -sS --fail -b "$COOKIE_JAR" -L -o /dev/null -w "Logout:       %{http_code}  %{size_download}b\n" http://localhost/logout.php

echo "DONE"
