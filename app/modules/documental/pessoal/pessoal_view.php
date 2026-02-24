<?php
$pessoal_lista = [];
$erro_pessoal = null;
$msg_pessoal = null;
$erro_add_colaborador = null;
$erro_update_colaborador = null;

$perfil_filtro = trim((string)($_GET['perfil'] ?? ''));
$pesquisa = trim((string)($_GET['q'] ?? ''));
$cargo_filtro = trim((string)($_GET['cargo'] ?? 'todos'));
$aplicar_filtro = isset($_GET['aplicar']) && (string)$_GET['aplicar'] === '1';

$cargos_por_perfil = [
    'oficina' => [
        'Electricista Auto',
        'Pintor Auto',
        'Mecanico',
        'Ajudante Mecanico',
        'Gestor da Oficina',
    ],
    'transporte' => [
        'Motorista',
        'Operador de Maquinas/Motorista',
        'Operador de Maquinas',
        'Ajudante Camiao',
        'Motorista Mini Bus',
        'Gestor de Transporte',
        'Riger',
    ],
];
$documentos_obrigatorios = [
    'doc_bi' => 'Bilhete de identidade',
    'doc_carta' => 'Carta de conducao',
    'doc_formacao' => 'Certificado de formacao profissional',
    'doc_competencias' => 'Certificado de copetencias',
    'doc_defensiva' => 'Conducao defensiva',
    'doc_foto' => 'Foto meio corpo',
    'doc_inducao' => 'Inducao global',
    'doc_medicals' => 'Medicals',
];
$datas_validade_documentos = [];
$datas_validade_documentos_edit = [];
$add_form_data = [
    'perfil' => '',
    'novo_numero' => '',
    'novo_nome' => '',
    'novo_cargo_nome' => '',
    'novo_estado' => 'Activo',
    'doc_validade' => [],
];
$edit_form_data = [
    'pessoal_id' => 0,
    'perfil' => '',
    'edit_numero' => '',
    'edit_nome' => '',
    'edit_cargo_nome' => '',
    'edit_estado' => 'Activo',
    'doc_validade' => [],
    'doc_anexo' => [],
];

function normalizarDataValidadeDocumento(?string $data): ?string
{
    $valor = trim((string)$data);
    if ($valor === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
        return $valor;
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor) === 1) {
        $dt = DateTime::createFromFormat('d/m/Y', $valor);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function perfilPorCargoPessoal(string $cargoNome, array $cargosPorPerfil): string
{
    foreach ($cargosPorPerfil as $perfil => $cargos) {
        if (in_array($cargoNome, $cargos, true)) {
            return $perfil;
        }
    }
    return '';
}

if (!isset($cargos_por_perfil[$perfil_filtro])) {
    $perfil_filtro = '';
}

if ($perfil_filtro !== '' && $cargo_filtro !== 'todos' && !in_array($cargo_filtro, $cargos_por_perfil[$perfil_filtro], true)) {
    $cargo_filtro = 'todos';
}

if (isset($_GET['saved_colaborador']) && (string)$_GET['saved_colaborador'] === '1') {
    $msg_pessoal = 'Novo colaborador adicionado com sucesso.';
}
if (isset($_GET['saved_update']) && (string)$_GET['saved_update'] === '1') {
    $msg_pessoal = 'Dados do colaborador atualizados com sucesso.';
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && isset($_POST['acao_pessoal'])
    && (string)$_POST['acao_pessoal'] === 'adicionar_colaborador'
) {
    $perfil_post = trim((string)($_POST['perfil'] ?? ''));
    $nome_novo = trim((string)($_POST['novo_nome'] ?? ''));
    $numero_raw = trim((string)($_POST['novo_numero'] ?? ''));
    $cargo_nome_novo = trim((string)($_POST['novo_cargo_nome'] ?? ''));
    $estado_novo = trim((string)($_POST['novo_estado'] ?? 'Activo'));
    foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
        $datas_validade_documentos[$campoDoc] = trim((string)($_POST[$campoDoc . '_validade'] ?? ''));
    }
    $add_form_data = [
        'perfil' => $perfil_post,
        'novo_numero' => $numero_raw,
        'novo_nome' => $nome_novo,
        'novo_cargo_nome' => $cargo_nome_novo,
        'novo_estado' => $estado_novo,
        'doc_validade' => $datas_validade_documentos,
    ];

    $perfil_filtro = isset($cargos_por_perfil[$perfil_post]) ? $perfil_post : '';
    $aplicar_filtro = true;

    if ($perfil_filtro === '') {
        $erro_add_colaborador = 'Perfil invalido para adicionar colaborador.';
    } elseif ($nome_novo === '') {
        $erro_add_colaborador = 'Informe o nome do colaborador.';
    } elseif (!in_array($cargo_nome_novo, $cargos_por_perfil[$perfil_filtro], true)) {
        $erro_add_colaborador = 'Cargo invalido para o perfil selecionado.';
    } else {
        try {
            $stmtCargo = $pdo->prepare("SELECT id FROM cargos WHERE nome = :nome LIMIT 1");
            $stmtCargo->execute([':nome' => $cargo_nome_novo]);
            $cargo_id_novo = (int)($stmtCargo->fetchColumn() ?: 0);

            if ($cargo_id_novo <= 0) {
                throw new RuntimeException('Cargo nao encontrado. Registe primeiro este cargo.');
            }

            $numero_novo = null;
            if ($numero_raw !== '') {
                if (!preg_match('/^\d+$/', $numero_raw)) {
                    throw new RuntimeException('Numero invalido. Use apenas digitos.');
                }
                $numero_novo = (int)$numero_raw;
            }

            if (!in_array($estado_novo, ['Activo', 'Inactivo'], true)) {
                $estado_novo = 'Activo';
            }

            foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
                if (!isset($_FILES[$campoDoc]) || (int)($_FILES[$campoDoc]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Anexe o documento obrigatorio: ' . $nomeDoc . '.');
                }

                $validadeNormalizada = normalizarDataValidadeDocumento($datas_validade_documentos[$campoDoc] ?? '');
                if ($validadeNormalizada === null) {
                    throw new RuntimeException('Informe a validade do documento: ' . $nomeDoc . ' (dd/mm/aaaa).');
                }
            }

            $stmtIns = $pdo->prepare("
                INSERT INTO pessoal (numero, nome, cargo_id, estado)
                VALUES (:numero, :nome, :cargo_id, :estado)
            ");
            $stmtIns->bindValue(':numero', $numero_novo, $numero_novo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmtIns->bindValue(':nome', $nome_novo, PDO::PARAM_STR);
            $stmtIns->bindValue(':cargo_id', $cargo_id_novo, PDO::PARAM_INT);
            $stmtIns->bindValue(':estado', $estado_novo, PDO::PARAM_STR);
            $stmtIns->execute();
            $novo_pessoal_id = (int)$pdo->lastInsertId();

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pessoal_documentos_anexos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    pessoal_id INT NOT NULL,
                    tipo_documento VARCHAR(120) NOT NULL,
                    arquivo_path VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pessoal (pessoal_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $baseDir = dirname(__DIR__, 5) . '/uploads/pessoal/documentos/' . $novo_pessoal_id . '/';
            if (!is_dir($baseDir) && !mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
                throw new RuntimeException('Nao foi possivel preparar a pasta de anexos.');
            }

            $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $insDocMeta = $pdo->prepare("
                INSERT INTO pessoal_documentos (pessoal_id, tipo_documento, data_emissao, data_vencimento)
                VALUES (:pessoal_id, :tipo_documento, NULL, :data_vencimento)
            ");
            $insDocAnexo = $pdo->prepare("
                INSERT INTO pessoal_documentos_anexos (pessoal_id, tipo_documento, arquivo_path)
                VALUES (:pessoal_id, :tipo_documento, :arquivo_path)
            ");

            foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
                $info = $_FILES[$campoDoc] ?? null;
                if (!$info || (int)($info['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Falha ao processar: ' . $nomeDoc . '.');
                }

                $nomeOriginal = (string)($info['name'] ?? '');
                $tmp = (string)($info['tmp_name'] ?? '');
                $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                if (!in_array($ext, $extPermitidas, true)) {
                    throw new RuntimeException('Extensao invalida em ' . $nomeDoc . '. Use PDF/JPG/PNG/WEBP.');
                }

                $nomeSeguro = preg_replace('/[^a-z0-9]+/i', '_', strtolower($nomeDoc));
                $fileName = $nomeSeguro . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $destino = $baseDir . $fileName;
                if (!move_uploaded_file($tmp, $destino)) {
                    throw new RuntimeException('Nao foi possivel guardar o anexo: ' . $nomeDoc . '.');
                }

                $relPath = 'uploads/pessoal/documentos/' . $novo_pessoal_id . '/' . $fileName;
                $validadeNormalizada = normalizarDataValidadeDocumento($datas_validade_documentos[$campoDoc] ?? '');
                if ($validadeNormalizada === null) {
                    throw new RuntimeException('Validade invalida para: ' . $nomeDoc . '.');
                }
                $insDocMeta->execute([
                    ':pessoal_id' => $novo_pessoal_id,
                    ':tipo_documento' => $nomeDoc,
                    ':data_vencimento' => $validadeNormalizada,
                ]);
                $insDocAnexo->execute([
                    ':pessoal_id' => $novo_pessoal_id,
                    ':tipo_documento' => $nomeDoc,
                    ':arquivo_path' => $relPath,
                ]);
            }

            if (function_exists('registrarAuditoria')) {
                registrarAuditoria($pdo, 'Inseriu colaborador no documental pessoal', 'pessoal');
            }

            header('Location: ?view=pessoal&perfil=' . urlencode($perfil_filtro) . '&aplicar=1&saved_colaborador=1');
            exit;
        } catch (Throwable $e) {
            $erro_add_colaborador = $e->getMessage();
        }
    }
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
    && isset($_POST['acao_pessoal'])
    && (string)$_POST['acao_pessoal'] === 'atualizar_colaborador'
) {
    $pessoalIdEdit = (int)($_POST['pessoal_id_edit'] ?? 0);
    $perfil_post = trim((string)($_POST['perfil'] ?? ''));
    $nome_edit = trim((string)($_POST['edit_nome'] ?? ''));
    $numero_raw_edit = trim((string)($_POST['edit_numero'] ?? ''));
    $cargo_nome_edit = trim((string)($_POST['edit_cargo_nome'] ?? ''));
    $estado_edit = trim((string)($_POST['edit_estado'] ?? 'Activo'));
    foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
        $datas_validade_documentos_edit[$campoDoc] = trim((string)($_POST['edit_' . $campoDoc . '_validade'] ?? ''));
    }
    $edit_form_data = [
        'pessoal_id' => $pessoalIdEdit,
        'perfil' => $perfil_post,
        'edit_numero' => $numero_raw_edit,
        'edit_nome' => $nome_edit,
        'edit_cargo_nome' => $cargo_nome_edit,
        'edit_estado' => $estado_edit,
        'doc_validade' => $datas_validade_documentos_edit,
        'doc_anexo' => [],
    ];

    $perfil_filtro = isset($cargos_por_perfil[$perfil_post]) ? $perfil_post : '';
    $aplicar_filtro = true;

    if ($pessoalIdEdit <= 0) {
        $erro_update_colaborador = 'Colaborador invalido para atualizacao.';
    } elseif ($perfil_filtro === '') {
        $erro_update_colaborador = 'Perfil invalido para atualizar colaborador.';
    } elseif ($nome_edit === '') {
        $erro_update_colaborador = 'Informe o nome do colaborador.';
    } elseif (!in_array($cargo_nome_edit, $cargos_por_perfil[$perfil_filtro], true)) {
        $erro_update_colaborador = 'Cargo invalido para o perfil selecionado.';
    } else {
        try {
            $stmtExiste = $pdo->prepare("SELECT id FROM pessoal WHERE id = :id LIMIT 1");
            $stmtExiste->execute([':id' => $pessoalIdEdit]);
            if (!(int)($stmtExiste->fetchColumn() ?: 0)) {
                throw new RuntimeException('Colaborador nao encontrado.');
            }

            $stmtCargo = $pdo->prepare("SELECT id FROM cargos WHERE nome = :nome LIMIT 1");
            $stmtCargo->execute([':nome' => $cargo_nome_edit]);
            $cargo_id_edit = (int)($stmtCargo->fetchColumn() ?: 0);
            if ($cargo_id_edit <= 0) {
                throw new RuntimeException('Cargo nao encontrado.');
            }

            $numero_edit = null;
            if ($numero_raw_edit !== '') {
                if (!preg_match('/^\d+$/', $numero_raw_edit)) {
                    throw new RuntimeException('Numero invalido. Use apenas digitos.');
                }
                $numero_edit = (int)$numero_raw_edit;
            }

            if (!in_array($estado_edit, ['Activo', 'Inactivo'], true)) {
                $estado_edit = 'Activo';
            }

            $updPessoal = $pdo->prepare("
                UPDATE pessoal
                   SET numero = :numero,
                       nome = :nome,
                       cargo_id = :cargo_id,
                       estado = :estado
                 WHERE id = :id
            ");
            $updPessoal->bindValue(':numero', $numero_edit, $numero_edit === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $updPessoal->bindValue(':nome', $nome_edit, PDO::PARAM_STR);
            $updPessoal->bindValue(':cargo_id', $cargo_id_edit, PDO::PARAM_INT);
            $updPessoal->bindValue(':estado', $estado_edit, PDO::PARAM_STR);
            $updPessoal->bindValue(':id', $pessoalIdEdit, PDO::PARAM_INT);
            $updPessoal->execute();

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pessoal_documentos_anexos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    pessoal_id INT NOT NULL,
                    tipo_documento VARCHAR(120) NOT NULL,
                    arquivo_path VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pessoal (pessoal_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $baseDir = dirname(__DIR__, 5) . '/uploads/pessoal/documentos/' . $pessoalIdEdit . '/';
            if (!is_dir($baseDir) && !mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
                throw new RuntimeException('Nao foi possivel preparar a pasta de anexos.');
            }

            $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
            $selDoc = $pdo->prepare("
                SELECT id, data_vencimento FROM pessoal_documentos
                 WHERE pessoal_id = :pessoal_id AND tipo_documento = :tipo_documento
                 ORDER BY id ASC
                 LIMIT 1
            ");
            $updDoc = $pdo->prepare("
                UPDATE pessoal_documentos
                   SET data_vencimento = :data_vencimento
                 WHERE id = :id
            ");
            $insDoc = $pdo->prepare("
                INSERT INTO pessoal_documentos (pessoal_id, tipo_documento, data_emissao, data_vencimento)
                VALUES (:pessoal_id, :tipo_documento, NULL, :data_vencimento)
            ");
            $insDocAnexo = $pdo->prepare("
                INSERT INTO pessoal_documentos_anexos (pessoal_id, tipo_documento, arquivo_path)
                VALUES (:pessoal_id, :tipo_documento, :arquivo_path)
            ");

            foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
                $validadeInput = trim((string)($datas_validade_documentos_edit[$campoDoc] ?? ''));
                $validadeNormalizada = null;
                if ($validadeInput !== '') {
                    $validadeNormalizada = normalizarDataValidadeDocumento($validadeInput);
                    if ($validadeNormalizada === null) {
                        throw new RuntimeException('Validade invalida para: ' . $nomeDoc . ' (dd/mm/aaaa).');
                    }
                }

                $selDoc->execute([
                    ':pessoal_id' => $pessoalIdEdit,
                    ':tipo_documento' => $nomeDoc,
                ]);
                $docRow = $selDoc->fetch(PDO::FETCH_ASSOC) ?: null;
                $docId = (int)($docRow['id'] ?? 0);
                if ($docId > 0) {
                    if ($validadeNormalizada !== null) {
                        $updDoc->execute([
                            ':data_vencimento' => $validadeNormalizada,
                            ':id' => $docId,
                        ]);
                    }
                }

                $campoArquivoEdit = 'edit_' . $campoDoc;
                $info = $_FILES[$campoArquivoEdit] ?? null;
                $temNovoAnexo = $info && (int)($info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

                if ($docId <= 0 && ($validadeNormalizada !== null || $temNovoAnexo)) {
                    $insDoc->execute([
                        ':pessoal_id' => $pessoalIdEdit,
                        ':tipo_documento' => $nomeDoc,
                        ':data_vencimento' => $validadeNormalizada,
                    ]);
                }

                if ($temNovoAnexo) {
                    $nomeOriginal = (string)($info['name'] ?? '');
                    $tmp = (string)($info['tmp_name'] ?? '');
                    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                    if (!in_array($ext, $extPermitidas, true)) {
                        throw new RuntimeException('Extensao invalida em ' . $nomeDoc . '. Use PDF/JPG/PNG/WEBP.');
                    }

                    $nomeSeguro = preg_replace('/[^a-z0-9]+/i', '_', strtolower($nomeDoc));
                    $fileName = $nomeSeguro . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $destino = $baseDir . $fileName;
                    if (!move_uploaded_file($tmp, $destino)) {
                        throw new RuntimeException('Nao foi possivel guardar o anexo: ' . $nomeDoc . '.');
                    }

                    $relPath = 'uploads/pessoal/documentos/' . $pessoalIdEdit . '/' . $fileName;
                    $insDocAnexo->execute([
                        ':pessoal_id' => $pessoalIdEdit,
                        ':tipo_documento' => $nomeDoc,
                        ':arquivo_path' => $relPath,
                    ]);
                }
            }

            if (function_exists('registrarAuditoria')) {
                registrarAuditoria($pdo, 'Atualizou colaborador no documental pessoal', 'pessoal');
            }

            header('Location: ?view=pessoal&perfil=' . urlencode($perfil_filtro) . '&aplicar=1&saved_update=1');
            exit;
        } catch (Throwable $e) {
            $erro_update_colaborador = $e->getMessage();
        }
    }
}

if ($aplicar_filtro && $perfil_filtro !== '') {
    try {
        $where = [];
        $params = [];

        if ($pesquisa !== '') {
            $where[] = "(p.nome LIKE :pesquisa OR CAST(p.numero AS CHAR) LIKE :pesquisa OR c.nome LIKE :pesquisa OR pd.tipo_documento LIKE :pesquisa)";
            $params[':pesquisa'] = '%' . $pesquisa . '%';
        }

        $cargos_permitidos = $cargos_por_perfil[$perfil_filtro];
        if (!empty($cargos_permitidos)) {
            $placeholders = [];
            foreach ($cargos_permitidos as $idx => $cargo_nome) {
                $ph = ':cargo_perfil_' . $idx;
                $placeholders[] = $ph;
                $params[$ph] = $cargo_nome;
            }
            $where[] = 'c.nome IN (' . implode(', ', $placeholders) . ')';
        }

        if ($cargo_filtro !== '' && $cargo_filtro !== 'todos') {
            $where[] = 'c.nome = :cargo_especifico';
            $params[':cargo_especifico'] = $cargo_filtro;
        }

        $sql = "
            SELECT
                p.id AS pessoal_id,
                p.numero,
                p.nome,
                c.nome AS cargo_nome,
                p.estado,
                pd.tipo_documento,
                pd.data_emissao,
                pd.data_vencimento,
                pd.created_at AS documento_created_at
            FROM pessoal p
            INNER JOIN (
                SELECT
                    COALESCE(NULLIF(numero, 0), id) AS chave_colaborador,
                    MAX(id) AS pessoal_id_recente
                FROM pessoal
                GROUP BY COALESCE(NULLIF(numero, 0), id)
            ) pu ON pu.pessoal_id_recente = p.id
            LEFT JOIN cargos c
                ON c.id = p.cargo_id
            LEFT JOIN pessoal_documentos pd
                ON pd.id = (
                    SELECT pd2.id
                    FROM pessoal_documentos pd2
                    INNER JOIN pessoal p2 ON p2.id = pd2.pessoal_id
                    WHERE COALESCE(NULLIF(p2.numero, 0), p2.id) = COALESCE(NULLIF(p.numero, 0), p.id)
                    ORDER BY pd2.created_at DESC, pd2.id DESC
                    LIMIT 1
                )
        ";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.nome ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pessoal_lista = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro_pessoal = 'Nao foi possivel carregar os funcionarios.';
    }
}

$edit_pessoal_id = (int)($_GET['edit_pessoal_id'] ?? 0);
$perfil_edicao_atual = '';
if ($edit_pessoal_id > 0 && $erro_update_colaborador === null) {
    try {
        $stEdit = $pdo->prepare("
            SELECT p.id, p.numero, p.nome, p.estado, c.nome AS cargo_nome
              FROM pessoal p
              LEFT JOIN cargos c ON c.id = p.cargo_id
             WHERE p.id = :id
             LIMIT 1
        ");
        $stEdit->execute([':id' => $edit_pessoal_id]);
        $rowEdit = $stEdit->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($rowEdit) {
            $perfil_edicao_atual = perfilPorCargoPessoal((string)($rowEdit['cargo_nome'] ?? ''), $cargos_por_perfil);
            $perfil_alvo_edit = $perfil_edicao_atual !== '' ? $perfil_edicao_atual : $perfil_filtro;

            $docsValidade = [];
            $docsAnexo = [];
            $stDocs = $pdo->prepare("
                SELECT tipo_documento, data_vencimento
                  FROM pessoal_documentos
                 WHERE pessoal_id = :id
            ");
            $stDocs->execute([':id' => $edit_pessoal_id]);
            $rowsDocs = $stDocs->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rowsDocs as $rd) {
                $tipo = trim((string)($rd['tipo_documento'] ?? ''));
                $venc = trim((string)($rd['data_vencimento'] ?? ''));
                if ($tipo !== '') {
                    $docsValidade[$tipo] = $venc;
                }
            }
            $stAnexos = $pdo->prepare("
                SELECT tipo_documento, arquivo_path
                  FROM pessoal_documentos_anexos
                 WHERE pessoal_id = :id
                 ORDER BY id DESC
            ");
            $stAnexos->execute([':id' => $edit_pessoal_id]);
            $rowsAnexos = $stAnexos->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rowsAnexos as $ra) {
                $tipo = trim((string)($ra['tipo_documento'] ?? ''));
                $arq = trim((string)($ra['arquivo_path'] ?? ''));
                if ($tipo !== '' && $arq !== '' && !isset($docsAnexo[$tipo])) {
                    $docsAnexo[$tipo] = basename($arq);
                }
            }

            $mapCampoPorNome = array_flip($documentos_obrigatorios);
            $docValores = [];
            foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc) {
                $docValores[$campoDoc] = $docsValidade[$nomeDoc] ?? '';
            }

            $edit_form_data = [
                'pessoal_id' => (int)$rowEdit['id'],
                'perfil' => $perfil_alvo_edit,
                'edit_numero' => (string)($rowEdit['numero'] ?? ''),
                'edit_nome' => (string)($rowEdit['nome'] ?? ''),
                'edit_cargo_nome' => (string)($rowEdit['cargo_nome'] ?? ''),
                'edit_estado' => (string)($rowEdit['estado'] ?? 'Activo'),
                'doc_validade' => $docValores,
                'doc_anexo' => $docsAnexo,
            ];
        }
    } catch (Throwable $e) {
        $erro_update_colaborador = 'Nao foi possivel carregar dados para edicao.';
    }
}

if (($edit_form_data['perfil'] ?? '') !== '' && isset($cargos_por_perfil[$edit_form_data['perfil']])) {
    $perfil_edicao_atual = (string)$edit_form_data['perfil'];
}

function tituloPerfilPessoal(string $perfil): string {
    if ($perfil === 'oficina') {
        return 'Oficina';
    }
    if ($perfil === 'transporte') {
        return 'Transporte';
    }
    return 'Perfil';
}
?>
<div data-mode-scope>
    <style>
        .pessoal-entry {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .btn-entry {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            min-height: 36px;
        }
        .btn-entry[data-open-pessoal-modal="oficina"] {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #93c5fd;
        }
        .btn-entry[data-open-pessoal-modal="transporte"] {
            background: #ffedd5;
            color: #9a3412;
            border-color: #fdba74;
        }
        .pessoal-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1100;
            padding: 22px;
            overflow: auto;
        }
        .pessoal-modal.open {
            display: block;
        }
        .pessoal-modal-window {
            max-width: 1200px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }
        .pessoal-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .pessoal-modal.perfil-oficina .pessoal-modal-header {
            background: #1d4ed8;
            border-bottom-color: #1e40af;
        }
        .pessoal-modal.perfil-transporte .pessoal-modal-header {
            background: #ea580c;
            border-bottom-color: #c2410c;
        }
        .pessoal-modal-header h4 {
            margin: 0;
            font-size: 14px;
            color: #111827;
        }
        .pessoal-modal.perfil-oficina .pessoal-modal-header h4,
        .pessoal-modal.perfil-transporte .pessoal-modal-header h4 {
            color: #ffffff;
        }
        .pessoal-modal-actions {
            display: flex;
            gap: 8px;
        }
        .pessoal-modal-btn {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .pessoal-modal-btn[data-minimizar-modal] {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #92400e;
        }
        .pessoal-modal-btn[data-fechar-modal] {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
        .pessoal-modal-body {
            padding: 14px;
        }
        .pessoal-modal.minimized .pessoal-modal-body {
            display: none;
        }
        .pessoal-tools {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .pessoal-tools .btn-export[data-export-format="excel"] {
            background: #dcfce7;
            border-color: #86efac;
            color: #166534;
        }
        .pessoal-tools .btn-export[data-export-format="pdf"] {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .doc-control {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 8px;
            align-items: center;
        }
        .doc-control input[type="date"] {
            min-width: 0;
        }
        .doc-control input[type="file"] {
            min-width: 0;
            width: 100%;
        }
        .add-colaborador-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 0;
            background: transparent;
            border: none;
        }
        .add-colab-section {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            padding: 12px;
        }
        .add-colab-section-title {
            margin: 0 0 10px 0;
            font-size: 12px;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .add-colab-grid-base {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 10px;
        }
        .add-colab-grid-docs {
            display: grid;
            grid-template-columns: repeat(2, minmax(300px, 1fr));
            gap: 10px;
        }
        .add-colab-doc-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            background: #f8fafc;
        }
        .add-colab-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 2px;
        }
        @media (max-width: 900px) {
            .pessoal-modal {
                padding: 8px;
            }
            .add-colab-grid-base {
                grid-template-columns: 1fr;
            }
            .add-colab-grid-docs {
                grid-template-columns: 1fr;
            }
            .doc-control {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="tool-header">
        <div class="tool-title">
            <h3><i class="fas fa-users"></i> Pessoal</h3>
            <p>Selecione Oficina ou Transporte para abrir a tela de filtragem e lista documental.</p>
        </div>
    </div>

    <div class="pessoal-entry">
        <button type="button" class="btn-entry" data-open-pessoal-modal="oficina"><i class="fas fa-screwdriver-wrench"></i> Oficina</button>
        <button type="button" class="btn-entry" data-open-pessoal-modal="transporte"><i class="fas fa-truck"></i> Transporte</button>
    </div>

    <?php if ($msg_pessoal !== null): ?>
        <div class="filter-container" style="margin-top:8px; background:#ecfdf5; border-color:#86efac; color:#166534;">
            <span style="font-size:12px; font-weight:700;"><?= htmlspecialchars($msg_pessoal) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$aplicar_filtro): ?>
        <div class="filter-container" style="margin-top:8px;">
            <span style="font-size:12px; color:#6b7280;">A lista de funcionarios so aparece apos aplicar os filtros dentro de Oficina ou Transporte.</span>
        </div>
    <?php endif; ?>

    <?php foreach ($cargos_por_perfil as $perfil => $listaCargos): ?>
        <?php
            $modalAberto = ($perfil_filtro === $perfil && $aplicar_filtro);
            $cargoAtual = ($perfil_filtro === $perfil) ? $cargo_filtro : 'todos';
            $pesquisaAtual = ($perfil_filtro === $perfil) ? $pesquisa : '';
            $mostrarLista = ($perfil_filtro === $perfil && $aplicar_filtro);
        ?>
        <div class="pessoal-modal perfil-<?= htmlspecialchars($perfil) ?> <?= $modalAberto ? 'open' : '' ?>" id="pessoal-modal-<?= htmlspecialchars($perfil) ?>">
            <div class="pessoal-modal-window">
                <div class="pessoal-modal-header">
                    <h4>Documental Pessoal - <?= htmlspecialchars(tituloPerfilPessoal($perfil)) ?></h4>
                    <div class="pessoal-modal-actions">
                        <button type="button" class="pessoal-modal-btn" data-minimizar-modal>Minimizar</button>
                        <button type="button" class="pessoal-modal-btn" data-fechar-modal>Fechar</button>
                    </div>
                </div>

                <div class="pessoal-modal-body">
                    <button
                        type="button"
                        class="btn-save"
                        style="background:#111827;"
                        data-open-add-colaborador-modal="pessoal-add-modal-<?= htmlspecialchars($perfil) ?>"
                    ><i class="fas fa-plus"></i> Adicionar novo colaborador</button>

                    <form class="filter-container" method="get" action="" style="margin-top:10px;">
                        <input type="hidden" name="view" value="pessoal">
                        <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfil) ?>">
                        <input type="hidden" name="aplicar" value="1">

                        <div class="form-group" style="flex:1;">
                            <label><i class="fas fa-magnifying-glass"></i> Pesquisa</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($pesquisaAtual) ?>" placeholder="Nome, numero, cargo, tipo documento...">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Cargo</label>
                            <select name="cargo">
                                <option value="todos">Todos os cargos</option>
                                <?php foreach ($listaCargos as $cargoNome): ?>
                                    <option value="<?= htmlspecialchars($cargoNome) ?>" <?= $cargoAtual === $cargoNome ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargoNome) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-save"><i class="fas fa-sliders"></i> Aplicar filtro</button>
                        <a href="?view=pessoal&perfil=<?= urlencode($perfil) ?>" class="btn-save" style="text-decoration:none;display:inline-flex;align-items:center;"><i class="fas fa-rotate-left" style="margin-right:6px;"></i> Limpar</a>
                    </form>

                    <div class="pessoal-tools">
                        <button type="button" class="btn-export" data-export-format="excel">
                            <i class="fas fa-file-excel"></i> Baixar Excel
                        </button>
                        <button type="button" class="btn-export" data-export-format="pdf">
                            <i class="fas fa-file-pdf"></i> Baixar PDF
                        </button>
                    </div>

                    <div class="panel-view <?= $mostrarLista ? '' : 'hidden' ?>">
                        <table class="list-table">
                            <thead>
                                <tr>
                                    <th>Numero</th>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Tipo Documento</th>
                                    <th>Data Emissao</th>
                                    <th>Data Vencimento</th>
                                    <th>Criado Em</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($mostrarLista && $erro_pessoal !== null): ?>
                                    <tr>
                                        <td colspan="8"><?= htmlspecialchars($erro_pessoal) ?></td>
                                    </tr>
                                <?php elseif ($mostrarLista && empty($pessoal_lista)): ?>
                                    <tr>
                                        <td colspan="8">Sem registos para os filtros aplicados.</td>
                                    </tr>
                                <?php elseif ($mostrarLista): ?>
                                    <?php foreach ($pessoal_lista as $item): ?>
                                        <?php
                                        $emissao = trim((string)($item['data_emissao'] ?? ''));
                                        $vencimento = trim((string)($item['data_vencimento'] ?? ''));
                                        $criadoEm = trim((string)($item['documento_created_at'] ?? ''));
                                        $pessoalIdRow = (int)($item['pessoal_id'] ?? 0);
                                        $urlAtualizar = '?view=pessoal&perfil=' . urlencode($perfil) . '&aplicar=1&edit_pessoal_id=' . $pessoalIdRow;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)($item['numero'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['nome'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['cargo_nome'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($item['tipo_documento'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars($emissao !== '' ? $emissao : '-') ?></td>
                                            <td><?= htmlspecialchars($vencimento !== '' ? $vencimento : '-') ?></td>
                                            <td><?= htmlspecialchars($criadoEm !== '' ? $criadoEm : '-') ?></td>
                                            <td>
                                                <?php if ($pessoalIdRow > 0): ?>
                                                    <a href="<?= htmlspecialchars($urlAtualizar) ?>" class="btn-save" style="background:#1d4ed8; text-decoration:none; display:inline-flex; align-items:center;">
                                                        <i class="fas fa-pen-to-square" style="margin-right:6px;"></i> Atualizar dados
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">Aplique os filtros para ver a lista de funcionarios.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php
            $addModalOpen = ($add_form_data['perfil'] === $perfil && $erro_add_colaborador !== null);
        ?>
        <div class="pessoal-modal perfil-<?= htmlspecialchars($perfil) ?> <?= $addModalOpen ? 'open' : '' ?>" id="pessoal-add-modal-<?= htmlspecialchars($perfil) ?>">
            <div class="pessoal-modal-window" style="max-width:980px;">
                <div class="pessoal-modal-header">
                    <h4>Pessoal - Adicionar</h4>
                    <div class="pessoal-modal-actions">
                        <button type="button" class="pessoal-modal-btn" data-minimizar-modal>Minimizar</button>
                        <button type="button" class="pessoal-modal-btn" data-fechar-modal>Fechar</button>
                    </div>
                </div>
                <div class="pessoal-modal-body">
                    <?php if ($addModalOpen && $erro_add_colaborador !== null): ?>
                        <div class="filter-container" style="margin-bottom:10px; background:#fef2f2; border-color:#fecaca; color:#b91c1c;">
                            <span style="font-size:12px; font-weight:700;"><?= htmlspecialchars($erro_add_colaborador) ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="add-colaborador-form" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="view" value="pessoal">
                        <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfil) ?>">
                        <input type="hidden" name="acao_pessoal" value="adicionar_colaborador">

                        <div class="add-colab-section">
                            <p class="add-colab-section-title">Informacao Base do Colaborador</p>
                            <div class="add-colab-grid-base">
                                <div class="form-group" style="min-width:150px;">
                                    <label>Numero</label>
                                    <input type="number" name="novo_numero" min="1" step="1" placeholder="Opcional" value="<?= htmlspecialchars((string)$add_form_data['novo_numero']) ?>">
                                </div>

                                <div class="form-group" style="min-width:220px;">
                                    <label>Nome</label>
                                    <input type="text" name="novo_nome" required placeholder="Nome do colaborador" value="<?= htmlspecialchars((string)$add_form_data['novo_nome']) ?>">
                                </div>

                                <div class="form-group" style="min-width:220px;">
                                    <label>Cargo</label>
                                    <select name="novo_cargo_nome" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($listaCargos as $cargoNome): ?>
                                            <option value="<?= htmlspecialchars($cargoNome) ?>" <?= (string)$add_form_data['novo_cargo_nome'] === $cargoNome ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cargoNome) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="min-width:140px;">
                                    <label>Estado</label>
                                    <select name="novo_estado">
                                        <option value="Activo" <?= (string)$add_form_data['novo_estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                                        <option value="Inactivo" <?= (string)$add_form_data['novo_estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="add-colab-section">
                            <p class="add-colab-section-title">Documentos e Anexos Obrigatorios</p>
                            <div class="add-colab-grid-docs">
                                <?php foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc): ?>
                                    <div class="add-colab-doc-card">
                                        <div class="form-group" style="min-width:0; margin:0;">
                                            <label><?= htmlspecialchars($nomeDoc) ?></label>
                                            <div class="doc-control">
                                                <input
                                                    type="date"
                                                    name="<?= htmlspecialchars($campoDoc) ?>_validade"
                                                    value="<?= htmlspecialchars((string)($add_form_data['doc_validade'][$campoDoc] ?? '')) ?>"
                                                    required
                                                >
                                                <input type="file" name="<?= htmlspecialchars($campoDoc) ?>" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="add-colab-actions">
                            <button type="submit" class="btn-save" style="background:#111827;"><i class="fas fa-save"></i> Salvar Registro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
            $editModalOpen = (
                ((int)($edit_form_data['pessoal_id'] ?? 0) > 0 && $perfil_edicao_atual === $perfil)
                || ($erro_update_colaborador !== null && (string)($edit_form_data['perfil'] ?? '') === $perfil)
            );
        ?>
        <div class="pessoal-modal perfil-<?= htmlspecialchars($perfil) ?> <?= $editModalOpen ? 'open' : '' ?>" id="pessoal-edit-modal-<?= htmlspecialchars($perfil) ?>">
            <div class="pessoal-modal-window" style="max-width:980px;">
                <div class="pessoal-modal-header">
                    <h4>Pessoal - Atualizar</h4>
                    <div class="pessoal-modal-actions">
                        <button type="button" class="pessoal-modal-btn" data-minimizar-modal>Minimizar</button>
                        <button type="button" class="pessoal-modal-btn" data-fechar-modal>Fechar</button>
                    </div>
                </div>
                <div class="pessoal-modal-body">
                    <?php if ($editModalOpen && $erro_update_colaborador !== null): ?>
                        <div class="filter-container" style="margin-bottom:10px; background:#fef2f2; border-color:#fecaca; color:#b91c1c;">
                            <span style="font-size:12px; font-weight:700;"><?= htmlspecialchars($erro_update_colaborador) ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="add-colaborador-form" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="view" value="pessoal">
                        <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfil) ?>">
                        <input type="hidden" name="acao_pessoal" value="atualizar_colaborador">
                        <input type="hidden" name="pessoal_id_edit" value="<?= htmlspecialchars((string)($edit_form_data['pessoal_id'] ?? 0)) ?>">

                        <div class="add-colab-section">
                            <p class="add-colab-section-title">Informacao Base do Colaborador</p>
                            <div class="add-colab-grid-base">
                                <div class="form-group" style="min-width:150px;">
                                    <label>Numero</label>
                                    <input type="number" name="edit_numero" min="1" step="1" placeholder="Opcional" value="<?= htmlspecialchars((string)($edit_form_data['edit_numero'] ?? '')) ?>">
                                </div>

                                <div class="form-group" style="min-width:220px;">
                                    <label>Nome</label>
                                    <input type="text" name="edit_nome" required placeholder="Nome do colaborador" value="<?= htmlspecialchars((string)($edit_form_data['edit_nome'] ?? '')) ?>">
                                </div>

                                <div class="form-group" style="min-width:220px;">
                                    <label>Cargo</label>
                                    <select name="edit_cargo_nome" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($listaCargos as $cargoNome): ?>
                                            <option value="<?= htmlspecialchars($cargoNome) ?>" <?= (string)($edit_form_data['edit_cargo_nome'] ?? '') === $cargoNome ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cargoNome) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" style="min-width:140px;">
                                    <label>Estado</label>
                                    <select name="edit_estado">
                                        <option value="Activo" <?= (string)($edit_form_data['edit_estado'] ?? 'Activo') === 'Activo' ? 'selected' : '' ?>>Activo</option>
                                        <option value="Inactivo" <?= (string)($edit_form_data['edit_estado'] ?? '') === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="add-colab-section">
                            <p class="add-colab-section-title">Documentos e Anexos Obrigatorios</p>
                            <div style="font-size:11px; color:#64748b; margin-bottom:10px;">Para anexos, selecione arquivo apenas se desejar substituir/adicionar novo.</div>
                            <div class="add-colab-grid-docs">
                                <?php foreach ($documentos_obrigatorios as $campoDoc => $nomeDoc): ?>
                                    <div class="add-colab-doc-card">
                                        <div class="form-group" style="min-width:0; margin:0;">
                                            <label><?= htmlspecialchars($nomeDoc) ?></label>
                                            <div class="doc-control">
                                                <input
                                                    type="date"
                                                    name="edit_<?= htmlspecialchars($campoDoc) ?>_validade"
                                                    value="<?= htmlspecialchars((string)(($edit_form_data['doc_validade'][$campoDoc] ?? ''))) ?>"
                                                >
                                                <input type="file" name="edit_<?= htmlspecialchars($campoDoc) ?>" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                            </div>
                                            <div style="margin-top:6px; font-size:11px; color:#64748b;">
                                                <?= !empty($edit_form_data['doc_anexo'][$nomeDoc] ?? '') ? htmlspecialchars((string)$edit_form_data['doc_anexo'][$nomeDoc]) : 'Nenhum arquivo escolhido' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="add-colab-actions">
                            <button type="submit" class="btn-save" style="background:#1d4ed8;"><i class="fas fa-floppy-disk"></i> Salvar Atualizacao</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function() {
    function abrirModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('open');
    }

    function fecharModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.classList.remove('minimized');
    }

    document.querySelectorAll('[data-open-pessoal-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var perfil = btn.getAttribute('data-open-pessoal-modal');
            abrirModal('pessoal-modal-' + perfil);
        });
    });
    document.querySelectorAll('[data-open-add-colaborador-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.getAttribute('data-open-add-colaborador-modal');
            abrirModal(id);
        });
    });

    document.querySelectorAll('[data-fechar-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            fecharModal(btn.closest('.pessoal-modal'));
        });
    });

    document.querySelectorAll('[data-minimizar-modal]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modal = btn.closest('.pessoal-modal');
            if (!modal) return;
            modal.classList.toggle('minimized');
            btn.textContent = modal.classList.contains('minimized') ? 'Restaurar' : 'Minimizar';
        });
    });

    document.querySelectorAll('.pessoal-modal').forEach(function(modal) {
        modal.addEventListener('click', function(ev) {
            if (ev.target === modal) {
                fecharModal(modal);
            }
        });
    });
})();
</script>
