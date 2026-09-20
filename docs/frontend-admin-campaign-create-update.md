# Admin campaign — create and update

**Audience:** frontend (admin dashboard)  
**Auth:** `Authorization: Bearer {token}`  
**Permission:** `campaigns.manage`  
**Base path:** `/api/v1/admin/campaigns`

A campaign awards loyalty points to customers in a **segment**.  
`filters_json` is **not** sent on the campaign. It belongs to the segment. Pick the segment by **name**, then send `segment_id`.

---

## Endpoints that exist

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/admin/campaigns` | Paginated list |
| `POST` | `/api/v1/admin/campaigns` | **Create** |
| `POST` | `/api/v1/admin/campaigns/{id}/execute` | Give the points |

There is **no** `GET /campaigns/{id}`, **no** `PUT /campaigns/{id}`, and **no** `PATCH`.  
Do not build an edit form that calls update. After create, the only change the API supports is **execute**.

---

## Create

`POST /api/v1/admin/campaigns`  
`Content-Type: application/json`

Status is set by the server to `draft`. Do not send `status`.

### Body fields

| Field | Required | Type | Rules | UI |
| --- | --- | --- | --- | --- |
| `name` | **yes** | string | max 255 | Text input |
| `points` | **yes** | number | min `1` | Number input |
| `reason` | no | string | max 255 | Text input. Used on the points transaction. If omitted, execute uses `name` |
| `segment_id` | **yes in the UI** | integer | must exist in `segments` | Dropdown of segment **names**. API allows omitting it, but then execute awards **nobody** |

Do **not** send: `filters_json`, `status`, `id`, `expires_at`, `scheduled_at`, `created_by`.

### Load the segment dropdown first

`GET /api/v1/admin/segments?per_page=100`  
Permission: `segments.manage`

Show `name`. Keep `id` in state. Prefer `"is_active": true`.

### Example request

```json
{
  "name": "Welcome points for new customers",
  "points": 100,
  "reason": "New customer gift",
  "segment_id": 4
}
```

Minimal valid body (not recommended — no recipients):

```json
{
  "name": "Welcome points for new customers",
  "points": 100
}
```

### Success `201`

```json
{
  "success": true,
  "message": null,
    "data": {
    "id": 2,
    "name": "Welcome points for new customers",
    "points": "100.00",
    "reason": "New customer gift",
    "status": "draft",
    "scheduled_at": null,
    "created_by": 1,
    "created_by_name": "Super Admin"
  },
  "meta": []
}
```

The response does **not** include `segment_id` or `reason`. Recipients were stored internally when `segment_id` was sent. That list is a snapshot; editing the segment later does not change this campaign.

### Validation error `422`

```json
{
  "success": false,
  "message": "The name field is required.",
  "data": {
    "name": ["The name field is required."]
  },
  "meta": []
}
```

| If you send | Error |
| --- | --- |
| no `name` | `name` is required |
| `name` longer than 255 | `name` max 255 |
| no `points` | `points` is required |
| `points` `0` or negative | `points` min 1 |
| `segment_id` that does not exist | `segment_id` must exist in `segments` |
| inactive segment | business error `segment_inactive` |

---

## Update

**Not available.** There is no update endpoint.

You cannot change `name`, `points`, `reason`, or `segment_id` after create.

If the admin made a mistake:

1. Do not execute the campaign
2. Create a new campaign with the correct values
3. There is also no cancel endpoint in this API (`cancelled` exists only as a status value in the database)

---

## Execute (the action after create)

`POST /api/v1/admin/campaigns/{id}/execute`  
No body.

Allowed only when `status` is `draft` or `scheduled`.

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

If already executed:

```json
{
  "success": false,
  "message": "Campaign cannot be executed in its current state.",
  "data": {
    "code": "campaign_not_executable"
  },
  "meta": []
}
```

---

## List (to show create result)

`GET /api/v1/admin/campaigns?per_page=15&page=1`

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2,
      "name": "Welcome points for new customers",
      "points": "100.00",
      "status": "draft",
      "scheduled_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

`status` values you may see: `draft`, `scheduled`, `running`, `completed`, `cancelled`.

---

## UI checklist

- [ ] Create form: `name` (required), `points` (required, min 1), `reason` (optional), segment dropdown by **name** (required in UI).
- [ ] Submit `segment_id` only. Never `filters_json` on this request.
- [ ] No edit/update screen for campaigns (API has none).
- [ ] After create, show Execute. After execute, show `awarded` / `skipped` / `failed`.
