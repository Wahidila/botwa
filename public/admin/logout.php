<?php

require_once __DIR__ . '/../../src/Bootstrap.php';
\BotWA\Bootstrap::init();

\BotWA\AdminAuth::logout();

header('Location: login.php');
exit;
