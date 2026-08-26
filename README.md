# 💼 TM Accountant

> **Modern, Self-Hosted Personal Accounting & Telegram AI Bookkeeper**  
> Powered by Laravel 11, Filament v4, WebAuthn Passkeys, Multi-Provider AI (Ollama / DeepSeek / OpenAI / Gemini), and Local Vision OCR.

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel 11](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Filament v4](https://img.shields.io/badge/Filament-v4-F59E0B?logo=filament&logoColor=white)](https://filamentphp.com)
[![Pest](https://img.shields.io/badge/Tests-Pest%20PHP-black?logo=pest)](https://pestphp.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

---

## ✨ Key Features

- 🏛️ **True Double-Entry General Ledger**: GAAP & IFRS compliant Chart of Accounts (COA) with balanced debits & credits, real-time trial balance, balance sheet, income statement (P&L), and cash flow statements.
- 🤖 **Smart Telegram AI Bookkeeper**: Natural language accounting parser with automatic expense/income/transfer classification, conversational guardrails, and instant undo actions.
- 🌐 **Multi-Provider AI Engine**: Seamlessly switch between **100% Local Offline LLMs (Ollama / LM Studio / vLLM)** and cloud AI providers (**DeepSeek, Google Gemini, OpenAI, Groq, OpenRouter**).
- 🧾 **Hybrid Vision OCR**: Transcribes receipts, store invoices, and bank transfer screenshots using Google Gemini Vision (Free Tier recommended) or your primary multimodal AI.
- 📸 **Native PHP GD Image Compression**: Automatically compresses high-resolution smartphone receipt photos by **> 95%** (~60–120 KB), saving disk space while keeping text crystal clear.
- 🔐 **Password-Only Login & WebAuthn Biometrics**: Fast authentication with identity detection and biometric Passkeys (Windows Hello, Touch ID, Face ID, Fingerprint).
- 👛 **Wallet Setup Wizard**: 3-step onboarding wizard to register cash wallets, bank accounts, e-wallets, and opening balances with negative balance alerts.

---

## 🚀 Quick Start (1-Minute Interactive CLI Setup)

Clone the repository and run the interactive installation wizard:

```bash
# 1. Clone repository
git clone https://github.com/your-username/tm-accountant.git
cd tm-accountant

# 2. Install PHP & Node dependencies
composer install
npm install && npm run build

# 3. Run the Interactive Installation Wizard
php artisan tmaccountant
```

The interactive wizard (`php artisan tmaccountant` or `php artisan tmaccountant:install`) will automatically:
1. Generate your `APP_KEY` and create storage symlinks.
2. Run database migrations and seed default GAAP/IFRS Chart of Accounts.
3. Configure your Admin credentials (Name, Email, Password).
4. Configure Telegram Bot Token & Whitelisted User IDs.
5. Configure your preferred AI Provider (Ollama, DeepSeek, OpenAI, Gemini, etc.) and OCR settings.

---

## 🤖 Supported AI Providers

Configure your preferred AI provider in `.env` or during the CLI setup:

### 1. 🦙 Ollama (100% Local, Offline & Free)
Run open-source models completely offline on your own machine:
```env
AI_PROVIDER=ollama
OLLAMA_BASE_URL=http://localhost:11434/v1
OLLAMA_MODEL=llama3.3
# or qwen2.5 / mistral / deepseek-r1-distill
```

### 2. 🧠 DeepSeek AI (Recommended Cloud - Ultra Economical)
```env
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-deepseek-api-key
DEEPSEEK_MODEL=deepseek-chat
```

### 3. ✨ Google Gemini API (Multimodal & Free Tier OCR)
```env
AI_PROVIDER=gemini
GEMINI_API_KEY=AIzaSy-your-gemini-key
GEMINI_MODEL=gemini-3.7-flash
```

### 4. 🤖 OpenAI
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-proj-your-openai-key
OPENAI_MODEL=gpt-4o-mini
```

### 5. ⚡ Groq / OpenRouter / LM Studio
```env
# Groq
AI_PROVIDER=groq
GROQ_API_KEY=gsk_your-groq-key
GROQ_MODEL=llama-3.3-70b-versatile

# OpenRouter
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-or-your-openrouter-key
OPENROUTER_MODEL=anthropic/claude-3.5-sonnet
```

---

## 📸 Vision OCR Strategy for Receipts

TM Accountant features a **Hybrid Vision OCR** pipeline:

```env
# Recommended: Economical 2-stage OCR pipeline (Gemini Free Tier extracts text, primary AI books entry)
AI_OCR_MODE=gemini
GEMINI_API_KEY=your-gemini-free-tier-api-key

# Single-stage: If your primary AI model already supports Vision (e.g. GPT-4o, Gemini 3.7 Flash)
AI_OCR_MODE=auto

# Text-only (disabled image processing)
AI_OCR_MODE=disabled
```

---

## 📱 Running the Telegram Bot

### Development (Long Polling)
In local development, start the long polling worker in your terminal:
```bash
php artisan telegram:poll
```

### Production (Webhook)
For production deployments with HTTPS, set your webhook URL:
```bash
curl -F "url=https://your-domain.com/telegram/webhook" https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook
```

### Example Telegram Interactions:
- **Expense**: *"bought lunch padang 32k via bca"*
- **Income**: *"salary 15m received in mandiri"*
- **Transfer**: *"transferred 200k from bca to gopay"*
- **Send Photo**: Attach any receipt photo or bank transfer screenshot $\rightarrow$ Auto-transcribed and journaled!
- **Summary**: *"weekly financial summary"* or `/saldo`

---

## 🧪 Testing

TM Accountant includes a full test suite built with **Pest PHP**:

```bash
# Run all automated tests
php artisan test

# Run specific feature tests
php artisan test --filter=TelegramBotTest
php artisan test --filter=ReceiptAttachmentTest
```

---

## 🎨 Code Style

Format the codebase using Laravel Pint:
```bash
vendor/bin/pint --format agent
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).
