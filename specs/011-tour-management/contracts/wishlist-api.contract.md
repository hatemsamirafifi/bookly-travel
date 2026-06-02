# Contract: Wishlist API

**Consumer**: Next.js frontend (authenticated traveler pages)  
**Provider**: Laravel API (`/api/traveler/*`)  
**Authentication**: Laravel Sanctum cookie-based session

---

## 1. Get Wishlist

```
GET /api/traveler/wishlist
```

### Query Parameters
| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| page      | number | No       | Pagination (default: 1) |
| per_page  | number | No       | Default: 12 |

### Success Response (200)
```json
{
  "data": [
    {
      "id": "wishlist-uuid",
      "tour": {
        "id": "tour-uuid",
        "name": "Rome Colosseum Underground Tour",
        "cover_image": "https://cdn.example.com/tours/123.jpg",
        "slug": "rome-colosseum-underground-tour",
        "price": 7500,
        "rating": 4.8,
        "review_count": 124,
        "location": "Rome, Italy",
        "duration": "3 hours",
        "is_available": true
      },
      "added_at": "2026-05-18T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 12,
    "total": 15
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |

---

## 2. Add to Wishlist

```
POST /api/traveler/wishlist
```

### Request Body
```json
{
  "tour_id": "tour-uuid"
}
```

### Success Response (201)
```json
{
  "data": {
    "id": "wishlist-uuid",
    "tour_id": "tour-uuid",
    "added_at": "2026-05-20T10:30:00Z"
  }
}
```

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 409  | Tour already in wishlist |
| 422  | Invalid `tour_id` |

---

## 3. Remove from Wishlist

```
DELETE /api/traveler/wishlist/{tour_id}
```

### Success Response (204)
No body.

### Error Responses
| Code | Scenario |
|------|----------|
| 401  | Unauthenticated |
| 404  | Tour not in wishlist |

---

## 4. Check Wishlist Status (Bulk)

```
GET /api/traveler/wishlist/status?tour_ids[]=uuid1&tour_ids[]=uuid2
```

### Success Response (200)
```json
{
  "data": {
    "tour-uuid-1": true,
    "tour-uuid-2": false
  }
}
```

*Note: Used when rendering a list of tour cards to show filled/outline hearts without N+1 requests.*
