# Languages

Esta pasta contém os arquivos de tradução do plugin `qTranslate to Polylang Migrator`.

Arquivos atualmente versionados:

- `qtx-polylang-migrator.pot`
- `qtx-polylang-migrator-pt_BR.po` / `.mo`
- `qtx-polylang-migrator-en_US.po` / `.mo`

O text domain do plugin é `qtx-polylang-migrator`.

## Locales disponíveis

- **pt_BR** — idioma-fonte do plugin (as strings originais em `includes/` já estão em português).
  Os `msgstr` deste `.po` ficam vazios de propósito: quando o WordPress não encontra um `.mo`
  correspondente, ele usa o `msgid` original, que já é o texto em português.
- **en_US** — tradução completa para inglês (EUA), incluindo mensagens de erro, rótulos da UI
  administrativa e textos de ajuda. Termos como `qTranslate`, `Polylang`, `WXR` e `XML` são mantidos
  sem tradução por serem nomes próprios/siglas técnicas.

## Como contribuir com uma nova tradução

1. Regenere o `.pot` sempre que strings novas forem adicionadas ao código (via WP-CLI
   `wp i18n make-pot qtx-polylang-migrator qtx-polylang-migrator/languages/qtx-polylang-migrator.pot`
   ou ferramenta equivalente), garantindo que o cabeçalho `POT-Creation-Date` seja atualizado.
2. Copie o `.pot` para `qtx-polylang-migrator-<locale>.po` (ex.: `qtx-polylang-migrator-es_ES.po`),
   preenchendo o cabeçalho (`Language`, `Last-Translator`, `Language-Team`, `Plural-Forms`) e
   traduzindo cada `msgstr`. Preserve placeholders (`%s`, `%d`, `%1$s`, etc.), pontuação e
   marcação HTML/backticks presentes no `msgid`.
3. Compile o `.mo` correspondente com:
   ```bash
   msgfmt -c -o qtx-polylang-migrator-<locale>.mo qtx-polylang-migrator-<locale>.po
   ```
   A flag `-c` valida o arquivo; o comando deve rodar sem erros ou avisos.
4. Confira as estatísticas de cobertura com `msgfmt --statistics -o /dev/null qtx-polylang-migrator-<locale>.po`
   e garanta que todas as mensagens estejam traduzidas antes de enviar o PR.
5. Liste o novo locale nesta seção do README e atualize a lista de "Arquivos atualmente versionados" acima.
