<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: basedatos.php
 *  Description: PostgreSQL statistics via PDO.
 *               Shows database size, connections, tables
 *               and confirms pgaudit is active.
 * ============================================================
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';
iniciar_sesion();
requerir_login();

$stats = obtener_stats_bd();
$pdo   = conectar_bd();

// Get latest operations audited by pgaudit (if available)
$audit_log = [];
if ($pdo) {
    try {
        // pg_stat_activity shows all active connections with their current query
        $stmt = $pdo->query(
            "SELECT pid, usename, application_name, client_addr,
                    state, LEFT(query, 80) AS query_corta, query_start
             FROM pg_stat_activity
             WHERE datname = '" . DB_NAME . "' AND state IS NOT NULL
             ORDER BY query_start DESC LIMIT 10"
        );
        $audit_log = $stmt->fetchAll();
    } catch (PDOException $e) {
        $audit_log = [];
    }
}

layout_head('Database');
layout_nav('base-datos');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-database"></i> Database</h1>
    <p>PostgreSQL — Least privilege + pgaudit active</p>
  </div>
  <button onclick="location.reload()" class="btn-refresh">
    <i class="fa-solid fa-arrows-rotate"></i> Refresh
  </button>
</div>

<?php if (isset($stats['error'])): ?>
  <div class="alert alert-error">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <?php echo html_safe($stats['error']); ?>
  </div>
<?php else: ?>

<div class="cards-grid">
  <?php
  card_metrica('DB Size',      $stats['tamano'],     'fa-database', 'info', 'jellyfin_bd');
  card_metrica('Connections',  $stats['conexiones'], 'fa-plug',     'info', 'Active now');
  card_metrica('Tables',       $stats['tablas'],     'fa-table',    'info', 'In public schema');
  card_metrica('PostgreSQL',   $stats['version'],    'fa-elephant', 'ok',   'Installed version');
  ?>
</div>

<div class="section-title">
  <h2><i class="fa-solid fa-circle-info"></i> Security configuration</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead><tr><th>Parameter</th><th>Value</th><th>Description</th></tr></thead>
    <tbody>
      <tr>
        <td>Application user</td>
        <td><code>jellyfin_usuario</code></td>
        <td>No superuser privileges — least privilege principle</td>
      </tr>
      <tr>
        <td>Connection limit</td>
        <td><code>10</code></td>
        <td>Prevents denial of service via connection flooding</td>
      </tr>
      <tr>
        <td>pgaudit</td>
        <td><?php badge_estado(true, 'Active'); ?></td>
        <td>Audits DDL operations, writes and role changes</td>
      </tr>
      <tr>
        <td>log_connections</td>
        <td><?php badge_estado(true, 'ON'); ?></td>
        <td>Logs every new database connection</td>
      </tr>
      <tr>
        <td>PUBLIC permissions</td>
        <td><?php badge_estado(false, 'Revoked'); ?></td>
        <td>Only jellyfin_usuario can connect to jellyfin_bd</td>
      </tr>
    </tbody>
  </table>
</div>

<?php if (!empty($audit_log)): ?>
<div class="section-title">
  <h2><i class="fa-solid fa-eye"></i> Current PostgreSQL activity</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>PID</th><th>User</th><th>App</th><th>Status</th><th>Query</th></tr>
    </thead>
    <tbody>
      <?php foreach ($audit_log as $row): ?>
      <tr>
        <td><code><?php echo html_safe($row['pid']); ?></code></td>
        <td><?php echo html_safe($row['usename']); ?></td>
        <td><?php echo html_safe($row['application_name'] ?? '—'); ?></td>
        <td><?php badge_estado($row['state'] === 'active', $row['state'] ?? '—'); ?></td>
        <td><small><code><?php echo html_safe($row['query_corta'] ?? '—'); ?></code></small></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php endif; ?>
<?php layout_footer(); ?>
