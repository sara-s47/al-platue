# Customer frontend — interior designer phone

Customer interior-designer responses now include an optional `phone`.

## Field

| Field | Type | Notes |
| --- | --- | --- |
| `phone` | string \| null | Max **20** characters. May be `null` if not set. |

## Endpoints

| Method | Path | Notes |
| --- | --- | --- |
| GET | `customer/interior-designers` | Each active designer includes `phone` |
| GET | `customer/interior-designers/{id}` | Includes `phone` |

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
