<?php
session_name("StarTrekCookie");
session_start();
require_once __DIR__ . '/db.php';

$error = "";

// Lógica de Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie('admin_session', '', time() - 3600, '/'); // Borrar cookie persistente
    header("Location: login.php?msg=logged_out");
    exit();
}

// Si ya esta logueado, redirigir al admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin/");
    exit();
} elseif (isset($_COOKIE['admin_session'])) {
    // Validar cookie si existe
    $token = $_COOKIE['admin_session'];
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin/");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Login seguro - las credenciales se obtienen por otros medios (no SQLi aquí)
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Generar token de sesión y guardarlo en BD
        $token = bin2hex(random_bytes(32));
        $stmt2 = $conn->prepare("UPDATE admin_users SET session_token = ? WHERE id = ?");
        $stmt2->bind_param("si", $token, $row['id']);
        $stmt2->execute();

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $row['username'];
        setcookie('admin_session', $token, ['expires' => 0, 'path' => '/', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Strict']);
        header("Location: admin/");
        exit();
    } else {
        $error = "Credenciales inválidas. Acceso denegado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🖖</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USS Enterprise - Acceso Oficial Starfleet</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 420px;
            margin: 4rem auto;
            padding: 2rem;
            background: var(--lcars-bg-panel);
            border: 1px solid var(--lcars-orange);
            border-radius: 8px;
        }

        .login-container h2 {
            color: var(--lcars-orange);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: var(--lcars-text-dim);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            background: #0a0a1a;
            border: 1px solid #333;
            color: var(--lcars-text);
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--lcars-orange);
        }

        .btn-login {
            width: 100%;
            padding: 0.7rem;
            background: var(--lcars-orange);
            color: #000;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            opacity: 0.85;
        }

        .error-box {
            background: rgba(255, 50, 50, 0.15);
            border: 1px solid #ff3232;
            color: #ff6666;
            padding: 0.7rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="lcars-bar-top">
        <div class="lcars-elbow-top">NCC-1701</div>
        <div class="lcars-strip-top">
            <div class="block"></div>
            <div class="block"></div>
            <div class="block"></div>
        </div>
    </div>

    <div class="lcars-layout">
        <div class="lcars-sidebar">
            <div class="panel orange">ACCESO<br>OFICIAL</div>
            <div class="panel blue">STARFLEET</div>
        </div>
        <div class="lcars-content">
            <div class="page-header">
                <h1>USS Enterprise</h1>
                <p class="subtitle">Acceso Restringido — Solo Personal Autorizado de Starfleet</p>
            </div>

            <div class="login-container">
                <h2>🖖 Autenticación Starfleet</h2>
                <?php if ($error): ?>
                    <div class="error-box">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>ID de Oficial</label>
                        <input type="text" name="username" placeholder="Introduce tu ID de oficial" required>
                    </div>
                    <div class="form-group">
                        <label>Código de Acceso</label>
                        <input type="password" name="password" placeholder="Código de acceso clasificado" required>
                    </div>
                    <button type="submit" class="btn-login">⚡ Autenticar</button>
                </form>
            </div>

            <div style="text-align:center; margin-top:1rem;">
                <a href="index.php" style="color: var(--lcars-text-dim); font-size:0.85rem;">← Volver al Puente</a>
            </div>
        </div>
    </div>

    <div class="lcars-bar-bottom">
        <div class="lcars-elbow-bottom">STARFLEET</div>
        <div class="lcars-strip-bottom">
            <div class="block"></div>
            <div class="block"></div>
            <div class="block"></div>
        </div>
    </div>
</body>

</html>