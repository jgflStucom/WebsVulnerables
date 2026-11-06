<?php
// Panel de admin - Subida de ficheros con autenticación por sesión
// VULNERABLE: Filtro de extensión débil (solo comprueba la última extensión)

session_name("lotrCookie");
session_start();

// Comprobar sesión
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <link rel="icon"
            href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>👁️</text></svg>">
        <title>Barad-dûr - Acceso Denegado</title>
        <link rel="stylesheet" href="../style.css">
    </head>

    <body>
        <div class="main-container">
            <div class="page-header">
                <div class="eye-of-sauron"></div>
                <h1>Acceso Denegado</h1>
                <p class="subtitle">El Ojo de Sauron no te reconoce</p>
            </div>
            <div class="content-box">
                <div class="error-message">
                    No posees la autoridad necesaria para acceder a esta área.
                    Solo aquellos con la bendición de Sauron pueden entrar.
                </div>
                <p style="text-align: center; margin-top: 1rem;"><a href="../login.php" class="btn-back">Ir al Portal de
                        Sauron</a></p>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit();
}

$error = "";
$success = "";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['map'])) {
    $filename = $_FILES['map']['name'];
    $tmp = $_FILES['map']['tmp_name'];
    $upload_dir = __DIR__ . '/uploads/';

    // Lista blanca de extensión + comprobación del contenido real + tamaño máximo
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $valido = $_FILES['map']['error'] === UPLOAD_ERR_OK
        && is_uploaded_file($tmp)
        && in_array($ext, ['jpg', 'jpeg'], true)
        && $_FILES['map']['size'] <= 2 * 1024 * 1024;
    if ($valido) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $valido = $finfo->file($tmp) === 'image/jpeg' && @getimagesize($tmp) !== false;
    }

    if (!$valido) {
        $error = "¡Solo se permiten imágenes de mapas (.jpg) de hasta 2 MB! ¡El Señor Oscuro lo exige!";
    } else {
        // Nombre generado por el servidor: el nombre del cliente nunca se usa
        $nombre_seguro = bin2hex(random_bytes(16)) . '.jpg';
        if (move_uploaded_file($tmp, $upload_dir . $nombre_seguro)) {
            $success = "Mapa subido correctamente: <a href='uploads/" . htmlspecialchars($nombre_seguro) . "' style='color: var(--lotr-gold);'>Ver Mapa</a>";
        } else {
            $error = "Error al subir. Los fuegos del Monte del Destino rechazan tu ofrenda.";
        }
    }
}

// List uploaded files
$uploads = glob(__DIR__ . '/uploads/*');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>👁️</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barad-dûr - Subida de Mapas</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <div class="main-container">
        <div class="admin-nav">
            <h1>🗺 Panel de Subida de Mapas</h1>
            <div>
                <a href="../logout.php" class="btn-back"
                    style="border-color: var(--lotr-red); color: var(--lotr-fire); margin-right: 0.5rem;">🚪 Cerrar
                    Sesión</a>
                <a href="../" class="btn-back">← Volver a la Torre</a>
            </div>
        </div>

        <div class="content-box">
            <h2>Subir Mapa de Batalla</h2>
            <p style="margin-bottom: 1rem; color: var(--lotr-text);">Sube nuevos mapas de la Tierra Media para
                planificación estratégica. Solo se aceptan ficheros .jpg.</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area">
                    <p style="color: var(--lotr-gold); font-family: 'Cinzel', serif; font-size: 1.1rem;">📤 Seleccionar
                        Mapa</p>
                    <p style="color: var(--lotr-stone-light); font-size: 0.85rem; margin-top: 0.5rem;">Formato aceptado:
                        .jpg</p>
                    <input type="file" name="map" accept=".jpg,.jpeg"
                        style="margin-top: 1rem; color: var(--lotr-text);">
                </div>
                <button type="submit" class="btn-primary">⚔ Subir Mapa</button>
            </form>
        </div>

        <?php if (!empty($uploads)): ?>
            <div class="content-box">
                <h2>📁 Mapas Subidos</h2>
                <ul class="scroll-list">
                    <?php foreach ($uploads as $file): ?>
                        <li><a href="uploads/<?php echo htmlspecialchars(basename($file)); ?>">
                                <?php echo htmlspecialchars(basename($file)); ?>
                            </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="footer">
            Un Anillo para gobernarlos a todos &bull; Mando Estratégico &bull; Clasificado
        </div>
    </div>
</body>

</html>