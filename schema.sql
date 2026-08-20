CREATE DATABASE IF NOT EXISTS agenda_medica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agenda_medica;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('administrador', 'atendente') NOT NULL DEFAULT 'atendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS medicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(140) NOT NULL,
    crm VARCHAR(40) NOT NULL UNIQUE,
    especialidade VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS escalas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medico_id INT NOT NULL,
    data_escala DATE NOT NULL,
    turno VARCHAR(60) NOT NULL,
    local_consultorio VARCHAR(120) NOT NULL,
    vagas_disponiveis INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medico_id) REFERENCES medicos(id)
);

CREATE TABLE IF NOT EXISTS pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(140) NOT NULL,
    idade INT NOT NULL,
    documento VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escala_id INT NOT NULL,
    paciente_id INT NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escala_id) REFERENCES escalas(id),
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
);

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    entity_type ENUM('escala', 'agendamento') NOT NULL,
    entity_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO users (name, username, password_hash, role)
VALUES ('Administrador', 'admin', '$2y$10$P8UUEvAj2MgNlEDHh7L8u.aPZleTuCaujt3ckUsU5MnBkB1fKfR6O', 'administrador')
ON DUPLICATE KEY UPDATE name = VALUES(name);
