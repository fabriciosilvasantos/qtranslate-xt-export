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

Status: corrigido (2026-07-07)

Correção implementada:

- cada importação registra um contexto de execução (`run` + `source`) na opção
  `qtxpm_current_migration_run` e etiqueta os posts importados com
  `_pll_migration_run` e `_pll_migration_source`
- hierarquia, restauração de idiomas e conexão de traduções passam a ser
  escopadas à execução corrente
- a deduplicação passa a ser escopada ao site de origem corrente (duplicatas
  só fazem sentido dentro do mesmo site — IDs originais colidem entre sites)
- sem contexto registrado (migrações feitas antes da correção), todas as
  consultas mantêm o comportamento legado (sem escopo)
- validado por 8 novos testes de integração e por re-execução do cenário E2E
  que originou o bug (hierarquia correta, 3 pares conectados, migração
  anterior intacta)

Limitação conhecida: um force re-import sobre uma migração feita ANTES da
correção não deduplica contra os posts antigos (eles não têm
`_pll_migration_source`).

Contexto original (encontrado em teste E2E de 2026-07-07, WordPress 7.0):

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

### 7. Observação de UX: fluxo já é encadeado — RESOLVIDO (0.3.0)

O passo "Importar para WordPress" já executa a migração completa (hierarquia,
conexão e dedup) e redireciona para `step=results`. Os botões separados
"Executar Migracao Completa" sugerem etapas manuais que não existem mais no
fluxo real — simplificar a UI ou documentar.

**Resolução:** removido o card/passo "Passo 3: Finalizar Migração" (botão
"Executar Migracao Completa") de `admin-render.php`, já que nenhum link do
fluxo real navega para `step=finalize` — o passo "Importar para WordPress"
já dispara hierarquia + conexão automaticamente antes de redirecionar direto
para `step=results`. O card de progresso e o texto do Passo 2 agora deixam
explícito que a reconstrução de hierarquia e a conexão de traduções rodam
automaticamente nesse mesmo passo. O nonce action e o handler
`qtxpm_migration_action_finalize` / `qtxpm_finalize_migration()` em
`admin-actions.php` foram mantidos intactos (compatibilidade retroativa /
uso via POST direto), apenas documentados como não expostos por nenhum botão
da UI. O botão "Reparar Duplicatas Órfãs" na tela de resultados foi mantido
(uso legítimo de reexecução manual, ver item 6) e teve o rótulo/descrição
atualizados para deixar claro que é uma ação opcional.

### Registro de compatibilidade

- 2026-07-07: pipeline completo validado sob WordPress 7.0 (export de site
  qTranslate-XT 3.16.2 em WP 7.0 → migrator em WP 7.0 + Polylang 3.8.5).
  Em destino limpo o fluxo é íntegro; o bug do item 6 afeta apenas
  re-execuções em destino já migrado.
- 2026-07-08: smoke test end-to-end com WXR real (4,5 MB, site da
  Pós-Graduação em Sociologia Política/UENF) sob WordPress 7.0 + Polylang
  3.8.5. Pipeline completo (transformar → importar → hierarquia → conexões)
  migrou 1.660 itens em 94s com zero erros: 177 páginas com idioma
  (106 pt + 71 en), 71 pares de tradução conectados, hierarquia 179/179
  arestas pai→filho corretas contra o WXR original, 86/87 URLs idênticas às
  do site real (única diferença: a página inicial estática, que o WXR não
  transporta — ver item de pós-migração no `readme.txt` do plugin) e
  deduplicação 179 grupos analisados / 0 duplicatas. Confirma o item 7
  (o fluxo "Importar para WordPress" já executa a migração completa) e não
  afeta o bug do item 6, que só aparece em re-execuções sobre destino já
  migrado.
