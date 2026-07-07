# Plano de Implementação — qTranslate-XT (Exportação) + qTx Polylang Migrator

> Gerado a partir da avaliação do agente **gerente** em 2026-07-06.
> Marque os itens conforme concluídos. Cada tarefa indica o **agente responsável** e o **critério de pronto**.
> Itens marcados com 🔐 exigem aprovação explícita do usuário antes de executar (ações irreversíveis/externas).

---

## Fase 0 — Salvaguarda do trabalho (URGENTE, hoje)

O risco mais crítico do projeto: os 7 commits da extração do migrator existem apenas no disco local.

- [x] 🔐 **0.1** Push da branch `chore/reformat-and-standalone-migrator` para o origin ✅ 2026-07-07
  - Branch já estava no origin (@ `f28e038`, 10 commits sobre master — 3 além dos avaliados: gitignore de config local, versionamento de `.claude/`, pin do WordPress 7.0 no Docker); tracking configurado.
- [x] **0.2** Conferir untracked ✅ 2026-07-07
  - Scripts órfãos `diagnose_languages.php`/`rebuild_hierarchy.php` **removidos do disco** (resolve 4.1/4.2). Pendência restante: a renomeação dos agentes (.claude) e este plano ainda não commitados na chore — tratar na Fase 2.

## Fase 1 — Gate de qualidade da branch chore

Validar o estado real antes de qualquer merge. Não há evidência registrada de que as suítes passaram.

- [x] **1.1** Rodar `./run-tests.sh all` na branch chore ✅ 2026-07-07, gate verde (branch @ `f28e038`, PHP 8.4.18, PHPUnit 9.6.34):
  - Unitário: `OK (20 tests, 45 assertions)` · Integração: `OK (43 tests, 128 assertions)` · PHPStan: `[OK] No errors` · PHPCS: 69 arquivos sem violações.
- [x] **1.2** Sem falhas — nenhuma correção necessária.

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

- [x] **4.1**/**4.2** Scripts órfãos `diagnose_languages.php` e `rebuild_hierarchy.php` ✅ resolvidos externamente (removidos do disco pelo usuário; nunca foram commitados) — verificado em 2026-07-07.
- [ ] **4.3** Completar type hints (parâmetros + retorno) nas 11+ funções sem tipos do migrator
  - Agente: **especialista-migracao** · Arquivos: `wxr-transformer.php`, `xml-import-service.php`, `hierarchy-service.php`, `duplicate-repair-service.php`, `admin-actions.php`, `admin-render.php`.
  - Pronto quando: PHPStan verde e revisão do **revisor-codigo** sem achados Crítico/Alto.

## Fase 5 — Testes do migrator

- [x] **5.1** Substituir o teste unitário tautológico `tests/QTX_Polylang_Migration_Test.php` por testes reais das funções puras (`qtxpm_split_multilingual_text`, `qtxpm_normalize_language_code`, `qtxpm_get_language_value`, `qtxpm_sort_items_by_hierarchy`) ✅ 2026-07-07 (36 testes reais, cobrindo os 14 casos canônicos; verificação por mutação manual confirmada)
  - Agente: **testador** (skills `run-tests`, `multilingual-test-fixtures`) · Pronto quando: testes carregam código real do migrator e falham se a lógica for quebrada de propósito (verificação por mutação manual).
- [x] **5.2** Criar fixtures WXR versionadas em `tests/fixtures/` — **anonimizadas** a partir do export real em `qtx-polylang-migrator/tmp/` (dados da UENF: NÃO commitá-los crus) ✅ 2026-07-07 (`tests/fixtures/sample-multilingual-wxr.xml`, 100% fictício; consumido por `tests/integration/QTX_WXR_Fixture_Integration_Test.php`)
  - Agente: **testador** com apoio do **especialista-migracao** (skill `wxr-fixtures`).
- [x] **5.3** Cobertura mínima de `admin-actions.php`/`bootstrap.php` (nonce, capability, roteamento de steps, com stubs) ✅ 2026-07-07 (`tests/integration/QTX_Polylang_Migrator_Admin_Actions_Test.php`; stubs de `current_user_can`/`wp_die`/`wp_safe_redirect`/`add_management_page`/`wp_unslash` adicionados a `tests/wordpress_stubs.php`)
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
| Perda dos commits locais (crítico) | Fase 0.1 | ✅ mitigado 2026-07-07 (branch no origin) |
| Conflito `.claude/` entre branches | Fase 2.2–2.3 | ⏳ aberto — `.claude/` agora versionado na chore (`de36fc1`) com nomes antigos; renomeação pendente de commit |
| Vazamento de dados reais da UENF (WXR em tmp/) | Fase 5.2 (anonimizar) | ✅ mitigado 2026-07-07 (fixture 100% fictícia criada; tmp/ não commitado) |
| Release com docs enganosas | Fase 3 antes da 7 | ⏳ aberto |
| Falsa cobertura do teste unitário | Fase 5.1 | ✅ resolvido 2026-07-07 |
| Scripts órfãos inseguros commitados por engano | Fase 4.1–4.2 | ✅ resolvido (scripts removidos do disco) |
