# Equipment Images — Frontend Guide

## Overview

Equipment now supports image upload on **create** and **update**.

Images are stored on the Laravel `public` disk under:

```text
storage/app/public/equipment/{filename}
```

The API does **not** rely on `php artisan storage:link`.  
Image paths returned by the API are always in this form:

```text
storage/app/public/{relative_path}
```

Example:

```text
storage/app/public/equipment/abc123.jpg
```

---

## Full image URL

Build the full URL by joining the API host (app base URL, **not** the `/api/v1` prefix) with the path from the response:

```text
{APP_BASE_URL}/{image_path}
```

Examples:

| APP_BASE_URL | `image` from API | Final URL |
|---|---|---|
| `https://example.com` | `storage/app/public/equipment/abc.jpg` | `https://example.com/storage/app/public/equipment/abc.jpg` |
| `https://example.com/api-elplatue` | `storage/app/public/equipment/abc.jpg` | `https://example.com/api-elplatue/storage/app/public/equipment/abc.jpg` |

Do **not** prepend `/api/v1` to image paths.

---

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/v1/admin/equipment` | Create equipment (with optional image) |
| `POST` | `/api/v1/admin/equipment/{id}` | Update equipment (multipart-friendly) |
| `PUT` | `/api/v1/admin/equipment/{id}` | Update equipment |
| `GET` | `/api/v1/admin/equipment` | List (includes images) |
| `GET` | `/api/v1/admin/equipment/{id}` | Show (includes images) |
| `GET` | `/api/v1/studios/{studioId}/equipment` | Customer list for a studio |

Auth: admin equipment routes require permission `equipment.manage`.

---

## Upload (multipart/form-data)

Content-Type must be `multipart/form-data` (not JSON).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | create: yes | |
| `description` | string | no | |
| `quantity` | integer | create: yes | min 1 |
| `price_per_hour` | number | create: yes | maps to DB `price` |
| `status` | string | no | |
| `image` | file | no | single image (`jpeg`, `jpg`, `png`, `webp`, max 5MB) |
| `images[]` | file[] | no | up to 5 images |
| `clear_images` | boolean | update only | `1` / `true` removes all images without uploading new ones |

### Create example (Postman / form)

- `name`: Softbox Light  
- `quantity`: `2`  
- `price_per_hour`: `50`  
- `image`: *(file)*

### Update image only

- `image`: *(file)*  
Other fields are optional on update.

### Clear images

- `clear_images`: `1`

---

## Response shape

```json
{
  "id": 1,
  "name": "Softbox Light",
  "description": null,
  "price_per_hour": "50.00",
  "quantity": 2,
  "status": "active",
  "image": "storage/app/public/equipment/abc123.jpg",
  "images": [
    {
      "id": 10,
      "path": "equipment/abc123.jpg",
      "url": "storage/app/public/equipment/abc123.jpg"
    }
  ]
}
```

### Field meanings

| Field | Meaning |
|---|---|
| `image` | Primary image path (`storage/app/public/...`), or `null` |
| `images[].path` | Relative path inside the public disk (`equipment/...`) |
| `images[].url` | Same as `storage/app/public/` + `path` — use this (or `image`) in the UI |

---

## Frontend usage tips

1. Prefer `item.image` for thumbnails; fall back to `item.images[0]?.url`.
2. Always build the URL as `` `${APP_BASE_URL}/${path}` `` (avoid double slashes).
3. On create/update with files, use `FormData` — do not `JSON.stringify` the body.
4. For updates that include a file, prefer `POST /api/v1/admin/equipment/{id}` (multipart is more reliable than `PUT` in some clients).

### React Native / Expo example

```ts
const form = new FormData();
form.append('name', name);
form.append('quantity', String(quantity));
form.append('price_per_hour', String(price));
form.append('image', {
  uri: localUri,
  name: 'equipment.jpg',
  type: 'image/jpeg',
} as any);

await api.post('/admin/equipment', form, {
  headers: { 'Content-Type': 'multipart/form-data' },
});
```

### Display example

```ts
const imageUri = equipment.image
  ? `${APP_BASE_URL}/${equipment.image}`
  : null;
```

---

## Notes for backend / deploy

- Files live under `storage/app/public/equipment/`.
- A web route serves `GET /storage/app/public/{path}` so browsers can load images **without** running `storage:link`.
- Ensure `storage/app/public` is writable by the PHP process.
