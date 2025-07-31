#!/bin/bash

echo "🔧 Verificando dependências..."

# Verifica se wp-env está instalado globalmente
if ! command -v wp-env &> /dev/null; then
  echo "❌ wp-env não encontrado. Instalando globalmente via npm..."
  npm install -g @wordpress/env
fi

# Inicia o ambiente com wp-env
echo "🚀 Iniciando WordPress com wp-env..."
wp-env start

echo "✅ WordPress rodando em:"
echo "🔗 Admin: http://localhost:8888/wp-admin"
echo "👤 Usuário: admin | Senha: password"
