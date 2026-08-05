-- migrations.sql
-- Execute para criar o schema mínimo necessário no MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS gaveta_inteligente CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gaveta_inteligente;

CREATE TABLE IF NOT EXISTS Condominio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  cep VARCHAR(20),
  endereco VARCHAR(512),
  whatsapp_sindico VARCHAR(50),
  prazo_retirada_horas INT DEFAULT 24,
  latitude DOUBLE DEFAULT 0,
  longitude DOUBLE DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Apartamentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  condominio_id INT NOT NULL,
  numero VARCHAR(50),
  bloco VARCHAR(50),
  nome_morador VARCHAR(255),
  whatsapp_morador VARCHAR(50),
  latitude DOUBLE DEFAULT 0,
  longitude DOUBLE DEFAULT 0,
  FOREIGN KEY (condominio_id) REFERENCES Condominio(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Logistica (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  logo_path VARCHAR(512)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Entregas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  apartamento_id INT NOT NULL,
  empresa_logistica VARCHAR(255),
  status_entrega VARCHAR(32) DEFAULT 'pendente',
  data_deposito DATETIME DEFAULT CURRENT_TIMESTAMP,
  qr_code_retirada VARCHAR(255),
  FOREIGN KEY (apartamento_id) REFERENCES Apartamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
