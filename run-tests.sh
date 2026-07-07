#!/bin/bash

# Script para executar testes do qTranslate-XT

case "$1" in
  up)
    echo "Iniciando containers..."
    docker compose up -d db wordpress
    echo "Aguardando WordPress estar pronto..."
    sleep 10
    echo "WordPress disponível em: http://localhost:8088"
    ;;

  test)
    echo "Executando PHPUnit..."
    docker compose run --build --rm phpunit
    ;;

  integration)
    echo "Executando testes de integração simplificados..."
    docker compose run --build --rm phpunit-integration
    ;;

  stan)
    echo "Executando PHPStan..."
    docker compose run --build --rm phpstan
    ;;

  phpcs)
    echo "Executando PHPCS..."
    docker compose run --build --rm phpcs
    ;;

  package-migrator)
    echo "Gerando pacote do plugin standalone..."
    ./scripts/package-qtx-polylang-migrator.sh
    ;;

  lint)
    echo "Executando PHPStan + PHPCS..."
    docker compose run --build --rm phpstan
    docker compose run --build --rm phpcs
    ;;

  all)
    echo "Executando todos os testes (PHPUnit + Integração + PHPStan + PHPCS)..."
    docker compose run --build --rm phpunit
    docker compose run --build --rm phpunit-integration
    docker compose run --build --rm phpstan
    docker compose run --build --rm phpcs
    ;;

  stop)
    echo "Parando containers..."
    docker compose down
    ;;

  logs)
    docker compose logs -f
    ;;

  shell)
    docker compose run --rm phpunit sh
    ;;

  *)
    echo "Uso: $0 {up|test|integration|stan|phpcs|package-migrator|lint|all|stop|logs|shell}"
    echo ""
    echo "Comandos:"
    echo "  up          - Inicia WordPress e banco de dados"
    echo "  test        - Executa testes PHPUnit unitários"
    echo "  integration - Executa testes de integração simplificados"
    echo "  stan        - Executa análise PHPStan"
    echo "  phpcs       - Executa verificação de código PHPCS"
    echo "  package-migrator - Gera o pacote distribuível do plugin standalone"
    echo "  lint        - Executa PHPStan + PHPCS"
    echo "  all         - Executa todos os testes"
    echo "  stop        - Para todos os containers"
    echo "  logs        - Mostra logs em tempo real"
    echo "  shell       - Abre shell no container de testes"
    exit 1
    ;;
esac
