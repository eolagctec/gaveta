-- seeds.sql
USE gaveta_inteligente;

INSERT INTO Condominio (nome, cep, endereco, whatsapp_sindico, prazo_retirada_horas, latitude, longitude) VALUES
('Condominio Central', '80000000', 'Rua Central, 100', '+5511999999999', 24, -25.4284, -49.2733),
('Condominio Leste', '81000000', 'Av. Leste, 200', '+5511988888888', 24, -25.4200, -49.2800);

INSERT INTO Apartamentos (condominio_id, numero, bloco, nome_morador, whatsapp_morador, latitude, longitude) VALUES
(1, '101', 'A', 'Maria Silva', '+5511977777777', -25.4285, -49.2734),
(1, '102', 'A', 'Joao Souza', '+5511966666666', -25.4286, -49.2735),
(2, '201', 'B', 'Pedro Alves', '+5511955555555', -25.4201, -49.2801);

INSERT INTO Logistica (nome, logo_path) VALUES
('Logistica A', NULL),
('Frotista Brasil', NULL);

INSERT INTO Entregas (apartamento_id, empresa_logistica, status_entrega, data_deposito, qr_code_retirada) VALUES
(1, 'Logistica A', 'disponivel', NOW() - INTERVAL 1 HOUR, 'TOKEN123'),
(2, 'Frotista Brasil', 'pendente', NOW(), 'TOKEN456');
