---
name: readme-txt-format
description: Formato do readme.txt do qTranslate-XT (variante markdown deste fork, não o formato clássico do WordPress.org). Use ao editar readme.txt para não quebrar a estrutura.
---

# Formato do readme.txt deste fork

**Atenção**: este `readme.txt` NÃO usa o formato clássico do WordPress.org (`=== Nome ===` / `== Seção ==`). Ele usa **headings markdown** (`#`/`##`). Siga o formato existente do arquivo — não "corrija" para o formato clássico.

## Estrutura atual

```
# qTranslate-XT (eXTended)          ← título nível 1
<bloco de metadados chave: valor>    ← logo após o título
<descrição curta em uma linha>

## Description
## Installation
## Frequently Asked Questions
## Upgrade Notice
## Screenshots
## Changelog
## Known Issues
## Credentials
## Desirable Unimplemented Features
```

## Metadados do cabeçalho (manter chaves e ordem)

`Developed by:`, `Contributors:`, `Tags:`, `Requires at least:`, `Tested up to:`, `Requires PHP:`, `Stable tag:`, `License:`, `License URI:`.

Regras:
- **`Stable tag: N/A`** — o plugin não está publicado no WordPress.org; mantenha `N/A`, não coloque a versão aqui. A versão oficial mora em `qtranslate.php` (ver skill **version-sync-check**).
- `Tested up to:` / `Requires PHP:` — atualize apenas quando a compatibilidade for de fato verificada.

## Seção Changelog

- Novas entradas no **topo** da seção `## Changelog`, espelhando o `CHANGELOG.md` (mesma versão, itens equivalentes; o CHANGELOG.md pode ser mais detalhado).
- Tom orientado ao usuário: o que mudou e por que importa.

## Idioma e tom

O arquivo é em **inglês** — mantenha, mesmo que a solicitação chegue em português. Links em markdown `[texto](url)` como no restante do arquivo.
