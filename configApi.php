<?php

date_default_timezone_set('Atlantic/Canary');

ini_set('curl.cainfo', '/dev/null');
set_time_limit(0);
ini_set('default_socket_timeout', 7200);

ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
mysqli_report(MYSQLI_REPORT_OFF);
