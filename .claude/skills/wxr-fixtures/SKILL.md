---
name: wxr-fixtures
description: Como construir e usar exports WXR de amostra para testar o pipeline de migração qTranslate → Polylang do plugin qtx-polylang-migrator. Use ao testar ou depurar o migrator.
---

# Fixtures WXR para o migrator

**Estado atual: não existem fixtures `.xml` em `tests/`.** Os testes de migração (`tests/QTX_Polylang_Migration_Test.php`, `tests/integration/QTX_Polylang_Migration_Integration_Test.php`) usam dados construídos em PHP com os stubs (`tests/polylang_stubs.php`). Ao criar fixtures novos, coloque-os em `tests/fixtures/` e referencie-os pelos testes.

## Esqueleto mínimo de WXR multilíngue

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:wp="http://wordpress.org/export/1.2/">
  <channel>
    <wp:wxr_version>1.2</wp:wxr_version>
    <item>
      <title>[:pt]Página Inicial[:en]Home Page[:]</title>
      <content:encoded><![CDATA[[:pt]Conteúdo em português[:en]English content[:]]]></content:encoded>
      <wp:post_id>10</wp:post_id>
      <wp:post_parent>0</wp:post_parent>
      <wp:menu_order>1</wp:menu_order>
      <wp:post_type><![CDATA[page]]></wp:post_type>
      <wp:status><![CDATA[publish]]></wp:status>
      <wp:post_name><![CDATA[pagina-inicial]]></wp:post_name>
    </item>
  </channel>
</rss>
```

## Casos que um conjunto de fixtures deve cobrir

- Post com todos os idiomas ativos; post com idioma **faltando**; post **monolíngue** (sem blocos).
- Hierarquia: página filha (`wp:post_parent` ≠ 0) e `wp:menu_order` — exercita `hierarchy-service.php`.
- Título multilíngue com conteúdo monolíngue (e vice-versa).
- Blocos nas três sintaxes (ver skill **language-blocks-reference**), HTML dentro de CDATA.
- Duplicatas em potencial (mesmo `post_name`) — exercita `duplicate-repair-service.php`.

## Pipeline e pontos de inspeção

Ordem em `qtx-polylang-migrator/includes/` (orquestração: `migration-engine.php`):
`xml-import-service.php` → `wxr-transformer.php` (split dos blocos) → `hierarchy-service.php` → `polylang-service.php` (idiomas + conexão de traduções) → `duplicate-repair-service.php`.

Estado intermediário em **transients** com prefixo `qtxpm_` (`staged_xml`, `import_report`, `migration_results`); as chaves são geradas por `qtxpm_get_migration_transient_key()`. Para depurar num WordPress real (`./run-tests.sh up`, http://localhost:8088):

```bash
wp transient get <chave>   # ou consulte wp_options: _transient_qtxpm_*
```

UI de teste manual: `Ferramentas > qTranslate Migrator` (`tools.php?page=qtx-polylang-migrator`).
