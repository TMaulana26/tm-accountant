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

# 2. Configure Git safe directory and prepare runtime directories (no aggressive chown)
echo -e "${BLUE}[2/5] 🔑 Menyiapkan folder runtime & izin git...${NC}"
git config --global --add safe.directory "$SCRIPT_DIR" 2>/dev/null || true
mkdir -p "$SCRIPT_DIR/database" \
         "$SCRIPT_DIR/storage/app" \
         "$SCRIPT_DIR/storage/logs" \
         "$SCRIPT_DIR/storage/framework/cache" \
         "$SCRIPT_DIR/storage/framework/sessions" \
         "$SCRIPT_DIR/storage/framework/views"
chmod -R 775 "$SCRIPT_DIR/storage" "$SCRIPT_DIR/database" 2>/dev/null || true

# 3. Pull latest code from Git (dengan fallback aman jika user/agent tidak punya SSH key)
if [ "${SKIP_GIT_PULL:-false}" = "true" ] || [ "$1" = "--no-pull" ] || [ "$2" = "--no-pull" ]; then
    echo -e "${YELLOW}[3/5] ⏩ Melewati git pull (flag --no-pull atau SKIP_GIT_PULL=true aktif)...${NC}"
else
    echo -e "${BLUE}[3/5] 📥 Mengambil pembaruan kode dari Git (origin main)...${NC}"
    if git pull origin main; then
        echo -e "${GREEN}   ✓ Kode terbaru berhasil di-pull.${NC}"
    else
        echo -e "${YELLOW}⚠️  Gagal git pull (mungkin SSH key / permission belum terdaftar untuk user: $(whoami)).${NC}"
        echo -e "${YELLOW}   Melanjutkan deployment dengan kode lokal yang sudah ada di server...${NC}"
    fi
fi

# 4. Check if .env exists, auto-generate APP_KEY if missing
echo -e "${BLUE}[4/5] ⚙️  Memeriksa konfigurasi environment (.env)...${NC}"
if [ ! -f "$SCRIPT_DIR/.env" ]; then
    echo -e "${YELLOW}⚠️  File .env tidak ditemukan! Menyalin dari .env.example...${NC}"
    cp "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env"
    if command -v openssl >/dev/null 2>&1; then
        GEN_KEY="base64:$(openssl rand -base64 32)"
        sed -i.bak "s|^APP_KEY=.*|APP_KEY=${GEN_KEY}|" "$SCRIPT_DIR/.env" && rm -f "$SCRIPT_DIR/.env.bak"
        echo -e "${GREEN}🔑 APP_KEY otomatis digenerate untuk file .env baru.${NC}"
    fi
    echo -e "${YELLOW}👉 Harap sesuaikan konfigurasi di .env jika diperlukan!${NC}"
fi

# 5. Rebuild and restart Docker containers
echo -e "${BLUE}[5/5] 🐳 Rebuilding and restarting Docker containers...${NC}"
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

# 6. Verify container health status (anti false-positive, periksa status sejati)
echo -e "${BLUE}🩺 Memverifikasi status kesehatan container...${NC}"
CONTAINER_NAME="tm_accountant_app"
MAX_RETRIES=15
RETRY=0
IS_HEALTHY=false

while [ $RETRY -lt $MAX_RETRIES ]; do
    STATE=$(sudo docker inspect --format='{{.State.Status}}' "$CONTAINER_NAME" 2>/dev/null || echo "not_found")
    HEALTH=$(sudo docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$CONTAINER_NAME" 2>/dev/null || echo "none")

    if [ "$STATE" = "running" ]; then
        if [ "$HEALTH" = "healthy" ]; then
            IS_HEALTHY=true
            break
        elif [ "$HEALTH" = "none" ]; then
            IS_HEALTHY=true
            break
        elif [ "$HEALTH" = "unhealthy" ]; then
            echo -e "   ❌ Container dilaporkan: UNHEALTHY"
            IS_HEALTHY=false
            break
        fi
    elif [ "$STATE" = "exited" ] || [ "$STATE" = "dead" ]; then
        echo -e "   ❌ Container berhenti/mati (status: $STATE)"
        IS_HEALTHY=false
        break
    fi

    echo -e "   ⏳ Menunggu container siap... (status: $STATE, health: $HEALTH) [$((RETRY+1))/$MAX_RETRIES]"
    sleep 2
    RETRY=$((RETRY+1))
done

if [ "$IS_HEALTHY" = "true" ]; then
    echo ""
    echo -e "${GREEN}================================================================${NC}"
    echo -e "${GREEN}  🎉 DEPLOYMENT BERHASIL! TM ACCOUNTANT AKTIF & SEHAT 24/7 🎉    ${NC}"
    echo -e "${GREEN}================================================================${NC}"
    echo ""
    sudo docker ps --filter "name=$CONTAINER_NAME"
    echo ""
    echo -e "${CYAN}📌 Tips berguna:${NC}"
    echo -e "  • Cek log realtime : ${YELLOW}sudo docker logs -f $CONTAINER_NAME${NC}"
    echo -e "  • Cek log error    : ${YELLOW}tail -n 30 storage/logs/laravel.log${NC}"
    echo -e "  • Setup wizard CLI : ${YELLOW}sudo docker exec -it $CONTAINER_NAME php artisan tm-accountant${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}================================================================${NC}"
    echo -e "${RED}  ❌ DEPLOYMENT GAGAL: Container tidak sehat atau gagal boot!   ${NC}"
    echo -e "${RED}================================================================${NC}"
    echo -e "${YELLOW}Log terakhir container (${CONTAINER_NAME}):${NC}"
    sudo docker logs --tail 40 "$CONTAINER_NAME" 2>/dev/null || true
    exit 1
fi
