# Plano de Implementação — qTranslate-XT (Exportação) + qTx Polylang Migrator

> Gerado a partir da avaliação do agente **gerente** em 2026-07-06.
> Marque os itens conforme concluídos. Cada tarefa indica o **agente responsável** e o **critério de pronto**.
> Itens marcados com 🔐 exigem aprovação explícita do usuário antes de executar (ações irreversíveis/externas).

---

## Fase 0 — Salvaguarda do trabalho (URGENTE, hoje)

O risco mais crítico do projeto: os 7 commits da extração do migrator existem apenas no disco local.

- [ ] 🔐 **0.1** Push da branch `chore/reformat-and-standalone-migrator` para o origin
  - Agente: **gerente** · Comando: `git push -u origin chore/reformat-and-standalone-migrator`
  - Pronto quando: branch visível no GitHub com os 7 commits (`f9f823d` → `f4f01db`).
- [ ] **0.2** Conferir que nada relevante ficou untracked além dos scripts órfãos conhecidos
  - Agente: **gerente** · `git status` limpo exceto `.claude/`, `.agent/`, `src/admin/diagnose_languages.php`, `src/admin/rebuild_hierarchy.php`.

## Fase 1 — Gate de qualidade da branch chore

Validar o estado real antes de qualquer merge. Não há evidência registrada de que as suítes passaram.

- [ ] **1.1** Rodar `./run-tests.sh all` na branch chore e registrar a saída real
  - Agente: **gerente** (skill `run-tests`) · Pronto quando: 4 suítes verdes (PHPUnit unit + integration + PHPStan + PHPCS) com saída anotada neste documento ou no PR.
- [ ] **1.2** Se houver falhas: delegar correção
  - Testes quebrados → **testador** · Erros de stan/phpcs → **especialista-migracao** com revisão do **revisor-codigo**.

## Fase 2 — Reconciliação de branches

Duas branches partem de `master` (`27b80c9`): a chore (extração do migrator) e a `worktree-agente-gerente` (agentes + skills, PR #1). `.claude/` está untracked na primeira e commitado na segunda.

- [ ] 🔐 **2.1** Merge `chore/reformat-and-standalone-migrator` → `master` (via PR recomendado)
  - Agente: **gerente** · Pronto quando: master contém a extração do migrator e o gate da Fase 1 está verde.
- [ ] **2.2** Rebase/merge da branch `worktree-agente-gerente` sobre o novo master e atualização do PR #1
  - Agente: **gerente** · Atenção: preservar `.claude/agents/` e `.claude/skills/`; resolver conflito com os arquivos untracked locais.
- [ ] **2.3** Após merges: remover cópias untracked duplicadas de `.claude/` no checkout (passam a ser versionadas)
  - Agente: **gerente** · Pronto quando: `git status` sem duplicidade e `/reload-plugins` carrega agentes/skills da versão do repositório.

## Fase 3 — Documentação e versão (docs stale = risco de release enganoso)

- [ ] **3.1** Nova entrada no `CHANGELOG.md` documentando a **extração** da migração embutida para o plugin standalone (a entrada 3.16.2 descreve UI de migração que não existe mais na raiz)
  - Agente: **documentador** (skills `version-sync-check`, `readme-txt-format`).
- [ ] **3.2** Decidir e aplicar bump de versão da raiz (3.16.3 ou 3.17.0 — a remoção de feature sugere minor: **3.17.0**)
  - Agente: **documentador** · Sincronizar: header `Version:` + `QTX_VERSION` em `qtranslate.php`, `CHANGELOG.md`, `## Changelog` do `readme.txt` (Stable tag permanece `N/A`).
- [ ] **3.3** Corrigir `README.md` linhas ~20–25: remover "Polylang Export"/"Migration Scripts" como features da raiz; apontar para `qtx-polylang-migrator/`
  - Agente: **documentador**.
- [ ] **3.4** Corrigir `readme.txt`: "Requires at least: 5.0" incongruente com "Requires PHP: 8.4"
  - Agente: **documentador**.
- [ ] **3.5** Corrigir inconsistência interna de `docs/IMPLEMENTACAO-QTX-POLYLANG-MIGRATOR.md` (linha ~92 lista wrapper legado como ativo; linha ~132 diz que foi removido)
  - Agente: **documentador**.
- [ ] **3.6** Unificar idioma do `README.md` (seções em PT dentro de doc EN, linhas ~57–65)
  - Agente: **documentador**.

## Fase 4 — Limpeza de código

- [ ] **4.1** Avaliar os scripts órfãos `src/admin/diagnose_languages.php` e `src/admin/rebuild_hierarchy.php`: há lógica de valor não coberta pelo migrator standalone?
  - Agente: **especialista-migracao** · Pronto quando: parecer registrado (manter/portar/remover).
- [ ] 🔐 **4.2** Executar a decisão: remover os scripts (provável) ou portar a lógica para página admin segura no migrator
  - Agente: **especialista-migracao** · Motivo da urgência: fazem `require_once` direto de `wp-config.php`/`wp-load.php` (inseguro) e um `git add -A` descuidado os commitaria.
- [ ] **4.3** Completar type hints (parâmetros + retorno) nas 11+ funções sem tipos do migrator
  - Agente: **especialista-migracao** · Arquivos: `wxr-transformer.php`, `xml-import-service.php`, `hierarchy-service.php`, `duplicate-repair-service.php`, `admin-actions.php`, `admin-render.php`.
  - Pronto quando: PHPStan verde e revisão do **revisor-codigo** sem achados Crítico/Alto.

## Fase 5 — Testes do migrator

- [ ] **5.1** Substituir o teste unitário tautológico `tests/QTX_Polylang_Migration_Test.php` por testes reais das funções puras (`qtxpm_split_multilingual_text`, `qtxpm_normalize_language_code`, `qtxpm_get_language_value`, `qtxpm_sort_items_by_hierarchy`)
  - Agente: **testador** (skills `run-tests`, `multilingual-test-fixtures`) · Pronto quando: testes carregam código real do migrator e falham se a lógica for quebrada de propósito (verificação por mutação manual).
- [ ] **5.2** Criar fixtures WXR versionadas em `tests/fixtures/` — **anonimizadas** a partir do export real em `qtx-polylang-migrator/tmp/` (dados da UENF: NÃO commitá-los crus)
  - Agente: **testador** com apoio do **especialista-migracao** (skill `wxr-fixtures`).
- [ ] **5.3** Cobertura mínima de `admin-actions.php`/`bootstrap.php` (nonce, capability, roteamento de steps, com stubs)
  - Agente: **testador**.

## Fase 6 — Segurança

- [ ] **6.1** Revisão de segurança dedicada da camada admin do migrator: upload/parse de XML (XXE, limites de tamanho, validação de mime), escaping em `admin-render.php`
  - Agente: **revisor-codigo** (skill `run-quality-gate`) · Pronto quando: relatório por severidade emitido; achados Crítico/Alto corrigidos pelo **especialista-migracao** e re-revisados.

## Fase 7 — Release

- [ ] **7.1** Seguir a skill `release-process` de ponta a ponta (versões já sincronizadas na Fase 3)
  - Agente: **gerente**.
- [ ] **7.2** `./run-tests.sh all` final verde após todas as fases
  - Agente: **gerente**.
- [ ] **7.3** `./run-tests.sh package-migrator` e conferência do zip (`build/qtx-polylang-migrator-<versão>.zip`); avaliar bump do migrator (0.1.0 → 0.2.0 se as Fases 4–6 alterarem comportamento)
  - Agente: **gerente** + **documentador**.
- [ ] 🔐 **7.4** Tag anotada, push da tag e release no GitHub com o zip anexado
  - Agente: **gerente** · Somente com aprovação explícita.

## Pendência de longo prazo (sem fase)

- [ ] i18n do migrator para novos locales (hoje só `pt_BR`) — **especialista-migracao** + **documentador**.

---

## Ordem de execução e dependências

```
Fase 0 (hoje) → Fase 1 → Fase 2 → Fase 3 ─┬→ Fase 7
                              Fase 4 ──────┤
                              Fase 5 ──────┤   (4, 5 e 6 podem rodar em paralelo
                              Fase 6 ──────┘    após a Fase 2)
```

## Riscos monitorados

| Risco | Mitigação | Status |
|---|---|---|
| Perda dos 7 commits locais (crítico) | Fase 0.1 | ⏳ aberto |
| Conflito `.claude/` entre branches | Fase 2.2–2.3 | ⏳ aberto |
| Vazamento de dados reais da UENF (WXR em tmp/) | Fase 5.2 (anonimizar) | ⏳ aberto |
| Release com docs enganosas | Fase 3 antes da 7 | ⏳ aberto |
| Falsa cobertura do teste unitário | Fase 5.1 | ⏳ aberto |
| Scripts órfãos inseguros commitados por engano | Fase 4.1–4.2 | ⏳ aberto |
