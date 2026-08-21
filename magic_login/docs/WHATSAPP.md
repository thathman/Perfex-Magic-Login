# WhatsApp / Baileys Integration

Magic Login can add **Continue with WhatsApp** to the Perfex client login page. Perfex remains responsible for OTP generation, validation, rate limiting and client authentication; the configured WhatsApp service is only the outbound message transport.

## Setup

In **Magic Login → WhatsApp Login**:

1. Enable **Continue with WhatsApp**.
2. Enter an HTTPS API URL for the WhatsApp/Baileys bridge.
3. Optionally enter a Bearer token for that service.
4. Configure the OTP message.
5. Choose OTP validity and maximum attempts.

The default message supports:

- `{company}`
- `{code}`
- `{minutes}`

## Default outbound contract

Magic Login sends an HTTPS JSON `POST` to the configured endpoint:

```json
{
  "to": "+2348012345678",
  "message": "Your Company login code is 123456. It expires in 5 minutes."
}
```

If a WhatsApp API token is configured, the default request also includes:

```http
Authorization: Bearer YOUR_TOKEN
```

For compatibility with gateways that use an API-key header, the same configured
token is also sent as `X-API-Key: YOUR_TOKEN`.

Any HTTP `2xx` response is treated as successful by default.

## Adapting an existing Baileys API

You do not need to modify the Magic Login source if your existing Baileys endpoint expects a different payload or header format. Perfex filters are provided for this purpose.

### Change the payload

Filter: `magic_login_whatsapp_payload`

The filter receives the current payload plus a context containing the normalized phone number and Perfex contact.

Example concept:

```php
hooks()->add_filter('magic_login_whatsapp_payload', function ($payload, $context) {
    return [
        'number' => ltrim($context['phone'], '+'),
        'text'   => $payload['message'],
    ];
}, 10, 2);
```

### Change headers

Filter: `magic_login_whatsapp_headers`

Use this when your bridge expects an API key header, instance identifier or another authentication scheme.

### Customize success detection

Filter: `magic_login_whatsapp_delivery_success`

The second argument contains HTTP status, raw response and request context. This is useful if your Baileys server returns HTTP `200` even when its JSON body reports delivery failure.

## OTP security behavior

- Phone numbers are normalized toward E.164 (`+countrycode...`).
- OTPs are six digits.
- Codes are stored with `password_hash()`, not plaintext.
- Requests expire after the configured number of minutes.
- Verification increments an attempt counter atomically.
- A successful OTP is single-use.
- New OTP requests invalidate previous unused OTPs for that contact.
- IP requests are limited to fewer than 10 per 15 minutes.
- Contact requests are limited to fewer than 5 per 15 minutes.
- Unknown numbers receive the same outward response pattern as registered numbers to reduce account enumeration.

## Contact phone formatting

For reliable lookup, save Perfex contact phone numbers in full international format, for example:

```text
+2348012345678
+447700900123
+12025550123
```

The module also attempts common forms without `+` and with a `00` prefix, and has a formatting fallback for spaces/dashes/brackets.

## Browser login flow

```text
Perfex login page
      ↓
Continue with WhatsApp
      ↓
Enter phone number
      ↓
Perfex generates OTP
      ↓
Baileys delivers OTP
      ↓
Contact enters code
      ↓
Perfex verifies code
      ↓
Perfex client session
```

When a Magic Login database migration is pending, the WhatsApp button is hidden and the WhatsApp endpoints return service unavailable until the admin completes the Perfex module migration.
