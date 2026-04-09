# JWT Hardening and Cutover Readiness

Dokumen ini dipakai sebagai checklist operasional sebelum JWT dijadikan default.

## 1) Security and Config Checklist

- [ ] `JWT_SECRET` hanya disimpan di `.env` server (tidak di-commit).
- [ ] `JWT_TTL` ditetapkan eksplisit di `.env` (contoh: `JWT_TTL=60`).
- [ ] `JWT_REFRESH_TTL` ditetapkan eksplisit di `.env` walau refresh belum dipakai.
- [ ] `JWT_LOGIN_RATE_LIMIT` dan `JWT_LOGIN_RATE_LIMIT_MINUTES` ditetapkan di `.env`.
- [ ] Runtime extension PHP aktif:
- `sodium`
- `zip`
- `gd` (dibutuhkan untuk stack export/spreadsheet tertentu).

## 2) Auth Reliability Checklist

- [ ] Login JWT: `POST /api/jwt/login` menghasilkan token.
- [ ] Auth check JWT: `GET /api/jwt/me` mengembalikan payload `success + user`.
- [ ] Logout JWT: `POST /api/jwt/logout` meng-invalidasi token.
- [ ] Token lama setelah logout selalu `401` JSON.
- [ ] Token invalid/expired/blacklisted selalu `401` JSON dengan shape konsisten.

## 3) Observability and Alerts

Log event yang sudah tersedia:

- `jwt.login.success`
- `jwt.login.failed`
- `jwt.logout.success`
- `jwt.logout.failed`
- `auth.unauthenticated`
- `jwt.token.invalid`

Alert minimum yang direkomendasikan:

- Lonjakan `401` (`auth.unauthenticated` + `jwt.token.invalid`) > baseline normal.
- Lonjakan `jwt.login.failed` per IP atau per email dalam interval pendek.

## 4) Sanctum Retirement Criteria

Sanctum bisa dipensiunkan setelah semua kondisi berikut terpenuhi:

- [ ] 100% endpoint frontend sudah menggunakan `/api/jwt/*`.
- [ ] Tidak ada request berarti ke route Sanctum selama 7 hari.
- [ ] Tidak ada lonjakan alert auth pada jam sibuk selama 7 hari.
- [ ] Uji regresi post-cutover lulus untuk modul utama:
- responses
- response-details (termasuk download/export)
- master data (categories/questions/choices/choice-types/dosen).

