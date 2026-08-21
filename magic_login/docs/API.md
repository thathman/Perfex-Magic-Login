# Magic Login API

The external API is disabled by default. An administrator must generate an API key in **Magic Login → API Key**, then enable **External Magic Login API**.

## Authentication

Send either:

```http
Authorization: Bearer ml_your_key
```

or:

```http
X-Magic-Login-Key: ml_your_key
```

The plaintext API key is shown once. Perfex stores only its SHA-256 hash.

All API endpoints are `POST` endpoints and accept JSON. They are intentionally excluded from Perfex browser CSRF validation because they use Bearer-style server authentication.

If Magic Login has a pending Perfex database migration, API routes return HTTP `503` until the migration is completed.

## Create a magic link

`POST /magic_login/api/create-link`

Request:

```json
{
  "contact_id": 42,
  "expiry_minutes": 30,
  "context_type": "invoice",
  "context_id": 123,
  "redirect_url": "clients"
}
```

Instead of `contact_id`, `email` may be supplied.

Successful response:

```json
{
  "ok": true,
  "login_url": "https://crm.example.com/magic_login/link/...",
  "expires_at": "2026-08-21 19:30:00"
}
```

## Request a WhatsApp OTP

`POST /magic_login/api/request-otp`

WhatsApp login must also be enabled in module settings.

Request:

```json
{
  "phone": "+2348012345678"
}
```

Accepted response:

```json
{
  "ok": true,
  "request_id": "64-character-request-token",
  "message": "If the number is registered, a code has been sent."
}
```

The response is intentionally generic to reduce phone-number enumeration.

## Verify a WhatsApp OTP

`POST /magic_login/api/verify-otp`

Request:

```json
{
  "request_id": "64-character-request-token",
  "code": "123456",
  "redirect_url": "clients",
  "expiry_minutes": 15
}
```

Successful verification returns a new one-time Perfex login link:

```json
{
  "ok": true,
  "login_url": "https://crm.example.com/magic_login/link/...",
  "expires_at": "2026-08-21 19:15:00"
}
```

This is preferable to attempting to share a PHP session between Perfex and an external WhatsApp/Baileys process.

## Revoke a token

`POST /magic_login/api/revoke`

Request:

```json
{
  "token_id": 91
}
```

Response:

```json
{
  "ok": true
}
```

Only active, unused and unrevoked tokens can be revoked.

## Common status codes

- `200` successful request.
- `202` OTP request accepted.
- `401` invalid API key or invalid/expired OTP.
- `404` API disabled, route/contact not available.
- `409` requested feature disabled or token state conflict.
- `422` missing required input.
- `429` OTP rate limit reached.
- `503` pending module migration, OTP delivery unavailable, or temporary service failure.

## Recommended integration pattern

For a Baileys service, keep Perfex as the authority for contact lookup, OTP generation/verification and final login-link creation. The WhatsApp service should only deliver the OTP or call the authenticated API; it should not create Perfex sessions itself.
