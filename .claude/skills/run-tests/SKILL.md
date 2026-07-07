---
name: run-tests
description: Como executar a suíte de testes do qTranslate-XT (PHPUnit unitário e integração) no ambiente Docker do projeto. Use SEMPRE antes de rodar qualquer teste — os testes NÃO rodam fora do container.
---

# Executar testes do qTranslate-XT

Todos os testes rodam em **Docker** (`docker-compose.yml`), via o wrapper `./run-tests.sh` na raiz do repositório. **Nunca** rode `vendor/bin/phpunit` direto no host — o PHP do host pode não ser 8.4 e as dependências vivem no container.

## Comandos

```bash
./run-tests.sh test          # PHPUnit unitário (phpunit.xml.dist, exclui tests/integration/)
./run-tests.sh integration   # Testes de integração (phpunit-integration.xml.dist)
./run-tests.sh stan          # PHPStan
./run-tests.sh phpcs         # PHPCS
./run-tests.sh lint          # PHPStan + PHPCS
./run-tests.sh all           # test + integration + stan + phpcs (gate completo)
./run-tests.sh shell         # Shell sh dentro do container de testes
./run-tests.sh stop          # Derruba os containers
```

## Rodar um único teste

O filtro precisa rodar **dentro do container**:

```bash
docker compose run --rm phpunit vendor/bin/phpunit --filter <NomeDaClasseOuMetodo>
# ou interativamente: ./run-tests.sh shell  →  vendor/bin/phpunit --filter test_nome
```

## Estrutura da suíte

- `tests/*.php` — unitários (bootstrap: `tests/bootstrap.php`). Referências de estilo: `QTX_Translator_Test.php`, `QTX_Language_Config_Test.php`, `QTX_Polylang_Migration_Test.php`.
- `tests/integration/*.php` — integração (bootstrap: `tests/bootstrap-integration.php`): `QTX_Integration_Test.php`, `QTX_Polylang_Migration_Integration_Test.php`.
- Stubs (reutilize, não recrie mocks): `wordpress_stubs.php`, `wp_class_stubs.php`, `polylang_stubs.php`, `acf_stubs.php`, `woocommerce_stubs.php`.

## Solução de problemas

- **Docker fora do ar / daemon não responde**: reporte a falha real; não conclua "testes passaram". Se o usuário permitiu, `./run-tests.sh up` sobe o ambiente (WordPress em http://localhost:8088, admin/admin).
- **`--build` lento**: os comandos do wrapper usam `--build`; a primeira execução demora, as seguintes usam cache.
- Sempre reporte a **saída real** do PHPUnit (contagem de testes/asserções/falhas), nunca uma suposição.
