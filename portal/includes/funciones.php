<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: includes/funciones.php
 *  Description: System functions that collect real server data:
 *               Docker status, CPU/RAM/disk metrics, logs,
 *               backups and Jellyfin statistics via REST API.
 * ============================================================
 */

require_once __DIR__ . '/config.php';

// ════════════════════════════════════════════════════════════
//  SECTION 1: DOCKER - Container status
// ════════════════════════════════════════════════════════════

/**
 * Gets the status of all defined Docker containers.
 *
 * shell_exec() runs a system command and returns its output as a string.
 *
 * @return array List of containers with name, status and image
 */
function obtener_estado_docker() {
    $salida = shell_exec("docker ps -a --format '{{json .}}' 2>/dev/null");
    $contenedores = [];
    if (empty($salida)) return $contenedores;
    $lineas = explode("\n", trim($salida));
    foreach ($lineas as $linea) {
        if (empty($linea)) continue;
        $datos = json_decode($linea, true);
        if (!$datos) continue;
        $estado  = strtolower($datos['State'] ?? 'unknown');
        $running = ($estado === 'running');
        $contenedores[] = [
            'nombre'  => $datos['Names']  ?? 'unknown',
            'imagen'  => $datos['Image']  ?? '-',
            'estado'  => $estado,
            'activo'  => $running,
            'uptime'  => $datos['Status'] ?? '-',
            'puertos' => $datos['Ports']  ?? '-',
        ];
    }
    return $contenedores;
}

/**
 * Checks if a specific Docker container is running.
 *
 * @param string $nombre  Container name (e.g. 'jellyfin')
 * @return bool           true if running, false otherwise
 */
function contenedor_activo($nombre) {
    $resultado = shell_exec("docker inspect --format='{{.State.Running}}' {$nombre} 2>/dev/null");
    return trim($resultado) === 'true';
}

// ════════════════════════════════════════════════════════════
//  SECTION 2: SYSTEM METRICS - CPU, RAM, Disk
// ════════════════════════════════════════════════════════════

/**
 * Gets the current CPU usage percentage.
 *
 * Reads /proc/stat, a special Linux file that the kernel updates
 * in real time with processor statistics.
 *
 * @return float  CPU usage percentage (0-100)
 */
function obtener_cpu() {
    $stat1    = file('/proc/stat');
    $valores1 = preg_split('/\s+/', trim($stat1[0]));
    usleep(100000);
    $stat2    = file('/proc/stat');
    $valores2 = preg_split('/\s+/', trim($stat2[0]));
    $total1 = array_sum(array_slice($valores1, 1));
    $total2 = array_sum(array_slice($valores2, 1));
    $idle1  = $valores1[4];
    $idle2  = $valores2[4];
    $delta_total = $total2 - $total1;
    $delta_idle  = $idle2  - $idle1;
    if ($delta_total === 0) return 0;
    $uso = (($delta_total - $delta_idle) / $delta_total) * 100;
    return round($uso, 1);
}

/**
 * Gets RAM memory metrics.
 *
 * Reads /proc/meminfo, a kernel file with detailed memory info in kilobytes.
 *
 * @return array  Array with total, used, free and usage percentage
 */
function obtener_ram() {
    $lineas = file('/proc/meminfo');
    $mem    = [];
    foreach ($lineas as $linea) {
        if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
            $mem[$m[1]] = (int)$m[2];
        }
    }
    $total     = $mem['MemTotal']     ?? 0;
    $available = $mem['MemAvailable'] ?? 0;
    $usado     = $total - $available;
    return [
        'total_mb'   => round($total     / 1024, 0),
        'usado_mb'   => round($usado     / 1024, 0),
        'libre_mb'   => round($available / 1024, 0),
        'porcentaje' => $total > 0 ? round(($usado / $total) * 100, 1) : 0,
    ];
}

/**
 * Gets disk usage metrics for a mount point.
 *
 * @param string $ruta  Mount point path (e.g. '/mnt/datos')
 * @return array        Array with total, used, free and percentage
 */
function obtener_disco($ruta = '/mnt/datos') {
    $total = disk_total_space($ruta);
    $libre = disk_free_space($ruta);
    if ($total === false || $libre === false) {
        return ['total_gb' => 0, 'usado_gb' => 0, 'libre_gb' => 0, 'porcentaje' => 0];
    }
    $usado = $total - $libre;
    return [
        'total_gb'   => round($total / (1024 ** 3), 1),
        'usado_gb'   => round($usado / (1024 ** 3), 1),
        'libre_gb'   => round($libre / (1024 ** 3), 1),
        'porcentaje' => round(($usado / $total) * 100, 1),
    ];
}

/**
 * Gets how long the system has been running (uptime).
 *
 * @return string  Uptime in readable format (e.g. "3d 2h 15min")
 */
function obtener_uptime() {
    $contenido = file_get_contents('/proc/uptime');
    $segundos  = (int)explode(' ', $contenido)[0];
    $dias    = floor($segundos / 86400);
    $horas   = floor(($segundos % 86400) / 3600);
    $minutos = floor(($segundos % 3600)  / 60);
    $resultado = '';
    if ($dias > 0)  $resultado .= "{$dias}d ";
    if ($horas > 0) $resultado .= "{$horas}h ";
    $resultado .= "{$minutos}min";
    return trim($resultado);
}

/**
 * Gets system load averages for 1, 5 and 15 minutes.
 *
 * @return array  Array with load1, load5, load15 and CPU core count
 */
function obtener_carga() {
    $carga   = sys_getloadavg();
    $nucleos = (int)shell_exec('nproc 2>/dev/null') ?: 1;
    return [
        'load1'    => round($carga[0], 2),
        'load5'    => round($carga[1], 2),
        'load15'   => round($carga[2], 2),
        'nucleos'  => $nucleos,
        'porciento' => round(($carga[0] / $nucleos) * 100, 1),
    ];
}

// ════════════════════════════════════════════════════════════
//  SECTION 3: LOGS - Latest log file entries
// ════════════════════════════════════════════════════════════

/**
 * Reads the last N lines of a log file.
 *
 * @param string $fichero   Path to the log file
 * @param int    $lineas    Number of lines to return
 * @return array            Array of strings with the last lines
 */
function leer_log($fichero, $lineas = 50) {
    if (!file_exists($fichero) || !is_readable($fichero)) {
        return ["[File {$fichero} does not exist or is not readable]"];
    }
    $salida = shell_exec("tail -n {$lineas} " . escapeshellarg($fichero) . " 2>/dev/null");
    if (empty($salida)) return ["[Log is empty]"];
    $resultado = array_filter(explode("\n", $salida));
    return array_values(array_reverse($resultado));
}

/**
 * Reads the systemd journal log for a specific service.
 *
 * @param string $servicio  Service name (e.g. 'fail2ban') or 'sistema'
 * @param int    $lineas    Number of lines to return
 * @return array            Array of strings with journal entries
 */
function leer_journal($servicio, $lineas = 30) {
    $servicio_safe = escapeshellarg($servicio);
    if ($servicio === 'syslog' || $servicio === 'sistema') {
        $salida = shell_exec("journalctl -n {$lineas} --no-pager 2>/dev/null");
    } else {
        $salida = shell_exec("journalctl -u {$servicio_safe} -n {$lineas} --no-pager 2>/dev/null");
    }
    if (empty($salida)) {
        return ["[No journal entries found for {$servicio}]"];
    }
    $resultado = array_filter(explode("\n", $salida));
    return array_values(array_reverse($resultado));
}

// ════════════════════════════════════════════════════════════
//  SECTION 4: RESTIC - Backup status
// ════════════════════════════════════════════════════════════

/**
 * Gets the list of snapshots from the Restic repository.
 *
 * @return array  Array of snapshots with id, date and paths
 */
function obtener_snapshots_restic() {
    $repo   = escapeshellarg(RESTIC_REPO);
    $salida = shell_exec("timeout 10 sudo restic -r {$repo} --password-file /etc/restic-password snapshots --json 2>/dev/null");
    if (empty($salida)) return [];
    $snapshots = json_decode($salida, true);
    if (!is_array($snapshots)) return [];
    $resultado = [];
    foreach ($snapshots as $snap) {
        $resultado[] = [
            'id'    => substr($snap['id'] ?? '', 0, 8),
            'fecha' => date('d/m/Y H:i', strtotime($snap['time'] ?? '')),
            'paths' => implode(', ', $snap['paths'] ?? []),
            'size'  => '-',
        ];
    }
    return array_reverse($resultado);
}

/**
 * Reads the last lines of the backup log.
 *
 * @return array  Array with the last log lines
 */
function ultimo_backup_log() {
    return leer_log(BACKUP_LOG, 20);
}

// ════════════════════════════════════════════════════════════
//  SECTION 5: JELLYFIN - Statistics via REST API
// ════════════════════════════════════════════════════════════

/**
 * Makes an HTTP request to the Jellyfin REST API.
 * Supports GET, POST and DELETE.
 *
 * @param string $endpoint  API path (e.g. '/Users')
 * @param string $metodo    HTTP method (GET, POST, DELETE)
 * @param array  $datos     Data for POST as array
 * @return array|null       Array with data or null on error
 */
function jellyfin_api($endpoint, $metodo = 'GET', $datos = null) {
    $ch = curl_init();
    $opciones = [
        CURLOPT_URL            => JELLYFIN_URL . $endpoint . (strpos($endpoint, '?') !== false ? '&' : '?') . 'api_key=' . JELLYFIN_API_KEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'X-Emby-Token: ' . JELLYFIN_API_KEY,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ];
    if ($metodo === 'POST') {
        $opciones[CURLOPT_POST]       = true;
        $opciones[CURLOPT_POSTFIELDS] = $datos ? json_encode($datos) : '{}';
    } elseif ($metodo === 'DELETE') {
        $opciones[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }
    curl_setopt_array($ch, $opciones);
    $respuesta   = curl_exec($ch);
    $codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($respuesta === false) return null;
    if ($metodo === 'DELETE') return $codigo_http === 204 || $codigo_http === 200;
    if ($codigo_http !== 200) return null;
    return json_decode($respuesta, true);
}

/**
 * Counts total video items in Jellyfin library.
 *
 * @return int  Total video count
 */
function jellyfin_contar_videos() {
    $r = jellyfin_api('/Items?IncludeItemTypes=Video&Recursive=true');
    return $r['TotalRecordCount'] ?? 0;
}

/**
 * Gets general Jellyfin statistics.
 *
 * @return array  Array with user count, items, active sessions, etc.
 */
function obtener_stats_jellyfin() {
    $usuarios = jellyfin_api('/Users');
    $sesiones = jellyfin_api('/Sessions');
    $items    = jellyfin_api('/Items/Counts');
    $info     = jellyfin_api('/System/Info');
    return [
        'usuarios'         => is_array($usuarios) ? count($usuarios) : 0,
        'sesiones_activas' => is_array($sesiones) ? count($sesiones) : 0,
        'version'          => $info['Version']      ?? '-',
        'servidor'         => $info['ServerName']   ?? '-',
        'peliculas'        => jellyfin_contar_videos(),
        'episodios'        => $items['EpisodeCount'] ?? 0,
        'musica'           => $items['SongCount']    ?? 0,
        'online'           => contenedor_activo('jellyfin'),
    ];
}

// ════════════════════════════════════════════════════════════
//  SECTION 6: POSTGRESQL - Statistics via PDO
// ════════════════════════════════════════════════════════════

/**
 * Creates and returns a PDO connection to PostgreSQL.
 *
 * @return PDO|null  PDO object or null if connection fails
 */
function conectar_bd() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Gets PostgreSQL database statistics.
 *
 * @return array  Array with size, connections, tables, etc.
 */
function obtener_stats_bd() {
    $pdo = conectar_bd();
    if (!$pdo) return ['error' => 'Cannot connect to PostgreSQL'];
    try {
        $stmt = $pdo->query("SELECT pg_size_pretty(pg_database_size('" . DB_NAME . "')) AS tamano");
        $tamano = $stmt->fetch()['tamano'] ?? '-';
        $stmt = $pdo->query("SELECT count(*) AS total FROM pg_stat_activity WHERE datname = '" . DB_NAME . "'");
        $conexiones = $stmt->fetch()['total'] ?? 0;
        $stmt = $pdo->query("SELECT count(*) AS total FROM information_schema.tables WHERE table_schema = 'public'");
        $tablas = $stmt->fetch()['total'] ?? 0;
        $stmt    = $pdo->query("SELECT version()");
        $version = $stmt->fetch()['version'] ?? '-';
        preg_match('/PostgreSQL ([\d.]+)/', $version, $m);
        $version_corta = $m[1] ?? $version;
        return [
            'tamano'     => $tamano,
            'conexiones' => $conexiones,
            'tablas'     => $tablas,
            'version'    => $version_corta,
            'online'     => true,
        ];
    } catch (PDOException $e) {
        error_log("DB query error: " . $e->getMessage());
        return ['error' => 'Error querying the database', 'online' => false];
    }
}

// ════════════════════════════════════════════════════════════
//  SECTION 7: UTILITIES - Helper functions
// ════════════════════════════════════════════════════════════

/**
 * Returns the CSS color class based on usage percentage.
 *
 * @param float $porcentaje  Usage percentage (0-100)
 * @return string            CSS class ('ok', 'warning', 'danger')
 */
function clase_uso($porcentaje) {
    if ($porcentaje < 60) return 'ok';
    if ($porcentaje < 85) return 'warning';
    return 'danger';
}

/**
 * Sanitizes a string for safe display in HTML.
 * Prevents XSS attacks by converting special HTML characters.
 *
 * @param string $texto  Text to sanitize
 * @return string        HTML-safe text
 */
function html_safe($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a Unix timestamp into a readable date and time.
 *
 * @param int    $timestamp  Unix timestamp
 * @param string $formato    Date format
 * @return string            Formatted date
 */
function formatear_fecha($timestamp, $formato = 'd/m/Y H:i') {
    return date($formato, $timestamp);
}
