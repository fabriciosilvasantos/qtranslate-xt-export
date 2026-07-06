=== qTranslate to Polylang Migrator ===
Contributors: qtranslate-community
Tags: migration, polylang, multilingual, import, qtranslate
Requires at least: 6.9.4
Tested up to: 6.9.4
Requires PHP: 8.4
Stable tag: 0.1.0
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

== Changelog ==

= 0.1.0 =

* Primeira extração do migrador qTranslate-XT → Polylang como plugin standalone.
* Inclusão do fluxo de importação, reconstrução de hierarquia e conexão de traduções.
* Inclusão da estrutura inicial de empacotamento e documentação própria.
