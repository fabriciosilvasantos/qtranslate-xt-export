---
name: language-blocks-reference
description: Referência da sintaxe de blocos multilíngues do qTranslate-XT ([:xx], <!--:xx-->, {:xx}) — parsing, round-trip e edge cases. Use ao trabalhar com parsing, split ou migração de conteúdo multilíngue.
---

# Blocos multilíngues — referência

## As três sintaxes (equivalentes)

| Estilo | Abertura | Fechamento/neutro |
|---|---|---|
| Colchete (padrão atual) | `[:pt]` | `[:]` |
| Comentário HTML (legado) | `<!--:pt-->` | `<!--:-->` |
| Chave (swirly, desde 3.3.6) | `{:pt}` | `{:}` |

Exemplo: `[:pt]Olá[:en]Hello[:]` — o texto após `[:xx]` pertence ao idioma `xx` até o próximo tag; `[:]` encerra retornando ao estado neutro (texto visível em todos os idiomas).

## Onde está o código (fonte da verdade)

- `src/language_blocks.php` — todo o parsing/serialização:
  - `qtranxf_get_language_blocks()` — tokeniza via o split regex que aceita as 3 sintaxes (`#(<!--:$lang_code-->|<!--:-->|\[:$lang_code\]|\[:\]|\{:$lang_code\}|\{:\})#ism`).
  - `qtranxf_split()` / variantes — montam o array `[lang => texto]`.
  - `qtranxf_use()` — extrai o texto do idioma corrente de uma string multilíngue.
- Formato do código de idioma: constante `QTX_LANG_CODE_FORMAT` (2 letras).
- Testes de referência: `tests/QTX_Translator_Test.php` documentam o comportamento esperado.

## Regras e edge cases obrigatórios

- **Round-trip sem perda**: split → join deve reproduzir o conteúdo; nunca descarte texto de idiomas que você não está processando.
- **Texto fora de qualquer bloco** (antes do primeiro tag ou após `[:]`) pertence a **todos** os idiomas.
- **Idioma ausente**: string multilíngue sem `[:xx]` para o idioma pedido → comportamento de fallback definido por `$q_config['not_available']` (ver `src/language_blocks.php:441`).
- **Sintaxes misturadas** na mesma string são válidas — o split regex aceita as três simultaneamente.
- **HTML dentro de blocos** é comum (o regex usa flags `ism`); não parseie linha a linha.
- **Case-insensitive**: `[:PT]` casa com o regex (`i`); normalize para minúsculas ao mapear.
- String **sem nenhum bloco** é conteúdo monolíngue — deve passar intacta.
- Na **migração para Polylang**, cada idioma vira um post separado; blocos vazios (`[:pt][:en]texto[:]` → pt vazio) não devem gerar posts vazios sem decisão explícita.
