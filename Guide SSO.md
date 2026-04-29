Guide SSO (Final, Tanpa Exchange di Web A)

.env
WEB_A_SSO_SECRET=7JqSPXqf2gnnMEBqKxlRIZFHSEcIG1AmDxl5Q098odE
WEB_A_SSO_ISSUER=web-sso
WEB_A_SSO_AUDIENCE=

1. Flow Ringkas

User login di Web A.
Web A backend kirim POST ke Quisioner: /api/jwt/sso/init.
Quisioner balas sso_code.
Web A redirect browser ke:
https://edom.unmuhjember.ac.id/sso/callback?code=<sso_code>
Frontend Quisioner yang melakukan POST /api/jwt/sso/exchange (internal frontend flow).


2. Endpoint Yang Dipakai Web A

POST /api/jwt/sso/init
Web A hanya perlu endpoint ini.


3. Body Wajib ke /api/jwt/sso/init

{
  "issuer": "web-sso",
  "request_id": "req-1714377600000",
  "timestamp": "2026-04-29T08:00:00Z",
  "nonce": "nonce-1714377600000",
  "username": "userTI",
  "fullname": "User Test TI",
  "roles": ["dosen"],
  "program_code": "1065",
  "email": "userTI@test.local",
  "signature": "<hex_hmac_sha256>"
}


Field rules:

issuer: wajib, harus match config backend Quisioner.
request_id: wajib, unik per request.
timestamp: wajib, ISO8601 UTC.
nonce: wajib, unik (anti replay).
username: wajib.
fullname: wajib.
roles: wajib array, minimal 1 role, contoh ["administrator"] atau ["dosen"].
program_code: wajib, kode prodi.
email: opsional tapi direkomendasikan.
signature: wajib.


4. Aturan Signature
String yang ditandatangani:

issuer|request_id|timestamp|nonce|username|fullname|roles_csv|program_code|email
roles_csv = join roles pakai koma.

Rumus:

signature = HMAC_SHA256(payload_string, WEB_A_SSO_SECRET)


5. Response Sukses dari Init

{
  "success": true,
  "sso_code": "JWYMvJ1Q0phz2k80ApUoIo92slB48tdQKb8Qz7nt9YM3CEqh0tuAZxFPZHJ8vWHl",
  "expires_in": 60
}


6. Redirect Yang Harus Dilakukan Web A
Setelah dapat sso_code, redirect user:

https://edom.unmuhjember.ac.id/sso/callback?code=<sso_code>


Catatan:

sso_code one-time use.
TTL saat ini pendek (default 60 detik), jadi redirect harus segera.
7. Error Handling Yang Perlu Disiapkan Web A

401: issuer/signature/timestamp invalid.
409: nonce replay.
422: payload invalid.
429: rate limit.
500: server config issue.


8. Rule Akses Data Setelah Login (Info untuk mapping role)

administrator -> full access.
non-admin + program_code -> scoped per prodi.
non-admin tanpa program_code -> ditolak (403).