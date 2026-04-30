<?php
// visit-tracker.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ========== CONFIGURACIÓN (YA CONFIGURADO CON TUS DATOS) ==========
define('TELEGRAM_TOKEN', '8607426657:AAFFQMG_061Qf_HlkDuMmzgXFsWH9mSmBOk');
define('CHAT_ID', '-5058110468');
define('SECRET_TOKEN', 'SmartViajes2024SecureToken');
// ===================================

// Función para obtener geolocalización por IP
function getGeolocation($ip) {
    $private_ips = ['127.0.0.1', '::1', 'localhost'];
    if (in_array($ip, $private_ips)) {
        return ['country' => 'Local', 'city' => 'Localhost'];
    }
    
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,city,regionName&lang=es";
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            return [
                'country' => $data['country'],
                'city' => $data['city'] . ($data['regionName'] ? " ({$data['regionName']})" : "")
            ];
        }
    }
    return ['country' => 'Desconocido', 'city' => 'Desconocida'];
}

// Función para enviar mensaje a Telegram
function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result !== false;
}

// Verificar token de seguridad
$headers = getallheaders();
$token = isset($headers['X-Visit-Token']) ? $headers['X-Visit-Token'] : '';

if ($token !== SECRET_TOKEN) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

// Obtener datos de la visita
$input = json_decode(file_get_contents('php://input'), true);
$ip = $input['ip'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
$ip = explode(',', $ip)[0];
$ip = trim($ip);

$geo = getGeolocation($ip);
$referer = $input['referer'] ?? $_SERVER['HTTP_REFERER'] ?? 'Directo';
$page = $input['page'] ?? $_SERVER['HTTP_REFERER'] ?? 'Página principal';
$userAgent = $input['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

// Formatear fecha
$date = new DateTime($timestamp);
$date->setTimezone(new DateTimeZone('America/Bogota'));
$formattedDate = $date->format('l j \d\e F, g:i A');
$formattedDate = str_replace(
    ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
    ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'],
    $formattedDate
);

$refererClean = parse_url($referer, PHP_URL_HOST) ?: ($referer !== 'Directo' ? $referer : 'Directo');
$pageClean = parse_url($page, PHP_URL_PATH) ?: 'Página principal';

function getBrowser($userAgent) {
    if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
    if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'Safari') !== false) return 'Safari';
    if (strpos($userAgent, 'Edge') !== false) return 'Edge';
    if (strpos($userAgent, 'Opera') !== false) return 'Opera';
    return 'Otro';
}

// Mensaje para Telegram
$message = "🟢 <b>NUEVO VISITANTE</b> 🟢\n\n";
$message .= "🌐 <b>IP:</b> <code>{$ip}</code>\n";
$message .= "📍 <b>País:</b> {$geo['country']}\n";
$message .= "🏙️ <b>Ciudad:</b> {$geo['city']}\n";
$message .= "🔗 <b>Referido:</b> {$refererClean}\n";
$message .= "📄 <b>Página:</b> {$pageClean}\n";
$message .= "⏰ <b>Hora:</b> {$formattedDate}\n";
$message .= "📱 <b>Dispositivo:</b> " . (strpos($userAgent, 'Mobile') !== false ? '📱 Móvil' : '💻 Desktop') . "\n";
$message .= "🖥️ <b>Navegador:</b> " . getBrowser($userAgent);

// Guardar en log
$logEntry = "[$timestamp] IP: $ip | País: {$geo['country']} | Ciudad: {$geo['city']} | Ref: $refererClean\n";
file_put_contents('visits.log', $logEntry, FILE_APPEND);

// Enviar a Telegram
$sent = sendTelegramMessage($message);

echo json_encode([
    'success' => true,
    'telegram_sent' => $sent,
    'timestamp' => $timestamp
]);
?>