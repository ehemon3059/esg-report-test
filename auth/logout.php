<?php
session_start();
session_destroy();
header('Location: /esg-report-test/auth/login.php');
exit;
