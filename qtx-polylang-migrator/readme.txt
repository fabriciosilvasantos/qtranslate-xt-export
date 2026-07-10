=== qTranslate to Polylang Migrator ===
Contributors: qtranslate-community
Tags: migration, polylang, multilingual, import, qtranslate
Requires at least: 6.9.4
Tested up to: 7.0
Requires PHP: 8.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Converte exportações WXR/XML com conteúdo qTranslate para um fluxo de importação compatível com Polylang.

== Description ==

O plugin `qTranslate to Polylang Migrator` foi extraído do fluxo legado do qTranslate-XT para concentrar em um componente standalone a migração de conteúdo multilíngue para o formato do Polylang.

Principais capacidades:

- processamento de XML/WXR com conteúdo no formato qTranslate
- importação direta no WordPress
- reconstrução de hierarquia de páginas
- provisionamento automático de idiomas reconhecidos no Polylang
- conexão de traduções pela API do Polylang
- reparo de duplicatas órfãs com rebaixamento seguro para `draft`

Este plugin é voltado para migração e operação administrativa. Ele não substitui o qTranslate-XT como plugin de conteúdo multilíngue no site de origem.

== Installation ==

1. Instale e ative o plugin `Polylang`.
2. Copie a pasta `qtx-polylang-migrator` para o diretório `wp-content/plugins/`.
3. Ative o plugin `qTranslate to Polylang Migrator` no painel do WordPress.
4. Acesse `Ferramentas > qTranslate Migrator`.
5. Faça upload do XML exportado do site original em qTranslate-XT.

== Frequently Asked Questions ==

= O plugin depende do qTranslate-XT ativo? =

Não. O objetivo desta nova versão é executar a migração de forma standalone no site de destino, com dependência apenas do Polylang.

= O plugin cria idiomas ausentes no Polylang? =

Sim. Quando o XML traz idiomas válidos ainda não configurados, o migrador tenta provisioná-los automaticamente antes da conexão final das traduções.

= O que acontece com duplicatas órfãs detectadas durante a migração? =

O migrador preserva um item canônico e rebaixa duplicatas seguras para `draft`. O fluxo evita exclusão permanente automática.

= Preciso clicar em todos os botões da tela de resultados para concluir a migração? =

Não. O botão **Importar para WordPress** já executa o fluxo completo: importação do XML, reconstrução de hierarquia, conexão de traduções no Polylang e verificação de duplicatas. Os demais botões da tela de resultados (ex.: "Executar Migração Completa") são atalhos redundantes para etapas que já foram executadas automaticamente — não é necessário acioná-los de novo.

== Após a Migração ==

A migração cobre o conteúdo transportável pelo WXR (posts, páginas, termos e as ligações de tradução entre eles), mas **não** cobre tudo que compõe um site WordPress. Depois de rodar o migrador, revise manualmente os itens abaixo no site de destino:

**Menus de navegação**

O WXR contém os itens de menu (`nav_menu_item`) como posts, mas as referências que eles carregam (ID do post/página apontado, idioma, hierarquia do menu) não são remontadas automaticamente pelo migrador. Recrie os menus em `Aparência > Menus` apontando para as páginas já migradas, um menu por idioma conforme a configuração do Polylang.

**Página inicial estática**

A opção de página inicial (`show_on_front` / `page_on_front`) não é migrada. Se o site de origem usava uma página estática como início (ex.: uma página "Apresentação"), configure novamente essa opção em `Configurações > Leitura` no destino — caso contrário a página migrada vira uma página comum, sem o papel de home.

**Tema, Customizer e widgets**

Configurações de tema, opções do Customizer (cores, logotipo, textos de cabeçalho/rodapé) e widgets não fazem parte do WXR e precisam ser reconfigurados manualmente no destino.

**Anexos (mídia)**

Os anexos migrados mantêm as URLs do site de origem (o WXR referencia os arquivos pelo domínio original, não copia os binários). Depois da migração, escolha uma das opções:

- copie os arquivos de `wp-content/uploads/` do site de origem para o destino (ex.: via `rsync`) e rode uma busca e substituição (search-replace) do domínio antigo pelo novo no banco de dados; ou
- reimporte o mesmo XML usando o importador oficial do WordPress (`Ferramentas > Importar > WordPress`) com a opção **"Download and import file attachments"** marcada, para que os arquivos de mídia sejam baixados e reassociados no destino.

== Changelog ==

= 0.2.0 =

* Segurança: verificação de capability (`manage_options`) em todos os handlers de ação do admin.
* Segurança: nonce dedicado por operação (upload, importação, finalização, reparo).
* Segurança: validação de extensão/tipo e limite de tamanho (50MB, filtrável via `qtxpm_max_upload_bytes`) no upload de XML; parsing com `LIBXML_NONET`.
* Correção: limpeza de desinstalação consolidada em `uninstall.php`, incluindo a option `qtxpm_current_migration_run`.
* Qualidade: type hints completos de parâmetros e retornos em todos os serviços; sanitização do parâmetro `step` no admin.

= 0.1.0 =

* Primeira extração do migrador qTranslate-XT → Polylang como plugin standalone.
* Inclusão do fluxo de importação, reconstrução de hierarquia e conexão de traduções.
* Inclusão da estrutura inicial de empacotamento e documentação própria.
