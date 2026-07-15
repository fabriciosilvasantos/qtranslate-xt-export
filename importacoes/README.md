# importacoes/

Pasta para os **arquivos WXR/XML originais de importação** (exports do site de origem em qTranslate-XT) usados nas migrações para Polylang.

## Regras

- **Nunca commitar os arquivos de export**: eles contêm dados reais (conteúdo, autores, e-mails) do site de origem. O `.gitignore` da raiz ignora todo o conteúdo desta pasta, exceto este README.
- Os arquivos aqui também ficam **fora do pacote distribuível** do migrator (o empacotamento usa allowlist).
- Convenção de nome: `<site>-<AAAA-MM-DD>.xml` (ex.: `sociologia-politica-2026-03-10.xml`), preservando a data do export para rastreabilidade.
- Para testes automatizados, use as **fixtures fictícias** de `tests/fixtures/` — nunca copie dados reais para lá sem anonimizar.

## Uso

1. No site de origem: `Ferramentas > Exportar > Todo o conteúdo`.
2. Salve o arquivo nesta pasta.
3. No site de destino (com Polylang + qTx Polylang Migrator ativos): `Ferramentas > qTranslate Migrator > upload` do arquivo.
