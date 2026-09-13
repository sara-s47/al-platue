# Admin Notifications — Frontend Guide

## Overview

Admin can send push notifications with:

1. A **category** (fixed list from the API)
2. An **audience** preset (all / with bookings / without bookings / custom IDs)

Recipients are always **active customers** (`role: customer`, `status: active`). Staff/admin users are never targeted by presets.

---

## Endpoints

Base path: `/api/v1/admin/notifications`  
Permission: `notifications.manage`

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/options` | Category + audience dropdowns |
| `POST` | `/preview-audience` | Estimated recipient count before send |
| `POST` | `/` | Send notification |
| `GET` | `/` | Sent notification history (paginated) |

---

## 1) Load dropdown options

`GET /api/v1/admin/notifications/options`

```json
{
  "success": true,
  "data": {
    "categories": [
      { "value": "general", "label": "General" },
      { "value": "promo", "label": "Promo" },
      { "value": "loyalty", "label": "Loyalty" },
      { "value": "booking", "label": "Booking" },
      { "value": "system", "label": "System" },
      { "value": "announcement", "label": "Announcement" }
    ],
    "audiences": [
      { "value": "all", "label": "All customers" },
      { "value": "with_bookings", "label": "Customers with previous bookings" },
      { "value": "without_bookings", "label": "Customers with no bookings" },
      { "value": "custom", "label": "Custom user list" }
    ]
  }
}
```

Use `value` in the send payload; show `label` in the UI.

---

## 2) Preview audience count

`POST /api/v1/admin/notifications/preview-audience`

```json
{
  "audience": "with_bookings",
  "user_ids": []
}
```

For `custom`, send selected IDs:

```json
{
  "audience": "custom",
  "user_ids": [3, 5, 9]
}
```

Response:

```json
{
  "success": true,
  "data": {
    "count": 42,
    "audience": "with_bookings"
  }
}
```

---

## 3) Send notification

`POST /api/v1/admin/notifications`

### Select all customers

```json
{
  "title": "Weekend offer",
  "body": "Book this weekend and get 10% off.",
  "category": "promo",
  "deep_link": null,
  "audience": "all"
}
```

### Customers who used services before (have bookings)

```json
{
  "title": "Thanks for booking with us",
  "body": "Come back this month for a loyalty bonus.",
  "category": "loyalty",
  "audience": "with_bookings"
}
```

### Customers who never booked

```json
{
  "title": "Your first session awaits",
  "body": "Book your first studio session today.",
  "category": "announcement",
  "audience": "without_bookings"
}
```

### Custom user list

```json
{
  "title": "Personal note",
  "body": "We reserved a slot for you.",
  "category": "general",
  "audience": "custom",
  "user_ids": [3, 8, 12]
}
```

### Field rules

| Field | Required | Notes |
|---|---|---|
| `title` | yes | max 255 |
| `body` | yes | |
| `category` | yes | one of options from `/options` |
| `audience` | yes | `all` \| `with_bookings` \| `without_bookings` \| `custom` |
| `user_ids` | only if `audience=custom` | min 1; must be existing user IDs (active customers only are sent) |
| `deep_link` | no | optional app deep link |

Backward compatible: sending `type` instead of `category` still works; the API maps it to `category`.

---

## UI mapping

| UI control | API |
|---|---|
| Category dropdown | `category` from `GET .../options` |
| Select all | `audience: "all"` |
| Used services before | `audience: "with_bookings"` |
| Never booked / no subscriptions | `audience: "without_bookings"` |
| Manual multi-select users | `audience: "custom"` + `user_ids` |
| Show “Will send to N users” | `POST .../preview-audience` |

Suggested UX:

1. Load `/options` on screen open.
2. When audience changes (or custom IDs change), call `/preview-audience` and show the count.
3. Disable Send if count is `0`.
4. On success, show toast and refresh history list.

---

## Audience definitions

| Audience | Meaning |
|---|---|
| `all` | Active customers |
| `with_bookings` | Active customers with **at least one** booking row (any status) |
| `without_bookings` | Active customers with **zero** bookings |
| `custom` | Explicit `user_ids` filtered to active customers |

Notifications are **push only** — they do not award loyalty points.
