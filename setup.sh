#!/bin/bash

# === CONFIGURAÇÕES COM VARIÁVEIS DE AMBIENTE ===
PROJECT_NAME="${PROJECT_NAME:-meu-plugin}"
DB_NAME="${DB_NAME:-wp_${PROJECT_NAME}}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-wab12345678}"
WP_USER="${WP_USER:-admin}"
WP_PASS="${WP_PASS:-admin}"
WP_EMAIL="${WP_EMAIL:-admin@example.com}"
WP_URL="${WP_URL:-http://localhost:8080}"
WP_PATH="${WP_PATH:-./wordpress}"
PHP_CMD="php -d memory_limit=512M"

# === INÍCIO ===
echo "📦 Iniciando instalação para '$PROJECT_NAME' no caminho '$WP_PATH'"

# === CRIA DIRETÓRIO DO WORDPRESS ===
mkdir -p "$WP_PATH"

# === FAZ DOWNLOAD DO WORDPRESS ===
echo "⬇️ Baixando WordPress..."
$PHP_CMD $(which wp) core download --locale=pt_BR --path="$WP_PATH"

# === CRIA ARQUIVO wp-config.php ===
echo "⚙️ Criando wp-config.php..."
$PHP_CMD $(which wp) config create \
  --dbname="$DB_NAME" \
  --dbuser="$DB_USER" \
  --dbpass="$DB_PASS" \
  --path="$WP_PATH" \
  --skip-check

# === CRIA O BANCO DE DADOS ===
echo "🧩 Criando banco de dados..."
$PHP_CMD $(which wp) db create --path="$WP_PATH"

# === INSTALA O WORDPRESS ===
echo "🚀 Instalando WordPress..."
$PHP_CMD $(which wp) core install \
  --url="$WP_URL" \
  --title="$PROJECT_NAME Dev" \
  --admin_user="$WP_USER" \
  --admin_password="$WP_PASS" \
  --admin_email="$WP_EMAIL" \
  --path="$WP_PATH"

# === CRIA PLUGIN ===
PLUGIN_DIR="$WP_PATH/wp-content/plugins/$PROJECT_NAME"
echo "🛠️ Criando plugin em $PLUGIN_DIR"
mkdir -p "$PLUGIN_DIR"

cat <<EOF > "$PLUGIN_DIR/$PROJECT_NAME.php"
<?php
/**
 * Plugin Name: Meu Plugin
 * Description: Um plugin de exemplo criado pelo setup.sh
 * Version: 0.1
 * Author: Seu Nome
 */

// Seu código começa aqui
EOF

cd "$PLUGIN_DIR"
git init
echo "# Plugin $PROJECT_NAME" > README.md
git add .
git commit -m "Início do plugin WordPress"

echo "✅ Plugin '$PROJECT_NAME' criado com sucesso em '$PLUGIN_DIR'"
echo "🔗 Acesse: $WP_URL/wp-admin/plugins.php para ativar o plugin."
