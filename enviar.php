<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/mailer/Exception.php';
require __DIR__ . '/mailer/PHPMailer.php';
require __DIR__ . '/mailer/SMTP.php';

/* =========================
   Configuración básica
   ========================= */
const SMTP_HOST = 'c2760078.ferozo.com';        // DonWeb SMTP
const SMTP_PORT = 465;                           // SSL
const SMTP_USER = 'TU_CORREO@tudominio.com';     // <-- Cambiar
const SMTP_PASS = 'TU_PASSWORD_SMTP_SEGURA';     // <-- Cambiar (mejor usar .env)
const FROM_EMAIL = 'invitaciones@tudominio.com'; // Remitente mostrado
const FROM_NAME  = 'Tarjeta de Invitación';
const FALLBACK_CLIENT_EMAIL = 'cliente@tudominio.com'; // Destinatario si no viene por POST

// Helper seguro para escapar HTML
function e($str)
{
    return htmlspecialchars((string)$str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Acceso denegado.';
    exit;
}

/* =========================
   Recolectar y sanitizar
   ========================= */
$names   = isset($_POST['nombre'])  && is_array($_POST['nombre'])  ? array_map('trim', $_POST['nombre'])  : [];
$surns   = isset($_POST['apellido']) && is_array($_POST['apellido']) ? array_map('trim', $_POST['apellido']) : [];
$menus   = isset($_POST['menu'])    && is_array($_POST['menu'])    ? array_map('trim', $_POST['menu'])    : [];

$regalo  = trim($_POST['regalo']  ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

$emailCliente   = filter_var($_POST['email_cliente']   ?? FALLBACK_CLIENT_EMAIL, FILTER_VALIDATE_EMAIL) ?: FALLBACK_CLIENT_EMAIL;
$emailRemitente = filter_var($_POST['email_remitente'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;

// Limitar a 5 invitados
$maxInvitados = 5;
$names = array_slice($names, 0, $maxInvitados);
$surns = array_slice($surns, 0, $maxInvitados);
$menus = array_slice($menus, 0, $maxInvitados);

// Empaquetar invitados válidos (nombre o apellido no vacíos)
$invitados = [];
for ($i = 0; $i < max(count($names), count($surns), count($menus)); $i++) {
    $n = $names[$i] ?? '';
    $a = $surns[$i] ?? '';
    $m = $menus[$i] ?? '';
    if ($n !== '' || $a !== '') {
        $invitados[] = ['nombre' => $n, 'apellido' => $a, 'menu' => $m];
    }
}

/* =========================
   Tarjetita HTML (Times NR)
   Paleta:
   bg: #F8EDDB | primario: #F1772D
   acento: #A882BF | secundario: #19734F
   ========================= */
$fecha = date('d/m/Y H:i');
$ip    = $_SERVER['REMOTE_ADDR'] ?? '';

$guestBlocks = '';
if (count($invitados) > 0) {
    foreach ($invitados as $idx => $g) {
        $guestBlocks .= '
      <div class="guest">
        <div class="pill">Invitado ' . ($idx + 1) . '</div>
        <div class="row"><span class="label">Nombre</span><span class="value">' . e($g['nombre']) . '</span></div>
        <div class="row"><span class="label">Apellido</span><span class="value">' . e($g['apellido']) . '</span></div>
        <div class="row"><span class="label">Menú</span><span class="tag">' . e($g['menu']) . '</span></div>
      </div>
    ';
    }
} else {
    $guestBlocks = '
    <div class="guest">
      <div class="row"><span class="value">No se informó ningún invitado.</span></div>
    </div>
  ';
}

$giftBlock = $regalo !== '' ? '<div class="gift-box"><span class="gift-label">Regalo elegido</span><div class="gift">' . e($regalo) . '</div></div>' : '';

$messageBlock = $mensaje !== '' ? '
  <div class="message">
    <div class="message-title">Mensaje para los novios</div>
    <blockquote>' . nl2br(e($mensaje)) . '</blockquote>
  </div>' : '';

$mensajeHTML = '
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Confirmación recibida</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  /* Tipografía */
  body, table, div, p, span {
    font-family: "Times New Roman", Times, serif;
  }
  body {
    margin:0; padding:0; background:#F8EDDB;
  }
  .container {
    max-width:640px; margin:24px auto; background:#ffffff; 
    border-radius:16px; overflow:hidden; 
    box-shadow:0 6px 24px rgba(30,25,56,.12);
    border:1px solid #F3E8D8;
  }
  .header {
    background:#F1772D; color:#fff; padding:20px 24px; text-align:center;
  }
  .header h1 {
    margin:0; font-size:28px; line-height:1.2; letter-spacing:.2px;
  }
  .subheader {
    background:#F8EDDB; color:#1e1938; padding:8px 24px; text-align:center; font-size:14px;
  }
  .content {
    padding:24px;
    color:#1e1938;
  }
  .section-title {
    font-size:18px; margin:0 0 12px; color:#19734F;
    border-left:6px solid #19734F; padding-left:10px;
  }
  .guest {
    background:#eef8fc; /* suave */
    border:1px solid #d9eef8; 
    border-radius:12px;
    padding:14px; margin:10px 0;
  }
  .pill {
    display:inline-block; font-size:12px; padding:4px 10px; border-radius:999px;
    background:#A882BF; color:#fff; margin-bottom:10px;
  }
  .row {
    display:flex; gap:8px; align-items:baseline; margin:6px 0;
  }
  .label {
    min-width:90px; font-weight:bold; color:#1e1938;
  }
  .value {
    flex:1;
  }
  .tag {
    display:inline-block; padding:3px 8px; border-radius:8px; border:1px solid #A882BF; color:#A882BF;
    font-size:13px;
    background:#fbf7ff;
  }
  .gift-box {
    border:1px solid #A882BF; border-radius:12px; padding:14px; margin:18px 0; background:#fbf7ff;
  }
  .gift-label {
    display:inline-block; font-size:12px; color:#A882BF; border:1px dashed #A882BF; padding:2px 8px; border-radius:999px; margin-bottom:8px;
  }
  .gift {
    font-size:16px; font-weight:bold; color:#1e1938; margin-top:6px;
  }
  .message {
    margin:18px 0; padding:0;
  }
  .message-title {
    font-size:16px; color:#19734F; font-weight:bold; margin-bottom:8px;
  }
  blockquote {
    margin:0; padding:14px 16px; border-left:6px solid #F1772D; background:#FFF7F1; border-radius:8px;
  }
  .meta {
    margin-top:18px; font-size:12px; color:#555;
  }
  .footer {
    background:#F8EDDB; color:#1e1938; padding:16px 24px; text-align:center; font-size:13px;
    border-top:1px solid #F3E8D8;
  }
  .badge {
    display:inline-block; background:#19734F; color:#fff; padding:4px 10px; border-radius:999px; font-size:12px;
  }
  @media (prefers-color-scheme: dark) {
    .container { background:#1f1b2f; border-color:#2b244d; }
    .content, .footer, .subheader, .label, .value { color:#f4f4f4; }
    .guest { background:#25203a; border-color:#3a315e; }
    .tag { background:#2b244d; color:#e8daf5; border-color:#A882BF; }
    blockquote { background:#392b24; color:#fff; }
  }
</style>
</head>
<body>
  <div class="container">
    <div class="header"><h1>Confirmación recibida</h1></div>
    <div class="subheader">
      <span class="badge">¡Gracias por confirmar!</span>
    </div>
    <div class="content">
      <h2 class="section-title">Detalle de invitados</h2>
      ' . $guestBlocks . '
      ' . ($giftBlock ? '<h2 class="section-title">Regalo</h2>' . $giftBlock : '') . '
      ' . ($messageBlock ? '<h2 class="section-title">Cartita</h2>' . $messageBlock : '') . '
      <div class="meta">
        Enviado el ' . $fecha . ' · IP: ' . e($ip) . '
      </div>
    </div>
    <div class="footer">
      Nos vemos en la fiesta ✨
    </div>
  </div>
</body>
</html>
';

// Versión de texto plano (fallback)
$altBody = "Confirmación recibida\n\n";
if (count($invitados) > 0) {
    foreach ($invitados as $i => $g) {
        $altBody .= "Invitado " . ($i + 1) . ": " . ($g['nombre'] ?: '-') . " " . ($g['apellido'] ?: '-') . " | Menú: " . ($g['menu'] ?: '-') . "\n";
    }
}
if ($regalo !== '') {
    $altBody .= "\nRegalo elegido: " . $regalo . "\n";
}
if ($mensaje !== '') {
    $altBody .= "\nMensaje para los novios:\n" . $mensaje . "\n";
}
$altBody .= "\nEnviado el $fecha\n";

/* =========================
   Envío con PHPMailer
   ========================= */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress($emailCliente, 'Cliente');

    // Si el usuario dejó su email, lo ponemos como Reply-To:
    if ($emailRemitente) {
        $mail->addReplyTo($emailRemitente);
    }

    $mail->isHTML(true);
    $mail->Subject = 'Confirmación de asistencia';
    $mail->Body    = $mensajeHTML;
    $mail->AltBody = $altBody;

    if ($mail->send()) {
        // Redirigí a una página de “gracias”
        // header('Location: confirmacion.html'); exit;
        echo 'OK';
    } else {
        http_response_code(500);
        echo "Error al enviar el mensaje: " . $mail->ErrorInfo;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al enviar el mensaje: " . $mail->ErrorInfo;
}
