# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Visão geral

Este repositório contém **dois plugins WordPress** distintos:

1. **qTranslate-XT (Exportação)** — raiz do repositório (`qtranslate.php`, `src/`). Fork do qTranslate-X que armazena conteúdo multilíngue em um único post usando blocos de idioma inline no formato `[:pt]...[:en]...[:]`. Esta variante ("Exportação") adiciona funcionalidade de migração para o Polylang.
2. **qTranslate to Polylang Migrator** — plugin standalone em `qtx-polylang-migrator/`. Roda de forma **independente** do qTranslate-XT no site de destino (que usa Polylang) e converte exports WXR/XML do qTranslate para o formato Polylang. É empacotado e distribuído separadamente.

PHP mínimo: **8.4**. Todo código PHP usa type hints estritos e assume PHP 8.4+.

## Comandos

O desenvolvimento roda dentro de Docker (`docker-compose.yml`). Use o wrapper `run-tests.sh`:

```bash
./run-tests.sh test          # PHPUnit unitários (phpunit.xml.dist, exclui tests/integration/)
./run-tests.sh integration   # Testes de integração (phpunit-integration.xml.dist, usa stubs)
./run-tests.sh stan          # PHPStan (phpstan.neon.dist, level 2)
./run-tests.sh phpcs         # PHPCS (phpcs.xml.dist, padrões WordPress)
./run-tests.sh lint          # PHPStan + PHPCS
./run-tests.sh all           # test + integration + stan + phpcs

./run-tests.sh up            # Sobe WordPress (http://localhost:8088, admin/admin) + MySQL
./run-tests.sh stop          # Derruba os containers
./run-tests.sh shell         # Shell no container de testes

./run-tests.sh package-migrator   # Empacota o plugin standalone → build/qtx-polylang-migrator-<versão>.zip
```

Rodar um único teste (dentro do container, ex. via `./run-tests.sh shell`):

```bash
vendor/bin/phpunit --filter test_nome_do_metodo tests/QTX_Translator_Test.php
```

Build dos assets JS (fora do Docker):

```bash
npm run build     # webpack produção → dist/
npm run dev       # build de desenvolvimento
npm run watch     # rebuild em watch
```

**Antes de commit**, rode `./run-tests.sh all`. PHPStan e PHPCS fazem parte do gate de qualidade.

## Arquitetura do qTranslate-XT (raiz)

Ponto de entrada `qtranslate.php` → `src/init.php`. O fluxo de inicialização (`qtranxf_init_language`, engatado em `plugins_loaded` com prioridade 2) é o coração do plugin:

1. `qtranxf_load_config()` — carrega config em `global $q_config` (o estado global compartilhado por todo o plugin).
2. Detecta o idioma da requisição via `src/language_detect.php` (cookies, URL, query). O modo de URL (`pre-path`, `query`, `pre-domain`) determina como o idioma é resolvido.
3. **Fork front vs. admin**: carrega `src/frontend.php` **ou** `src/admin/admin.php`, nunca ambos.
4. Registra hooks comuns (`src/hooks.php`), REST API, widget, date/time.
5. Carrega módulos ativos via `QTX_Module_Loader::load_active_modules()`.

Peças centrais:

- **`src/class_translator.php`** (`QTX_Translator`) — singleton via `QTX_Translator::get_translator()`. Implementa `QTX_Translator_Interface` e registra os filtros `translate_text`/`translate_term`/`translate_url`. É a API pública para outros plugins traduzirem conteúdo.
- **`src/language_blocks.php`** — parsing e serialização do formato de bloco `[:pt]...[:en]...[:]`. A função `qtranxf_use()` extrai o texto do idioma corrente de uma string multilíngue.
- **`src/language_config.php`** / **`src/default_language_config.php`** — definição, habilitação e configuração de idiomas.
- **`src/modules/`** — integrações com plugins de terceiros (ACF, WooCommerce, Yoast/wp-seo, All-in-One SEO, Gravity Forms, Jetpack, Events Made Easy, Google Site Kit, slugs). Cada módulo tem um loader e frequentemente `admin.php`/`front.php`. Registrados via `QTX_Module_Loader`.
- **`src/admin/`** — toda a UI de administração: opções, configuração de idiomas, import/export, editor de blocos.

Convenções:
- Funções globais usam o prefixo **`qtranxf_`**.
- Estado global mora em `global $q_config` — a maior parte da lógica lê/escreve nele.
- Autoload PSR-4 `QTX\` → `src/classes/` está declarado no composer, mas a maioria do código atual usa `require_once` explícito e funções globais, não classes autoloadadas.

## Arquitetura do Migrator (`qtx-polylang-migrator/`)

Plugin standalone independente. Entrada: `qtx-polylang-migrator.php` → `admin/bootstrap.php`. Constantes com prefixo `QTXPM_`, funções com prefixo `qtxpm_`. Requer o Polylang ativo no destino (`Requires Plugins: polylang`).

Pipeline de migração (em `includes/`), orquestrado por `migration-engine.php`:

- **`xml-import-service.php`** / **`wxr-transformer.php`** — recebe o WXR exportado do qTranslate e transforma os blocos `[:xx]` em posts separados por idioma no formato Polylang.
- **`hierarchy-service.php`** — reconstrói relações pai-filho e `menu_order` das páginas.
- **`polylang-service.php`** — provisiona idiomas faltantes no Polylang e conecta as traduções entre si.
- **`duplicate-repair-service.php`** — rebaixa duplicatas órfãs para `draft`.
- **`admin-page.php`** / **`admin-render.php`** / **`admin-actions.php`** — UI em `Ferramentas > qTranslate Migrator` (`tools.php?page=qtx-polylang-migrator`).

Estado intermediário do fluxo é guardado em transients com prefixo `qtxpm_` (`staged_xml`, `import_report`, `migration_results`).

Docs de implementação e pendências: `docs/IMPLEMENTACAO-QTX-POLYLANG-MIGRATOR.md`, `docs/TODO-QTX-POLYLANG-MIGRATOR.md`.

## Testes

Os testes **não** dependem da WordPress Test Suite completa — usam stubs em `tests/` (`wordpress_stubs.php`, `acf_stubs.php`, `woocommerce_stubs.php`, `polylang_stubs.php`, `wp_class_stubs.php`). Dois bootstraps: `tests/bootstrap.php` (unitários) e `tests/bootstrap-integration.php` (integração). Testes de integração ficam em `tests/integration/` e são excluídos da suíte unitária.

## Documentação

Ao alterar comportamento/versão, mantenha sincronizados: `CHANGELOG.md`, `README.md`, `readme.txt` (formato WordPress.org) e a versão em `qtranslate.php` (constante `QTX_VERSION` + header do plugin). O agente `docs-changelog-maintainer` cobre essa sincronização.
