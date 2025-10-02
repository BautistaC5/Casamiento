<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/mailer/Exception.php';
require __DIR__ . '/mailer/PHPMailer.php';
require __DIR__ . '/mailer/SMTP.php';

/* =========================
   Configuración
   ========================= */
const SMTP_HOST = 'c2760078.ferozo.com';
const SMTP_PORT = 465; // SSL
const SMTP_USER = 'TU_CORREO@tudominio.com';     // <-- Cambiar
const SMTP_PASS = 'TU_PASSWORD_SMTP_SEGURA';     // <-- Cambiar
const FROM_EMAIL = 'invitaciones@tudominio.com'; // <-- Cambiar
const FROM_NAME  = 'Tarjeta de Invitación';
const RECIPIENT_EMAIL = 'cliente@tudominio.com'; // <-- Cambiar (destinatario)
const GOOGLE_APPS_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbycp2d-K2p1k5vu2B6jDuofCmlgkG9EP0PuhnhtwtpFKqo0xkfw5QoOgkdsv1EbMUMU/exec'; // <-- Cambiar

function e($str)
{
  return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Acceso denegado.';
  exit;
}

/* =========================
   Recolectar datos del form
   ========================= */
// Invitado único (tu form usa arrays pero hay 1 invitado)
$nombre   = isset($_POST['nombre'][0])   ? trim($_POST['nombre'][0])   : '';
$apellido = isset($_POST['apellido'][0]) ? trim($_POST['apellido'][0]) : '';
$menu     = isset($_POST['menu'][0])     ? trim($_POST['menu'][0])     : '';

$alergia     = isset($_POST['alergia'][0]) ? trim($_POST['alergia'][0]) : '';
$mayorRaw    = $_POST['mayor_edad']  ?? '';   // 'si' | 'no'
$telefono    = trim($_POST['phone']   ?? '');
$usaCombiRaw = $_POST['usa_combi']    ?? '';   // 'si' | 'no'
$barrio      = trim($_POST['combi_barrio'] ?? '');
$mensaje     = (string)($_POST['message'] ?? ''); // si viene null -> ''

// Reglas pedidas
$alergia = $alergia !== '' ? $alergia : 'N/A';
$combi   = ($usaCombiRaw === 'si') ? 'Sí' : 'N/A';     // si no usa combi -> N/A
$barrio  = ($usaCombiRaw === 'si' && $barrio !== '') ? $barrio : 'N/A';
$mayor   = ($mayorRaw === 'si') ? 'Sí' : (($mayorRaw === 'no') ? 'No' : ''); // sin regla especial

$ip        = $_SERVER['REMOTE_ADDR']      ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT']  ?? '';
$fecha     = date('d/m/Y H:i');

/* =========================
   Tarjeta HTML del mail
   ========================= */
$detalleInvitado = '
  <div style="background:#eef8fc;border:1px solid #d9eef8;border-radius:12px;padding:14px;margin:10px 0;font-family:\'Times New Roman\',Times,serif;color:#1e1938">
    <div style="display:inline-block;font-size:12px;padding:4px 10px;border-radius:999px;background:#A882BF;color:#fff;margin-bottom:10px">Invitado</div>
    <div style="margin:6px 0"><strong>Nombre:</strong> ' . e($nombre) . '</div>
    <div style="margin:6px 0"><strong>Apellido:</strong> ' . e($apellido) . '</div>
    <div style="margin:6px 0"><strong>Menú:</strong> <span style="display:inline-block;padding:3px 8px;border-radius:8px;border:1px solid #A882BF;color:#A882BF;background:#fbf7ff;font-size:13px">' . e($menu) . '</span></div>
    <div style="margin:6px 0"><strong>Alergia:</strong> ' . e($alergia) . '</div>
    <div style="margin:6px 0"><strong>Mayor de 18:</strong> ' . e($mayor) . '</div>
    <div style="margin:6px 0"><strong>Teléfono:</strong> ' . e($telefono) . '</div>
    <div style="margin:6px 0"><strong>Combi:</strong> ' . e($combi) . '</div>
    <div style="margin:6px 0"><strong>Barrio:</strong> ' . e($barrio) . '</div>
  </div>';

$mensajeBlock = ($mensaje !== '') ? '
  <div style="margin:18px 0">
    <div style="font-size:16px;color:#19734F;font-weight:bold;margin-bottom:8px">Mensaje para los novios</div>
    <blockquote style="margin:0;padding:14px 16px;border-left:6px solid #F1772D;background:#FFF7F1;border-radius:8px">' . nl2br(e($mensaje)) . '</blockquote>
  </div>' : '';

$bodyHTML = '
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirmación de asistencia</title></head>
<body style="margin:0;padding:0;background:#F8EDDB">
  <div style="max-width:640px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(30,25,56,.12);border:1px solid #F3E8D8">
    <div style="background:#F1772D;color:#fff;padding:20px 24px;text-align:center;font-family:\'Times New Roman\',Times,serif">
      <h1 style="margin:0;font-size:28px;line-height:1.2;letter-spacing:.2px">Confirmación recibida</h1>
    </div>
    <div style="background:#F8EDDB;color:#1e1938;padding:8px 24px;text-align:center;font-size:14px;font-family:\'Times New Roman\',Times,serif">
      ¡Gracias por confirmar!
    </div>
    <div style="padding:24px;color:#1e1938;font-family:\'Times New Roman\',Times,serif">
      <h2 style="font-size:18px;margin:0 0 12px;color:#19734F;border-left:6px solid #19734F;padding-left:10px">Detalle</h2>
      ' . $detalleInvitado . '
      ' . $mensajeBlock . '
      <div style="margin-top:18px;font-size:12px;color:#555">Enviado el ' . e($fecha) . ' · IP: ' . e($ip) . '</div>
    </div>
    <div style="background:#F8EDDB;color:#1e1938;padding:16px 24px;text-align:center;font-size:13px;border-top:1px solid #F3E8D8;font-family:\'Times New Roman\',Times,serif">
      Nos vemos en la fiesta ✨
    </div>
  </div>
</body></html>';

$altBody = "Confirmación recibida\n\n" .
  "Nombre: $nombre\nApellido: $apellido\nMenú: $menu\nAlergia: $alergia\nMayor de 18: $mayor\n" .
  "Teléfono: $telefono\nCombi: $combi\nBarrio: $barrio\n\n" .
  ($mensaje !== '' ? "Mensaje:\n$mensaje\n\n" : "") .
  "Enviado el $fecha\n";

/* =========================
   Enviar a Google Sheets
   ========================= */
$payload = [
  'nombre'   => $nombre,
  'apellido' => $apellido,
  'menu'     => $menu,
  'alergia'  => $alergia,              // "N/A" si estaba vacío
  'mayor'    => $mayor,                // "Sí" | "No" | ""
  'telefono' => $telefono,
  'combi'    => $combi,                // "Sí" si usa combi, si no: "N/A"
  'barrio'   => $barrio,               // "N/A" si no usa combi o vacío
  'mensaje'  => $mensaje               // "" si venía null
];

$curlOk = true;
$curlResp = null;
$curlCode = null;
if (filter_var(GOOGLE_APPS_SCRIPT_URL, FILTER_VALIDATE_URL)) {
  $ch = curl_init(GOOGLE_APPS_SCRIPT_URL);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $curlResp = curl_exec($ch);
  $curlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($curlResp === false || $curlCode >= 400) {
    $curlOk = false;
  }
  curl_close($ch);
}

/* =========================
   Enviar mail (PHPMailer)
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
  $mail->addAddress(RECIPIENT_EMAIL, 'Organizador');

  $mail->isHTML(true);
  $mail->Subject = 'Confirmación de asistencia';
  $mail->Body    = $bodyHTML;
  $mail->AltBody = $altBody;

  $sent = $mail->send();

  // Respuesta simple (podés cambiar a redirect si querés)
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'status' => ($sent ? 'ok' : 'mail_error'),
    'sheets' => ($curlOk ? 'ok' : 'sheets_error'),
    'sheets_http' => $curlCode,
    'sheets_resp' => $curlResp
  ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'status' => 'mail_exception',
    'error'  => $e->getMessage(),
    'sheets' => ($curlOk ? 'ok' : 'sheets_error'),
    'sheets_http' => $curlCode,
    'sheets_resp' => $curlResp
  ], JSON_UNESCAPED_UNICODE);
}
