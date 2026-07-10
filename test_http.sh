#!/bin/bash
# Login with -L to follow redirect, -c to save final cookie state
curl -s -L -c /tmp/ck -d 'username=admin&password=admin123&login=1' -o /tmp/dash http://localhost/login.php
SIZE=$(wc -c < /tmp/dash)
echo "Login+Dashboard: size=${SIZE}b"
grep -c "لوحة التحكم" /tmp/dash > /dev/null && echo "Dashboard content: OK" || echo "Dashboard content: MISSING"

# Use saved cookies for subsequent requests
curl -s -b /tmp/ck -o /dev/null -w "Mosques:      %{http_code}  %{size_download}b\n" http://localhost/mosques.php
curl -s -b /tmp/ck -o /dev/null -w "AddMosque:    %{http_code}  %{size_download}b\n" http://localhost/add_mosque.php
curl -s -b /tmp/ck -o /dev/null -w "EditMosque:   %{http_code}  %{size_download}b\n" 'http://localhost/edit_mosque.php?id=1'
curl -s -b /tmp/ck -o /dev/null -w "QuranList:    %{http_code}  %{size_download}b\n" http://localhost/quran_mosques.php
curl -s -b /tmp/ck -o /dev/null -w "AddQuran:     %{http_code}  %{size_download}b\n" http://localhost/add_quran_mosque.php
curl -s -b /tmp/ck -o /dev/null -w "ImportExport: %{http_code}  %{size_download}b\n" http://localhost/import_export.php
curl -s -b /tmp/ck -o /dev/null -w "MapPage:      %{http_code}  %{size_download}b\n" http://localhost/mosque_maps.php
curl -s -b /tmp/ck -o /dev/null -w "AJAXStats:    %{http_code}  %{size_download}b\n" http://localhost/ajax/get_mosque_stats.php
curl -s -b /tmp/ck -o /dev/null -w "AJAXSearch:   %{http_code}  %{size_download}b\n" 'http://localhost/ajax/search_mosques.php?search=test'
curl -s -b /tmp/ck -o /dev/null -w "AJAXMap:      %{http_code}  %{size_download}b\n" http://localhost/ajax/get_mosques_for_map.php

# Logout test
curl -s -b /tmp/ck -L -o /dev/null -w "Logout:       %{http_code}  %{size_download}b\n" http://localhost/logout.php

rm -f /tmp/ck /tmp/dash
echo "DONE"
