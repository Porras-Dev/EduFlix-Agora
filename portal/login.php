<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/funciones.php';
iniciar_sesion();
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: http://'.$_SERVER['HTTP_HOST'].'/index.php');
    exit;
}
$mensaje_error = '';
if (isset($_GET['motivo']) && $_GET['motivo'] === 'timeout') {
    $mensaje_error = 'Session expired. Please log in again.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($usuario) || empty($password)) {
        $mensaje_error = 'Please enter your username and password.';
    } else {
        $resultado = intentar_login($usuario, $password);
        if ($resultado['exito']) {
            header('Location: http://'.$_SERVER['HTTP_HOST'].'/index.php');
            exit;
        } else {
            $mensaje_error = $resultado['mensaje'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduFlix Agora - Login</title>
  <link rel="stylesheet" href="/assets/css/portal.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
<div class="login-container">
  <div class="login-logo">
    <img src="/assets/logo-agora.png" alt="IES Agora" style="width:90px;height:90px;object-fit:contain;margin-bottom:.5rem;">
    <h1>EduFlix <span>Agora</span></h1>
    <p>Administration Panel</p>
  </div>
  <?php if (!empty($mensaje_error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
  <?php endif; ?>
  <form class="login-form" action="" method="post">
    <div class="form-group">
      <label for="usuario"><i class="fa-solid fa-user"></i> Username</label>
      <input type="text" id="usuario" name="usuario" placeholder="Username" required autofocus>
    </div>
    <div class="form-group">
      <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
      <input type="password" id="password" name="password" placeholder="Password" required>
    </div>
    <button type="submit" class="btn-login">
      <i class="fa-solid fa-right-to-bracket"></i> Sign in
    </button>
  </form>
  <div class="login-footer">
    <small>IES Agora - Caceres - 2025/2026</small>
  </div>
</div>
<script src="/assets/js/portal.js"></script>
</body>
</html>
