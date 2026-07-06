---
name: multilingual-test-fixtures
description: Biblioteca de casos de teste padrão para conteúdo multilíngue do qTranslate-XT — strings de entrada e comportamento esperado. Use ao escrever testes PHPUnit que envolvam blocos [:xx].
---

# Fixtures de teste multilíngue

Todo teste novo que toca parsing/split/uso de blocos deve cobrir estes casos. Use-os como data providers (`@dataProvider`) para não repetir estrutura.

## Casos canônicos

| # | Entrada | Comportamento esperado |
|---|---|---|
| 1 | `[:pt]Olá[:en]Hello[:]` | caminho feliz: pt→"Olá", en→"Hello" |
| 2 | `[:pt]Só português[:]` | idioma ausente (en): fallback conforme `$q_config['not_available']` — nunca string perdida |
| 3 | `Texto sem blocos` | monolíngue: retorna intacto para qualquer idioma |
| 4 | `` (string vazia) / `null` | não fatal; atenção à null safety do PHP 8.4 |
| 5 | `Prefixo [:pt]meio[:] sufixo` | texto fora de bloco pertence a todos os idiomas |
| 6 | `<!--:pt-->Olá<!--:en-->Hello<!--:-->` | sintaxe legada equivale à de colchetes |
| 7 | `{:pt}Olá{:en}Hello{:}` | sintaxe swirly equivale às demais |
| 8 | `[:pt]A<!--:en-->B{:}` | sintaxes misturadas na mesma string são válidas |
| 9 | `[:pt]<p>HTML <b>rico</b></p>[:en]<p>Rich</p>[:]` | HTML e quebras de linha dentro do bloco preservados |
| 10 | `[:pt][:en]texto[:]` | bloco vazio (pt vazio): não inventar conteúdo nem quebrar |
| 11 | `[:PT]maiúsculo[:]` | tags são case-insensitive no regex; código normalizado |
| 12 | `[:xx]idioma não habilitado[:]` | código válido no formato mas não ativo: não vazar para outros idiomas |
| 13 | `[:pt]acentuação: ção, ã, é[:en]uni: 中文 🎉[:]` | UTF-8/multibyte intactos (round-trip) |
| 14 | entrada malformada: `[:pt sem fechar` | degradação previsível, sem warning/fatal |

## Round-trip (obrigatório em mudanças de parsing)

Para os casos 1, 5–9 e 13: `join(split(X))` deve reproduzir o conteúdo sem perda. Na migração, o inverso: cada idioma extraído + reconstrução deve preservar todo o texto original.

## Convenções da suíte

- Padrão Arrange/Act/Assert; nomes descritivos (`test_split_retorna_fallback_quando_idioma_ausente`).
- Reutilize os stubs de `tests/` (não recrie mocks de funções WP).
- Rode via Docker — ver skill **run-tests**.
