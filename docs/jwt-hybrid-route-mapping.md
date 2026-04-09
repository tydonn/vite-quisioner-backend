# JWT Hybrid Route Mapping

Dokumen ini memetakan endpoint lama (Sanctum) ke endpoint baru (JWT) untuk migrasi bertahap frontend.

## Auth

- `POST /api/login` -> deprecated (`410`), gunakan `POST /api/jwt/login`
- `GET /api/me` -> `GET /api/jwt/me`
- `POST /api/logout` -> `POST /api/jwt/logout`

## Responses (Read First)

- `GET /api/responses/filter-options/prodi` -> `GET /api/jwt/responses/filter-options/prodi`
- `GET /api/responses/filter-options/matakuliah` -> `GET /api/jwt/responses/filter-options/matakuliah`
- `GET /api/responses/count-respondents` -> `GET /api/jwt/responses/count-respondents`
- `GET /api/responses` -> `GET /api/jwt/responses`
- `GET /api/responses/{id}` -> `GET /api/jwt/responses/{id}`

## Response Details (Dashboard + Export)

- `GET /api/response-details` -> `GET /api/jwt/response-details`
- `GET /api/response-details/{id}` -> `GET /api/jwt/response-details/{id}`
- `GET /api/response-details/download` -> `GET /api/jwt/response-details/download`
- `GET /api/response-details/satisfaction-labels` -> `GET /api/jwt/response-details/satisfaction-labels`
- `GET /api/response-details/label-counts` -> `GET /api/jwt/response-details/label-counts`

## Master Data

### Categories

- `GET /api/categories/count` -> `GET /api/jwt/categories/count`
- `GET /api/categories` -> `GET /api/jwt/categories`
- `GET /api/categories/{id}` -> `GET /api/jwt/categories/{id}`
- `POST /api/categories` -> `POST /api/jwt/categories`
- `PUT/PATCH /api/categories/{id}` -> `PUT/PATCH /api/jwt/categories/{id}`
- `DELETE /api/categories/{id}` -> `DELETE /api/jwt/categories/{id}`

### Questions

- `GET /api/questions/count` -> `GET /api/jwt/questions/count`
- `GET /api/questions` -> `GET /api/jwt/questions`
- `GET /api/questions/{id}` -> `GET /api/jwt/questions/{id}`
- `POST /api/questions` -> `POST /api/jwt/questions`
- `PUT/PATCH /api/questions/{id}` -> `PUT/PATCH /api/jwt/questions/{id}`
- `DELETE /api/questions/{id}` -> `DELETE /api/jwt/questions/{id}`

### Choices

- `GET /api/choices` -> `GET /api/jwt/choices`
- `GET /api/choices/{id}` -> `GET /api/jwt/choices/{id}`
- `POST /api/choices` -> `POST /api/jwt/choices`
- `PUT/PATCH /api/choices/{id}` -> `PUT/PATCH /api/jwt/choices/{id}`
- `DELETE /api/choices/{id}` -> `DELETE /api/jwt/choices/{id}`

### Choice Types

- `GET /api/choice-types` -> `GET /api/jwt/choice-types`
- `GET /api/choice-types/{id}` -> `GET /api/jwt/choice-types/{id}`
- `POST /api/choice-types` -> `POST /api/jwt/choice-types`
- `PUT/PATCH /api/choice-types/{id}` -> `PUT/PATCH /api/jwt/choice-types/{id}`
- `DELETE /api/choice-types/{id}` -> `DELETE /api/jwt/choice-types/{id}`

### Dosen

- `GET /api/dosen` -> `GET /api/jwt/dosen`
- `GET /api/dosen/{id}` -> `GET /api/jwt/dosen/{id}`
- `POST /api/dosen` -> `POST /api/jwt/dosen`
- `PUT/PATCH /api/dosen/{id}` -> `PUT/PATCH /api/jwt/dosen/{id}`
- `DELETE /api/dosen/{id}` -> `DELETE /api/jwt/dosen/{id}`

## Health

- Saat ini endpoint health masih route Sanctum/internal:
- `GET /api/health/db/quisioner`
- `GET /api/health/db/siakad`
- Jika nanti frontend non-admin perlu akses, endpoint ini bisa diduplikasi ke `/api/jwt/health/...`.

## Header

Gunakan header yang sama untuk route JWT:

`Authorization: Bearer <access_token>`

## Operational Notes

- Checklist hardening dan kriteria cutover tersedia di `docs/jwt-cutover-readiness.md`.
