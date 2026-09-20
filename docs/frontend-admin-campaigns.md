# Admin campaigns and segment filters

**Audience:** frontend (admin dashboard)  
**Auth:** `Authorization: Bearer {token}`

A **campaign** gives loyalty points to a group of customers.  
The group is a **segment**. The segment’s rules live in `filters_json`.

The campaign body does **not** contain `filters_json`. You:

1. Create (or pick) a **segment** with `filters_json`
2. Create the **campaign** with `segment_id`

Do **not** show the admin a JSON textarea. Use form controls, then send an object.

---

## `filters_json` — allowed keys and values

Send `filters_json` as a **JSON object**, not a string.

Only these keys are used. Any other key is ignored (including `min_bookings`).

| Key | Type | Allowed values | Meaning |
| --- | --- | --- | --- |
| `status` | string | `active` \| `inactive` \| `blocked` | User account status |
| `booked_before` | boolean | `true` only (omit if false) | Has at least one booking |
| `never_booked` | boolean | `true` only (omit if false) | Has zero bookings |
| `studio_id` | integer | existing studio id | Has booked this studio |
| `min_booking_count` | integer | `1` or more | Booking count ≥ this number |
| `min_total_spend` | number | `0` or more | Sum of **completed** booking totals ≥ this |
| `last_booking_before` | date string | e.g. `2026-06-01` | Last booking start is before this date |
| `inactive_days` | integer | `1` or more | No login for N days, or never logged in |
| `min_points_balance` | number | `0` or more | Points balance ≥ this |
| `never_redeemed` | boolean | `true` only (omit if false) | Never redeemed points |

### Rules

- Omit a key if the admin did not fill that filter. Do not send `null` or `false` unless you mean it. `false` for `booked_before` / `never_booked` / `never_redeemed` does nothing.
- Do **not** set `booked_before` and `never_booked` together. Use one radio: Any / Has booked / Never booked.
- Wrong key: `min_bookings` → **no one matches**. Correct key: `min_booking_count`.
- `status` must be exactly `active`, `inactive`, or `blocked` (lowercase).

### Empty object

```json
{
  "name": "All customers",
  "is_active": true,
  "filters_json": {}
}
```

`{}` means **every user** in `users` (no extra filters). Usually you still want `"status": "active"`.

---

## Examples (`filters_json` only)

Active customers who never booked:

```json
{
  "status": "active",
  "never_booked": true
}
```

Active customers with at least 1 booking (do **not** use `min_bookings`):

```json
{
  "status": "active",
  "min_booking_count": 1
}
```

Booked studio `3`, at least 2 bookings, spent at least 2000:

```json
{
  "booked_before": true,
  "studio_id": 3,
  "min_booking_count": 2,
  "min_total_spend": 2000
}
```

Last booking before June 2026, inactive 30 days, never redeemed points:

```json
{
  "last_booking_before": "2026-06-01",
  "inactive_days": 30,
  "never_redeemed": true,
  "min_points_balance": 100
}
```

---

## Create the segment

`POST /api/v1/admin/segments`  
Permission: `segments.manage`

```json
{
  "name": "Active, never booked",
  "is_active": true,
  "filters_json": {
    "status": "active",
    "never_booked": true
  }
}
```

**Response `201`**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 4,
    "name": "Active, never booked",
    "filters_json": {
      "status": "active",
      "never_booked": true
    },
    "is_active": true
  },
  "meta": []
}
```

Preview how many people match:

`GET /api/v1/admin/segments/4/preview`

```json
{
  "success": true,
  "data": { "count": 27 },
  "message": null,
  "meta": []
}
```

List segments for the campaign dropdown (show **name**, keep `id`):

`GET /api/v1/admin/segments?per_page=100`

Use only `"is_active": true` in the campaign select.

---

## Create the campaign

`POST /api/v1/admin/campaigns`  
Permission: `campaigns.manage`

The admin picks a segment **by name**. You send `segment_id`. Do not put `filters_json` here.

| Field | Required | Type | Notes |
| --- | --- | --- | --- |
| `name` | yes | string | Campaign title |
| `points` | yes | number, min 1 | Points given to each matching user |
| `reason` | no | string | Shown on the points transaction |
| `segment_id` | yes in UI | integer | From the segment dropdown. If omitted, execute awards **nobody** |

```json
{
  "name": "Welcome points for new customers",
  "points": 100,
  "reason": "New customer gift",
  "segment_id": 4
}
```

**Response `201`**

```json
{
  "success": true,
  "message": null,
  "data": {
    "id": 2,
    "name": "Welcome points for new customers",
    "points": "100.00",
    "status": "draft",
    "scheduled_at": null
  },
  "meta": []
}
```

Recipients are copied from the segment **at create time**. Later changes to the segment do not change this campaign’s list.

---

## List and execute

`GET /api/v1/admin/campaigns`

| Field | Values |
| --- | --- |
| `status` | `draft` \| `scheduled` \| `running` \| `completed` \| `cancelled` |

`POST /api/v1/admin/campaigns/{id}/execute`  
No body. Allowed when status is `draft` or `scheduled`.

**Response `200`**

```json
{
  "success": true,
  "message": "Campaign executed.",
  "data": {
    "awarded": 27,
    "skipped": 0,
    "failed": 0
  },
  "meta": []
}
```

If the segment was inactive when the campaign was created, create/execute can fail with `segment_inactive`.

---

## UI summary

1. Segment form = name + active + the filter controls in the table. Build `filters_json` in code.
2. Campaign form = name + points + reason + segment **name** dropdown.
3. Submit campaign with `segment_id` only. No `filters_json` on the campaign request.
