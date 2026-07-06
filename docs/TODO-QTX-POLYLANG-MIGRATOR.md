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
