import json
import os
import uuid

ROUTES_FILE = os.path.join(os.path.dirname(__file__), "..", "storage", "app", "routes.json")
OUTPUT_FILE = os.path.join(os.path.dirname(__file__), "..", "docs", "postman", "Al-Plateau-Studios-API.postman_collection.json")

SAMPLE_BODIES = {
    "POST api/v1/auth/register": {
        "name": "John Doe",
        "phone": "+201234567890",
        "email": "john@example.com",
        "password": "password123",
    },
    "POST api/v1/auth/login": {"phone": "+201000000003", "password": "password"},
    "POST api/v1/auth/verify-otp": {"phone": "+201234567890", "code": "123456"},
    "POST api/v1/auth/login-otp": {"phone": "+201234567890", "code": "123456"},
    "POST api/v1/auth/forgot-password": {"phone": "+201234567890"},
    "POST api/v1/auth/reset-password": {
        "phone": "+201234567890",
        "code": "123456",
        "password": "newpassword123",
    },
    "POST api/v1/customer/booking-holds": {
        "studio_id": 1,
        "start_at": "2026-09-15T10:00:00",
        "end_at": "2026-09-15T12:00:00",
        "guest_count": 4,
    },
    "POST api/v1/customer/bookings/quote": {
        "studio_id": 1,
        "start_at": "2026-09-15T10:00:00",
        "end_at": "2026-09-15T12:00:00",
        "guest_count": 4,
        "equipment": [{"equipment_id": 1, "quantity": 1}],
        "hospitality": [{"hospitality_item_id": 1, "quantity": 2}],
    },
    "POST api/v1/customer/bookings": {
        "hold_id": 1,
        "guest_count": 4,
        "promo_code": "WELCOME10",
        "points_to_redeem": 0,
        "customer_notes": "Need extra lighting",
    },
    "POST api/v1/customer/bookings/{bookingId}/payments": {"type": "full"},
    "POST api/v1/customer/promo-codes/validate": {
        "code": "WELCOME10",
        "studio_id": 1,
        "subtotal": 500.0,
    },
    "POST api/v1/customer/devices": {
        "device_token": "fcm_token_here",
        "platform": "android",
    },
    "POST api/v1/customer/favorites": {"studio_id": 1},
    "POST api/v1/customer/reviews": {
        "booking_id": 1,
        "rating": 4.5,
        "comment": "Great studio!",
        "dimensions": [{"dimension_id": 1, "score": 5}],
    },
    "POST api/v1/admin/categories": {
        "name": "Photography",
        "description": "Photo studios",
        "sort_order": 1,
        "is_active": True,
    },
    "POST api/v1/admin/studios": {
        "category_id": 1,
        "name": "New Studio",
        "capacity": 10,
        "address": "Cairo, Egypt",
        "is_active": True,
    },
}


def folder_name(uri: str) -> str:
    parts = uri.replace("api/v1/", "").split("/")
    if parts[0] == "auth":
        return "Auth"
    if parts[0] == "customer":
        label = parts[1].replace("-", " ").title() if len(parts) > 1 else "General"
        return f"Customer / {label}"
    if parts[0] == "admin":
        label = parts[1].replace("-", " ").title() if len(parts) > 1 else "General"
        return f"Admin / {label}"
    if parts[0] == "webhooks":
        return "Webhooks"
    return "Other"


def auth_type(uri: str, middleware: list) -> str | None:
    if not any("Authenticate:sanctum" in m for m in middleware):
        return None
    if uri.startswith("api/v1/admin/"):
        return "admin"
    return "customer"


def build_url(uri: str) -> str:
    replacements = {
        "{id}": "1",
        "{studioId}": "1",
        "{bookingId}": "1",
        "{paymentId}": "1",
        "{equipmentId}": "1",
        "{itemId}": "1",
        "{overrideId}": "1",
        "{blockId}": "1",
        "{recipientId}": "1",
        "{slug}": "terms",
        "{saved_setup}": "1",
    }
    path = uri
    for k, v in replacements.items():
        path = path.replace(k, v)
    return "{{base_url}}/" + path


def _login_request(name: str, phone: str, token_var: str) -> dict:
    return {
        "name": name,
        "event": [
            {
                "listen": "test",
                "script": {
                    "exec": [
                        "const res = pm.response.json();",
                        f"if (res.success && res.data && res.data.token) {{",
                        f"  pm.collectionVariables.set('{token_var}', res.data.token);",
                        "}",
                    ],
                    "type": "text/javascript",
                },
            }
        ],
        "request": {
            "method": "POST",
            "header": [
                {"key": "Accept", "value": "application/json"},
                {"key": "Content-Type", "value": "application/json"},
            ],
            "body": {
                "mode": "raw",
                "raw": json.dumps({"phone": phone, "password": "password"}, indent=2),
                "options": {"raw": {"language": "json"}},
            },
            "url": "{{base_url}}/api/v1/auth/login",
        },
        "response": [],
    }


def main() -> None:
    with open(ROUTES_FILE, encoding="utf-8-sig") as f:
        routes = json.load(f)

    folders: dict[str, list] = {}
    for route in routes:
        method = route["method"].split("|")[0]
        if method == "HEAD":
            continue

        uri = route["uri"]
        fname = folder_name(uri)
        auth = auth_type(uri, route.get("middleware", []))
        key = f"{method} {uri}"

        item = {
            "name": key,
            "request": {
                "method": method,
                "header": [{"key": "Accept", "value": "application/json"}],
                "url": build_url(uri),
            },
            "response": [],
        }

        if method in ("POST", "PUT", "PATCH"):
            item["request"]["header"].append(
                {"key": "Content-Type", "value": "application/json"}
            )
            body = SAMPLE_BODIES.get(key, {})
            item["request"]["body"] = {
                "mode": "raw",
                "raw": json.dumps(body, indent=2) if body else "{}",
                "options": {"raw": {"language": "json"}},
            }

        if auth == "customer":
            item["request"]["auth"] = {
                "type": "bearer",
                "bearer": [
                    {"key": "token", "value": "{{customer_token}}", "type": "string"}
                ],
            }
        elif auth == "admin":
            item["request"]["auth"] = {
                "type": "bearer",
                "bearer": [
                    {"key": "token", "value": "{{admin_token}}", "type": "string"}
                ],
            }

        if key == "POST api/v1/auth/login" and item["request"].get("body"):
            item["event"] = [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "const res = pm.response.json();",
                            "if (res.success && res.data && res.data.token) {",
                            "  const phone = JSON.parse(pm.request.body.raw).phone;",
                            "  if (phone === '+201000000001' || phone === '+201000000002') {",
                            "    pm.collectionVariables.set('admin_token', res.data.token);",
                            "  } else {",
                            "    pm.collectionVariables.set('customer_token', res.data.token);",
                            "  }",
                            "}",
                        ],
                        "type": "text/javascript",
                    },
                }
            ]

        folders.setdefault(fname, []).append(item)

    collection = {
        "info": {
            "_postman_id": str(uuid.uuid4()),
            "name": "Al-Plateau Studios API",
            "description": (
                "Complete API collection for Al-Plateau Studios booking platform. "
                "Set base_url, then run Auth > Login (Customer/Admin) to populate tokens."
            ),
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "variable": [
            {"key": "base_url", "value": "http://127.0.0.1:8000"},
            {"key": "customer_token", "value": ""},
            {"key": "admin_token", "value": ""},
        ],
        "item": [
            {
                "name": "0. Quick Start",
                "item": [
                    _login_request("Customer Login", "+201000000003", "customer_token"),
                    _login_request("Admin Login", "+201000000001", "admin_token"),
                ],
            },
            *[{"name": k, "item": folders[k]} for k in sorted(folders.keys())],
        ],
    }

    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)
    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(collection, f, indent=2)

    print(f"Generated {len(routes)} routes into {len(folders)} folders -> {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
