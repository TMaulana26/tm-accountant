#!/usr/bin/env bash
# ==============================================================================
# TM Accountant - Automated VPS Deployment Script
# ==============================================================================

set -e

# Colors for terminal output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo ""
echo -e "${CYAN}================================================================${NC}"
echo -e "${CYAN}     🚀 TM ACCOUNTANT - VPS AUTOMATED DEPLOYMENT SCRIPT         ${NC}"
echo -e "${CYAN}================================================================${NC}"
echo ""

# 1. Navigate to project root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${BLUE}[1/5] 📁 Working directory: ${SCRIPT_DIR}${NC}"

# 2. Fix permissions on .git and project files to prevent root conflicts
echo -e "${BLUE}[2/5] 🔑 Ensuring correct file permissions...${NC}"
if command -v sudo >/dev/null 2>&1; then
    sudo chown -R "$USER:$USER" "$SCRIPT_DIR" 2>/dev/null || true
fi

# 3. Pull latest code from Git
echo -e "${BLUE}[3/5] 📥 Pulling latest code from origin main...${NC}"
git pull origin main

# 4. Check if .env exists
if [ ! -f "$SCRIPT_DIR/.env" ]; then
    echo -e "${YELLOW}⚠️  File .env tidak ditemukan! Menyalin dari .env.example...${NC}"
    cp "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env"
    echo -e "${YELLOW}👉 Harap sesuaikan konfigurasi di .env sebelum melanjutkan!${NC}"
fi

# 5. Rebuild and restart Docker containers
echo -e "${BLUE}[4/5] 🐳 Rebuilding and restarting Docker containers...${NC}"
if command -v docker >/dev/null 2>&1; then
    if sudo docker compose version >/dev/null 2>&1; then
        sudo docker compose up -d --build --force-recreate
    elif docker compose version >/dev/null 2>&1; then
        docker compose up -d --build --force-recreate
    elif sudo docker-compose version >/dev/null 2>&1; then
        sudo docker-compose up -d --build --force-recreate
    else
        docker-compose up -d --build --force-recreate
    fi
else
    echo -e "${RED}❌ Docker tidak ditemukan di server ini!${NC}"
    exit 1
fi

# 6. Verify container status
echo -e "${BLUE}[5/5] 🩺 Verifying container status...${NC}"
sleep 3

if sudo docker ps --filter "name=tm_accountant_app" --format "{{.Status}}" | grep -q "Up"; then
    echo ""
    echo -e "${GREEN}================================================================${NC}"
    echo -e "${GREEN}  🎉 DEPLOYMENT BERHASIL! APLIKASI TELAH AKTIF KEMBALI 24/7 🎉   ${NC}"
    echo -e "${GREEN}================================================================${NC}"
    echo ""
    sudo docker ps --filter "name=tm_accountant"
    echo ""
    echo -e "${CYAN}📌 Tips berguna:${NC}"
    echo -e "  • Cek log realtime : ${YELLOW}sudo docker logs -f tm_accountant_app${NC}"
    echo -e "  • Cek log error    : ${YELLOW}tail -n 30 storage/logs/laravel.log${NC}"
    echo -e "  • Setup wizard CLI : ${YELLOW}sudo docker exec -it tm_accountant_app php artisan tm-accountant${NC}"
    echo ""
else
    echo -e "${RED}❌ Container belum berjalan dengan baik. Silakan cek log:${NC}"
    sudo docker logs --tail 30 tm_accountant_app
    exit 1
fi
