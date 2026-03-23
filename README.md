# AI Professional Assistant

An AI-powered professional assistant built with Laravel 13 and the Laravel AI SDK. It represents James Gifford in conversations with potential employers, recruiters, and hiring managers through REST API, SMS, and email interfaces.

## Overview

This application uses multiple AI providers (Anthropic Claude primary, OpenAI GPT-4 fallback) with automatic failover and health monitoring. Conversation history is persisted per session, enabling multi-turn conversations across all channels.

### Interfaces

- **REST API** — `POST /api/chat` for direct integration and testing
- **SMS** — Twilio webhook at `POST /webhook/sms` for text message conversations
- **Email** — Resend webhook at `POST /webhook/resend/inbound` for email conversations

### Channel Flagging

Each conversation and individual message is tagged with a `channel` value to distinguish the origin:

- `'web'` — Messages from the browser-based chat UI
- `'api'` — Messages from the REST API
- `'sms'` — Messages from Twilio SMS
- `'email'` — Messages from Resend inbound email

This allows conversations that span multiple channels to track the origin of each individual message.

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

# Resend (required for email)
RESEND_API_KEY=re_...
RESEND_INBOUND_ADDRESS=prompt@jamesgifford.ai
RESEND_WEBHOOK_SECRET=whsec_...

# Mail (uses Resend as the transport)
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=prompt@jamesgifford.ai
MAIL_FROM_NAME="James Gifford's AI Assistant"
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

## Configuring Resend Inbound Email

### Resend Dashboard Setup

1. Sign up at [resend.com](https://resend.com) and get your API key
2. Go to **Domains** and add your domain (e.g., `jamesgifford.ai`)
3. Configure the required DNS records:
   - MX record pointing to Resend's inbound servers
   - SPF, DKIM, and DMARC records for outbound delivery
4. Go to **Webhooks** and create a new webhook:
   - URL: `https://<your-domain>/webhook/resend/inbound`
   - Events: Select `email.received`
   - Copy the signing secret to `RESEND_WEBHOOK_SECRET` in `.env`

### Environment Variables

```env
RESEND_API_KEY=re_...              # Your Resend API key
RESEND_INBOUND_ADDRESS=prompt@jamesgifford.ai  # The email address receiving inbound mail
RESEND_WEBHOOK_SECRET=whsec_...    # Webhook signing secret from Resend dashboard
MAIL_MAILER=resend                 # Use Resend as the mail transport
MAIL_FROM_ADDRESS=prompt@jamesgifford.ai
MAIL_FROM_NAME="James Gifford's AI Assistant"
```

### Testing Locally with ngrok

```bash
# Start your server
php artisan serve

# In another terminal, start ngrok
ngrok http 8000

# In Resend dashboard → Webhooks → Add webhook:
# URL: https://<ngrok-url>/webhook/resend/inbound
# Events: email.received

# Send an email to your Resend inbound address
# Verify the AI reply arrives in your inbox and threads correctly
```

### Verifying Email Integration

```bash
# Check the webhook route exists
php artisan route:list --path=webhook/resend

# Verify a conversation was created with channel='email'
php artisan tinker --execute="echo App\Models\Conversation::where('channel', 'email')->count();"

# Verify messages have channel='email'
php artisan tinker --execute="echo App\Models\Conversation::where('channel', 'email')->first()?->messages()->pluck('channel');"
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
│   │       └── EmailController.php   # Resend email webhook
│   ├── Middleware/
│   │   ├── VerifyTwilioSignature.php # Twilio webhook auth
│   │   └── VerifyResendSignature.php # Resend webhook auth (Svix)
│   └── Requests/Api/
│       └── ChatRequest.php           # Chat validation
├── Mail/ProfessionalAssistantReply.php     # Email reply mailable
├── Models/Conversation.php           # Conversation persistence
└── Services/AiProviderService.php    # Provider failover logic
```
