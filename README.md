# grzywniak.pl

Vite + React landing page with an AI Project Discovery view (`#discovery`). The production API is PHP to match the existing contact endpoint.

## AI discovery

For local XAMPP development, paste the key after `OPENAI_API_KEY=` in the Git-ignored root `.env` file. In production, set `OPENAI_API_KEY` and optionally `OPENAI_MODEL` in the host's server environment; never use `VITE_` for the key. Server variables take priority over the local file. `api/discovery-prompt.php` contains the agent instruction. Sessions and their structured project state are stored as JSON files in `api/storage/` (protected by `api/.htaccess`); use `DISCOVERY_MOCK=true` for local development without OpenAI calls. The Vite-only development server does not execute PHP: use an Apache/PHP virtual host for an end-to-end local conversation.

The endpoint uses Responses API structured JSON schema and validates status/flags before it persists a state update. When the visitor finishes the short discovery, `DISCOVERY_TO` receives a plain-text brief by email; set `CONTACT_FROM` to an address verified by the hosting provider. Run `npm run typecheck` and `npm run build` before release.

## MyDevil production layout

Deploy the built frontend to `domains/grzywniak.pl/public_nodejs/public`. Deploy the contents of the repository's `api` directory directly to `domains/api.grzywniak.pl/public_html`. On MyDevil, keep production secrets in `domains/api.grzywniak.pl/public_html/.env`; the deployed API `.htaccess` denies all HTTP access to that file. Do not place `.env` in the frontend directory. The production frontend calls `https://api.grzywniak.pl/contact.php` and `https://api.grzywniak.pl/discovery.php`.

## Admin panel

Set `ADMIN_USERNAME` and a long, unique `ADMIN_PASSWORD` in the server environment (or local `.env`), then open `/api/admin.php`. The panel is protected with HTTP Basic Authentication and shows sessions, briefs, complete conversation history, risk flags, token usage, cache tokens, and last-response latency.

The panel supports archiving, restoring, and permanent deletion of local conversation files. `DISCOVERY_RETENTION_DAYS=90` automatically removes non-archived conversations older than 90 days whenever the panel is opened; set it to `0` to disable automatic cleanup.
