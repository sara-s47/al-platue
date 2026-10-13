# Studio Booking Flow — Frontend Guide

This document explains the full **studio booking flow** for the customer app, plus the **equipment**, **hospitality** and **packages** endpoints, with real example responses.

> ## ⚠️ Online payment (Paymob) is temporarily disabled
>
> - All studio bookings are now **Cash on Delivery (COD)** — the customer pays at the studio.
> - A booking is **`confirmed` immediately** when it is created. There is no "pending payment" step.
> - `POST /api/v1/customer/bookings/{bookingId}/payments` is **disabled** (returns `404`). Remove the "Pay online" button / Paymob iframe from the booking flow.
> - When the customer pays at the studio, the booking's `paid_amount` / `remaining_amount` are updated by the studio staff.

---

## Conventions

- Base URL: `https://api-elplatue.thecyberbuzz.com/api/v1`
- All customer endpoints require `Authorization: Bearer {token}` and `Accept: application/json`.
- Response envelope:

```json
{ "success": true, "message": "...", "data": {}, "meta": {} }
```

- Error envelope (business errors return `422` unless stated otherwise):

```json
{
  "success": false,
  "message": "Selected slot is no longer available.",
  "data": { "code": "conflicting_holds", "reason": "conflicting_holds", "conflicting_hold_ids": [28] }
}
```

Always branch on `data.code`, not on `message`.

### Dates & times (important)

- **Send** times as studio local time (Cairo), format `YYYY-MM-DD HH:mm:ss`, **without** a timezone. Example: `"2026-10-15 10:00:00"`.
- Dates for daily bookings use `YYYY-MM-DD`.
- `start_at` / `end_at` returned on **holds** and **bookings** look like `"2026-10-15T10:00:00.000000Z"`. The `Z` suffix is misleading: the value is already **studio local time**. **Display the date and time as-is — do not convert it to the device timezone.** (`10:00:00.000000Z` → show "10:00".)

### Booking settings (current defaults)

| Setting | Value | Meaning |
|---|---|---|
| Hold duration | 15 min | A hold expires 15 minutes after it is created |
| Booking interval | 30 min | Start times and durations move in 30-minute steps |
| Minimum booking | 60 min | Shortest hourly booking |
| Maximum booking | 480 min | Longest hourly booking (8 hours) |
| Cleanup buffer | 15 min | Gap kept between bookings (handled by the backend) |
| Max daily booking | 7 days | Longest daily booking |

---

## 1. Flow overview

There are three ways to book a studio. All of them follow the same pattern: **pick a time → hold it → (quote) → create the booking**.

### A) Hourly booking

```
1. GET  /customer/studios/{studioId}                             → studio details
2. GET  /customer/studios/{studioId}/availability                → free time slots
3. GET  /customer/studios/{studioId}/equipment                   → optional add-ons
   GET  /customer/studios/{studioId}/hospitality                 → optional add-ons
4. POST /customer/booking-holds                                  → reserve the slot (15 min)
5. POST /customer/bookings/quote   (with hold_id)                → price breakdown
6. POST /customer/bookings         (with hold_id)                → booking created, status = confirmed
```

### B) Package booking

```
1. GET  /customer/studios/{studioId}/packages                    → list packages
2. GET  /customer/packages/{id}                                  → package details
3. POST /customer/packages/quote                                 → price (optional, before hold)
4. POST /customer/packages/hold                                  → reserve the slot (15 min)
5. POST /customer/packages/quote   (with hold_id)                → refresh price with extras (optional)
6. POST /customer/bookings         (with hold_id + package_id)   → booking created, status = confirmed
```

### C) Daily booking (full days)

```
1. GET  /customer/studios/{studioId}/availability/daily          → are these days free?
2. POST /customer/booking-holds    (booking_mode = daily)        → reserve the days (15 min)
3. POST /customer/bookings/quote   (booking_mode = daily + hold_id)
4. POST /customer/bookings         (with hold_id)                → booking created, status = confirmed
```

### Golden rules

1. **After you create a hold, send `hold_id` in every quote request** for that same attempt. Without it, the quote collides with your own hold and returns `conflicting_holds`.
2. Send the **same `equipment`, `hospitality`, `accessories`, `promo_code`, `points_to_redeem`** to the final `POST /bookings` that you sent to the quote. The booking is re-priced on the server, and the final price comes from that call.
3. Complete the booking before the hold expires (`expires_at`). Otherwise you get `hold_expired` and must create a new hold.
4. If the user leaves the checkout, release the hold with `DELETE /customer/booking-holds/{id}`.
5. Debounce quote calls (e.g. 300–500 ms) when the user changes extras.

---

## 2. Availability

### 2.1 Hourly slots

```
GET /customer/studios/{studioId}/availability?date=2026-10-15&duration=120&guest_count=2
```

| Param | Required | Notes |
|---|---|---|
| `date` | yes | `YYYY-MM-DD` |
| `duration` | yes | Minutes. Must be between 60 and 480, in 30-minute steps |
| `guest_count` | no | Rejects with `capacity_exceeded` if above the studio capacity |

Response — every free start time for this duration (studio local time):

```json
{
  "success": true,
  "message": null,
  "data": [
    { "start_at": "2026-10-15 08:00:00", "end_at": "2026-10-15 10:00:00" },
    { "start_at": "2026-10-15 08:30:00", "end_at": "2026-10-15 10:30:00" },
    { "start_at": "2026-10-15 09:00:00", "end_at": "2026-10-15 11:00:00" }
  ]
}
```

- Only **free** slots are returned. There is no list of booked slots; anything missing is unavailable.
- An empty array means the studio is closed or fully booked for that duration.
- Use a returned `start_at` / `end_at` **exactly** when creating the hold.

### 2.2 Daily availability

```
GET /customer/studios/{studioId}/availability/daily?start_date=2026-10-15&end_date=2026-10-16&guest_count=2
```

```json
{
  "success": true,
  "message": null,
  "data": {
    "available": true,
    "start_date": "2026-10-15",
    "end_date": "2026-10-16",
    "days_count": 2,
    "days": [
      { "date": "2026-10-15", "open_time": "08:00", "close_time": "23:00" },
      { "date": "2026-10-16", "open_time": "08:00", "close_time": "23:00" }
    ],
    "start_at": "2026-10-15 08:00:00",
    "end_at": "2026-10-16 23:00:00",
    "reason": null
  }
}
```

When `available` is `false`, `reason` holds the error code (for example `conflicting_bookings` or `studio_closed`).

---

## 3. Equipment

```
GET /customer/studios/{studioId}/equipment
```

Equipment assigned to this studio that the customer can add to a booking.

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 1,
      "name": "Sony A7 IV Camera",
      "description": "Full-frame mirrorless camera with 28-70mm lens.",
      "price_per_hour": "250.00",
      "quantity": 4,
      "status": "active",
      "image": null,
      "images": []
    },
    {
      "id": 2,
      "name": "LED Softbox Lighting Kit",
      "description": "Three-point lighting setup with diffusers and stands.",
      "price_per_hour": "150.00",
      "quantity": 6,
      "status": "active",
      "image": null,
      "images": []
    }
  ]
}
```

| Field | Notes |
|---|---|
| `price_per_hour` | Price per unit. **Currently charged once per booking** (`price × quantity`), not multiplied by hours. Always show the amount from the quote. |
| `quantity` | Total units the studio owns. Availability for a specific time is checked on hold / quote / booking. |
| `image` / `images` | Image URLs (may be `null` / empty). |

How to send it in quote / booking:

```json
"equipment": [ { "id": 1, "quantity": 1 } ]
```

Errors: `equipment_not_assigned`, `equipment_unavailable`, `equipment_insufficient_quantity`, `invalid_equipment_quantity`.

---

## 4. Hospitality

```
GET /customer/studios/{studioId}/hospitality
```

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 2,
      "category_id": 1,
      "name": "Bottled Water",
      "description": "Chilled bottled water.",
      "pricing_model": "per_person",
      "price": 15,
      "quantity": 200,
      "is_active": 1,
      "category_name": "Beverages"
    },
    {
      "id": 1,
      "category_id": 1,
      "name": "Coffee & Tea",
      "description": "Unlimited coffee and tea during your session.",
      "pricing_model": "included",
      "price": 0,
      "quantity": 100,
      "is_active": 1,
      "category_name": "Beverages"
    }
  ]
}
```

### Pricing models

| `pricing_model` | Total |
|---|---|
| `included` | Free (`0`) |
| `per_person` | `price × guest_count × quantity` |
| `per_item`, `per_unit`, `per_package` | `price × quantity` |

Example: Bottled Water (`per_person`, 15 EGP), `quantity: 2`, `guest_count: 2` → `15 × 2 × 2 = 60`.

Group items by `category_name` in the UI.

How to send it:

```json
"hospitality": [ { "id": 2, "quantity": 2 } ]
```

Errors: `hospitality_unavailable`, `hospitality_not_eligible`, `hospitality_insufficient_quantity`, `invalid_hospitality_quantity`.

---

## 5. Packages

A package is a fixed-duration session at a fixed price, with some equipment and hospitality **included for free**. Customers can still add extra equipment and hospitality, which are charged normally.

### 5.1 List packages for a studio

```
GET /customer/studios/{studioId}/packages
```

```json
{
  "success": true,
  "message": null,
  "data": [
    {
      "id": 16,
      "studio_id": 1,
      "name": "Express Hour",
      "description": "One-hour session with lighting included. Add extra camera or drinks on quote.",
      "duration_minutes": 60,
      "price": "550.00",
      "is_active": true,
      "valid_from": "2026-10-12T00:00:00.000000Z",
      "valid_to": "2027-10-13T00:00:00.000000Z",
      "equipment": [
        { "id": 2, "name": "LED Softbox Lighting Kit", "quantity": 1, "price": "150.00" }
      ],
      "hospitality": [
        { "id": 1, "name": "Coffee & Tea", "quantity": 1, "price": "0.00" }
      ]
    }
  ]
}
```

- `equipment` / `hospitality` are the items **included** in the package price. Their `price` is the normal price, so you can show "worth X".
- `duration_minutes` sets the session length. The customer only picks the **start time**.
- To find valid start times, call the hourly availability endpoint with `duration = duration_minutes`.

### 5.2 Package details

```
GET /customer/packages/{id}
```

Returns the same object as one item of the list above.

### 5.3 Package quote

```
POST /customer/packages/quote
```

```json
{
  "package_id": 19,
  "start_at": "2026-10-15 15:00:00",
  "guest_count": 2,
  "hold_id": 2,
  "equipment": [ { "id": 5, "quantity": 1 } ],
  "hospitality": [ { "id": 2, "quantity": 4 } ],
  "promo_code": "SUMMER10",
  "points_to_redeem": 100
}
```

| Field | Required | Notes |
|---|---|---|
| `package_id` | yes | |
| `start_at` | yes | End time = start + `duration_minutes` |
| `guest_count` | yes | |
| `hold_id` | no | **Send it after the package hold is created** |
| `equipment`, `hospitality` | no | Extras on top of what the package includes |
| `promo_code`, `points_to_redeem` | no | |

Response (no extras):

```json
{
  "success": true,
  "message": null,
  "data": {
    "studio_id": 1,
    "package_id": 19,
    "booking_mode": "hourly",
    "days_count": null,
    "days": null,
    "start_at": "2026-10-15T15:00:00+00:00",
    "end_at": "2026-10-15T21:00:00+00:00",
    "line_items": [
      { "item_type": "package", "item_id": 19, "description": "Creator Day (6h)", "quantity": 1, "unit_price": 2200, "discount_amount": 0, "total_price": 2200, "included": false },
      { "item_type": "equipment", "item_id": 1, "description": "Sony A7 IV Camera (included in package)", "quantity": 1, "unit_price": 0, "discount_amount": 0, "total_price": 0, "included": true },
      { "item_type": "hospitality", "item_id": 2, "description": "Bottled Water (included in package)", "quantity": 8, "unit_price": 0, "discount_amount": 0, "total_price": 0, "included": true }
    ],
    "subtotal": 2200,
    "discount_amount": 0,
    "points_discount": 0,
    "points_to_redeem": null,
    "tax_amount": 0,
    "total_amount": 2200,
    "promo": null
  }
}
```

### 5.4 Package hold

```
POST /customer/packages/hold
```

```json
{
  "package_id": 19,
  "start_at": "2026-10-15 15:00:00",
  "guest_count": 2,
  "equipment": [ { "id": 5, "quantity": 1 } ],
  "hospitality": [ { "id": 2, "quantity": 4 } ]
}
```

Response `201` — note the hold is inside `data.hold`:

```json
{
  "success": true,
  "message": "Package hold created.",
  "data": {
    "hold": {
      "id": 2,
      "studio_id": 1,
      "booking_mode": "hourly",
      "start_at": "2026-10-15T15:00:00.000000Z",
      "end_at": "2026-10-15T21:00:00.000000Z",
      "expires_at": "2026-10-13T12:32:20.000000Z",
      "status": "active"
    },
    "package_id": 19,
    "duration_minutes": 360
  }
}
```

Then create the booking with `hold_id = data.hold.id` and `package_id` (section 7).

Package errors: `package_inactive`, `package_expired`, `package_not_valid`, `package_studio_mismatch`, `package_equipment_invalid`, `package_hospitality_invalid`.

---

## 6. Holds

A hold reserves a time slot for the customer for **15 minutes**, so nobody else can take it while they check out.

### 6.1 Create a hold (hourly)

```
POST /customer/booking-holds
```

```json
{
  "studio_id": 1,
  "start_at": "2026-10-15 10:00:00",
  "end_at": "2026-10-15 12:00:00",
  "guest_count": 2
}
```

Response `201`:

```json
{
  "success": true,
  "message": "Hold created.",
  "data": {
    "id": 1,
    "studio_id": 1,
    "booking_mode": "hourly",
    "start_at": "2026-10-15T10:00:00.000000Z",
    "end_at": "2026-10-15T12:00:00.000000Z",
    "expires_at": "2026-10-13T12:32:19.000000Z",
    "status": "active"
  }
}
```

- `start_at` / `end_at` must match a slot returned by the availability endpoint. Otherwise you get `slot_unavailable`.
- `expires_at` is a real UTC timestamp, so it is safe to convert. Use it for the countdown timer.

### 6.2 Create a hold (daily)

```json
{
  "studio_id": 1,
  "booking_mode": "daily",
  "start_date": "2026-10-18",
  "end_date": "2026-10-19",
  "guest_count": 2
}
```

```json
{
  "success": true,
  "message": "Hold created.",
  "data": {
    "id": 3,
    "studio_id": 1,
    "booking_mode": "daily",
    "start_at": "2026-10-18T08:00:00.000000Z",
    "end_at": "2026-10-19T23:00:00.000000Z",
    "expires_at": "2026-10-13T12:32:20.000000Z",
    "status": "active"
  }
}
```

The backend expands the dates to opening time on the first day through closing time on the last day.

### 6.3 Release a hold

```
DELETE /customer/booking-holds/{id}
```

Returns the hold with `"status": "cancelled"`. Errors: `hold_forbidden` (`403`, not your hold) and `hold_not_active`.

### Hold errors

| `code` | Meaning |
|---|---|
| `slot_unavailable` | Slot is not in the free slots list (taken, misaligned, or outside hours) |
| `conflicting_bookings` / `conflicting_holds` / `conflicting_blocks` | Time overlaps another booking, another hold, or a blocked period |
| `studio_closed` / `outside_opening_hours` | Studio closed at that time |
| `invalid_duration` / `invalid_duration_interval` | Duration outside 60–480 min or not a 30-min step |
| `invalid_time_range` | End before start, or a booking that crosses midnight |
| `capacity_exceeded` | `guest_count` is above the studio capacity |
| `studio_inactive` | Studio is disabled |

---

## 7. Quote & create booking

### 7.1 Quote

```
POST /customer/bookings/quote
```

**Hourly:**

```json
{
  "studio_id": 1,
  "hold_id": 1,
  "start_at": "2026-10-15 10:00:00",
  "end_at": "2026-10-15 12:00:00",
  "guest_count": 2,
  "equipment": [ { "id": 1, "quantity": 1 } ],
  "hospitality": [ { "id": 2, "quantity": 2 } ],
  "accessories": [ { "id": 3, "quantity": 1 } ],
  "promo_code": "SUMMER10",
  "points_to_redeem": 100
}
```

**Daily:**

```json
{
  "studio_id": 1,
  "hold_id": 3,
  "booking_mode": "daily",
  "start_date": "2026-10-18",
  "end_date": "2026-10-19",
  "guest_count": 2
}
```

| Field | Required | Notes |
|---|---|---|
| `studio_id` | yes | |
| `hold_id` | no | **Send it whenever a hold exists for this attempt.** Must be the current user's hold, otherwise `404` `hold_not_found`. |
| `booking_mode` | no | `hourly` (default) or `daily` |
| `start_at`, `end_at` | hourly | Same values as the hold |
| `start_date`, `end_date` | daily | Same values as the hold |
| `guest_count` | yes | |
| `package_id` | no | Prefer `POST /packages/quote` for packages |
| `equipment`, `hospitality`, `accessories` | no | `[{ "id", "quantity" }]` |
| `promo_code`, `points_to_redeem` | no | |

Hourly response (camera + 2 waters for 2 guests):

```json
{
  "success": true,
  "message": null,
  "data": {
    "studio_id": 1,
    "package_id": null,
    "booking_mode": "hourly",
    "days_count": null,
    "days": null,
    "start_at": "2026-10-15T10:00:00+00:00",
    "end_at": "2026-10-15T12:00:00+00:00",
    "line_items": [
      { "item_type": "studio", "item_id": 1, "description": "Studio rental", "quantity": 2, "unit_price": 500, "discount_amount": 0, "total_price": 1000 },
      { "item_type": "equipment", "item_id": 1, "description": "Sony A7 IV Camera", "quantity": 1, "unit_price": 250, "discount_amount": 0, "total_price": 250, "included": false },
      { "item_type": "hospitality", "item_id": 2, "description": "Bottled Water", "quantity": 2, "unit_price": 30, "discount_amount": 0, "total_price": 60, "included": false }
    ],
    "subtotal": 1310,
    "discount_amount": 0,
    "points_discount": 0,
    "points_to_redeem": null,
    "tax_amount": 0,
    "total_amount": 1310,
    "promo": null
  }
}
```

- `studio` line: `quantity` = hours, `unit_price` = effective hourly rate.
- For hospitality, `unit_price` = `total_price / quantity` (for `per_person` items it already includes the guest count).

Daily response:

```json
{
  "success": true,
  "message": null,
  "data": {
    "studio_id": 1,
    "package_id": null,
    "booking_mode": "daily",
    "days_count": 2,
    "days": [
      { "date": "2026-10-18", "open_time": "08:00", "close_time": "23:00", "daily_rate": 3500 },
      { "date": "2026-10-19", "open_time": "08:00", "close_time": "23:00", "daily_rate": 3500 }
    ],
    "start_at": "2026-10-18T08:00:00+03:00",
    "end_at": "2026-10-19T23:00:00+03:00",
    "line_items": [
      { "item_type": "studio", "item_id": 1, "description": "Studio daily rental (2 days)", "quantity": 2, "unit_price": 3500, "discount_amount": 0, "total_price": 7000 }
    ],
    "subtotal": 7000,
    "discount_amount": 0,
    "points_discount": 0,
    "points_to_redeem": null,
    "tax_amount": 0,
    "total_amount": 7000,
    "promo": null
  }
}
```

> In quote responses, use `line_items` and the amounts. For displaying the time, prefer the values you sent (or the hold's), since the timezone suffix on quote times is not consistent.

Quote error when `hold_id` is missing after creating a hold:

```json
{
  "success": false,
  "message": "Selected slot is no longer available.",
  "data": { "code": "conflicting_holds", "reason": "conflicting_holds", "conflicting_hold_ids": [1] }
}
```

### 7.2 Create the booking

```
POST /customer/bookings
```

```json
{
  "hold_id": 1,
  "guest_count": 2,
  "package_id": null,
  "equipment": [ { "id": 1, "quantity": 1 } ],
  "hospitality": [ { "id": 2, "quantity": 2 } ],
  "accessories": [ { "id": 3, "quantity": 1 } ],
  "promo_code": "SUMMER10",
  "points_to_redeem": 100,
  "customer_notes": "Need parking"
}
```

| Field | Required | Notes |
|---|---|---|
| `hold_id` | yes | Studio, dates and mode are taken from the hold |
| `guest_count` | yes | |
| `package_id` | for packages | Same package used for the package hold |
| `equipment`, `hospitality`, `accessories` | no | Same as the quote |
| `promo_code`, `points_to_redeem` | no | Same as the quote |
| `customer_notes` | no | Max 1000 chars |

There is **no** `payment_method` field. Bookings are cash on delivery.

Response `201`:

```json
{
  "success": true,
  "message": "Booking created.",
  "data": {
    "id": 1,
    "booking_number": "BK-20261013-3018E5",
    "studio_id": 1,
    "package_id": null,
    "booking_mode": "hourly",
    "start_at": "2026-10-15T10:00:00.000000Z",
    "end_at": "2026-10-15T12:00:00.000000Z",
    "guest_count": 2,
    "status": "confirmed",
    "subtotal": "1310.00",
    "discount_amount": "0.00",
    "points_discount": "0.00",
    "tax_amount": "0.00",
    "total_amount": "1310.00",
    "paid_amount": "0.00",
    "remaining_amount": "1310.00",
    "customer_notes": "Need parking",
    "studio": {
      "id": 1,
      "category_id": 1,
      "name": "Plateau Portrait Studio",
      "description": "Compact portrait studio with seamless backdrops and professional lighting.",
      "capacity": 6,
      "address": "12 Nile Corniche, Zamalek, Cairo",
      "latitude": "30.0626000",
      "longitude": "31.2197000",
      "rules": "No smoking inside the studio.\nHandle equipment with care.\nClean up after your session.",
      "is_active": true,
      "image": null
    },
    "accessories": []
  }
}
```

After success:

- Show a confirmation screen: **"Booking BK-20261013-3018E5 confirmed — pay 1310.00 EGP at the studio."** (`remaining_amount` is the amount due).
- **Do not** call any payment endpoint.
- The hold is consumed (status `converted`) and can't be reused.

Booking errors: all the hold, equipment, hospitality and package errors above, plus:

| `code` | Meaning |
|---|---|
| `hold_expired` | 15 minutes passed. Create a new hold. |
| `hold_not_active` | Hold already used or released |
| `hold_unauthorized` | Hold belongs to another user |
| `promo_invalid`, `promo_expired`, `promo_inactive`, `promo_not_started`, `promo_min_value`, `promo_usage_limit`, `promo_user_limit`, `promo_studio_restricted` | Promo code problems |
| `insufficient_points`, `min_redemption_not_met`, `redemption_not_configured` | Loyalty points problems |
| `accessory_insufficient_quantity`, `accessory_not_available` | Accessory add-on problems |

---

## 8. After booking

### 8.1 My bookings

```
GET /customer/bookings?page=1&per_page=15
```

Paginated list of booking objects (same shape as 7.2) with `meta`:

```json
{ "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 } }
```

### 8.2 Booking details

```
GET /customer/bookings/{id}
```

Same object as 7.2, including `studio` and `accessories`.

### 8.3 Booking statuses

| `status` | Show as |
|---|---|
| `confirmed` | Confirmed — pay at the studio |
| `in_progress` | Session in progress |
| `extended` | Session extended |
| `rescheduled` | Rescheduled |
| `completed` | Completed |
| `cancelled` | Cancelled |
| `no_show` | Missed |

> `pending` is no longer used for new bookings while Paymob is disabled.

Payment label: if `remaining_amount > 0`, show **"Pay {remaining_amount} EGP at the studio"**. Otherwise show **"Paid"**.

### 8.4 Cancel

```
POST /customer/bookings/{id}/cancel
```

```json
{ "reason": "Plans changed" }
```

`reason` is optional. Response: the booking with `"status": "cancelled"`. Error: `booking_not_cancellable` (already cancelled or completed).

### 8.5 Reschedule

```
POST /customer/bookings/{id}/reschedule
```

Hourly: `{ "start_at": "2026-10-16 10:00:00", "end_at": "2026-10-16 12:00:00", "reason": "..." }`
Daily: `{ "start_date": "2026-10-20", "end_date": "2026-10-21", "reason": "..." }`

Allowed while the booking is `confirmed` or `rescheduled`, within the reschedule policy limits (error `max_reschedules_reached`). After a successful reschedule, the booking status becomes `rescheduled`, which counts as an active booking. Any price difference or fee is added to `remaining_amount` (paid in cash).

### 8.6 Extend (hourly only)

```
POST /customer/bookings/{id}/extend
```

```json
{ "end_at": "2026-10-15 13:00:00" }
```

Allowed while the booking is `confirmed`, `rescheduled`, `in_progress` or `extended`. The extra amount is added to `total_amount` and `remaining_amount`. Daily bookings return `daily_extension_not_supported`.

### 8.7 Book again

```
POST /customer/bookings/{id}/book-again
```

```json
{ "start_at": "2026-10-22 10:00:00" }
```

Creates a new booking with the same studio, duration and add-ons. Returns `201` with the new booking.

---

## 9. Paymob (disabled) — what to remove in the app

| Item | Status |
|---|---|
| `POST /customer/bookings/{bookingId}/payments` | Disabled (`404`) |
| `GET /customer/payments/{id}` | Still available (shows cash payments recorded at the studio) |
| "Pay online" button, Paymob iframe / redirect, payment-result screen | Remove / hide |
| "Waiting for payment" / `pending` booking state | Not used anymore |

When online payment is re-enabled, this document will be updated. Bookings will go back to starting as `pending` and becoming `confirmed` after payment.
