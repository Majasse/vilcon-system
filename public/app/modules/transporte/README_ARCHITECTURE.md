# Arquitetura Limpa - Transporte

## Pastas

- `bootstrap/`
  - Inicializacao de sessao, auth e conexoes.

- `application/`
  - `context.php`: normaliza request (`tab`, `view`, `mode`).
  - `actions/`: handlers HTTP legados.
  - `usecases/`: casos de uso da aplicacao.

- `domain/services/`
  - Regras de negocio puras (reserva, distancia, schema, stock, checklist).

- `infrastructure/database/`
  - SQL, migracoes e repositorios PDO.

- `presentation/pages/`
  - `module.php`: layout da pagina.
  - `content/form.php`: tela de cadastro por view.
  - `content/list.php`: tela de listagem.

- `includes/`
  - Header, tabs, sidebar e footer compartilhados.

## Onde colocar o codigo grande que voce enviou

1. Funcoes de schema (`ensureColumnExists`, CREATE TABLE):
   - `domain/services/TransporteSchemaService.php`

2. Regras de urgencia, consumo, distancia, checklist, stock:
   - `domain/services/*.php`

3. Blocos `if($_SERVER['REQUEST_METHOD'] === 'POST')`:
   - `application/actions/` por contexto (`reservas.php`, `os.php`, `stock.php` etc.)

4. HTML grande por tela:
   - `presentation/pages/content/form_*.php` e `list_*.php`

## Status atual

A estrutura ja esta ativa e o modulo continua funcional com o layout existente.
