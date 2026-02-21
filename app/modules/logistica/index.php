<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: /vilcon-systemon/public/login.php'); exit; }

function norm($s){ return strtolower(trim((string)$s)); }
function money($v){ return number_format((float)$v,2,',','.') . ' MZN'; }
function st($s){ $s=norm($s); if($s==='aprovado')$s='aprovada'; if($s==='negado')$s='negada'; return in_array($s,['pendente','aprovada','negada','em transito','entregue','cancelada'],true)?$s:'pendente'; }
function stLabel($s){ $s=st($s); if($s==='em transito') return 'Em transito'; return ucfirst($s); }
function badge($s){ $s=norm($s); if(in_array($s,['aprovada','entregue','ativo'],true)) return 'ok'; if(in_array($s,['negada','cancelada','inativo'],true)) return 'danger'; return ($s==='em transito')?'info':'warn'; }
function departamentoCanonico($s){
  $d = norm($s);
  if(in_array($d, ['oficina','transporte'], true)) return $d;
  return 'oficina';
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
  ?string $criadoPor = null
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
    (departamento,tipo,valor,referencia,descricao,origem_tabela,origem_id,saldo_apos,criado_por)
    VALUES
    (:departamento,:tipo,:valor,:referencia,:descricao,:origem_tabela,:origem_id,:saldo_apos,:criado_por)
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
    'criado_por'=>$criadoPor
  ]);

  return ['departamento'=>$dep,'saldo_antes'=>$saldoAtual,'saldo_apos'=>$saldoApos];
}
function garantir(PDO $pdo){
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_requisicoes (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,origem VARCHAR(150) NOT NULL,destino VARCHAR(150) NOT NULL,item VARCHAR(180) NOT NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,unidade VARCHAR(20) NOT NULL DEFAULT 'un',prioridade VARCHAR(20) NOT NULL DEFAULT 'Normal',status VARCHAR(20) NOT NULL DEFAULT 'Pendente',data_requisicao DATE NOT NULL,responsavel VARCHAR(150) NULL,observacoes TEXT NULL,origem_modulo VARCHAR(40) NOT NULL DEFAULT 'logistica',categoria_item VARCHAR(40) NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_solicitante VARCHAR(30) NOT NULL DEFAULT 'oficina',valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,custo_total DECIMAL(14,2) NOT NULL DEFAULT 0,referencia_cotacao VARCHAR(120) NULL,anexo_preco_por VARCHAR(150) NULL,anexo_preco_em DATETIME NULL,decidido_por VARCHAR(150) NULL,decidido_em DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fornecedores (id INT AUTO_INCREMENT PRIMARY KEY,nome VARCHAR(180) NOT NULL,contacto VARCHAR(150) NULL,telefone VARCHAR(50) NULL,email VARCHAR(150) NULL,nuit VARCHAR(50) NULL,tipo_fornecedor VARCHAR(80) NOT NULL DEFAULT 'Pecas',escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',status VARCHAR(20) NOT NULL DEFAULT 'Ativo',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(50) UNIQUE,nome VARCHAR(180) NOT NULL,categoria VARCHAR(80) NOT NULL DEFAULT 'Peca',unidade VARCHAR(20) NOT NULL DEFAULT 'un',stock_atual DECIMAL(12,2) NOT NULL DEFAULT 0,stock_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,preco_referencia DECIMAL(14,2) NOT NULL DEFAULT 0,fornecedor_preferencial_id INT NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_aplicacao VARCHAR(30) NOT NULL DEFAULT 'oficina',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_movimentos_stock (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NOT NULL,tipo_movimento ENUM('Entrada','Saida','Ajuste') NOT NULL DEFAULT 'Entrada',quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,custo_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,referencia VARCHAR(120) NULL,observacoes TEXT NULL,criado_por INT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_cotacoes (id INT AUTO_INCREMENT PRIMARY KEY,fornecedor_id INT NOT NULL,item_nome VARCHAR(180) NOT NULL,categoria_item VARCHAR(80) NOT NULL DEFAULT 'Peca',preco_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,prazo_dias INT NOT NULL DEFAULT 0,validade DATE NULL,escopo_logistica VARCHAR(20) NOT NULL DEFAULT 'operacional',area_solicitante VARCHAR(30) NOT NULL DEFAULT 'oficina',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas_substituidas (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NOT NULL,matricula_ativo VARCHAR(50) NOT NULL,area_origem VARCHAR(30) NOT NULL DEFAULT 'Oficina',quantidade DECIMAL(12,2) NOT NULL DEFAULT 1,custo_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,data_substituicao DATE NOT NULL,motivo VARCHAR(200) NULL,responsavel VARCHAR(150) NULL,referencia_os VARCHAR(60) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fin_facturas (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,fornecedor_id INT NULL,departamento VARCHAR(30) NOT NULL DEFAULT 'oficina',descricao VARCHAR(180) NOT NULL,valor_total DECIMAL(14,2) NOT NULL DEFAULT 0,data_factura DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Pendente',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_fin_pagamentos (id INT AUTO_INCREMENT PRIMARY KEY,codigo VARCHAR(40) UNIQUE,factura_id INT NULL,descricao VARCHAR(180) NOT NULL,valor_pago DECIMAL(14,2) NOT NULL DEFAULT 0,data_pagamento DATE NOT NULL,metodo VARCHAR(40) NOT NULL DEFAULT 'Transferencia',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_pecas_avariadas (id INT AUTO_INCREMENT PRIMARY KEY,peca_id INT NULL,codigo_peca VARCHAR(50) NULL,nome_peca VARCHAR(180) NOT NULL,quantidade DECIMAL(12,2) NOT NULL DEFAULT 1,motivo VARCHAR(200) NULL,data_registo DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'Avariada',observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_operacional_custos (id INT AUTO_INCREMENT PRIMARY KEY,categoria VARCHAR(40) NOT NULL,departamento VARCHAR(30) NOT NULL DEFAULT 'transporte',descricao VARCHAR(180) NOT NULL,valor DECIMAL(14,2) NOT NULL DEFAULT 0,data_lancamento DATE NOT NULL,responsavel VARCHAR(150) NULL,observacoes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_budjet_departamentos (id INT AUTO_INCREMENT PRIMARY KEY,departamento VARCHAR(30) NOT NULL UNIQUE,orcamento_total DECIMAL(14,2) NOT NULL DEFAULT 0,saldo_atual DECIMAL(14,2) NOT NULL DEFAULT 0,atualizado_em DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS logistica_budjet_movimentos (id INT AUTO_INCREMENT PRIMARY KEY,departamento VARCHAR(30) NOT NULL,tipo VARCHAR(20) NOT NULL,valor DECIMAL(14,2) NOT NULL DEFAULT 0,referencia VARCHAR(120) NULL,descricao VARCHAR(255) NULL,origem_tabela VARCHAR(80) NULL,origem_id INT NULL,saldo_apos DECIMAL(14,2) NOT NULL DEFAULT 0,criado_por VARCHAR(150) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_budjet_dep_data (departamento,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
$garantirColuna($pdo, 'logistica_requisicoes', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_requisicoes', 'area_solicitante', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
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

$garantirColuna($pdo, 'logistica_pecas', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_pecas', 'area_aplicacao', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_pecas', 'preco_referencia', 'DECIMAL(14,2) NOT NULL DEFAULT 0');

$garantirColuna($pdo, 'logistica_cotacoes', 'escopo_logistica', "VARCHAR(20) NOT NULL DEFAULT 'operacional'");
$garantirColuna($pdo, 'logistica_cotacoes', 'area_solicitante', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");

$garantirColuna($pdo, 'logistica_pecas_substituidas', 'area_origem', "VARCHAR(30) NOT NULL DEFAULT 'Oficina'");
$garantirColuna($pdo, 'logistica_fin_facturas', 'departamento', "VARCHAR(30) NOT NULL DEFAULT 'oficina'");
$garantirColuna($pdo, 'logistica_operacional_custos', 'departamento', "VARCHAR(30) NOT NULL DEFAULT 'transporte'");

$pdo->exec("INSERT INTO logistica_budjet_departamentos (departamento,orcamento_total,saldo_atual,atualizado_em) VALUES ('oficina',0,0,NOW()) ON DUPLICATE KEY UPDATE departamento=departamento");
$pdo->exec("INSERT INTO logistica_budjet_departamentos (departamento,orcamento_total,saldo_atual,atualizado_em) VALUES ('transporte',0,0,NOW()) ON DUPLICATE KEY UPDATE departamento=departamento");
}

$view = $_GET['view'] ?? 'painel';
$mode = $_GET['mode'] ?? 'list';
$q = trim((string)($_GET['q'] ?? ''));
$status_filtro = trim((string)($_GET['status'] ?? 'todos'));
$departamento_filtro = trim((string)($_GET['departamento'] ?? 'todos'));
$escopo_filtro = trim((string)($_GET['escopo'] ?? 'todos'));
$periodo = trim((string)($_GET['periodo'] ?? 'mensal'));
if(!in_array($view,['painel','requisicoes','fornecedores','pecas','cotacoes','substituicoes','relatorios','pedidos_oficina','extratos','facturas','pagamentos','pecas_avariadas','oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes','budjet','alertas'],true)) $view='painel';
if($view==='painel') $view='pedidos_oficina';
if(!in_array($mode,['list','form'],true)) $mode='list';
if(in_array($view,['painel','relatorios','pedidos_oficina','extratos','budjet','alertas'],true)) $mode='list';

$perfil = norm($_SESSION['usuario_perfil'] ?? '');
$pode_oper = in_array($perfil,['logistica','logistico','logisticaoperacional','supervisorlogistica','admin','administrador'],true);
$pode_geral = in_array($perfil,['logisticageral','supervisorlogistica','admin','administrador'],true);
$operacional_sem_restricao = true;

function secaoLogistica(string $view): string {
  if($view==='pedidos_oficina') return 'requisicoes';
  if(in_array($view,['extratos','fornecedores','facturas','cotacoes','pagamentos'],true)) return 'financas';
  if(in_array($view,['pecas','substituicoes','pecas_avariadas'],true)) return 'stock';
  if($view==='budjet') return 'budjet';
  if($view==='alertas') return 'alertas';
  if(in_array($view,['oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true)) return 'operacional';
  return 'requisicoes';
}
$secao = secaoLogistica($view);

function opcoesSecaoLogistica(string $secao): array {
  if($secao==='requisicoes') return ['pedidos_oficina' => 'Pedidos recebidos da Oficina'];
  if($secao==='financas') return [
    'extratos' => 'Extratos',
    'fornecedores' => 'Fornecedores',
    'facturas' => 'Facturas',
    'cotacoes' => 'Cotacoes',
    'pagamentos' => 'Pagamentos',
  ];
  if($secao==='stock') return [
    'pecas' => 'Armazem de pecas',
    'substituicoes' => 'Pecas substituidas',
    'pecas_avariadas' => 'Pecas avariadas',
  ];
  if($secao==='operacional') return [
    'oper_uniforme' => 'Controle uniforme',
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
$msg=''; $erro=''; $requisicoes=[]; $fornecedores=[]; $pecas=[]; $cotacoes=[]; $comp=[]; $subs=[]; $fornRef=[]; $pecasRef=[]; $rel=[]; $pedidosOficina=[]; $facturas=[]; $pagamentos=[]; $pecasAvariadas=[]; $custosOperacionais=[]; $budjetResumo=[]; $budjetMovimentos=[];

try {
  garantir($pdo);

  if($_SERVER['REQUEST_METHOD']==='POST'){
    $acao = $_POST['acao'] ?? '';
    if($acao==='criar_requisicao'){
      $escopo = in_array($_POST['escopo_logistica'] ?? '',['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional';
      $area = in_array($_POST['area_solicitante'] ?? '',['oficina','transporte','geral'],true)?$_POST['area_solicitante']:'oficina';
      if(!$operacional_sem_restricao && (($escopo==='operacional'&&!$pode_oper)||($escopo==='geral'&&!$pode_geral))) throw new RuntimeException('Sem permissao neste escopo');
      $origem=trim((string)($_POST['origem']??'')); $destino=trim((string)($_POST['destino']??'')); $item=trim((string)($_POST['item']??''));
      $qtd=(float)($_POST['quantidade']??0); if($origem===''||$destino===''||$item===''||$qtd<=0) throw new RuntimeException('Campos obrigatorios incompletos');
      $stmt=$pdo->prepare("INSERT INTO logistica_requisicoes (origem,destino,item,quantidade,unidade,prioridade,status,data_requisicao,responsavel,observacoes,origem_modulo,categoria_item,escopo_logistica,area_solicitante,valor_total,custo_total) VALUES (:origem,:destino,:item,:qtd,:unidade,:prioridade,'Pendente',:data,:resp,:obs,'logistica',:cat,:escopo,:area,:valor,:valor)");
      $stmt->execute(['origem'=>$origem,'destino'=>$destino,'item'=>$item,'qtd'=>$qtd,'unidade'=>trim((string)($_POST['unidade']??'un'))?:'un','prioridade'=>trim((string)($_POST['prioridade']??'Normal'))?:'Normal','data'=>trim((string)($_POST['data_requisicao']??date('Y-m-d'))),'resp'=>trim((string)($_POST['responsavel']??''))?:null,'obs'=>trim((string)($_POST['observacoes']??''))?:null,'cat'=>trim((string)($_POST['categoria_item']??'Peca')),'escopo'=>$escopo,'area'=>$area,'valor'=>(float)($_POST['valor_total']??0)]);
      $id=(int)$pdo->lastInsertId(); $codigo=sprintf('REQ-LOG-%s-%04d',date('Y'),$id); $pdo->prepare('UPDATE logistica_requisicoes SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);
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
        $s=$pdo->prepare('SELECT codigo, area_solicitante, COALESCE(valor_total,custo_total,0) AS valor_total, budjet_debitado FROM logistica_requisicoes WHERE id=:i LIMIT 1');
        $s->execute(['i'=>$id]);
        $rReq=$s->fetch(PDO::FETCH_ASSOC) ?: null;
        $valorAtual=(float)($rReq['valor_total'] ?? 0);
        if($valorAtual <= 0){
          throw new RuntimeException('Antes de aprovar, anexe o preco pesquisado da requisicao.');
        }
        $pdo->beginTransaction();
        if((int)($rReq['budjet_debitado'] ?? 0) !== 1){
          movimentarBudjet(
            $pdo,
            departamentoCanonico((string)($rReq['area_solicitante'] ?? $area)),
            'debito',
            $valorAtual,
            (string)($rReq['codigo'] ?? ('REQ-' . $id)),
            'Aprovacao de requisicao com preco validado',
            'logistica_requisicoes',
            $id,
            (string)($_SESSION['usuario_nome'] ?? 'Logistica')
          );
          $pdo->prepare('UPDATE logistica_requisicoes SET budjet_debitado=1, budjet_debitado_em=NOW(), budjet_debito_valor=:v WHERE id=:i')
            ->execute(['v'=>$valorAtual,'i'=>$id]);
        }
        $pdo->prepare('UPDATE logistica_requisicoes SET status=:s,decidido_por=:u,decidido_em=NOW() WHERE id=:i')
          ->execute(['s'=>stLabel($_POST['novo_status']??''),'u'=>(string)($_SESSION['usuario_nome']??'Logistica'),'i'=>$id]);
        $pdo->commit();
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

      if((int)($req['budjet_debitado'] ?? 0) !== 1){
        $departamentoBudjet = departamentoCanonico((string)($req['area_solicitante'] ?? 'oficina'));
        movimentarBudjet(
          $pdo,
          $departamentoBudjet,
          'debito',
          $valor,
          (string)($req['codigo'] ?? ('REQ-' . $id)),
          'Compra processada da requisicao',
          'logistica_requisicoes',
          $id,
          (string)($_SESSION['usuario_nome'] ?? 'Logistica')
        );
        $pdo->prepare('UPDATE logistica_requisicoes SET budjet_debitado=1, budjet_debitado_em=NOW(), budjet_debito_valor=:v WHERE id=:i')
          ->execute(['v'=>$valor,'i'=>$id]);
      }

      if($forma==='credito' && $fornecedorId){
        $stFor = $pdo->prepare("SELECT saldo_budjet,nome FROM logistica_fornecedores WHERE id=:i LIMIT 1");
        $stFor->execute(['i'=>$fornecedorId]);
        $forn = $stFor->fetch(PDO::FETCH_ASSOC);
        if(!$forn) throw new RuntimeException('Fornecedor nao encontrado para credito');
        $saldoAtual = (float)($forn['saldo_budjet'] ?? 0);
        if($saldoAtual < $valor) throw new RuntimeException('Saldo de budjet insuficiente do fornecedor para esta compra');
        $pdo->prepare("UPDATE logistica_fornecedores SET saldo_budjet = saldo_budjet - :v WHERE id=:i")
          ->execute(['v'=>$valor,'i'=>$fornecedorId]);
        $obs .= ($obs!=='' ? "\n" : '') . '[Credito] Debito no budjet do fornecedor: ' . (string)($forn['nome'] ?? ('#'.$fornecedorId));
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
    if($acao==='budjet_creditar'){
      $dep = departamentoCanonico($_POST['departamento'] ?? 'oficina');
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
        (string)($_SESSION['usuario_nome'] ?? 'Logistica')
      );
      $pdo->commit();
      header('Location: ?view=budjet&departamento=' . urlencode($dep) . '&updated=1'); exit;
    }
    if($acao==='criar_fornecedor'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $nome=trim((string)($_POST['nome']??'')); if($nome==='') throw new RuntimeException('Nome obrigatorio');
      $pdo->prepare('INSERT INTO logistica_fornecedores (nome,contacto,telefone,email,nuit,tipo_fornecedor,escopo_logistica,status,saldo_budjet,observacoes) VALUES (:n,:c,:t,:e,:nu,:tipo,:esc,:st,:sb,:o)')->execute(['n'=>$nome,'c'=>trim((string)($_POST['contacto']??''))?:null,'t'=>trim((string)($_POST['telefone']??''))?:null,'e'=>trim((string)($_POST['email']??''))?:null,'nu'=>trim((string)($_POST['nuit']??''))?:null,'tipo'=>trim((string)($_POST['tipo_fornecedor']??'Pecas')),'esc'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional','st'=>in_array($_POST['status']??'', ['Ativo','Inativo'],true)?$_POST['status']:'Ativo','sb'=>(float)($_POST['saldo_budjet']??0),'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      header('Location: ?view=fornecedores&mode=list&saved=1'); exit;
    }
    if($acao==='criar_peca'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $nome=trim((string)($_POST['nome']??'')); if($nome==='') throw new RuntimeException('Nome obrigatorio');
      $pdo->prepare('INSERT INTO logistica_pecas (codigo,nome,categoria,unidade,stock_atual,stock_minimo,preco_referencia,fornecedor_preferencial_id,escopo_logistica,area_aplicacao) VALUES (:cod,:n,:cat,:u,:sa,:sm,:pr,:fp,:esc,:ar)')->execute(['cod'=>trim((string)($_POST['codigo']??''))?:null,'n'=>$nome,'cat'=>trim((string)($_POST['categoria']??'Peca')),'u'=>trim((string)($_POST['unidade']??'un'))?:'un','sa'=>(float)($_POST['stock_atual']??0),'sm'=>(float)($_POST['stock_minimo']??0),'pr'=>(float)($_POST['preco_referencia']??0),'fp'=>((int)($_POST['fornecedor_preferencial_id']??0))>0?(int)$_POST['fornecedor_preferencial_id']:null,'esc'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional','ar'=>in_array($_POST['area_aplicacao']??'', ['oficina','transporte','geral'],true)?$_POST['area_aplicacao']:'oficina']);
      header('Location: ?view=pecas&mode=list&saved=1'); exit;
    }
    if($acao==='ajustar_stock'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $id=(int)($_POST['peca_id']??0); $qtd=(float)($_POST['quantidade']??0); $tipo=$_POST['tipo_movimento']??'Entrada';
      if($id<=0||$qtd<=0) throw new RuntimeException('Movimento invalido'); if(!in_array($tipo,['Entrada','Saida','Ajuste'],true)) $tipo='Entrada';
      $pdo->beginTransaction();
      $custoUnit = (float)($_POST['custo_unitario']??0);
      $pdo->prepare('INSERT INTO logistica_movimentos_stock (peca_id,tipo_movimento,quantidade,custo_unitario,referencia,observacoes,criado_por) VALUES (:p,:t,:q,:c,:r,:o,:u)')->execute(['p'=>$id,'t'=>$tipo,'q'=>$qtd,'c'=>(float)($_POST['custo_unitario']??0),'r'=>trim((string)($_POST['referencia']??''))?:null,'o'=>trim((string)($_POST['observacoes']??''))?:null,'u'=>(int)($_SESSION['usuario_id']??0)]);
      if($tipo==='Entrada') {
        $pdo->prepare('UPDATE logistica_pecas SET stock_atual=stock_atual+:q WHERE id=:i')->execute(['q'=>$qtd,'i'=>$id]);
        $stPeca = $pdo->prepare("SELECT codigo,nome,area_aplicacao FROM logistica_pecas WHERE id=:i LIMIT 1");
        $stPeca->execute(['i'=>$id]);
        $pecaRow = $stPeca->fetch(PDO::FETCH_ASSOC) ?: null;
        $valorEntrada = $qtd * $custoUnit;
        if($valorEntrada > 0){
          $dep = departamentoCanonico((string)($pecaRow['area_aplicacao'] ?? 'oficina'));
          movimentarBudjet(
            $pdo,
            $dep,
            'debito',
            $valorEntrada,
            trim((string)($_POST['referencia']??'')) !== '' ? trim((string)$_POST['referencia']) : ('STK-ENT-' . $id . '-' . date('YmdHis')),
            'Compra/entrada de stock: ' . (string)($pecaRow['nome'] ?? ('Peca #' . $id)),
            'logistica_movimentos_stock',
            $id,
            (string)($_SESSION['usuario_nome'] ?? 'Logistica')
          );
        }
      }
      elseif($tipo==='Saida') $pdo->prepare('UPDATE logistica_pecas SET stock_atual=GREATEST(0,stock_atual-:q) WHERE id=:i')->execute(['q'=>$qtd,'i'=>$id]);
      else $pdo->prepare('UPDATE logistica_pecas SET stock_atual=:q WHERE id=:i')->execute(['q'=>$qtd,'i'=>$id]);
      $pdo->commit(); header('Location: ?view=pecas&mode=list&updated=1'); exit;
    }
    if($acao==='criar_cotacao'){
      if(!$operacional_sem_restricao && !$pode_oper&&!$pode_geral) throw new RuntimeException('Sem permissao');
      $fid=(int)($_POST['fornecedor_id']??0); $item=trim((string)($_POST['item_nome']??'')); $pre=(float)($_POST['preco_unitario']??0);
      if($fid<=0||$item===''||$pre<=0) throw new RuntimeException('Fornecedor, item e preco obrigatorios');
      $pdo->prepare('INSERT INTO logistica_cotacoes (fornecedor_id,item_nome,categoria_item,preco_unitario,prazo_dias,validade,escopo_logistica,area_solicitante,observacoes) VALUES (:f,:i,:c,:p,:d,:v,:e,:a,:o)')->execute(['f'=>$fid,'i'=>$item,'c'=>trim((string)($_POST['categoria_item']??'Peca')),'p'=>$pre,'d'=>(int)($_POST['prazo_dias']??0),'v'=>trim((string)($_POST['validade']??''))?:null,'e'=>in_array($_POST['escopo_logistica']??'', ['operacional','geral'],true)?$_POST['escopo_logistica']:'operacional','a'=>in_array($_POST['area_solicitante']??'', ['oficina','transporte','geral'],true)?$_POST['area_solicitante']:'oficina','o'=>trim((string)($_POST['observacoes']??''))?:null]);
      header('Location: ?view=cotacoes&mode=list&saved=1'); exit;
    }
    if($acao==='registar_substituicao'){
      if(!$operacional_sem_restricao && !$pode_oper) throw new RuntimeException('Substituicoes exigem operacional');
      $pid=(int)($_POST['peca_id']??0); $mat=trim((string)($_POST['matricula_ativo']??'')); $qtd=(float)($_POST['quantidade']??1);
      if($pid<=0||$mat===''||$qtd<=0) throw new RuntimeException('Dados invalidos');
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_pecas_substituidas (peca_id,matricula_ativo,area_origem,quantidade,custo_unitario,data_substituicao,motivo,responsavel,referencia_os) VALUES (:p,:m,:a,:q,:c,:d,:mo,:r,:os)')->execute(['p'=>$pid,'m'=>$mat,'a'=>in_array($_POST['area_origem']??'', ['Oficina','Transporte'],true)?$_POST['area_origem']:'Oficina','q'=>$qtd,'c'=>(float)($_POST['custo_unitario']??0),'d'=>trim((string)($_POST['data_substituicao']??date('Y-m-d'))),'mo'=>trim((string)($_POST['motivo']??''))?:null,'r'=>trim((string)($_POST['responsavel']??''))?:null,'os'=>trim((string)($_POST['referencia_os']??''))?:null]);
      $pdo->prepare('UPDATE logistica_pecas SET stock_atual=GREATEST(0,stock_atual-:q) WHERE id=:i')->execute(['q'=>$qtd,'i'=>$pid]);
      $pdo->commit(); header('Location: ?view=substituicoes&mode=list&saved=1'); exit;
    }
    if($acao==='criar_factura'){
      $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor_total']??0); $data=trim((string)($_POST['data_factura']??date('Y-m-d')));
      if($descricao===''||$valor<=0) throw new RuntimeException('Descricao e valor da factura sao obrigatorios');
      $fid=((int)($_POST['fornecedor_id']??0))>0?(int)$_POST['fornecedor_id']:null;
      $departamento = departamentoCanonico($_POST['departamento'] ?? 'oficina');
      $st=in_array($_POST['status']??'', ['Pendente','Pago','Parcial'], true)?$_POST['status']:'Pendente';
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_fin_facturas (fornecedor_id,departamento,descricao,valor_total,data_factura,status,observacoes) VALUES (:f,:dep,:d,:v,:dt,:s,:o)')
        ->execute(['f'=>$fid,'dep'=>$departamento,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'s'=>$st,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
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
        (string)($_SESSION['usuario_nome'] ?? 'Logistica')
      );
      $pdo->commit();
      header('Location: ?view=facturas&mode=list&saved=1'); exit;
    }
    if($acao==='criar_pagamento'){
      $descricao=trim((string)($_POST['descricao']??'')); $valor=(float)($_POST['valor_pago']??0); $data=trim((string)($_POST['data_pagamento']??date('Y-m-d')));
      if($descricao===''||$valor<=0) throw new RuntimeException('Descricao e valor do pagamento sao obrigatorios');
      $facturaId=((int)($_POST['factura_id']??0))>0?(int)$_POST['factura_id']:null;
      $metodo=trim((string)($_POST['metodo']??'Transferencia')) ?: 'Transferencia';
      $pdo->prepare('INSERT INTO logistica_fin_pagamentos (factura_id,descricao,valor_pago,data_pagamento,metodo,observacoes) VALUES (:f,:d,:v,:dt,:m,:o)')
        ->execute(['f'=>$facturaId,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'m'=>$metodo,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
      $id=(int)$pdo->lastInsertId(); $codigo=sprintf('PAG-%s-%04d',date('Y'),$id); $pdo->prepare('UPDATE logistica_fin_pagamentos SET codigo=:c WHERE id=:i')->execute(['c'=>$codigo,'i'=>$id]);
      header('Location: ?view=pagamentos&mode=list&saved=1'); exit;
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
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO logistica_operacional_custos (categoria,departamento,descricao,valor,data_lancamento,responsavel,observacoes) VALUES (:c,:dep,:d,:v,:dt,:r,:o)')
        ->execute(['c'=>$categoria,'dep'=>$departamento,'d'=>$descricao,'v'=>$valor,'dt'=>$data,'r'=>trim((string)($_POST['responsavel']??''))?:null,'o'=>trim((string)($_POST['observacoes']??''))?:null]);
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
        (string)($_SESSION['usuario_nome'] ?? 'Logistica')
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

  $fornecedores=$pdo->query('SELECT * FROM logistica_fornecedores ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $fornRef=$pdo->query("SELECT id,nome FROM logistica_fornecedores WHERE status='Ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pecas=$pdo->query('SELECT p.*, f.nome AS fornecedor_preferencial FROM logistica_pecas p LEFT JOIN logistica_fornecedores f ON f.id=p.fornecedor_preferencial_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pecasRef=$pdo->query('SELECT id,codigo,nome FROM logistica_pecas ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $cotacoes=$pdo->query('SELECT c.*, f.nome AS fornecedor_nome FROM logistica_cotacoes c INNER JOIN logistica_fornecedores f ON f.id=c.fornecedor_id ORDER BY c.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $comp=$pdo->query('SELECT item_nome,categoria_item,MIN(preco_unitario) AS melhor_preco,MAX(preco_unitario) AS maior_preco,AVG(preco_unitario) AS preco_medio,COUNT(*) AS total_cotacoes FROM logistica_cotacoes GROUP BY item_nome,categoria_item ORDER BY item_nome ASC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $subs=$pdo->query('SELECT s.*, p.codigo AS peca_codigo, p.nome AS peca_nome FROM logistica_pecas_substituidas s INNER JOIN logistica_pecas p ON p.id=s.peca_id ORDER BY s.id DESC')->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pedidosOficina=$pdo->query("SELECT r.id,r.codigo,r.item,r.quantidade,r.unidade,r.status,r.data_requisicao,r.responsavel,r.referencia_cotacao,r.fornecedor_id,r.forma_pagamento,r.tipo_compra,r.cotacao_automatica,r.cotacao_id,COALESCE(r.valor_total,r.custo_total,0) AS valor_total,f.nome AS fornecedor_nome FROM logistica_requisicoes r LEFT JOIN logistica_fornecedores f ON f.id=r.fornecedor_id WHERE r.origem_modulo='oficina' ORDER BY r.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $facturas=$pdo->query("SELECT f.*, fr.nome AS fornecedor_nome FROM logistica_fin_facturas f LEFT JOIN logistica_fornecedores fr ON fr.id=f.fornecedor_id ORDER BY f.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pagamentos=$pdo->query("SELECT p.*, f.codigo AS factura_codigo FROM logistica_fin_pagamentos p LEFT JOIN logistica_fin_facturas f ON f.id=p.factura_id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $pecasAvariadas=$pdo->query("SELECT * FROM logistica_pecas_avariadas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $custosOperacionais=$pdo->query("SELECT * FROM logistica_operacional_custos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $budRows = $pdo->query("SELECT departamento,orcamento_total,saldo_atual FROM logistica_budjet_departamentos WHERE departamento IN ('oficina','transporte')")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $aggRows = $pdo->query("SELECT departamento, COALESCE(SUM(CASE WHEN tipo='Credito' THEN valor ELSE 0 END),0) AS total_creditos, COALESCE(SUM(CASE WHEN tipo='Debito' THEN valor ELSE 0 END),0) AS total_debitos FROM logistica_budjet_movimentos WHERE departamento IN ('oficina','transporte') GROUP BY departamento")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $aggMap = [];
  foreach($aggRows as $ar){ $aggMap[(string)$ar['departamento']] = $ar; }
  foreach(['oficina','transporte'] as $dep){
    $br = null;
    foreach($budRows as $b){ if((string)$b['departamento'] === $dep){ $br = $b; break; } }
    $budjetResumo[$dep] = [
      'departamento' => $dep,
      'orcamento_total' => (float)($br['orcamento_total'] ?? 0),
      'saldo_atual' => (float)($br['saldo_atual'] ?? 0),
      'total_creditos' => (float)($aggMap[$dep]['total_creditos'] ?? 0),
      'total_debitos' => (float)($aggMap[$dep]['total_debitos'] ?? 0)
    ];
  }
  if($budjetDepartamentoSelecionado !== ''){
    $stBudMov = $pdo->prepare("SELECT * FROM logistica_budjet_movimentos WHERE departamento=:d ORDER BY id DESC LIMIT 200");
    $stBudMov->execute(['d'=>$budjetDepartamentoSelecionado]);
    $budjetMovimentos = $stBudMov->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }

  $totalFacturas = (float)($pdo->query("SELECT COALESCE(SUM(valor_total),0) FROM logistica_fin_facturas")->fetchColumn() ?: 0);
  $totalPagamentos = (float)($pdo->query("SELECT COALESCE(SUM(valor_pago),0) FROM logistica_fin_pagamentos")->fetchColumn() ?: 0);
  $saldoPagar = $totalFacturas - $totalPagamentos;
  $rel['total_facturas'] = count($facturas);
  $rel['total_pagamentos'] = count($pagamentos);
  $rel['total_valor_facturas'] = $totalFacturas;
  $rel['total_valor_pagamentos'] = $totalPagamentos;
  $rel['saldo_a_pagar'] = $saldoPagar;

  $di=date('Y-m-d'); $df=date('Y-m-d');
  if($periodo==='semanal'){ $dt=new DateTimeImmutable(); $n=(int)$dt->format('N'); $di=$dt->modify('-'.($n-1).' days')->format('Y-m-d'); $df=$dt->modify('+'.(7-$n).' days')->format('Y-m-d'); }
  elseif($periodo==='mensal'){ $dt=new DateTimeImmutable(); $di=$dt->modify('first day of this month')->format('Y-m-d'); $df=$dt->modify('last day of this month')->format('Y-m-d'); }
  $r=$pdo->prepare("SELECT (SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df) AS total_requisicoes,(SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df AND LOWER(status)='aprovada') AS requisicoes_aprovadas,(SELECT COUNT(*) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df AND LOWER(status)='negada') AS requisicoes_negadas,(SELECT COALESCE(SUM(COALESCE(valor_total,custo_total,0)),0) FROM logistica_requisicoes WHERE data_requisicao BETWEEN :di AND :df) AS total_valor_requisicoes,(SELECT COUNT(*) FROM logistica_fornecedores WHERE status='Ativo') AS fornecedores_ativos,(SELECT COUNT(*) FROM logistica_pecas WHERE stock_atual<=stock_minimo) AS pecas_stock_baixo,(SELECT COALESCE(SUM(stock_atual*preco_referencia),0) FROM logistica_pecas) AS valor_stock,(SELECT COALESCE(SUM(quantidade*custo_unitario),0) FROM logistica_pecas_substituidas WHERE data_substituicao BETWEEN :di AND :df) AS custo_substituicoes");
  $r->execute(['di'=>$di,'df'=>$df]); $rel=$r->fetch(PDO::FETCH_ASSOC)?:[];

}catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); $erro='Nao foi possivel processar Logistica: '.$e->getMessage(); }

$total=count($requisicoes); $pend=0; $ap=0; $ng=0;
foreach($requisicoes as $r){ $s=st((string)($r['status']??'')); if($s==='pendente')$pend++; if(in_array($s,['aprovada','entregue'],true))$ap++; if(in_array($s,['negada','cancelada'],true))$ng++; }
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
            --vilcon-black: #1a1a1a;
            --vilcon-orange: #f39c12;
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
        .logi-main-tabs { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:12px; }
        .tab-menu { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab-btn { padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; border: 1px solid #ddd; color: #666 !important; text-transform: uppercase; transition: 0.3s; display: flex; align-items: center; gap: 8px; background:#fff; }
        .tab-btn.active { background: var(--vilcon-orange); color: #fff !important; border-color: var(--vilcon-orange); }
        .sub-tab-container { background: #eee; padding: 8px; border-radius: 8px; margin: 10px 0 12px 0; display: flex; gap: 5px; flex-wrap: wrap; }
        .sub-tab-btn { padding: 8px 18px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: 10px; color: #555; text-transform: uppercase; transition: 0.2s; background:transparent; }
        .sub-tab-btn.active { background: #fff; color: var(--vilcon-black); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .logi-kpis { display: grid; grid-template-columns: repeat(4,minmax(120px,1fr)); gap: 8px; margin-bottom: 10px; }
        .logi-kpi { border: 1px solid #e5e7eb; background: #f8fafc !important; border-radius: 10px; padding: 10px; font-size: 13px; color:#0f172a !important; }
        .logi-filters, .logi-form, .logi-inline-form { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .logi-inner-nav { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px dashed #ddd; flex-wrap:wrap; }
        .logi-filters.fixed-position { margin-bottom: 0; align-items:center; }
        .logi-filters.fixed-position input[name="q"] { min-width: 300px; }
        .logi-filters input, .logi-filters select, .logi-form input, .logi-form select, .logi-inline-form input, .logi-inline-form select {
            border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 13px; min-height: 36px;
        }
        .logi-filters button, .logi-form button, .logi-inline-form button, .logi-action-btn {
            border: 1px solid var(--logi-primary); border-radius: 8px; background: var(--logi-primary); color: #fff; padding: 8px 12px; font-size: 13px; cursor: pointer;
        }
        .logi-filters button:hover, .logi-form button:hover, .logi-inline-form button:hover, .logi-action-btn:hover { background: var(--logi-primary-dark); border-color: var(--logi-primary-dark); }
        .mode-selector { margin-bottom: 10px; display: flex; gap: 10px; flex-wrap:wrap; }
        .btn-mode { padding: 8px 15px; border-radius: 20px; font-size: 11px; font-weight: 700; text-decoration: none; text-transform: uppercase; border: 1px solid #ddd; color: #666; background: #fff; }
        .btn-mode.active { background: var(--vilcon-black); color: #fff; border-color: var(--vilcon-black); }
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
        .budjet-grid { display:grid; grid-template-columns:repeat(2,minmax(280px,1fr)); gap:16px; }
        .budjet-card { border:1px solid var(--logi-border); border-radius:14px; padding:18px; background:linear-gradient(180deg,#ffffff 0%,#fff8f2 100%); box-shadow:0 2px 10px rgba(230,126,34,.10); }
        .budjet-card h3 { margin:0 0 6px 0; font-size:22px; color:#0f172a; }
        .budjet-card .budjet-icon { font-size:28px; color:var(--logi-primary); margin-bottom:8px; }
        .budjet-card .budjet-value { font-size:28px; font-weight:800; color:var(--logi-dark); margin:6px 0 10px; }
        .budjet-meta { color:#475569; font-size:13px; margin-bottom:10px; }
        .budjet-detail-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px; flex-wrap:wrap; }
        .budjet-pill { display:inline-block; border:1px solid var(--logi-border); color:#7c2d12; background:var(--logi-soft); border-radius:999px; padding:5px 10px; font-size:12px; }
        .budjet-resumo { display:grid; grid-template-columns:repeat(3,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
        .budjet-box { border:1px solid #e2e8f0; border-radius:10px; background:#fff; padding:10px; }
        .budjet-box .k { font-size:12px; color:#64748b; }
        .budjet-box .v { font-size:20px; font-weight:700; color:#0f172a; }
        .budjet-reforco { margin:10px 0 14px; padding:10px; border:1px dashed var(--logi-primary); border-radius:10px; background:var(--logi-soft); }
        .budjet-reforco .logi-inline-form { margin:0; }
        @media (max-width: 900px) { .logi-kpis { grid-template-columns: repeat(2,minmax(120px,1fr)); } }
        @media (max-width: 900px) { .budjet-grid { grid-template-columns: 1fr; } .budjet-resumo { grid-template-columns: 1fr; } }
    </style>

    <div class="top-bar">
        <h2>Logistica</h2>
        <div class="user-info"><strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Utilizador') ?></strong></div>
    </div>

    <div class="dashboard-container logi-page">
        <div class="logi-main-tabs">
            <div class="tab-menu">
                <a href="?view=pedidos_oficina&mode=list" class="tab-btn <?= $secao==='requisicoes' ? 'active' : '' ?>">Pedidos recebidos da Oficina</a>
                <a href="?view=extratos&mode=list" class="tab-btn <?= $secao==='financas' ? 'active' : '' ?>">Financas</a>
                <a href="?view=pecas&mode=list" class="tab-btn <?= $secao==='stock' ? 'active' : '' ?>">Controle de Stock</a>
                <a href="?view=oper_uniforme&mode=list" class="tab-btn <?= $secao==='operacional' ? 'active' : '' ?>">Logistica Operacional</a>
                <a href="?view=budjet&mode=list" class="tab-btn <?= $secao==='budjet' ? 'active' : '' ?>">Budjet</a>
                <a href="?view=alertas&mode=list" class="tab-btn <?= $secao==='alertas' ? 'active' : '' ?>">Alertas</a>
                <a href="?view=relatorios&mode=list" class="tab-btn <?= $view==='relatorios' ? 'active' : '' ?>">Relatorios</a>
            </div>
        </div>
        <?php $opcoesSecao = opcoesSecaoLogistica($secao); ?>
        <?php if(!empty($opcoesSecao) && $secao !== 'budjet' && $secao !== 'requisicoes'): ?>
            <div class="sub-tab-container">
                <?php foreach($opcoesSecao as $k => $lbl): ?>
                    <a
                        href="?view=<?= urlencode((string)$k) ?>&mode=list"
                        class="sub-tab-btn <?= $view===$k ? 'active' : '' ?>"
                    ><?= htmlspecialchars((string)$lbl) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="logi-card">
            <?php if(!in_array($view, ['requisicoes','pedidos_oficina','budjet'], true)): ?>
                <div class="logi-kpis">
                    <div class="logi-kpi">Total: <b><?= $total ?></b></div>
                    <div class="logi-kpi">Pendentes: <b><?= $pend ?></b></div>
                    <div class="logi-kpi">Aprovadas: <b><?= $ap ?></b></div>
                    <div class="logi-kpi">Negadas: <b><?= $ng ?></b></div>
                </div>
                <div class="logi-alert success" style="background:#f8fafc;border-color:#e5e7eb;color:#334155;">
                    Resumo: Valor <?= htmlspecialchars(money((float)($rel['total_valor_requisicoes'] ?? 0))) ?> |
                    Stock baixo <?= (int)($rel['pecas_stock_baixo'] ?? 0) ?> |
                    Fornecedores ativos <?= (int)($rel['fornecedores_ativos'] ?? 0) ?>
                </div>
            <?php endif; ?>

            <?php if($erro): ?><div class="logi-alert error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if($msg): ?><div class="logi-alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

            <?php if($view !== 'budjet'): ?>
                <div class="logi-inner-nav">
                    <form method="GET" class="logi-filters fixed-position">
                        <input type="hidden" name="view" value="<?= htmlspecialchars((string)$view) ?>">
                        <input type="hidden" name="mode" value="<?= htmlspecialchars((string)$mode) ?>">
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= in_array($view,['requisicoes','pedidos_oficina'],true) ? 'Pesquisar por codigo, item ou responsavel' : 'Pesquisar' ?>">
                    <?php if(in_array($view,['requisicoes','pedidos_oficina'],true)): ?>
                        <select name="status">
                                <option value="todos">Status: Todos</option>
                                <option value="pendente" <?= $status_filtro==='pendente'?'selected':'' ?>>Status: Pendente</option>
                                <option value="aprovada" <?= $status_filtro==='aprovada'?'selected':'' ?>>Status: Aprovada</option>
                                <option value="negada" <?= $status_filtro==='negada'?'selected':'' ?>>Status: Negada</option>
                                <option value="em transito" <?= $status_filtro==='em transito'?'selected':'' ?>>Status: Em transito</option>
                                <option value="entregue" <?= $status_filtro==='entregue'?'selected':'' ?>>Status: Entregue</option>
                        </select>
                    <?php endif; ?>
                    <?php if($view==='requisicoes'): ?>
                        <select name="departamento">
                            <option value="todos">Departamento: Todos</option>
                            <option value="oficina" <?= $departamento_filtro==='oficina'?'selected':'' ?>>Departamento: Oficina</option>
                            <option value="transporte" <?= $departamento_filtro==='transporte'?'selected':'' ?>>Departamento: Transporte</option>
                        </select>
                    <?php endif; ?>
                    <button class="btn-mode">Aplicar</button>
                        <a class="btn-mode" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=<?= urlencode((string)$view) ?>&mode=<?= urlencode((string)$mode) ?>">Limpar</a>
                    </form>
                </div>
            <?php endif; ?>

            <?php if(in_array($view,['fornecedores','pecas','cotacoes','substituicoes','facturas','pagamentos','pecas_avariadas','oper_uniforme','oper_alimentacao','oper_portagem','oper_multas','oper_seguros','oper_taxas_radios','oper_extintores','oper_manutencoes'],true)): ?>
                <div class="mode-selector">
                    <a class="btn-mode <?= $mode==='list' ? 'active' : '' ?>" href="?view=<?= urlencode((string)$view) ?>&mode=list">Lista</a>
                    <a class="btn-mode <?= $mode==='form' ? 'active' : '' ?>" href="?view=<?= urlencode((string)$view) ?>&mode=form">Novo Registo</a>
                </div>
            <?php endif; ?>

            <?php if($view==='requisicoes' && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_requisicao">
                    <input name="origem" placeholder="Origem" required>
                    <input name="destino" placeholder="Destino" required>
                    <input name="item" placeholder="Item" required>
                    <input type="number" step="0.01" min="0.01" name="quantidade" placeholder="Qtd" required>
                    <input name="unidade" value="un">
                    <input type="number" step="0.01" min="0" name="valor_total" value="0" placeholder="Valor">
                    <select name="escopo_logistica"><option value="operacional">Operacional</option><option value="geral">Geral</option></select>
                    <select name="area_solicitante"><option value="oficina">Oficina</option><option value="transporte">Transporte</option><option value="geral">Geral</option></select>
                    <button>Guardar</button>
                </form>
            <?php elseif($view==='requisicoes'): ?>
                <div class="logi-table-wrap">
                    <table class="logi-table">
                        <tr><th>Codigo</th><th>Departamento</th><th>Item</th><th>Origem -> Destino</th><th>Qtd</th><th>Valor</th><th>Status</th><th>Proxima acao</th></tr>
                        <?php if(!$requisicoes): ?>
                            <tr><td colspan="8">Sem requisicoes encontradas para os filtros selecionados.</td></tr>
                        <?php endif; ?>
                        <?php foreach($requisicoes as $r): $sn=st((string)$r['status']); ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$r['codigo']) ?></td>
                                <td>
                                    <?php $depReq = strtolower((string)($r['area_solicitante'] ?? 'oficina')); ?>
                                    <span class="logi-status <?= $depReq==='transporte' ? 'info' : 'warn' ?>">
                                        <?= htmlspecialchars(ucfirst($depReq)) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string)$r['item']) ?></td>
                                <td><?= htmlspecialchars((string)$r['origem']) ?> -> <?= htmlspecialchars((string)$r['destino']) ?></td>
                                <td><?= htmlspecialchars((string)$r['quantidade']) ?></td>
                                <td>
                                    <?= htmlspecialchars(money((float)($r['valor_total_calc'] ?? $r['valor_total'] ?? 0))) ?>
                                    <?php if(!empty($r['referencia_cotacao'])): ?>
                                        <div style="font-size:11px;color:#64748b;">Ref: <?= htmlspecialchars((string)$r['referencia_cotacao']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="logi-status <?= badge((string)$r['status']) ?>"><?= htmlspecialchars(stLabel((string)$r['status'])) ?></span></td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <?php if($sn==='pendente'): ?>
                                            <form method="POST" class="logi-inline-form">
                                                <input type="hidden" name="acao" value="anexar_preco">
                                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                <input type="hidden" name="ret_view" value="requisicoes">
                                                <input type="hidden" name="escopo_logistica" value="<?= htmlspecialchars((string)$r['escopo_logistica']) ?>">
                                                <input type="number" step="0.01" min="0.01" name="valor_total" placeholder="Preco (MZN)" style="max-width:120px;" required>
                                                <input type="text" name="referencia_cotacao" placeholder="Referencia" style="max-width:120px;">
                                                <input type="text" name="nota_preco" placeholder="Obs." style="max-width:120px;">
                                                <button>Guardar preco</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="logi-inline-form">
                                            <input type="hidden" name="acao" value="mudar_status">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="escopo_logistica" value="<?= htmlspecialchars((string)$r['escopo_logistica']) ?>">
                                            <input type="hidden" name="area_solicitante" value="<?= htmlspecialchars((string)$r['area_solicitante']) ?>">
                                            <?php if($sn==='pendente'): ?>
                                                <select name="novo_status" required>
                                                    <option value="aprovada">Aprovar</option>
                                                    <option value="negada">Negar</option>
                                                </select>
                                                <button>Atualizar</button>
                                            <?php elseif($sn==='aprovada'): ?>
                                                <input type="hidden" name="novo_status" value="em transito">
                                                <button>Despachar</button>
                                            <?php elseif($sn==='em transito'): ?>
                                                <input type="hidden" name="novo_status" value="entregue">
                                                <button>Marcar entregue</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

            <?php elseif($view==='pedidos_oficina'): ?>
                <div class="logi-alert success" style="background:#f8fafc;border-color:#e5e7eb;color:#334155;">
                    Fluxo simples: informe valor, escolha pagamento e clique em <b>Confirmar compra</b>.
                </div>
                <div class="logi-table-wrap">
                    <table class="logi-table">
                        <tr><th>Codigo</th><th>Item</th><th>Quantidade</th><th>Status</th><th>Responsavel</th><th>Compra</th></tr>
                        <?php if(!$pedidosOficina): ?><tr><td colspan="6">Sem pedidos recebidos da Oficina.</td></tr><?php endif; ?>
                        <?php foreach($pedidosOficina as $p): $sp = st((string)($p['status'] ?? '')); ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($p['codigo'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)($p['item'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string)(($p['quantidade'] ?? '0') . ' ' . ($p['unidade'] ?? ''))) ?></td>
                                <td><span class="logi-status <?= badge((string)($p['status'] ?? '')) ?>"><?= htmlspecialchars(stLabel((string)($p['status'] ?? ''))) ?></span></td>
                                <td><?= htmlspecialchars((string)($p['responsavel'] ?? '-')) ?></td>
                                <td>
                                    <?php if($sp==='pendente'): ?>
                                        <form method="POST" class="logi-inline-form" style="min-width:460px;">
                                            <input type="hidden" name="acao" value="processar_compra_requisicao">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <input type="number" step="0.01" min="0.01" name="valor_total" value="<?= (float)($p['valor_total'] ?? 0) > 0 ? htmlspecialchars(number_format((float)$p['valor_total'], 2, '.', '')) : '' ?>" placeholder="Valor (MZN)" required>
                                            <input type="text" name="referencia_cotacao" value="<?= htmlspecialchars((string)($p['referencia_cotacao'] ?? '')) ?>" placeholder="Ref cotacao (opcional)">
                                            <select name="fornecedor_id">
                                                <option value="">Fornecedor (opcional)</option>
                                                <?php foreach($fornRef as $f): ?>
                                                    <option value="<?= (int)$f['id'] ?>" <?= (int)($p['fornecedor_id'] ?? 0) === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$f['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="forma_pagamento" required>
                                                <option value="numerario">Numerario</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="credito">Credito</option>
                                            </select>
                                            <select name="tipo_compra" required>
                                                <option value="normal">Compra normal</option>
                                                <option value="compra_direta">Compra direta (gera cotacao)</option>
                                            </select>
                                            <button>Confirmar compra</button>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size:12px;color:#334155;">
                                            Valor: <?= htmlspecialchars(money((float)($p['valor_total'] ?? 0))) ?><br>
                                            Forma: <?= htmlspecialchars((string)($p['forma_pagamento'] ?? '-')) ?><br>
                                            Tipo: <?= htmlspecialchars((string)($p['tipo_compra'] ?? '-')) ?><br>
                                            Fornecedor: <?= htmlspecialchars((string)($p['fornecedor_nome'] ?? '-')) ?>
                                            <?php if(!empty($p['cotacao_automatica']) && !empty($p['cotacao_id'])): ?>
                                                <br>Cotacao auto: COT-<?= (int)$p['cotacao_id'] ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

            <?php elseif($view==='extratos'): ?>
                <div class="logi-table-wrap">
                    <table class="logi-table">
                        <tr><th>Indicador Financeiro</th><th>Valor</th></tr>
                        <tr><td>Total de facturas</td><td><?= (int)($rel['total_facturas'] ?? 0) ?></td></tr>
                        <tr><td>Total facturado</td><td><?= htmlspecialchars(money((float)($rel['total_valor_facturas'] ?? 0))) ?></td></tr>
                        <tr><td>Total de pagamentos</td><td><?= (int)($rel['total_pagamentos'] ?? 0) ?></td></tr>
                        <tr><td>Total pago</td><td><?= htmlspecialchars(money((float)($rel['total_valor_pagamentos'] ?? 0))) ?></td></tr>
                        <tr><td>Saldo a pagar</td><td><strong><?= htmlspecialchars(money((float)($rel['saldo_a_pagar'] ?? 0))) ?></strong></td></tr>
                    </table>
                </div>

            <?php elseif($view==='facturas' && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_factura">
                    <select name="fornecedor_id"><option value="">Fornecedor (opcional)</option><?php foreach($fornRef as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option><?php endforeach; ?></select>
                    <select name="departamento" required><option value="oficina">Oficina</option><option value="transporte">Transporte</option></select>
                    <input name="descricao" placeholder="Descricao da factura" required>
                    <input type="number" name="valor_total" min="0.01" step="0.01" placeholder="Valor total" required>
                    <input type="date" name="data_factura" value="<?= date('Y-m-d') ?>" required>
                    <select name="status"><option>Pendente</option><option>Parcial</option><option>Pago</option></select>
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar factura</button>
                </form>
            <?php elseif($view==='facturas'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Departamento</th><th>Fornecedor</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Status</th></tr><?php if(!$facturas): ?><tr><td colspan="7">Sem facturas registadas.</td></tr><?php endif; ?><?php foreach($facturas as $f): ?><tr><td><?= htmlspecialchars((string)($f['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars(ucfirst((string)($f['departamento'] ?? 'oficina'))) ?></td><td><?= htmlspecialchars((string)($f['fornecedor_nome'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($f['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($f['valor_total'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($f['data_factura'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($f['status'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='pagamentos' && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_pagamento">
                    <select name="factura_id"><option value="">Factura (opcional)</option><?php foreach($facturas as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)(($f['codigo'] ?? 'FAT') . ' - ' . ($f['descricao'] ?? ''))) ?></option><?php endforeach; ?></select>
                    <input name="descricao" placeholder="Descricao do pagamento" required>
                    <input type="number" name="valor_pago" min="0.01" step="0.01" placeholder="Valor pago" required>
                    <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
                    <select name="metodo"><option>Transferencia</option><option>Cheque</option><option>Numerario</option><option>Carteira movel</option></select>
                    <input name="observacoes" placeholder="Observacoes">
                    <button>Guardar pagamento</button>
                </form>
            <?php elseif($view==='pagamentos'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Codigo</th><th>Factura</th><th>Descricao</th><th>Valor pago</th><th>Data</th><th>Metodo</th></tr><?php if(!$pagamentos): ?><tr><td colspan="6">Sem pagamentos registados.</td></tr><?php endif; ?><?php foreach($pagamentos as $p): ?><tr><td><?= htmlspecialchars((string)($p['codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['factura_codigo'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($p['valor_pago'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($p['data_pagamento'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($p['metodo'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='fornecedores' && $mode==='form'): ?>
                <form method="POST" class="logi-form">
                    <input type="hidden" name="acao" value="criar_fornecedor">
                    <input name="nome" placeholder="Nome" required>
                    <input name="contacto" placeholder="Contacto">
                    <input name="telefone" placeholder="Telefone">
                    <input name="email" placeholder="Email">
                    <select name="escopo_logistica"><option value="operacional">Operacional</option><option value="geral">Geral</option></select>
                    <input type="number" name="saldo_budjet" min="0" step="0.01" value="0" placeholder="Saldo budjet">
                    <button>Guardar</button>
                </form>
            <?php elseif($view==='fornecedores'): ?>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Nome</th><th>Contacto</th><th>Escopo</th><th>Status</th><th>Saldo budjet</th></tr><?php if(!$fornecedores): ?><tr><td colspan="6">Sem fornecedores registados.</td></tr><?php endif; ?><?php foreach($fornecedores as $f): ?><tr><td><?= (int)$f['id'] ?></td><td><?= htmlspecialchars((string)$f['nome']) ?></td><td><?= htmlspecialchars((string)($f['contacto']??'-')) ?></td><td><?= htmlspecialchars((string)$f['escopo_logistica']) ?></td><td><span class="logi-status <?= badge((string)$f['status']) ?>"><?= htmlspecialchars((string)$f['status']) ?></span></td><td><?= htmlspecialchars(money((float)($f['saldo_budjet'] ?? 0))) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='pecas' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="criar_peca"><input name="codigo" placeholder="Codigo"><input name="nome" placeholder="Nome" required><input type="number" name="stock_atual" step="0.01" min="0" value="0" placeholder="Stock"><input type="number" name="stock_minimo" step="0.01" min="0" value="0" placeholder="Min"><input type="number" name="preco_referencia" step="0.01" min="0" value="0" placeholder="Preco"><button>Guardar</button></form>
            <?php elseif($view==='pecas'): ?>
                <form method="POST" class="logi-inline-form"><input type="hidden" name="acao" value="ajustar_stock"><select name="peca_id"><?php foreach($pecasRef as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)($p['codigo'].' - '.$p['nome'])) ?></option><?php endforeach; ?></select><select name="tipo_movimento"><option>Entrada</option><option>Saida</option><option>Ajuste</option></select><input type="number" name="quantidade" step="0.01" min="0.01" required><button>Movimentar</button></form>
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>ID</th><th>Codigo</th><th>Nome</th><th>Stock</th><th>Minimo</th><th>Preco</th></tr><?php if(!$pecas): ?><tr><td colspan="6">Sem pecas em stock.</td></tr><?php endif; ?><?php foreach($pecas as $p): ?><tr><td><?= (int)$p['id'] ?></td><td><?= htmlspecialchars((string)$p['codigo']) ?></td><td><?= htmlspecialchars((string)$p['nome']) ?></td><td><?= htmlspecialchars((string)$p['stock_atual']) ?></td><td><?= htmlspecialchars((string)$p['stock_minimo']) ?></td><td><?= htmlspecialchars(money((float)$p['preco_referencia'])) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='cotacoes' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="criar_cotacao"><select name="fornecedor_id"><?php foreach($fornRef as $f): ?><option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['nome']) ?></option><?php endforeach; ?></select><input name="item_nome" placeholder="Item" required><input type="number" name="preco_unitario" step="0.01" min="0.01" required><button>Guardar</button></form>
            <?php elseif($view==='cotacoes'): ?>
                <h4>Comparacao de precos</h4><div class="logi-table-wrap"><table class="logi-table"><tr><th>Item</th><th>Melhor</th><th>Maior</th><th>Medio</th><th>Cotacoes</th></tr><?php if(!$comp): ?><tr><td colspan="5">Sem cotacoes para comparar.</td></tr><?php endif; ?><?php foreach($comp as $c): ?><tr><td><?= htmlspecialchars((string)$c['item_nome']) ?></td><td><?= htmlspecialchars(money((float)$c['melhor_preco'])) ?></td><td><?= htmlspecialchars(money((float)$c['maior_preco'])) ?></td><td><?= htmlspecialchars(money((float)$c['preco_medio'])) ?></td><td><?= (int)$c['total_cotacoes'] ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='substituicoes' && $mode==='form'): ?>
                <form method="POST" class="logi-form"><input type="hidden" name="acao" value="registar_substituicao"><select name="peca_id"><?php foreach($pecasRef as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)($p['codigo'].' - '.$p['nome'])) ?></option><?php endforeach; ?></select><input name="matricula_ativo" placeholder="Matricula" required><input type="number" name="quantidade" step="0.01" min="0.01" value="1" required><input type="number" name="custo_unitario" step="0.01" min="0" value="0"><button>Guardar</button></form>
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
                    <input name="descricao" placeholder="Descricao do custo" required>
                    <input type="number" name="valor" min="0.01" step="0.01" placeholder="Valor" required>
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
                <div class="logi-table-wrap"><table class="logi-table"><tr><th>Departamento</th><th>Descricao</th><th>Valor</th><th>Data</th><th>Responsavel</th><th>Observacoes</th></tr><?php if(!$custosFiltrados): ?><tr><td colspan="6">Sem lancamentos nesta categoria.</td></tr><?php endif; ?><?php foreach($custosFiltrados as $c): ?><tr><td><?= htmlspecialchars(ucfirst((string)($c['departamento'] ?? 'transporte'))) ?></td><td><?= htmlspecialchars((string)($c['descricao'] ?? '-')) ?></td><td><?= htmlspecialchars(money((float)($c['valor'] ?? 0))) ?></td><td><?= htmlspecialchars((string)($c['data_lancamento'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['responsavel'] ?? '-')) ?></td><td><?= htmlspecialchars((string)($c['observacoes'] ?? '-')) ?></td></tr><?php endforeach; ?></table></div>

            <?php elseif($view==='budjet'): ?>
                <?php if($budjetDepartamentoSelecionado === ''): ?>
                    <div class="budjet-grid">
                        <?php foreach(['oficina'=>'fa-screwdriver-wrench','transporte'=>'fa-truck'] as $dep => $icon): $b = $budjetResumo[$dep] ?? ['saldo_atual'=>0,'orcamento_total'=>0,'total_debitos'=>0]; ?>
                            <div class="budjet-card">
                                <div class="budjet-icon"><i class="fa-solid <?= htmlspecialchars($icon) ?>"></i></div>
                                <h3><?= ucfirst($dep) ?></h3>
                                <div class="budjet-meta">Budjet do departamento</div>
                                <div class="budjet-value"><?= htmlspecialchars(money((float)($b['saldo_atual'] ?? 0))) ?></div>
                                <div class="budjet-meta">
                                    Orcamento total: <?= htmlspecialchars(money((float)($b['orcamento_total'] ?? 0))) ?><br>
                                    Gasto acumulado: <?= htmlspecialchars(money((float)($b['total_debitos'] ?? 0))) ?>
                                </div>
                                <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=budjet&departamento=<?= urlencode($dep) ?>">Ver detalhes</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php $b = $budjetResumo[$budjetDepartamentoSelecionado] ?? ['saldo_atual'=>0,'orcamento_total'=>0,'total_debitos'=>0,'total_creditos'=>0]; ?>
                    <div class="budjet-detail-header">
                        <div>
                            <h3 style="margin:0; text-transform:capitalize;">Detalhes do Budjet - <?= htmlspecialchars($budjetDepartamentoSelecionado) ?></h3>
                            <span class="budjet-pill">Extrato financeiro do departamento</span>
                        </div>
                        <a class="logi-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;" href="?view=budjet">Voltar aos departamentos</a>
                    </div>

                    <div class="budjet-resumo">
                        <div class="budjet-box"><div class="k">Saldo atual</div><div class="v"><?= htmlspecialchars(money((float)($b['saldo_atual'] ?? 0))) ?></div></div>
                        <div class="budjet-box"><div class="k">Orcamento total</div><div class="v"><?= htmlspecialchars(money((float)($b['orcamento_total'] ?? 0))) ?></div></div>
                        <div class="budjet-box"><div class="k">Gasto acumulado</div><div class="v"><?= htmlspecialchars(money((float)($b['total_debitos'] ?? 0))) ?></div></div>
                    </div>

                    <div class="budjet-reforco">
                        <form method="POST" class="logi-inline-form">
                            <input type="hidden" name="acao" value="budjet_creditar">
                            <input type="hidden" name="departamento" value="<?= htmlspecialchars($budjetDepartamentoSelecionado) ?>">
                            <input type="number" name="valor" min="0.01" step="0.01" placeholder="Valor para reforco" required>
                            <input type="text" name="descricao" placeholder="Descricao (opcional)">
                            <button>Reforcar budjet</button>
                        </form>
                    </div>

                    <div class="logi-table-wrap">
                        <table class="logi-table">
                            <tr><th>Data</th><th>Tipo</th><th>Valor</th><th>Referencia</th><th>Descricao</th><th>Saldo apos</th></tr>
                            <?php if(!$budjetMovimentos): ?>
                                <tr><td colspan="6">Sem movimentos neste departamento.</td></tr>
                            <?php endif; ?>
                            <?php foreach($budjetMovimentos as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($m['created_at'] ?? '-')) ?></td>
                                    <td><span class="logi-status <?= strtolower((string)($m['tipo'] ?? ''))==='credito' ? 'ok' : 'info' ?>"><?= htmlspecialchars((string)($m['tipo'] ?? '-')) ?></span></td>
                                    <td><?= htmlspecialchars(money((float)($m['valor'] ?? 0))) ?></td>
                                    <td><?= htmlspecialchars((string)($m['referencia'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($m['descricao'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars(money((float)($m['saldo_apos'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
