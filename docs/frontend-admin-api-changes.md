# Frontend Admin API Changes

Backend changes for the admin dashboard. Base path: `/api/v1/admin`.

## UI notes (required)

1. **Pricing Rules — show studio name**
   - Do **not** display raw `studio_id` alone in tables/forms.
   - Use `studio_name` from the pricing-rule payload, or join against `GET /studios` for dropdown labels.

2. **Scrolling / overflow**
   - Audit dashboard tables, side panels, and modals for clipped content.
   - Long schedule/override/block lists and pricing tables need proper page/panel scroll, not truncated overflow.

---

## Pricing Rules

### Behavior

- There is **no date range** (`end_date` is rejected). Only `specific_date` for `rule_type=date_specific`.
- `start_date` is accepted as an alias for `specific_date`.
- Non-`date_specific` rules clear `specific_date` on create/update.
- Precedence: `date_specific` → `peak` → `weekend` → `base`.

### Response shape (no fake `end_date`)

```json
{
  "id": 1,
  "studio_id": 2,
  "studio_name": "Studio A",
  "rule_type": "weekend",
  "day_of_week": 5,
  "start_time": null,
  "end_time": null,
  "specific_date": null,
  "price_per_hour": 500,
  "hourly_rate": 500,
  "price_per_day": 4000,
  "priority": 10,
  "is_active": true
}
```

---

## CRM Users

- `GET /users` returns **customers only** (`role=customer`).
- Each user includes `roles` (array of role names).

---

## Studio schedule

Prefix: `/studios/{studioId}/schedule`  
Permission: `schedules.manage`

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/weekly` | List weekly open/close per `day_of_week` |
| PUT | `/weekly` | Upsert weekly days (`days[]`) |
| GET | `/overview?from&to` | Range overview (weekly + per-day details) |
| GET | `/day?date=YYYY-MM-DD` | **Day show** (schedule + weekend + blocks + pricing) |
| GET | `/effective?date=` | Effective hours for one date (legacy) |
| GET | `/overrides` | Paginated overrides |
| POST | `/overrides` | Create override |
| PUT | `/overrides/{id}` | Update override |
| DELETE | `/overrides/{id}` | Delete override |
| GET | `/blocks` | Paginated blocks |
| POST | `/blocks` | Create block |
| PUT | `/blocks/{id}` | Update block |
| DELETE | `/blocks/{id}` | Delete block |

### Day show example

`GET /studios/{id}/schedule/day?date=2026-09-15`

```json
{
  "date": "2026-09-15",
  "day_of_week": 2,
  "is_weekend": false,
  "schedule": {
    "source": "weekly",
    "open_time": "09:00:00",
    "close_time": "22:00:00",
    "is_closed": false,
    "override_id": null,
    "reason": null
  },
  "blocks": [],
  "pricing": {
    "price_per_hour": 400,
    "price_per_day": 3000,
    "winning_hourly_rule_type": "base",
    "winning_daily_rule_type": "base",
    "hourly": { "rule_id": 1, "rule_type": "base", "price_per_hour": 400, "price_per_day": 3000, "priority": 0 },
    "daily": { "rule_id": 1, "rule_type": "base", "price_per_hour": 400, "price_per_day": 3000, "priority": 0 }
  }
}
```

`schedule.source`: `weekly` | `override` | `closed`

Weekend days: Friday/Saturday (`day_of_week` 5/6).

### Create studio with optional weekly schedule

`POST /studios` accepts optional `weekly_schedule` (same shape as weekly `days[]`).

---

## Packages (admin)

- `GET /packages/{id}` now includes nested `equipment` and `hospitality` (id, name, quantity, price).

---

## Equipment availability helper

`GET /equipment/{equipmentId}/availability?studio_id=&start_at=&end_at=`

Returns `studio_quantity`, `allocated`, `available`, and `reason` (`not_assigned` / `studio_qty_zero` / null).

Availability is based on **`studio_equipment.quantity`**, not the global `equipment.quantity` alone.

---

## Cancellation policies

Selection uses float hours until start and picks the highest matching `hours_before` tier. Refund is computed at cancel time; **no automatic Paymob refund** in v1 (manual admin refund).

Demo tiers (after reseed): 72h→100%, 48h→50%, 24h→25%.
