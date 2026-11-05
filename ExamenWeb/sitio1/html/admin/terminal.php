<?php
session_name("StarWarsCookie");
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}
// Admin Check
$username = $_SESSION['username'] ?? '';
if ($username !== 'admin') {
    header("Location: fail.php");
    exit();
}
$output = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip'])) {
    $ip = trim($_POST['ip']);

    // Lista blanca estricta: solo una IP (v4/v6) o un nombre de host válido.
    $es_ip   = filter_var($ip, FILTER_VALIDATE_IP) !== false;
    $es_host = (bool) preg_match(
        '/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',
        $ip
    );
    $blocked = !($es_ip || $es_host);

    if ($blocked) {
        $output = "╔══════════════════════════════════════════════════════╗\n";
        $output .= "║  ⚠  ALERTA DE SEGURIDAD IMPERIAL - NIVEL MÁXIMO  ⚠  ║\n";
        $output .= "╠══════════════════════════════════════════════════════╣\n";
        $output .= "║                                                      ║\n";
        $output .= "║   Destino no válido. Solo se admite una dirección    ║\n";
        $output .= "║   IP o un nombre de host.                            ║\n";
        $output .= "║                                                      ║\n";
        $output .= "╚══════════════════════════════════════════════════════╝";
    } else {
        // Argumento escapado y sin shell intermedia para la entrada del usuario
        $param = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? '-n' : '-c';
        $output = shell_exec("ping $param 1 " . escapeshellarg($ip));
    }

    if (empty($output)) {
        $output = "Sin respuesta del objetivo. La nave puede haber saltado al hiperespacio.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⭐</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrella de la Muerte - Escáner de Naves Rebeldes</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="terminal-container">
        <div class="terminal-header">
            <h1>📡 Escáner de Naves Rebeldes</h1>
            <a href="./" class="btn-logout">← Volver a Admin</a>
        </div>

        <div class="terminal-box">
            <div class="terminal-titlebar">
                <div class="terminal-dot red"></div>
                <div class="terminal-dot amber"></div>
                <div class="terminal-dot green"></div>
                <span>deathstar-scanner v1.3 — /admin/terminal.php</span>
            </div>

            <div class="terminal-body">
                <div class="terminal-info">
                    Introduce la dirección IP de una nave rebelde sospechosa para escanear sus señales de comunicación.
                    Esta herramienta envía un ping para verificar si el objetivo es alcanzable.
                </div>

                <form method="POST" action="">
                    <div class="terminal-input-row">
                        <input type="text" name="ip" placeholder="Introduce la IP del objetivo (ej: 192.168.1.1)" 
                               value="<?php echo isset($_POST['ip']) ? htmlspecialchars($_POST['ip']) : ''; ?>">
                        <button type="submit" class="btn-scan">⚡ Escanear</button>
                    </div>
                </form>

                <?php if ($output): ?>
                    <div class="terminal-output">
                        <pre><span class="prompt">deathstar@scanner:~$</span> ping -c 1 <?php echo htmlspecialchars($_POST['ip'] ?? ''); ?>

<?php echo htmlspecialchars($output); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            Imperio Galáctico &bull; Sistema de Detección Rebelde &bull; Clasificado
        </div>
    </div>
</body>
</html>
