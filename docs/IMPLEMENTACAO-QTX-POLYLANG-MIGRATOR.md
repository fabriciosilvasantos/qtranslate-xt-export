# Implementação da Nova Versão do Migrador qTranslate-XT → Polylang

## Visão Geral

Esta documentação registra a implementação da nova versão da migração qTranslate-XT → Polylang, agora extraída para um plugin standalone. O objetivo desta nova arquitetura é desacoplar a migração do runtime do qTranslate-XT, reduzir o acoplamento com o plugin legado e concentrar em um único plugin o fluxo de processamento de XML, importação, reconstrução de hierarquia, conexão com o Polylang e reparo de duplicatas.

O escopo implementado cobre o fluxo `WXR/XML qTranslate -> importação -> reconstrução -> Polylang -> reparo`, com foco em manutenção técnica, compatibilidade de transição e previsibilidade operacional.

## Estado Atual da Implementação

O estado atual do repositório já reflete a extração da migração para o plugin standalone `qTranslate to Polylang Migrator`.

Hoje, a implementação está organizada assim:

- o plugin standalone já existe e possui bootstrap, constantes, uninstall e tela própria em `Ferramentas`
- o fluxo da migração já opera sem dependência do admin do qTranslate-XT
- o motor da migração foi movido fisicamente para dentro de `qtx-polylang-migrator/`
- o motor já foi modularizado em serviços menores, separados por responsabilidade
- a extração foi validada pelas suítes `test`, `integration`, `stan` e `phpcs`

## Fases Implementadas

### Fase 1 — Extração inicial para plugin standalone

Foi criado o plugin `qTranslate to Polylang Migrator`, com identidade própria e ciclo de carga independente.

Nesta fase foram implementados:

- arquivo principal do plugin standalone
- nova tela em `Ferramentas > qTranslate Migrator`
- bootstrap próprio
- constantes próprias do plugin novo
- `uninstall` próprio para limpeza de artefatos da migração

O objetivo desta fase foi tirar a migração do papel de funcionalidade “acoplada” ao qTranslate-XT e estabelecer uma unidade de execução própria.

### Fase 2 — Transição do qTranslate-XT legado

Depois da criação do plugin standalone, a migração foi desacoplada do admin do qTranslate-XT e ficou concentrada no plugin novo.

Nesta fase foram implementados:

- retirada da responsabilidade de migração do qTranslate-XT
- isolamento da interface de migração no plugin standalone
- preparação para remoção dos pontos legados de transição

O objetivo desta fase foi garantir que o fluxo passasse a existir por conta própria, sem depender do plugin legado para operar.

### Fase 3 — Migração do motor para o plugin novo

Com a transição estabilizada, o motor da migração foi movido para dentro do plugin standalone.

Nesta fase foram implementados:

- movimentação física do engine para `qtx-polylang-migrator/`
- inversão de posse da implementação, que deixou de pertencer ao qTranslate-XT
- manutenção apenas de uma bridge mínima no caminho legado

O objetivo desta fase foi fazer com que o plugin novo passasse a ser o dono real da migração.

### Fase 4 — Modularização do motor

Na sequência, o motor foi quebrado em serviços menores para reduzir o tamanho dos arquivos centrais e separar responsabilidades.

Nesta fase foram implementados:

- bootstrap e fallbacks do migrador
- parser e transformação de WXR
- integração com Polylang
- importação direta de XML
- reconstrução de hierarquia
- deduplicação e reparo
- ações do admin
- renderização da interface administrativa

O objetivo desta fase foi tornar a manutenção mais previsível e permitir evolução por componente, em vez de continuar concentrando toda a lógica em um único arquivo.

## O Que Foi Implementado Funcionalmente

A extração não foi apenas estrutural. O fluxo funcional da migração também foi consolidado no plugin standalone.

As capacidades hoje implementadas incluem:

- parser local dos formatos multilíngues do qTranslate
- normalização de idiomas com `pb -> pt`
- preservação de idiomas reais como `en`, `de`, `es` e `fr`
- provisionamento automático de idiomas reconhecidos no Polylang
- importação direta do XML convertido
- reconstrução de hierarquia de páginas
- conexão de traduções via API do Polylang
- deduplicação e reparo de duplicatas órfãs com rebaixamento seguro para `draft`
- manutenção de um wrapper legado no qTranslate-XT para transição
 

Também foi preservado o contrato interno de metadados `_pll_migration_*`, para evitar quebrar o processamento já construído em torno da migração.

## Arquitetura Atual

### Bootstrap do plugin standalone

O plugin standalone é carregado a partir do seu arquivo principal e define identidade própria, constantes, carregamento de traduções e `uninstall`.

### Engine agregador

O engine atual atua como ponto de montagem do migrador, reunindo os módulos necessários sem concentrar a regra de negócio diretamente no arquivo agregador.

### Serviços de transformação, importação e reparo

O núcleo foi dividido em serviços especializados para:

- transformação do WXR
- integração e provisionamento de idiomas no Polylang
- importação do XML
- reconstrução de hierarquia
- deduplicação e conexão de traduções

Essa separação reduz o acoplamento entre etapas e deixa mais claro onde cada responsabilidade está implementada.

### Camada de admin

A interface administrativa foi separada em duas partes:

- ações do admin
- renderização do admin

Com isso, a camada visual e o tratamento das ações deixaram de disputar o mesmo espaço no mesmo arquivo principal.

### Separação do legado

O qTranslate-XT antigo deixou de participar do fluxo operacional da migração.

A bridge técnica intermediária foi removida quando os testes e o carregamento passaram a depender diretamente do engine do plugin standalone. Na etapa seguinte, o wrapper visual do admin legado também foi removido, deixando o migrador acessível apenas pelo plugin novo.

## Compatibilidade e Requisitos

Esta nova versão do migrador está documentada para o seguinte baseline:

- WordPress `6.9.4`
- PHP `8.4`
- dependência ativa do `Polylang`
- independência operacional em relação ao qTranslate-XT

Além disso, o contrato `_pll_migration_*` foi preservado como base de compatibilidade interna da migração, evitando ruptura desnecessária entre a implementação anterior e a nova arquitetura.

## Validação Realizada

A extração e modularização foram validadas no repositório com as seguintes suítes:

- `./run-tests.sh test`
- `./run-tests.sh integration`
- `./run-tests.sh stan`
- `./run-tests.sh phpcs`

No estado atual da implementação, essas validações foram usadas para confirmar que a nova organização do migrador permaneceu funcional e consistente com o fluxo já existente.

Também foi executado um smoke test do plugin standalone em ambiente WordPress com:

- `Polylang` ativo
- `qTranslate-XT` inativo
- `qTranslate to Polylang Migrator` ativo

Nesse teste, a renderização da tela inicial do migrador foi validada com sucesso no passo de upload do XML.

No ambiente local de desenvolvimento, o `docker-compose` também passou a expor o diretório `qtx-polylang-migrator` como plugin top-level em `wp-content/plugins/`, eliminando a necessidade de exposição manual para os testes do migrador standalone.

Depois do ajuste do ambiente, essa validação também foi repetida com sucesso após a recriação limpa do serviço WordPress, confirmando o carregamento operacional do admin do migrador no cenário standalone local.

Além disso, o repositório passou a ter um fluxo reproduzível de empacotamento do plugin standalone, com geração de ZIP distribuível a partir do diretório `qtx-polylang-migrator/`.

Também foram gerados e versionados arquivos reais de tradução do plugin standalone em `languages/`, incluindo catálogo `.pot`, catálogo `.po` e arquivo compilado `.mo`, todos associados ao text domain `qtx-polylang-migrator`.

## Pendências e Próximos Passos

Embora a extração principal esteja concluída, ainda existem frentes de acabamento e endurecimento para a nova versão:

- refinamentos futuros de internacionalização para novos locales além do catálogo base atual

No estado atual, o empacotamento básico do plugin standalone já foi implementado com script e saída distribuível, os arquivos reais de tradução já foram versionados e os pontos legados de transição do qTranslate-XT já foram removidos do fluxo da migração.

Esses pontos não bloqueiam a documentação da implementação atual, mas representam o próximo ciclo natural de maturação do plugin novo.

## Mapa de Implementação

Os principais pontos de entrada da implementação atual são:

- `qtx-polylang-migrator/`

Esse mapa resume onde a nova arquitetura vive hoje:

- o plugin standalone concentra a implementação principal
