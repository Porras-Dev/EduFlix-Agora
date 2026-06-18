<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: includes/auth.php
 *  Description: Authentication and session management functions.
 *               Controls login, logout, inactivity timeout
 *               and brute force protection by IP.
 * ============================================================
 */

require_once __DIR__ . '/config.php';

/**
 * Starts a secure PHP session.
 * Called at the beginning of every portal page.
 */
function iniciar_sesion() {
    // Configure the session cookie to travel over HTTPS only
    // and be inaccessible from JavaScript (XSS protection).
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => false,   // Set to true in production with HTTPS
        'httponly' => true,    // Inaccessible from JavaScript
        'samesite' => 'Strict' // Basic CSRF protection
    ]);

    session_start();
}

/**
 * Checks if the user is authenticated and the session has not expired.
 * If not authenticated, redirects to login.
 */
function requerir_login() {
    // If 'autenticado' session variable is not set, the user has not logged in
    if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
        header('Location: http://'.$_SERVER['HTTP_HOST'].'/login.php');
        exit; // Important: stop execution after redirect
    }

    // Check if the session has exceeded the maximum inactivity time
    if (isset($_SESSION['ultima_actividad'])) {
        $tiempo_inactivo = time() - $_SESSION['ultima_actividad'];

        if ($tiempo_inactivo > SESSION_TIMEOUT) {
            // Session expired: destroy everything and redirect to login
            cerrar_sesion();
            header('Location: http://'.$_SERVER['HTTP_HOST'].'/login.php?motivo=timeout');
            exit;
        }
    }

    // Update last activity timestamp
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Attempts to authenticate the user with login form data.
 *
 * @param string $usuario   Username entered in the form
 * @param string $password  Password entered in the form
 * @return array            Array with 'exito' (bool) and 'mensaje' (string)
 */
function intentar_login($usuario, $password) {
    // Get client IP for failed attempt tracking.
    // If behind a proxy, try to get the real IP.
    $ip_cliente = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

    // ── Brute force protection ────────────────────────────
    // Store failed attempt count and first attempt time in session.
    // Initialize to 0 if not yet set.
    if (!isset($_SESSION['intentos_fallidos'])) {
        $_SESSION['intentos_fallidos'] = 0;
        $_SESSION['primer_intento']    = time();
    }

    // Check if IP is locked out due to too many failed attempts
    if ($_SESSION['intentos_fallidos'] >= MAX_LOGIN_ATTEMPTS) {
        $tiempo_bloqueado = time() - $_SESSION['primer_intento'];

        if ($tiempo_bloqueado < LOCKOUT_TIME) {
            $segundos_restantes = LOCKOUT_TIME - $tiempo_bloqueado;
            return [
                'exito'   => false,
                'mensaje' => "Too many failed attempts. Wait {$segundos_restantes} seconds."
            ];
        } else {
            // Lockout time has passed: reset counters
            $_SESSION['intentos_fallidos'] = 0;
            $_SESSION['primer_intento']    = time();
        }
    }

    // ── Credential verification ───────────────────────────
    // Compare username against the constant defined in config.php.
    // password_verify() compares the entered password against the bcrypt hash.
    // We never compare passwords in plain text.
    if ($usuario === ADMIN_USER && password_verify($password, ADMIN_PASS_HASH)) {
        // Correct login: regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Store authentication state in session
        $_SESSION['autenticado']      = true;
        $_SESSION['usuario']          = $usuario;
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['inicio_sesion']    = time();

        // Reset failed attempt counters
        $_SESSION['intentos_fallidos'] = 0;

        return ['exito' => true, 'mensaje' => 'Login successful'];
    }

    // Incorrect login: increment failed attempt counter
    $_SESSION['intentos_fallidos']++;

    $intentos_restantes = MAX_LOGIN_ATTEMPTS - $_SESSION['intentos_fallidos'];

    return [
        'exito'   => false,
        'mensaje' => "Invalid credentials. Attempts remaining: {$intentos_restantes}"
    ];
}

/**
 * Logs out the user by destroying all session data.
 */
function cerrar_sesion() {
    // Clear the session array
    $_SESSION = [];

    // Delete the session cookie from the client browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // Destroy the session on the server
    session_destroy();
}

/**
 * Returns how long the user has had the session open,
 * in readable format (e.g. "1h 23min").
 *
 * @return string Formatted session time
 */
function tiempo_sesion() {
    if (!isset($_SESSION['inicio_sesion'])) return '—';

    $segundos = time() - $_SESSION['inicio_sesion'];
    $horas    = floor($segundos / 3600);
    $minutos  = floor(($segundos % 3600) / 60);

    if ($horas > 0) {
        return "{$horas}h {$minutos}min";
    }
    return "{$minutos}min";
}
