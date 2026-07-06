---
name: delegation-briefs
description: Templates de prompt para o gerente delegar tarefas aos agentes especialistas do qTranslate-XT com contexto completo. Use ao acionar qtx-polylang-expert, php-wp-reviewer, php-test-writer ou docs-changelog-maintainer via Task.
---

# Briefs de delegação

Especialistas **não veem a conversa** — todo prompt de delegação precisa ser autossuficiente. Inclua sempre: objetivo, arquivos/áreas, restrições e formato de retorno esperado.

## qtx-polylang-expert (implementação/investigação)

```
Objetivo: <o que implementar/investigar, com critério de pronto>
Arquivos/área: <ex.: src/language_blocks.php, qtx-polylang-migrator/includes/wxr-transformer.php>
Contexto: <sintoma, comportamento atual vs esperado, exemplo de entrada [:pt]...[:en]...[:]>
Restrições: PHP 8.4 com type hints; NÃO corromper dados multilíngues existentes (round-trip);
considerar idiomas ausentes/parciais e as 3 sintaxes de bloco.
Retorno: causa raiz com arquivo:linha, mudança feita, impacto em dados/compatibilidade, testes tocados.
```

## php-wp-reviewer (revisão — somente leitura)

```
Objetivo: revisar o diff de <branch/commits ou lista de arquivos>.
Foco: <ex.: handler de admin novo → nonce/capability/sanitização; saída no frontend → escaping>
Como: revise o diff (git diff <base>), não o repo inteiro. Rode PHPCS/PHPStan nos arquivos
alterados DENTRO do Docker (./run-tests.sh shell) e reporte a saída real.
Retorno: achados por severidade (Crítico→Baixo), cada um com arquivo:linha, problema concreto
e correção sugerida. Diga explicitamente se nada foi encontrado.
```

## php-test-writer (testes)

```
Objetivo: <cobrir a função X | corrigir os testes que falharam após Y>
O que mudou: <resumo da mudança de produção, arquivos>
Como: use os stubs existentes em tests/; rode via Docker (./run-tests.sh test|integration ou
docker compose run --rm phpunit vendor/bin/phpunit --filter <Nome>).
Edge cases obrigatórios: idiomas ausentes, conteúdo vazio, entrada malformada, null safety.
Retorno: testes adicionados/alterados, saída real do PHPUnit, bugs de produção revelados.
```

## docs-changelog-maintainer (documentação)

```
Objetivo: <registrar a feature/correção X | bump para X.Y.Z>
O que mudou: <resumo ou faixa de commits — ele confirmará com git log/diff>
Arquivos: CHANGELOG.md, readme.txt (## Changelog; Stable tag permanece N/A), README.md se a
feature muda o uso; em bump: header Version + QTX_VERSION em qtranslate.php.
Retorno: arquivos atualizados, entradas adicionadas, inconsistências de versão encontradas.
```

## Regras de orquestração

- **Paralelize** apenas tarefas sem dependência entre si (ex.: investigações em módulos distintos).
- **Sequencie** o fluxo padrão: implementar → revisar → testar → documentar.
- Retorno vago ou sem saída real de ferramenta → **redelegue** citando exatamente o que faltou.
