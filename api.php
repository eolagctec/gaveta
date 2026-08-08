<?php
// api.php - session-based authentication (HttpOnly cookie) with optional Redis session backend
// --- Inicialização segura: desativar exibição direta de erros e configurar logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

// garantir diretórios de logs e fallback para sessões dentro do projeto
$logsDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
$tmpDir  = __DIR__ . DIRECTORY_SEPARATOR . 'tmp_sessions';
if (!is_dir($logsDir))  @mkdir($logsDir, 0777, true);
if (!is_dir($tmpDir))   @mkdir($tmpDir, 0777, true);

// validar session.save_path atual; se inválido, usar tmp_sessions
$savePath = ini_get('session.save_path');
$validSavePath = false;
if ($savePath) {
    // session.save_path pode ter múltiplos paths separados por ; (Unix/Windows) — verificar se algum é válido
    $candidates = preg_split('/[;:]/', $savePath);
    foreach ($candidates as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (is_dir($p) && is_writable($p)) { $validSavePath = true; break; }
    }
}
if (!$validSavePath) {
    // fallback local no projeto
    session_save_path($tmpDir);
}

// proteger que headers JSON sejam enviados sempre
header("Content-Type: application/json; charset=UTF-8");

// CORS handling: allow configured origins for credentialed requests. Fallback permissive for dev if no origin.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'http://localhost',
    'http://127.0.0.1',
    'http://gaveta.local'
];
if ($origin && in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else if (!$origin) {
    // no Origin header (same-origin requests) — allow all
    header('Access-Control-Allow-Origin: *');
} else {
    // unknown origin: do not allow credentials
    header('Access-Control-Allow-Origin: null');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// If Redis is configured via environment variables, enable Redis session handler before starting session.
if (getenv('REDIS_HOST')) {
    // lib/session_redis.php sets session.save_handler and session.save_path accordingly
    $redisHelper = __DIR__ . '/lib/session_redis.php';
    if (file_exists($redisHelper)) {
        require_once $redisHelper;
    }
}

// Session cookie params - set secure/httponly/samesite
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Inicia a sessão apenas se ainda não houver uma ativa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// DB connection - read credentials from environment when available
$mysqli_host = getenv('MYSQL_HOST') ?: 'localhost';
$mysqli_user = getenv('MYSQL_USER') ?: 'root';
$mysqli_pass = getenv('MYSQL_PASSWORD') ?: '';
$mysqli_db   = getenv('MYSQL_DATABASE') ?: 'gaveta_inteligente';

$mysqli = new mysqli($mysqli_host, $mysqli_user, $mysqli_pass, $mysqli_db);
if ($mysqli->connect_error) {
    http_response_code(500);
    // registrar o erro no log (não exibir no output)
    error_log("MySQL connect_error: " . $mysqli->connect_error);
    die(json_encode(["error" => "Falha na conexão com o banco"]));
}
$mysqli->set_charset("utf8mb4");

function calcularDistanciaMetros($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

function publish_mqtt_command($equipamento_id, $payload) {
    // Placeholder MQTT publisher: if MQTT_BROKER_HOST is not set, do nothing.
    $host = getenv('MQTT_BROKER_HOST') ?: '';
    if (!$host) {
        error_log("MQTT not configured - payload: " . json_encode($payload));
        return false;
    }
    $port = getenv('MQTT_BROKER_PORT') ?: '1883';
    $user = getenv('MQTT_USER') ?: '';
    $pass = getenv('MQTT_PASS') ?: '';
    $topicPrefix = getenv('MQTT_TOPIC_PREFIX') ?: 'gaveta';
    $topic = rtrim($topicPrefix, '/') . '/' . intval($equipamento_id) . '/command';

    $message = json_encode($payload);
    // Prefer using external mosquitto_pub if available (placeholder approach)
    $mosq = getenv('MQTT_PUB_CMD') ?: 'mosquitto_pub';
    $cmd = escapeshellcmd($mosq) . ' -h ' . escapeshellarg($host) . ' -p ' . escapeshellarg($port) . ' -t ' . escapeshellarg($topic) . ' -m ' . escapeshellarg($message);
    if ($user !== '') {
        $cmd .= ' -u ' . escapeshellarg($user) . ' -P ' . escapeshellarg($pass);
    }
    // if TLS requested, additional flags would be needed; placeholder leaves that to environment
    @exec($cmd, $out, $rc);
    if ($rc === 0) {
        return true;
    }
    error_log("MQTT publish failed (cmd={$cmd}) rc={$rc} out=" . json_encode($out));
    return false;
}

function require_auth() {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
    if (isset($_SESSION['expires']) && $_SESSION['expires'] < time()) {
        // session expired
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(["error" => "Session expired"]);
        exit;
    }
    // refresh expiry (sliding)
    $_SESSION['expires'] = time() + 8 * 3600;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// OPTIONS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// AUTH endpoints (session-based)
if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';

    $admin_user = getenv('GAVETA_ADMIN_USER') ?: 'admin';
    $admin_pass = getenv('GAVETA_ADMIN_PASS') ?: 'admin';

    if ($user === $admin_user && $pass === $admin_pass) {
        // gerar novo id de sessão seguro
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['expires'] = time() + 8 * 3600; // 8h
        echo json_encode(["status" => "ok"]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Credenciais inválidas"]);
    }
    exit;
}

if ($method === 'POST' && $action === 'logout') {
    // destroy session cookie and data
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    setcookie(session_name(), '', time() - 3600, '/');
    echo json_encode(["status" => "ok"]);
    exit;
}

if ($method === 'GET' && $action === 'validate_session') {
    if (isset($_SESSION['user']) && isset($_SESSION['expires']) && $_SESSION['expires'] >= time()) {
        echo json_encode(["valid" => true]);
    } else {
        echo json_encode(["valid" => false]);
    }
    exit;
}

// 1) DASHBOARD (public read)
if ($method === 'GET' && $action === 'dados_dashboard') {
    $updateSql = "UPDATE Entregas e
        JOIN Apartamentos a ON e.apartamento_id = a.id
        JOIN Condominio c ON a.condominio_id = c.id
        SET e.status_entrega = 'expirado'
        WHERE e.status_entrega = 'disponivel'
        AND TIMESTAMPDIFF(HOUR, e.data_deposito, NOW()) >= c.prazo_retirada_horas";
    $mysqli->query($updateSql);

    $res = $mysqli->query("SELECT status_entrega, COUNT(*) as qtd FROM Entregas GROUP BY status_entrega");
    $metricas = ["disponivel" => 0, "pendente" => 0, "expirado" => 0, "retirado" => 0];
    while ($row = $res->fetch_assoc()) {
        $status = $row['status_entrega'];
        $metricas[$status] = intval($row['qtd']);
    }
    echo json_encode($metricas);
    exit;
}

// 2) CONDOMÍNIOS
if ($action === 'listar_condominios') {
    $res = $mysqli->query("SELECT id, nome, cep, endereco, whatsapp_sindico, prazo_retirada_horas, latitude, longitude FROM Condominio ORDER BY nome ASC");
    $dados = [];
    while ($row = $res->fetch_assoc()) $dados[] = $row;
    echo json_encode(["condominios" => $dados]);
    exit;
}

if ($method === 'POST' && $action === 'salvar_condominio') {
    require_auth();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { echo json_encode(["error"=>"Payload inválido"]); exit; }

    $nome = $input['nome'] ?? '';
    $cep = $input['cep'] ?? '';
    $endereco = $input['endereco'] ?? '';
    $sindico = $input['whatsapp_sindico'] ?? '';
    $prazo = intval($input['prazo_retirada_horas'] ?? 24);
    $lat = floatval($input['latitude'] ?? 0);
    $lng = floatval($input['longitude'] ?? 0);

    if (!empty($input['id'])) {
        $id = intval($input['id']);
        $stmt = $mysqli->prepare("UPDATE Condominio SET nome=?, cep=?, endereco=?, whatsapp_sindico=?, prazo_retirada_horas=?, latitude=?, longitude=? WHERE id=?");
        $stmt->bind_param('sssiddi', $nome, $cep, $endereco, $sindico, $prazo, $lat, $lng, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO Condominio (nome, cep, endereco, whatsapp_sindico, prazo_retirada_horas, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssidd', $nome, $cep, $endereco, $sindico, $prazo, $lat, $lng);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "sucesso"]);
    exit;
}

if ($method === 'POST' && $action === 'excluir_condominio') {
    require_auth();
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare("DELETE FROM Condominio WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status" => "sucesso"]);
    exit;
}

// 3) MORADORES / APARTAMENTOS
if ($action === 'listar_moradores') {
    $condo_id = isset($_GET['condominio_id']) ? intval($_GET['condominio_id']) : null;
    if ($condo_id) {
        $stmt = $mysqli->prepare("SELECT * FROM Apartamentos WHERE condominio_id = ? ORDER BY bloco ASC, numero ASC");
        $stmt->bind_param('i', $condo_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $mysqli->query("SELECT * FROM Apartamentos ORDER BY bloco ASC, numero ASC");
    }
    $dados = [];
    while ($row = $res->fetch_assoc()) $dados[] = $row;
    echo json_encode(["moradores" => $dados]);
    exit;
}

if ($method === 'POST' && $action === 'salvar_morador') {
    require_auth();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { echo json_encode(["error"=>"Payload inválido"]); exit; }

    $condo_id = intval($input['condominio_id'] ?? 0);
    $num = $input['numero'] ?? '';
    $bloco = $input['bloco'] ?? '';
    $nome = $input['nome'] ?? '';
    $wpp = $input['whatsapp'] ?? $input['whatsapp_morador'] ?? '';
    $lat = floatval($input['latitude'] ?? 0);
    $lng = floatval($input['longitude'] ?? 0);

    if (!empty($input['id'])) {
        $id = intval($input['id']);
        $stmt = $mysqli->prepare("UPDATE Apartamentos SET numero=?, bloco=?, nome_morador=?, whatsapp_morador=?, latitude=?, longitude=? WHERE id=?");
        $stmt->bind_param('ssssddi', $num, $bloco, $nome, $wpp, $lat, $lng, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO Apartamentos (condominio_id, numero, bloco, nome_morador, whatsapp_morador, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssdd', $condo_id, $num, $bloco, $nome, $wpp, $lat, $lng);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "sucesso"]);
    exit;
}

if ($method === 'POST' && $action === 'excluir_morador') {
    require_auth();
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare("DELETE FROM Apartamentos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status" => "sucesso"]);
    exit;
}

// 4) EMPRESAS / LOGISTICA
if ($method === 'POST' && $action === 'salvar_empresa') {
    require_auth();
    $nome = $_POST['nome_empresa'] ?? '';
    $empresa_id = intval($_POST['empresa_id'] ?? 0);
    $logo_path = null;
    if (!empty($_FILES['logo_empresa']) && $_FILES['logo_empresa']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['logo_empresa']['name'], PATHINFO_EXTENSION);
        $destino_dir = __DIR__ . '/uploads';
        if (!is_dir($destino_dir)) mkdir($destino_dir, 0755, true);
        $destino = 'uploads/' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($_FILES['logo_empresa']['tmp_name'], __DIR__ . '/' . $destino)) {
            $logo_path = $destino;
        }
    }
    if ($empresa_id) {
        if ($logo_path) {
            $stmt = $mysqli->prepare("UPDATE Logistica SET nome=?, logo_path=? WHERE id=?");
            $stmt->bind_param('ssi', $nome, $logo_path, $empresa_id);
        } else {
            $stmt = $mysqli->prepare("UPDATE Logistica SET nome=? WHERE id=?");
            $stmt->bind_param('si', $nome, $empresa_id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO Logistica (nome, logo_path) VALUES (?, ?)");
        $stmt->bind_param('ss', $nome, $logo_path);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "sucesso"]);
    exit;
}

if ($action === 'listar_empresas') {
    $res = $mysqli->query("SELECT id, nome, logo_path FROM Logistica ORDER BY nome ASC");
    $dados = [];
    while ($row = $res->fetch_assoc()) $dados[] = $row;
    echo json_encode(["empresas" => $dados]);
    exit;
}

if ($method === 'POST' && $action === 'excluir_empresa') {
    require_auth();
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare("DELETE FROM Logistica WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status" => "sucesso"]);
    exit;
}

// 5) DADOS PARA ENTREGADOR (QR)
if ($method === 'GET' && $action === 'dados_entrega_condominio') {
    $condo_id = intval($_GET['condominio_id'] ?? 0);
    $stmt = $mysqli->prepare("SELECT nome FROM Condominio WHERE id = ?");
    $stmt->bind_param('i', $condo_id);
    $stmt->execute();
    $res_condo = $stmt->get_result();
    $stmt->close();

    $stmtA = $mysqli->prepare("SELECT id, numero, bloco FROM Apartamentos WHERE condominio_id = ? ORDER BY bloco ASC, numero ASC");
    $stmtA->bind_param('i', $condo_id);
    $stmtA->execute();
    $res_aptos = $stmtA->get_result();
    $stmtA->close();

    $aptos = [];
    while ($row = $res_aptos->fetch_assoc()) $aptos[] = $row;

    if ($res_condo && $res_condo->num_rows > 0) {
        $condo = $res_condo->fetch_assoc();
        echo json_encode(["condominio_nome" => $condo['nome'], "apartamentos" => $aptos]);
    } else {
        echo json_encode(["error" => "Condominio nao localizado"]);
    }
    exit;
}

// New endpoints: scan_equipamento, authorize_open, authorize_resident_open, sensor_reading, gerar_resident_link

// scan_equipamento: accepts qr_hash or equipamento_id and returns equipamento info + entregas
if ($method === 'POST' && $action === 'scan_equipamento') {
    $input = json_decode(file_get_contents('php://input'), true);
    $qr = $input['qr_hash'] ?? '';
    $equipamento_id = intval($input['equipamento_id'] ?? 0);

    if ($qr !== '') {
        $stmt = $mysqli->prepare("SELECT id, condominio_id, label FROM Equipamentos WHERE qr_hash = ? LIMIT 1");
        $stmt->bind_param('s', $qr);
    } else {
        $stmt = $mysqli->prepare("SELECT id, condominio_id, label FROM Equipamentos WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $equipamento_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(["error" => "Equipamento não encontrado"]);
        exit;
    }
    $equip = $res->fetch_assoc();
    $stmt->close();

    $stmt2 = $mysqli->prepare("SELECT e.id, e.qr_code_retirada, e.status_entrega FROM Entregas e WHERE (e.equipamento_id = ? OR e.condominio_id = ?) AND e.status_entrega IN ('disponivel','pendente')");
    $stmt2->bind_param('ii', $equip['id'], $equip['condominio_id']);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $ent = [];
    while ($r = $res2->fetch_assoc()) $ent[] = $r;
    $stmt2->close();

    // fetch condo name
    $stmt3 = $mysqli->prepare("SELECT nome FROM Condominio WHERE id = ? LIMIT 1");
    $stmt3->bind_param('i', $equip['condominio_id']);
    $stmt3->execute();
    $cn = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();

    echo json_encode(["equipamento" => $equip, "condominio_nome" => $cn['nome'] ?? null, "entregas" => $ent]);
    exit;
}

// authorize_open: called by deliverer app to request opening the front slot (insertion flow)
if ($method === 'POST' && $action === 'authorize_open') {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipamento_id = intval($input['equipamento_id'] ?? 0);
    $condominio_id = intval($input['condominio_id'] ?? 0);
    $empresa_id = intval($input['empresa_id'] ?? 0);
    $bloco = $input['bloco'] ?? '';
    $numero = $input['numero'] ?? '';
    $port = $input['port'] ?? 'front';

    if (!$equipamento_id && !$condominio_id) { echo json_encode(["error"=>"equipamento_id ou condominio_id obrigatorio"]); exit; }

    // create an authorization record (simple approach: resident link generated later when stored)
    $token = bin2hex(random_bytes(12));
    $expires = date('Y-m-d H:i:s', time() + 60*5); // short-lived command token (5 min)

    // publish MQTT command (placeholder - will do nothing if MQTT not configured)
    $payload = ["action"=>"open","port"=>$port,"token"=>$token,"issued_by"=>"api","expires_at"=>$expires];
    publish_mqtt_command($equipamento_id ?: $condominio_id, $payload);

    // record audit (simple file log)
    error_log("authorize_open: equip=".($equipamento_id?:$condominio_id)." port={$port} token={$token}");

    echo json_encode(["open"=>true,"token"=>$token,"expires_at"=>$expires]);
    exit;
}

// authorize_resident_open: opens rear port using resident token (token-based flow)
if ($method === 'POST' && $action === 'authorize_resident_open') {
    $input = json_decode(file_get_contents('php://input'), true);
    $resident_token = $input['token'] ?? '';
    $port = $input['port'] ?? 'rear';
    // validate token against Entregas.resident_token
    $stmt = $mysqli->prepare("SELECT id, equipamento_id, resident_token_expires FROM Entregas WHERE resident_token = ? LIMIT 1");
    $stmt->bind_param('s', $resident_token);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows===0) { echo json_encode(["open"=>false,"reason"=>"token_invalido"]); exit; }
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row['resident_token_expires'] && strtotime($row['resident_token_expires']) < time()) {
        echo json_encode(["open"=>false,"reason"=>"token_expirado"]);
        exit;
    }
    $equipamento_id = $row['equipamento_id'];
    $token = bin2hex(random_bytes(12));
    $expires = date('Y-m-d H:i:s', time() + 60*5);
    $payload = ["action"=>"open","port"=>$port,"token"=>$token,"issued_by"=>"resident","entrega_id"=>intval($row['id']),"expires_at"=>$expires];
    publish_mqtt_command($equipamento_id, $payload);
    echo json_encode(["open"=>true,"token"=>$token,"expires_at"=>$expires]);
    exit;
}

// sensor_reading: records sensor readings (ultrassom and presence) and evaluates rules
if ($method === 'POST' && $action === 'sensor_reading') {
    $input = json_decode(file_get_contents('php://input'), true);
    $equip_id = intval($input['equipamento_id'] ?? 0);
    $entrega_id = intval($input['entrega_id'] ?? 0);
    $type = $input['type'] ?? '';
    $phase = $input['phase'] ?? null; // before|after
    $value = isset($input['value']) ? floatval($input['value']) : null;
    $port = $input['port'] ?? null;
    if (!$type || $value === null) { echo json_encode(["error"=>"type e value obrigatorios"]); exit; }

    $stmt = $mysqli->prepare("INSERT INTO SensorReadings (entrega_id, equipamento_id, port_id, type, phase, value) VALUES (?, ?, NULL, ?, ?, ?)");
    $stmt->bind_param('iissd', $entrega_id, $equip_id, $type, $phase, $value);
    $stmt->execute();
    $stmt->close();

    if ($type === 'ultrassom' && $entrega_id) {
        if ($phase === 'before') {
            $stmt = $mysqli->prepare("UPDATE Entregas SET volume_before = ? WHERE id = ?");
            $stmt->bind_param('di', $value, $entrega_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["status"=>"ok","msg"=>"before saved"]);
            exit;
        } else if ($phase === 'after') {
            $stmt = $mysqli->prepare("SELECT volume_before FROM Entregas WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $entrega_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $before = isset($row['volume_before']) ? floatval($row['volume_before']) : null;
            $stmt = $mysqli->prepare("UPDATE Entregas SET volume_after = ? WHERE id = ?");
            $stmt->bind_param('di', $value, $entrega_id);
            $stmt->execute();
            $stmt->close();

            // check presence last reading
            $stmt = $mysqli->prepare("SELECT value FROM SensorReadings WHERE entrega_id = ? AND type = 'presence' ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('i', $entrega_id);
            $stmt->execute();
            $pv = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $presence = $pv ? (bool)$pv['value'] : false;

            $relative_threshold = 0.10;
            $absolute_threshold = 0.05;

            $delivered = false;
            $note = '';
            if ($before !== null) {
                $delta = $before - $value;
                $relative = ($before>0) ? ($delta / $before) : 0;
                if ($presence && ($relative >= $relative_threshold || $delta >= $absolute_threshold)) {
                    $delivered = true;
                } elseif (!$presence && ($relative >= $relative_threshold || $delta >= $absolute_threshold)) {
                    $delivered = true;
                } elseif ($presence && !($relative >= $relative_threshold || $delta >= $absolute_threshold)) {
                    $note = 'presence_no_volume_change';
                } else {
                    $note = 'no_presence_no_volume_change';
                }
            } else {
                if ($presence) { $delivered = true; $note='delivered_by_presence_only'; } else { $note='insufficient_data'; }
            }

            if ($delivered) {
                $stmt = $mysqli->prepare("UPDATE Entregas SET status_entrega = 'retirado', ultrassom_confirmado = 1 WHERE id = ?");
                $stmt->bind_param('i',$entrega_id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(["status"=>"ok","result"=>"delivered","note"=>$note]);
            } else {
                $stmt = $mysqli->prepare("UPDATE Entregas SET status_entrega = 'pendente', ultrassom_confirmado = 0 WHERE id = ?");
                $stmt->bind_param('i',$entrega_id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(["status"=>"ok","result"=>"pending_review","note"=>$note]);
            }
            exit;
        }
    }

    echo json_encode(["status"=>"ok"]);
    exit;
}

// gerar_resident_link: generates resident_token and expiry for an entrega and optionally sends whatsapp
if ($method === 'POST' && $action === 'gerar_resident_link') {
    require_auth();
    $input = json_decode(file_get_contents('php://input'), true);
    $entrega_id = intval($input['entrega_id'] ?? 0);
    if (!$entrega_id) { echo json_encode(["error"=>"entrega_id necessario"]); exit; }
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 24*3600); // 24h
    $stmt = $mysqli->prepare("UPDATE Entregas SET resident_token = ?, resident_token_expires = ? WHERE id = ?");
    $stmt->bind_param('ssi', $token, $expires, $entrega_id);
    $stmt->execute();
    $stmt->close();
    // Optionally: send WhatsApp using external service (placeholder)
    $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'gaveta.local') . '/retirada.php?token=' . $token;
    echo json_encode(["status"=>"ok","token"=>$token,"expires_at"=>$expires,"link"=>$link]);
    exit;
}

// Rotas legadas (laboratório) - require auth
if ($method === 'POST' && $action === 'criar_pendente') {
    require_auth();
    $input = json_decode(file_get_contents('php://input'), true);
    $apto_id = intval($input['apto_id'] ?? 0);
    $empresa = $mysqli->real_escape_string($input['empresa'] ?? '');
    $stmt = $mysqli->prepare("INSERT INTO Entregas (apartamento_id, empresa_logistica, status_entrega) VALUES (?, ?, 'pendente')");
    $stmt->bind_param('is', $apto_id, $empresa);
    $stmt->execute();
    echo json_encode(["status"=>"sucesso", "entrega_id" => $stmt->insert_id]);
    $stmt->close();
    exit;
}
if ($method === 'POST' && $action === 'cancelar_entrega') {
    require_auth();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['entrega_id'] ?? 0);
    $stmt = $mysqli->prepare("DELETE FROM Entregas WHERE id = ? AND status_entrega = 'pendente'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status"=>"sucesso"]);
    exit;
}

$mysqli->close();
http_response_code(404);
echo json_encode(["error"=>"Rota nao encontrada"]);
