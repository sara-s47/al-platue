# Auth API change: password is now required on register

**Audience:** frontend  
**Breaking change:** yes  
**Endpoint:** `POST /api/v1/auth/register`

## What changed

Registration no longer accepts an account without a password.

Previously, `password` was optional. A user could register with name + phone only. That account could not log in with `POST /api/v1/auth/login`, because login requires `phone` + `password`.

Password is now **required** on register, and it must be confirmed.

## Frontend action required

Update the register form and API payload:

1. Add a password field.
2. Add a confirm password field.
3. Send both `password` and `password_confirmation`.
4. Block submit unless the password is at least 8 characters and both fields match.

Requests that omit `password` or `password_confirmation` will fail validation (`422`).

## Register

`POST /api/v1/auth/register`

### Request body

```json
{
  "name": "John Doe",
  "phone": "+201234567890",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

| Field | Required | Rules |
| --- | --- | --- |
| `name` | yes | string, max 255 |
| `phone` | yes | string, max 20 |
| `email` | no | valid email, max 255 |
| `password` | **yes** | string, min 8 |
| `password_confirmation` | **yes** | must match `password` |

`password_confirmation` is a Laravel confirmation field. It is used only for validation and is not stored.

### Success (`201`)

The account is created as **active**. No auth token is returned yet. The user can log in immediately (OTP is temporarily skipped).

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

### Validation error (`422`)

Example when password is missing:

```json
{
  "success": false,
  "message": "The password field is required.",
  "data": {
    "password": ["The password field is required."]
  },
  "meta": []
}
```

Example when confirmation does not match:

```json
{
  "success": false,
  "message": "The password field confirmation does not match.",
  "data": {
    "password": ["The password field confirmation does not match."]
  },
  "meta": []
}
```

Show `data.password` (and other field errors) under the matching inputs.

> **Temporary:** phone OTP after register is currently skipped.  
> See [`frontend-otp-temporarily-disabled.md`](./frontend-otp-temporarily-disabled.md).  
> After register, the user is `active` and can log in with phone + password immediately.

## Recommended auth flow (temporary)

```text
Register (name, phone, password, password_confirmation)
        ↓
Login with phone + password
```

Do not send the user to the OTP screen after register.

### 1. Register

`POST /api/v1/auth/register`

Creates the user as **`active`**. No OTP is sent. No token is returned yet.

### 2. Login

`POST /api/v1/auth/login`

```json
{
  "phone": "+201234567890",
  "password": "password123"
}
```

This works immediately after register. Store `data.token`.

If the password is wrong, login returns `401` with `invalid_credentials`.

`POST /api/v1/auth/verify-otp` still exists but is **not required** right now.

## OTP login is unchanged

`POST /api/v1/auth/login-otp` still exists as an alternative login:

```json
{
  "phone": "+201234567890",
  "code": "123456"
}
```

This does **not** replace the password on register. Every new account must still set a password so phone + password login works.

## Password reset (already required)

Forgot / reset password is unchanged. Reset already requires confirmation:

1. `POST /api/v1/auth/forgot-password` with `{ "phone": "+201234567890" }`
2. `POST /api/v1/auth/reset-password`

```json
{
  "phone": "+201234567890",
  "code": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

## Profile password update

`PUT /api/v1/customer/profile` still treats password as optional. If the user changes it, send both:

```json
{
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

## UI checklist

- [ ] Register screen has password + confirm password.
- [ ] Client-side validation: min 8 characters, both fields match.
- [ ] Register request includes `password` and `password_confirmation`.
- [ ] After register, skip OTP and go to login (or call login automatically).
- [ ] Login screen uses `phone` + `password`.
- [ ] Save `data.token` from login.
- [ ] Handle `422` field errors from register.
- [ ] Handle `401 invalid_credentials` on login.
