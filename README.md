# AI Professional Assistant

An AI-powered professional assistant built with Laravel 13 and the Laravel AI SDK. It represents James Gifford in conversations with potential employers, recruiters, and hiring managers through REST API, SMS, and email interfaces.

## Overview

This application uses multiple AI providers (Anthropic Claude primary, OpenAI GPT-4 fallback) with automatic failover and health monitoring. Conversation history is persisted per session, enabling multi-turn conversations across all channels.

### Interfaces

- **REST API** — `POST /api/chat` for direct integration and testing
- **SMS** — Twilio webhook at `POST /webhook/sms` for text message conversations
- **Email** — Mailgun webhook at `POST /webhook/email` for email conversations

## Requirements

- PHP 8.3+
- Composer
- SQLite (default) or MySQL
- Node.js & npm (for frontend assets)

## Setup

### 1. Clone and Install

```bash
git clone <repo-url>
cd professional-assistant
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Environment Variables

Edit `.env` with your credentials:

```env
# AI Providers (required — at least one)
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
AI_PRIMARY_PROVIDER=anthropic
AI_FALLBACK_PROVIDER=openai

# Twilio (required for SMS)
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_PHONE_NUMBER=+15551234567

# Mailgun (required for email)
MAILGUN_INBOUND_ADDRESS=hire-james@yourdomain.com
MAILGUN_WEBHOOK_SIGNING_KEY=...
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Start the Server

```bash
php artisan serve
```

## Testing the REST API

### New conversation

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -d '{"session_key": "test-1", "message": "Tell me about James'\''s experience"}'
```

### Conversation continuity

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -d '{"session_key": "test-1", "message": "What are his salary expectations?"}'
```

### Technical architecture question

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -d '{"session_key": "test-1", "message": "How was this assistant built?"}'
```

### Privacy boundary

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -d '{"session_key": "test-2", "message": "What is James'\''s home address?"}'
```

### Quick test endpoint

```bash
curl http://localhost:8000/api/chat/test
```

### Health check

```bash
curl http://localhost:8000/api/health
```

### Health check command

```bash
php artisan ai:health
```

## Testing SMS Locally

1. Install ngrok: `brew install ngrok`
2. Start your server: `php artisan serve`
3. Start ngrok: `ngrok http 8000`
4. Configure Twilio webhook URL to `https://<ngrok-url>/webhook/sms`
5. Text your Twilio phone number from your phone

Webhook signature validation is bypassed in `local` and `testing` environments.

## Configuring Mailgun Inbound Email

1. Set up a Mailgun receiving route pointing to `https://<your-domain>/webhook/email`
2. Configure the route to forward to your webhook URL with `store()` and `notify()` actions
3. Set `MAILGUN_INBOUND_ADDRESS` and `MAILGUN_WEBHOOK_SIGNING_KEY` in `.env`

For local testing with ngrok:
```bash
ngrok http 8000
# Configure Mailgun inbound route to https://<ngrok-url>/webhook/email
# Send an email to hire-james@yourdomain.com
```

## Health Check & Failover System

The application includes a health monitoring system for AI providers:

- **Scheduled health checks** run every 5 minutes via `php artisan ai:health`
- Each check sends a test prompt to each provider and measures response time
- Results are cached with a 5-minute TTL
- If the primary provider is marked as "down", requests route directly to the fallback
- The `GET /api/health` endpoint returns current provider status

### Health response format

```json
{
  "providers": {
    "anthropic": {
      "status": "up",
      "latency_ms": 823,
      "last_checked": "2026-03-19T10:30:00Z"
    },
    "openai": {
      "status": "up",
      "latency_ms": 641,
      "last_checked": "2026-03-19T10:30:00Z"
    }
  },
  "active_provider": "anthropic"
}
```

### Enable the scheduler

Add this to your crontab for automatic health checks:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Adding Additional AI Providers

1. Add the provider's API key to `.env`:
   ```env
   GEMINI_API_KEY=...
   ```

2. The provider is already configured in `config/ai.php` (the Laravel AI SDK ships with support for Anthropic, OpenAI, Gemini, Groq, Mistral, DeepSeek, and more).

3. To use it as the primary or fallback provider:
   ```env
   AI_PRIMARY_PROVIDER=gemini
   AI_FALLBACK_PROVIDER=anthropic
   ```

4. Update the health check model mapping in `app/Services/AiProviderService.php` if needed.

## Running Tests

```bash
# All tests
php artisan test --compact

# Specific test file
php artisan test --compact tests/Feature/Api/ChatTest.php

# Filter by name
php artisan test --compact --filter="handles an incoming SMS"
```

## Architecture

```
app/
├── Ai/Agents/ProfessionalAssistant.php    # AI agent with system prompt
├── Console/Commands/AiHealthCheck.php # Health check artisan command
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── ChatController.php    # REST API endpoint
│   │   │   └── HealthController.php  # Health check endpoint
│   │   └── Webhook/
│   │       ├── SmsController.php     # Twilio SMS webhook
│   │       └── EmailController.php   # Mailgun email webhook
│   ├── Middleware/
│   │   ├── VerifyTwilioSignature.php # Twilio webhook auth
│   │   └── VerifyMailgunSignature.php # Mailgun webhook auth
│   └── Requests/Api/
│       └── ChatRequest.php           # Chat validation
├── Mail/ProfessionalAssistantReply.php     # Email reply mailable
├── Models/Conversation.php           # Conversation persistence
└── Services/AiProviderService.php    # Provider failover logic
```
