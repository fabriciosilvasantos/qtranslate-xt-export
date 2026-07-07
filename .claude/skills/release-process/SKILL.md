---
name: release-process
description: Roteiro completo de release do qTranslate-XT (Exportação) e do plugin qTx Polylang Migrator — versão, changelog, gate de qualidade, empacotamento. Use ao fechar uma versão.
---

# Processo de release

Siga na ordem. Não pule etapas nem as execute em paralelo.

## 1. Decidir escopo e versão

- `git log --oneline <última-tag-ou-versão>..HEAD` para listar o que entra.
- Versionamento: patch (correções), minor (features compatíveis), major (quebra).
- O plugin raiz (qTranslate-XT, `QTX_VERSION`) e o migrator (`qtx-polylang-migrator.php`) têm **versões independentes** — decida cada um pelo seu próprio diff.

## 2. Sincronizar versão e documentação

- Use a skill **version-sync-check** para os locais exatos.
- Atualize `CHANGELOG.md` (entrada `### X.Y.Z` com itens agrupados Added/Fixed/Changed) e a seção `## Changelog` do `readme.txt`.
- Delegue ao agente **documentador** quando estiver orquestrando.

## 3. Gate de qualidade (obrigatório)

```bash
./run-tests.sh all   # PHPUnit + integração + PHPStan + PHPCS, tudo no Docker
```

Só prossiga com o gate **verde de verdade** (saída real, não suposição). Falhou → corrigir → rodar de novo.

## 4. Empacotamento (se o migrator mudou)

```bash
./run-tests.sh package-migrator
# → build/qtx-polylang-migrator-<versão>.zip (versão lida do header do plugin)
```

Confira se o zip gerado tem a versão esperada no nome.

## 5. Commit, tag e release

- Commit único de release ("Release X.Y.Z") com versão + changelog + readme.
- Tag anotada `git tag -a vX.Y.Z` e push da tag — **somente com aprovação explícita do usuário**.
- Release no GitHub anexando o zip do migrator quando aplicável.

## Checklist final

- [ ] Versões idênticas em todos os locais (version-sync-check limpo)
- [ ] `CHANGELOG.md` e `readme.txt` com a nova entrada
- [ ] `./run-tests.sh all` verde (saída real reportada)
- [ ] Zip do migrator gerado e conferido (se aplicável)
- [ ] Nada de código de produção alterado depois do gate
