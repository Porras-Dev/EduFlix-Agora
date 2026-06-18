<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: metricas.php
 *  Description: Detailed real-time system metrics:
 *               CPU, RAM, disk and server load average.
 * ============================================================
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion();
requerir_login();

$cpu        = obtener_cpu();
$ram        = obtener_ram();
$disco      = obtener_disco('/mnt/datos');
$disco_root = obtener_disco('/');
$carga      = obtener_carga();
$uptime     = obtener_uptime();

layout_head('Metrics');
layout_nav('metricas');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-chart-line"></i> System Metrics</h1>
    <p>Real-time resource usage of the servidor-principal container</p>
  </div>
  <button onclick="location.reload()" class="btn-refresh">
    <i class="fa-solid fa-arrows-rotate"></i> Refresh
  </button>
</div>

<!-- Main KPIs -->
<div class="cards-grid">
  <?php
  card_metrica('CPU',         $cpu . '%',               'fa-microchip',  clase_uso($cpu),              "Load avg 1min: {$carga['load1']}");
  card_metrica('RAM used',    $ram['usado_mb'] . ' MB', 'fa-memory',     clase_uso($ram['porcentaje']), "{$ram['porcentaje']}% of {$ram['total_mb']} MB");
  card_metrica('Disk /datos', $disco['usado_gb'] . ' GB','fa-hard-drive', clase_uso($disco['porcentaje']), "Free: {$disco['libre_gb']} GB");
  card_metrica('Uptime',      $uptime,                  'fa-clock',      'info',                       'System uptime');
  ?>
</div>

<!-- Detailed usage bars -->
<div class="table-card" style="padding: 1.5rem 2rem;">
  <h3 style="margin-bottom:1.5rem; color: var(--azul);">
    <i class="fa-solid fa-chart-bar"></i> Usage detail
  </h3>

  <div class="resource-row" style="margin-bottom:1.2rem;">
    <span class="resource-label" style="min-width:160px;">CPU (current)</span>
    <?php barra_progreso($cpu, clase_uso($cpu)); ?>
  </div>
  <div class="resource-row" style="margin-bottom:1.2rem;">
    <span class="resource-label" style="min-width:160px;">RAM</span>
    <?php barra_progreso($ram['porcentaje'], clase_uso($ram['porcentaje'])); ?>
  </div>
  <div class="resource-row" style="margin-bottom:1.2rem;">
    <span class="resource-label" style="min-width:160px;">Disk /datos (HDD 1TB)</span>
    <?php barra_progreso($disco['porcentaje'], clase_uso($disco['porcentaje'])); ?>
  </div>
  <div class="resource-row">
    <span class="resource-label" style="min-width:160px;">Disk / (SSD system)</span>
    <?php barra_progreso($disco_root['porcentaje'], clase_uso($disco_root['porcentaje'])); ?>
  </div>
</div>

<!-- System load average table -->
<div class="section-title">
  <h2><i class="fa-solid fa-wave-square"></i> System Load Average</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>Period</th><th>Value</th><th>Status</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>Last 1 minute</td>
        <td><strong><?php echo $carga['load1']; ?></strong></td>
        <td><?php badge_estado($carga['load1'] < $carga['nucleos'], $carga['load1'] < $carga['nucleos'] ? 'Normal' : 'Overloaded'); ?></td>
      </tr>
      <tr>
        <td>Last 5 minutes</td>
        <td><strong><?php echo $carga['load5']; ?></strong></td>
        <td><?php badge_estado($carga['load5'] < $carga['nucleos'], $carga['load5'] < $carga['nucleos'] ? 'Normal' : 'Overloaded'); ?></td>
      </tr>
      <tr>
        <td>Last 15 minutes</td>
        <td><strong><?php echo $carga['load15']; ?></strong></td>
        <td><?php badge_estado($carga['load15'] < $carga['nucleos'], $carga['load15'] < $carga['nucleos'] ? 'Normal' : 'Overloaded'); ?></td>
      </tr>
      <tr>
        <td>Available cores</td>
        <td><strong><?php echo $carga['nucleos']; ?> cores</strong></td>
        <td><span class="badge badge-online">● Available</span></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- RAM detail table -->
<div class="section-title">
  <h2><i class="fa-solid fa-memory"></i> RAM Memory Detail</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>Metric</th><th>Value</th></tr>
    </thead>
    <tbody>
      <tr><td>Total RAM</td><td><strong><?php echo $ram['total_mb']; ?> MB</strong></td></tr>
      <tr><td>Used RAM</td><td><strong><?php echo $ram['usado_mb']; ?> MB (<?php echo $ram['porcentaje']; ?>%)</strong></td></tr>
      <tr><td>Free / Available RAM</td><td><strong><?php echo $ram['libre_mb']; ?> MB</strong></td></tr>
    </tbody>
  </table>
</div>

<?php layout_footer(); ?>
