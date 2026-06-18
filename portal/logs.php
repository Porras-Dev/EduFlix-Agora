<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: logs.php
 *  Description: System log viewer with source filtering.
 *               Shows the most recent log entries for backup,
 *               Fail2Ban and system journal.
 *               Real-time updates via WebSockets
 *               (Node.js server on port 8765).
 * ============================================================
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';
iniciar_sesion();
requerir_login();

// Available log sources
$fuentes_log = [
    'backup'   => ['label' => 'Restic Backup',    'tipo' => 'file', 'ruta' => BACKUP_LOG],
    'fail2ban' => ['label' => 'Fail2Ban',          'tipo' => 'file', 'ruta' => '/var/log/fail2ban.log'],
    'phpfpm'   => ['label' => 'PHP-FPM',           'tipo' => 'file', 'ruta' => '/var/log/php8.3-fpm.log'],
    'sistema'  => ['label' => 'Kernel / System',   'tipo' => 'file', 'ruta' => '/var/log/syslog'],
];

// Get selected source from GET parameter
$fuente_seleccionada = 'fail2ban';
if (isset($_GET['fuente']) && array_key_exists($_GET['fuente'], $fuentes_log)) {
    $fuente_seleccionada = $_GET['fuente'];
}

// Number of lines to show (default 50, max 200)
$num_lineas = min(200, max(20, (int)($_GET['lineas'] ?? 50)));

// Load lines based on selected source type
$config_fuente = $fuentes_log[$fuente_seleccionada];
if ($config_fuente['tipo'] === 'file') {
    $lineas = leer_log($config_fuente['ruta'], $num_lineas);
} else {
    $lineas = leer_journal($config_fuente['servicio'], $num_lineas);
}

layout_head('Logs');
layout_nav('logs');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-scroll"></i> Log Viewer</h1>
    <p>Latest entries from system logs</p>
  </div>
  <div style="display:flex; gap:.8rem; align-items:center;">
    <span id="ws-estado" style="font-size:.85rem; color:var(--gris-texto);">
      <i class="fa-solid fa-circle" style="color:#aaa;"></i> WebSocket disconnected
    </span>
    <button onclick="location.reload()" class="btn-refresh">
      <i class="fa-solid fa-arrows-rotate"></i> Refresh
    </button>
  </div>
</div>

<!-- Log source selector -->
<form method="get" class="log-filter-form">
  <div class="filter-group">
    <label for="fuente">Source:</label>
    <select name="fuente" id="fuente" onchange="this.form.submit()">
      <?php foreach ($fuentes_log as $clave => $cfg): ?>
        <option value="<?php echo $clave; ?>"
          <?php echo ($clave === $fuente_seleccionada) ? 'selected' : ''; ?>>
          <?php echo html_safe($cfg['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label for="lineas">Lines:</label>
    <select name="lineas" id="lineas" onchange="this.form.submit()">
      <?php foreach ([20, 50, 100, 200] as $n): ?>
        <option value="<?php echo $n; ?>" <?php echo ($n == $num_lineas) ? 'selected' : ''; ?>>
          Last <?php echo $n; ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<!-- Log viewer -->
<div class="log-card" id="log-container" style="max-height:600px; overflow-y:auto;">
  <?php
  $error_words   = ['error', 'ERROR', 'FAILED', 'failed', 'fatal', 'FATAL'];
  $warning_words = ['warn', 'WARN', 'WARNING', 'warning'];
  $ok_words      = ['success', 'OK', 'started', 'active', 'completed'];

  foreach ($lineas as $linea):
    if (empty(trim($linea))) continue;
    $clase_linea = '';
    foreach ($error_words   as $p) { if (strpos($linea, $p) !== false) { $clase_linea = 'log-error';   break; } }
    foreach ($warning_words as $p) { if (strpos($linea, $p) !== false) { $clase_linea = 'log-warning'; break; } }
    foreach ($ok_words      as $p) { if (strpos($linea, $p) !== false) { $clase_linea = 'log-ok';      break; } }
  ?>
    <div class="log-line <?php echo $clase_linea; ?>">
      <?php echo html_safe($linea); ?>
    </div>
  <?php endforeach; ?>
</div>

<div style="text-align:right; margin-top:.5rem;">
  <small class="text-muted">
    Showing last <?php echo count($lineas); ?> entries from
    <strong><?php echo html_safe($config_fuente['label']); ?></strong>
  </small>
</div>

<!-- WebSocket: real-time updates -->
<script>
(function() {
    var activeSource = '<?php echo $fuente_seleccionada; ?>';
    var wsHost = window.location.hostname;
    var wsUrl  = 'ws://' + wsHost + ':8765';

    var container = document.getElementById('log-container');
    var statusEl  = document.getElementById('ws-estado');

    var errorWords   = ['error', 'ERROR', 'FAILED', 'failed', 'fatal', 'FATAL'];
    var warningWords = ['warn', 'WARN', 'WARNING', 'warning'];
    var okWords      = ['success', 'OK', 'started', 'active', 'completed'];

    function classifyLine(text) {
        for (var i = 0; i < errorWords.length;   i++) { if (text.indexOf(errorWords[i])   !== -1) return 'log-error'; }
        for (var i = 0; i < warningWords.length; i++) { if (text.indexOf(warningWords[i]) !== -1) return 'log-warning'; }
        for (var i = 0; i < okWords.length;      i++) { if (text.indexOf(okWords[i])      !== -1) return 'log-ok'; }
        return '';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function connectWS() {
        var ws = new WebSocket(wsUrl);

        ws.onopen = function() {
            statusEl.innerHTML = '<i class="fa-solid fa-circle" style="color:#22c55e;"></i> WebSocket connected — real-time active';
            ws.send(activeSource);
        };

        ws.onmessage = function(event) {
            var lines = event.data.split('\n');
            lines.forEach(function(line) {
                if (!line.trim()) return;
                var div = document.createElement('div');
                div.className = 'log-line ' + classifyLine(line);
                div.innerHTML = escapeHtml(line);
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
        };

        ws.onerror = function() {
            statusEl.innerHTML = '<i class="fa-solid fa-circle" style="color:#ef4444;"></i> WebSocket error';
        };

        ws.onclose = function() {
            statusEl.innerHTML = '<i class="fa-solid fa-circle" style="color:#aaa;"></i> WebSocket disconnected — reconnecting...';
            setTimeout(connectWS, 5000);
        };
    }

    connectWS();
})();
</script>

<?php layout_footer(); ?>
