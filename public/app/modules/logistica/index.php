<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: /vilcon-systemon/public/login.php'); exit; }

function norm($s){ return strtolower(trim((string)$s)); }
function money($v){ return number_format((float)$v,2,',','.') . ' MZN'; }
function st($s){ $s=norm($s); if($s==='aprovado')$s='aprovada'; if($s==='negado')$s='negada'; return in_array($s,['pendente','aprovada','negada','em transito','entregue','cancelada'],true)?$s:'pendente'; }
function stLabel($s){ $s=st($s); if($s==='em transito') return 'Em transito'; return ucfirst($s); }
function badge($s){ $s=norm($s); if(in_array($s,['aprovada','entregue','ativo'],true)) return 'ok'; if(in_array($s,['negada','cancelada','inativo'],true)) return 'danger'; return ($s==='em transito')?'info':'warn'; }
function modalidadeFornecedor($s): string { return norm($s) === 'credito' ? 'Credito' : 'Normal'; }
function modalidadeFornecedorLabel($s): string { return modalidadeFornecedor($s) === 'Credito' ? 'A credito' : 'Normal'; }
function modalidadeFornecedorBadge($s): string { return modalidadeFornecedor($s) === 'Credito' ? 'warn' : 'info'; }
function prioridadeCategoria($s){
  $p = norm($s);
  if(in_array($p,['urgente','alta','alto'],true)) return 'urgente';
  if(in_array($p,['baixo','baixa'],true)) return 'baixo';
  return 'medio';
}
function prioridadeLabel($s){
  $c = prioridadeCategoria($s);
  if($c==='urgente') return 'Urgente';
  if($c==='baixo') return 'Baixo';
  return 'Medio';
}
function departamentoCanonico($s){
  $d = norm($s);
  if(in_array($d, ['oficina','transporte'], true)) return $d;
  return 'oficina';
}
function projectoCanonico($s): string {
  $p = norm($s);
  if(in_array($p, ['projecto oficina','oficina'], true)) return 'Projecto Oficina';
  if(in_array($p, ['projecto transporte','transporte'], true)) return 'Projecto Transporte';
  return '';
}
function departamentoPorProjecto(string $projecto): string {
  return projectoCanonico($projecto) === 'Projecto Transporte' ? 'transporte' : 'oficina';
}
function budjetDepartamentoBloqueado(PDO $pdo, string $departamento): bool {
  $dep = departamentoCanonico($departamento);
  $st = $pdo->prepare("SELECT COALESCE(bloqueado,0) FROM logistica_budjet_departamentos WHERE departamento=:d LIMIT 1");
  $st->execute(['d'=>$dep]);
  return ((int)$st->fetchColumn()) === 1;
}
function categoriaRequisicaoCanonica($s): string {
  $c = norm($s);
  if(in_array($c, ['peca','pecas'], true)) return 'Pecas';
  if(in_array($c, ['controle de eps','controle eps','eps','epi'], true)) return 'Controle de EPs';
  if(in_array($c, ['ferramenta','ferramentas'], true)) return 'Ferramentas';
  if($c === 'alimentacao') return 'Alimentacao';
  if(in_array($c, ['custos de portagem','portagem'], true)) return 'Custos de portagem';
  if($c === 'multas') return 'Multas';
  if($c === 'seguros') return 'Seguros';
  if(in_array($c, ['taxas de radios','taxas radios','radios'], true)) return 'Taxas de radios';
  if($c === 'extintores') return 'Extintores';
  if(in_array($c, ['manutencao','manutencoes'], true)) return 'Manutencoes';
  return '';
}
function salvarUploadDocumento(string $campo, string $subpasta = 'financeiro'): ?string {
  if(!isset($_FILES[$campo])) return null;
  $err = (int)($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE);
  if($err === UPLOAD_ERR_NO_FILE) return null;
  if($err !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao anexar documento.');
  $tmp = (string)($_FILES[$campo]['tmp_name'] ?? '');
  $orig = (string)($_FILES[$campo]['name'] ?? '');
  $ext = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));
  $permit = ['pdf','png','jpg','jpeg','doc','docx','xls','xlsx'];
  if(!in_array($ext, $permit, true)) throw new RuntimeException('Formato de anexo nao permitido.');
  $dirFs = dirname(__DIR__, 3) . '/uploads/logistica/' . trim($subpasta, '/');
  if(!is_dir($dirFs) && !@mkdir($dirFs, 0775, true)) throw new RuntimeException('Nao foi possivel criar pasta de anexos.');
  $file = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $destFs = $dirFs . '/' . $file;
  if(!@move_uploaded_file($tmp, $destFs)) throw new RuntimeException('Nao foi possivel guardar documento anexado.');
  return '/vilcon-systemon/public/uploads/logistica/' . trim($subpasta, '/') . '/' . $file;
}
function salvarUploadDocumentoIndice(string $campo, int $idx, string $subpasta = 'financeiro'): ?string {
  if(!isset($_FILES[$campo])) return null;
  $erros = $_FILES[$campo]['error'] ?? null;
  $tmps = $_FILES[$campo]['tmp_name'] ?? null;
  $nomes = $_FILES[$campo]['name'] ?? null;
  if(!is_array($erros) || !is_array($tmps) || !is_array($nomes)) return null;
  $err = (int)($erros[$idx] ?? UPLOAD_ERR_NO_FILE);
  if($err === UPLOAD_ERR_NO_FILE) return null;
  if($err !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao anexar cotacao.');
  $tmp = (string)($tmps[$idx] ?? '');
  $orig = (string)($nomes[$idx] ?? '');
  if($tmp === '') return null;
  $ext = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));
  $permit = ['pdf','png','jpg','jpeg','doc','docx','xls','xlsx'];
  if(!in_array($ext, $permit, true)) throw new RuntimeException('Formato de anexo de cotacao nao permitido.');
  $dirFs = dirname(__DIR__, 3) . '/uploads/logistica/' . trim($subpasta, '/');
  if(!is_dir($dirFs) && !@mkdir($dirFs, 0775, true)) throw new RuntimeException('Nao foi possivel criar pasta de anexos.');
  $file = 'cot_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $destFs = $dirFs . '/' . $file;
  if(!@move_uploaded_file($tmp, $destFs)) throw new RuntimeException('Nao foi possivel guardar anexo da cotacao.');
  return '/vilcon-systemon/public/uploads/logistica/' . trim($subpasta, '/') . '/' . $file;
}
function seedBudjetOficinaTemplate(PDO $pdo): void {
  $existe = $pdo->prepare("SELECT COUNT(*) FROM logistica_budjet_itens WHERE departamento='oficina'");
  $existe->execute();
  if((int)$existe->fetchColumn() > 0) return;

  $itens = [
    ['COMBUSTIVEIS E LUBRIFICANTES',1,'Massa de Grease Kg.','Kg',1000,10,426000.00,421740.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',2,'Oleo Hidraulico','L',1000,84,300000.00,280344.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',3,'PETROLEO','L',500,0,60000.00,60000.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',4,'Oleo 10','L',500,0,114500.00,114500.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',5,'Oleo 90','L',500,2,160000.00,159600.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',6,'Oleo de Caixa','L',500,0,150000.00,150000.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',7,'Oleo dos Travoes','L',500,0,150000.00,150000.00],
    ['CUSTO DO PESSOAL',1,'Salario mensal do pessoal Quadro','Mensal',12,0,2580000.00,2580000.00],
    ['DESPESAS ADMINISTRATIVAS',1,'Despesa de energia (EDM)','Mensal',12,0,120000.00,120000.00],
    ['DESPESAS ADMINISTRATIVAS',2,'RECARGAS PARA COLABORADORES','Un',12,0,36000.00,36000.00],
    ['DESPESAS ADMINISTRATIVAS',3,'Despesa de internet','Mensal',12,0,36000.00,36000.00],
    ['DESPESAS ADMINISTRATIVAS',4,'RENDA DE CASA','MT',12,0,420000.00,420000.00],
    ['MEIOS DE TRANSPORTE',1,'Despesas de manutencao de veiculos','Vg',12,0,90000.00,90000.00],
    ['ALUGUER DE EQUIPAMENTOS',1,'Aluguer de Viatura Ligeira 4X4','Dias',12,0,720000.00,720000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',1,'Uniforme EPI','Un',30,0,54000.00,54000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',2,'BOTA DE TRABALHO C/PALMILHA DE ACO BASIX','Pr',30,0,50400.00,50400.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',3,'LUVA CABEDAL MANGA CURTA Mascara de Proteccao com Filtro','Un',30,0,9600.00,9600.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',5,'MASCARA DE PAPEL','Un',100,0,6257.00,6257.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',6,'OCULO DE PROTECCAO TRANSPARENTE','Un',300,0,36000.00,36000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',7,'CAPACETE DE PROTECAO AZUL','Un',30,0,8550.00,8550.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',8,'CAPACETE DE PROTECAO BRANCO','Un',10,0,3120.00,3120.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',9,'PROTECTOR DE OUVIDOS','Un',300,0,26154.00,26154.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',10,'LUVAS SOFT GLOVES','Par',200,0,166326.00,166326.00],
    ['DESPESAS DE FUNCIONAMENTO DO PROJECTO',1,'Despesas de alimentacao','MT',12,0,529145.04,529145.04],
    ['COMBUSTIVEIS E LUBRIFICANTES',8,'Oleo de Motor.','Un',500,49,110000.00,100093.18],
    ['REPARACAO DE MAQUINAS E VIATURAS',1,'Custos varios','Vg',1,22,500000.00,492170.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',9,'DIESEL.','Lt',2000,0,180100.00,142841.38],
    ['COMBUSTIVEIS E LUBRIFICANTES',10,'GASOLINA','Lt',500,0,45520.00,45520.00],
  ];

  $ins = $pdo->prepare("
    INSERT INTO logistica_budjet_itens
    (departamento,categoria,ordem_item,descricao,unidade,qtd_planeada,qtd_actual,orcamento_compra,saldo_pendente,preco_unitario)
    VALUES
    (:d,:c,:o,:de,:u,:qp,:qa,:oc,:sp,:pu)
  ");
  foreach($itens as $it){
    $qtdPlaneada = (float)$it[4];
    $orcamentoCompra = (float)$it[6];
    $precoUnit = $qtdPlaneada > 0 ? ($orcamentoCompra / $qtdPlaneada) : 0;
    $ins->execute([
      'd' => 'oficina',
      'c' => (string)$it[0],
      'o' => (int)$it[1],
      'de' => (string)$it[2],
      'u' => (string)$it[3],
      'qp' => $qtdPlaneada,
      'qa' => (float)$it[5],
      'oc' => $orcamentoCompra,
      'sp' => (float)$it[7],
      'pu' => $precoUnit
    ]);
  }
}
function seedBudjetTransporteTemplate(PDO $pdo): void {
  $existe = $pdo->prepare("SELECT COUNT(*) FROM logistica_budjet_itens WHERE departamento='transporte'");
  $existe->execute();
  if((int)$existe->fetchColumn() > 0) return;

  $itens = [
    ['CUSTO DO PESSOAL',1,'Salario mensal do pessoal Quadro','Mensal',12,0,7800000.00,7800000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',1,'Uniforme EPI','Un',72,0,129600.00,129600.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',2,'LUVA CABEDAL MANGA CURTA','Pr',500,0,159600.00,159600.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',3,'Mascara de Proteccao com Filtro','Un',120,0,38400.00,38400.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',4,'MASCARA DE PAPEL','Un',500,0,31285.00,31285.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',5,'OCULO DE PROTECCAO TRANSPARENTE','Un',500,0,60000.00,60000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',6,'CAPACETE DE PROTECAO BRANCO','Un',10,0,3120.00,3120.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',7,'CAPACETE DE PROTECAO AZUL','Un',80,0,22800.00,22800.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',8,'PROTECTOR DE OUVIDOS','Un',500,0,43590.00,43590.00],
    ['DESPESAS DE FUNCIONAMENTO DO PROJECTO',1,'Despesas de alimentacao','MT',1,209,500000.00,492600.00],
    ['DESPESAS DE FUNCIONAMENTO DO PROJECTO',2,'Despesa de alojamento','Noite',1,0,250000.00,250000.00],
    ['MEIOS DE TRANSPORTE',1,'Despesas de Seguros e Taxas','Vg',1,0,1000000.00,1000000.00],
    ['MEIOS DE TRANSPORTE',2,'Compra de veiculos','Un',1,0,3000000.00,3000000.00],
    ['MEIOS DE TRANSPORTE',3,'TAXA DE EQUIPAMENTO USADO','Un',1,0,500000.00,500000.00],
    ['DESPESAS GERAIS DE ACTIVIDADES E PARA SERVICOS',1,'GEST FROTA','Un',1,0,1640182.00,1640182.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',1,'MASSA GREASE KG','Un',500,0,213000.00,213000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',9,'Uniforme Flame...','Un',72,0,252000.00,252000.00],
    ['MATERIAIS E EQUIPAMENTOS DE PROTECCAO E SEGURANCA/SAFETY',10,'botas bazik ...','Un',144,0,241920.00,241920.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',2,'DIESEL.','Lt',70000,1612,6303500.00,5922814.10],
    ['MEIOS DE TRANSPORTE',4,'Despesas de manutencao de veiculos','Vg',1,0,5000000.00,4995347.29],
    ['DESPESAS ADMINISTRATIVAS',1,'Material diverso para escritorio','Un',1,0,300000.00,300000.00],
    ['REPARACAO DE MAQUINAS E VIATURAS',1,'Aquisicao de acessorios para a reparacao de maquinas e viaturas','Un',1,0,5000000.00,5000000.00],
    ['COMBUSTIVEIS E LUBRIFICANTES',3,'GASOLINA','Lt',500,0,45520.00,45520.00],
  ];

  $ins = $pdo->prepare("
    INSERT INTO logistica_budjet_itens
    (departamento,categoria,ordem_item,descricao,unidade,qtd_planeada,qtd_actual,orcamento_compra,saldo_pendente,preco_unitario)
    VALUES
    (:d,:c,:o,:de,:u,:qp,:qa,:oc,:sp,:pu)
  ");
  foreach($itens as $it){
    $qtdPlaneada = (float)$it[4];
    $orcamentoCompra = (float)$it[6];
    $precoUnit = $qtdPlaneada > 0 ? ($orcamentoCompra / $qtdPlaneada) : 0;
    $ins->execute([
      'd' => 'transporte',
      'c' => (string)$it[0],
      'o' => (int)$it[1],
      'de' => (string)$it[2],
      'u' => (string)$it[3],
      'qp' => $qtdPlaneada,
      'qa' => (float)$it[5],
      'oc' => $orcamentoCompra,
      'sp' => (float)$it[7],
      'pu' => $precoUnit
    ]);
  }
}
function sincronizarBudjetDepartamentosPorTemplate(PDO $pdo): void {
  $rows = $pdo->query("SELECT departamento, COALESCE(SUM(orcamento_compra),0) AS total_orc, COALESCE(SUM(saldo_pendente),0) AS total_saldo FROM logistica_budjet_itens GROUP BY departamento")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if(!$rows) return;
  $up = $pdo->prepare("UPDATE logistica_budjet_departamentos SET orcamento_total=:o, saldo_atual=:s, atualizado_em=NOW() WHERE departamento=:d");
  foreach($rows as $r){
    $dep = departamentoCanonico((string)($r['departamento'] ?? ''));
    garantirContaBudjet($pdo, $dep);
    $up->execute([
      'o' => (float)($r['total_orc'] ?? 0),
      's' => (float)($r['total_saldo'] ?? 0),
      'd' => $dep
    ]);
  }
}
function garantirContaBudjet(PDO $pdo, string $departamento): void {
  $dep = departamentoCanonico($departamento);
  $ins = $pdo->prepare("INSERT INTO logistica_budjet_departamentos (departamento,orcamento_total,saldo_atual,atualizado_em) VALUES (:d,0,0,NOW()) ON DUPLICATE KEY UPDATE departamento=departamento");
  $ins->execute(['d'=>$dep]);
}
function movimentarBudjet(
  PDO $pdo,
  string $departamento,
  string $tipo,
  float $valor,
  ?string $referencia = null,
  ?string $descricao = null,
  ?string $origemTabela = null,
  ?int $origemId = null,
  ?string $criadoPor = null,
  ?string $secaoModulo = null,
  ?string $categoriaBudjet = null
): array {
  $dep = departamentoCanonico($departamento);
  $tp = norm($tipo) === 'credito' ? 'Credito' : 'Debito';
  if($valor <= 0) throw new RuntimeException('Valor de movimento do budjet invalido');

  garantirContaBudjet($pdo, $dep);
  $st = $pdo->prepare("SELECT saldo_atual FROM logistica_budjet_departamentos WHERE departamento=:d LIMIT 1");
  $st->execute(['d'=>$dep]);
  $saldoAtual = (float)($st->fetchColumn() ?: 0);

  if($tp === 'Debito' && $saldoAtual < $valor){
    throw new RuntimeException('Saldo insuficiente no budjet de ' . ucfirst($dep) . '. Saldo atual: ' . money($saldoAtual));
  }
  $saldoApos = $tp === 'Credito' ? ($saldoAtual + $valor) : ($saldoAtual - $valor);

  $up = $pdo->prepare("UPDATE logistica_budjet_departamentos SET saldo_atual=:s, atualizado_em=NOW() WHERE departamento=:d");
  $up->execute(['s'=>$saldoApos,'d'=>$dep]);

  $insMov = $pdo->prepare("
    INSERT INTO logistica_budjet_movimentos
    (departamento,tipo,valor,referencia,descricao,origem_tabela,origem_id,saldo_apos,criado_por,secao_modulo,categoria_budjet)
    VALUES
    (:departamento,:tipo,:valor,:referencia,:descricao,:origem_tabela,:origem_id,:saldo_apos,:criado_por,:secao_modulo,:categoria_budjet)
  ");
  $insMov->execute([
    'departamento'=>$dep,
    'tipo'=>$tp,
    'valor'=>$valor,
    'referencia'=>$referencia,
    'descricao'=>$descricao,
    'origem_tabela'=>$origemTabela,
    'origem_id'=>$origemId,
    'saldo_apos'=>$saldoApos,
    'criado_por'=>$criadoPor,
    'secao_modulo'=>$secaoModulo ?: 'Budjet',
    'categoria_budjet'=>$categoriaBudjet
  ]);

  return ['departamento'=>$dep,'saldo_antes'=>$saldoAtual,'saldo_apos'=>$saldoApos];
}
function garantir(PDO $pdo){
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_requisicoes (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,origem VARCHAR(150) NOT NULL,destino VARCHAR(150) NOT NULL,item VARCHAR(180) NOT NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,unidade VARCHAR(20) NOT NULL DEFAULT 'un',prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',status VARCHAR(20) NOT NULL DEFAULT 'Pendente',data_requisicao DATE NOT NULL,responsavel VARCHAR(150) NULL,observacoes TEXT NULL,finalidade VARCHAR(255) NULL,origem_modulo VARCHAR(40) NOT NULL DEFAULT 'logistica',categoria_item VARCHAR(40) NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_solicitante VARCHAR(30) NOT NULL DEFAULT 'oficina',preco_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,custo_total DECIMAL(14,2) NOT NULL DEFAULT 0,referencia_cotacao VARCHAR(120) NULL,anexo_preco_por VARCHAR(150) NULL,anexo_preco_em DATETIME NULL,decidido_por VARCHAR(150) NULL,decidido_em DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fornecedores (id INT AUTO_INCREMENT PRIMARY KEY,nome VARCHAR(180) NOT NULL,contacto VARCHAR(150) NULL,telefone VARCHAR(50) NULL,email VARCHAR(150) NULL,nuit VARCHAR(50) NULL,tipo_fornecedor VARCHAR(80) NOT NULL DEFAULT 'Pecas',escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',status VARCHAR(20) NOT NULL DEFAULT 'Ativo',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fornecedor_credito_mov (id INT AUTO_INCREMENT PRIMARY KEY,fornecedor_id INT NOT NULL,material_nome VARCHAR(180) NOT NULL,especificacoes VARCHAR(255) NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,preco_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,total DECIMAL(14,2) NOT NULL DEFAULT 0,saldo_usado DECIMAL(14,2) NOT NULL DEFAULT 0,divida_gerada DECIMAL(14,2) NOT NULL DEFAULT 0,observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_fornecedor_data (fornecedor_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(50) UNIQUE,nome VARCHAR(180) NOT NULL,categoria VARCHAR(80) NOT NULL DEFAULT 'Peca',unidade VARCHAR(20) NOT NULL DEFAULT 'un',stock_atual DECIMAL(12,2) NOT NULL DEFAULT 0,stock_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,preco_referencia DECIMAL(14,2) NOT NULL DEFAULT 0,fornecedor_preferencial_id INT NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_aplicacao VARCHAR(30) NOT NULL DEFAULT 'oficina',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_movimentos_stock (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NOT NULL,tipo_movimento ENUM('Entrada','Saida','Ajuste') NOT NULL DEFAULT 'Entrada',fornecedor_id INT NULL,projecto VARCHAR(40) NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,custo_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,referencia VARCHAR(120) NULL,observacoes TEXT NULL,criado_por INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_cotacoes (id INT AUTO_INCREMENT PRIMARY KEY,fornecedor_id INT NOT NULL,item_nome VARCHAR(180) NOT NULL,categoria_item VARCHAR(80) NOT NULL DEFAULT 'Peca',preco_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,prazo_dias INT NOT NULL DEFAULT 0,validade DATE NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_solicitante VARCHAR(30) NOT NULL DEFAULT 'oficina',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas_substituidas (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NOT NULL,matricula_ativo VARCHAR(50) NOT NULL,area_origem VARCHAR(30) NOT NULL DEFAULT 'Oficina',quantidade DECIMAL(12,2) NOT NULL DEFAULT 1,custo_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,data_substituicao DATE NOT NULL,motivo VARCHAR(200) NULL,responsavel VARCHAR(150) NULL,referencia_os VARCHAR(60) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_ordens_compra (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,requisicao_id INT NULL,assunto VARCHAR(180) NOT NULL,fornecedor_id INT NULL,projecto VARCHAR(180) NOT NULL,departamento VARCHAR(30) NOT NULL DEFAULT 'oficina',data_entrega DATE NULL,data_registo DATE NOT NULL,termo_pagamento VARCHAR(40) NOT NULL DEFAULT 'Pos pago',moeda VARCHAR(10) NOT NULL DEFAULT 'MT',cambio DECIMAL(14,4) NOT NULL DEFAULT 1,categoria VARCHAR(120) NULL,subcategoria VARCHAR(120) NULL,solicitante VARCHAR(150) NULL,destino VARCHAR(150) NULL,prioridade VARCHAR(20) NULL,observacao TEXT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Pendente',valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,budjet_debitado TINYINT(1) NOT NULL DEFAULT 0,budjet_debitado_em DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_ordem_dep_status (departamento,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_ordens_compra_itens (id INT AUTO_INCREMENT PRIMARY KEY,ordem_id INT NOT NULL,budjet_item_id INT NULL,artigo_servico VARCHAR(255) NOT NULL,custo_forn DECIMAL(14,2) NOT NULL DEFAULT 0,quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_ordem_item (ordem_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_ordens_compra_cotacoes (id INT AUTO_INCREMENT PRIMARY KEY,ordem_id INT NOT NULL,fornecedor_id INT NOT NULL,preco DECIMAL(14,2) NOT NULL DEFAULT 0,anexo_cotacao VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_ordem_cotacao (ordem_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fin_facturas (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,fornecedor_id INT NULL,departamento VARCHAR(30) NOT NULL DEFAULT 'oficina',projecto VARCHAR(180) NULL,descricao VARCHAR(180) NOT NULL,valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,data_factura DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Pendente',anexo_documento VARCHAR(255) NULL,observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fin_pagamentos (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,tipo_registo VARCHAR(20) NOT NULL DEFAULT 'Pagamento',factura_id INT NULL,projecto VARCHAR(180) NULL,descricao VARCHAR(180) NOT NULL,valor_pago DECIMAL(14,2) NOT NULL DEFAULT 0,data_pagamento DATE NOT NULL,metodo VARCHAR(40) NOT NULL DEFAULT 'Transferencia',anexo_documento VARCHAR(255) NULL,observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fin_dividas (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,fornecedor_id INT NULL,projecto VARCHAR(180) NULL,descricao VARCHAR(180) NOT NULL,valor_divida DECIMAL(14,2) NOT NULL DEFAULT 0,data_divida DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Aberta',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas_avariadas (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NULL,codigo_peca VARCHAR(50) NULL,nome_peca VARCHAR(180) NOT NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 1,motivo VARCHAR(200) NULL,data_registo DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Avariada',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_operacional_custos (id INT AUTO_INCREMENT PRIMARY KEY,categoria VARCHAR(40) NOT NULL,departamento VARCHAR(30) NOT NULL DEFAULT 'transporte',fornecedor_id INT NULL,forma_pagamento VARCHAR(20) NOT NULL DEFAULT 'Numerario',referencia_cotacao VARCHAR(120) NULL,descricao VARCHAR(180) NOT NULL,valor DECIMAL(14,2) NOT NULL DEFAULT 0,data_lancamento DATE NOT NULL,responsavel VARCHAR(150) NULL,observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_budjet_departamentos (id INT AUTO_INCREMENT PRIMARY KEY,departamento VARCHAR(30) NOT NULL UNIQUE,orcamento_total DECIMAL(14,2) NOT NULL DEFAULT 0,saldo_atual DECIMAL(14,2) NOT NULL DEFAULT 0,atualizado_em DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_budjet_movimentos (id INT AUTO_INCREMENT PRIMARY KEY,departamento VARCHAR(30) NOT NULL,tipo VARCHAR(20) NOT NULL,valor DECIMAL(14,2) NOT NULL DEFAULT 0,referencia VARCHAR(120) NULL,descricao VARCHAR(255) NULL,origem_tabela VARCHAR(80) NULL,origem_id INT NULL,saldo_apos DECIMAL(14,2) NOT NULL DEFAULT 0,criado_por VARCHAR(150) NULL,secao_modulo VARCHAR(60) NULL,categoria_budjet VARCHAR(180) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_budjet_dep_data (departamento,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_budjet_itens (id INT AUTO_INCREMENT PRIMARY KEY,departamento VARCHAR(30) NOT NULL,categoria VARCHAR(180) NOT NULL,ordem_item INT NOT NULL DEFAULT 1,descricao VARCHAR(255) NOT NULL,unidade VARCHAR(40) NULL,qtd_planeada DECIMAL(14,2) NOT NULL DEFAULT 0,qtd_actual DECIMAL(14,2) NOT NULL DEFAULT 0,orcamento_compra DECIMAL(14,2) NOT NULL DEFAULT 0,saldo_pendente DECIMAL(14,2) NOT NULL DEFAULT 0,preco_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_budjet_itens_dep_cat (departamento,categoria,ordem_item)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colunaExiste = static function (PDO $pdo, string $tabela, string $coluna): bool {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna");
  $stmt->execute(['tabela' => $tabela, 'coluna' => $coluna]);
  return (int)$stmt->fetchColumn() > 0;
};
$garantirColuna = static function (PDO $pdo, string $tabela, string $coluna, string $definicao) use ($colunaExiste): void {
  if (!$colunaExiste($pdo, $tabela, $coluna)) {
    $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
  }
};

// Retrocompatibilidade com bases antigas
$garantirColuna($pdo, 'logistica_requisicoes', 'origem_modulo', "VARCHAR(40) NOT NULL DEFAULT 'logistica'");
$garantirColuna($pdo, 'logistica_requisicoes', 'categoria_item', 'VARCHAR(40) NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'finalidade', 'VARCHAR(255) NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_requisicoes', 'area_solicitante', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_requisicoes', 'preco_unitario', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_requisicoes', 'valor_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_requisicoes', 'custo_total', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_requisicoes', 'referencia_cotacao', 'VARCHAR(120) NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'anexo_preco_por', 'VARCHAR(150) NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'anexo_preco_em', 'DATETIME NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'decidido_por', 'VARCHAR(150) NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'decidido_em', 'DATETIME NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'fornecedor_id', 'INT NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'forma_pagamento', "VARCHAR(20) NULL");
$garantirColuna($pdo, 'logistica_requisicoes', 'tipo_compra', "VARCHAR(30) NULL");
$garantirColuna($pdo, 'logistica_requisicoes', 'cotacao_automatica', 'TINYINT(1) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_requisicoes', 'cotacao_id', 'INT NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'budjet_debitado', 'TINYINT(1) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_requisicoes', 'budjet_debitado_em', 'DATETIME NULL');
$garantirColuna($pdo, 'logistica_requisicoes', 'budjet_debito_valor', 'DECIMAL(14,2) NOT NULL DEFAULT 0');

$garantirColuna($pdo, 'logistica_fornecedores', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_fornecedores', 'tipo_fornecedor', "VARCHAR(80) NOT NULL DEFAULT 'Pecas'");
$garantirColuna($pdo, 'logistica_fornecedores', 'status', "VARCHAR(20) NOT NULL DEFAULT 'Ativo'");
$garantirColuna($pdo, 'logistica_fornecedores', 'saldo_budjet', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_fornecedores', 'modalidade_credito', "VARCHAR(20) NOT NULL DEFAULT 'Normal'");
$garantirColuna($pdo, 'logistica_fornecedores', 'divida_atual', 'DECIMAL(14,2) NOT NULL DEFAULT 0');

$garantirColuna($pdo, 'logistica_pecas', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_pecas', 'area_aplicacao', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_pecas', 'preco_referencia', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_movimentos_stock', 'fornecedor_id', 'INT NULL');
$garantirColuna($pdo, 'logistica_movimentos_stock', 'projecto', 'VARCHAR(40) NULL');

$garantirColuna($pdo, 'logistica_cotacoes', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_cotacoes', 'area_solicitante', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_cotacoes', 'quantidade', 'DECIMAL(12,2) NOT NULL DEFAULT 1');
$garantirColuna($pdo, 'logistica_cotacoes', 'total_cotacao', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_cotacoes', 'anexo_cotacao', 'VARCHAR(255) NULL');

$garantirColuna($pdo, 'logistica_pecas_substituidas', 'area_origem', "VARCHAR(30) NOT NULL DEFAULT 'Oficina'");
$garantirColuna($pdo, 'logistica_fin_facturas', 'departamento', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_fin_facturas', 'projecto', 'VARCHAR(180) NULL');
$garantirColuna($pdo, 'logistica_fin_facturas', 'anexo_documento', 'VARCHAR(255) NULL');
$garantirColuna($pdo, 'logistica_fin_pagamentos', 'tipo_registo', "VARCHAR(20) NOT NULL DEFAULT 'Pagamento'");
$garantirColuna($pdo, 'logistica_fin_pagamentos', 'projecto', 'VARCHAR(180) NULL');
$garantirColuna($pdo, 'logistica_fin_pagamentos', 'anexo_documento', 'VARCHAR(255) NULL');
$garantirColuna($pdo, 'logistica_operacional_custos', 'departamento', "VARCHAR(30) NOT NULL DEFAULT 'transporte'");
$garantirColuna($pdo, 'logistica_operacional_custos', 'fornecedor_id', 'INT NULL');
$garantirColuna($pdo, 'logistica_operacional_custos', 'forma_pagamento', "VARCHAR(20) NOT NULL DEFAULT 'Numerario'");
$garantirColuna($pdo, 'logistica_operacional_custos', 'referencia_cotacao', 'VARCHAR(120) NULL');
$garantirColuna($pdo, 'logistica_ordens_compra', 'solicitante', 'VARCHAR(150) NULL');
$garantirColuna($pdo, 'logistica_ordens_compra', 'destino', 'VARCHAR(150) NULL');
$garantirColuna($pdo, 'logistica_ordens_compra', 'prioridade', 'VARCHAR(20) NULL');
$garantirColuna($pdo, 'logistica_budjet_movimentos', 'secao_modulo', 'VARCHAR(60) NULL');
$garantirColuna($pdo, 'logistica_budjet_movimentos', 'categoria_budjet', 'VARCHAR(180) NULL');
$garantirColuna($pdo, 'logistica_budjet_departamentos', 'bloqueado', 'TINYINT(1) NOT NULL DEFAULT 0');
$garantirColuna($pdo, 'logistica_budjet_departamentos', 'bloqueado_por', 'VARCHAR(150) NULL');
$garantirColuna($pdo, 'logistica_budjet_departamentos', 'bloqueado_em', 'DATETIME NULL');

$pdo->exec("INSERT INTO logistica_budjet_departamentos (departamento,orcamento_total,saldo_atual,atualizado_em) VALUES ('oficina',0,0,NOW()) ON DUPLICATE KEY UPDATE departamento=departamento");
$pdo->exec("INSERT INTO logistica_budjet_departamentos (departamento,orcamento_total,saldo_atual,atualizado_em) VALUES ('transporte',0,0,NOW()) ON DUPLICATE KEY UPDATE departamento=departamento");
seedBudjetOficinaTemplate($pdo);
seedBudjetTransporteTemplate($pdo);
sincronizarBudjetDepartamentosPorTemplate($pdo);
}

$view = $_GET['view'] ?? 'painel';
$mode = $_GET['mode'] ?? 'list';
$q = trim((string)($_GET['q'] ?? ''));
$status_filtro = trim((string)($_GET['status'] ?? 'todos'));
$departamento_filtro = trim((string)($_GET['departamento'] ?? 'todos'));
$escopo_filtro = trim((string)($_GET['escopo'] ?? 'todos'));
$periodo = trim((string)($_GET['periodo'] ?? 'mensal'));
$pedidos_prioridade = trim((string)($_GET['pedidos_prioridade'] ?? 'todos'));
$compras_tab = trim((string)($_GET['compras_tab'] ?? 'requisicoes'));
$item_nome_prefill = trim((string)($_GET['item_nome'] ?? ''));
$ordem_requisicao_prefill = (int)($_GET['requisicao_id'] ?? 0);
$ordem_assunto_prefill = trim((string)($_GET['assunto'] ?? ''));
$ordem_projecto_prefill = projectoCanonico((string)($_GET['projecto'] ?? ''));
$ordem_solicitante_prefill = trim((string)($_GET['solicitante'] ?? ''));
$ordem_destino_prefill = trim((string)($_GET['destino'] ?? 'Compras'));
$ordem_prioridade_prefill = trim((string)($_GET['prioridade'] ?? 'Normal')) ?: 'Normal';
if(!in_array($view,['painel','requisicoes','fornecedores','pecas','cotacoes','substituicoes','relatorios','pedidos_oficina','extratos','facturas','recibos','pagamentos','pecas_avariadas','oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes','budjet','alertas'],true)) $view='painel';
if($view==='painel') $view='pedidos_oficina';
if(!in_array($mode,['list','form'],true)) $mode='list';
if(in_array($view,['painel','relatorios','pedidos_oficina','extratos','budjet','alertas'],true)) $mode='list';
if(!in_array($pedidos_prioridade,['todos','urgente','medio','baixo'],true)) $pedidos_prioridade='todos';
if(!in_array($compras_tab,['requisicoes','cotacoes','fornecedores','ordens_compras'],true)) $compras_tab='requisicoes';
if($view==='requisicoes' && $compras_tab==='cotacoes') $mode='list';

$perfil = norm($_SESSION['usuario_perfil'] ?? '');
$pode_oper = in_array($perfil,['logistica','logistico','logisticaoperacional','supervisorlogistica','admin','administrador'],true);
$pode_geral = in_array($perfil,['logisticageral','supervisorlogistica','admin','administrador'],true);
$is_admin_logistica = in_array($perfil,['admin','administrador','superadmin','master','direccao','diretor'],true) || ((int)($_SESSION['usuario_id'] ?? 0) === 1);
$operacional_sem_restricao = true;

function secaoLogistica(string $view): string {
  if($view==='pedidos_oficina') return 'requisicoes';
  if($view==='fornecedores') return 'requisicoes';
  if(in_array($view,['extratos','facturas','recibos','cotacoes','pagamentos'],true)) return 'financas';
  if(in_array($view,['pecas','substituicoes','pecas_avariadas'],true)) return 'stock';
  if($view==='budjet') return 'budjet';
  if($view==='alertas') return 'alertas';
  if(in_array($view,['oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true)) return 'operacional';
  return 'requisicoes';
}
$secao = secaoLogistica($view);

function opcoesSecaoLogistica(string $secao): array {
  if($secao==='requisicoes') return [];
  if($secao==='financas') return [
    'facturas' => 'Facturas',
    'recibos' => 'Recibos',
    'pagamentos' => 'Pagamentos',
    'extratos' => 'Dividas',
  ];
  if($secao==='stock') return [
    'pecas' => 'Inventario',
    'substituicoes' => 'Pecas substituidas',
    'pecas_avariadas' => 'Pecas avariadas',
  ];
  if($secao==='operacional') return [
    'oper_uniforme' => 'EPs e Ferramentas',
    'oper_alimentacao' => 'Alimentacao',
    'oper_portagem' => 'Custos de portagem',
    'oper_multas' => 'Multas',
    'oper_seguros' => 'Seguros',
    'oper_taxas_radios' => 'Taxas de radios',
    'oper_extintores' => 'Extintores',
    'oper_manutencoes' => 'Manutencoes',
  ];
  if($secao==='budjet') return ['budjet' => 'Resumo Budjet'];
  if($secao==='alertas') return ['alertas' => 'Alertas'];
  return [];
}

$depRaw = norm($_GET['departamento'] ?? '');
$budjetDepartamentoSelecionado = in_array($depRaw, ['oficina','transporte'], true) ? $depRaw : '';
$msg=''; $erro=''; $requisicoes=[]; $fornecedores=[]; $pecas=[]; $cotacoes=[]; $cotacoesOrdem=[]; $comp=[]; $subs=[]; $fornRef=[]; $fornCreditoRef=[]; $fornNomePorId=[]; $pecasRef=[]; $rel=[]; $pedidosOficina=[]; $facturas=[]; $pagamentos=[]; $recibos=[]; $dividas=[]; $ordensCompra=[]; $pecasAvariadas=[]; $custosOperacionais=[]; $budjetResumo=[]; $budjetMovimentos=[]; $budjetItensTemplate=[]; $budjetItensCompra=[]; $impactoSecoes=['Compras'=>0.0,'Financas'=>0.0,'Controle de Stock'=>0.0,'Logistica Operacional'=>0.0];

try {
  garantir($pdo);

  if($_SERVER['REQUEST_METHOD']==='POST'){
    $acao = $_POST['acao'] ?? '';
    if($acao==='criar_requisicao'){
      $escopo = in_array($_POST['escopo_logistica'] ?? '',['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional';
      $area = in_array($_POST['area_solicitante'] ?? '',['oficina','transporte','geral'],true)?$_POST['area_solicitante']:'oficina';
      $categoriaReq = categoriaRequisicaoCanonica($_POST['categoria_item'] ?? '');
      if(!$operacional_sem_restricao && (($escopo==='operacional'&&!$pode_oper)||($escopo==='geral'&&!$pode_geral))) throw new RuntimeException('Sem permissao neste escopo');
      $origem=trim((string)($_POST['origem']??'')); $destino=trim((string)($_POST['destino']??''));
      if($origem===''||$destino===''||$categoriaReq==='') throw new RuntimeException('Campos obrigatorios incompletos');
      $itemRaw = $_POST['item'] ?? [];
      $finRaw = $_POST['finalidade'] ?? [];
      $qtdRaw = $_POST['quantidade'] ?? [];
      $uniRaw = $_POST['unidade'] ?? [];
      $preRaw = $_POST['preco_unitario'] ?? [];
      if(!is_array($itemRaw)) $itemRaw = [$itemRaw];
      if(!is_array($finRaw)) $finRaw = [$finRaw];
      if(!is_array($qtdRaw)) $qtdRaw = [$qtdRaw];
      if(!is_array($uniRaw)) $uniRaw = [$uniRaw];
      if(!is_array($preRaw)) $preRaw = [$preRaw];
      $maxRows = max(count($itemRaw), count($finRaw), count($qtdRaw), count($uniRaw), count($preRaw));
      $linhas = [];
      for($i=0; $i<$maxRows; $i++){
        $item = trim((string)($itemRaw[$i] ?? ''));
        $finalidade = trim((string)($finRaw[$i] ?? ''));
        $qtd = (float)($qtdRaw[$i] ?? 0);
        $unidade = trim((string)($uniRaw[$i] ?? 'un'));
        $precoUnitario = (float)($preRaw[$i] ?? 0);
        $temAlgum = ($item !== '' || $finalidade !== '' || $qtd > 0 || $precoUnitario > 0);
        if(!$temAlgum) continue;
        if($item==='' || $finalidade==='' || $qtd<=0 || $precoUnitario<=0){
          throw new RuntimeException('Cada linha da requisicao precisa de item, finalidade, quantidade e valor unitario validos.');
        }
        $linhas[] = [
          'item' => $item,
          'finalidade' => $finalidade,
          'qtd' => $qtd,
          'unidade' => $unidade !== '' ? $unidade : 'un',
          'preco' => $precoUnitario,
          'valor' => round($qtd * $precoUnitario, 2)
        ];
      }
      if(!$linhas) throw new RuntimeException('Adicione pelo menos uma linha na requisicao.');
      $obsCriar = trim((string)($_POST['observacoes']??''));
      $tipoFornecedorReq = norm($_POST['tipo_fornecedor_requisicao'] ?? 'credito');
      if(!in_array($tipoFornecedorReq, ['credito','normal'], true)) $tipoFornecedorReq = 'credito';
      $fornCredId = (int)($_POST['fornecedor_credito_id'] ?? 0);
      if($fornCredId > 0){
        $stF = $pdo->prepare("SELECT nome,status,modalidade_credito FROM logistica_fornecedores WHERE id=:i LIMIT 1");
        $stF->execute(['i'=>$fornCredId]);
        $fornSel = $stF->fetch(PDO::FETCH_ASSOC) ?: null;
        if(!$fornSel) throw new RuntimeException('Fornecedor selecionado nao encontrado.');
        if(norm((string)($fornSel['status'] ?? '')) !== 'ativo') throw new RuntimeException('Fornecedor selecionado esta inativo.');
        $modalidadeSel = strtolower(modalidadeFornecedor((string)($fornSel['modalidade_credito'] ?? 'Normal')));
        if($modalidadeSel !== $tipoFornecedorReq){
          throw new RuntimeException('Selecione um fornecedor compativel com o tipo escolhido (Credito/Normal).');
        }
        $fn = (string)($fornSel['nome'] ?? '');
        $tagForn = 'Fornecedor selecionado: ' . ($fn !== '' ? $fn : ('#'.$fornCredId)) . ' (' . modalidadeFornecedorLabel((string)($fornSel['modalidade_credito'] ?? 'Normal')) . ')';
        $obsCriar = $obsCriar !== '' ? ($obsCriar . ' | ' . $tagForn) : $tagForn;
      }
      $prioridadeReq = trim((string)($_POST['prioridade']??'Normal'))?:'Normal';
      $statusReqInicial = in_array($area, ['oficina','transporte'], true) ? 'Em transito' : 'Pendente';
      $stmt=$pdo->prepare("INSERT INTO logistica_requisicoes (origem,destino,item,quantidade,unidade,prioridade,status,data_requisicao,responsavel,observacoes,finalidade,origem_modulo,categoria_item,escopo_logistica,area_solicitante,fornecedor_id,preco_unitario,valor_total,custo_total) VALUES (:origem,:destino,:item,:qtd,:unidade,:prioridade,:status,:data,:resp,:obs,:fin,'logistica',:cat,:escopo,:area,:forn,:preco,:valor,:valor)");
      $insOrd=$pdo->prepare("INSERT INTO logistica_ordens_compra (requisicao_id,assunto,fornecedor_id,projecto,departamento,data_entrega,data_registo,termo_pagamento,moeda,cambio,categoria,subcategoria,solicitante,destino,prioridade,observacao,status,valor_total,budjet_debitado) VALUES (:r,:a,:f,:p,:d,NULL,:dr,:t,'MT',1,:cat,:sub,:sol,:dst,:pri,:o,'Pendente',:v,0)");
      $insOrdItem=$pdo->prepare("INSERT INTO logistica_ordens_compra_itens (ordem_id,budjet_item_id,artigo_servico,custo_forn,quantidade,subtotal) VALUES (:o,NULL,:a,:c,:q,:s)");
      $pdo->beginTransaction();
      foreach($linhas as $ln){
        $stmt->execute([
          'origem'=>$origem,
          'destino'=>$destino,
          'item'=>$ln['item'],
          'qtd'=>$ln['qtd'],
          'unidade'=>$ln['unidade'],
          'prioridade'=>$prioridadeReq,
          'status'=>$statusReqInicial,
          'data'=>trim((string)($_POST['data_requisicao']??date('Y-m-d'))),
          'resp'=>trim((string)($_POST['responsavel']??''))?:null,
          'obs'=>$obsCriar!==''?$obsCriar:null,
          'fin'=>$ln['finalidade'],
          'cat'=>$categoriaReq,
          'escopo'=>$escopo,
          'area'=>$area,
          'forn'=>$fornCredId>0?$fornCredId:null,
          'preco'=>$ln['preco'],
          'valor'=>$ln['valor']
        ]);
        $id=(int)$pdo->lastInsertId();
        $codigo=sprintf('REQ-LOG-%s-%04d',date('Y'),$id);
        $pdo->prepare('UPDATE logistica_requisicoes SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);

        if(in_array($area, ['oficina','transporte'], true)){
          $projectoReq = $area === 'transporte' ? 'Projecto Transporte' : 'Projecto Oficina';
          $departamentoReq = departamentoPorProjecto($projectoReq);
          $termoReq = $tipoFornecedorReq === 'credito' ? 'Pos pago' : 'Pre pago';
          $obsOrdem = trim((string)($obsCriar !== '' ? ($obsCriar . ' | ') : '') . 'Gerada automaticamente via requisicao interna. Solicitante: ' . ($origem!==''?$origem:'-') . ' | Destino: ' . ($destino!==''?$destino:'-') . ' | Prioridade: ' . $prioridadeReq);
          $insOrd->execute([
            'r'=>$id,
            'a'=>$ln['item'],
            'f'=>$fornCredId>0?$fornCredId:null,
            'p'=>$projectoReq,
            'd'=>$departamentoReq,
            'dr'=>trim((string)($_POST['data_requisicao']??date('Y-m-d'))),
            't'=>$termoReq,
            'cat'=>$categoriaReq,
            'sub'=>$ln['finalidade'],
            'sol'=>$origem!==''?$origem:null,
            'dst'=>$destino!==''?$destino:null,
            'pri'=>$prioridadeReq,
            'o'=>$obsOrdem!==''?$obsOrdem:null,
            'v'=>$ln['valor']
          ]);
          $ordId = (int)$pdo->lastInsertId();
          $codOrd = sprintf('OC-%s-%04d', date('Y'), $ordId);
          $pdo->prepare("UPDATE logistica_ordens_compra SET codigo=:c WHERE id=:i")->execute(['c'=>$codOrd,'i'=>$ordId]);
          $insOrdItem->execute([
            'o'=>$ordId,
            'a'=>$ln['item'],
            'c'=>$ln['preco'],
            'q'=>$ln['qtd'],
            's'=>$ln['valor']
          ]);
        }
      }
      $pdo->commit();
      if(in_array($area, ['oficina','transporte'], true)){
        header('Location: ?view=requisicoes&compras_tab=ordens_compras&mode=list&saved=1'); exit;
      }
      header('Location: ?view=requisicoes&mode=list&saved=1'); exit;
    }
    if($acao==='mudar_status'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisicao invalida');
      $escopo=norm($_POST['escopo_logistica']??'operacional'); $area=norm($_POST['area_solicitante']??'oficina');
      if(!$operacional_sem_restricao && $escopo==='operacional'&&!$pode_oper) throw new RuntimeException('Sem permissao operacional');
      if(!$operacional_sem_restricao && $escopo==='geral'&&!$pode_geral) throw new RuntimeException('Sem permissao geral');
      $novoStatus = st($_POST['novo_status'] ?? '');
      if(!$operacional_sem_restricao && in_array($area,['oficina','transporte'],true) && in_array($novoStatus,['aprovada','negada'],true) && !$pode_oper) throw new RuntimeException('Aprovacao Oficina/Transporte exige Operacional');
      if($novoStatus === 'aprovada'){
        $s=$pdo->prepare('SELECT COALESCE(valor_total,custo_total,0) AS valor_total FROM logistica_requisicoes WHERE id=:i LIMIT 1');
        $s->execute(['i'=>$id]);
        $rReq=$s->fetch(PDO::FETCH_ASSOC) ?: null;
        $valorAtual=(float)($rReq['valor_total'] ?? 0);
        if($valorAtual <= 0){
          throw new RuntimeException('Antes de aprovar, anexe o preco pesquisado da requisicao.');
        }
        $pdo->prepare('UPDATE logistica_requisicoes SET status=:s,decidido_por=:u,decidido_em=NOW() WHERE id=:i')
          ->execute(['s'=>stLabel($_POST['novo_status']??''),'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),'i'=>$id]);
        header('Location: ?view=requisicoes&mode=list&updated=1'); exit;
      }
      $pdo->prepare('UPDATE logistica_requisicoes SET status=:s,decidido_por=:u,decidido_em=NOW() WHERE id=:i')
        ->execute(['s'=>stLabel($_POST['novo_status']??''),'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),'i'=>$id]);
      header('Location: ?view=requisicoes&mode=list&updated=1'); exit;
    }
    if($acao==='anexar_preco'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisicao invalida');
      $escopo=norm($_POST['escopo_logistica']??'operacional');
      if(!$operacional_sem_restricao && $escopo==='operacional'&&!$pode_oper) throw new RuntimeException('Sem permissao operacional');
      if(!$operacional_sem_restricao && $escopo==='geral'&&!$pode_geral) throw new RuntimeException('Sem permissao geral');
      $valor=(float)($_POST['valor_total']??0);
      if($valor<=0) throw new RuntimeException('Informe um valor valido para anexar ao pedido');
      $ref=trim((string)($_POST['referencia_cotacao']??''));
      $nota=trim((string)($_POST['nota_preco']??''));
      $stmtObs=$pdo->prepare('SELECT observacoes FROM logistica_requisicoes WHERE id=:i LIMIT 1');
      $stmtObs->execute(['i'=>$id]);
      $obsAtual=(string)($stmtObs->fetchColumn() ?: '');
      $bloco='[Pesquisa de preco] '.date('Y-m-d H:i').' | '.(string)($_SESSION['usuario_nome']??'Logistica');
      if($ref!=='') $bloco.=' | Ref: '.$ref;
      if($nota!=='') $bloco.=' | '.$nota;
      $obsNova=trim($obsAtual);
      if($obsNova!=='') $obsNova.="\n";
      $obsNova.=$bloco;
      $pdo->prepare('UPDATE logistica_requisicoes SET valor_total=:v,custo_total=:v,referencia_cotacao=:r,anexo_preco_por=:u,anexo_preco_em=NOW(),observacoes=:o WHERE id=:i')
          ->execute([
            'v'=>$valor,
            'r'=>$ref!==''?$ref:null,
            'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),
            'o'=>$obsNova,
            'i'=>$id
          ]);
      $retView = trim((string)($_POST['ret_view'] ?? ''));
      if(!in_array($retView, ['requisicoes','pedidos_oficina'], true)) $retView = 'requisicoes';
      header('Location: ?view=' . urlencode($retView) . '&mode=list&updated=1'); exit;
    }
    if($acao==='processar_compra_requisicao'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new RuntimeException('Requisicao invalida');
      $forma=norm($_POST['forma_pagamento']??''); if(!in_array($forma,['numerario','cheque','credito'],true)) throw new RuntimeException('Forma de pagamento invalida');
      $tipoCompra=norm($_POST['tipo_compra']??'normal'); if(!in_array($tipoCompra,['normal','compra_direta'],true)) $tipoCompra='normal';
      $fornecedorId=((int)($_POST['fornecedor_id']??0))>0?(int)$_POST['fornecedor_id']:null;
      $valorInformado=(float)($_POST['valor_total']??0);
      $refCotacao=trim((string)($_POST['referencia_cotacao']??''));

      $stReq = $pdo->prepare("SELECT id,codigo,item,categoria_item,quantidade,valor_total,custo_total,status,observacoes,area_solicitante,budjet_debitado FROM logistica_requisicoes WHERE id=:i LIMIT 1");
      $stReq->execute(['i'=>$id]);
      $req = $stReq->fetch(PDO::FETCH_ASSOC);
      if(!$req) throw new RuntimeException('Requisicao nao encontrada');
      if(st((string)($req['status'] ?? '')) !== 'pendente') throw new RuntimeException('Esta requisicao ja foi processada.');

      $valorBase = (float)($req['valor_total'] ?? $req['custo_total'] ?? 0);
      $valor = $valorInformado > 0 ? $valorInformado : $valorBase;
      $qtd = (float)($req['quantidade'] ?? 0);
      if($valor <= 0) throw new RuntimeException('Anexe o preco antes de processar a compra');
      if($forma==='credito' && !$fornecedorId) throw new RuntimeException('Fornecedor obrigatorio para compra a credito');

      $cotacaoId = null;
      $obs = trim((string)($req['observacoes'] ?? ''));
      $pdo->beginTransaction();

      if($valorInformado > 0){
        $obs .= ($obs!=='' ? "\n" : '') . '[Preco] ' . date('Y-m-d H:i') . ' | ' . (string)($_SESSION['usuario_nome']??'Logistica');
        if($refCotacao !== '') $obs .= ' | Ref: ' . $refCotacao;
        $pdo->prepare("UPDATE logistica_requisicoes SET valor_total=:v,custo_total=:v,referencia_cotacao=:r,anexo_preco_por=:u,anexo_preco_em=NOW() WHERE id=:i")
          ->execute([
            'v'=>$valor,
            'r'=>$refCotacao!==''?$refCotacao:null,
            'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),
            'i'=>$id
          ]);
      }

      $pdo->prepare('UPDATE logistica_requisicoes SET budjet_debitado=0, budjet_debitado_em=NULL, budjet_debito_valor=0 WHERE id=:i')
        ->execute(['i'=>$id]);

      if($forma==='credito' && $fornecedorId){
        $stFor = $pdo->prepare("SELECT saldo_budjet,nome,modalidade_credito,COALESCE(divida_atual,0) AS divida_atual FROM logistica_fornecedores WHERE id=:i LIMIT 1");
        $stFor->execute(['i'=>$fornecedorId]);
        $forn = $stFor->fetch(PDO::FETCH_ASSOC);
        if(!$forn) throw new RuntimeException('Fornecedor nao encontrado para credito');
        if(strtolower((string)($forn['modalidade_credito'] ?? 'normal')) !== 'credito'){
          throw new RuntimeException('Este fornecedor esta definido como normal. Selecione fornecedor a credito.');
        }
        $saldoAtual = (float)($forn['saldo_budjet'] ?? 0);
        $utilizadoSaldo = min($saldoAtual, $valor);
        $valorDivida = max(0, $valor - $utilizadoSaldo);
        $pdo->prepare("UPDATE logistica_fornecedores SET saldo_budjet = GREATEST(0, saldo_budjet - :v), divida_atual = divida_atual + :d WHERE id=:i")
          ->execute(['v'=>$valor,'d'=>$valorDivida,'i'=>$fornecedorId]);
        $obs .= ($obs!=='' ? "\n" : '') . '[Credito] Fornecedor: ' . (string)($forn['nome'] ?? ('#'.$fornecedorId)) . ' | Debitado saldo: ' . money($utilizadoSaldo);
        if($valorDivida > 0){
          $obs .= ' | Divida gerada: ' . money($valorDivida);
        }
      }

      if($tipoCompra==='compra_direta'){
        if(!$fornecedorId) throw new RuntimeException('Fornecedor obrigatorio para compra direta');
        $precoUnit = ($qtd > 0) ? ($valor / $qtd) : $valor;
        $insCot = $pdo->prepare("INSERT INTO logistica_cotacoes (fornecedor_id,item_nome,categoria_item,preco_unitario,prazo_dias,validade,escopo_logistica,area_solicitante,observacoes) VALUES (:f,:i,:c,:p,0,NULL,'operacional',:a,:o)");
        $insCot->execute([
          'f'=>$fornecedorId,
          'i'=>(string)($req['item'] ?? ''),
          'c'=>(string)($req['categoria_item'] ?? 'Peca'),
          'p'=>$precoUnit,
          'a'=>in_array((string)($req['area_solicitante'] ?? ''), ['oficina','transporte','geral'], true) ? (string)$req['area_solicitante'] : 'oficina',
          'o'=>'Gerada automaticamente via compra direta da requisicao #'.$id
        ]);
        $cotacaoId = (int)$pdo->lastInsertId();
        $obs .= ($obs!=='' ? "\n" : '') . '[Compra direta] Cotacao automatica gerada: COT-' . $cotacaoId;
      }

      $upReq = $pdo->prepare("UPDATE logistica_requisicoes SET status='Aprovada', fornecedor_id=:f, forma_pagamento=:fp, tipo_compra=:tc, cotacao_automatica=:ca, cotacao_id=:cid, observacoes=:o, decidido_por=:u, decidido_em=NOW() WHERE id=:i");
      $upReq->execute([
        'f'=>$fornecedorId,
        'fp'=>ucfirst($forma),
        'tc'=>$tipoCompra==='compra_direta'?'Compra Direta':'Normal',
        'ca'=>$tipoCompra==='compra_direta'?1:0,
        'cid'=>$cotacaoId,
        'o'=>$obs!==''?$obs:null,
        'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),
        'i'=>$id
      ]);

      $pdo->commit();
      header('Location: ?view=pedidos_oficina&mode=list&updated=1'); exit;
    }
    if($acao==='criar_ordem_compra'){
      $assunto = trim((string)($_POST['assunto'] ?? ''));
      $projecto = projectoCanonico($_POST['projecto'] ?? '');
      $fornecedorId = ((int)($_POST['fornecedor_id'] ?? 0)) > 0 ? (int)$_POST['fornecedor_id'] : null;
      $termo = trim((string)($_POST['termo_pagamento'] ?? 'Pos pago'));
      if(strtolower($termo) === 'requisicao') $termo = 'Pos pago';
      if(!in_array($termo,['Pre pago','Pos pago'],true)) $termo = 'Pos pago';
      $requisicaoId = ((int)($_POST['requisicao_id'] ?? 0)) > 0 ? (int)$_POST['requisicao_id'] : null;
      if($assunto==='' || $projecto==='') throw new RuntimeException('Informe assunto e projecto da ordem.');
      $modalidadePermitida = $termo === 'Pos pago' ? 'credito' : 'normal';

      $artRaw = $_POST['artigo_servico'] ?? [];
      $qtdRaw = $_POST['quantidade_item'] ?? [];
      $cusRaw = $_POST['custo_forn'] ?? [];
      $budRaw = $_POST['budjet_item_id'] ?? [];
      if(!is_array($artRaw)) $artRaw = [$artRaw];
      if(!is_array($qtdRaw)) $qtdRaw = [$qtdRaw];
      if(!is_array($cusRaw)) $cusRaw = [$cusRaw];
      if(!is_array($budRaw)) $budRaw = [$budRaw];
      $maxRows = max(count($artRaw), count($qtdRaw), count($cusRaw), count($budRaw));
      $linhas = [];
      for($i=0; $i<$maxRows; $i++){
        $art = trim((string)($artRaw[$i] ?? ''));
        $qtd = (float)($qtdRaw[$i] ?? 0);
        $cus = (float)($cusRaw[$i] ?? 0);
        $bud = ((int)($budRaw[$i] ?? 0)) > 0 ? (int)$budRaw[$i] : null;
        $tem = $art!=='' || $qtd>0 || $cus>0;
        if(!$tem) continue;
        if($art==='' || $qtd<=0 || $cus<0) throw new RuntimeException('Cada item da ordem precisa de artigo, quantidade e custo validos.');
        $linhas[] = ['art'=>$art,'qtd'=>$qtd,'cus'=>$cus,'sub'=>round($qtd*$cus,2),'bud'=>$bud];
      }
      if(!$linhas) throw new RuntimeException('Adicione pelo menos um artigo/servico na ordem.');
      $valorTotal = 0.0;
      foreach($linhas as $ln) $valorTotal += (float)$ln['sub'];

      $fornOrdRows = $pdo->query("SELECT id, LOWER(TRIM(COALESCE(modalidade_credito,'normal'))) AS modalidade FROM logistica_fornecedores WHERE status='Ativo'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $fornModalidade = [];
      foreach($fornOrdRows as $fr){
        $fornModalidade[(int)($fr['id'] ?? 0)] = ((string)($fr['modalidade'] ?? '') === 'credito') ? 'credito' : 'normal';
      }
      if($fornecedorId){
        $modOrdem = $fornModalidade[$fornecedorId] ?? '';
        if($modOrdem !== $modalidadePermitida){
          throw new RuntimeException($modalidadePermitida === 'credito'
            ? 'Para requisicao/pos pago selecione fornecedor a credito.'
            : 'Para pre pago selecione fornecedor normal.'
          );
        }
      }

      $cotacoesOrdem = [];
      if($termo === 'Pre pago'){
        $cotFornRaw = $_POST['fornecedor_cotacao_id'] ?? [];
        $cotPrecoRaw = $_POST['preco_cotacao'] ?? [];
        if(!is_array($cotFornRaw)) $cotFornRaw = [$cotFornRaw];
        if(!is_array($cotPrecoRaw)) $cotPrecoRaw = [$cotPrecoRaw];
        $maxCot = max(count($cotFornRaw), count($cotPrecoRaw));
        for($i=0; $i<$maxCot; $i++){
          $cf = (int)($cotFornRaw[$i] ?? 0);
          $cp = (float)($cotPrecoRaw[$i] ?? 0);
          $temLinha = $cf > 0 || $cp > 0 || (isset($_FILES['anexo_cotacao_ordem']['error'][$i]) && (int)$_FILES['anexo_cotacao_ordem']['error'][$i] !== UPLOAD_ERR_NO_FILE);
          if(!$temLinha) continue;
          if($cf <= 0 || $cp <= 0) throw new RuntimeException('Preencha fornecedor e preco em todas as cotacoes.');
          $modCot = $fornModalidade[$cf] ?? '';
          if($modCot !== 'normal'){
            throw new RuntimeException('Nas cotacoes de pre pago use apenas fornecedores normal.');
          }
          $cotacoesOrdem[] = ['fornecedor_id'=>$cf,'preco'=>$cp,'idx'=>$i];
        }
        if(count($cotacoesOrdem) < 3) throw new RuntimeException('Anexe no minimo 3 cotacoes para compra pre pago.');
      }

      $pdo->beginTransaction();
      $pdo->prepare("INSERT INTO logistica_ordens_compra (requisicao_id,assunto,fornecedor_id,projecto,departamento,data_entrega,data_registo,termo_pagamento,moeda,cambio,categoria,subcategoria,solicitante,destino,prioridade,observacao,status,valor_total,budjet_debitado) VALUES (:r,:a,:f,:p,:d,:de,:dr,:t,:m,:c,:cat,:sub,:sol,:dst,:pri,:o,'Pendente',:v,0)")
        ->execute([
          'r'=>$requisicaoId,
          'a'=>$assunto,
          'f'=>$fornecedorId,
          'p'=>$projecto,
          'd'=>departamentoPorProjecto($projecto),
          'de'=>trim((string)($_POST['data_entrega'] ?? '')) ?: null,
          'dr'=>trim((string)($_POST['data_registo'] ?? date('Y-m-d'))),
          't'=>$termo,
          'm'=>trim((string)($_POST['moeda'] ?? 'MT')) ?: 'MT',
          'c'=>(float)($_POST['cambio'] ?? 1),
          'cat'=>trim((string)($_POST['categoria'] ?? '')) ?: null,
          'sub'=>trim((string)($_POST['subcategoria'] ?? '')) ?: null,
          'sol'=>trim((string)($_POST['solicitante'] ?? '')) ?: (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
          'dst'=>trim((string)($_POST['destino'] ?? 'Compras')) ?: 'Compras',
          'pri'=>trim((string)($_POST['prioridade'] ?? 'Normal')) ?: 'Normal',
          'o'=>trim((string)($_POST['observacao'] ?? '')) ?: null,
          'v'=>$valorTotal
        ]);
      $ordemId = (int)$pdo->lastInsertId();
      $codigo = sprintf('OC-%s-%04d', date('Y'), $ordemId);
      $pdo->prepare("UPDATE logistica_ordens_compra SET codigo=:c WHERE id=:i")->execute(['c'=>$codigo,'i'=>$ordemId]);
      $insItem = $pdo->prepare("INSERT INTO logistica_ordens_compra_itens (ordem_id,budjet_item_id,artigo_servico,custo_forn,quantidade,subtotal) VALUES (:o,:b,:a,:c,:q,:s)");
      foreach($linhas as $ln){
        $insItem->execute(['o'=>$ordemId,'b'=>$ln['bud'],'a'=>$ln['art'],'c'=>$ln['cus'],'q'=>$ln['qtd'],'s'=>$ln['sub']]);
      }
      if($cotacoesOrdem){
        $insCot = $pdo->prepare("INSERT INTO logistica_ordens_compra_cotacoes (ordem_id,fornecedor_id,preco,anexo_cotacao) VALUES (:o,:f,:p,:a)");
        foreach($cotacoesOrdem as $ct){
          $anexo = salvarUploadDocumentoIndice('anexo_cotacao_ordem', (int)$ct['idx'], 'cotacoes');
          $insCot->execute(['o'=>$ordemId,'f'=>(int)$ct['fornecedor_id'],'p'=>(float)$ct['preco'],'a'=>$anexo]);
        }
      }
      $pdo->commit();
      header('Location: ?view=requisicoes&compras_tab=ordens_compras&mode=list&saved=1'); exit;
    }
    if($acao==='finalizar_ordem_compra'){
      $ordemId = (int)($_POST['ordem_id'] ?? 0);
      if($ordemId <= 0) throw new RuntimeException('Ordem invalida.');
      $pdo->beginTransaction();
      $stOr = $pdo->prepare("SELECT * FROM logistica_ordens_compra WHERE id=:i LIMIT 1");
      $stOr->execute(['i'=>$ordemId]);
      $ord = $stOr->fetch(PDO::FETCH_ASSOC) ?: null;
      if(!$ord) throw new RuntimeException('Ordem nao encontrada.');
      if((int)($ord['budjet_debitado'] ?? 0) === 1){
        $pdo->prepare("UPDATE logistica_ordens_compra SET status='Finalizada' WHERE id=:i")->execute(['i'=>$ordemId]);
        $pdo->commit();
        header('Location: ?view=requisicoes&compras_tab=ordens_compras&mode=list&updated=1'); exit;
      }
      $dep = departamentoCanonico((string)($ord['departamento'] ?? 'oficina'));
      $valor = (float)($ord['valor_total'] ?? 0);
      if($valor <= 0) throw new RuntimeException('Ordem sem valor para finalizar.');
      movimentarBudjet(
        $pdo,
        $dep,
        'debito',
        $valor,
        (string)($ord['codigo'] ?? ('OC-' . $ordemId)),
        'Finalizacao de ordem de compra: ' . (string)($ord['assunto'] ?? ''),
        'logistica_ordens_compra',
        $ordemId,
        (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
        'Compras',
        trim((string)($ord['categoria'] ?? 'Ordem de Compra')) ?: 'Ordem de Compra'
      );
      $pdo->prepare("UPDATE logistica_ordens_compra SET status='Finalizada', budjet_debitado=1, budjet_debitado_em=NOW() WHERE id=:i")
        ->execute(['i'=>$ordemId]);
      if(((int)($ord['requisicao_id'] ?? 0)) > 0){
        $pdo->prepare("UPDATE logistica_requisicoes SET status='Entregue', budjet_debitado=1, budjet_debitado_em=NOW(), budjet_debito_valor=:v WHERE id=:i")
          ->execute(['v'=>$valor,'i'=>(int)$ord['requisicao_id']]);
      }
      $pdo->commit();
      header('Location: ?view=requisicoes&compras_tab=ordens_compras&mode=list&updated=1'); exit;
    }
    if($acao==='budjet_creditar'){
      $dep = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      if(budjetDepartamentoBloqueado($pdo, $dep) && !$is_admin_logistica){
        throw new RuntimeException('Budjet trancado. Somente administrador pode ajustar enquanto estiver trancado.');
      }
      $valor = (float)($_POST['valor'] ?? 0);
      $descricao = trim((string)($_POST['descricao'] ?? 'Reforco de budjet'));
      if($valor <= 0) throw new RuntimeException('Informe um valor valido para reforco do budjet');
      $pdo->beginTransaction();
      garantirContaBudjet($pdo, $dep);
      $pdo->prepare("UPDATE logistica_budjet_departamentos SET orcamento_total = orcamento_total + :v WHERE departamento=:d")
        ->execute(['v'=>$valor,'d'=>$dep]);
      movimentarBudjet(
        $pdo,
        $dep,
        'credito',
        $valor,
        'REF-BUDGET',
        $descricao !== '' ? $descricao : 'Reforco de budjet',
        'logistica_budjet_departamentos',
        null,
        (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
        'Budjet',
        'Reforco'
      );
      $pdo->commit();
      header('Location: ?view=budjet&departamento=' . urlencode($dep) . '&updated=1'); exit;
    }
    if($acao==='budjet_toggle_lock'){
      $dep = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      if(!$is_admin_logistica){
        throw new RuntimeException('Apenas administrador pode trancar ou destrancar o budjet.');
      }
      $bloquear = (int)($_POST['bloquear'] ?? 0) === 1 ? 1 : 0;
      $pdo->prepare("
        UPDATE logistica_budjet_departamentos
        SET bloqueado=:b, bloqueado_por=:u, bloqueado_em=CASE WHEN :b=1 THEN NOW() ELSE NULL END, atualizado_em=NOW()
        WHERE departamento=:d
      ")->execute([
        'b'=>$bloquear,
        'u'=>(string)($_SESSION['usuario_nome'] ?? 'Administrador'),
        'd'=>$dep
      ]);
      header('Location: ?view=budjet&departamento=' . urlencode($dep) . '&updated=1'); exit;
    }
    if($acao==='budjet_item_guardar'){
      $dep = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      $itemId = (int)($_POST['item_id'] ?? 0);
      $categoria = trim((string)($_POST['categoria'] ?? ''));
      $descricao = trim((string)($_POST['descricao'] ?? ''));
      $unidade = trim((string)($_POST['unidade'] ?? 'Un')) ?: 'Un';
      $qtdPlaneada = (float)($_POST['qtd_planeada'] ?? 0);
      $qtdActual = (float)($_POST['qtd_actual'] ?? 0);
      $orcamento = (float)($_POST['orcamento_compra'] ?? 0);
      $saldo = (float)($_POST['saldo_pendente'] ?? 0);
      $ordemItem = (int)($_POST['ordem_item'] ?? 1);
      $precoUnit = (float)($_POST['preco_unitario'] ?? 0);
      if(budjetDepartamentoBloqueado($pdo, $dep) && !$is_admin_logistica){
        throw new RuntimeException('Budjet trancado. Somente administrador pode alterar itens.');
      }
      if(!$operacional_sem_restricao && !$pode_oper && !$pode_geral && !$is_admin_logistica){
        throw new RuntimeException('Sem permissao para editar itens do budjet.');
      }
      if($categoria==='' || $descricao===''){
        throw new RuntimeException('Categoria e actividade/servico sao obrigatorios.');
      }
      if($ordemItem <= 0) $ordemItem = 1;
      if($qtdPlaneada < 0 || $qtdActual < 0 || $orcamento < 0){
        throw new RuntimeException('Valores numericos do budjet nao podem ser negativos.');
      }
      if($precoUnit <= 0 && $qtdPlaneada > 0){
        $precoUnit = $orcamento / $qtdPlaneada;
      }
      if($itemId > 0){
        $pdo->prepare("
          UPDATE logistica_budjet_itens
          SET categoria=:c, ordem_item=:o, descricao=:de, unidade=:u, qtd_planeada=:qp, qtd_actual=:qa, orcamento_compra=:oc, saldo_pendente=:sp, preco_unitario=:pu
          WHERE id=:i AND departamento=:d
        ")->execute([
          'c'=>$categoria,'o'=>$ordemItem,'de'=>$descricao,'u'=>$unidade,'qp'=>$qtdPlaneada,'qa'=>$qtdActual,'oc'=>$orcamento,'sp'=>$saldo,'pu'=>$precoUnit,'i'=>$itemId,'d'=>$dep
        ]);
      } else {
        $pdo->prepare("
          INSERT INTO logistica_budjet_itens (departamento,categoria,ordem_item,descricao,unidade,qtd_planeada,qtd_actual,orcamento_compra,saldo_pendente,preco_unitario)
          VALUES (:d,:c,:o,:de,:u,:qp,:qa,:oc,:sp,:pu)
        ")->execute([
          'd'=>$dep,'c'=>$categoria,'o'=>$ordemItem,'de'=>$descricao,'u'=>$unidade,'qp'=>$qtdPlaneada,'qa'=>$qtdActual,'oc'=>$orcamento,'sp'=>$saldo,'pu'=>$precoUnit
        ]);
      }
      sincronizarBudjetDepartamentosPorTemplate($pdo);
      header('Location: ?view=budjet&departamento=' . urlencode($dep) . '&updated=1'); exit;
    }
    if($acao==='budjet_item_excluir'){
      $dep = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      $itemId = (int)($_POST['item_id'] ?? 0);
      if($itemId <= 0) throw new RuntimeException('Item de budjet invalido.');
      if(!$is_admin_logistica){
        throw new RuntimeException('Apenas administrador pode excluir item do budjet.');
      }
      $pdo->prepare("DELETE FROM logistica_budjet_itens WHERE id=:i AND departamento=:d")->execute(['i'=>$itemId, 'd'=>$dep]);
      sincronizarBudjetDepartamentosPorTemplate($pdo);
      header('Location: ?view=budjet&departamento=' . urlencode($dep) . '&updated=1'); exit;
    }
    if($acao==='criar_fornecedor'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $nome=trim((string)($_POST['nome']??'')); if($nome==='') throw new RuntimeException('Nome obrigatorio');
      $modalidade = in_array((string)($_POST['modalidade_credito'] ?? ''), ['Normal','Credito'], true) ? (string)$_POST['modalidade_credito'] : 'Normal';
      $saldoInicial = $modalidade === 'Credito' ? (float)($_POST['saldo_budjet']??0) : 0.0;
      $nomesRaw = $_POST['material_credito_nome'] ?? [];
      $espRaw = $_POST['material_credito_especificacoes'] ?? [];
      $qtdRaw = $_POST['material_credito_quantidade'] ?? [];
      $preRaw = $_POST['material_credito_preco'] ?? [];
      $totRaw = $_POST['material_credito_total'] ?? [];
      $obsRaw = $_POST['material_credito_observacoes'] ?? [];
      if(!is_array($nomesRaw)) $nomesRaw = [$nomesRaw];
      if(!is_array($espRaw)) $espRaw = [$espRaw];
      if(!is_array($qtdRaw)) $qtdRaw = [$qtdRaw];
      if(!is_array($preRaw)) $preRaw = [$preRaw];
      if(!is_array($totRaw)) $totRaw = [$totRaw];
      if(!is_array($obsRaw)) $obsRaw = [$obsRaw];
      $maxMatRows = max(count($nomesRaw), count($espRaw), count($qtdRaw), count($preRaw), count($totRaw), count($obsRaw));
      $linhasMaterial = [];
      for($i=0; $i<$maxMatRows; $i++){
        $mNome = trim((string)($nomesRaw[$i] ?? ''));
        $mEsp = trim((string)($espRaw[$i] ?? ''));
        $mQtd = (float)($qtdRaw[$i] ?? 0);
        $mPre = (float)($preRaw[$i] ?? 0);
        $mTotInfo = (float)($totRaw[$i] ?? 0);
        $mObs = trim((string)($obsRaw[$i] ?? ''));
        $temAlgoLinha = ($mNome !== '' || $mEsp !== '' || $mQtd > 0 || $mPre > 0 || $mTotInfo > 0 || $mObs !== '');
        if(!$temAlgoLinha) continue;
        if($mNome === ''){
          throw new RuntimeException('Informe o nome do material na linha ' . ($i + 1) . '.');
        }
        $mTotal = $mTotInfo > 0 ? $mTotInfo : (($mQtd > 0 && $mPre > 0) ? ($mQtd * $mPre) : 0.0);
        if($mTotal <= 0){
          throw new RuntimeException('Informe o total ou quantidade e preco validos na linha ' . ($i + 1) . '.');
        }
        $linhasMaterial[] = [
          'nome' => $mNome,
          'esp' => $mEsp,
          'qtd' => $mQtd > 0 ? $mQtd : 0.0,
          'pre' => $mPre > 0 ? $mPre : 0.0,
          'tot' => $mTotal,
          'obs' => $mObs
        ];
      }
      $temRegistoMaterial = !empty($linhasMaterial);
      if($temRegistoMaterial && $modalidade !== 'Credito'){
        throw new RuntimeException('Registo de material a credito so e permitido para fornecedor na modalidade Credito.');
      }

      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_fornecedores (nome,contacto,email,nuit,tipo_fornecedor,escopo_logistica,status,saldo_budjet,modalidade_credito,divida_atual,observacoes) VALUES (:n,:c,:e,:nu,:tipo,:esc,:st,:sb,:mc,0,:o)')
        ->execute(['n'=>$nome,'c'=>trim((string)($_POST['contacto']??''))?:null,'e'=>trim((string)($_POST['email']??''))?:null,'nu'=>trim((string)($_POST['nuit']??''))?:null,'tipo'=>trim((string)($_POST['tipo_fornecedor']??'Pecas')),'esc'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional','st'=>in_array($_POST['status']??'', ['Ativo','Inativo'],true)?$_POST['status']:'Ativo','sb'=>$saldoInicial,'mc'=>$modalidade,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $fornecedorIdNovo = (int)$pdo->lastInsertId();

      if($temRegistoMaterial && $fornecedorIdNovo > 0){
        $saldoRestante = $saldoInicial;
        $dividaTotal = 0.0;
        $saldoUsadoTotal = 0.0;
        $insMov = $pdo->prepare("INSERT INTO logistica_fornecedor_credito_mov (fornecedor_id,material_nome,especificacoes,quantidade,preco_unitario,total,saldo_usado,divida_gerada,observacoes) VALUES (:f,:m,:e,:q,:p,:t,:s,:d,:o)");
        foreach($linhasMaterial as $ln){
          $saldoUsadoLinha = min($saldoRestante, (float)$ln['tot']);
          $dividaLinha = max(0, (float)$ln['tot'] - $saldoUsadoLinha);
          $saldoRestante -= $saldoUsadoLinha;
          $saldoUsadoTotal += $saldoUsadoLinha;
          $dividaTotal += $dividaLinha;
          $insMov->execute([
            'f'=>$fornecedorIdNovo,
            'm'=>$ln['nome'],
            'e'=>$ln['esp']!==''?$ln['esp']:null,
            'q'=>$ln['qtd'],
            'p'=>$ln['pre'],
            't'=>$ln['tot'],
            's'=>$saldoUsadoLinha,
            'd'=>$dividaLinha,
            'o'=>$ln['obs']!==''?$ln['obs']:null
          ]);
        }
        $pdo->prepare("UPDATE logistica_fornecedores SET saldo_budjet=:sb, divida_atual=:d WHERE id=:i")
          ->execute(['sb'=>max(0, $saldoInicial - $saldoUsadoTotal),'d'=>$dividaTotal,'i'=>$fornecedorIdNovo]);
      }
      $pdo->commit();
      header('Location: ?view=requisicoes&compras_tab=fornecedores&mode=list&saved=1'); exit;
    }
    if($acao==='criar_peca'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $nome=trim((string)($_POST['nome']??'')); if($nome==='') throw new RuntimeException('Nome obrigatorio');
      $pdo->prepare('INSERT INTO logistica_pecas (codigo,nome,categoria,unidade,stock_atual,stock_minimo,preco_referencia,fornecedor_preferencial_id,escopo_logistica,area_aplicacao) VALUES (:cod,:n,:cat,:u,:sa,:sm,:pr,:fp,:esc,:ar)')->execute(['cod'=>trim((string)($_POST['codigo']??''))?:null,'n'=>$nome,'cat'=>trim((string)($_POST['categoria']??'Peca')),'u'=>trim((string)($_POST['unidade']??'un'))?:'un','sa'=>(float)($_POST['stock_atual']??0),'sm'=>(float)($_POST['stock_minimo']??0),'pr'=>(float)($_POST['preco_referencia']??0),'fp'=>((int)($_POST['fornecedor_preferencial_id']??0))>0?(int)$_POST['fornecedor_preferencial_id']:null,'esc'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional','ar'=>in_array($_POST['area_aplicacao']??'', ['oficina','transporte','geral'],true)?$_POST['area_aplicacao']:'oficina']);
      header('Location: ?view=pecas&mode=list&saved=1'); exit;
    }
    if($acao==='ajustar_stock'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $pecaRaw = $_POST['peca_id'] ?? [];
      $tipoRaw = $_POST['tipo_movimento'] ?? [];
      $fornRaw = $_POST['fornecedor_id'] ?? [];
      $projRaw = $_POST['projecto'] ?? [];
      $qtdRaw = $_POST['quantidade'] ?? [];
      $cusRaw = $_POST['custo_unitario'] ?? [];
      if(!is_array($pecaRaw)) $pecaRaw = [$pecaRaw];
      if(!is_array($tipoRaw)) $tipoRaw = [$tipoRaw];
      if(!is_array($fornRaw)) $fornRaw = [$fornRaw];
      if(!is_array($projRaw)) $projRaw = [$projRaw];
      if(!is_array($qtdRaw)) $qtdRaw = [$qtdRaw];
      if(!is_array($cusRaw)) $cusRaw = [$cusRaw];
      $maxRows = max(count($pecaRaw), count($tipoRaw), count($fornRaw), count($projRaw), count($qtdRaw), count($cusRaw));
      $linhas = [];
      for($i=0; $i<$maxRows; $i++){
        $id = (int)($pecaRaw[$i] ?? 0);
        $tipo = (string)($tipoRaw[$i] ?? 'Entrada');
        if(!in_array($tipo,['Entrada','Saida','Ajuste'],true)) $tipo='Entrada';
        $qtd = (float)($qtdRaw[$i] ?? 0);
        $custoUnit = (float)($cusRaw[$i] ?? 0);
        $fornId = ((int)($fornRaw[$i] ?? 0)) > 0 ? (int)$fornRaw[$i] : null;
        $projecto = projectoCanonico($projRaw[$i] ?? '');
        $temAlgum = $id > 0 || $qtd > 0 || $custoUnit > 0;
        if(!$temAlgum) continue;
        if($id<=0 || $qtd<=0 || $projecto==='') throw new RuntimeException('Cada movimento de stock deve ter peca, quantidade e projecto validos.');
        $linhas[] = ['id'=>$id,'tipo'=>$tipo,'qtd'=>$qtd,'custo'=>$custoUnit,'forn'=>$fornId,'projecto'=>$projecto];
      }
      if(!$linhas) throw new RuntimeException('Movimento invalido');
      $pdo->beginTransaction();
      $insMov = $pdo->prepare('INSERT INTO logistica_movimentos_stock (peca_id,tipo_movimento,fornecedor_id,projecto,quantidade,custo_unitario,referencia,observacoes,criado_por) VALUES (:p,:t,:f,:pr,:q,:c,:r,:o,:u)');
      $stPeca = $pdo->prepare("SELECT codigo,nome,area_aplicacao FROM logistica_pecas WHERE id=:i LIMIT 1");
      foreach($linhas as $ln){
        $insMov->execute([
          'p'=>$ln['id'],
          't'=>$ln['tipo'],
          'f'=>$ln['forn'],
          'pr'=>$ln['projecto'],
          'q'=>$ln['qtd'],
          'c'=>$ln['custo'],
          'r'=>trim((string)($_POST['referencia']??''))?:null,
          'o'=>trim((string)($_POST['observacoes']??''))?:null,
          'u'=>(int)($_SESSION['usuario_id']??0)
        ]);
        $movId = (int)$pdo->lastInsertId();
        if($ln['tipo']==='Entrada') {
          $pdo->prepare('UPDATE logistica_pecas SET stock_atual=stock_atual+:q WHERE id=:i')->execute(['q'=>$ln['qtd'],'i'=>$ln['id']]);
          $stPeca->execute(['i'=>$ln['id']]);
          $pecaRow = $stPeca->fetch(PDO::FETCH_ASSOC) ?: null;
          $valorEntrada = $ln['qtd'] * $ln['custo'];
          if($valorEntrada > 0){
            $dep = $ln['projecto'] === 'Projecto Transporte' ? 'transporte' : 'oficina';
            movimentarBudjet(
              $pdo,
              $dep,
              'debito',
              $valorEntrada,
              trim((string)($_POST['referencia']??'')) !== '' ? trim((string)$_POST['referencia']) : ('STK-ENT-' . $ln['id'] . '-' . date('YmdHis')),
              'Compra/entrada de stock: ' . (string)($pecaRow['nome'] ?? ('Peca #' . $ln['id'])),
              'logistica_movimentos_stock',
              $movId,
              (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
              'Controle de Stock',
              'Entrada de stock'
            );
          }
        }
        elseif($ln['tipo']==='Saida') $pdo->prepare('UPDATE logistica_pecas SET stock_atual=GREATEST(0,stock_atual-:q) WHERE id=:i')->execute(['q'=>$ln['qtd'],'i'=>$ln['id']]);
        else $pdo->prepare('UPDATE logistica_pecas SET stock_atual=:q WHERE id=:i')->execute(['q'=>$ln['qtd'],'i'=>$ln['id']]);
      }
      $pdo->commit(); header('Location: ?view=pecas&mode=list&updated=1'); exit;
    }
    if($acao==='criar_cotacao'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $item=trim((string)($_POST['item_nome']??''));
      if($item==='') throw new RuntimeException('Informe o item da cotacao.');

      $fornRaw = $_POST['fornecedor_id'] ?? [];
      $qtdRaw = $_POST['quantidade'] ?? [];
      $preRaw = $_POST['preco_unitario'] ?? [];
      if(!is_array($fornRaw)) $fornRaw = [$fornRaw];
      if(!is_array($qtdRaw)) $qtdRaw = [$qtdRaw];
      if(!is_array($preRaw)) $preRaw = [$preRaw];

      $maxRows = max(count($fornRaw), count($qtdRaw), count($preRaw));
      $linhas = [];
      for($i=0; $i<$maxRows; $i++){
        $fid = (int)($fornRaw[$i] ?? 0);
        $qtd = (float)($qtdRaw[$i] ?? 0);
        $pre = (float)($preRaw[$i] ?? 0);
        $temAlgum = $fid > 0 || $qtd > 0 || $pre > 0;
        if(!$temAlgum) continue;
        if($fid<=0 || $qtd<=0 || $pre<=0){
          throw new RuntimeException('Cada cotacao deve ter fornecedor, quantidade e preco validos.');
        }
        $linhas[] = ['idx'=>$i,'fid'=>$fid,'qtd'=>$qtd,'pre'=>$pre,'total'=>$qtd*$pre];
      }
      if(count($linhas) < 3){
        throw new RuntimeException('Adicione no minimo 3 cotacoes.');
      }
      $fornUnicos = [];
      foreach($linhas as $ln){ $fornUnicos[(string)$ln['fid']] = true; }
      if(count($fornUnicos) < 3){
        throw new RuntimeException('As 3 cotacoes devem ser de fornecedores diferentes.');
      }

      $salvarAnexo = static function(int $idx) : ?string {
        if(!isset($_FILES['anexo_cotacao']) || !is_array($_FILES['anexo_cotacao'])) return null;
        $names = $_FILES['anexo_cotacao']['name'] ?? null;
        if(!is_array($names)) return null;
        $err = (int)($_FILES['anexo_cotacao']['error'][$idx] ?? UPLOAD_ERR_NO_FILE);
        if($err === UPLOAD_ERR_NO_FILE) return null;
        if($err !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao anexar cotacao.');
        $tmp = (string)($_FILES['anexo_cotacao']['tmp_name'][$idx] ?? '');
        $orig = (string)($_FILES['anexo_cotacao']['name'][$idx] ?? '');
        $ext = strtolower((string)pathinfo($orig, PATHINFO_EXTENSION));
        $permit = ['pdf','png','jpg','jpeg','doc','docx','xls','xlsx'];
        if(!in_array($ext, $permit, true)) throw new RuntimeException('Formato de anexo nao permitido.');
        $dirFs = dirname(__DIR__, 3) . '/uploads/logistica/cotacoes';
        if(!is_dir($dirFs) && !@mkdir($dirFs, 0775, true)) throw new RuntimeException('Nao foi possivel criar pasta de anexos.');
        $file = 'cotacao_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destFs = $dirFs . '/' . $file;
        if(!@move_uploaded_file($tmp, $destFs)) throw new RuntimeException('Nao foi possivel guardar anexo da cotacao.');
        return '/vilcon-systemon/public/uploads/logistica/cotacoes/' . $file;
      };

      $ins = $pdo->prepare('INSERT INTO logistica_cotacoes (fornecedor_id,item_nome,categoria_item,preco_unitario,quantidade,total_cotacao,anexo_cotacao,prazo_dias,validade,escopo_logistica,area_solicitante,observacoes) VALUES (:f,:i,:c,:p,:q,:t,:an,:d,:v,:e,:a,:o)');
      foreach($linhas as $ln){
        $ins->execute([
          'f'=>$ln['fid'],
          'i'=>$item,
          'c'=>trim((string)($_POST['categoria_item']??'Peca')),
          'p'=>$ln['pre'],
          'q'=>$ln['qtd'],
          't'=>$ln['total'],
          'an'=>$salvarAnexo((int)$ln['idx']),
          'd'=>(int)($_POST['prazo_dias']??0),
          'v'=>trim((string)($_POST['validade']??''))?:null,
          'e'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional',
          'a'=>in_array($_POST['area_solicitante']??'', ['oficina','transporte','geral'],true)?$_POST['area_solicitante']:'oficina',
          'o'=>trim((string)($_POST['observacoes']??''))?:null
        ]);
      }
      header('Location: ?view=requisicoes&compras_tab=cotacoes&mode=list&saved=1'); exit;
    }
    if($acao==='registar_substituicao'){
      if(!$operacional_sem_restricao && !$pode_oper) throw new RuntimeException('Substituicoes exigem operacional');
      $pid=(int)($_POST['peca_id']??0); $mat=trim((string)($_POST['matricula_ativo']??'')); $qtd=(float)($_POST['quantidade']??1);
      if($pid<=0||$mat===''||$qtd<=0) throw new RuntimeException('Dados invalidos');
      $pdo->beginTransaction();
      $areaOrigem = in_array($_POST['area_origem']??'', ['Oficina','Transporte'],true)?$_POST['area_origem']:'Oficina';
      $custoUnitSub = (float)($_POST['custo_unitario']??0);
      $pdo->prepare('INSERT INTO logistica_pecas_substituidas (peca_id,matricula_ativo,area_origem,quantidade,custo_unitario,data_substituicao,motivo,responsavel,referencia_os) VALUES (:p,:m,:a,:q,:c,:d,:mo,:r,:os)')->execute(['p'=>$pid,'m'=>$mat,'a'=>$areaOrigem,'q'=>$qtd,'c'=>$custoUnitSub,'d'=>trim((string)($_POST['data_substituicao']??date('Y-m-d'))),'mo'=>trim((string)($_POST['motivo']??''))?:null,'r'=>trim((string)($_POST['responsavel']??''))?:null,'os'=>trim((string)($_POST['referencia_os']??''))?:null]);
      $pdo->prepare('UPDATE logistica_pecas SET stock_atual=GREATEST(0,stock_atual-:q) WHERE id=:i')->execute(['q'=>$qtd,'i'=>$pid]);
      $valorSub = $qtd * $custoUnitSub;
      if($valorSub > 0){
        movimentarBudjet(
          $pdo,
          strtolower($areaOrigem)==='transporte' ? 'transporte' : 'oficina',
          'debito',
          $valorSub,
          trim((string)($_POST['referencia_os'] ?? '')) !== '' ? trim((string)$_POST['referencia_os']) : ('SUB-' . date('YmdHis')),
          'Substituicao de peca: ' . $mat,
          'logistica_pecas_substituidas',
          null,
          (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
          'Controle de Stock',
          'Pecas substituidas'
        );
      }
      $pdo->commit(); header('Location: ?view=substituicoes&mode=list&saved=1'); exit;
    }
    if($acao==='criar_factura'){
      $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor_total']??0); $data=trim((string)($_POST['data_factura']??date('Y-m-d')));
      $projecto = projectoCanonico($_POST['projecto'] ?? '');
      if($descricao===''||$valor<=0||$projecto==='') throw new RuntimeException('Selecione o projecto (Oficina ou Transporte), descricao e valor da factura');
      $fid=((int)($_POST['fornecedor_id']??0))>0?(int)$_POST['fornecedor_id']:null;
      $departamento = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      $st=in_array($_POST['status']??'', ['Pendente','Pago','Parcial'], true)?$_POST['status']:'Pendente';
      $anexoDocumento = salvarUploadDocumento('anexo_documento', 'financeiro/facturas');
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_fin_facturas (fornecedor_id,departamento,projecto,descricao,valor_total,data_factura,status,anexo_documento,observacoes) VALUES (:f,:dep,:pr,:d,:v,:dt,:s,:an,:o)')
        ->execute(['f'=>$fid,'dep'=>$departamento,'pr'=>$projecto,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'s'=>$st,'an'=>$anexoDocumento,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $id=(int)$pdo->lastInsertId();
      $codigo=sprintf('FAT-%s-%04d',date('Y'),$id);
      $pdo->prepare('UPDATE logistica_fin_facturas SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);
      movimentarBudjet(
        $pdo,
        $departamento,
        'debito',
        $valor,
        $codigo,
        'Registo de factura',
        'logistica_fin_facturas',
        $id,
        (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
        'Financas',
        'Facturas'
      );
      $pdo->commit();
      header('Location: ?view=facturas&mode=list&saved=1'); exit;
    }
    if($acao==='criar_pagamento'){
      $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor_pago']??0); $data=trim((string)($_POST['data_pagamento']??date('Y-m-d')));
      $projecto = projectoCanonico($_POST['projecto'] ?? '');
      if($descricao===''||$valor<=0||$projecto==='') throw new RuntimeException('Selecione o projecto (Oficina ou Transporte), descricao e valor do pagamento');
      $facturaId=((int)($_POST['factura_id']??0))>0?(int)$_POST['factura_id']:null;
      $metodo=trim((string)($_POST['metodo']??'Transferencia')) ?: 'Transferencia';
      $anexoDocumento = salvarUploadDocumento('anexo_documento', 'financeiro/pagamentos');
      $pdo->prepare('INSERT INTO logistica_fin_pagamentos (tipo_registo,factura_id,projecto,descricao,valor_pago,data_pagamento,metodo,anexo_documento,observacoes) VALUES (:t,:f,:pr,:d,:v,:dt,:m,:an,:o)')
        ->execute(['t'=>'Pagamento','f'=>$facturaId,'pr'=>$projecto,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'m'=>$metodo,'an'=>$anexoDocumento,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $id=(int)$pdo->lastInsertId(); $codigo=sprintf('PAG-%s-%04d',date('Y'),$id); $pdo->prepare('UPDATE logistica_fin_pagamentos SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);
      header('Location: ?view=pagamentos&mode=list&saved=1'); exit;
    }
    if($acao==='criar_recibo'){
      $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor_pago']??0); $data=trim((string)($_POST['data_pagamento']??date('Y-m-d')));
      $projecto = projectoCanonico($_POST['projecto'] ?? '');
      if($descricao===''||$valor<=0||$projecto==='') throw new RuntimeException('Selecione o projecto (Oficina ou Transporte), descricao e valor do recibo');
      $facturaId=((int)($_POST['factura_id']??0))>0?(int)$_POST['factura_id']:null;
      $metodo=trim((string)($_POST['metodo']??'Transferencia')) ?: 'Transferencia';
      $anexoDocumento = salvarUploadDocumento('anexo_documento', 'financeiro/recibos');
      $pdo->prepare('INSERT INTO logistica_fin_pagamentos (tipo_registo,factura_id,projecto,descricao,valor_pago,data_pagamento,metodo,anexo_documento,observacoes) VALUES (:t,:f,:pr,:d,:v,:dt,:m,:an,:o)')
        ->execute(['t'=>'Recibo','f'=>$facturaId,'pr'=>$projecto,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'m'=>$metodo,'an'=>$anexoDocumento,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $id=(int)$pdo->lastInsertId(); $codigo=sprintf('REC-%s-%04d',date('Y'),$id); $pdo->prepare('UPDATE logistica_fin_pagamentos SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);
      header('Location: ?view=recibos&mode=list&saved=1'); exit;
    }
    if($acao==='criar_divida'){
      throw new RuntimeException('Dividas estao em modo de listagem apenas.');
    }
    if($acao==='criar_peca_avariada'){
      $nome=trim((string)($_POST['nome_peca']??'')); $qtd=(float)($_POST['quantidade']??0); $data=trim((string)($_POST['data_registo']??date('Y-m-d')));
      if($nome===''||$qtd<=0) throw new RuntimeException('Nome da peca e quantidade sao obrigatorios');
      $pid=((int)($_POST['peca_id']??0))>0?(int)$_POST['peca_id']:null;
      $codigo=trim((string)($_POST['codigo_peca']??'')) ?: null;
      $pdo->prepare('INSERT INTO logistica_pecas_avariadas (peca_id,codigo_peca,nome_peca,quantidade,motivo,data_registo,status,observacoes) VALUES (:p,:c,:n,:q,:m,:d,:s,:o)')
        ->execute(['p'=>$pid,'c'=>$codigo,'n'=>$nome,'q'=>$qtd,'m'=>trim((string)($_POST['motivo']??''))?:null,'d'=>$data,'s'=>'Avariada','o'=>trim((string)($_POST['observacoes']??''))?:null]);
      header('Location: ?view=pecas_avariadas&mode=list&saved=1'); exit;
    }
    if($acao==='criar_custo_operacional'){
      $categoria=trim((string)($_POST['categoria']??'')); $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor']??0); $data=trim((string)($_POST['data_lancamento']??date('Y-m-d')));
      if($categoria===''||$descricao===''||$valor<=0) throw new RuntimeException('Categoria, descricao e valor sao obrigatorios');
      $departamento = departamentoCanonico($_POST['departamento'] ?? 'transporte');
      $fornecedorId = ((int)($_POST['fornecedor_id'] ?? 0)) > 0 ? (int)$_POST['fornecedor_id'] : null;
      $formaPagamento = trim((string)($_POST['forma_pagamento'] ?? 'Numerario'));
      if(!in_array($formaPagamento, ['Numerario','Transferencia','Cheque','Credito','Cotacao'], true)) $formaPagamento = 'Numerario';
      $referenciaCotacao = trim((string)($_POST['referencia_cotacao'] ?? ''));
      if(in_array($formaPagamento, ['Credito','Cotacao'], true) && !$fornecedorId){
        throw new RuntimeException('Selecione o fornecedor quando o pagamento for a credito ou via cotacao.');
      }
      if($formaPagamento === 'Cotacao' && $referenciaCotacao === ''){
        throw new RuntimeException('Informe a referencia da cotacao para pagamento via cotacao.');
      }
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_operacional_custos (categoria,departamento,fornecedor_id,forma_pagamento,referencia_cotacao,descricao,valor,data_lancamento,responsavel,observacoes) VALUES (:c,:dep,:f,:fp,:rc,:d,:v,:dt,:r,:o)')
        ->execute(['c'=>$categoria,'dep'=>$departamento,'f'=>$fornecedorId,'fp'=>$formaPagamento,'rc'=>$referenciaCotacao!==''?$referenciaCotacao:null,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'r'=>trim((string)($_POST['responsavel']??''))?:null,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $idCusto = (int)$pdo->lastInsertId();
      movimentarBudjet(
        $pdo,
        $departamento,
        'debito',
        $valor,
        strtoupper(substr((string)$categoria, 0, 3)) . '-' . str_pad((string)$idCusto, 5, '0', STR_PAD_LEFT),
        'Custo operacional: ' . $descricao,
        'logistica_operacional_custos',
        $idCusto,
        (string)($_SESSION['usuario_nome'] ?? 'Logistica'),
        'Logistica Operacional',
        (string)$categoria
      );
      $pdo->commit();
      header('Location: ?view='.urlencode((string)($_POST['ret_view']??'oper_uniforme')).'&mode=list&saved=1'); exit;
    }
  }

  if(($_GET['saved']??'')==='1') $msg='Registo criado com sucesso.';
  if(($_GET['updated']??'')==='1') $msg='Registo atualizado com sucesso.';

  $w = '';
  if($escopo_filtro!=='todos') $w = " WHERE r.escopo_logistica=:esc";
  elseif(!$operacional_sem_restricao && $pode_oper&&!$pode_geral) $w = " WHERE r.escopo_logistica='operacional'";
  elseif(!$operacional_sem_restricao && $pode_geral&&!$pode_oper) $w = " WHERE r.escopo_logistica='geral'";
  $stmt=$pdo->prepare("SELECT r.*, COALESCE(r.valor_total, r.custo_total, 0) AS valor_total_calc FROM logistica_requisicoes r $w ORDER BY r.id DESC");
  if($escopo_filtro!=='todos' && strpos($w,':esc')!==false) $stmt->execute(['esc'=>$escopo_filtro]); else $stmt->execute();
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
  $requisicoes=array_values(array_filter($rows,function($r) use($q,$status_filtro,$departamento_filtro){ $t=strtolower(($r['codigo']??'').' '.($r['origem']??'').' '.($r['destino']??'').' '.($r['item']??'').' '.($r['responsavel']??'').' '.($r['area_solicitante']??'')); if($q!=='' && strpos($t,strtolower($q))===false) return false; if($status_filtro!=='todos'&&$status_filtro!==''&&st((string)($r['status']??''))!==strtolower($status_filtro)) return false; if($departamento_filtro!=='todos' && $departamento_filtro!=='' && strtolower((string)($r['area_solicitante']??''))!==strtolower($departamento_filtro)) return false; return true; }));

  $fornecedores=$pdo->query("SELECT * FROM logistica_fornecedores ORDER BY CASE WHEN LOWER(COALESCE(modalidade_credito,'Normal'))='credito' THEN 0 ELSE 1 END, id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  foreach($fornecedores as $f){ $fornNomePorId[(int)($f['id'] ?? 0)] = (string)($f['nome'] ?? ''); }
  $fornRef=$pdo->query("SELECT id,nome,modalidade_credito FROM logistica_fornecedores WHERE status='Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $fornCreditoRef = array_values(array_filter($fornecedores, static function(array $f): bool {
    return strtolower(trim((string)($f['status'] ?? ''))) === 'ativo'
      && strtolower(trim((string)($f['modalidade_credito'] ?? 'normal'))) === 'credito';
  }));
  $pecas=$pdo->query('SELECT p.*, f.nome AS fornecedor_preferencial FROM logistica_pecas p LEFT JOIN logistica_fornecedores f ON f.id=p.fornecedor_preferencial_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pecasRef=$pdo->query('SELECT id,codigo,nome FROM logistica_pecas ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $cotacoes=$pdo->query('SELECT c.*, f.nome AS fornecedor_nome FROM logistica_cotacoes c INNER JOIN logistica_fornecedores f ON f.id=c.fornecedor_id ORDER BY c.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $comp=$pdo->query("
    SELECT
      c.item_nome,
      c.categoria_item,
      MIN(c.preco_unitario) AS melhor_preco,
      MAX(c.preco_unitario) AS maior_preco,
      AVG(c.preco_unitario) AS preco_medio,
      COUNT(*) AS total_cotacoes,
      (
        SELECT f2.nome
        FROM logistica_cotacoes c2
        INNER JOIN logistica_fornecedores f2 ON f2.id = c2.fornecedor_id
        WHERE c2.item_nome = c.item_nome
          AND c2.categoria_item = c.categoria_item
        ORDER BY c2.preco_unitario ASC, c2.id DESC
        LIMIT 1
      ) AS melhor_fornecedor
    FROM logistica_cotacoes c
    GROUP BY c.item_nome, c.categoria_item
    ORDER BY c.item_nome ASC
  ")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $subs=$pdo->query('SELECT s.*, p.codigo AS peca_codigo, p.nome AS peca_nome FROM logistica_pecas_substituidas s INNER JOIN logistica_pecas p ON p.id=s.peca_id ORDER BY s.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pedidosOficina=$pdo->query("SELECT r.id,r.codigo,r.item,r.quantidade,r.unidade,r.status,r.prioridade,r.data_requisicao,r.responsavel,r.area_solicitante FROM logistica_requisicoes r WHERE r.origem_modulo='oficina' ORDER BY r.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  if($pedidos_prioridade!=='todos'){
    $pedidosOficina = array_values(array_filter($pedidosOficina, static function(array $p) use ($pedidos_prioridade): bool {
      return prioridadeCategoria((string)($p['prioridade'] ?? '')) === $pedidos_prioridade;
    }));
  }
  $facturas=$pdo->query("SELECT f.*, fr.nome AS fornecedor_nome FROM logistica_fin_facturas f LEFT JOIN logistica_fornecedores fr ON fr.id=f.fornecedor_id ORDER BY f.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pagamentos=$pdo->query("SELECT p.*, f.codigo AS factura_codigo FROM logistica_fin_pagamentos p LEFT JOIN logistica_fin_facturas f ON f.id=p.factura_id WHERE LOWER(COALESCE(p.tipo_registo,'pagamento'))='pagamento' ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $recibos=$pdo->query("SELECT p.*, f.codigo AS factura_codigo FROM logistica_fin_pagamentos p LEFT JOIN logistica_fin_facturas f ON f.id=p.factura_id WHERE LOWER(COALESCE(p.tipo_registo,'pagamento'))='recibo' ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $dividas=$pdo->query("SELECT d.*, fr.nome AS fornecedor_nome FROM logistica_fin_dividas d LEFT JOIN logistica_fornecedores fr ON fr.id=d.fornecedor_id ORDER BY d.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $ordensCompra=$pdo->query("SELECT o.*, f.nome AS fornecedor_nome, r.codigo AS requisicao_codigo FROM logistica_ordens_compra o LEFT JOIN logistica_fornecedores f ON f.id=o.fornecedor_id LEFT JOIN logistica_requisicoes r ON r.id=o.requisicao_id ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $cotacoesOrdem=$pdo->query("
    SELECT
      c.*,
      o.codigo AS ordem_codigo,
      o.assunto AS ordem_assunto,
      o.termo_pagamento,
      o.data_registo AS ordem_data_registo,
      f.nome AS fornecedor_nome,
      COALESCE(f.modalidade_credito,'Normal') AS fornecedor_modalidade
    FROM logistica_ordens_compra_cotacoes c
    INNER JOIN logistica_ordens_compra o ON o.id = c.ordem_id
    INNER JOIN logistica_fornecedores f ON f.id = c.fornecedor_id
    ORDER BY c.id DESC
  ")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $budjetItensCompra=$pdo->query("SELECT id,departamento,categoria,descricao,preco_unitario,saldo_pendente FROM logistica_budjet_itens ORDER BY departamento,categoria,ordem_item,id")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pecasAvariadas=$pdo->query("SELECT * FROM logistica_pecas_avariadas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $custosOperacionais=$pdo->query("SELECT * FROM logistica_operacional_custos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $budRows = $pdo->query("SELECT departamento,orcamento_total,saldo_atual,COALESCE(bloqueado,0) AS bloqueado,bloqueado_por,bloqueado_em FROM logistica_budjet_departamentos WHERE departamento IN ('oficina','transporte')")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $aggRows = $pdo->query("SELECT departamento, COALESCE(SUM(CASE WHEN tipo='Credito' THEN valor ELSE 0 END),0) AS total_creditos, COALESCE(SUM(CASE WHEN tipo='Debito' THEN valor ELSE 0 END),0) AS total_debitos FROM logistica_budjet_movimentos WHERE departamento IN ('oficina','transporte') GROUP BY departamento")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $aggMap = [];
  foreach($aggRows as $ar){ $aggMap[(string)$ar['departamento']] = $ar; }
  foreach(['oficina','transporte'] as $dep){
    $br = null;
    foreach($budRows as $b){ if((string)$b['departamento'] === $dep){ $br = $b; break; } }
    $orcDept = (float)($br['orcamento_total'] ?? 0);
    $saldoDept = (float)($br['saldo_atual'] ?? 0);
    $gastoPorSaldo = max(0, $orcDept - $saldoDept);
    $gastoMov = (float)($aggMap[$dep]['total_debitos'] ?? 0);
    $budjetResumo[$dep] = [
      'departamento' => $dep,
      'orcamento_total' => $orcDept,
      'saldo_atual' => $saldoDept,
      'total_creditos' => (float)($aggMap[$dep]['total_creditos'] ?? 0),
      'total_debitos' => max($gastoMov, $gastoPorSaldo),
      'bloqueado' => (int)($br['bloqueado'] ?? 0),
      'bloqueado_por' => (string)($br['bloqueado_por'] ?? ''),
      'bloqueado_em' => (string)($br['bloqueado_em'] ?? '')
    ];
  }
  if($budjetDepartamentoSelecionado !== ''){
    $stBudMov = $pdo->prepare("SELECT * FROM logistica_budjet_movimentos WHERE departamento=:d ORDER BY id DESC LIMIT 200");
    $stBudMov->execute(['d'=>$budjetDepartamentoSelecionado]);
    $budjetMovimentos = $stBudMov->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $stBudItens = $pdo->prepare("SELECT * FROM logistica_budjet_itens WHERE departamento=:d ORDER BY categoria ASC, ordem_item ASC, id ASC");
    $stBudItens->execute(['d'=>$budjetDepartamentoSelecionado]);
    $budjetItensTemplate = $stBudItens->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
  $impRows = $pdo->query("SELECT secao_modulo, COALESCE(SUM(CASE WHEN tipo='Debito' THEN valor ELSE 0 END),0) AS total_debitos FROM logistica_budjet_movimentos GROUP BY secao_modulo")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach($impRows as $ir){
    $k = (string)($ir['secao_modulo'] ?? '');
    if(isset($impactoSecoes[$k])) $impactoSecoes[$k] = (float)($ir['total_debitos'] ?? 0);
  }

  $totalFacturas = (float)($pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM logistica_fin_facturas")->fetchColumn() ?: 0);
  $totalPagamentos = (float)($pdo->query("SELECT COALESCE(SUM(valor_pago),0) FROM logistica_fin_pagamentos WHERE LOWER(COALESCE(tipo_registo,'pagamento'))='pagamento'")->fetchColumn() ?: 0);
  $totalRecibos = (float)($pdo->query("SELECT COALESCE(SUM(valor_pago),0) FROM logistica_fin_pagamentos WHERE LOWER(COALESCE(tipo_registo,'pagamento'))='recibo'")->fetchColumn() ?: 0);
  $totalDividas = (float)($pdo->query("SELECT COALESCE(SUM(valor_divida),0) FROM logistica_fin_dividas")->fetchColumn() ?: 0);
  $saldoPagar = $totalFacturas - $totalPagamentos;
  $rel['total_facturas'] = count($facturas);
  $rel['total_pagamentos'] = count($pagamentos);
  $rel['total_recibos'] = count($recibos);
  $rel['total_dividas'] = count($dividas);
  $rel['total_valor_facturas'] = $totalFacturas;
  $rel['total_valor_pagamentos'] = $totalPagamentos;
  $rel['total_valor_recibos'] = $totalRecibos;
  $rel['total_valor_dividas'] = $totalDividas;
  $rel['saldo_a_pagar'] = $saldoPagar;

  $di=date('Y-m-d'); $df=date('Y-m-d');
  if($periodo==='semanal'){ $dt=new DateTimeImmutable(); $n=(int)$dt->format('N'); $di=$dt->modify('-'.($n-1).' days')->format('Y-m-d'); $df=$dt->modify('+'.(7-$n).' days')->format('Y-m-d'); }
  elseif($periodo==='mensal'){ $dt=new DateTimeImmutable(); $di=$dt->modify('first day of this month')->format('Y-m-d'); $df=$dt->modify('last day of this month')->format('Y-m-d'); }
  $r=$pdo->prepare("SELECT (SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df) AS total_requisicoes,(SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df AND LOWER(status)='aprovada') AS requisicoes_aprovadas,(SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df AND LOWER(status)='negada') AS requisicoes_negadas,(SELECT COALESCE(SUM(COALESCE(valor_total,custo_total,0)),0) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df) AS total_valor_requisicoes,(SELECT COUNT(*) FROM logistica_fornecedores WHERE status='Ativo') AS fornecedores_ativos,(SELECT COUNT(*) FROM logistica_pecas WHERE stock_atual<=stock_minimo) AS pecas_stock_baixo,(SELECT COALESCE(SUM(stock_atual*preco_referencia),0) FROM logistica_pecas) AS valor_stock,(SELECT COALESCE(SUM(quantidade*custo_unitario),0) FROM logistica_pecas_substituidas WHERE data_substituicao BETWEEN :di AND :df) AS custo_substituicoes");
  $r->execute(['di'=>$di,'df'=>$df]); $rel = array_merge($rel, $r->fetch(PDO::FETCH_ASSOC)?:[]);

}catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); $erro='Nao foi possivel processar Logistica: '.$e->getMessage(); }

$total=count($requisicoes); $pend=0; $ap=0; $ng=0;
foreach($requisicoes as $r){ $s=st((string)($r['status']??'')); if($s==='pendente')$pend++; if(in_array($s,['aprovada','entregue'],true))$ap++; if(in_array($s,['negada','cancelada'],true))$ng++; }
$pedUrg=0; $pedMed=0; $pedBai=0;
foreach($pedidosOficina as $p){
  $cat = prioridadeCategoria((string)($p['prioridade'] ?? ''));
  if($cat==='urgente') $pedUrg++;
  elseif($cat==='baixo') $pedBai++;
  else $pedMed++;
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main-content">
    <style>
        :root {
            --logi-primary: var(--accent-orange);
            --logi-primary-dark: #ca6a17;
            --logi-dark: #1a1a1a;
            --logi-soft: #fdf2e9;
            --logi-border: #f3c99f;
        }
        body {
            background: #f8fafc !important;
            color: #0f172a !important;
        }
        .main-content,
        .dashboard-container {
            background: #f8fafc !important;
            color: #0f172a !important;
        }
        .main-content .top-bar {
            background: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 10px;
            padding: 10px 14px;
            margin: 12px 16px 0 16px;
            color: #0f172a !important;
        }
        .main-content .top-bar h2,
        .main-content .top-bar .user-info,
        .main-content .top-bar .user-info strong {
            color: #0f172a !important;
        }
        .logi-page { padding: 16px; background: transparent !important; }
        .logi-card { background: #fff !important; border: 1px solid #e5e7eb !important; border-radius: 12px; padding: 14px; }
        .logi-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .logi-tab { text-decoration: none; padding: 8px 12px; border-radius: 999px; border: 1px solid #d1d5db; color: #334155 !important; background: #fff !important; font-size: 13px; display:inline-flex; align-items:center; gap:8px; font-weight:700; }
        .logi-tab i { width:26px; height:26px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#eff6ff; color:#1d4ed8; font-size:13px; }
        .logi-tab:hover { transform:translateY(-1px); box-shadow:0 5px 14px rgba(15,23,42,.10); }
        .logi-tab.active { background: var(--logi-soft) !important; color: var(--logi-dark) !important; border-color: var(--logi-primary) !important; box-shadow:0 8px 20px rgba(249,115,22,.18); }
        .logi-tab.active i { background:#fb923c; color:#fff; }
        .logi-kpis { display: grid; grid-template-columns: repeat(4,minmax(120px,1fr)); gap: 8px; margin-bottom: 10px; }
        .logi-kpi { border: 1px solid #e5e7eb; background: #f8fafc !important; border-radius: 10px; padding: 10px; font-size: 13px; color:#0f172a !important; }
        .logi-filters, .logi-form, .logi-inline-form { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .logi-filters input, .logi-filters select, .logi-form input, .logi-form select, .logi-inline-form input, .logi-inline-form select {
            border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 13px; min-height: 36px;
        }
        .logi-filters button, .logi-form button, .logi-inline-form button, .logi-action-btn {
            border: 1px solid var(--logi-primary); border-radius: 8px; background: var(--logi-primary); color: #fff; padding: 8px 12px; font-size: 13px; cursor: pointer;
        }
        .logi-filters button:hover, .logi-form button:hover, .logi-inline-form button:hover, .logi-action-btn:hover { background: var(--logi-primary-dark); border-color: var(--logi-primary-dark); }
        .logi-toggle { margin-bottom: 10px; display: flex; gap: 8px; }
        .logi-toggle a { text-decoration: none; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; color: #334155; font-size: 13px; }
        .logi-toggle a.active { background: var(--logi-soft); border-color: var(--logi-primary); color: var(--logi-dark); }
        .logi-subtabs {
            background: #f3f4f6;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .logi-subtabs .logi-subtab-btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        .logi-subtabs .logi-subtab-btn.active {
            background: #ffffff;
            color: #111827;
            box-shadow: 0 2px 4px rgba(0,0,0,.08);
            border-color: #e5e7eb;
        }
        .logi-subtabs.compras-nav {
            background: linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);
            border: 1px solid #dbe4ef;
            gap: 10px;
            padding: 10px;
        }
        .logi-subtabs.compras-nav .logi-subtab-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 180px;
            border-radius: 12px;
            padding: 10px 12px;
            background: #ffffff;
            border: 1px solid #dbe4ef;
            text-transform: none;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(15,23,42,.06);
            transition: all .15s ease;
        }
        .logi-subtabs.compras-nav .logi-subtab-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15,23,42,.12);
        }
        .logi-subtabs.compras-nav .logi-subtab-btn.active {
            background: linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);
            border-color: #fb923c;
            box-shadow: 0 10px 22px rgba(249,115,22,.20);
        }
        .compras-ico {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            background: #eff6ff;
            color: #1d4ed8;
            flex: 0 0 auto;
        }
        .logi-subtabs.compras-nav .logi-subtab-btn.active .compras-ico {
            background: #fb923c;
            color: #fff;
        }
        .compras-title { display:block; font-weight:800; color:#0f172a; line-height:1.1; }
        .compras-desc { display:block; font-size:11px; color:#64748b; margin-top:2px; line-height:1.15; }
        .ord-search-wrap { position: relative; width: 100%; }
        .ord-search-wrap input {
            width:100%;
            padding-left:10px !important;
            padding-right:40px !important;
        }
        .ord-search-wrap .ord-search-btn {
            position:absolute;
            right:4px;
            top:50%;
            transform:translateY(-50%);
            width:30px;
            height:30px;
            border-radius:8px;
            border:1px solid #d1d5db;
            background:#fff;
            color:#1d4ed8;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            padding:0;
        }
        .ord-search-wrap .ord-search-btn:hover {
            background:#eff6ff;
            border-color:#93c5fd;
        }
        .ord-search-wrap input:focus,
        .ord-search-wrap .ord-search-btn:focus {
            outline:none;
            border-color:#93c5fd;
            box-shadow:0 0 0 2px rgba(59,130,246,.15);
        }
        .ord-search-sugestoes {
            position:absolute;
            left:0;
            right:0;
            top:calc(100% + 4px);
            width:100%;
            border:1px solid #d1d5db;
            border-radius:10px;
            background:#fff;
            max-height:180px;
            overflow:auto;
            z-index:40;
            box-shadow:0 10px 22px rgba(15,23,42,.14);
        }
        .ord-search-sugestoes.hidden { display:none; }
        .ord-search-item {
            width:100%;
            border:0;
            border-bottom:1px solid #e5e7eb;
            background:#fff;
            text-align:left;
            padding:8px 10px;
            font-size:12px;
            color:#0f172a;
            cursor:pointer;
        }
        .ord-search-item:last-child { border-bottom:0; }
        .ord-search-item:hover { background:#f8fafc; }
        .ord-search-empty {
            padding:8px 10px;
            font-size:12px;
            color:#991b1b;
            background:#fff1f2;
        }
        .ord-step-actions { width:100%; display:flex; justify-content:flex-end; margin-top:6px; }
        .ord-step-actions .next { background:#1d4ed8; border-color:#1d4ed8; }
        .ord-step-actions .next:hover { background:#1e40af; border-color:#1e40af; }
        .ord-step-erro {
            width:100%;
            margin-top:6px;
            border-radius:8px;
            border:1px solid #fecaca;
            background:#fff1f2;
            color:#991b1b;
            font-size:12px;
            padding:8px 10px;
            display:none;
        }
        .logi-subtabs.stock-nav { background: linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%); gap:10px; padding:10px; border:1px solid #dbe4ef; }
        .logi-subtabs.stock-nav .logi-subtab-btn {
            display:flex;
            align-items:center;
            gap:10px;
            min-width:180px;
            border-radius:12px;
            padding:10px 12px;
            background:#ffffff;
            border:1px solid #dbe4ef;
            text-transform:none;
            font-size:12px;
            box-shadow:0 1px 3px rgba(15,23,42,.06);
        }
        .logi-subtabs.stock-nav .logi-subtab-btn.active {
            background:linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);
            border-color:#fb923c;
            box-shadow:0 8px 20px rgba(249,115,22,.20);
        }
        .stock-sub-ico {
            width:34px;
            height:34px;
            border-radius:10px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:14px;
            background:#eff6ff;
            color:#1d4ed8;
            flex:0 0 auto;
        }
        .logi-subtabs.stock-nav .logi-subtab-btn.active .stock-sub-ico {
            background:#fb923c;
            color:#fff;
        }
        .stock-sub-title { display:block; font-weight:800; color:#0f172a; line-height:1.1; }
        .stock-sub-desc { display:block; font-size:11px; color:#64748b; margin-top:2px; line-height:1.15; }
        .logi-alert { border-radius: 8px; padding: 9px 10px; margin-bottom: 10px; font-size: 13px; }
        .logi-alert.error { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; }
        .logi-alert.success { color: #166534; background: #ecfdf3; border: 1px solid #bbf7d0; }
        .logi-table-wrap { overflow-x: auto; }
        .logi-table { width: 100%; border-collapse: collapse; }
        .logi-table th, .logi-table td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 10px 8px; font-size: 13px; vertical-align: top; }
        .logi-table th { font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: .02em; background: #f8fafc !important; }
        .logi-status { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 11px; border: 1px solid transparent; }
        .logi-status.ok { background: #dcfce7; border-color: #bbf7d0; color: #166534; }
        .logi-status.warn { background: #fef3c7; border-color: #fde68a; color: #92400e; }
        .logi-status.info { background: #fdf2e9; border-color: #f3c99f; color: #7c2d12; }
        .logi-status.danger { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
        .logi-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .logi-action-link {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 10px;
            border-radius:999px;
            text-decoration:none;
            border:1px solid #d1d5db;
            background:#fff;
            color:#334155;
            font-size:12px;
            font-weight:700;
            transition: all .15s ease;
        }
        .logi-action-link i { font-size:13px; }
        .logi-action-link:hover { transform: translateY(-1px); }
        .logi-action-link.stock { border-color:#bfdbfe; color:#1d4ed8; background:#eff6ff; }
        .logi-action-link.req { border-color:#fed7aa; color:#c2410c; background:#fff7ed; }
        .logi-action-link.cot { border-color:#c7d2fe; color:#4338ca; background:#eef2ff; }
        .logi-action-link.buy { border-color:#86efac; color:#166534; background:#f0fdf4; }
        .logi-action-link.buy.urgent { border-color:#fecaca; color:#991b1b; background:#fff1f2; }
        .stock-metrics { display:grid; grid-template-columns:repeat(4,minmax(160px,1fr)); gap:10px; margin-bottom:12px; }
        .stock-metric { border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:12px; }
        .stock-metric .ic { width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:8px; }
        .stock-metric .k { font-size:12px; color:#64748b; }
        .stock-metric .v { font-size:22px; font-weight:800; color:#0f172a; line-height:1.1; }
        .stock-metric .s { font-size:12px; color:#475569; margin-top:4px; }
        .stock-metric.ok .ic { background:#dcfce7; color:#166534; }
        .ordem-bloco { width:100%; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:10px; margin-bottom:8px; }
        .ordem-bloco h4 { margin:0 0 8px 0; font-size:13px; color:#0f172a; text-transform:uppercase; letter-spacing:.04em; }
        .ordem-grid { display:grid; grid-template-columns:repeat(4,minmax(180px,1fr)); gap:8px; }
        .ordem-grid > * { min-width:0; }
        .ordem-grid input, .ordem-grid select { width:100%; min-width:0; max-width:100%; }
        .ordens-head {
            display:grid;
            grid-template-columns:36px minmax(170px,1.2fr) minmax(150px,1fr) 100px 72px 108px 84px 84px;
            gap:8px;
            width:100%;
            font-size:11px;
            text-transform:uppercase;
            color:#64748b;
            font-weight:700;
            padding:0 2px;
        }
        .ordem-linha {
            display:grid;
            grid-template-columns:36px minmax(170px,1.2fr) minmax(150px,1fr) 100px 72px 108px 84px 84px;
            gap:8px;
            width:100%;
            align-items:center;
        }
        .ordem-linha-num {
            width:36px;
            height:36px;
            border-radius:9px;
            border:1px solid #cbd5e1;
            background:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:12px;
            font-weight:700;
            color:#334155;
        }
        .btn_mandar_item_ordem { background:#0f766e !important; border-color:#0f766e !important; }
        .btn_mandar_item_ordem:hover { background:#115e59 !important; border-color:#115e59 !important; }
        @media (max-width: 980px){
            .ordem-grid { grid-template-columns:repeat(2,minmax(180px,1fr)); }
            .ordens-head { display:none; }
            .ordem-linha {
                grid-template-columns:1fr;
                border:1px solid #e2e8f0;
                border-radius:10px;
                padding:8px;
                background:#fff;
            }
        }
        .stock-metric.warn .ic { background:#fef3c7; color:#92400e; }
        .stock-metric.danger .ic { background:#fee2e2; color:#991b1b; }
        .stock-metric.info .ic { background:#dbeafe; color:#1d4ed8; }
        .stock-toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; align-items:center; }
        .stock-toolbar input, .stock-toolbar select { max-width:260px; }
        .budjet-grid { display:grid; grid-template-columns:repeat(2,minmax(280px,1fr)); gap:16px; }
        .budjet-card { border:1px solid #fed7aa; border-radius:16px; padding:18px; background:linear-gradient(155deg,#ffffff 0%,#fff7ed 50%,#ffedd5 100%); box-shadow:0 10px 22px rgba(249,115,22,.16); }
        .budjet-card h3 { margin:0 0 6px 0; font-size:22px; color:#0f172a; }
        .budjet-card .budjet-icon { font-size:28px; color:var(--logi-primary); margin-bottom:8px; background:#fff; width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; border:1px solid #fed7aa; }
        .budjet-card .budjet-value { font-size:28px; font-weight:800; color:var(--logi-dark); margin:6px 0 10px; }
        .budjet-card .budjet-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
        .budjet-progress { margin:10px 0 8px; }
        .budjet-progress .lbl { display:flex; justify-content:space-between; font-size:11px; color:#64748b; margin-bottom:4px; }
        .budjet-progress .bar { width:100%; height:8px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
        .budjet-progress .fill { height:100%; background:linear-gradient(90deg,#f97316 0%,#fb923c 100%); }
        .budjet-switch { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .budjet-switch a { display:inline-flex; align-items:center; gap:6px; border:1px solid #d1d5db; border-radius:999px; padding:7px 12px; text-decoration:none; color:#334155; background:#fff; font-size:12px; font-weight:700; }
        .budjet-switch a.active { border-color:#fb923c; background:#fff7ed; color:#9a3412; box-shadow:0 4px 10px rgba(249,115,22,.18); }
        .budjet-meta { color:#475569; font-size:13px; margin-bottom:10px; }
        .budjet-detail-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px; flex-wrap:wrap; }
        .budjet-pill { display:inline-block; border:1px solid var(--logi-border); color:#7c2d12; background:var(--logi-soft); border-radius:999px; padding:5px 10px; font-size:12px; }
        .budjet-resumo { display:grid; grid-template-columns:repeat(3,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
        .budjet-box { border:1px solid #e2e8f0; border-radius:10px; background:#fff; padding:10px; }
        .budjet-box .k { font-size:12px; color:#64748b; }
        .budjet-box .v { font-size:20px; font-weight:700; color:#0f172a; }
        .budjet-reforco { margin:10px 0 14px; padding:10px; border:1px dashed var(--logi-primary); border-radius:10px; background:var(--logi-soft); }
        .budjet-reforco .logi-inline-form { margin:0; }
        .budjet-lock-banner { display:flex; justify-content:space-between; align-items:center; gap:12px; border-radius:10px; padding:10px 12px; margin-bottom:12px; border:1px solid #e2e8f0; background:#f8fafc; }
        .budjet-lock-banner.locked { border-color:#fecaca; background:#fff1f2; }
        .budjet-lock-banner.open { border-color:#bbf7d0; background:#f0fdf4; }
        .budjet-alert-box { margin:10px 0 12px; border:1px solid #fecaca; background:#fff7ed; color:#9a3412; border-radius:10px; padding:10px 12px; font-size:13px; }
        .budjet-alert-box.ok { border-color:#bbf7d0; background:#f0fdf4; color:#166534; }
        .budjet-tools { display:flex; justify-content:space-between; align-items:center; gap:10px; margin:8px 0 12px; flex-wrap:wrap; }
        .budjet-tools input { min-width:260px; }
        .budjet-action-bar { display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; margin:8px 0 12px; }
        .budjet-action-bar .right { display:flex; gap:8px; flex-wrap:wrap; }
        .budjet-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .budjet-form-card { border:1px solid #fed7aa; border-radius:12px; background:linear-gradient(180deg,#fff 0%,#fff7ed 100%); padding:12px; }
        .budjet-form-card h4 { margin:0 0 10px 0; font-size:13px; text-transform:uppercase; color:#9a3412; letter-spacing:.04em; }
        .budjet-form-card .logi-inline-form { display:grid; grid-template-columns:repeat(2,minmax(160px,1fr)); gap:8px; margin:0; }
        .budjet-form-card .logi-inline-form input,
        .budjet-form-card .logi-inline-form button,
        .budjet-form-card .logi-inline-form a { width:100%; }
        .btn-icon-only { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; border:1px solid #d1d5db; background:#fff; color:#334155; cursor:pointer; }
        .btn-icon-only.danger { border-color:#fecaca; color:#b91c1c; background:#fff1f2; }
        .btn-icon-only:hover { transform:translateY(-1px); }
        @media print {
            .top-bar, .logi-tabs, .logi-subtabs, .budjet-action-bar, .budjet-switch, .budjet-reforco, .budjet-form-grid, .budjet-tools, .budjet-lock-banner form, .btn-icon-only { display:none !important; }
            .dashboard-container { box-shadow:none !important; border:none !important; }
        }
        .impacto-grid { display:grid; grid-template-columns:repeat(4,minmax(160px,1fr)); gap:10px; margin-bottom:12px; }
        .impacto-card { border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:10px; }
        .impacto-card .k { font-size:12px; color:#64748b; }
        .impacto-card .v { font-size:18px; font-weight:800; color:#0f172a; margin-top:3px; }
        .impacto-card.active { border-color:#fb923c; box-shadow:0 6px 14px rgba(249,115,22,.16); background:#fff7ed; }
        .logi-budget-note { width:100%; font-size:12px; color:#7c2d12; background:#fff8f2; border:1px dashed #f3c99f; border-radius:8px; padding:8px 10px; }
        @media (max-width: 900px) { .logi-kpis { grid-template-columns: repeat(2,minmax(120px,1fr)); } .stock-metrics { grid-template-columns:repeat(2,minmax(140px,1fr)); } }
        @media (max-width: 900px) { .budjet-grid { grid-template-columns: 1fr; } .budjet-resumo { grid-template-columns: 1fr; } .impacto-grid{grid-template-columns:repeat(2,minmax(140px,1fr));} .budjet-form-grid{grid-template-columns:1fr;} .budjet-form-card .logi-inline-form{grid-template-columns:1fr;} }
    </style>

    <div class="top-bar">
        <h2>Logistica</h2>
        <div class="user-info"><strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong></div>
    </div>

        <div class="dashboard-container logi-page">
            <div class="logi-tabs">
            <a href="?view=pedidos_oficina" class="logi-tab <?= $view==='pedidos_oficina' ? 'active' : '' ?>"><i class="fa-solid fa-inbox"></i><span>Formularios recebidos</span></a>
            <a href="?view=requisicoes&mode=list" class="logi-tab <?= in_array($view,['requisicoes','fornecedores'],true) ? 'active' : '' ?>"><i class="fa-solid fa-cart-shopping"></i><span>Compras</span></a>
            <a href="?view=extratos" class="logi-tab <?= $secao==='financas' ? 'active' : '' ?>"><i class="fa-solid fa-file-invoice-dollar"></i><span>Financas</span></a>
            <a href="?view=pecas" class="logi-tab <?= $secao==='stock' ? 'active' : '' ?>"><i class="fa-solid fa-boxes-stacked"></i><span>Controle de Stock</span></a>
            <a href="?view=oper_uniforme" class="logi-tab <?= $secao==='operacional' ? 'active' : '' ?>"><i class="fa-solid fa-truck-fast"></i><span>Logistica Operacional</span></a>
            <a href="?view=budjet" class="logi-tab <?= $secao==='budjet' ? 'active' : '' ?>"><i class="fa-solid fa-wallet"></i><span>Budjet</span></a>
            <a href="?view=alertas" class="logi-tab <?= $secao==='alertas' ? 'active' : '' ?>"><i class="fa-solid fa-bell"></i><span>Alertas</span></a>
            <a href="?view=relatorios" class="logi-tab <?= $view==='relatorios' ? 'active' : '' ?>"><i class="fa-solid fa-chart-column"></i><span>Relatorios</span></a>
        </div>
        <?php $opcoesSecao = opcoesSecaoLogistica($secao); ?>
        <?php if(!empty($opcoesSecao) && $view!=='requisicoes'): ?>
            <div class="logi-subtabs <?= $secao==='stock' ? 'stock-nav' : '' ?>">
                <?php
                    $stockMeta = [
                        'pecas' => ['ico' => 'fa-warehouse', 'desc' => 'Armazem de pecas'],
                        'substituicoes' => ['ico' => 'fa-arrow-up-right-dots', 'desc' => 'Saidas'],
                        'pecas_avariadas' => ['ico' => 'fa-triangle-exclamation', 'desc' => 'Entradas'],
                    ];
                ?>
                <?php foreach($opcoesSecao as $k => $lbl): ?>
                    <?php if($secao==='stock'): ?>
                        <?php $m = $stockMeta[$k] ?? ['ico'=>'fa-boxes-stacked','desc'=>'']; ?>
                        <a class="logi-subtab-btn <?= $view===$k ? 'active' : '' ?>" href="?view=<?= urlencode((string)$k) ?>&mode=list">
                            <span class="stock-sub-ico"><i class="fa-solid <?= htmlspecialchars((string)$m['ico']) ?>"></i></span>
                            <span>
                                <span class="stock-sub-title"><?= htmlspecialchars((string)$lbl) ?></span>
                                <span class="stock-sub-desc"><?= htmlspecialchars((string)$m['desc']) ?></span>
                            </span>
                        </a>
                    <?php else: ?>
                        <a class="logi-subtab-btn <?= $view===$k ? 'active' : '' ?>" href="?view=<?= urlencode((string)$k) ?>&mode=list"><?= htmlspecialchars((string)$lbl) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="logi-card">
            <?php if($erro): ?><div class="logi-alert error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if($msg): ?><div class="logi-alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <?php
                $mapaSecaoImpacto = ['requisicoes'=>'Compras','financas'=>'Financas','stock'=>'Controle de Stock','operacional'=>'Logistica Operacional'];
                $secaoImpactoAtiva = $mapaSecaoImpacto[$secao] ?? '';
            ?>
            <?php if($secaoImpactoAtiva !== ''): ?>
                <div class="impacto-grid">
                    <?php foreach($impactoSecoes as $kImpacto => $vImpacto): ?>
                        <div class="impacto-card <?= $secaoImpactoAtiva===$kImpacto ? 'active' : '' ?>">
                            <div class="k"><?= htmlspecialchars($kImpacto) ?></div>
                            <div class="v"><?= htmlspecialchars(money((float)$vImpacto)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if(in_array($view,['fornecedores','pecas','cotacoes','substituicoes','facturas','recibos','pagamentos','pecas_avariadas','oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true)): ?>
                <div class="logi-toggle">
                    <a class="<?= $mode==='list' ? 'active' : '' ?>" href="?view=<?= urlencode((string)$view) ?>&mode=list">Lista</a>
                    <a class="<?= $mode==='form' ? 'active' : '' ?>" href="?view=<?= urlencode((string)$view) ?>&mode=form">Novo Registo</a>
                </div>
            <?php endif; ?>

            <?php if($view==='requisicoes'): ?>
                <div class="logi-subtabs compras-nav">
                    <?php
                        $comprasNav = [
                            'ordens_compras' => ['label' => 'Ordens de compras', 'ico' => 'fa-file-invoice-dollar', 'desc' => 'Fluxo principal de compra'],
                            'requisicoes' => ['label' => 'Requisicoes', 'ico' => 'fa-clipboard-list', 'desc' => 'Pedidos internos'],
                            'cotacoes' => ['label' => 'Cotacoes', 'ico' => 'fa-file-signature', 'desc' => 'Pre pago e Pos pago'],
                            'fornecedores' => ['label' => 'Fornecedores', 'ico' => 'fa-building-user', 'desc' => 'Normal e a credito'],
                        ];
                    ?>
                    <?php foreach($comprasNav as $k => $meta): ?>
                        <a class="logi-subtab-btn <?= ($view==='requisicoes' && $compras_tab===$k) ? 'active' : '' ?>" href="?view=requisicoes&mode=list&compras_tab=<?= urlencode((string)$k) ?>">
                            <span class="compras-ico"><i class="fa-solid <?= htmlspecialchars((string)$meta['ico']) ?>"></i></span>
                            <span>
                                <span class="compras-title"><?= htmlspecialchars((string)$meta['label']) ?></span>
                                <span class="compras-desc"><?= htmlspecialchars((string)$meta['desc']) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if($view==='requisicoes' && $compras_tab==='requisicoes'): ?>
                    <div class="logi-toggle">
                        <a class="<?= $mode==='list' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=requisicoes&mode=list">Ver Lista</a>
                        <a class="<?= $mode==='form' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=requisicoes&mode=form">Adicionar Novo</a>
                    </div>
                    <?php if($mode==='form'): ?>
                        <form method="POST" class="logi-form js-form-ordem" enctype="multipart/form-data">
                            <input type="hidden" name="acao" value="criar_requisicao">
                            <input type="hidden" name="origem" value="Logistica">
                            <input type="hidden" name="destino" value="Compras">
                            <input type="hidden" name="escopo_logistica" value="operacional">
                            <select name="categoria_item" required>
                                <option value="">Categoria da requisicao</option>
                                <option value="Pecas">Pecas</option>
                                <option value="Controle de EPs">Controle de EPs</option>
                                <option value="Ferramentas">Ferramentas</option>
                                <option value="Alimentacao">Alimentacao</option>
                                <option value="Custos de portagem">Custos de portagem</option>
                                <option value="Multas">Multas</option>
                                <option value="Seguros">Seguros</option>
                                <option value="Taxas de radios">Taxas de radios</option>
                                <option value="Extintores">Extintores</option>
                                <option value="Manutencoes">Manutencoes</option>
                            </select>
                            <select name="area_solicitante" required>
                                <option value="">Projecto</option>
                                <option value="oficina">Projecto Oficina</option>
                                <option value="transporte">Projecto Transporte</option>
                            </select>
                            <div id="requisicoes_linhas" style="display:flex;flex-direction:column;gap:8px;width:100%;">
                                <div class="logi-inline-form requisicao-linha" style="margin:0;">
                                    <input name="item[]" placeholder="Item / descricao da requisicao" required>
                                    <input name="finalidade[]" placeholder="Pra que fim (finalidade)" required>
                                    <input type="number" class="req-qtd" name="quantidade[]" step="0.01" min="0.01" value="1" placeholder="Quantidade" required>
                                    <input name="unidade[]" value="un" placeholder="Unidade">
                                    <input type="number" class="req-preco" name="preco_unitario[]" step="0.01" min="0.01" placeholder="Valor unitario (MZN)" required>
                                    <input type="number" class="req-total" name="valor_total[]" step="0.01" min="0" placeholder="Total (MZN)" readonly>
                                    <button type="button" class="btn_remove_requisicao">- Menos</button>
                                </div>
                            </div>
                            <button type="button" id="btn_add_requisicao">+ Requisicao</button>
                            <select name="prioridade">
                                <option value="Urgente">Urgente</option>
                                <option value="Normal" selected>Medio</option>
                                <option value="Baixo">Baixo</option>
                            </select>
                            <input type="date" name="data_requisicao" value="<?= date('Y-m-d') ?>" required>
                            <input name="responsavel" value="<?= htmlspecialchars((string)($_SESSION['usuario_nome'] ?? '')) ?>" placeholder="Responsavel">
                            <select name="tipo_fornecedor_requisicao" class="js-tipo-fornecedor-req">
                                <option value="credito">Fornecedores a credito</option>
                                <option value="normal">Fornecedores normal</option>
                            </select>
                            <select name="fornecedor_credito_id" class="js-fornecedor-req">
                                <option value="">Selecionar fornecedor (opcional)</option>
                                <?php foreach($fornecedores as $f): ?>
                                    <?php if(strtolower(trim((string)($f['status'] ?? ''))) !== 'ativo') continue; ?>
                                    <?php $modFornecedor = strtolower(modalidadeFornecedor((string)($f['modalidade_credito'] ?? 'Normal'))); ?>
                                    <option value="<?= (int)$f['id'] ?>" data-modalidade="<?= htmlspecialchars($modFornecedor) ?>">
                                        <?= htmlspecialchars((string)$f['nome']) ?> - <?= htmlspecialchars(modalidadeFornecedorLabel((string)($f['modalidade_credito'] ?? 'Normal'))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input name="observacoes" placeholder="Observacoes">
                            <button>Guardar requisicao e mandar para ordem de compra</button>
                        </form>
                    <?php else: ?>
                        <div class="logi-table-wrap">
                            <table class="logi-table">
                                <tr><th>Codigo</th><th>Categoria</th><th>Projecto</th><th>Item</th><th>Finalidade</th><th>Quantidade</th><th>Valor Unit.</th><th>Total</th><th>Fornecedor credito</th><th>Prioridade</th><th>Status</th><th>Responsavel</th><th>Data</th></tr>
                                <?php if(!$requisicoes): ?><tr><td colspan="13">Sem requisicoes registadas.</td></tr><?php endif; ?>
                                <?php foreach($requisicoes as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($r['codigo'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['categoria_item'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)(projectoCanonico((string)($r['area_solicitante'] ?? '')) ?: ucfirst((string)($r['area_solicitante'] ?? '-')))) ?></td>
                                        <td><?= htmlspecialchars((string)($r['item'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['finalidade'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)(($r['quantidade'] ?? '0') . ' ' . ($r['unidade'] ?? ''))) ?></td>
                                        <td><?= htmlspecialchars(money((float)($r['preco_unitario'] ?? 0))) ?></td>
                                        <td><?= htmlspecialchars(money((float)($r['valor_total'] ?? $r['custo_total'] ?? 0))) ?></td>
                                        <td><?= htmlspecialchars((string)($fornNomePorId[(int)($r['fornecedor_id'] ?? 0)] ?? '-')) ?></td>
                                        <td><span class="logi-status <?= prioridadeCategoria((string)($r['prioridade'] ?? 'Normal')) === 'urgente' ? 'danger' : (prioridadeCategoria((string)($r['prioridade'] ?? 'Normal')) === 'baixo' ? 'ok' : 'warn') ?>"><?= htmlspecialchars(prioridadeLabel((string)($r['prioridade'] ?? 'Normal'))) ?></span></td>
                                        <td><span class="logi-status <?= badge((string)($r['status'] ?? '')) ?>"><?= htmlspecialchars(stLabel((string)($r['status'] ?? ''))) ?></span></td>
                                        <td><?= htmlspecialchars((string)($r['responsavel'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($r['data_requisicao'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if($compras_tab==='cotacoes'): ?>
                    <div class="logi-alert" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">
                        Aqui aparecem apenas as cotacoes ja feitas aos fornecedores (Pre pago e Pos pago).
                    </div>
                    <?php
                        $cotacoesOrdemPagas = array_values(array_filter($cotacoesOrdem, static function(array $c): bool {
                            $t = trim((string)($c['termo_pagamento'] ?? ''));
                            if(strtolower($t) === 'requisicao') $t = 'Pos pago';
                            return in_array($t, ['Pre pago','Pos pago'], true);
                        }));
                    ?>
                    <div class="logi-table-wrap">
                        <table class="logi-table">
                            <tr><th>Ordem</th><th>Assunto</th><th>Termo pag.</th><th>Fornecedor</th><th>Modalidade</th><th>Preco</th><th>Anexo</th><th>Data</th></tr>
                            <?php if(!$cotacoesOrdemPagas): ?><tr><td colspan="8">Sem cotacoes de Pre pago/Pos pago registadas.</td></tr><?php endif; ?>
                            <?php foreach($cotacoesOrdemPagas as $c): ?>
                                <?php $termoCot = trim((string)($c['termo_pagamento'] ?? '')); if(strtolower($termoCot) === 'requisicao') $termoCot = 'Pos pago'; ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($c['ordem_codigo'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($c['ordem_assunto'] ?? '-')) ?></td>
                                    <td><span class="logi-status <?= $termoCot==='Pre pago' ? 'info' : 'warn' ?>"><?= htmlspecialchars($termoCot !== '' ? $termoCot : '-') ?></span></td>
                                    <td><?= htmlspecialchars((string)($c['fornecedor_nome'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars(modalidadeFornecedorLabel((string)($c['fornecedor_modalidade'] ?? 'Normal'))) ?></td>
                                    <td><?= htmlspecialchars(money((float)($c['preco'] ?? 0))) ?></td>
                                    <td>
                                        <?php if(!empty($c['anexo_cotacao'])): ?>
                                            <a href="<?= htmlspecialchars((string)$c['anexo_cotacao']) ?>" target="_blank" rel="noopener">Abrir</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($c['created_at'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
                <?php if($compras_tab==='fornecedores'): ?>
                    <div class="logi-toggle">
                        <a class="<?= $mode==='list' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=fornecedores&mode=list">Ver Lista</a>
                        <a class="<?= $mode==='form' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=fornecedores&mode=form">Adicionar Novo</a>
                    </div>
                    <?php if($mode==='form'): ?>
                        <form method="POST" class="logi-form js-fornecedor-form">
                            <input type="hidden" name="acao" value="criar_fornecedor">
                            <input type="hidden" name="escopo_logistica" value="operacional">
                            <input name="nome" placeholder="Nome" required>
                            <input name="contacto" placeholder="Contacto">
                            <input name="email" placeholder="Email">
                            <select name="modalidade_credito" class="forn-modalidade">
                                <option value="Normal">Fornecedor normal</option>
                                <option value="Credito">Fornecedor a credito</option>
                            </select>
                            <input type="number" name="saldo_budjet" min="0" step="0.01" value="0" placeholder="Saldo para credito">
                            <small style="font-size:12px;color:#64748b;">Se o fornecedor for a credito e o saldo acabar, o restante da compra vira divida.</small>
                            <div class="forn-credito-box" style="display:none;width:100%;border:1px dashed #f3c99f;padding:10px;border-radius:10px;background:#fff8f2;">
                                <div class="forn-credito-linhas" style="display:flex;flex-direction:column;gap:8px;">
                                    <div class="logi-inline-form material-credito-linha" style="margin:0;">
                                        <input name="material_credito_nome[]" placeholder="Material (opcional)">
                                        <input name="material_credito_especificacoes[]" placeholder="Especificacoes (opcional)">
                                        <input type="number" class="mat-qtd" name="material_credito_quantidade[]" min="0" step="0.01" placeholder="Quantidade">
                                        <input type="number" class="mat-preco" name="material_credito_preco[]" min="0" step="0.01" placeholder="Preco">
                                        <input type="number" class="mat-total" name="material_credito_total[]" min="0" step="0.01" placeholder="Total">
                                        <input name="material_credito_observacoes[]" placeholder="Obs. (opcional)">
                                        <button type="button" class="btn_remove_material_credito">-</button>
                                    </div>
                                </div>
                                <button type="button" class="btn_add_material_credito">+ Material</button>
                            </div>
                            <button>Guardar</button>
                        </form>
                    <?php else: ?>
                        <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Nome</th><th>Contacto</th><th>Modalidade</th><th>Status</th><th>Saldo credito</th><th>Divida</th></tr><?php if(!$fornecedores): ?><tr><td colspan="7">Sem fornecedores registados.</td></tr><?php endif; ?><?php foreach($fornecedores as $f): ?><tr><td><?= (int)$f['id'] ?></td><td><?= htmlspecialchars((string)$f['nome']) ?></td><td><?= htmlspecialchars((string)($f['contacto']??'-')) ?></td><td><span class="logi-status <?= modalidadeFornecedorBadge((string)($f['modalidade_credito'] ?? 'Normal')) ?>"><?= htmlspecialchars(modalidadeFornecedorLabel((string)($f['modalidade_credito'] ?? 'Normal'))) ?></span></td><td><span class="logi-status <?= badge((string)$f['status']) ?>"><?= htmlspecialchars((string)$f['status']) ?></span></td><td><?= htmlspecialchars(money((float)($f['saldo_budjet'] ?? 0))) ?></td><td><?= htmlspecialchars(money((float)($f['divida_atual'] ?? 0))) ?></td></tr><?php endforeach; ?></table></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if($compras_tab==='ordens_compras'): ?>
                    <div class="logi-toggle">
                        <a class="<?= $mode==='list' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=ordens_compras&mode=list">Ver Lista</a>
                        <a class="<?= $mode==='form' ? 'active' : '' ?>" href="?view=requisicoes&compras_tab=ordens_compras&mode=form">Nova Ordem</a>
                    </div>
                    <?php if($mode==='form'): ?>
                        <form method="POST" class="logi-form" enctype="multipart/form-data">
                            <?php
                                $requisicoesAprovadas = array_values(array_filter($requisicoes, static function(array $r): bool {
                                    $st = strtolower(trim((string)($r['status'] ?? '')));
                                    return in_array($st, ['aprovada','entregue'], true);
                                }));
                                $requisicoesOrigem = $requisicoesAprovadas;
                                if($ordem_requisicao_prefill > 0){
                                    $requisicoesOrigem = array_values(array_filter($requisicoes, static function(array $r) use ($ordem_requisicao_prefill): bool {
                                        return (int)($r['id'] ?? 0) === $ordem_requisicao_prefill;
                                    }));
                                    if(!$requisicoesOrigem){
                                        $requisicoesOrigem = array_values(array_filter($requisicoesAprovadas, static function(array $r) use ($ordem_requisicao_prefill): bool {
                                            return (int)($r['id'] ?? 0) === $ordem_requisicao_prefill;
                                        }));
                                    }
                                }
                            ?>
                            <input type="hidden" name="acao" value="criar_ordem_compra">
                            <div class="ordem-bloco">
                                <h4>Dados da ordem</h4>
                                <div class="ordem-grid">
                                    <input name="assunto" value="<?= htmlspecialchars($ordem_assunto_prefill) ?>" placeholder="Assunto" required>
                                    <input name="ordem_numero_preview" value="Numero automatico ao guardar" readonly>
                                    <input name="solicitante" value="<?= htmlspecialchars($ordem_solicitante_prefill !== '' ? $ordem_solicitante_prefill : (string)($_SESSION['usuario_nome'] ?? 'Logistica')) ?>" placeholder="Solicitante" required>
                                    <input type="hidden" name="destino" value="<?= htmlspecialchars($ordem_destino_prefill !== '' ? $ordem_destino_prefill : 'Compras') ?>">
                                    <select name="prioridade">
                                        <option value="Urgente" <?= prioridadeCategoria($ordem_prioridade_prefill)==='urgente' ? 'selected' : '' ?>>Urgente</option>
                                        <option value="Normal" <?= prioridadeCategoria($ordem_prioridade_prefill)==='medio' ? 'selected' : '' ?>>Medio</option>
                                        <option value="Baixo" <?= prioridadeCategoria($ordem_prioridade_prefill)==='baixo' ? 'selected' : '' ?>>Baixo</option>
                                    </select>
                                    <select name="requisicao_id">
                                        <option value="">Requisicao origem (opcional)</option>
                                        <?php foreach($requisicoesOrigem as $r): ?>
                                            <option value="<?= (int)$r['id'] ?>" <?= $ordem_requisicao_prefill===(int)$r['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)($r['codigo'] ?? 'REQ')) ?> - <?= htmlspecialchars((string)($r['item'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="ord-search-wrap">
                                        <input type="text" class="js-ord-fornecedor-search" placeholder="Pesquisar fornecedor...">
                                        <button type="button" class="ord-search-btn js-ord-fornecedor-filtrar" aria-label="Pesquisar fornecedor">
                                            <i class="fa-solid fa-magnifying-glass"></i>
                                        </button>
                                        <div class="ord-search-sugestoes js-ord-fornecedor-sugestoes hidden"></div>
                                    </div>
                                    <select name="fornecedor_id" class="js-ord-fornecedor" required style="display:none;">
                                        <option value="">Selecionar</option>
                                        <?php foreach($fornRef as $f): ?>
                                            <?php $modFornOrdem = modalidadeFornecedor((string)($f['modalidade_credito'] ?? 'Normal')); ?>
                                            <option value="<?= (int)$f['id'] ?>" data-modalidade="<?= htmlspecialchars(strtolower($modFornOrdem)) ?>">
                                                <?= htmlspecialchars((string)$f['nome']) ?> - <?= htmlspecialchars(modalidadeFornecedorLabel((string)($f['modalidade_credito'] ?? 'Normal'))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="projecto" class="js-ord-projecto" required>
                                        <option value="">Projecto</option>
                                        <option value="Projecto Oficina" <?= $ordem_projecto_prefill==='Projecto Oficina' ? 'selected' : '' ?>>Projecto Oficina</option>
                                        <option value="Projecto Transporte" <?= $ordem_projecto_prefill==='Projecto Transporte' ? 'selected' : '' ?>>Projecto Transporte</option>
                                    </select>
                                    <input type="hidden" name="data_entrega" value="">
                                    <input type="date" name="data_registo" value="<?= date('Y-m-d') ?>" required>
                                    <select name="termo_pagamento" class="js-ord-termo" required>
                                        <option value="Pre pago">Pre pago</option>
                                        <option value="Pos pago" selected>Pos pago</option>
                                    </select>
                                    <select name="moeda">
                                        <option value="MT">MT</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                    <input type="hidden" name="cambio" value="1">
                                    <select name="categoria" class="js-ord-categoria">
                                        <option value="">Categoria</option>
                                    </select>
                                    <select name="subcategoria" class="js-ord-subcategoria">
                                        <option value="">Seleccione a Subcategoria</option>
                                    </select>
                                </div>
                                <input name="observacao" placeholder="Observacao">
                                <div class="ord-step-actions">
                                    <button type="button" id="btn_ir_itens_ordem" class="next js-ord-ir-itens">Continuar para Itens da ordem</button>
                                </div>
                                <div class="ord-step-erro js-ord-step-erro"></div>
                            </div>

                            <div class="js-ord-etapa-itens" style="display:none;width:100%;">
                            <div class="ordem-bloco js-ord-cotacoes-bloco">
                                <h4>Cotacoes anexas</h4>
                                <div id="ordem_cotacoes_linhas" style="display:flex;flex-direction:column;gap:8px;width:100%;">
                                    <?php for($i=0;$i<3;$i++): ?>
                                        <div class="logi-inline-form ordem-cot-linha" style="margin:0;">
                                            <select name="fornecedor_cotacao_id[]" class="js-ord-cot-fornecedor" data-required-prepago="1">
                                                <option value="">Fornecedor</option>
                                                <?php foreach($fornRef as $f): ?>
                                                    <?php $modFornCot = modalidadeFornecedor((string)($f['modalidade_credito'] ?? 'Normal')); ?>
                                                    <option value="<?= (int)$f['id'] ?>" data-modalidade="<?= htmlspecialchars(strtolower($modFornCot)) ?>">
                                                        <?= htmlspecialchars((string)$f['nome']) ?> - <?= htmlspecialchars(modalidadeFornecedorLabel((string)($f['modalidade_credito'] ?? 'Normal'))) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" value="1" readonly>
                                            <input type="number" name="preco_cotacao[]" class="js-ord-cot-preco" step="0.01" min="0.01" placeholder="Preco" data-required-prepago="1">
                                            <input type="file" name="anexo_cotacao_ordem[]" class="js-ord-cot-anexo" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx">
                                            <button type="button" class="btn_remove_ordem_cotacao">- Menos</button>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <button type="button" id="btn_add_ordem_cotacao">+ Cotacao</button>
                            </div>

                            <div class="ordem-bloco">
                                <h4>Itens da ordem</h4>
                                <div class="ordens-head">
                                    <span>#</span>
                                    <span>Artigo/Servico do budjet</span>
                                    <span>Artigo/Servico</span>
                                    <span>Custo forn</span>
                                    <span>Qtd</span>
                                    <span>Sub Total</span>
                                    <span>Mandar</span>
                                    <span>Acoes</span>
                                </div>
                                <div id="ordens_linhas" style="display:flex;flex-direction:column;gap:8px;width:100%;margin-top:6px;">
                                    <div class="ordem-linha">
                                        <div class="ordem-linha-num">1</div>
                                        <select name="budjet_item_id[]" class="js-ord-buditem">
                                            <option value="">Artigo/Servico do budjet</option>
                                        </select>
                                        <input name="artigo_servico[]" class="ord-artigo" placeholder="Artigo/Servico" required>
                                        <input type="number" name="custo_forn[]" class="ord-custo" step="0.01" min="0" placeholder="Custo forn" required>
                                        <input type="number" name="quantidade_item[]" class="ord-qtd" step="0.01" min="0.01" value="1" placeholder="Qtd" required>
                                        <input type="text" class="ord-subtotal" placeholder="Sub Total" readonly>
                                        <button type="button" class="btn_mandar_item_ordem">Mandar</button>
                                        <button type="button" class="btn_remove_ordem">- Menos</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="btn_add_ordem">+ Artigo/Servico</button>
                            <div class="logi-budget-note">Total da ordem: <strong class="js-ord-total">0,00 MZN</strong></div>
                            <button>Enviar para aprovacao</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="logi-table-wrap">
                            <table class="logi-table">
                                <tr><th>Ordem</th><th>Requisicao</th><th>Assunto</th><th>Solicitante</th><th>Destino</th><th>Prioridade</th><th>Fornecedor</th><th>Projecto</th><th>Termo pag.</th><th>Total</th><th>Status</th><th>Data reg.</th><th>Budjet</th><th>Acoes</th></tr>
                                <?php if(!$ordensCompra): ?><tr><td colspan="14">Sem ordens de compra registadas.</td></tr><?php endif; ?>
                                <?php foreach($ordensCompra as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)($o['codigo'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($o['requisicao_codigo'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($o['assunto'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($o['solicitante'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($o['destino'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars(prioridadeLabel((string)($o['prioridade'] ?? 'Normal'))) ?></td>
                                        <td><?= htmlspecialchars((string)($o['fornecedor_nome'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string)($o['projecto'] ?? '-')) ?></td>
                                        <?php $termoOrd = trim((string)($o['termo_pagamento'] ?? '-')); if(strtolower($termoOrd) === 'requisicao') $termoOrd = 'Pos pago'; ?>
                                        <td><?= htmlspecialchars($termoOrd) ?></td>
                                        <td><?= htmlspecialchars(money((float)($o['valor_total'] ?? 0))) ?></td>
                                        <td><span class="logi-status <?= badge((string)($o['status'] ?? 'Pendente')) ?>"><?= htmlspecialchars((string)($o['status'] ?? 'Pendente')) ?></span></td>
                                        <td><?= htmlspecialchars((string)($o['data_registo'] ?? '-')) ?></td>
                                        <td><?= ((int)($o['budjet_debitado'] ?? 0) === 1) ? 'Debitado na finalizacao' : 'Pendente por finalizar compra' ?></td>
                                        <td>
                                            <?php if(strtolower(trim((string)($o['status'] ?? ''))) !== 'finalizada'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="acao" value="finalizar_ordem_compra">
                                                    <input type="hidden" name="ordem_id" value="<?= (int)$o['id'] ?>">
                                                    <button type="submit">Finalizar compra</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="logi-status ok">Finalizada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif($view==='pedidos_oficina'): ?>
                <form method="GET" class="logi-filters">
                    <input type="hidden" name="view" value="pedidos_oficina">
                    <input type="hidden" name="mode" value="list">
                    <select name="pedidos_prioridade">
                        <option value="todos" <?= $pedidos_prioridade==='todos'?'selected':'' ?>>Prioridade: Todos</option>
                        <option value="urgente" <?= $pedidos_prioridade==='urgente'?'selected':'' ?>>Prioridade: Urgente</option>
                        <option value="medio" <?= $pedidos_prioridade==='medio'?'selected':'' ?>>Prioridade: Medio</option>
                        <option value="baixo" <?= $pedidos_prioridade==='baixo'?'selected':'' ?>>Prioridade: Baixo</option>
                    </select>
                    <button>Aplicar filtro</button>
                    <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=pedidos_oficina&mode=list&pedidos_prioridade=todos">Limpar</a>
                </form>
                <div class="logi-kpis">
                    <div class="logi-kpi">Urgente: <b><?= (int)$pedUrg ?></b></div>
                    <div class="logi-kpi">Medio: <b><?= (int)$pedMed ?></b></div>
                    <div class="logi-kpi">Baixo: <b><?= (int)$pedBai ?></b></div>
                    <div class="logi-kpi">Total: <b><?= count($pedidosOficina) ?></b></div>
                </div>
                <div class="logi-table-wrap">
                    <table class="logi-table">
                        <tr><th>Codigo</th><th>Item</th><th>Quantidade</th><th>Stock</th><th>Prioridade</th><th>Status</th><th>Responsavel</th><th>Data</th><th>Acoes</th></tr>
                        <?php if(!$pedidosOficina): ?><tr><td colspan="9">Sem formularios recebidos para os filtros selecionados.</td></tr><?php endif; ?>
                        <?php
                            $stockPorNome = [];
                            foreach($pecas as $pc){
                                $k = norm((string)($pc['nome'] ?? ''));
                                if($k === '') continue;
                                $stockPorNome[$k] = (float)($stockPorNome[$k] ?? 0) + (float)($pc['stock_atual'] ?? 0);
                            }
                        ?>
                        <?php foreach($pedidosOficina as $p): ?>
                            <?php
                                $itemPedido = (string)($p['item'] ?? '');
                                $itemKey = norm($itemPedido);
                                $qtdPedido = (float)($p['quantidade'] ?? 0);
                                $stockAtualPedido = (float)($stockPorNome[$itemKey] ?? 0);
                                $semStock = $stockAtualPedido < $qtdPedido || $stockAtualPedido <= 0;
                                $projectoPedido = (norm((string)($p['area_solicitante'] ?? '')) === 'transporte') ? 'Projecto Transporte' : 'Projecto Oficina';
                                $urlSubmeterCompra = '?view=requisicoes&compras_tab=ordens_compras&mode=form'
                                    . '&requisicao_id=' . (int)($p['id'] ?? 0)
                                    . '&assunto=' . rawurlencode($itemPedido)
                                    . '&projecto=' . rawurlencode($projectoPedido)
                                    . '&solicitante=' . rawurlencode((string)($p['responsavel'] ?? ''))
                                    . '&destino=' . rawurlencode('Compras')
                                    . '&prioridade=' . rawurlencode((string)($p['prioridade'] ?? 'Normal'));
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($p['codigo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($p['item'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)(($p['quantidade'] ?? '0') . ' ' . ($p['unidade'] ?? ''))) ?></td>
                                <td><span class="logi-status <?= $semStock ? 'danger' : 'ok' ?>"><?= $semStock ? 'Sem stock' : 'Stock suficiente' ?></span></td>
                                <td><span class="logi-status <?= prioridadeCategoria((string)($p['prioridade'] ?? '')) === 'urgente' ? 'danger' : (prioridadeCategoria((string)($p['prioridade'] ?? '')) === 'baixo' ? 'ok' : 'warn') ?>"><?= htmlspecialchars(prioridadeLabel((string)($p['prioridade'] ?? ''))) ?></span></td>
                                <td><span class="logi-status <?= badge((string)($p['status'] ?? '')) ?>"><?= htmlspecialchars(stLabel((string)($p['status'] ?? ''))) ?></span></td>
                                <td><?= htmlspecialchars((string)($p['responsavel'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($p['data_requisicao'] ?? '-')) ?></td>
                                <td>
                                    <div class="logi-actions">
                                        <a class="logi-action-link stock" href="?view=pecas&mode=list" title="Ver stock de pecas" aria-label="Ver stock de pecas">
                                            <i class="fa-solid fa-warehouse"></i><span>Stock</span>
                                        </a>
                                        <a class="logi-action-link buy <?= $semStock ? 'urgent' : '' ?>" href="<?= htmlspecialchars($urlSubmeterCompra) ?>" title="Submeter compra do pedido" aria-label="Submeter compra do pedido">
                                            <i class="fa-solid fa-cart-plus"></i><span>Submeter compra</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

            <?php elseif($view==='extratos'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Fornecedor</th><th>Projecto</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Status</th></tr><?php if(!$dividas): ?><tr><td colspan="7">Sem dividas registadas.</td></tr><?php endif; ?><?php foreach($dividas as $d): ?><tr><td><?= htmlspecialchars((string)($d['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($d['fornecedor_nome'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($d['projecto'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($d['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($d['valor_divida'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($d['data_divida'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($d['status'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='facturas' && $mode==='form'): ?>
                <form method="POST" class="logi-form" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="criar_factura">
                    <select name="fornecedor_id"><option value="">Fornecedor (opcional)</option><?php foreach($fornRef as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option><?php endforeach; ?></select>
                    <select name="departamento" required><option value="oficina">Oficina</option><option value="transporte">Transporte</option></select>
                    <select name="projecto" required><option value="">Projecto</option><option value="Projecto Oficina">Projecto Oficina</option><option value="Projecto Transporte">Projecto Transporte</option></select>
                    <input name="descricao" placeholder="Descricao da factura" required>
                    <input type="number" name="valor_total" min="0.01" step="0.01" placeholder="Valor total" required>
                    <div class="logi-budget-note">Valor a abater do Budjet: <strong class="js-abate-factura">0,00 MZN</strong></div>
                    <input type="date" name="data_factura" value="<?= date('Y-m-d') ?>" required>
                    <select name="status"><option>Pendente</option><option>Parcial</option><option>Pago</option></select>
                    <input type="file" name="anexo_documento" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx">
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar factura</button>
                </form>
            <?php elseif($view==='facturas'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Departamento</th><th>Projecto</th><th>Fornecedor</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Status</th><th>Anexo</th></tr><?php if(!$facturas): ?><tr><td colspan="9">Sem facturas registadas.</td></tr><?php endif; ?><?php foreach($facturas as $f): ?><tr><td><?= htmlspecialchars((string)($f['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars(ucfirst((string)($f['departamento'] ?? 'oficina'))) ?></td><td><?= htmlspecialchars((string)($f['projecto'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($f['fornecedor_nome'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($f['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($f['valor_total'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($f['data_factura'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($f['status'] ?? '-')) ?></td><td><?php if(!empty($f['anexo_documento'])): ?><a href="<?= htmlspecialchars((string)$f['anexo_documento']) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='pagamentos' && $mode==='form'): ?>
                <form method="POST" class="logi-form" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="criar_pagamento">
                    <select name="factura_id"><option value="">Factura (opcional)</option><?php foreach($facturas as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)(($f['codigo'] ?? 'FAT') . ' - ' . ($f['descricao'] ?? ''))) ?></option><?php endforeach; ?></select>
                    <select name="projecto" required><option value="">Projecto</option><option value="Projecto Oficina">Projecto Oficina</option><option value="Projecto Transporte">Projecto Transporte</option></select>
                    <input name="descricao" placeholder="Descricao do pagamento" required>
                    <input type="number" name="valor_pago" min="0.01" step="0.01" placeholder="Valor pago" required>
                    <div class="logi-budget-note">Pagamento nao abate Budjet novamente quando a factura ja foi lancada.</div>
                    <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
                    <select name="metodo"><option>Transferencia</option><option>Cheque</option><option>Numerario</option><option>Carteira movel</option></select>
                    <input type="file" name="anexo_documento" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx">
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar pagamento</button>
                </form>
            <?php elseif($view==='pagamentos'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Factura</th><th>Projecto</th><th>Descricao</th><th>Valor pago</th><th>Data</th><th>Metodo</th><th>Anexo</th></tr><?php if(!$pagamentos): ?><tr><td colspan="8">Sem pagamentos registados.</td></tr><?php endif; ?><?php foreach($pagamentos as $p): ?><tr><td><?= htmlspecialchars((string)($p['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['factura_codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['projecto'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($p['valor_pago'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($p['data_pagamento'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['metodo'] ?? '-')) ?></td><td><?php if(!empty($p['anexo_documento'])): ?><a href="<?= htmlspecialchars((string)$p['anexo_documento']) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='recibos' && $mode==='form'): ?>
                <form method="POST" class="logi-form" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="criar_recibo">
                    <select name="factura_id"><option value="">Factura (opcional)</option><?php foreach($facturas as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)(($f['codigo'] ?? 'FAT') . ' - ' . ($f['descricao'] ?? ''))) ?></option><?php endforeach; ?></select>
                    <select name="projecto" required><option value="">Projecto</option><option value="Projecto Oficina">Projecto Oficina</option><option value="Projecto Transporte">Projecto Transporte</option></select>
                    <input name="descricao" placeholder="Descricao do recibo" required>
                    <input type="number" name="valor_pago" min="0.01" step="0.01" placeholder="Valor do recibo" required>
                    <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
                    <select name="metodo"><option>Transferencia</option><option>Cheque</option><option>Numerario</option><option>Carteira movel</option></select>
                    <input type="file" name="anexo_documento" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx">
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar recibo</button>
                </form>
            <?php elseif($view==='recibos'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Recibo</th><th>Factura</th><th>Projecto</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Metodo</th><th>Anexo</th></tr><?php if(!$recibos): ?><tr><td colspan="8">Sem recibos emitidos.</td></tr><?php endif; ?><?php foreach($recibos as $p): ?><tr><td><?= htmlspecialchars((string)($p['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['factura_codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['projecto'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($p['valor_pago'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($p['data_pagamento'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['metodo'] ?? '-')) ?></td><td><?php if(!empty($p['anexo_documento'])): ?><a href="<?= htmlspecialchars((string)$p['anexo_documento']) ?>" target="_blank" rel="noopener">Abrir</a><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='fornecedores' && $mode==='form'): ?>
                <form method="POST" class="logi-form js-fornecedor-form">
                    <input type="hidden" name="acao" value="criar_fornecedor">
                    <input type="hidden" name="escopo_logistica" value="operacional">
                    <input name="nome" placeholder="Nome" required>
                    <input name="contacto" placeholder="Contacto">
                    <input name="email" placeholder="Email">
                    <select name="modalidade_credito" class="forn-modalidade">
                        <option value="Normal">Fornecedor normal</option>
                        <option value="Credito">Fornecedor a credito</option>
                    </select>
                    <input type="number" name="saldo_budjet" min="0" step="0.01" value="0" placeholder="Saldo para credito">
                    <small style="font-size:12px;color:#64748b;">Se o fornecedor for a credito e o saldo acabar, o restante da compra vira divida.</small>
                    <div class="forn-credito-box" style="display:none;width:100%;border:1px dashed #f3c99f;padding:10px;border-radius:10px;background:#fff8f2;">
                        <div class="forn-credito-linhas" style="display:flex;flex-direction:column;gap:8px;">
                            <div class="logi-inline-form material-credito-linha" style="margin:0;">
                                <input name="material_credito_nome[]" placeholder="Material (opcional)">
                                <input name="material_credito_especificacoes[]" placeholder="Especificacoes (opcional)">
                                <input type="number" class="mat-qtd" name="material_credito_quantidade[]" min="0" step="0.01" placeholder="Quantidade">
                                <input type="number" class="mat-preco" name="material_credito_preco[]" min="0" step="0.01" placeholder="Preco">
                                <input type="number" class="mat-total" name="material_credito_total[]" min="0" step="0.01" placeholder="Total">
                                <input name="material_credito_observacoes[]" placeholder="Obs. (opcional)">
                                <button type="button" class="btn_remove_material_credito">-</button>
                            </div>
                        </div>
                        <button type="button" class="btn_add_material_credito">+ Material</button>
                    </div>
                    <button>Guardar</button>
                </form>
            <?php elseif($view==='fornecedores'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Nome</th><th>Contacto</th><th>Modalidade</th><th>Status</th><th>Saldo credito</th><th>Divida</th></tr><?php if(!$fornecedores): ?><tr><td colspan="7">Sem fornecedores registados.</td></tr><?php endif; ?><?php foreach($fornecedores as $f): ?><tr><td><?= (int)$f['id'] ?></td><td><?= htmlspecialchars((string)$f['nome']) ?></td><td><?= htmlspecialchars((string)($f['contacto']??'-')) ?></td><td><span class="logi-status <?= modalidadeFornecedorBadge((string)($f['modalidade_credito'] ?? 'Normal')) ?>"><?= htmlspecialchars(modalidadeFornecedorLabel((string)($f['modalidade_credito'] ?? 'Normal'))) ?></span></td><td><span class="logi-status <?= badge((string)$f['status']) ?>"><?= htmlspecialchars((string)$f['status']) ?></span></td><td><?= htmlspecialchars(money((float)($f['saldo_budjet'] ?? 0))) ?></td><td><?= htmlspecialchars(money((float)($f['divida_atual'] ?? 0))) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='pecas' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="criar_peca"><input name="codigo" placeholder="Codigo"><input name="nome" placeholder="Nome" required><input type="number" name="stock_atual" step="0.01" min="0" value="0" placeholder="Stock"><input type="number" name="stock_minimo" step="0.01" min="0" value="0" placeholder="Min"><input type="number" name="preco_referencia" step="0.01" min="0" value="0" placeholder="Preco"><button>Guardar</button></form>
            <?php elseif($view==='pecas'): ?>
                <form method="POST" class="logi-inline-form">
                    <input type="hidden" name="acao" value="ajustar_stock">
                    <div id="stock_linhas" style="display:flex;flex-direction:column;gap:8px;width:100%;">
                        <div class="logi-inline-form stock-linha" style="margin:0;">
                            <select name="peca_id[]" required><?php foreach($pecasRef as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)($p['codigo'].' - '.$p['nome'])) ?></option><?php endforeach; ?></select>
                            <select name="tipo_movimento[]"><option>Entrada</option><option>Saida</option><option>Ajuste</option></select>
                            <select name="fornecedor_id[]"><option value="">Fornecedor (opcional)</option><?php foreach($fornRef as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option><?php endforeach; ?></select>
                            <select name="projecto[]" required><option value="">Projecto</option><option value="Projecto Oficina">Projecto Oficina</option><option value="Projecto Transporte">Projecto Transporte</option></select>
                            <input type="number" class="stk-qtd" name="quantidade[]" step="0.01" min="0.01" required placeholder="Qtd">
                            <input type="number" class="stk-custo" name="custo_unitario[]" step="0.01" min="0" placeholder="Custo unitario">
                            <input type="text" class="stk-total" placeholder="Total" readonly>
                            <button type="button" class="btn_remove_stock">- Menos</button>
                        </div>
                    </div>
                    <button type="button" id="btn_add_stock">+ Movimento</button>
                    <div class="logi-budget-note">Valor a abater do Budjet (somente Entrada): <strong class="js-abate-stock">0,00 MZN</strong></div>
                    <button>Movimentar</button>
                </form>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Codigo</th><th>Nome</th><th>Stock</th><th>Minimo</th><th>Preco</th></tr><?php if(!$pecas): ?><tr><td colspan="6">Sem pecas em stock.</td></tr><?php endif; ?><?php foreach($pecas as $p): ?><tr><td><?= (int)$p['id'] ?></td><td><?= htmlspecialchars((string)$p['codigo']) ?></td><td><?= htmlspecialchars((string)$p['nome']) ?></td><td><?= htmlspecialchars((string)$p['stock_atual']) ?></td><td><?= htmlspecialchars((string)$p['stock_minimo']) ?></td><td><?= htmlspecialchars(money((float)$p['preco_referencia'])) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='cotacoes' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="criar_cotacao"><select name="fornecedor_id"><?php foreach($fornRef as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option><?php endforeach; ?></select><input name="item_nome" placeholder="Item" required><input type="number" name="preco_unitario" step="0.01" min="0.01" required><button>Guardar</button></form>
            <?php elseif($view==='cotacoes'): ?>
                <h4>Comparacao de precos</h4><div class="logi-table-wrap"><table class="logi-table"><tr><th>Item</th><th>Melhor</th><th>Maior</th><th>Medio</th><th>Cotacoes</th></tr><?php if(!$comp): ?><tr><td colspan="5">Sem cotacoes para comparar.</td></tr><?php endif; ?><?php foreach($comp as $c): ?><tr><td><?= htmlspecialchars((string)$c['item_nome']) ?></td><td><?= htmlspecialchars(money((float)$c['melhor_preco'])) ?></td><td><?= htmlspecialchars(money((float)$c['maior_preco'])) ?></td><td><?= htmlspecialchars(money((float)$c['preco_medio'])) ?></td><td><?= (int)$c['total_cotacoes'] ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='substituicoes' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="registar_substituicao"><select name="peca_id"><?php foreach($pecasRef as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)($p['codigo'].' - '.$p['nome'])) ?></option><?php endforeach; ?></select><input name="matricula_ativo" placeholder="Matricula" required><input type="number" name="quantidade" step="0.01" min="0.01" value="1" required><input type="number" name="custo_unitario" step="0.01" min="0" value="0"><div class="logi-budget-note">Valor a abater do Budjet: <strong class="js-abate-substituicao">0,00 MZN</strong></div><button>Guardar</button></form>
            <?php elseif($view==='substituicoes'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Peca</th><th>Matricula</th><th>Qtd</th><th>Custo Unit.</th><th>Total</th><th>Data</th></tr><?php if(!$subs): ?><tr><td colspan="7">Sem pecas substituidas registadas.</td></tr><?php endif; ?><?php foreach($subs as $s): $tot=(float)$s['quantidade']*(float)$s['custo_unitario']; ?><tr><td><?= (int)$s['id'] ?></td><td><?= htmlspecialchars((string)($s['peca_codigo'].' - '.$s['peca_nome'])) ?></td><td><?= htmlspecialchars((string)$s['matricula_ativo']) ?></td><td><?= htmlspecialchars((string)$s['quantidade']) ?></td><td><?= htmlspecialchars(money((float)$s['custo_unitario'])) ?></td><td><?= htmlspecialchars(money($tot)) ?></td><td><?= htmlspecialchars((string)$s['data_substituicao']) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='pecas_avariadas' && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_peca_avariada">
                    <select name="peca_id"><option value="">Peca do armazem (opcional)</option><?php foreach($pecasRef as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)($p['codigo'].' - '.$p['nome'])) ?></option><?php endforeach; ?></select>
                    <input name="codigo_peca" placeholder="Codigo peca">
                    <input name="nome_peca" placeholder="Nome da peca" required>
                    <input type="number" name="quantidade" min="0.01" step="0.01" placeholder="Quantidade" required>
                    <input name="motivo" placeholder="Motivo da avaria">
                    <input type="date" name="data_registo" value="<?= date('Y-m-d') ?>" required>
                    <button>Guardar peca avariada</button>
                </form>
            <?php elseif($view==='pecas_avariadas'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Peca</th><th>Quantidade</th><th>Motivo</th><th>Data</th><th>Status</th></tr><?php if(!$pecasAvariadas): ?><tr><td colspan="6">Sem pecas avariadas registadas.</td></tr><?php endif; ?><?php foreach($pecasAvariadas as $p): ?><tr><td><?= htmlspecialchars((string)($p['codigo_peca'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['nome_peca'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['quantidade'] ?? '0')) ?></td><td><?= htmlspecialchars((string)($p['motivo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['data_registo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['status'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif(in_array($view,['oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true) && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_custo_operacional">
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars((string)$view) ?>">
                    <input type="hidden" name="ret_view" value="<?= htmlspecialchars((string)$view) ?>">
                    <select name="departamento" required><option value="transporte">Transporte</option><option value="oficina">Oficina</option></select>
                    <select name="fornecedor_id">
                        <option value="">Fornecedor (opcional)</option>
                        <?php foreach($fornRef as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="forma_pagamento" required>
                        <option value="Numerario">Numerario</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Credito">Credito</option>
                        <option value="Cotacao">Via cotacao</option>
                    </select>
                    <input name="referencia_cotacao" placeholder="Referencia da cotacao (se aplicavel)">
                    <input name="descricao" placeholder="Descricao do custo" required>
                    <input type="number" name="valor" min="0.01" step="0.01" placeholder="Valor" required>
                    <div class="logi-budget-note">Valor a abater do Budjet: <strong class="js-abate-operacional">0,00 MZN</strong></div>
                    <input type="date" name="data_lancamento" value="<?= date('Y-m-d') ?>" required>
                    <input name="responsavel" placeholder="Responsavel">
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar custo</button>
                </form>
            <?php elseif(in_array($view,['oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true)): ?>
                <?php
                    $custosFiltrados = array_values(array_filter($custosOperacionais, static function(array $c) use ($view): bool {
                        return (string)($c['categoria'] ?? '') === $view;
                    }));
                ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Departamento</th><th>Fornecedor</th><th>Pagamento</th><th>Ref. cotacao</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Responsavel</th><th>Observacoes</th></tr><?php if(!$custosFiltrados): ?><tr><td colspan="9">Sem lancamentos nesta categoria.</td></tr><?php endif; ?><?php foreach($custosFiltrados as $c): ?><tr><td><?= htmlspecialchars(ucfirst((string)($c['departamento'] ?? 'transporte'))) ?></td><td><?= htmlspecialchars((string)($fornNomePorId[(int)($c['fornecedor_id'] ?? 0)] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['forma_pagamento'] ?? 'Numerario')) ?></td><td><?= htmlspecialchars((string)($c['referencia_cotacao'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($c['valor'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($c['data_lancamento'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['responsavel'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['observacoes'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='budjet'): ?>
                <?php if($budjetDepartamentoSelecionado === ''): ?>
                    <div class="budjet-grid">
                        <?php foreach(['oficina'=>'fa-screwdriver-wrench','transporte'=>'fa-truck'] as $dep => $icon): $b = $budjetResumo[$dep] ?? ['saldo_atual'=>0,'orcamento_total'=>0,'total_debitos'=>0,'bloqueado'=>0]; ?>
                            <?php
                                $orcDep = (float)($b['orcamento_total'] ?? 0);
                                $gastoDep = (float)($b['total_debitos'] ?? 0);
                                $pctUsoDep = $orcDep > 0 ? min(100, ($gastoDep / $orcDep) * 100) : 0;
                            ?>
                            <div class="budjet-card">
                                <div class="budjet-top">
                                    <div>
                                        <div class="budjet-icon"><i class="fa-solid <?= htmlspecialchars($icon) ?>"></i></div>
                                        <h3><?= ucfirst($dep) ?></h3>
                                    </div>
                                    <span class="logi-status <?= $pctUsoDep >= 90 ? 'danger' : ($pctUsoDep >= 70 ? 'warn' : 'ok') ?>"><i class="fa-solid fa-gauge-high"></i> <?= number_format($pctUsoDep, 1, ',', '.') ?>%</span>
                                </div>
                                <div class="budjet-meta">Budjet do departamento</div>
                                <div class="budjet-value"><?= htmlspecialchars(money((float)($b['saldo_atual'] ?? 0))) ?></div>
                                <div class="budjet-progress">
                                    <div class="lbl"><span>Execucao do budjet</span><strong><?= number_format($pctUsoDep, 1, ',', '.') ?>%</strong></div>
                                    <div class="bar"><div class="fill" style="width: <?= number_format($pctUsoDep, 2, '.', '') ?>%"></div></div>
                                </div>
                                <div class="budjet-meta">
                                    Orcamento total: <?= htmlspecialchars(money((float)($b['orcamento_total'] ?? 0))) ?><br>
                                    Disponivel: <?= htmlspecialchars(money((float)($b['saldo_atual'] ?? 0))) ?>
                                </div>
                                <div class="budjet-meta" style="margin:6px 0 0 0;">
                                    <?php if(((int)($b['bloqueado'] ?? 0)) === 1): ?>
                                        <span class="logi-status warn"><i class="fa-solid fa-lock"></i> Trancado</span>
                                    <?php else: ?>
                                        <span class="logi-status ok"><i class="fa-solid fa-lock-open"></i> Aberto</span>
                                    <?php endif; ?>
                                </div>
                                <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;" href="?view=budjet&departamento=<?= urlencode($dep) ?>"><i class="fa-solid fa-circle-right"></i> Ver detalhes</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php
                        $b = $budjetResumo[$budjetDepartamentoSelecionado] ?? ['saldo_atual'=>0,'orcamento_total'=>0,'total_debitos'=>0,'total_creditos'=>0,'bloqueado'=>0,'bloqueado_por'=>'','bloqueado_em'=>''];
                        $budjetBloqueado = ((int)($b['bloqueado'] ?? 0) === 1);
                        $orcDetalhe = (float)($b['orcamento_total'] ?? 0);
                        $gastoDetalhe = (float)($b['total_debitos'] ?? 0);
                        $pctUsoDetalhe = $orcDetalhe > 0 ? min(100, ($gastoDetalhe / $orcDetalhe) * 100) : 0;
                        $edicaoIdBudjet = (int)($_GET['budjet_item_edit'] ?? 0);
                        $itemEdicaoBudjet = null;
                        if($edicaoIdBudjet > 0){
                            foreach($budjetItensTemplate as $it){
                                if((int)($it['id'] ?? 0) === $edicaoIdBudjet){
                                    $itemEdicaoBudjet = $it;
                                    break;
                                }
                            }
                        }
                    ?>
                    <div class="budjet-detail-header">
                        <div>
                            <h3 style="margin:0; text-transform:capitalize;">Detalhes do Budjet - <?= htmlspecialchars($budjetDepartamentoSelecionado) ?></h3>
                            <span class="budjet-pill">Extrato financeiro do departamento</span>
                        </div>
                        <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=budjet">Voltar aos departamentos</a>
                    </div>
                    <div class="budjet-action-bar">
                        <span class="logi-status info"><i class="fa-solid fa-wand-magic-sparkles"></i> Painel interativo do budjet</span>
                        <div class="right">
                            <button type="button" class="logi-action-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
                            <button type="button" class="logi-action-btn" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Baixar PDF</button>
                        </div>
                    </div>
                    <div class="budjet-switch">
                        <a href="?view=budjet&departamento=oficina" class="<?= $budjetDepartamentoSelecionado === 'oficina' ? 'active' : '' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> Oficina</a>
                        <a href="?view=budjet&departamento=transporte" class="<?= $budjetDepartamentoSelecionado === 'transporte' ? 'active' : '' ?>"><i class="fa-solid fa-truck"></i> Transporte</a>
                    </div>

                    <div class="budjet-resumo">
                        <div class="budjet-box"><div class="k"><i class="fa-solid fa-wallet"></i> Saldo atual</div><div class="v"><?= htmlspecialchars(money((float)($b['saldo_atual'] ?? 0))) ?></div></div>
                        <div class="budjet-box"><div class="k"><i class="fa-solid fa-sack-dollar"></i> Orcamento total</div><div class="v"><?= htmlspecialchars(money((float)($b['orcamento_total'] ?? 0))) ?></div></div>
                        <div class="budjet-box"><div class="k"><i class="fa-solid fa-circle-check"></i> Estado</div><div class="v" style="font-size:16px;"><?= $budjetBloqueado ? 'Trancado' : 'Aberto' ?></div></div>
                    </div>
                    <div class="budjet-progress" style="margin-top:-4px;">
                        <div class="lbl"><span>Execucao geral do departamento</span><strong><?= number_format($pctUsoDetalhe, 1, ',', '.') ?>%</strong></div>
                        <div class="bar"><div class="fill" style="width: <?= number_format($pctUsoDetalhe, 2, '.', '') ?>%"></div></div>
                    </div>

                    <div class="budjet-lock-banner <?= $budjetBloqueado ? 'locked' : 'open' ?>">
                        <div>
                            <?php if($budjetBloqueado): ?>
                                <strong><i class="fa-solid fa-lock"></i> Budjet trancado</strong>
                                <div style="font-size:12px; color:#6b7280;">Bloqueado por <?= htmlspecialchars((string)($b['bloqueado_por'] ?: 'Administrador')) ?><?= !empty($b['bloqueado_em']) ? ' em '.htmlspecialchars((string)$b['bloqueado_em']) : '' ?></div>
                            <?php else: ?>
                                <strong><i class="fa-solid fa-lock-open"></i> Budjet aberto para ajustes</strong>
                                <div style="font-size:12px; color:#6b7280;">Edicao de itens permitida para perfis autorizados.</div>
                            <?php endif; ?>
                        </div>
                        <?php if($is_admin_logistica): ?>
                            <form method="POST" class="logi-inline-form" style="margin:0;">
                                <input type="hidden" name="acao" value="budjet_toggle_lock">
                                <input type="hidden" name="departamento" value="<?= htmlspecialchars($budjetDepartamentoSelecionado) ?>">
                                <input type="hidden" name="bloquear" value="<?= $budjetBloqueado ? '0' : '1' ?>">
                                <button type="submit" class="logi-action-btn" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
                                    <i class="fa-solid <?= $budjetBloqueado ? 'fa-unlock' : 'fa-lock' ?>"></i>
                                    <?= $budjetBloqueado ? 'Destrancar budjet' : 'Trancar budjet' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="budjet-form-grid">
                        <div class="budjet-form-card">
                            <h4><i class="fa-solid fa-coins"></i> Reforco de Budjet</h4>
                            <form method="POST" class="logi-inline-form">
                                <input type="hidden" name="acao" value="budjet_creditar">
                                <input type="hidden" name="departamento" value="<?= htmlspecialchars($budjetDepartamentoSelecionado) ?>">
                                <input type="number" name="valor" min="0.01" step="0.01" placeholder="Valor para reforco" required>
                                <input type="text" name="descricao" placeholder="Descricao (opcional)">
                                <button <?= $budjetBloqueado && !$is_admin_logistica ? 'disabled title="Budjet trancado para ajustes"' : '' ?>><i class="fa-solid fa-plus"></i> Reforcar budjet</button>
                            </form>
                        </div>
                        <div class="budjet-form-card">
                            <h4><i class="fa-solid fa-layer-group"></i> Categoria / Actividade</h4>
                            <form method="POST" class="logi-inline-form">
                                <input type="hidden" name="acao" value="budjet_item_guardar">
                                <input type="hidden" name="departamento" value="<?= htmlspecialchars($budjetDepartamentoSelecionado) ?>">
                                <input type="hidden" name="item_id" value="<?= (int)($itemEdicaoBudjet['id'] ?? 0) ?>">
                                <input type="text" name="categoria" placeholder="Categoria" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['categoria'] ?? '')) ?>" required>
                                <input type="text" name="descricao" placeholder="Actividade/Servico (subcategoria)" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['descricao'] ?? '')) ?>" required>
                                <input type="text" name="unidade" placeholder="Un" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['unidade'] ?? 'Un')) ?>">
                                <input type="number" name="ordem_item" min="1" step="1" placeholder="Ordem" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['ordem_item'] ?? 1)) ?>">
                                <input type="number" name="qtd_planeada" min="0" step="0.01" placeholder="Qtd planeada" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['qtd_planeada'] ?? 0)) ?>">
                                <input type="number" name="qtd_actual" min="0" step="0.01" placeholder="Qtd actual" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['qtd_actual'] ?? 0)) ?>">
                                <input type="number" name="orcamento_compra" min="0" step="0.01" placeholder="Orcamento compra" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['orcamento_compra'] ?? 0)) ?>">
                                <input type="number" name="saldo_pendente" step="0.01" placeholder="Saldo pendente" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['saldo_pendente'] ?? 0)) ?>">
                                <input type="number" name="preco_unitario" min="0" step="0.01" placeholder="Preco unitario" value="<?= htmlspecialchars((string)($itemEdicaoBudjet['preco_unitario'] ?? 0)) ?>">
                                <button type="submit" <?= $budjetBloqueado && !$is_admin_logistica ? 'disabled title="Budjet trancado para ajustes"' : '' ?>><i class="fa-solid fa-floppy-disk"></i> <?= $itemEdicaoBudjet ? 'Atualizar item' : 'Adicionar item' ?></button>
                                <?php if($itemEdicaoBudjet): ?>
                                    <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;" href="?view=budjet&departamento=<?= urlencode($budjetDepartamentoSelecionado) ?>"><i class="fa-solid fa-rotate-left"></i> Cancelar</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if($budjetItensTemplate): ?>
                        <?php
                            $grupoCategorias = [];
                            foreach($budjetItensTemplate as $it){
                                $cat = (string)($it['categoria'] ?? 'Sem categoria');
                                if(!isset($grupoCategorias[$cat])) $grupoCategorias[$cat] = [];
                                $grupoCategorias[$cat][] = $it;
                            }
                            $totOrc = 0.0; $totSaldo = 0.0; $totExcedido = 0; $totQuase = 0;
                        ?>
                        <?php
                            foreach($budjetItensTemplate as $ix){
                                $orcIt = (float)($ix['orcamento_compra'] ?? 0);
                                $saldoIt = (float)($ix['saldo_pendente'] ?? 0);
                                $consumoIt = max(0, $orcIt - $saldoIt);
                                $pctIt = $orcIt > 0 ? (($consumoIt / $orcIt) * 100) : 0;
                                if($saldoIt < 0) $totExcedido++;
                                elseif($pctIt >= 90) $totQuase++;
                            }
                        ?>
                        <?php if($totExcedido > 0 || $totQuase > 0): ?>
                            <div class="budjet-alert-box">
                                <strong><i class="fa-solid fa-triangle-exclamation"></i> Alerta de budjet por actividade/servico:</strong>
                                <?= $totExcedido > 0 ? htmlspecialchars((string)$totExcedido) . ' item(ns) excedido(s)' : '0 excedidos' ?><?= $totQuase > 0 ? ' | ' . htmlspecialchars((string)$totQuase) . ' item(ns) perto do limite' : '' ?>.
                            </div>
                        <?php else: ?>
                            <div class="budjet-alert-box ok"><i class="fa-solid fa-circle-check"></i> Sem excesso: todas as actividades/servicos estao dentro do budjet.</div>
                        <?php endif; ?>
                        <div class="budjet-tools">
                            <div class="logi-status info"><i class="fa-solid fa-filter"></i> Filtro rapido por Actividade/Servico</div>
                            <input type="text" id="budjetFiltroItens" placeholder="Pesquisar categoria, actividade ou estado...">
                        </div>
                        <div class="logi-table-wrap">
                            <table class="logi-table" id="tabelaBudjetItens">
                                <tr><th>#</th><th>Actividade/Servico</th><th>UND</th><th>Preco Unt</th><th>Qtd</th><th>Qtd Actual</th><th>Orcamento Compra</th><th>Saldo Pendente</th><th>Alerta</th><th>Acoes</th></tr>
                                <?php foreach($grupoCategorias as $categoria => $itensCat): ?>
                                    <tr data-budjet-row="1" data-budjet-text="<?= htmlspecialchars(strtolower((string)$categoria), ENT_QUOTES, 'UTF-8') ?>"><td colspan="10" style="font-weight:800;background:#fff7ed;color:#7c2d12;"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($categoria) ?></td></tr>
                                    <?php foreach($itensCat as $idx=>$it): $totOrc += (float)($it['orcamento_compra'] ?? 0); $totSaldo += (float)($it['saldo_pendente'] ?? 0); ?>
                                        <?php
                                            $orcIt = (float)($it['orcamento_compra'] ?? 0);
                                            $saldoIt = (float)($it['saldo_pendente'] ?? 0);
                                            $consumoIt = max(0, $orcIt - $saldoIt);
                                            $pctIt = $orcIt > 0 ? (($consumoIt / $orcIt) * 100) : 0;
                                            $alertaClasse = 'ok';
                                            $alertaIcone = 'fa-circle-check';
                                            $alertaTxt = 'Dentro do budjet';
                                            if($saldoIt < 0){
                                                $alertaClasse = 'danger';
                                                $alertaIcone = 'fa-triangle-exclamation';
                                                $alertaTxt = 'Excedido';
                                            } elseif($pctIt >= 90){
                                                $alertaClasse = 'warn';
                                                $alertaIcone = 'fa-bell';
                                                $alertaTxt = 'Perto do limite';
                                            }
                                        ?>
                                        <tr data-budjet-row="1" data-budjet-text="<?= htmlspecialchars(strtolower((string)($categoria.' '.($it['descricao'] ?? '').' '.$alertaTxt)), ENT_QUOTES, 'UTF-8') ?>">
                                            <td><?= (int)($idx + 1) ?></td>
                                            <td><?= htmlspecialchars((string)($it['descricao'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($it['unidade'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars(money((float)($it['preco_unitario'] ?? 0))) ?></td>
                                            <td><?= htmlspecialchars((string)number_format((float)($it['qtd_planeada'] ?? 0), 2, ',', '.')) ?></td>
                                            <td><?= htmlspecialchars((string)number_format((float)($it['qtd_actual'] ?? 0), 2, ',', '.')) ?></td>
                                            <td><?= htmlspecialchars(money((float)($it['orcamento_compra'] ?? 0))) ?></td>
                                            <td><?= htmlspecialchars(money((float)($it['saldo_pendente'] ?? 0))) ?></td>
                                            <td><span class="logi-status <?= $alertaClasse ?>"><i class="fa-solid <?= $alertaIcone ?>"></i> <?= htmlspecialchars($alertaTxt) ?></span></td>
                                            <td style="white-space:nowrap;">
                                                <a class="btn-icon-only" title="Editar item" href="?view=budjet&departamento=<?= urlencode($budjetDepartamentoSelecionado) ?>&budjet_item_edit=<?= (int)($it['id'] ?? 0) ?>"><i class="fa-solid fa-pen-to-square"></i></a>
                                                <?php if($is_admin_logistica): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este item do budjet?');">
                                                        <input type="hidden" name="acao" value="budjet_item_excluir">
                                                        <input type="hidden" name="departamento" value="<?= htmlspecialchars($budjetDepartamentoSelecionado) ?>">
                                                        <input type="hidden" name="item_id" value="<?= (int)($it['id'] ?? 0) ?>">
                                                        <button type="submit" class="btn-icon-only danger" title="Excluir item"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="6" style="font-weight:800;">TOTAL ORCAMENTO DO PROJECTO</td>
                                    <td style="font-weight:800;"><?= htmlspecialchars(money($totOrc)) ?></td>
                                    <td style="font-weight:800;"><?= htmlspecialchars(money($totSaldo)) ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </table>
                        </div>
                        <script>
                            (function(){
                                var input = document.getElementById('budjetFiltroItens');
                                var tabela = document.getElementById('tabelaBudjetItens');
                                if(!input || !tabela) return;
                                function filtrar(){
                                    var q = (input.value || '').toLowerCase().trim();
                                    var rows = tabela.querySelectorAll('tr[data-budjet-row=\"1\"]');
                                    rows.forEach(function(r){
                                        var txt = (r.getAttribute('data-budjet-text') || '').toLowerCase();
                                        r.style.display = (q === '' || txt.indexOf(q) >= 0) ? '' : 'none';
                                    });
                                }
                                input.addEventListener('input', filtrar);
                            })();
                        </script>
                    <?php endif; ?>

                <?php endif; ?>

            <?php elseif($view==='alertas'): ?>
                <?php
                    $pendentes = array_values(array_filter($requisicoes, static function(array $r): bool {
                        return st((string)($r['status'] ?? '')) === 'pendente';
                    }));
                    $stockBaixoRows = array_values(array_filter($pecas, static function(array $p): bool {
                        return (float)($p['stock_atual'] ?? 0) <= (float)($p['stock_minimo'] ?? 0);
                    }));
                    $facturasPendentes = array_values(array_filter($facturas, static function(array $f): bool {
                        $s = strtolower(trim((string)($f['status'] ?? 'pendente')));
                        return $s !== 'pago';
                    }));
                ?>
                <div class="logi-table-wrap">
                    <table class="logi-table">
                        <tr><th>Tipo de alerta</th><th>Descricao</th><th>Quantidade</th><th>Acao sugerida</th></tr>
                        <tr>
                            <td>Requisicoes pendentes</td>
                            <td>Pedidos aguardando decisao</td>
                            <td><?= count($pendentes) ?></td>
                            <td><a href="?view=pedidos_oficina">Rever pedidos</a></td>
                        </tr>
                        <tr>
                            <td>Stock critico</td>
                            <td>Pecas com stock igual/abaixo do minimo</td>
                            <td><?= count($stockBaixoRows) ?></td>
                            <td><a href="?view=pecas">Repor stock</a></td>
                        </tr>
                        <tr>
                            <td>Facturas em aberto</td>
                            <td>Facturas pendentes/parciais</td>
                            <td><?= count($facturasPendentes) ?></td>
                            <td><a href="?view=facturas">Regularizar pagamento</a></td>
                        </tr>
                        <tr>
                            <td>Saldo a pagar</td>
                            <td>Exposicao financeira atual</td>
                            <td><?= htmlspecialchars(money((float)($rel['saldo_a_pagar'] ?? 0))) ?></td>
                            <td><a href="?view=budjet">Ver budjet</a></td>
                        </tr>
                    </table>
                </div>

            <?php elseif($view==='relatorios'): ?>
                <form method="GET" class="logi-inline-form"><input type="hidden" name="view" value="relatorios"><select name="periodo"><option value="diario" <?= $periodo==='diario'?'selected':'' ?>>Diario</option><option value="semanal" <?= $periodo==='semanal'?'selected':'' ?>>Semanal</option><option value="mensal" <?= $periodo==='mensal'?'selected':'' ?>>Mensal</option></select><button>Aplicar</button></form>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Indicador</th><th>Valor</th></tr><tr><td>Total requisicoes</td><td><?= (int)($rel['total_requisicoes']??0) ?></td></tr><tr><td>Aprovadas</td><td><?= (int)($rel['requisicoes_aprovadas']??0) ?></td></tr><tr><td>Negadas</td><td><?= (int)($rel['requisicoes_negadas']??0) ?></td></tr><tr><td>Valor requisicoes</td><td><?= htmlspecialchars(money((float)($rel['total_valor_requisicoes']??0))) ?></td></tr><tr><td>Fornecedores ativos</td><td><?= (int)($rel['fornecedores_ativos']??0) ?></td></tr><tr><td>Pecas abaixo minimo</td><td><?= (int)($rel['pecas_stock_baixo']??0) ?></td></tr><tr><td>Valor stock</td><td><?= htmlspecialchars(money((float)($rel['valor_stock']??0))) ?></td></tr><tr><td>Custo pecas substituidas</td><td><?= htmlspecialchars(money((float)($rel['custo_substituicoes']??0))) ?></td></tr></table></div>

            <?php else: ?>
                <p>A Logistica Operacional atende Oficina/Transporte com comparacao de precos, fornecedores, cotacoes, stock, pecas substituidas e requisicoes. A Logistica Geral cobre compras macro.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function formatMzn(v){
    var n = Number(v || 0);
    return n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MZN';
}
function normTxt(v){
    return String(v || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}
var ordemBudjetItens = <?= json_encode(array_map(static function(array $it): array {
    return [
        'id'=>(int)($it['id'] ?? 0),
        'dep'=>departamentoCanonico((string)($it['departamento'] ?? 'oficina')),
        'categoria'=>(string)($it['categoria'] ?? ''),
        'descricao'=>(string)($it['descricao'] ?? ''),
        'preco'=>(float)($it['preco_unitario'] ?? 0),
        'saldo'=>(float)($it['saldo_pendente'] ?? 0),
    ];
}, $budjetItensCompra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function atualizarPrevisaoAbates(){
    var fatAcao = document.querySelector('form input[name="acao"][value="criar_factura"]');
    if(fatAcao){
        var ff = fatAcao.closest('form');
        var iv = ff ? ff.querySelector('input[name="valor_total"]') : null;
        var ov = ff ? ff.querySelector('.js-abate-factura') : null;
        if(iv && ov) ov.textContent = formatMzn(iv.value || 0);
    }
    var opAcao = document.querySelector('form input[name="acao"][value="criar_custo_operacional"]');
    if(opAcao){
        var fo = opAcao.closest('form');
        var io = fo ? fo.querySelector('input[name="valor"]') : null;
        var oo = fo ? fo.querySelector('.js-abate-operacional') : null;
        if(io && oo) oo.textContent = formatMzn(io.value || 0);
    }
    var stAcao = document.querySelector('form input[name="acao"][value="ajustar_stock"]');
    if(stAcao){
        var fs = stAcao.closest('form');
        var os = fs ? fs.querySelector('.js-abate-stock') : null;
        if(fs && os){
            var total = 0;
            fs.querySelectorAll('.stock-linha').forEach(function(linha){
                var it = linha.querySelector('select[name="tipo_movimento[]"]');
                var iq = linha.querySelector('.stk-qtd');
                var ic = linha.querySelector('.stk-custo');
                if(it && iq && ic && it.value === 'Entrada'){
                    total += (Number(iq.value || 0) * Number(ic.value || 0));
                }
            });
            os.textContent = formatMzn(total);
        }
    }
    var subAcao = document.querySelector('form input[name="acao"][value="registar_substituicao"]');
    if(subAcao){
        var fb = subAcao.closest('form');
        var iqb = fb ? fb.querySelector('input[name="quantidade"]') : null;
        var icb = fb ? fb.querySelector('input[name="custo_unitario"]') : null;
        var ob = fb ? fb.querySelector('.js-abate-substituicao') : null;
        if(iqb && icb && ob) ob.textContent = formatMzn(Number(iqb.value || 0) * Number(icb.value || 0));
    }
}
function atualizarTotaisRequisicaoLinhas(){
    document.querySelectorAll('.requisicao-linha').forEach(function(linha){
        var qtd = linha.querySelector('.req-qtd');
        var preco = linha.querySelector('.req-preco');
        var total = linha.querySelector('.req-total');
        if(!qtd || !preco || !total) return;
        total.value = (Number(qtd.value || 0) * Number(preco.value || 0)).toFixed(2);
    });
}
function atualizarTotaisStockLinhas(){
    document.querySelectorAll('.stock-linha').forEach(function(linha){
        var qtd = linha.querySelector('.stk-qtd');
        var custo = linha.querySelector('.stk-custo');
        var total = linha.querySelector('.stk-total');
        if(!qtd || !custo || !total) return;
        total.value = formatMzn(Number(qtd.value || 0) * Number(custo.value || 0));
    });
}
function adicionarLinhaStock(){
    var box = document.getElementById('stock_linhas');
    if(!box) return;
    var primeira = box.querySelector('.stock-linha');
    if(!primeira) return;
    var nova = primeira.cloneNode(true);
    nova.querySelectorAll('input').forEach(function(i){
        if(i.classList.contains('stk-total')) i.value = '';
        else i.value = '';
    });
    nova.querySelectorAll('select').forEach(function(s){
        if(s.name === 'tipo_movimento[]') s.value = 'Entrada';
        else if(s.name === 'fornecedor_id[]') s.value = '';
        else if(s.name === 'projecto[]') s.value = '';
        else s.selectedIndex = 0;
    });
    box.appendChild(nova);
    atualizarTotaisStockLinhas();
    atualizarEstadoRemoverStock();
}
function atualizarEstadoRemoverStock(){
    var box = document.getElementById('stock_linhas');
    if(!box) return;
    var linhas = box.querySelectorAll('.stock-linha');
    var desativar = linhas.length <= 1;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_stock');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function atualizarFiltroFornecedorRequisicao(){
    var tipoSel = document.querySelector('.js-tipo-fornecedor-req');
    var fornecedorSel = document.querySelector('.js-fornecedor-req');
    if(!tipoSel || !fornecedorSel) return;
    var tipo = (tipoSel.value === 'normal') ? 'normal' : 'credito';
    Array.prototype.forEach.call(fornecedorSel.options, function(opt, idx){
        if(idx === 0) return;
        var mod = String(opt.getAttribute('data-modalidade') || '').toLowerCase();
        var visivel = mod === tipo;
        opt.hidden = !visivel;
        opt.disabled = !visivel;
        if(!visivel && opt.selected) fornecedorSel.value = '';
    });
}
function adicionarLinhaRequisicao(){
    var box = document.getElementById('requisicoes_linhas');
    if(!box) return;
    var primeira = box.querySelector('.requisicao-linha');
    if(!primeira) return;
    var nova = primeira.cloneNode(true);
    nova.querySelectorAll('input').forEach(function(i){
        if(i.classList.contains('req-qtd')) i.value = '1';
        else if(i.classList.contains('req-total')) i.value = '';
        else if(i.name === 'unidade[]') i.value = 'un';
        else i.value = '';
    });
    box.appendChild(nova);
    atualizarTotaisRequisicaoLinhas();
    atualizarEstadoRemoverRequisicao();
}
function atualizarEstadoRemoverRequisicao(){
    var box = document.getElementById('requisicoes_linhas');
    if(!box) return;
    var linhas = box.querySelectorAll('.requisicao-linha');
    var desativar = linhas.length <= 1;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_requisicao');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function departamentoPorProjectoOrdem(projecto){
    return String(projecto || '').toLowerCase().indexOf('transporte') >= 0 ? 'transporte' : 'oficina';
}
function atualizarSubTotalLinhaOrdem(linha){
    var qtd = linha.querySelector('.ord-qtd');
    var custo = linha.querySelector('.ord-custo');
    var sub = linha.querySelector('.ord-subtotal');
    if(!qtd || !custo || !sub) return 0;
    var total = Number(qtd.value || 0) * Number(custo.value || 0);
    sub.value = formatMzn(total);
    return total;
}
function atualizarTotalOrdemCompra(){
    var total = 0;
    document.querySelectorAll('.ordem-linha').forEach(function(linha){
        total += atualizarSubTotalLinhaOrdem(linha);
    });
    var out = document.querySelector('.js-ord-total');
    if(out) out.textContent = formatMzn(total);
}
function atualizarEstadoRemoverOrdem(){
    var box = document.getElementById('ordens_linhas');
    if(!box) return;
    var linhas = box.querySelectorAll('.ordem-linha');
    linhas.forEach(function(linha, idx){
        var num = linha.querySelector('.ordem-linha-num');
        if(num) num.textContent = String(idx + 1);
    });
    var desativar = linhas.length <= 1;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_ordem');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function obterOpcoesSubcategorias(dep, categoria){
    var set = new Set();
    ordemBudjetItens.forEach(function(it){
        if(it.dep !== dep) return;
        if(categoria && it.categoria !== categoria) return;
        if(it.descricao) set.add(it.descricao);
    });
    return Array.from(set);
}
function preencherSelectOrdem(select, opcoes, placeholder, valorAtual){
    if(!select) return;
    select.innerHTML = '';
    var base = document.createElement('option');
    base.value = '';
    base.textContent = placeholder;
    select.appendChild(base);
    opcoes.forEach(function(v){
        var opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        if(valorAtual && valorAtual === v) opt.selected = true;
        select.appendChild(opt);
    });
}
function atualizarFiltrosCabecalhoOrdem(){
    var projeto = document.querySelector('.js-ord-projecto');
    var categoria = document.querySelector('.js-ord-categoria');
    var subcategoria = document.querySelector('.js-ord-subcategoria');
    if(!projeto || !categoria || !subcategoria) return;
    var dep = departamentoPorProjectoOrdem(projeto.value);
    var categorias = Array.from(new Set(ordemBudjetItens.filter(function(it){ return it.dep === dep && it.categoria; }).map(function(it){ return it.categoria; })));
    var categoriaAtual = categoria.value;
    preencherSelectOrdem(categoria, categorias, 'Categoria', categoriaAtual);
    if(categoria.value !== categoriaAtual) categoriaAtual = categoria.value;
    var subAtual = subcategoria.value;
    preencherSelectOrdem(subcategoria, obterOpcoesSubcategorias(dep, categoriaAtual), 'Seleccione a Subcategoria', subAtual);
    atualizarOpcoesItensOrdem();
}
function atualizarOpcoesItensOrdem(){
    var projeto = document.querySelector('.js-ord-projecto');
    var categoria = document.querySelector('.js-ord-categoria');
    var subcategoria = document.querySelector('.js-ord-subcategoria');
    if(!projeto) return;
    var dep = departamentoPorProjectoOrdem(projeto.value);
    var cat = categoria ? categoria.value : '';
    var sub = subcategoria ? subcategoria.value : '';
    document.querySelectorAll('.js-ord-buditem').forEach(function(sel){
        var atual = sel.value;
        sel.innerHTML = '<option value="">Artigo/Servico do budjet</option>';
        ordemBudjetItens.forEach(function(it){
            if(it.dep !== dep) return;
            if(cat && it.categoria !== cat) return;
            if(sub && it.descricao !== sub) return;
            var op = document.createElement('option');
            op.value = String(it.id);
            op.setAttribute('data-artigo', it.descricao || '');
            op.setAttribute('data-custo', String(it.preco || 0));
            op.textContent = '[' + (it.categoria || '-') + '] ' + (it.descricao || '-') + ' | Saldo: ' + formatMzn(it.saldo || 0);
            if(String(it.id) === String(atual)) op.selected = true;
            sel.appendChild(op);
        });
    });
}
function modalidadePermitidaPorTermo(){
    var termo = document.querySelector('.js-ord-termo');
    var t = termo ? String(termo.value || '').toLowerCase() : 'requisicao';
    return (t === 'pre pago') ? 'normal' : 'credito';
}
function filtrarFornecedorPorModalidade(select, modalidade){
    if(!select) return;
    Array.prototype.forEach.call(select.options, function(opt, idx){
        if(idx === 0) return;
        var mod = String(opt.getAttribute('data-modalidade') || '').toLowerCase();
        var visivel = mod === modalidade;
        opt.hidden = !visivel;
        opt.disabled = !visivel;
        if(!visivel && opt.selected) select.value = '';
    });
}
function filtrarFornecedorPorTexto(select, termo){
    if(!select) return;
    var txt = normTxt(termo);
    Array.prototype.forEach.call(select.options, function(opt, idx){
        if(idx === 0) return;
        var label = normTxt(opt.textContent || '');
        var visivelTexto = txt === '' || label.indexOf(txt) >= 0;
        if(opt.disabled && opt.hidden) return;
        opt.hidden = !visivelTexto;
        if(!visivelTexto && opt.selected) select.value = '';
    });
}
function obterMatchesFornecedor(select, termo){
    if(!select) return [];
    var txt = normTxt(termo);
    var arr = [];
    Array.prototype.forEach.call(select.options, function(opt, idx){
        if(idx === 0 || opt.disabled || opt.hidden) return;
        var label = String(opt.textContent || '');
        var nlabel = normTxt(label);
        if(txt === '' || nlabel.indexOf(txt) >= 0){
            arr.push({ value: String(opt.value || ''), label: label, nlabel: nlabel });
        }
    });
    return arr;
}
function selecionarFornecedorPorTexto(termo){
    var sel = document.querySelector('.js-ord-fornecedor');
    var busca = document.querySelector('.js-ord-fornecedor-search');
    var box = document.querySelector('.js-ord-fornecedor-sugestoes');
    if(!sel) return false;
    atualizarFiltroFornecedorOrdem();
    var txt = normTxt(termo);
    var matches = obterMatchesFornecedor(sel, txt);
    if(matches.length === 0){
        if(box){
            box.innerHTML = '<div class="ord-search-empty">Nao encontrado</div>';
            box.classList.remove('hidden');
        }
        return false;
    }
    var escolhido = matches.find(function(m){ return m.nlabel === txt; }) || matches[0];
    sel.value = escolhido.value;
    if(busca) busca.value = escolhido.label;
    if(box){
        box.innerHTML = '';
        box.classList.add('hidden');
    }
    return true;
}
function atualizarFiltroFornecedorOrdem(){
    var modalidade = modalidadePermitidaPorTermo();
    var selPrincipal = document.querySelector('.js-ord-fornecedor');
    filtrarFornecedorPorModalidade(selPrincipal, modalidade);
    document.querySelectorAll('.js-ord-cot-fornecedor').forEach(function(sel){
        filtrarFornecedorPorModalidade(sel, modalidade);
    });
    var busca = document.querySelector('.js-ord-fornecedor-search');
    if(busca && selPrincipal){
        filtrarFornecedorPorTexto(selPrincipal, busca.value || '');
    }
    atualizarSugestoesFornecedorOrdem();
}
function atualizarSugestoesFornecedorOrdem(){
    var busca = document.querySelector('.js-ord-fornecedor-search');
    var sel = document.querySelector('.js-ord-fornecedor');
    var box = document.querySelector('.js-ord-fornecedor-sugestoes');
    if(!busca || !sel || !box) return;
    var txt = normTxt(busca.value);
    if(txt === ''){
        box.innerHTML = '';
        box.classList.add('hidden');
        return;
    }
    var matches = obterMatchesFornecedor(sel, txt);
    box.innerHTML = '';
    if(matches.length === 0){
        var vazio = document.createElement('div');
        vazio.className = 'ord-search-empty';
        vazio.textContent = 'Nao encontrado';
        box.appendChild(vazio);
        box.classList.remove('hidden');
        return;
    }
    matches.slice(0, 12).forEach(function(m){
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ord-search-item';
        btn.setAttribute('data-forn', m.value);
        btn.textContent = m.label;
        box.appendChild(btn);
    });
    box.classList.remove('hidden');
}
function atualizarVisibilidadeCotacoesOrdem(){
    var bloco = document.querySelector('.js-ord-cotacoes-bloco');
    var termo = document.querySelector('.js-ord-termo');
    if(!bloco || !termo) return;
    var mostrar = String(termo.value || '').toLowerCase() === 'pre pago';
    bloco.style.display = mostrar ? 'block' : 'none';
    bloco.querySelectorAll('select, input').forEach(function(el){
        if(el.name === 'anexo_cotacao_ordem[]'){
            el.disabled = !mostrar;
            return;
        }
        if(el.hasAttribute('data-required-prepago')){
            el.required = mostrar;
        }
        el.disabled = !mostrar;
    });
}
function abrirEtapaItensOrdem(){
    var form = document.querySelector('.js-form-ordem');
    if(!form) return;
    var etapa = form.querySelector('.js-ord-etapa-itens');
    if(!etapa) return;
    etapa.style.display = 'block';
    etapa.querySelectorAll('input, select, button, textarea').forEach(function(el){
        el.disabled = false;
    });
    atualizarVisibilidadeCotacoesOrdem();
    etapa.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function bloquearEtapaItensOrdem(){
    var form = document.querySelector('.js-form-ordem');
    if(!form) return;
    var etapa = form.querySelector('.js-ord-etapa-itens');
    if(!etapa) return;
    etapa.querySelectorAll('input, select, button, textarea').forEach(function(el){
        el.disabled = true;
    });
}
function validarDadosOrdemAntesDeItens(){
    var form = document.querySelector('.js-form-ordem');
    if(!form) return false;
    function definirErro(msg){
        var box = form.querySelector('.js-ord-step-erro');
        if(!box) return;
        box.textContent = msg;
        box.style.display = msg ? 'block' : 'none';
    }
    var selFornecedor = form.querySelector('select[name="fornecedor_id"]');
    var buscaFornecedor = form.querySelector('.js-ord-fornecedor-search');
    if(selFornecedor && String(selFornecedor.value || '').trim() === '' && buscaFornecedor && String(buscaFornecedor.value || '').trim() !== ''){
        selecionarFornecedorPorTexto(buscaFornecedor.value || '');
    }
    if(selFornecedor && String(selFornecedor.value || '').trim() === ''){
        if(buscaFornecedor && String(buscaFornecedor.value || '').trim() !== ''){
            definirErro('Fornecedor nao encontrado para o texto pesquisado. Selecione um fornecedor valido.');
        } else {
            definirErro('Campo obrigatorio em falta: Fornecedor.');
        }
        if(buscaFornecedor) buscaFornecedor.focus();
        return false;
    }
    var obrigatorios = [
        { sel:'input[name="assunto"]', nome:'Assunto' },
        { sel:'input[name="solicitante"]', nome:'Solicitante' },
        { sel:'select[name="projecto"]', nome:'Projecto' },
        { sel:'input[name="data_registo"]', nome:'Data de registo' },
        { sel:'select[name="termo_pagamento"]', nome:'Termo de pagamento' },
        { sel:'select[name="moeda"]', nome:'Moeda' }
    ];
    for(var i=0;i<obrigatorios.length;i++){
        var cfg = obrigatorios[i];
        var campo = form.querySelector(cfg.sel);
        if(!campo) continue;
        var val = String(campo.value || '').trim();
        if(val === ''){
            if(campo.style.display === 'none' && buscaFornecedor){
                buscaFornecedor.focus();
            } else {
                campo.focus();
            }
            definirErro('Campo obrigatorio em falta: ' + cfg.nome + '.');
            return false;
        }
    }
    definirErro('');
    return true;
}
function atualizarEstadoRemoverOrdemCotacao(){
    var box = document.getElementById('ordem_cotacoes_linhas');
    if(!box) return;
    var linhas = box.querySelectorAll('.ordem-cot-linha');
    var desativar = linhas.length <= 3;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_ordem_cotacao');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function adicionarLinhaCotacaoOrdem(){
    var box = document.getElementById('ordem_cotacoes_linhas');
    if(!box) return;
    var primeira = box.querySelector('.ordem-cot-linha');
    if(!primeira) return;
    var nova = primeira.cloneNode(true);
    nova.querySelectorAll('input').forEach(function(i){
        if(i.type === 'number') i.value = i.readOnly ? '1' : '';
        else if(i.type === 'file') i.value = '';
        else i.value = '';
    });
    var sel = nova.querySelector('.js-ord-cot-fornecedor');
    if(sel) sel.value = '';
    box.appendChild(nova);
    atualizarFiltroFornecedorOrdem();
    atualizarEstadoRemoverOrdemCotacao();
}
function aplicarItemBudjetNaLinha(linha, forcarCusto){
    if(!linha) return;
    var sel = linha.querySelector('.js-ord-buditem');
    if(!sel) return;
    var op = sel.options[sel.selectedIndex];
    if(!op) return;
    var artigo = linha.querySelector('.ord-artigo');
    var custo = linha.querySelector('.ord-custo');
    if(artigo) artigo.value = op.getAttribute('data-artigo') || '';
    if(custo){
        var preco = Number(op.getAttribute('data-custo') || 0);
        if(forcarCusto || Number(custo.value || 0) <= 0){
            if(preco > 0) custo.value = preco.toFixed(2);
        }
    }
    atualizarTotalOrdemCompra();
}
function adicionarLinhaOrdem(){
    var box = document.getElementById('ordens_linhas');
    if(!box) return;
    var primeira = box.querySelector('.ordem-linha');
    if(!primeira) return;
    var nova = primeira.cloneNode(true);
    nova.querySelectorAll('input').forEach(function(i){
        if(i.classList.contains('ord-qtd')) i.value = '1';
        else if(i.classList.contains('ord-subtotal')) i.value = '';
        else i.value = '';
    });
    var sel = nova.querySelector('.js-ord-buditem');
    if(sel) sel.value = '';
    box.appendChild(nova);
    atualizarOpcoesItensOrdem();
    atualizarTotalOrdemCompra();
    atualizarEstadoRemoverOrdem();
}
function baixarComparacaoCotacao(item, fornecedor, melhor, maior, medio, total){
    var cab = ['Item','Fornecedor (melhor)','Melhor','Maior','Medio','Cotacoes'];
    var row = [item || '-', fornecedor || '-', formatMzn(melhor), formatMzn(maior), formatMzn(medio), String(total || 0)];
    var csv = cab.join(';') + '\n' + row.map(function(v){ return '"' + String(v).replace(/"/g, '""') + '"'; }).join(';');
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'comparacao_cotacao_' + String(item || 'item').replace(/\s+/g, '_') + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
function imprimirComparacaoCotacao(item, fornecedor, melhor, maior, medio, total){
    var w = window.open('', '_blank', 'width=840,height=640');
    if(!w) return;
    var html = '<!doctype html><html><head><meta charset="utf-8"><title>Comparacao de cotacao</title>' +
        '<style>body{font-family:Arial,sans-serif;padding:18px;color:#111}h2{margin:0 0 12px 0;font-size:18px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}</style>' +
        '</head><body>' +
        '<h2>Comparacao de cotacao</h2>' +
        '<table><tr><th>Item</th><th>Fornecedor (melhor)</th><th>Melhor</th><th>Maior</th><th>Medio</th><th>Cotacoes</th></tr>' +
        '<tr><td>' + String(item || '-') + '</td><td>' + String(fornecedor || '-') + '</td><td>' + formatMzn(melhor) + '</td><td>' + formatMzn(maior) + '</td><td>' + formatMzn(medio) + '</td><td>' + String(total || 0) + '</td></tr></table>' +
        '<script>window.print();<\/script></body></html>';
    w.document.open();
    w.document.write(html);
    w.document.close();
}
function atualizarTotaisCotacaoLinhas(){
    document.querySelectorAll('.cotacao-linha').forEach(function(linha){
        var qtd = linha.querySelector('.cot-qtd');
        var preco = linha.querySelector('.cot-preco');
        var total = linha.querySelector('.cot-total');
        if(!qtd || !preco || !total) return;
        total.value = formatMzn(Number(qtd.value || 0) * Number(preco.value || 0));
    });
}
function adicionarLinhaCotacao(){
    var box = document.getElementById('cotacoes_linhas');
    if(!box) return;
    var primeira = box.querySelector('.cotacao-linha');
    if(!primeira) return;
    var nova = primeira.cloneNode(true);
    nova.querySelectorAll('input').forEach(function(i){
        if(i.type === 'number'){ i.value = i.classList.contains('cot-qtd') ? '1' : ''; }
        else if(i.type === 'file'){ i.value = ''; }
        else { i.value = ''; }
    });
    var sel = nova.querySelector('select');
    if(sel) sel.value = '';
    box.appendChild(nova);
    atualizarTotaisCotacaoLinhas();
    atualizarEstadoRemoverCotacao();
}
function atualizarEstadoRemoverCotacao(){
    var box = document.getElementById('cotacoes_linhas');
    if(!box) return;
    var linhas = box.querySelectorAll('.cotacao-linha');
    var desativar = linhas.length <= 3;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_cotacao');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function atualizarTotalMaterialLinha(linha){
    var qtd = linha.querySelector('.mat-qtd');
    var preco = linha.querySelector('.mat-preco');
    var total = linha.querySelector('.mat-total');
    if(!qtd || !preco || !total) return;
    var q = Number(qtd.value || 0);
    var p = Number(preco.value || 0);
    if(q > 0 && p > 0){
        total.value = (q * p).toFixed(2);
    }
}
function atualizarEstadoRemoverMaterial(form){
    var linhas = form.querySelectorAll('.material-credito-linha');
    var desativar = linhas.length <= 1;
    linhas.forEach(function(linha){
        var btn = linha.querySelector('.btn_remove_material_credito');
        if(!btn) return;
        btn.disabled = desativar;
        btn.style.opacity = desativar ? '0.5' : '1';
        btn.style.cursor = desativar ? 'not-allowed' : 'pointer';
    });
}
function inicializarFormularioFornecedorCredito(form){
    var modalidade = form.querySelector('.forn-modalidade');
    var box = form.querySelector('.forn-credito-box');
    var linhasBox = form.querySelector('.forn-credito-linhas');
    if(!modalidade || !box || !linhasBox) return;

    function toggleCreditoBox(){
        box.style.display = modalidade.value === 'Credito' ? 'block' : 'none';
    }
    function adicionarLinhaMaterial(){
        var primeira = linhasBox.querySelector('.material-credito-linha');
        if(!primeira) return;
        var nova = primeira.cloneNode(true);
        nova.querySelectorAll('input').forEach(function(i){ i.value = ''; });
        linhasBox.appendChild(nova);
        atualizarEstadoRemoverMaterial(form);
    }
    function removerLinhaMaterial(btn){
        var linhas = linhasBox.querySelectorAll('.material-credito-linha');
        if(linhas.length <= 1){
            var unica = linhasBox.querySelector('.material-credito-linha');
            if(unica) unica.querySelectorAll('input').forEach(function(i){ i.value=''; });
            return;
        }
        var linha = btn.closest('.material-credito-linha');
        if(linha) linha.remove();
        atualizarEstadoRemoverMaterial(form);
    }

    form.addEventListener('change', function(ev){
        if(ev.target === modalidade) toggleCreditoBox();
    });
    form.addEventListener('input', function(ev){
        if(ev.target && (ev.target.classList.contains('mat-qtd') || ev.target.classList.contains('mat-preco'))){
            var linha = ev.target.closest('.material-credito-linha');
            if(linha) atualizarTotalMaterialLinha(linha);
        }
    });
    form.addEventListener('click', function(ev){
        if(ev.target && ev.target.classList.contains('btn_add_material_credito')){
            adicionarLinhaMaterial();
        }
        if(ev.target && ev.target.classList.contains('btn_remove_material_credito')){
            removerLinhaMaterial(ev.target);
        }
    });

    linhasBox.querySelectorAll('.material-credito-linha').forEach(atualizarTotalMaterialLinha);
    toggleCreditoBox();
    atualizarEstadoRemoverMaterial(form);
}
document.addEventListener('input', function(ev){
    if(ev.target && (ev.target.classList.contains('stk-qtd') || ev.target.classList.contains('stk-custo'))){
        atualizarTotaisStockLinhas();
    }
    if(ev.target && (ev.target.classList.contains('req-qtd') || ev.target.classList.contains('req-preco'))){
        atualizarTotaisRequisicaoLinhas();
    }
    if(ev.target && (ev.target.classList.contains('cot-qtd') || ev.target.classList.contains('cot-preco'))){
        atualizarTotaisCotacaoLinhas();
    }
    if(ev.target && (ev.target.classList.contains('ord-qtd') || ev.target.classList.contains('ord-custo'))){
        atualizarTotalOrdemCompra();
    }
    atualizarPrevisaoAbates();
});
document.addEventListener('click', function(ev){
    if(ev.target && ev.target.id === 'btn_add_stock'){
        adicionarLinhaStock();
    }
    if(ev.target && ev.target.classList.contains('btn_remove_stock')){
        var boxStock = document.getElementById('stock_linhas');
        if(!boxStock) return;
        var linhasStock = boxStock.querySelectorAll('.stock-linha');
        if(linhasStock.length <= 1) return;
        var linhaStock = ev.target.closest('.stock-linha');
        if(linhaStock) linhaStock.remove();
        atualizarTotaisStockLinhas();
        atualizarEstadoRemoverStock();
    }
    if(ev.target && ev.target.id === 'btn_add_requisicao'){
        adicionarLinhaRequisicao();
    }
    if(ev.target && ev.target.classList.contains('btn_remove_requisicao')){
        var boxReq = document.getElementById('requisicoes_linhas');
        if(!boxReq) return;
        var linhasReq = boxReq.querySelectorAll('.requisicao-linha');
        if(linhasReq.length <= 1) return;
        var linhaReq = ev.target.closest('.requisicao-linha');
        if(linhaReq) linhaReq.remove();
        atualizarTotaisRequisicaoLinhas();
        atualizarEstadoRemoverRequisicao();
    }
    if(ev.target && ev.target.id === 'btn_add_cotacao'){
        adicionarLinhaCotacao();
    }
    if(ev.target && ev.target.classList.contains('btn_remove_cotacao')){
        var box = document.getElementById('cotacoes_linhas');
        if(!box) return;
        var linhas = box.querySelectorAll('.cotacao-linha');
        if(linhas.length <= 3) return;
        var linha = ev.target.closest('.cotacao-linha');
        if(linha) linha.remove();
        atualizarTotaisCotacaoLinhas();
        atualizarEstadoRemoverCotacao();
    }
    if(ev.target && ev.target.id === 'btn_add_ordem'){
        adicionarLinhaOrdem();
    }
    if(ev.target && ev.target.classList.contains('btn_remove_ordem')){
        var boxOrd = document.getElementById('ordens_linhas');
        if(!boxOrd) return;
        var linhasOrd = boxOrd.querySelectorAll('.ordem-linha');
        if(linhasOrd.length <= 1) return;
        var linhaOrd = ev.target.closest('.ordem-linha');
        if(linhaOrd) linhaOrd.remove();
        atualizarTotalOrdemCompra();
        atualizarEstadoRemoverOrdem();
    }
    if(ev.target && ev.target.id === 'btn_add_ordem_cotacao'){
        adicionarLinhaCotacaoOrdem();
    }
    if(ev.target && ev.target.classList.contains('btn_remove_ordem_cotacao')){
        var boxCotOrd = document.getElementById('ordem_cotacoes_linhas');
        if(!boxCotOrd) return;
        var linhasCotOrd = boxCotOrd.querySelectorAll('.ordem-cot-linha');
        if(linhasCotOrd.length <= 3) return;
        var linhaCotOrd = ev.target.closest('.ordem-cot-linha');
        if(linhaCotOrd) linhaCotOrd.remove();
        atualizarEstadoRemoverOrdemCotacao();
    }
});
document.addEventListener('change', function(){
    atualizarFiltroFornecedorRequisicao();
    atualizarFiltroFornecedorOrdem();
    atualizarVisibilidadeCotacoesOrdem();
    atualizarFiltrosCabecalhoOrdem();
    atualizarTotaisStockLinhas();
    atualizarPrevisaoAbates();
});
document.addEventListener('input', function(ev){
    if(ev.target && ev.target.classList.contains('js-ord-fornecedor-search')){
        var selPrincipal = document.querySelector('.js-ord-fornecedor');
        if(selPrincipal){
            atualizarFiltroFornecedorOrdem();
        }
    }
});
document.addEventListener('change', function(ev){
    if(ev.target && ev.target.classList.contains('js-ord-fornecedor')){
        var busca = document.querySelector('.js-ord-fornecedor-search');
        var box = document.querySelector('.js-ord-fornecedor-sugestoes');
        var sel = ev.target;
        var op = sel.options[sel.selectedIndex];
        if(busca && op && sel.value !== '') busca.value = String(op.textContent || '');
        if(box){
            box.innerHTML = '';
            box.classList.add('hidden');
        }
    }
    if(ev.target && ev.target.classList.contains('js-ord-buditem')){
        var linha = ev.target.closest('.ordem-linha');
        aplicarItemBudjetNaLinha(linha, false);
    }
    if(ev.target && (ev.target.classList.contains('js-ord-projecto') || ev.target.classList.contains('js-ord-categoria') || ev.target.classList.contains('js-ord-subcategoria'))){
        atualizarFiltrosCabecalhoOrdem();
    }
});
document.addEventListener('keydown', function(ev){
    if(ev.target && ev.target.classList.contains('js-ord-fornecedor-search') && ev.key === 'Enter'){
        ev.preventDefault();
        selecionarFornecedorPorTexto(ev.target.value || '');
    }
});
document.addEventListener('click', function(ev){
    if(ev.target && (ev.target.classList.contains('js-ord-fornecedor-filtrar') || (ev.target.closest && ev.target.closest('.js-ord-fornecedor-filtrar')))){
        var busca = document.querySelector('.js-ord-fornecedor-search');
        selecionarFornecedorPorTexto(busca ? busca.value : '');
        var selF = document.querySelector('.js-ord-fornecedor');
        if(selF) selF.focus();
    }
    if(ev.target && ev.target.classList.contains('ord-search-item')){
        var id = String(ev.target.getAttribute('data-forn') || '');
        var sel = document.querySelector('.js-ord-fornecedor');
        var busca = document.querySelector('.js-ord-fornecedor-search');
        var box = document.querySelector('.js-ord-fornecedor-sugestoes');
        if(sel && id !== ''){
            sel.value = id;
            var op = sel.options[sel.selectedIndex];
            if(busca && op) busca.value = String(op.textContent || '');
        }
        if(box){
            box.innerHTML = '';
            box.classList.add('hidden');
        }
    }
    if(ev.target && ev.target.classList.contains('btn_mandar_item_ordem')){
        var linha = ev.target.closest('.ordem-linha');
        aplicarItemBudjetNaLinha(linha, true);
    }
    if(ev.target && ev.target.classList.contains('js-ord-ir-itens')){
        // Avanco forcado solicitado: abre itens mesmo com validacao incompleta.
        abrirEtapaItensOrdem();
    }
});
var btnIrItensOrdem = document.getElementById('btn_ir_itens_ordem');
if(btnIrItensOrdem){
    btnIrItensOrdem.addEventListener('click', function(){
        // Avanco forcado solicitado: abre itens mesmo com validacao incompleta.
        abrirEtapaItensOrdem();
    });
}
atualizarFiltroFornecedorRequisicao();
atualizarFiltroFornecedorOrdem();
atualizarVisibilidadeCotacoesOrdem();
bloquearEtapaItensOrdem();
atualizarFiltrosCabecalhoOrdem();
atualizarOpcoesItensOrdem();
atualizarTotalOrdemCompra();
atualizarEstadoRemoverOrdem();
atualizarEstadoRemoverOrdemCotacao();
atualizarTotaisStockLinhas();
atualizarEstadoRemoverStock();
atualizarTotaisRequisicaoLinhas();
atualizarEstadoRemoverRequisicao();
atualizarTotaisCotacaoLinhas();
atualizarEstadoRemoverCotacao();
document.querySelectorAll('.js-fornecedor-form').forEach(inicializarFormularioFornecedorCredito);
atualizarPrevisaoAbates();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
