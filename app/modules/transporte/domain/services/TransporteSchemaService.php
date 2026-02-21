<?php

final class TransporteSchemaService
{
    private function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("SHOW TABLES LIKE :t");
        $st->execute([':t' => $table]);
        return (bool) $st->fetchColumn();
    }

    private function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!$this->tableExists($pdo, $table)) {
            return;
        }
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");
        $stmt->execute([':column' => $column]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    public function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transporte_pedidos_reparacao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL,
                viatura_id VARCHAR(120) NULL,
                matricula VARCHAR(50) NULL,
                condutor VARCHAR(120) NULL,
                km_atual INT NULL,
                localizacao VARCHAR(255) NULL,
                avaria_reportada TEXT NULL,
                prioridade VARCHAR(30) NOT NULL DEFAULT 'Media',
                solicitante VARCHAR(120) NULL,
                pdf_anexo VARCHAR(255) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'Enviado para Oficina',
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transporte_reservas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(30) NOT NULL,
                departamento VARCHAR(120) NOT NULL,
                solicitante VARCHAR(120) NULL,
                tipo_ativo VARCHAR(40) NOT NULL DEFAULT 'Viatura',
                viatura_id VARCHAR(120) NULL,
                destino VARCHAR(255) NOT NULL,
                data_partida DATETIME NOT NULL,
                data_retorno DATETIME NULL,
                atividade TEXT NOT NULL,
                urgencia VARCHAR(20) NOT NULL DEFAULT 'Media',
                status VARCHAR(40) NOT NULL DEFAULT 'Pendente Alinhamento',
                alinhado_por VARCHAR(120) NULL,
                alinhado_em DATETIME NULL,
                observacoes TEXT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->ensureColumnExists($pdo, 'transporte_reservas', 'horas_antecedencia', "decimal(10,2) NULL AFTER `urgencia`");
        $this->ensureColumnExists($pdo, 'transporte_reservas', 'urgencia_calculada_em', "datetime NULL AFTER `horas_antecedencia`");
        $this->ensureColumnExists($pdo, 'transporte_reservas', 'ordem_servico_id', "int NULL AFTER `status`");
        $this->ensureColumnExists($pdo, 'transporte_reservas', 'aprovado_por', "varchar(120) NULL AFTER `ordem_servico_id`");
        $this->ensureColumnExists($pdo, 'transporte_reservas', 'aprovado_em', "datetime NULL AFTER `aprovado_por`");

        $this->ensureColumnExists($pdo, 'transporte_guias', 'local_saida', "varchar(255) NULL AFTER `condutor`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'matricula', "varchar(60) NULL AFTER `viatura_id`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'empresa_cliente', "varchar(180) NULL AFTER `destino`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'distancia_km', "decimal(10,2) NULL AFTER `km_chegada`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'tipo_equipamento', "varchar(100) NULL AFTER `viatura_id`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'consumo_l_100km', "decimal(10,2) NULL AFTER `tipo_equipamento`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'projeto', "varchar(120) NULL AFTER `destino`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'abastecido_por', "varchar(120) NULL AFTER `autorizado_por`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'abastecido_em', "datetime NULL AFTER `abastecido_por`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'abastecimento_rejeitado_por', "varchar(120) NULL AFTER `abastecido_em`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'abastecimento_rejeitado_motivo', "text NULL AFTER `abastecimento_rejeitado_por`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'abastecimento_rejeitado_em', "datetime NULL AFTER `abastecimento_rejeitado_motivo`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'hora_saida', "time NULL AFTER `data_saida`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'hora_chegada', "time NULL AFTER `hora_saida`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'tipo_servico', "varchar(40) NULL AFTER `atividade_prevista`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'servico_inicio', "time NULL AFTER `tipo_servico`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'servico_fim', "time NULL AFTER `servico_inicio`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'quantidade_inicial_l', "decimal(10,2) NULL AFTER `km_chegada`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'pedido_abastecimento', "tinyint(1) NOT NULL DEFAULT 0 AFTER `quantidade_inicial_l`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'pedido_abastecimento_por', "varchar(120) NULL AFTER `pedido_abastecimento`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'pedido_abastecimento_em', "datetime NULL AFTER `pedido_abastecimento_por`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'pedido_abastecimento_obs', "text NULL AFTER `pedido_abastecimento_em`");
        $this->ensureColumnExists($pdo, 'transporte_guias', 'pedido_abastecimento_qtd_l', "decimal(10,2) NULL AFTER `pedido_abastecimento_obs`");

        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'origem_mapa', "varchar(255) NULL AFTER `media_esperada`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'destino_mapa', "varchar(255) NULL AFTER `origem_mapa`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'distancia_mapa_km', "decimal(10,2) NULL AFTER `destino_mapa`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'tempo_mapa_min', "decimal(10,2) NULL AFTER `distancia_mapa_km`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'litros_recomendados', "decimal(10,2) NULL AFTER `distancia_mapa_km`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'distancia_utilizada_km', "decimal(10,2) NULL AFTER `litros_recomendados`");
        $this->ensureColumnExists($pdo, 'transporte_combustivel', 'justificativa_alerta', "text NULL AFTER `alerta_fraude`");
    }
}
