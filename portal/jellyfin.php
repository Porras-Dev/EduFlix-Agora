<?php
/**
 * ============================================================
 *  EduFlix Agora - Administration Portal
 *  File: jellyfin.php
 *  Description: Jellyfin statistics obtained via its REST API.
 *               Shows users, active sessions and educational
 *               content library. Includes user management
 *               via API and bidirectional sync with PostgreSQL.
 * ============================================================
 */

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/funciones.php';

iniciar_sesion();
requerir_login();

// Process user management actions
$mensaje_accion = '';
$tipo_mensaje   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Create user
    if (isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol      = trim($_POST['rol']      ?? 'alumno');

        // Validate that the role is one of the allowed values
        if (!in_array($rol, ['admin', 'profesor', 'alumno'])) {
            $rol = 'alumno';
        }

        $es_admin = ($rol === 'admin');

        if (!empty($nombre) && !empty($password)) {
            $datos = [
                'Name'     => $nombre,
                'Password' => $password,
            ];
            $resultado = jellyfin_api('/Users/New', 'POST', $datos);
            if (isset($resultado['Id'])) {
                // If admin role, update the user policy
                if ($es_admin) {
                    $politica = ['IsAdministrator' => true, 'IsDisabled' => false];
                    jellyfin_api('/Users/' . $resultado['Id'] . '/Policy', 'POST', $politica);
                }
                // Sync with PostgreSQL
                $pdo = conectar_bd();
                if ($pdo) {
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, rol, fecha_registro) VALUES (?, ?, CURRENT_DATE) ON CONFLICT DO NOTHING");
                    $stmt->execute([$nombre, $rol]);
                }
                $mensaje_accion = 'User ' . html_safe($nombre) . ' created successfully as ' . html_safe($rol) . '.';
                $tipo_mensaje   = 'success';
            } else {
                $mensaje_accion = 'Error creating user. Check that the username does not already exist.';
                $tipo_mensaje   = 'error';
            }
        } else {
            $mensaje_accion = 'Username and password are required.';
            $tipo_mensaje   = 'error';
        }
    }

    // Delete user
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
        $user_id = trim($_POST['user_id'] ?? '');
        if (!empty($user_id)) {
            // Get the username before deleting to sync with PostgreSQL
            $usuarios_actuales = jellyfin_api('/Users');
            $nombre_a_eliminar = '';
            if (is_array($usuarios_actuales)) {
                foreach ($usuarios_actuales as $u) {
                    if ($u['Id'] === $user_id) {
                        $nombre_a_eliminar = $u['Name'];
                        break;
                    }
                }
            }
            $resultado = jellyfin_api('/Users/' . $user_id, 'DELETE');
            // Sync with PostgreSQL
            if ($nombre_a_eliminar) {
                $pdo = conectar_bd();
                if ($pdo) {
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE nombre = ?");
                    $stmt->execute([$nombre_a_eliminar]);
                }
            }
            $mensaje_accion = 'User deleted successfully.';
            $tipo_mensaje   = 'success';
        }
    }
}

// Get all data from the Jellyfin API
$stats    = obtener_stats_jellyfin();
$usuarios = jellyfin_api('/Users');
$sesiones = jellyfin_api('/Sessions');

layout_head('Jellyfin');
layout_nav('jellyfin');
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-film"></i> Jellyfin</h1>
    <p>Statistics and status of the educational media server</p>
  </div>
  <div style="display:flex; gap:.8rem; align-items:center;">
    <?php badge_estado($stats['online']); ?>
    <a href="<?php echo JELLYFIN_URL; ?>" target="_blank" class="btn-refresh">
      <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Jellyfin
    </a>
  </div>
</div>

<!-- Jellyfin KPIs -->
<div class="cards-grid">
  <?php
  card_metrica('Users',           $stats['usuarios'],         'fa-users',       'info', 'Registered accounts');
  card_metrica('Active sessions', $stats['sesiones_activas'], 'fa-circle-dot',  'info', 'Current connections');
  card_metrica('Movies',          $stats['peliculas'],        'fa-film',        'info', 'In library');
  card_metrica('Episodes',        $stats['episodios'],        'fa-clapperboard','info', 'Series and episodes');
  ?>
</div>

<!-- Server info -->
<div class="section-title">
  <h2><i class="fa-solid fa-circle-info"></i> Server information</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead><tr><th>Parameter</th><th>Value</th></tr></thead>
    <tbody>
      <tr><td>Server name</td><td><strong><?php echo html_safe($stats['servidor']); ?></strong></td></tr>
      <tr><td>Jellyfin version</td><td><strong><?php echo html_safe($stats['version']); ?></strong></td></tr>
      <tr><td>Internal URL</td><td><a href="<?php echo JELLYFIN_URL; ?>" target="_blank"><?php echo JELLYFIN_URL; ?></a></td></tr>
      <tr><td>External URL (DNS)</td><td><a href="https://eduflix.agora.local" target="_blank">https://eduflix.agora.local</a></td></tr>
      <tr><td>Container status</td><td><?php badge_estado($stats['online']); ?></td></tr>
    </tbody>
  </table>
</div>

<!-- User list -->
<?php if (is_array($usuarios) && !empty($usuarios)): ?>
<div class="section-title">
  <h2><i class="fa-solid fa-users"></i> Registered users</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>User</th><th>Role</th><th>Last activity</th><th>Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><strong><?php echo html_safe($u['Name'] ?? '—'); ?></strong></td>
        <td>
          <?php if ($u['Policy']['IsAdministrator'] ?? false): ?>
            <span class="badge badge-warning">● Admin</span>
          <?php else: ?>
            <span class="badge">● User</span>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $ultima = $u['LastActivityDate'] ?? null;
          echo $ultima ? date('d/m/Y H:i', strtotime($ultima)) : '—';
          ?>
        </td>
        <td>
          <?php
          $deshabilitado = $u['Policy']['IsDisabled'] ?? false;
          badge_estado(!$deshabilitado, $deshabilitado ? 'Disabled' : 'Active');
          ?>
        </td>
        <td>
          <?php if (!($u['Policy']['IsAdministrator'] ?? false)): ?>
          <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');">
            <input type="hidden" name="accion"  value="eliminar">
            <input type="hidden" name="user_id" value="<?php echo html_safe($u['Id']); ?>">
            <button type="submit" class="btn-danger-small">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </form>
          <?php else: ?>
            <span style="color:var(--gris-texto); font-size:.85rem;">Protected</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Active sessions -->
<?php if (is_array($sesiones) && !empty($sesiones)): ?>
<div class="section-title">
  <h2><i class="fa-solid fa-circle-dot"></i> Currently active sessions</h2>
</div>
<div class="table-card">
  <table class="data-table">
    <thead>
      <tr><th>User</th><th>Device</th><th>Client</th><th>IP</th><th>Now playing</th></tr>
    </thead>
    <tbody>
      <?php foreach ($sesiones as $s): ?>
      <tr>
        <td><strong><?php echo html_safe($s['UserName'] ?? '—'); ?></strong></td>
        <td><?php echo html_safe($s['DeviceName'] ?? '—'); ?></td>
        <td><?php echo html_safe($s['Client'] ?? '—'); ?></td>
        <td><code><?php echo html_safe($s['RemoteEndPoint'] ?? '—'); ?></code></td>
        <td>
          <?php
          $nowPlaying = $s['NowPlayingItem']['Name'] ?? null;
          echo $nowPlaying
              ? '<span class="badge badge-online">&#9654; ' . html_safe($nowPlaying) . '</span>'
              : '—';
          ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- User management -->
<div class="section-title">
  <h2><i class="fa-solid fa-user-gear"></i> User management</h2>
</div>

<?php if (!empty($mensaje_accion)): ?>
<div class="alert alert-<?php echo $tipo_mensaje; ?>" style="margin-bottom:1rem;">
  <i class="fa-solid fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'triangle-exclamation'; ?>"></i>
  <?php echo $mensaje_accion; ?>
</div>
<?php endif; ?>

<div class="table-card">
  <h3 style="margin-bottom:1rem; color:var(--azul);">
    <i class="fa-solid fa-user-plus"></i> Create new user
  </h3>
  <form method="post" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
    <input type="hidden" name="accion" value="crear">
    <div class="form-group" style="flex:1; min-width:180px;">
      <label>Username</label>
      <input type="text" name="nombre" placeholder="Username" required
             style="width:100%; padding:.5rem; border:1px solid var(--gris-borde); border-radius:6px;">
    </div>
    <div class="form-group" style="flex:1; min-width:180px;">
      <label>Password</label>
      <input type="password" name="password" placeholder="Password" required
             style="width:100%; padding:.5rem; border:1px solid var(--gris-borde); border-radius:6px;">
    </div>
    <div class="form-group" style="flex:1; min-width:150px;">
      <label>Role</label>
      <select name="rol" style="width:100%; padding:.5rem; border:1px solid var(--gris-borde); border-radius:6px;">
        <option value="alumno">Student</option>
        <option value="profesor">Teacher</option>
        <option value="admin">Administrator</option>
      </select>
    </div>
    <button type="submit" class="btn-refresh">
      <i class="fa-solid fa-user-plus"></i> Create user
    </button>
  </form>
</div>

<?php layout_footer(); ?>
