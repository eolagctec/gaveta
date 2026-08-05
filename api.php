<?php
header("Content-Type: application/json; charset=UTF-8");
// CORS - permitir origens específicas em produção
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . ($origin === '*' ? '*' : $origin));
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Simple token store file (for demo). In production, use DB or cache.
define('TOKEN_STORE', __DIR__ . '/tokens.json');

// DB connection - adjust for your environment
$mysqli = new mysqli("localhost", "root", "", "gaveta_inteligente");
if ($mysqli->connect_error) {
    http_response_code(500);
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

function load_tokens() {
    if (!file_exists(TOKEN_STORE)) return [];
    $raw = file_get_contents(TOKEN_STORE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function save_tokens($tokens) {
    file_put_contents(TOKEN_STORE, json_encode($tokens));
}

function generate_token() {
    return bin2hex(random_bytes(16));
}

function get_bearer_token() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return trim($matches[1]);
        }
    }
    // check query param
    if (isset($_GET['token'])) return $_GET['token'];
    return null;
}

function require_auth() {
    $token = get_bearer_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
    $tokens = load_tokens();
    if (!isset($tokens[$token])) {
        http_response_code(401);
        echo json_encode(["error" => "Token inválido ou expirado"]);
        exit;
    }
    // check expiry
    if ($tokens[$token] < time()) {
        unset($tokens[$token]);
        save_tokens($tokens);
        http_response_code(401);
        echo json_encode(["error" => "Token expirado"]);
        exit;
    }
    return true;
}

$method = $_SERVER['REQUEST_METHOD'];
action:
$action = isset($_GET['action']) ? $_GET['action'] : '';

// OPTIONS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// AUTH: login, logout, validate
if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $input['username'] ?? '';
    $pass = $input['password'] ?? '';
    // use env vars in production
    $admin_user = getenv('GAVETA_ADMIN_USER') ?: 'admin';
    $admin_pass = getenv('GAVETA_ADMIN_PASS') ?: 'admin';
    if ($user === $admin_user && $pass === $admin_pass) {
        $tokens = load_tokens();
        $token = generate_token();
        // token válido por 8 horas
        $tokens[$token] = time() + 8 * 3600;
        save_tokens($tokens);
        echo json_encode(["status" => "ok", "token" => $token]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Credenciais inválidas"]);
    }
    exit;
}

if ($method === 'POST' && $action === 'logout') {
    $token = get_bearer_token();
    $tokens = load_tokens();
    if ($token && isset($tokens[$token])) {
        unset($tokens[$token]);
        save_tokens($tokens);
    }
    echo json_encode(["status" => "ok"]);
    exit;
}

if ($method === 'GET' && $action === 'validate_token') {
    $token = get_bearer_token();
    $tokens = load_tokens();
    if ($token && isset($tokens[$token]) && $tokens[$token] >= time()) {
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

// 6) VALIDAR PERIMETRO POR TOKEN
if ($method === 'POST' && $action === 'validar_perimetro_morador') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { echo json_encode(["error"=>"Payload inválido"]); exit; }

    $token = $input['token'] ?? '';
    $user_lat = floatval($input['latitude'] ?? 0);
    $user_lng = floatval($input['longitude'] ?? 0);

    $sql = "SELECT e.id as entrega_id, c.latitude, c.longitude, c.nome as condo_nome
            FROM Entregas e
            JOIN Apartamentos a ON e.apartamento_id = a.id
            JOIN Condominio c ON a.condominio_id = c.id
            WHERE e.qr_code_retirada = ? AND e.status_entrega IN ('disponivel', 'expirado')";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $dados = $res->fetch_assoc();
        $distancia = calcularDistanciaMetros($user_lat, $user_lng, $dados['latitude'], $dados['longitude']);
        if ($distancia <= 50.0) {
            echo json_encode(["status" => "autorizado", "entrega_id" => $dados['entrega_id'], "distancia_metros" => round($distancia,1)]);
        } else {
            echo json_encode(["status" => "bloqueado", "mensagem" => "Acesso negado. Voce esta fora do perimetro do " . $dados['condo_nome'] . " (Distancia: " . round($distancia) . " metros). Vá até o armário físico."]);
        }
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Encomenda indisponivel ou token invalido."]);
    }
    $stmt->close();
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
