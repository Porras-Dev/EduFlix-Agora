<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: servicios.php
 *  Description: Detailed view of all infrastructure services.
 *               Shows Docker, Fail2Ban, PHP-FPM and NGINX
 *               with their status and statistics.
 * ============================================================
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion();
requerir_login();

// Get all Docker container data
$contenedores = obtener_estado_docker();

// Define system services to monitor.
// For each one we run a command to check if it's running.
$servicios_sistema = [
    [
        'nombre'  => 'PHP-FPM 8.3',
        'icono'   => 'fa-code',
        'comando' => 'systemctl is-active php8.3-fpm',
        'desc'    => 'PHP processor for the administration portal',
    ],
    [
        'nombre'  => 'NGINX',
        'icono'   => 'fa-globe',
        'comando' => "docker inspect --format='{{.State.Running}}' nginx",
        'desc'    => 'Web server and HTTPS reverse proxy',
    ],
    [
        'nombre'  => 'Fail2Ban',
        'icono'   => 'fa-shield-halved',
        'comando' => 'systemctl is-active fail2ban',
        'desc'    => 'Brute force attack protection',
    ],
    [
        'nombre'  => 'UFW Firewall',
        'icono'   => 'fa-fire-flame-curved',
        'comando' => 'sudo ufw status 2>/dev/null | grep -c "Status: active"',
        'desc'    => 'Firewall — deny incoming, allow outgoing',
    ],
    [
        'nombre'  => 'unattended-upgrades',
        'icono'   => 'fa-rotate',
        'comando' => 'systemctl is-active unattended-upgrades',
        'desc'    => 'Automatic security updates',
    ],
];

// For each service, run the command and evaluate if it is active
foreach ($servicios_sistema as &$srv) {
    // shell_exec() runs the command; trim() removes trailing newlines
    $resultado = trim(shell_exec($srv['comando'] . ' 2>/dev/null') ?? '');

    // Determine if active based on command output:
    // 'active' for systemctl, 'true' for docker inspect, '1' for grep -c
    $srv['activo'] = in_array($resultado, ['active', 'true', '1']);
    $srv['output'] = $resultado;
}
unset($srv); // Release foreach reference

// Get active Fail2Ban jails
$fail2ban_jaulas = shell_exec("fail2ban-client status 2>/dev/null | grep 'Jail list' | cut -d: -f2");
$jaulas = array_filter(array_map('trim', explode(',', $fail2ban_jaulas ?? '')));

layout_head('Services');
layout_nav('servicios');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-server"></i> Services</h1>
    <p>Detailed status of all infrastructure services</p>
  </div>
  <button onclick="location.reload()" class="btn-refresh">
    <i class="fa-solid fa-arrows-rotate"></i> Refresh
  </button>
</div>

<!-- DOCKER CONTAINERS -->
<div class="section-title">
  <h2><i class="fa-brands fa-docker"></i> Docker Containers</h2>
</div>

<div class="services-grid">
  <?php foreach ($contenedores as $c): ?>
  <div class="service-card <?php echo $c['activo'] ? 'service-ok' : 'service-down'; ?>">
    <div class="service-card-header">
      <i class="fa-solid fa-box"></i>
      <strong><?php echo html_safe($c['nombre']); ?></strong>
      <?php badge_estado($c['activo']); ?>
    </div>
    <div class="service-card-body">
      <div class="service-detail"><span>Image:</span><code><?php echo html_safe($c['imagen']); ?></code></div>
      <div class="service-detail"><span>Status:</span><span><?php echo html_safe($c['uptime']); ?></span></div>
      <?php if (!empty($c['puertos']) && $c['puertos'] !== '—'): ?>
      <div class="service-detail"><span>Ports:</span><small><?php echo html_safe($c['puertos']); ?></small></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- SYSTEM SERVICES (systemd) -->
<div class="section-title">
  <h2><i class="fa-solid fa-cogs"></i> System Services</h2>
</div>

<div class="services-grid">
  <?php foreach ($servicios_sistema as $srv): ?>
  <div class="service-card <?php echo $srv['activo'] ? 'service-ok' : 'service-down'; ?>">
    <div class="service-card-header">
      <i class="fa-solid <?php echo $srv['icono']; ?>"></i>
      <strong><?php echo html_safe($srv['nombre']); ?></strong>
      <?php badge_estado($srv['activo']); ?>
    </div>
    <div class="service-card-body">
      <p><?php echo html_safe($srv['desc']); ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- FAIL2BAN ACTIVE JAILS -->
<?php if (!empty($jaulas)): ?>
<div class="section-title">
  <h2><i class="fa-solid fa-shield-halved"></i> Fail2Ban — Active jails</h2>
</div>

<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>Jail</th><th>Banned IPs</th><th>Failed attempts</th></tr>
    </thead>
    <tbody>
      <?php foreach ($jaulas as $jaula):
        if (empty($jaula)) continue;
        // Get statistics for each jail individually
        $status = shell_exec("fail2ban-client status {$jaula} 2>/dev/null");
        preg_match('/Currently banned:\s*(\d+)/', $status, $baneadas);
        preg_match('/Total failed:\s*(\d+)/', $status, $fallidos);
      ?>
      <tr>
        <td><strong><?php echo html_safe($jaula); ?></strong></td>
        <td><?php echo $baneadas[1] ?? '0'; ?></td>
        <td><?php echo $fallidos[1] ?? '0'; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php layout_footer(); ?>
