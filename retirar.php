<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel Condominial - Gavetas Inteligentes</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
  <div id="login-screen">
    <div class="login-card">
      <h2>Gestão do Condomínio</h2>
      <p>Faça login para gerenciar o mobiliário e acessos</p>
      <div class="field"><label for="login_user">Usuário:</label><input id="login_user" value="admin"></div>
      <div class="field"><label for="login_pass">Senha:</label><input id="login_pass" value="admin" type="password"></div>
      <button class="btn btn-primary" onclick="realizarLogin()">Entrar</button>
    </div>
  </div>

  <div id="main-dashboard" class="hidden">
    <sidebar>
      <div class="brand">Gaveta IOT</div>
      <a onclick="switchSection('moradores')" id="nav-moradores" class="nav-item">Gestão do Condomínio</a>
      <div style="flex-grow:1"></div>
      <button onclick="handleLogout()" class="btn btn-danger">Sair</button>
    </sidebar>

    <content-wrapper>
      <header><h1 id="header-title">Mobiliário e Unidades</h1><div>Administrador</div></header>
      <main>
        <section id="sec-moradores" class="view-section active">
          <div class="card-container">
            <h2 id="morador-form-title">Cadastrar Unidade / Mobiliário</h2>
            <form id="form-morador" onsubmit="saveMorador(event)">
              <input id="morador_id" type="hidden">
              <div class="form-row">
                <div class="field"><label>Número AP:</label><input id="morador_num" required></div>
                <div class="field"><label>Bloco:</label><input id="morador_bloco" required></div>
                <div class="field"><label>Responsável:</label><input id="morador_nome" required></div>
                <div class="field"><label>WhatsApp:</label><input id="morador_wpp" required></div>
              </div>
              <div class="form-row">
                <div class="field"><label>Latitude Mobiliário:</label><input id="morador_lat" placeholder="-25.4284"></div>
                <div class="field"><label>Longitude Mobiliário:</label><input id="morador_lng" placeholder="-49.2733"></div>
                <div class="field"><button type="button" class="btn btn-secondary" onclick="getGPSLocation()">Obter Localização Atual</button></div>
              </div>
              <div style="display:flex;gap:0.5rem;margin-top:1rem"><button class="btn btn-primary" type="submit">Salvar</button><button type="button" onclick="resetMoradorForm()" class="btn btn-secondary">Cancelar</button></div>
            </form>
          </div>

          <div class="table-card"><table><thead><tr><th>Unidade</th><th>Bloco</th><th>Responsável</th><th>WhatsApp</th><th>QR Code</th><th>Ações</th></tr></thead><tbody id="data-moradores"></tbody></table></div>
        </section>
      </main>
    </content-wrapper>
  </div>

  <div id="qr-modal" class="modal hidden"><div class="modal-content" id="printable-qr-zone"><h3 id="qr-modal-title">QR Code do Mobiliário</h3><div id="qrcode-box"></div><div class="no-print"><button onclick="window.print()" class="btn btn-primary btn-sm">Exportar / Imprimir</button><button onclick="closeQrModal()" class="btn btn-secondary btn-sm">Fechar</button></div></div></div>

  <script src="/assets/app.js"></script>
</body>
</html>
