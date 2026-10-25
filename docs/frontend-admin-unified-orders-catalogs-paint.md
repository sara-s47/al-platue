# Admin frontend — cover images & accessory categories

## Cover image (all catalogs)

Dedicated `cover_image` column on product tables (not in gallery `*_images`). Upload as multipart file field `cover_image` on create/update.

| Catalog | Endpoints |
| --- | --- |
| Studios | `POST/PUT admin/studios`, `POST admin/studios/{id}` |
| Accessories | `POST/PUT admin/accessories`, `POST admin/accessories/{id}` |
| Equipment | `POST/PUT admin/equipment`, `POST admin/equipment/{id}` |
| Portable bathrooms | `POST/PUT admin/portable-bathrooms`, `POST admin/portable-bathrooms/{id}` |
| Caravans | `POST/PUT admin/caravans`, `POST admin/caravans/{id}` |
| Generators | `POST/PUT admin/generators`, `POST admin/generators/{id}` |

### Multipart fields

| Field | Notes |
| --- | --- |
| `cover_image` | Optional image (`jpeg/jpg/png/webp`). Studios/equipment max **5MB**; accessories & day catalogs max **50MB**. |
| `clear_cover` | On update: `1` / `true` removes cover (deletes stored file). |
| `image` / `images` | Gallery (unchanged). Independent of cover. |
| `clear_images` | Clears gallery only (not cover). |

### Response fields

```json
{
  "cover_image": "https://…/storage/app/public/accessories/….jpg",
  "image": "https://…",
  "images": []
}
```

- `cover_image`: dedicated cover URL, or `null`.
- `image`: **cover if set**, otherwise first gallery image (backward compatible).
- `images`: gallery only (does not include cover).

---

## Accessory categories

Separate from studio `categories`. Permission: `accessories.manage`.

### Admin routes (`admin/accessory-categories`)

| Method | Path | Body / notes |
| --- | --- | --- |
| GET | `/` | Paginated list (`per_page`) |
| POST | `/` | `{ name, description?, sort_order?, is_active? }` |
| GET | `/{id}` | Show |
| PUT | `/{id}` | Partial update |
| DELETE | `/{id}` | Fails with `category_has_items` if accessories still linked |
| POST | `/reorder` | `{ ordered_ids: number[] }` |
| POST | `/{id}/toggle` | Flip `is_active` |

### Accessories + `category_id`

| Method | Path | Notes |
| --- | --- | --- |
| POST | `admin/accessories` | **`category_id` required** (`exists:accessory_categories`) |
| PUT/POST | `admin/accessories/{id}` | `category_id` optional |
| GET | `admin/accessories` | Optional filters: `category_id`, `search` |

Accessory responses include `category_id` and nested `category` when loaded:

```json
{
  "id": 1,
  "category_id": 2,
  "category": { "id": 2, "name": "Decor", "description": "…", "sort_order": 1, "is_active": true },
  "cover_image": null,
  "image": null
}
```
