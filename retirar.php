<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Condominial - Gavetas Inteligentes</title>
    <!-- Gerador de QR Code JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <!-- Leaflet CSS & JS (must load before any script that uses L) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f1f5f9; color: #334155; min-height: 100vh; }
        
        #login-screen { position: fixed; inset: 0; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .login-card { background: white; padding: 2.5rem; border-radius: 1rem; width: 100%; max-width: 24rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); text-align: center; }
        .login-card h2 { color: #0f172a; margin-bottom: 0.5rem; font-size: 1.5rem; }
        .login-card p { color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem; }
        
        #main-dashboard { display: flex; min-height: 100vh; }
        sidebar { width: 16rem; background-color: #0f172a; color: white; display: flex; flex-direction: column; padding: 1.5rem 1rem; }
        sidebar .brand { font-size: 1.25rem; font-weight: 700; color: #38bdf8; text-align: center; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
        sidebar .nav-item { padding: 0.75rem 1rem; border-radius: 0.5rem; color: #94a3b8; font-weight: 600; cursor: pointer; text-decoration: none; display: block; margin-bottom: 0.5rem; transition: all 0.2s; }
        sidebar .nav-item.active, sidebar .nav-item:hover { background-color: #1e293b; color: white; }
        
        content-wrapper { flex-grow: 1; display: flex; flex-direction: column; }
        header { background: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
        header h1 { font-size: 1.25rem; color: #1e293b; }
        main { padding: 2rem; max-width: 75rem; width: 100%; margin: 0 auto; flex-grow: 1; }
        
        .view-section { display: none; }
        .view-section.active { display: block; }
        
        .card-container { background: white; padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .field { display: flex; flex-direction: column; gap: 0.375rem; }
        .field label { font-size: 0.812rem; font-weight: 600; color: #475569; }
        input, select { padding: 0.625rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; color: #1e293b; background: white; width: 100%; }
        
        .table-card { background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 1.5rem; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
        th, td { padding: 0.875rem 1.25rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
        
        .btn { padding: 0.625rem 1.25rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; border-radius: 0.25rem; }
        .action-group { display: flex; gap: 0.5rem; }

        /* MODAL DE EXPORTAÇÃO DO QR CODE */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 10000; }
        .modal-content { background: white; padding: 2rem; border-radius: 0.75rem; text-align: center; max-width: 360px; width: 100%; }
        #qrcode-box { margin: 1.5rem auto; display: flex; justify-content: center; }

        /* REGRAS DE IMPRESSÃO */
        @media print {
            body * { visibility: hidden; }
            #printable-qr-zone, #printable-qr-zone * { visibility: visible; }
            #printable-qr-zone { position: absolute; left: 0; top: 0; width: 100%; text-align: center; padding: 2rem; }
            .no-print { display: none !important; }
        }
        
        .hidden { display: none !important; }
    </style>
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

    <script>
        window.onload = function() {
            if (localStorage.getItem('auth_token') === 'true') {
                document.getElementById('login-screen').className = 'hidden';
                document.getElementById('main-dashboard').className = '';
                syncData();
            }
        };

        function realizarLogin() {
            var u = document.getElementById('login_user').value;
            var p = document.getElementById('login_pass').value;
            if (u === 'admin' && p === 'admin') {
                localStorage.setItem('auth_token', 'true');
                document.getElementById('login-screen').className = 'hidden';
                document.getElementById('main-dashboard').className = '';
                syncData();
            }
        }

        function handleLogout() {
            localStorage.removeItem('auth_token');
            window.location.reload();
        }

        function switchSection(sec) {
            document.querySelectorAll('.view-section').forEach(s => s.className = 'view-section');
            document.querySelectorAll('.nav-item').forEach(n => n.className = 'nav-item');
            document.getElementById('sec-' + sec).className = 'view-section active';
            document.getElementById('nav-' + sec).className = 'nav-item active';
        }

        /* OBTÉM AS COORDENADAS GPS DO NAVEGADOR */
        function getGPSLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('morador_lat').value = position.coords.latitude;
                    document.getElementById('morador_lng').value = position.coords.longitude;
                }, function() {
                    alert("Erro ao obter a localização via navegador.");
                });
            } else {
                alert("Geolocalização não é suportada por este dispositivo.");
            }
        }

        /* GERA O QR CODE DO MOBILIÁRIO QUE O ENTREGADOR VAI LER */
        function generateQrCode(id, bloco, numero) {
            document.getElementById('qrcode-box').innerHTML = "";
            
            // O QR Code direciona para a página de entrega pública com a chave do condomínio/mobiliário
            var urlEntrega = window.location.origin + "/entregar.php?condominio_id=" + id;
            
            new QRCode(document.getElementById("qrcode-box"), {
                text: urlEntrega,
                width: 180,
                height: 180
            });

            document.getElementById('qr-modal-title').innerText = "Mobiliário - Bloco " + bloco + " / AP " + numero;
            document.getElementById('qr-modal').classList.remove('hidden');
        }

        function closeQrModal() {
            document.getElementById('qr-modal').classList.add('hidden');
        }

        /* LISTAR DADOS */
        function syncData() {
            fetch('api.php?action=listar_moradores')
                .then(res => res.json())
                .then(data => {
                    var morTable = document.getElementById('data-moradores');
                    morTable.innerHTML = '';
                    if (data.moradores) {
                        data.moradores.forEach(m => {
                            morTable.innerHTML += `
                                <tr>
                                    <td>${m.numero}</td>
                                    <td>${m.bloco}</td>
                                    <td>${m.nome_morador}</td>
                                    <td>${m.whatsapp_morador}</td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="generateQrCode(${m.id}, '${m.bloco}', '${m.numero}')">
                                            Gerar / Imprimir QR Code
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <button class="btn btn-primary btn-sm" onclick="editMorador(${m.id}, '${m.numero}', '${m.bloco}', '${m.nome_morador}', '${m.whatsapp_morador}', '${m.latitude||''}', '${m.longitude||''}')">Editar</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteMorador(${m.id})">Excluir</button>
                                        </div>
                                    </td>
                                </tr>`;
                        });
                    }
                }).catch(console.error);
        }

        /* SALVAR / EDITAR */
        function saveMorador(e) {
            e.preventDefault();
            var id = document.getElementById('morador_id').value;
            var action = id ? 'editar_morador' : 'cadastrar_morador';
            var payload = {
                id: id,
                numero: document.getElementById('morador_num').value,
                bloco: document.getElementById('morador_bloco').value,
                nome: document.getElementById('morador_nome').value,
                whatsapp: document.getElementById('morador_wpp').value,
                latitude: document.getElementById('morador_lat').value,
                longitude: document.getElementById('morador_lng').value
            };

            fetch('api.php?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(() => {
                resetMoradorForm();
                syncData();
            }).catch(console.error);
        }

        function editMorador(id, num, bloco, nome, wpp, lat, lng) {
            document.getElementById('morador_id').value = id;
            document.getElementById('morador_num').value = num;
            document.getElementById('morador_bloco').value = bloco;
            document.getElementById('morador_nome').value = nome;
            document.getElementById('morador_wpp').value = wpp;
            document.getElementById('morador_lat').value = lat;
            document.getElementById('morador_lng').value = lng;
            document.getElementById('morador-form-title').innerText = "Editar Cadastro Condominial";
            document.getElementById('btn-morador-submit').innerText = "Atualizar";
            document.getElementById('btn-morador-cancel').classList.remove('hidden');
        }

        function resetMoradorForm() {
            document.getElementById('form-morador').reset();
            document.getElementById('morador_id').value = '';
            document.getElementById('morador-form-title').innerText = "Cadastrar Unidade / Mobiliário";
            document.getElementById('btn-morador-submit').innerText = "Salvar";
            document.getElementById('btn-morador-cancel').classList.add('hidden');
        }

        function deleteMorador(id) {
            if (confirm('Deseja excluir este registro do condomínio?')) {
                fetch('api.php?action=excluir_morador&id=' + id, { method: 'POST' })
                    .then(() => syncData()).catch(console.error);
            }
        }
    </script>
</body>
</html>
