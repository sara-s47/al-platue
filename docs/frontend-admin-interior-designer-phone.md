# Admin frontend — interior designer phone

Permission: `interior-designers.manage`

Interior designers accept an optional `phone` on create/update.

## Field

| Field | Type | Notes |
| --- | --- | --- |
| `phone` | string \| null | Max **20** characters. Optional. |

## Endpoints

| Method | Path | Notes |
| --- | --- | --- |
| POST | `admin/interior-designers` | Include `phone` with the usual fields (`name`, `title`, `image`, `portfolio_url`, `notes`, …) |
| PUT / POST | `admin/interior-designers/{id}` | Optional `phone`; omit to leave unchanged |
| GET | `admin/interior-designers` | List includes `phone` |
| GET | `admin/interior-designers/{id}` | Show includes `phone` |

## Example response snippet

```json
{
  "id": 1,
  "name": "Sara Design",
  "title": "Interior Designer",
  "phone": "01012345678",
  "image": "https://…/storage/app/public/interior-designers/….jpg",
  "portfolio_url": "https://…",
  "notes": [],
  "is_active": true,
  "sort_order": 0
}
```

Existing designers keep `phone: null` until set.
