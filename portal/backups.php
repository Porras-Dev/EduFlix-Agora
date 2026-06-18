<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: backups.php
 *  Description: Restic backup viewer. Shows all snapshots
 *               from the encrypted repository and the log
 *               of the latest cron backup execution.
 * ============================================================
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';
iniciar_sesion();
requerir_login();
$snapshots  = obtener_snapshots_restic();
$log_backup = leer_log(BACKUP_LOG, 30);
layout_head('Backups');
layout_nav('backups');
?>
<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-box-archive"></i> Backups</h1>
    <p>Encrypted Restic repository — Retention: 7 daily · 4 weekly · 3 monthly</p>
  </div>
  <button onclick="location.reload()" class="btn-refresh">
    <i class="fa-solid fa-arrows-rotate"></i> Refresh
  </button>
</div>

<div class="cards-grid">
  <?php
  card_metrica('Total snapshots', count($snapshots), 'fa-layer-group', 'info',  'Stored backups');
  card_metrica('Repository',      'AES-256 encrypted','fa-lock',        'ok',    RESTIC_REPO);
  card_metrica('Schedule',        'Daily at 02:00',  'fa-clock',       'info',  'Scheduled via cron');
  card_metrica('Notification',    'Automatic email', 'fa-envelope',    'ok',    'Admin email configured in .env');
  ?>
</div>

<div class="section-title">
  <h2><i class="fa-solid fa-layer-group"></i> Available snapshots</h2>
</div>

<?php if (!empty($snapshots)): ?>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>ID</th><th>Date & Time</th><th>Contents</th></tr>
    </thead>
    <tbody>
      <?php foreach ($snapshots as $snap): ?>
      <tr>
        <td><code><?php echo html_safe($snap['id']); ?></code></td>
        <td><?php echo html_safe($snap['fecha']); ?></td>
        <td><small><?php echo html_safe($snap['paths']); ?></small></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="alert alert-warning">
  <i class="fa-solid fa-triangle-exclamation"></i>
  Cannot list snapshots. Check that the Restic repository is accessible
  and that the portal has read permissions.
</div>
<?php endif; ?>

<div class="section-title">
  <h2><i class="fa-solid fa-scroll"></i> Latest backup log</h2>
</div>
<div class="log-card">
  <?php foreach ($log_backup as $linea): ?>
    <?php if (empty(trim($linea))) continue; ?>
    <div class="log-line <?php echo strpos($linea, 'ERROR') !== false ? 'log-error' : ''; ?>">
      <?php echo html_safe($linea); ?>
    </div>
  <?php endforeach; ?>
</div>
<?php layout_footer(); ?>
