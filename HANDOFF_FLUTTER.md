# Handoff — Laravel API for Flutter

This document explains how the Flutter team should integrate with the Laravel API in this repository.

## Base
- Repository: https://github.com/<your-org>/<repo>
- Branch for handoff: `api-handoff-to-flutter` (or `develop` if agreed)
- Base API URL (local): `http://localhost:8000/api`

## Auth
- Authentication: Laravel Sanctum tokens
- Login endpoint: `POST /api/login`
  - Body: `{ "email": "...", "password": "..." }`
  - Response: `{ data: { token: "<token>", token_type: "Bearer", user: { id, name, email, role } }}`
- Add token to all protected requests as header:
  - `Authorization: Bearer <token>`

## Account Activation
- New registrations are activated and email-verified automatically; `POST /api/register` returns a Sanctum token immediately.
- Existing accounts created before this behavior changed may still be unverified and receive 403 on login.
- The email verification and resend endpoints remain available for those legacy accounts.

## Password Reset
- Password reset is email-only: call `POST /api/forgot-password` with `{ "email": "..." }`.
- Do not implement a phone-number or OTP reset flow for this API version.

## Provider launch policy
- After `POST /api/provider/onboarding`, the provider registration is approved immediately in this release.
- `GET /api/provider/verification` therefore returns `approved` after a successful onboarding submission.
- Every first call to `POST /api/provider/subscription` creates one free month, even when the app submits `monthly` or `yearly` as the selected plan.
- Paid plans and bank-transfer proof are not active in this release. A later subscription attempt returns `409` until the paid-subscription update is released.

## Customer booking, manual payment and notifications
- Read `CUSTOMER_BOOKING_HANDOFF.md` before integrating customer booking screens.
- Customer booking payment is an external transfer directly to the provider. The app never initiates a bank transfer.
- The customer submits a payment proof; the provider confirms or rejects it in the API, and both sides receive an in-app notification.

## Provider dashboard, services and availability
- Read `PROVIDER_DASHBOARD_HANDOFF.md` before integrating provider screens.
- `GET /api/provider/dashboard` powers the provider home, trial banner, counters and latest orders.
- Services and packages are both managed through `/api/provider/services`; use `service_type=service|package`.
- The provider can hide a service without deleting it and block dates in its availability calendar. Blocked dates are unavailable to the customer booking calendar.

## Endpoints (short list)
- `GET /api/cities` — list cities
- `POST /api/register` — register and activate a new user, returning a token
- `POST /api/login` — login
- `POST /api/logout` — logout (auth required)
- `POST /api/forgot-password` — send reset link
- `POST /api/reset-password` — reset password
- `GET /api/profile` — get profile (auth required)
- `POST /api/profile/update` — update profile (auth required)
- `DELETE /api/account` — delete account (auth required)
- `POST /api/change-password` — change password (auth required)
- `API Resource /api/locations` — locations CRUD (auth required)

## Common responses
- Success example (registration and login):

```json
{
  "icon":"success",
  "title":"Login successful",
  "data":{
    "token":"<token>",
    "token_type":"Bearer",
    "user":{ "id":1, "name":"...", "email":"...", "role":"customer" }
  }
}
```

- Error (legacy account email not verified): HTTP 403
```json
{ "icon":"error", "title":"Please verify your email before logging in." }
```

- Validation error: HTTP 422
```json
{ "icon":"error", "title":"<first error>", "errors": { "email": ["..."], ... } }
```

## Postman
- Postman collection: `postman_collection.json` (not included) — ask backend dev to export if needed.

## Notes for Flutter
- Use `token_type` + `token` for Authorization header.
- Treat 401 as authentication failure; 403 may indicate email not verified.
- For files (avatar) upload, use multipart/form-data.

---
Contact backend dev: @your-name
