<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: includes/layout.php
 *  Description: Reusable HTML template. Defines the common
 *               structure for all pages: header, navigation
 *               sidebar and footer. All other pages include
 *               it with require_once and only add their content.
 * ============================================================
 */

require_once __DIR__ . '/auth.php';

/**
 * Generates the full HTML <head> for the page.
 *
 * @param string $titulo  Browser tab title
 */
function layout_head($titulo = 'Administration Panel') {
    $titulo_safe = html_safe($titulo);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>EduFlix Agora — {$titulo_safe}</title>
  <link rel="stylesheet" href="/assets/css/portal.css">
  <script>
  (function(){
    var s=localStorage.getItem("eduflix-theme");
    var d=window.matchMedia("(prefers-color-scheme: dark)").matches;
    if(s?s==="dark":s==="light"?false:d) document.documentElement.classList.add("dark-mode");
  })();
  function toggleTheme(){
    document.documentElement.classList.toggle("dark-mode");
    var dark=document.documentElement.classList.contains("dark-mode");
    localStorage.setItem("eduflix-theme",dark?"dark":"light");
  }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
HTML;
}

/**
 * Generates the sidebar navigation bar.
 * Marks the current page link as active.
 *
 * @param string $pagina_actual  Identifier of the current page
 */
function layout_nav($pagina_actual = '') {
    $usuario       = html_safe($_SESSION['usuario'] ?? 'admin');
    $tiempo_sesion = tiempo_sesion();

    // Navigation menu items
    $menu = [
        ['id' => 'dashboard',  'url' => '/index.php',     'icono' => 'fa-gauge',        'label' => 'Dashboard'],
        ['id' => 'servicios',  'url' => '/servicios.php', 'icono' => 'fa-server',       'label' => 'Services'],
        ['id' => 'metricas',   'url' => '/metricas.php',  'icono' => 'fa-chart-line',   'label' => 'Metrics'],
        ['id' => 'jellyfin',   'url' => '/jellyfin.php',  'icono' => 'fa-film',         'label' => 'Jellyfin'],
        ['id' => 'base-datos', 'url' => '/basedatos.php', 'icono' => 'fa-database',     'label' => 'Database'],
        ['id' => 'backups',    'url' => '/backups.php',   'icono' => 'fa-box-archive',  'label' => 'Backups'],
        ['id' => 'logs',       'url' => '/logs.php',      'icono' => 'fa-scroll',       'label' => 'Logs'],
    ];

    echo '<div class="layout">';
    echo '<nav class="sidebar">';

    // Sidebar header / logo
    echo '<div class="sidebar-logo">';
    echo '<img src="/assets/logo-agora.png" alt="IES Agora" style="width:45px;height:45px;object-fit:contain;">';
    echo '<div class="logo-text"><strong>EduFlix</strong><span>Agora</span></div>';
    echo '</div>';

    // Navigation menu
    echo '<ul class="sidebar-menu">';
    foreach ($menu as $item) {
        $activo = ($pagina_actual === $item['id']) ? ' active' : '';
        echo "<li class=\"menu-item{$activo}\">";
        echo "<a href=\"{$item['url']}\">";
        echo "<i class=\"fa-solid {$item['icono']}\"></i>";
        echo "<span>{$item['label']}</span>";
        echo "</a></li>";
    }
    echo '</ul>';

    // User info at the bottom of the sidebar
    echo '<div class="sidebar-user">';
    echo "<div class=\"user-info\">";
    echo "<i class=\"fa-solid fa-circle-user\"></i>";
    echo "<div><strong>{$usuario}</strong><small>Session: {$tiempo_sesion}</small></div>";
    echo "</div>";
    echo '<a href="/logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Log out</a>';
    echo '</div>';

    echo '</nav>';
    echo '<main class="main-content">';
}

/**
 * Generates the layout closing: </main>, </div> and </body></html>.
 * Also loads the portal JavaScript.
 */
function layout_footer() {
    $hora = date('d/m/Y H:i:s');
    echo <<<HTML
    </main>
  </div><!-- /.layout -->

  <footer class="portal-footer">
    <span>EduFlix Agora — Administration Panel v1.0</span>
    <span>IES Agora · Caceres · {$hora}</span>
  </footer>

  <script src="/assets/js/portal.js"></script>
</body>
</html>
HTML;
}

/**
 * Generates a metric card (KPI card) for the dashboard.
 *
 * @param string $titulo     Card title (e.g. "CPU")
 * @param string $valor      Main value (e.g. "42%")
 * @param string $icono      Font Awesome class (e.g. "fa-microchip")
 * @param string $clase      Color class: 'ok', 'warning', 'danger', 'info'
 * @param string $subtitulo  Secondary text below the value
 */
function card_metrica($titulo, $valor, $icono, $clase = 'ok', $subtitulo = '') {
    $titulo    = html_safe($titulo);
    $valor     = html_safe($valor);
    $subtitulo = html_safe($subtitulo);
    echo <<<HTML
<div class="card card-metrica {$clase}">
  <div class="card-icon"><i class="fa-solid {$icono}"></i></div>
  <div class="card-body">
    <div class="card-titulo">{$titulo}</div>
    <div class="card-valor">{$valor}</div>
    <div class="card-sub">{$subtitulo}</div>
  </div>
</div>
HTML;
}

/**
 * Generates a progress bar to display usage percentages.
 *
 * @param float  $porcentaje  Usage percentage (0-100)
 * @param string $clase       Color class ('ok', 'warning', 'danger')
 */
function barra_progreso($porcentaje, $clase = 'ok') {
    $porcentaje = min(100, max(0, (float)$porcentaje));
    echo <<<HTML
<div class="progress-bar-wrap">
  <div class="progress-bar {$clase}" style="width: {$porcentaje}%"></div>
</div>
<span class="progress-label">{$porcentaje}%</span>
HTML;
}

/**
 * Generates a status badge (Online/Offline) with the appropriate color.
 *
 * @param bool   $activo  true = Online (green), false = Offline (red)
 * @param string $label   Optional alternative text
 */
function badge_estado($activo, $label = null) {
    $clase = $activo ? 'badge-online' : 'badge-offline';
    $texto = $label ?? ($activo ? 'Online' : 'Offline');
    $punto = '●';
    echo "<span class=\"badge {$clase}\">{$punto} {$texto}</span>";
}
