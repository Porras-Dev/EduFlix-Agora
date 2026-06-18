<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: index.php (Main Dashboard)
 *  Description: Portal home page. Shows a visual summary of
 *               the entire infrastructure: service status,
 *               key metrics and latest security alerts.
 * ============================================================
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';

// ── Access control ────────────────────────────────────────
// iniciar_sesion() starts a secure PHP session.
// requerir_login() checks for an active session.
// If none exists, redirects to /login.php automatically.
iniciar_sesion();
requerir_login();

// ── System data collection ────────────────────────────────
// We call all functions from funciones.php to get
// real server data before generating the HTML.
$cpu          = obtener_cpu();            // CPU usage %
$ram          = obtener_ram();            // RAM memory data
$disco        = obtener_disco();          // 1TB HDD data
$carga        = obtener_carga();          // System load average
$uptime       = obtener_uptime();         // System uptime
$contenedores = obtener_estado_docker();  // Docker container status
$stats_jf     = obtener_stats_jellyfin(); // Jellyfin stats
$logs_recientes = leer_log(BACKUP_LOG, 5); // Last 5 lines of backup log

// ── HTML generation ───────────────────────────────────────
layout_head('Dashboard');
layout_nav('dashboard');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-gauge"></i> Dashboard</h1>
    <p>General overview of the EduFlix Agora infrastructure</p>
  </div>
  <!-- Button to reload the page and get fresh data -->
  <button onclick="location.reload()" class="btn-refresh">
    <i class="fa-solid fa-arrows-rotate"></i> Refresh
  </button>
</div>

<!-- ══════════════════════════════════════════════════════════
     MAIN METRIC CARDS (KPIs)
     ══════════════════════════════════════════════════════════ -->
<div class="cards-grid">
  <?php
  // card_metrica() generates each card with title, value, icon and color.
  // clase_uso() returns 'ok' (green), 'warning' (orange) or 'danger' (red)
  card_metrica('CPU',         $cpu . '%',               'fa-microchip',  clase_uso($cpu),              "Load: {$carga['load1']} · {$carga['nucleos']} cores");
  card_metrica('RAM',         $ram['usado_mb'] . ' MB', 'fa-memory',     clase_uso($ram['porcentaje']), "{$ram['porcentaje']}% of {$ram['total_mb']} MB");
  card_metrica('Disk /datos', $disco['usado_gb'] . ' GB','fa-hard-drive', clase_uso($disco['porcentaje']), "{$disco['porcentaje']}% of {$disco['total_gb']} GB");
  card_metrica('Uptime',      $uptime,                  'fa-clock',      'info',                       'System uptime');
  ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     DOCKER SERVICES STATUS
     ══════════════════════════════════════════════════════════ -->
<div class="section-title">
  <h2><i class="fa-solid fa-boxes-stacked"></i> Docker Services</h2>
</div>

<div class="table-card">
  <table class="data-table">
    <thead>
      <tr>
        <th>Container</th>
        <th>Image</th>
        <th>Status</th>
        <th>Uptime</th>
        <th>Ports</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($contenedores as $c): ?>
      <tr>
        <td><strong><?php echo html_safe($c['nombre']); ?></strong></td>
        <td><code><?php echo html_safe($c['imagen']); ?></code></td>
        <td><?php badge_estado($c['activo']); ?></td>
        <td><?php echo html_safe($c['uptime']); ?></td>
        <td><small><?php echo html_safe($c['puertos']); ?></small></td>
      </tr>
      <?php endforeach; ?>

      <?php if (empty($contenedores)): ?>
      <tr>
        <td colspan="5" class="text-center text-muted">Cannot retrieve Docker data</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ══════════════════════════════════════════════════════════
     JELLYFIN STATS + LATEST BACKUP
     ══════════════════════════════════════════════════════════ -->
<div class="two-col-grid">

  <div class="info-card">
    <div class="info-card-header">
      <i class="fa-solid fa-film"></i> Jellyfin
      <?php badge_estado($stats_jf['online']); ?>
    </div>
    <div class="info-card-body">
      <div class="stat-row"><span>Registered users</span><strong><?php echo $stats_jf['usuarios']; ?></strong></div>
      <div class="stat-row"><span>Active sessions</span><strong><?php echo $stats_jf['sesiones_activas']; ?></strong></div>
      <div class="stat-row"><span>Movies / Episodes</span><strong><?php echo $stats_jf['peliculas'] . ' / ' . $stats_jf['episodios']; ?></strong></div>
      <div class="stat-row"><span>Version</span><strong><?php echo html_safe($stats_jf['version']); ?></strong></div>
    </div>
    <div class="info-card-footer">
      <a href="/jellyfin.php" class="link-more">View details <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

  <div class="info-card">
    <div class="info-card-header">
      <i class="fa-solid fa-box-archive"></i> Latest Backup
    </div>
    <div class="info-card-body log-mini">
      <?php foreach ($logs_recientes as $linea): if (empty(trim($linea))) continue; ?>
        <div class="log-line"><?php echo html_safe($linea); ?></div>
      <?php endforeach; ?>
    </div>
    <div class="info-card-footer">
      <a href="/backups.php" class="link-more">View all backups <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     RESOURCE USAGE BARS
     ══════════════════════════════════════════════════════════ -->
<div class="section-title">
  <h2><i class="fa-solid fa-chart-bar"></i> Resource usage</h2>
</div>

<div class="resource-bars-card">
  <div class="resource-row">
    <span class="resource-label">CPU</span>
    <?php barra_progreso($cpu, clase_uso($cpu)); ?>
  </div>
  <div class="resource-row">
    <span class="resource-label">RAM</span>
    <?php barra_progreso($ram['porcentaje'], clase_uso($ram['porcentaje'])); ?>
  </div>
  <div class="resource-row">
    <span class="resource-label">Disk /datos</span>
    <?php barra_progreso($disco['porcentaje'], clase_uso($disco['porcentaje'])); ?>
  </div>
</div>

<?php
// layout_footer() closes the HTML: </main>, </div>, footer and </body></html>
layout_footer();
?>
