<?php
$pdo->exec("CREATE TABLE IF NOT EXISTS aprovacoes_solicitacoes (
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) UNIQUE NULL,
 tipo VARCHAR(20) NOT NULL DEFAULT 'Custo',
 titulo VARCHAR(180) NOT NULL,
 projeto VARCHAR(180) NOT NULL,
 justificativa_tecnica TEXT NOT NULL,
 valor_estimado DECIMAL(14,2) NOT NULL DEFAULT 0,
 valor_final_praticado DECIMAL(14,2) NULL,
 quadro_cotacoes TEXT NULL,
 fornecedor_nome VARCHAR(180) NULL,
 solicitado_por VARCHAR(120) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'Pendente',
 aprovado_por VARCHAR(120) NULL,
 aprovado_em DATETIME NULL,
 guia_codigo VARCHAR(40) NULL,
 guia_emitida_em DATETIME NULL,
 anexo_solicitacao VARCHAR(255) NULL,
 anexo_guia_assinada VARCHAR(255) NULL,
 anexo_contraprova VARCHAR(255) NULL,
 contraprova_por VARCHAR(120) NULL,
 contraprova_em DATETIME NULL,
 observacoes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS aprovacoes_eventos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 solicitacao_id INT NOT NULL,
 evento VARCHAR(120) NOT NULL,
 detalhe TEXT NULL,
 usuario VARCHAR(120) NULL,
 criado_em DATETIME NOT NULL,
 INDEX idx_aprov_sid (solicitacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
