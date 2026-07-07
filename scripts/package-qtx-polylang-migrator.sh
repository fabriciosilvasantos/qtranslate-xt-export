#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/qtx-polylang-migrator"
BUILD_ROOT="$ROOT_DIR/build"
STAGE_DIR="$BUILD_ROOT/qtx-polylang-migrator"
MAIN_FILE="$PLUGIN_DIR/qtx-polylang-migrator.php"

if [[ ! -f "$MAIN_FILE" ]]; then
  echo "Arquivo principal do plugin não encontrado: $MAIN_FILE" >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "O comando 'zip' é necessário para gerar o pacote distribuível." >&2
  exit 1
fi

VERSION="$(
  sed -n "s/^ \\* Version: //p" "$MAIN_FILE" | head -n 1 | tr -d '\r'
)"

if [[ -z "$VERSION" ]]; then
  echo "Não foi possível determinar a versão do plugin em $MAIN_FILE" >&2
  exit 1
fi

PACKAGE_FILE="$BUILD_ROOT/qtx-polylang-migrator-$VERSION.zip"

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"

cp -R "$PLUGIN_DIR/admin" "$STAGE_DIR/"
cp -R "$PLUGIN_DIR/includes" "$STAGE_DIR/"
cp -R "$PLUGIN_DIR/languages" "$STAGE_DIR/"
cp "$PLUGIN_DIR/qtx-polylang-migrator.php" "$STAGE_DIR/"
cp "$PLUGIN_DIR/readme.txt" "$STAGE_DIR/"
cp "$PLUGIN_DIR/uninstall.php" "$STAGE_DIR/"

rm -f "$PACKAGE_FILE"

(
  cd "$BUILD_ROOT"
  zip -qr "$(basename "$PACKAGE_FILE")" qtx-polylang-migrator
)

echo "Pacote gerado com sucesso:"
echo "  Versão: $VERSION"
echo "  Pasta:  $STAGE_DIR"
echo "  ZIP:    $PACKAGE_FILE"
