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

## Fase 2 — Reconciliação de branches ✅ concluída 2026-07-07

Duas branches partem de `master` (`27b80c9`): a chore (extração do migrator) e a `worktree-agente-gerente` (agentes + skills, PR #1). `.claude/` está untracked na primeira e commitado na segunda.

- [x] 🔐 **2.1** Merge `chore/reformat-and-standalone-migrator` → `master` (via PR recomendado) ✅ 2026-07-07
  - Renomeação dos agentes/plano commitada em `6ecf65f`; PR #2 mergeado em `master` @ `371d90c` com a extração do migrator e o gate da Fase 1 verde.
- [x] **2.2** Rebase/merge da branch `worktree-agente-gerente` sobre o novo master e atualização do PR #1 ✅ 2026-07-07
  - PR #1 fechado como superado pelo conteúdo já incorporado via `6ecf65f`/PR #2; `.claude/agents/` e `.claude/skills/` preservados.
- [x] **2.3** Após merges: remover cópias untracked duplicadas de `.claude/` no checkout (passam a ser versionadas) ✅ 2026-07-07
  - Sem duplicidade remanescente após o merge; `.claude/` versionado a partir de `371d90c`.

## Fase 3 — Documentação e versão (docs stale = risco de release enganoso) ✅ concluída 2026-07-07

- [x] **3.1** Nova entrada no `CHANGELOG.md` documentando a **extração** da migração embutida para o plugin standalone (a entrada 3.16.2 descreve UI de migração que não existe mais na raiz) ✅ 2026-07-07
  - Agente: **documentador** (skills `version-sync-check`, `readme-txt-format`).
- [x] **3.2** Decidir e aplicar bump de versão da raiz (3.16.3 ou 3.17.0 — a remoção de feature sugere minor: **3.17.0**) ✅ 2026-07-07
  - Agente: **documentador** · Sincronizado: header `Version:` + `QTX_VERSION` em `qtranslate.php` → `3.17.0`; `CHANGELOG.md`; `## Changelog` do `readme.txt` (referencia CHANGELOG.md, sem entrada duplicada; Stable tag permanece `N/A`).
- [x] **3.3** Corrigir `README.md` linhas ~20–25: remover "Polylang Export"/"Migration Scripts" como features da raiz; apontar para `qtx-polylang-migrator/` ✅ 2026-07-07
  - Agente: **documentador**.
- [x] **3.4** Corrigir `readme.txt`: "Requires at least: 5.0" incongruente com "Requires PHP: 8.4" ✅ 2026-07-07
  - Agente: **documentador** · Ajustado para `Requires at least: 6.0` em `readme.txt` e `README.md`, coerente com `Tested up to: 7.0`/`Requires PHP: 8.4`.
- [x] **3.5** Corrigir inconsistência interna de `docs/IMPLEMENTACAO-QTX-POLYLANG-MIGRATOR.md` (linha ~92 lista wrapper legado como ativo; linha ~132 diz que foi removido) ✅ 2026-07-07
  - Agente: **documentador** · Removida a linha da lista de capacidades que citava a manutenção do wrapper legado.
- [x] **3.6** Unificar idioma do `README.md` (seções em PT dentro de doc EN, linhas ~57–65) ✅ 2026-07-07
  - Agente: **documentador** · Seções "Implementação Técnica"/"Resumo do estado atual"/"Empacotamento" traduzidas para inglês; label de menu "Ferramentas" → "Tools".

## Fase 4 — Limpeza de código

- [x] **4.1**/**4.2** Scripts órfãos `diagnose_languages.php` e `rebuild_hierarchy.php` ✅ resolvidos externamente (removidos do disco pelo usuário; nunca foram commitados) — verificado em 2026-07-07.
- [x] **4.3** Completar type hints (parâmetros + retorno) nas 11+ funções sem tipos do migrator ✅ 2026-07-07
  - 18+ funções tipadas em 8 arquivos (commit `22493ff`); PHPStan `[OK] No errors`; re-revisão do **revisor-codigo** sem achados e sem risco de `TypeError` (uniões `array|false` p/ transients, `DOMNode` p/ clones).

## Fase 5 — Testes do migrator

- [x] **5.1** Substituir o teste unitário tautológico `tests/QTX_Polylang_Migration_Test.php` por testes reais das funções puras (`qtxpm_split_multilingual_text`, `qtxpm_normalize_language_code`, `qtxpm_get_language_value`, `qtxpm_sort_items_by_hierarchy`) ✅ 2026-07-07 (36 testes reais, cobrindo os 14 casos canônicos; verificação por mutação manual confirmada)
  - Agente: **testador** (skills `run-tests`, `multilingual-test-fixtures`) · Pronto quando: testes carregam código real do migrator e falham se a lógica for quebrada de propósito (verificação por mutação manual).
- [x] **5.2** Criar fixtures WXR versionadas em `tests/fixtures/` — **anonimizadas** a partir do export real em `qtx-polylang-migrator/tmp/` (dados da UENF: NÃO commitá-los crus) ✅ 2026-07-07 (`tests/fixtures/sample-multilingual-wxr.xml`, 100% fictício; consumido por `tests/integration/QTX_WXR_Fixture_Integration_Test.php`)
  - Agente: **testador** com apoio do **especialista-migracao** (skill `wxr-fixtures`).
- [x] **5.3** Cobertura mínima de `admin-actions.php`/`bootstrap.php` (nonce, capability, roteamento de steps, com stubs) ✅ 2026-07-07 (`tests/integration/QTX_Polylang_Migrator_Admin_Actions_Test.php`; stubs de `current_user_can`/`wp_die`/`wp_safe_redirect`/`add_management_page`/`wp_unslash` adicionados a `tests/wordpress_stubs.php`)
  - Agente: **testador**.

## Fase 6 — Segurança

- [x] **6.1** Revisão de segurança dedicada da camada admin do migrator ✅ 2026-07-07
  - 1ª rodada (**revisor-codigo**): 1 Alto (capability ausente em 3 handlers de `admin_init`), 3 Médios (nonce único, upload sem mime/tamanho, uninstall duplicado/incompleto), 2 Baixos; XXE/SQLi/path traversal/escaping verificados OK.
  - Correções (**especialista-migracao**, commit `1b2cdad`): capability + nonces por operação + `wp_check_filetype`/`QTXPM_MAX_UPLOAD_BYTES` (50MB filtrável) + uninstall consolidado + `sanitize_key` + restauração libxml + `LIBXML_NONET`.
  - Re-revisão: **APTO para merge** — todos corrigidos, sem regressões. Follow-up não bloqueante registrado (restaurar `libxml_use_internal_errors` também em `qtxpm_direct_xml_import`, `xml-import-service.php:29`).

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
- [ ] Follow-up da re-revisão de segurança (não bloqueante): restaurar o estado de `libxml_use_internal_errors` em `qtxpm_direct_xml_import` (`xml-import-service.php:29`), no padrão aplicado em `admin-actions.php` — **especialista-migracao**.

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
| Conflito `.claude/` entre branches | Fase 2.2–2.3 | ✅ resolvido 2026-07-07 (renomeação commitada em `6ecf65f`, PR #2 mergeado em `master` @ `371d90c`, PR #1 fechado como superado) |
| Vazamento de dados reais da UENF (WXR em tmp/) | Fase 5.2 (anonimizar) | ✅ mitigado 2026-07-07 (fixture 100% fictícia criada; tmp/ não commitado) |
| Release com docs enganosas | Fase 3 antes da 7 | ✅ mitigado 2026-07-07 (CHANGELOG/README/readme.txt/docs sincronizados; versão 3.17.0) |
| Falsa cobertura do teste unitário | Fase 5.1 | ✅ resolvido 2026-07-07 |
| Scripts órfãos inseguros commitados por engano | Fase 4.1–4.2 | ✅ resolvido (scripts removidos do disco) |
