# AGENTS.md

## Agentes de IA para qTranslate-XT Export

Este arquivo documenta os agentes de IA disponíveis para auxiliar no desenvolvimento do plugin qTranslate-XT Export.

---

## Agentes Disponíveis

| Agente | Função | Uso Principal |
|--------|--------|---------------|
| **backend-specialist** | Desenvolvimento PHP/WordPress | Código do plugin, funções PHP |
| **debugger** | Depuração e troubleshooting | Bugs, erros, problemas |
| **test-engineer** | Testes PHPUnit | Criar e executar testes |
| **qa-automation-engineer** | Automação de testes | Testes de integração |
| **security-auditor** | Auditoria de segurança | Segurança do plugin |
| **documentation-writer** | Documentação | README, docs, comentários |

---

## Skills Disponíveis

| Skill | Descrição | Quando Usar |
|-------|-----------|-------------|
| **lint-and-validate** | PHPStan + PHPCS | Antes de commit |
| **testing-patterns** | Padrões de teste PHPUnit | Ao criar testes |
| **i18n-localization** | Internacionalização | Strings multilíngues |
| **code-review-checklist** | Revisão de código | Pull requests |
| **database-design** | Design de banco | Estruturas de dados |
| **systematic-debugging** | Depuração sistemática | Bugs complexos |

---

## Comandos Rápidos

```bash
# Testes
./run-tests.sh test          # PHPUnit unitários
./run-tests.sh integration   # Testes de integração simplificados
./run-tests.sh stan          # PHPStan (análise estática)
./run-tests.sh phpcs         # PHPCS (code style)
./run-tests.sh all           # Todos os testes

# Ambiente
./run-tests.sh up            # Iniciar WordPress + MySQL
./run-tests.sh stop          # Parar containers
./run-tests.sh shell         # Abrir shell no container

# Docker
docker compose up -d db wordpress
docker compose down
docker compose logs -f
```

---

## Workflow de Desenvolvimento

### Antes de implementar:
1. Carregar skill `lint-and-validate`
2. Carregar skill `testing-patterns`

### Durante implementação:
1. Usar `backend-specialist` para código
2. Usar `test-engineer` para criar testes
3. Usar `qa-automation-engineer` para testes de integração

### Antes de commit:
1. Executar `./run-tests.sh all`
2. Executar `./run-tests.sh stan`
3. Executar `./run-tests.sh phpcs`

### Após implementação:
1. Atualizar documentação
2. Criar pull request
3. Code review com `code-review-checklist`

---

## Estrutura de Testes

```
tests/
├── bootstrap.php                 # Testes unitários
├── bootstrap-integration.php     # Testes de integração (simplificado)
├── wordpress_stubs.php           # Stubs do WordPress
├── acf_stubs.php                 # Stubs do ACF
├── woocommerce_stubs.php         # Stubs do WooCommerce
├── polylang_stubs.php            # Stubs do Polylang
├── integration/
│   ├── QTX_Integration_Test.php
│   └── QTX_Polylang_Migration_Integration_Test.php
├── QTX_Translator_Test.php
├── QTX_Language_Config_Test.php
└── QTX_Polylang_Migration_Test.php
```

---

## Variáveis de Ambiente

| Variável | Descrição | Valor Padrão |
|----------|-----------|--------------|
| `WORDPRESS_DB_HOST` | Host do MySQL | `db:3306` |
| `WORDPRESS_DB_USER` | Usuário do MySQL | `wordpress` |
| `WORDPRESS_DB_PASSWORD` | Senha do MySQL | `wordpress` |
| `WORDPRESS_DB_NAME` | Nome do banco | `wordpress` |

---

## Ferramentas de Análise

| Ferramenta | Configuração | Comando |
|------------|--------------|---------|
| **PHPStan** | `phpstan.neon.dist` | `./run-tests.sh stan` |
| **PHPCS** | `phpcs.xml.dist` | `./run-tests.sh phpcs` |
| **PHPUnit** | `phpunit.xml.dist` | `./run-tests.sh test` |
| **PHPUnit Integration** | `phpunit-integration.xml.dist` | `./run-tests.sh integration` |

---

## Credenciais do Ambiente de Teste

| Serviço | URL | Usuário | Senha |
|---------|-----|---------|-------|
| **WordPress** | http://localhost:8088 | admin | admin |
| **MySQL** | db:3306 | wordpress | wordpress |

---

## Links Úteis

- [Repositório GitHub](https://github.com/qtranslate/qtranslate-xt)
- [Issues](https://github.com/qtranslate/qtranslate-xt/issues)
- [Wiki](https://github.com/qtranslate/qtranslate-xt/wiki)
- [Documentação Polylang](https://polylang.pro/doc/)

---

## Notas

- Testes de integração usam stubs/mocks (não dependem do WordPress Test Suite completo)
- PHPStan level: 2 (moderado)
- PHPCS usa padrões WordPress
- PHP versão: 8.4
- MySQL versão: 8.0
