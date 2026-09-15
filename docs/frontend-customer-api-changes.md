# Frontend Customer API Changes

Backend changes for the customer/mobile app. Base path: `/api/v1/customer`.

---

## Daily booking

- `booking_mode`: `hourly` (default) | `daily`
- Daily quote/hold/booking use `start_date` + `end_date` (not clock times).
- Check: `GET /studios/{studioId}/availability/daily?start_date=&end_date=&guest_count=`

### Unavailable response

When unavailable, `reason` is more specific than a generic `slot_unavailable`:

| reason | meaning |
|--------|---------|
| `conflicting_bookings` | Overlaps existing bookings (`details.conflicting_booking_ids`) |
| `conflicting_holds` | Overlaps active holds (`details.conflicting_hold_ids`) |
| `conflicting_blocks` | Overlaps studio blocks (`details.conflicting_block_ids`) |
| `studio_closed` | Closed on one of the days |
| `capacity_exceeded` | Guest count > studio capacity |
| `invalid_daily_duration` | Outside max daily days |

Expired holds are cleaned/excluded before checks. Daily range checks do **not** inflate conflicts with cleanup buffer.

---

## Package booking (protected)

Packages are **hourly only** (not daily).

### Details

`GET /packages/{id}` returns package scalars **plus**:

```json
{
  "equipment": [{ "id": 1, "name": "Camera", "quantity": 2, "price": 100 }],
  "hospitality": [{ "id": 3, "name": "Coffee", "quantity": 1, "price": 50 }]
}
```

### Simple endpoints

| Method | Path | Body |
|--------|------|------|
| POST | `/packages/quote` | `package_id`, `start_at`, `guest_count`, optional extras/promo/points |
| POST | `/packages/hold` | `package_id`, `start_at`, optional `guest_count` + extras |

- `end_at` is **derived** from `package.duration_minutes` (client end time is ignored / optional).
- Included package equipment/hospitality are **auto-added** and allocated.
- Included items appear on the quote as line items with `unit_price: 0` / included in package.
- Extra quantities beyond package includes are charged normally (no double-charge for included qty).

### Existing flows still work

- `POST /bookings/quote` with `package_id` (optional `end_at`; duration enforced).
- `POST /bookings` with `package_id` when converting a hold.

### Errors

- `package_duration_mismatch` — start/end do not equal package duration.
- `package_not_allowed_for_daily`
- Equipment errors may include `reason`: `not_assigned` | `studio_qty_zero` | `fully_booked` with `studio_quantity`, `allocated`, `available`.

---

## Cancellation

Refund % + fee come from the matching cancellation policy (`hours_before`).  
**No automatic Paymob refund** — financial refund is handled manually by admin after cancel.
