# Daily Studio Booking — Frontend Guide

## Overview

Studios support two booking modes:

| Mode | Meaning |
|---|---|
| `hourly` (default) | Same-day hourly booking (existing behavior) |
| `daily` | Consecutive calendar days, **no overnight stay**. Each day uses the studio’s open→close hours. Max **7 days**. |

Pricing for `daily` uses **`price_per_day`** from pricing rules (not hourly).

---

## Check daily availability

`GET /api/v1/studios/{studioId}/availability/daily?start_date=2026-09-21&end_date=2026-09-23&guest_count=1`

```json
{
  "available": true,
  "start_date": "2026-09-21",
  "end_date": "2026-09-23",
  "days_count": 3,
  "days": [
    { "date": "2026-09-21", "open_time": "09:00:00", "close_time": "23:00:00" }
  ],
  "start_at": "2026-09-21 09:00:00",
  "end_at": "2026-09-23 23:00:00",
  "reason": null
}
```

If unavailable, `available` is `false` and `reason` is an error code (e.g. `studio_closed`, `slot_unavailable`).

Hourly slots remain: `GET /api/v1/studios/{studioId}/availability?date=&duration=`

---

## Quote (daily)

`POST /api/v1/customer/bookings/quote`

```json
{
  "booking_mode": "daily",
  "studio_id": 2,
  "start_date": "2026-09-21",
  "end_date": "2026-09-23",
  "guest_count": 1,
  "equipment": [],
  "hospitality": [],
  "promo_code": null,
  "points_to_redeem": 0
}
```

**Do not send `package_id` with daily** — packages are hourly-only in v1.

Response includes `booking_mode`, `days_count`, and `days` (each with `daily_rate`).

---

## Hold (daily)

`POST /api/v1/customer/booking-holds`

```json
{
  "booking_mode": "daily",
  "studio_id": 2,
  "start_date": "2026-09-21",
  "end_date": "2026-09-23",
  "guest_count": 1
}
```

Hold stores computed `start_at` / `end_at` (first open → last close) and `booking_mode`.

---

## Create booking

Unchanged: `POST /api/v1/customer/bookings` with `hold_id` + extras. Mode comes from the hold.

---

## Reschedule

- **Hourly:** `start_at` + `end_at`
- **Daily:** `start_date` + `end_date` (preferred) or datetimes

```json
{
  "start_date": "2026-09-24",
  "end_date": "2026-09-26",
  "reason": "Moved to next week"
}
```

---

## Not supported on daily bookings

| Action | Behavior |
|---|---|
| Extend by hours | `422` — `daily_extension_not_supported` (reschedule end date instead) |
| Overtime | `daily_overtime_not_supported` |
| Packages | `package_not_allowed_for_daily` |

---

## Admin pricing rules

Set `price_per_day` on base/weekend/date_specific rules:

```json
{
  "studio_id": 1,
  "rule_type": "base",
  "price_per_hour": 500,
  "price_per_day": 3500,
  "is_active": true
}
```

Peak time-window rules are ignored for daily pricing. If no rule with `price_per_day` matches a day → `daily_price_not_configured`.

---

## Settings

- `max_daily_booking_days` (default `7`)

---

## Suggested UI flow

1. Toggle Hourly / Daily.
2. Daily: date range picker (max 7 days) → call availability/daily → show open/close per day.
3. Quote → Hold → Create (same as hourly).
4. Show `booking_mode` on booking details.
