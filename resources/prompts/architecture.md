Overview:
This assistant is a Laravel 13 application using the Laravel AI SDK (laravel/ai) with a multi-provider architecture supporting automatic failover between AI providers.

Tech stack:
- Framework: Laravel 13 (PHP 8.x)
- AI integration: Laravel AI SDK (laravel/ai) — provides the Agent, Conversational, and Promptable abstractions
- Primary AI provider: Anthropic Claude (claude-sonnet-4-20250514)
- Fallback AI provider: OpenAI GPT-4
- Database: MySQL for conversation persistence
- SMS: Twilio SDK for inbound/outbound SMS via webhooks
- Email: Resend inbound email with Laravel Mail for replies
- Frontend: Blade template with Tailwind CSS, styled to match jamesgifford.com
- Fonts: Instrument Sans (body), JetBrains Mono (code/technical content)

Architecture:
The core of the application is an Agent class (app/Ai/Agents/ProfessionalAssistant.php) that implements the Laravel AI SDK's Agent and Conversational interfaces. The agent's instructions() method returns a comprehensive system prompt containing James's professional background, interview context, and behavioral guidelines. The agent uses the RemembersConversations concern to maintain conversation context across multiple messages.

Conversation flow:
1. An inbound message arrives via one of three channels: REST API (POST /api/chat), Twilio SMS webhook (POST /webhook/sms), or Resend email webhook (POST /webhook/resend/inbound)
2. The app identifies or creates a Conversation record in MySQL, keyed by session identifier — phone number for SMS, email address for email, or a client-provided token for the API
3. The full conversation history is loaded from the messages JSON column and passed to the ProfessionalAssistant agent along with the new message
4. The agent sends the conversation to the active AI provider with the system prompt
5. The AI response is appended to the conversation history and persisted
6. The response is returned through the same channel it arrived on — as JSON for the API, as TwiML for SMS, or as a reply email via Laravel Mail for email

Multi-provider failover:
The app maintains a cached health status for each configured AI provider, updated every five minutes by a scheduled artisan command. Before each request, the app checks the primary provider's cached status. If healthy, it attempts the primary provider first. If the primary fails at request time (timeout, rate limit, or error), it automatically retries with the fallback provider. If the primary provider's cached status is already "down," requests route directly to the fallback without attempting the primary, avoiding unnecessary latency. A health check endpoint (GET /api/health) exposes the current status, latency, and last-checked timestamp for each provider.

The failover architecture was a deliberate design decision born from direct experience — during development, James encountered extended provider outages that would have completely blocked the assistant. Rather than accepting a single point of failure, he built the assistant to be resilient to any individual provider going down.

SMS handling:
Inbound SMS arrives via Twilio's webhook with signature validation middleware to verify authenticity. Responses exceeding 1600 characters are split into multiple SMS segments. If both AI providers fail, a graceful fallback message directs the sender to contact James directly via email.

Email handling:
Inbound email arrives via Resend's inbound email webhook with Svix-based signature verification. The app strips HTML and quoted replies, processes the message body through the agent, and sends a reply via the Resend API through Laravel's mail system. Replies preserve threading headers (In-Reply-To, References), the original subject line, and include a professional footer. Auto-replies and bounce messages are detected and ignored to prevent infinite loops.

Web UI:
The chat interface is a single-page Blade template styled with Tailwind CSS using Instrument Sans typography, zinc color scale, dark/light mode with system preference detection. The UI includes a health status indicator, suggested prompt buttons for first-time visitors, markdown rendering for assistant responses, and a typing indicator during response generation.

Security considerations:
- Twilio webhook signature validation prevents spoofed SMS requests
- Resend webhook signature verification (via Svix) prevents spoofed email requests
- The system prompt contains only professional information — no personal data beyond professional contact details
- The AI is explicitly instructed not to fabricate information or share details beyond what is provided
- API keys are stored in environment variables, never in source code

Development approach:
The application was scaffolded using Claude Code, with James directing the architecture and making design decisions while using AI to accelerate implementation of boilerplate, webhook handling, and UI styling. This mirrors his broader approach to AI-assisted development — using AI as an accelerator for mechanical work while retaining engineering judgment over architecture and system design.

The Laravel AI SDK's provider abstraction was central to the architecture. It allowed multiple AI backends to be supported without duplicating conversation logic, routing, or response handling. Adding a new provider requires only configuration — no changes to the agent, conversation, or channel code.

Architecture optimization:
The detailed technical breakdown you're reading right now is loaded on demand via the Laravel AI SDK's tool system. Rather than including this entire section in every API call (which would add ~1,500 tokens to every message regardless of relevance), the system prompt contains a short instruction telling the agent to call the GetArchitectureDetails tool when a technical architecture question is detected. This reduces per-message token costs for the majority of conversations that don't involve architecture questions, while still providing the full breakdown when requested. This is a practical example of how to optimize token usage in production AI applications.
