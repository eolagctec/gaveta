-- migrations/20260808_add_equipments_ports_sensors.sql
-- Cria tabelas necessárias para equipamentos, portas e leituras de sensores
-- Altera tabela Entregas para suportar integração com equipamentos

CREATE TABLE IF NOT EXISTS Equipamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  condominio_id INT NOT NULL,
  label VARCHAR(100) DEFAULT 'Gaveta C',
  qr_hash VARCHAR(128) UNIQUE,
  meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS EquipmentPorts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipamento_id INT NOT NULL,
  name VARCHAR(32) NOT NULL, -- 'front'|'rear'
  label VARCHAR(100) DEFAULT '',
  meta JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (equipamento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS SensorReadings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entrega_id INT NULL,
  equipamento_id INT NULL,
  port_id INT NULL,
  type VARCHAR(32) NOT NULL, -- 'ultrassom' | 'presence'
  phase VARCHAR(16) NULL,     -- 'before' | 'after'
  value DOUBLE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (entrega_id),
  INDEX (equipamento_id),
  INDEX (port_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adiciona colunas em Entregas (somente se não existirem)
ALTER TABLE Entregas
  ADD COLUMN IF NOT EXISTS equipamento_id INT NULL,
  ADD COLUMN IF NOT EXISTS equipamento_port_id INT NULL,
  ADD COLUMN IF NOT EXISTS volume_before DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS volume_after DOUBLE NULL,
  ADD COLUMN IF NOT EXISTS ultrassom_confirmado TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS resident_token VARCHAR(128) NULL,
  ADD COLUMN IF NOT EXISTS resident_token_expires DATETIME NULL;

-- Observação: alguns servidores MySQL/MariaDB não aceitam ADD COLUMN IF NOT EXISTS combinado com várias colunas em um único ALTER,
-- se ocorrer erro, rode as adições de coluna separadamente.
