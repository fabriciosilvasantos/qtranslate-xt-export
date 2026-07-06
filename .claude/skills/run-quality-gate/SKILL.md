---
name: run-quality-gate
description: Como rodar PHPStan e PHPCS do qTranslate-XT no Docker e interpretar os resultados. Use ao revisar código PHP ou antes de considerar uma mudança concluída.
---

# Gate de qualidade: PHPStan + PHPCS

As ferramentas rodam **dentro do Docker**, nunca no host:

```bash
./run-tests.sh stan     # PHPStan (config: phpstan.neon.dist, level 2)
./run-tests.sh phpcs    # PHPCS com WordPress Coding Standards (config: phpcs.xml.dist)
./run-tests.sh lint     # os dois em sequência
```

## Rodar só nos arquivos alterados

Dentro do container (`./run-tests.sh shell` ou `docker compose run --rm phpunit sh`):

```bash
vendor/bin/phpcs --standard=phpcs.xml.dist src/admin/admin_options.php
vendor/bin/phpstan analyse -c phpstan.neon.dist src/admin/admin_options.php
```

Use `git diff --name-only master -- '*.php'` para obter a lista de alterados.

## Interpretação

- **PHPStan level 2**: erros reportados são quase sempre reais (tipos, chamadas inexistentes, null safety). Não suprima com `@phpstan-ignore` sem justificar.
- **PHPCS/WPCS**: distinga `ERROR` (bloqueia) de `WARNING` (avalie caso a caso). O código legado do plugin tem estilo próprio — não reformate arquivos inteiros; corrija apenas o que a mudança introduziu.
- Critério de bloqueio: falha de **segurança** (escaping/sanitização/nonce/capability ausente) ou erro novo de PHPStan bloqueiam; questões puramente estilísticas em código não tocado são recomendação, não bloqueio.
- Reporte sempre a saída real das ferramentas, com arquivo:linha.
