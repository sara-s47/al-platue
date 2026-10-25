# Customer frontend — cover images & accessory categories

## Cover / primary image

Catalog products (studios, accessories, equipment, portable bathrooms, caravans, generators) return:

| Field | Meaning |
| --- | --- |
| `cover_image` | Dedicated cover URL, or `null` |
| `image` | Prefer cover; if missing, first gallery image |
| `images` | Gallery only |

Use `image` for list/cards (same as before). Use `cover_image` when you need the explicit cover.

Cart and order line items also use the same preference for their `image` field.

---

## Accessory categories

Studio categories stay on `GET customer/categories`. Accessories have their own list.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `customer/accessory-categories` | Active categories only (ordered by `sort_order`) |
| GET | `customer/accessories` | Optional `category_id`, `search`, `per_page`; optional availability via `start_date` / `end_date` |
| GET | `customer/accessories/{id}` | Includes `category_id` / `category` when available |

Example list filter:

`GET customer/accessories?category_id=2&per_page=15`
