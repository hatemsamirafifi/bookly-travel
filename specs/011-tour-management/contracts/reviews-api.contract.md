# Contract: Reviews API (Read-Only for Tour Management)

**Consumer**: Next.js frontend (authenticated traveler pages)  
**Provider**: Laravel API (`/api/traveler/*`)  
**Authentication**: Laravel Sanctum cookie-based session

---

## 1. List My Reviews

```
GET /api/traveler/reviews
```

### Query Parameters
| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| page      | number | No       | Pagination (default: 1) |
| per_page  | number | No       | Default: 10 |

### Success Response (200)
```json
{
  "data": [
    {
      "id": "review-uuid",
      "tour": {
        "id": "tour-uuid",
        "name": "Rome Colosseum Underground Tour",
        "slug": "rome-colosseum-underground-tour"
      },
      "rating": 5,
      "text": "Absolutely incredible experience! Our guide was so knowledgeable.",
      "submitted_at": "2026-05-10T14:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 2
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |

---

*Review submission remains in the scope of spec 009 (Reviews & Ratings). Tour Management (spec 011) only consumes the read endpoint for the My Reviews page.*
