# Frontend note: phone OTP verification is temporarily disabled

**Audience:** frontend  
**Temporary:** yes — restore the OTP flow later  
**Related:** register / login only. Forgot-password OTP is unchanged.

## What changed

Phone OTP verification after register is **temporarily skipped**.

A new user can log in with `phone` + `password` immediately. Do **not** send them to the OTP screen after register.

`verify-otp` and `login-otp` endpoints still exist, but they are **not required** for the current flow.

## Current flow

```text
Register (name, phone, password, password_confirmation)
        ↓
Login with phone + password
        ↓
Use the returned token
```

## Register

`POST /api/v1/auth/register`

Request body is unchanged:

```json
{
  "name": "John Doe",
  "phone": "+201234567890",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Success (`201`): the user is created as **`active`**. No token is returned. No OTP is sent.

```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+201234567890",
    "status": "active",
    "last_login_at": null,
    "created_at": "2026-09-14T08:00:00.000000Z"
  },
  "meta": []
}
```

After this, go to login (or call login automatically). Do not wait for OTP.

## Login

`POST /api/v1/auth/login`

```json
{
  "phone": "+201234567890",
  "password": "password123"
}
```

This works right after register. You should **not** get `403 account_inactive` for an unverified phone.

Wrong password still returns `401 invalid_credentials`.  
Blocked accounts still return `403`.

On success, store `data.token` as before.

## What to skip in the UI

- [ ] Do **not** open the OTP / verify-phone screen after register.
- [ ] Do **not** call `POST /api/v1/auth/verify-otp` in the happy path.
- [ ] After register, send the user to login, or call login with the same phone + password.
- [ ] Login screen: `phone` + `password` only.

## Unchanged

| Endpoint | Status |
| --- | --- |
| `POST /api/v1/auth/login-otp` | Still available, optional |
| `POST /api/v1/auth/verify-otp` | Still available, **not required** |
| `POST /api/v1/auth/forgot-password` | Still sends OTP |
| `POST /api/v1/auth/reset-password` | Still requires OTP + `password_confirmation` |

Password on register is still required (`min 8` + `password_confirmation`).

## When OTP is turned back on

Backend will restore:

1. New users created as `inactive`
2. OTP sent on register
3. `verify-otp` required before login
4. Login returns `403 account_inactive` until the phone is verified

At that point, show the OTP screen again after register and wait for a token from `verify-otp`.
