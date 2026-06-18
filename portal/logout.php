<?php
require_once __DIR__ . '/includes/auth.php';
iniciar_sesion();
cerrar_sesion();
header('Location: http://'.$_SERVER['HTTP_HOST'].'/login.php');
exit;
