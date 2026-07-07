# TODO da Nova Versão do Migrador qTranslate-XT → Polylang

## Objetivo

Este documento registra os próximos passos da extração do migrador para plugin standalone, diferenciando o que já foi concluído do que ainda precisa ser fechado para distribuição e operação mais isolada.

## Status Geral

- [x] Plugin standalone criado
- [x] Wrapper legado no qTranslate-XT implementado
- [x] Engine movido para o plugin novo
- [x] Engine modularizado em serviços menores
- [x] Documentação técnica da implementação criada
- [x] `readme.txt` próprio do plugin standalone
- [x] Estrutura de `languages/` com catálogos reais versionados
- [x] Smoke test do plugin standalone isolado
- [x] Exposição automática do plugin standalone como plugin top-level no ambiente Docker local
- [x] Revisão final do empacotamento para distribuição
- [x] Remoção da bridge legada
- [x] Remoção do wrapper legado no qTranslate-XT

## Próximos Pontos Implementados Neste Ciclo

### 1. Empacotamento básico do plugin standalone

Status: concluído

Entregas:

- arquivo `readme.txt` próprio em `qtx-polylang-migrator/`
- estrutura inicial de `languages/`
- documentação do plugin novo separada do README principal

### 2. Estrutura de internacionalização

Status: concluído

Entregas:

- diretório `qtx-polylang-migrator/languages/`
- arquivo de proteção `index.php`
- catálogo `qtx-polylang-migrator.pot`
- catálogo `qtx-polylang-migrator-pt_BR.po`
- catálogo compilado `qtx-polylang-migrator-pt_BR.mo`
- strings públicas do plugin standalone conectadas ao text domain `qtx-polylang-migrator`

### 3. Empacotamento distribuível do plugin standalone

Status: concluído

Entregas:

- script de empacotamento em `scripts/package-qtx-polylang-migrator.sh`
- comando `./run-tests.sh package-migrator`
- geração do ZIP distribuível em `build/qtx-polylang-migrator-<versão>.zip`
- atualização do `.gitignore` para ignorar artefatos de build

## Próximos Pontos Ainda Abertos

### 4. Smoke test do plugin standalone isolado

Status: concluído

Objetivo:

- validar o fluxo com apenas WordPress + Polylang + `qTranslate to Polylang Migrator`
- confirmar abertura da tela do migrador e ciclo básico de carga sem depender do qTranslate-XT

Resultado desta rodada:

- `Polylang` ativo
- `qTranslate-XT` inativo
- `qTranslate to Polylang Migrator` ativo
- renderização validada com sucesso na tela do migrador
- validação repetida com sucesso após recriação limpa do serviço WordPress

Observação de empacotamento:

- o ambiente Docker local agora expõe automaticamente `qtx-polylang-migrator` como plugin top-level em `wp-content/plugins/`
- o empacotamento distribuível do plugin standalone já foi implementado neste repositório

### 5. Encerramento da transição legada

Status: concluído

Entregas:

- bridge técnica legada removida
- testes ajustados para carregar diretamente o engine do plugin standalone
- wrapper legado em `src/admin/migration_wrapper.php` removido
- carregamento do admin do qTranslate-XT deixou de registrar a tela antiga da migração

### 6. Bug: contaminação de metadados entre execuções de migração

Status: aberto

Contexto (encontrado em teste E2E de 2026-07-07, WordPress 7.0):

Ao rodar uma segunda migração em um site de destino que já recebeu uma migração
anterior, os metadados `_pll_migration_original_id` / `_pll_migration_parent_id` /
`_pll_migration_lang` da execução antiga permanecem no banco e colidem com os da
nova execução (os IDs originais dos dois exports se sobrepõem — ex.: `original_id=5`
apontando para 4 posts de sites diferentes).

Sintomas observados:

- `hierarchy-service.php` mapeia `original_parent_id` para o post errado
  (página nova ficou filha de página da migração anterior)
- os pares de tradução da segunda migração não são conectados no Polylang
- combinado com o reparo de duplicatas, pares válidos novos podem ser
  rebaixados para `draft`

Direções de correção possíveis:

- etiquetar os metadados com um ID de execução (batch) e escopar todas as
  queries dos serviços à execução corrente
- e/ou limpar os metadados `_pll_migration_*` ao final de uma migração
  concluída com sucesso

### 7. Observação de UX: fluxo já é encadeado

O passo "Importar para WordPress" já executa a migração completa (hierarquia,
conexão e dedup) e redireciona para `step=results`. Os botões separados
"Executar Migracao Completa" sugerem etapas manuais que não existem mais no
fluxo real — simplificar a UI ou documentar.

### Registro de compatibilidade

- 2026-07-07: pipeline completo validado sob WordPress 7.0 (export de site
  qTranslate-XT 3.16.2 em WP 7.0 → migrator em WP 7.0 + Polylang 3.8.5).
  Em destino limpo o fluxo é íntegro; o bug do item 6 afeta apenas
  re-execuções em destino já migrado.
