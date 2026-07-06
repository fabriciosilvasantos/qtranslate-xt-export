#!/bin/bash
set -e

echo "Setting up WordPress Test Suite..."

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests}
WP_VERSION=${WP_VERSION:-6.4.3}

mkdir -p "$WP_TESTS_DIR/includes"
mkdir -p "$WP_TESTS_DIR/data"

echo "Downloading WordPress test includes..."
curl -sL "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" -o /tmp/includes_list.html
grep -o 'href="[^"]*\.php"' /tmp/includes_list.html | sed 's/href="//;s/"//' | while read file; do
    curl -sL "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/$file" -o "$WP_TESTS_DIR/includes/$file"
done

echo "Downloading WordPress test data..."
curl -sL "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/" -o /tmp/data_list.html
grep -o 'href="[^"]*"' /tmp/data_list.html | sed 's/href="//;s/"//' | while read file; do
    if [ -n "$file" ] && [ "$file" != ".." ]; then
        curl -sL "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/$file" -o "$WP_TESTS_DIR/data/$file"
    fi
done

echo "Creating wp-tests-config.php..."
cat > "$WP_TESTS_DIR/wp-tests-config.php" << 'EOF'
<?php
define( 'ABSPATH', '/tmp/wordpress-tests/src/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db:3306' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'MYSQL_SSL', false );
$table_prefix = 'wp_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PERMALINK_URL', '' );
define( 'WP_TESTS_SUBDOMAIN', false );
define( 'WP_TESTS_PREFIX', 'test_' );
EOF

echo "WordPress Test Suite setup complete!"
echo "WP_TESTS_DIR: $WP_TESTS_DIR"
ls -la "$WP_TESTS_DIR/includes/" | head -10
