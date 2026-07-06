---
name: version-sync-check
description: Verifica a consistência de versão do qTranslate-XT entre qtranslate.php, readme.txt e CHANGELOG.md, e a versão própria do plugin migrator. Use em bumps de versão e antes de releases.
---

# Checagem de sincronia de versão

## Onde a versão mora (qTranslate-XT, raiz)

| Local | O quê |
|---|---|
| `qtranslate.php` (header) | `* Version: X.Y.Z` |
| `qtranslate.php` | `const QTX_VERSION = 'X.Y.Z';` |
| `CHANGELOG.md` | primeira entrada `### X.Y.Z` |
| `readme.txt` → `## Changelog` | entrada correspondente |

Notas deste fork:
- `readme.txt` tem `Stable tag: N/A` — o plugin **não** está no WordPress.org; mantenha `N/A`, não sincronize com a versão.
- `composer.json` **não** tem campo `version` — não crie um.

## Plugin standalone (qtx-polylang-migrator/)

Versão **independente** em `qtx-polylang-migrator/qtx-polylang-migrator.php` (header `* Version:`). O script `scripts/package-qtx-polylang-migrator.sh` lê essa versão para nomear o zip (`build/qtx-polylang-migrator-<versão>.zip`). Bump do plugin raiz NÃO implica bump do migrator, e vice-versa.

## Verificação rápida

```bash
grep -n "Version:" qtranslate.php | head -2
grep -n "QTX_VERSION" qtranslate.php
head -3 CHANGELOG.md
grep -n "Stable tag" readme.txt
grep -n "Version:" qtx-polylang-migrator/qtx-polylang-migrator.php
```

As duas ocorrências em `qtranslate.php` e a primeira entrada do `CHANGELOG.md` devem ser idênticas. Ao fazer bump, atualize **todas de uma vez** e adicione a entrada no `## Changelog` do `readme.txt` no mesmo commit. Reporte qualquer divergência encontrada com arquivo:linha.
