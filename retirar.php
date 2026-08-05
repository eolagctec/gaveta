<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Condominial - Gavetas Inteligentes</title>
    <!-- Gerador de QR Code JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>

    <!-- AUTENTICAÇÃO -->
    <div id="login-screen">
        <div class="login-card">
            <h2>Gestão do Condomínio</h2>
            <p>Faça login para gerenciar o mobiliário e acessos</p>
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <div class="field" style="text-align:left;">
                    <label for="login_user">Usuário:</label>
                    <input type="text" id="login_user" value="admin">
                </div>
                <div class="field" style="text-align:left;">
                    <label for="login_pass">Senha:</label>
                    <input type="password" id="login_pass" value="admin">
                </div>
                <button type="button" onclick="realizarLogin()" class="btn btn-primary" style="width:100%; padding:0.75rem;">Entrar</button>
            </div>
        </div>
    </div>

    <!-- PAINEL -->
    <div id="main-dashboard" class="hidden">
        <sidebar>
            <div class="brand">Gaveta IOT</div>
            <a onclick="switchSection('moradores')" class="nav-item active" id="nav-moradores">Gestão do Condomínio</a>
            <div style="flex-grow:1;"></div>
            <button onclick="handleLogout()" class="btn btn-danger" style="width:100%;">Sair</button>
        </sidebar>

        <content-wrapper>
            <header>
                <h1 id="header-title">Mobiliário e Unidades</h1>
                <div style="font-size:0.875rem; color:#64748b;">Administrador Autenticado</div>
            </header>

            <main>
                <!-- SEÇÃO DO CONDOMÍNIO (CRUD + GPS + QR CODE) -->
                <div id="sec-moradores" class="view-section active">
                    <div class="card-container">
                        <h2 id="morador-form-title">Cadastrar Unidade / Mobiliário</h2>
                        <form id="form-morador" onsubmit="saveMorador(event)" style="margin-top:1rem;">
                            <input type="hidden" id="morador_id">
                            
                            <div class="form-row">
                                <div class="field"><label for="morador_num">Número AP:</label><input type="text" id="morador_num" required></div>
                                <div class="field"><label for="morador_bloco">Bloco:</label><input type="text" id="morador_bloco" required></div>
                                <div class="field"><label for="morador_nome">Responsável:</label><input type="text" id="morador_nome" required></div>
                                <div class="field"><label for="morador_wpp">WhatsApp:</label><input type="text" id="morador_wpp" required></div>
                            </div>
                            
                            <!-- COORDENADAS GPS PARA O ARMÁRIO -->
                            <div class="form-row">
                                <div class="field"><label for="morador_lat">Latitude Mobiliário:</label><input type="text" id="morador_lat" placeholder="-25.4284" required></div>
                                <div class="field"><label for="morador_lng">Longitude Mobiliário:</label><input type="text" id="morador_lng" placeholder="-49.2733" required></div>
                                <div class="field" style="justify-content: flex-end;">
                                    <button type="button" class="btn btn-secondary" onclick="getGPSLocation()">Obter Localização Atual</button>
                                </div>
                            </div>

                            <div style="display:flex; gap:0.5rem; margin-top: 1rem;">
                                <button type="submit" class="btn btn-primary" id="btn-morador-submit">Salvar</button>
                                <button type="button" onclick="resetMoradorForm()" class="btn btn-secondary hidden" id="btn-morador-cancel">Cancelar</button>
                            </div>
                        </form>
                    </div>

                    <!-- TABELA DE UNIDADES -->
                    <div class="table-card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Unidade</th>
                                    <th>Bloco</th>
                                    <th>Responsável</th>
                                    <th>WhatsApp</th>
                                    <th>QR Code Mobiliário</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="data-moradores"></tbody>
                        </table>
                    </div>
                </div>
            </main>
        </content-wrapper>
    </div>

    <!-- MODAL DE QR CODE E IMPRESSÃO -->
    <div id="qr-modal" class="modal hidden">
        <div class="modal-content" id="printable-qr-zone">
            <h3 id="qr-modal-title">QR Code do Mobiliário</h3>
            <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">Aponte a câmera para abrir o formulário de entrega no condomínio</p>
            <div id="qrcode-box"></div>
            <div style="display: flex; gap: 0.5rem; justify-content: center;" class="no-print">
                <button class="btn btn-primary btn-sm" onclick="window.print()">Exportar / Imprimir</button>
                <button class="btn btn-secondary btn-sm" onclick="closeQrModal()">Fechar</button>
            </div>
        </div>
    </div>

    <script src="/assets/app.js"></script>
</body>
</html>
