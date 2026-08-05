# Gaveta - Painel Condominial (setup rápido)

Este repositório contém um painel web simples (frontend) e uma API em PHP para gerenciar condomínios, apartamentos, frotistas e entregas.

Pré-requisitos
- PHP 7.4+ com extensões MySQLi
- MySQL/MariaDB
- Servidor web (ou usar php -S para testes locais)

Passos rápidos (desenvolvimento)
1. Clone o repositório
   git clone https://github.com/eolagctec/gaveta.git

2. Criar banco e tabelas
   mysql -u root -p < migrations.sql

3. Ajustar credenciais do banco
   Se necessário, edite `api.php` e ajuste as credenciais da conexão.

4. Configurar credenciais administrativas (opcional)
   Por padrão o usuário/ senha administrativo é `admin`/`admin`.
   Para alterar via variáveis de ambiente:
     export GAVETA_ADMIN_USER=seu_usuario
     export GAVETA_ADMIN_PASS=sua_senha

5. Permissões para uploads
   mkdir uploads && chown www-data:www-data uploads && chmod 755 uploads

6. Rodar localmente (teste)
   php -S 0.0.0.0:8000
   Abra http://localhost:8000/index.html

Sobre autenticação
- Implementado fluxo simples de login que retorna um token (Bearer). O frontend armazena esse token em localStorage e o envia no header Authorization para rotas que modificam dados.
- Tokens são guardados em `tokens.json` (demo). Em produção, implemente armazenamento seguro (DB/Redis) e HTTPS.

Próximos passos recomendados
- Mover credenciais sensíveis para variáveis de ambiente e não versionar.
- Substituir store de tokens por sessão segura no servidor (cookies seguros) ou por JWT.
- Habilitar HTTPS e regras CORS mais restritas.
- Adicionar validação de tipos e limites para uploads.
- Implementar testes automatizados e CI.

Se quiser, posso:
- Implementar autenticação via sessão (cookies) ou JWT.
- Adicionar scripts de deploy com Docker.
- Criar seeds para inserir dados de exemplo.

Escolha uma ação e eu aplico as mudanças e commito no repositório.
