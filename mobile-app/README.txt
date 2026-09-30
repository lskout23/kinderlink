KinderLink Mobile (Capacitor local-assets app)

Goal
- Native-wrapper app that ships local UI assets (better App Store position than a remote website wrapper).
- Uses Bearer token auth against existing KinderLink API.
- Supports parent, teacher, admin login with one flow.

Current status
- Implemented screens:
  1) API settings + connectivity status
  2) Login (token issue)
  3) Parent dashboard (children + daily messages)
  4) Staff inbox quick view (thread list, thread messages, reply)
- Android platform scaffolded: mobile-app/android

Backend requirements (already added in this repo)
- POST /api/auth/token
- POST /api/auth/me
- POST /api/auth/revoke
- POST /api/parent/children
- POST /api/parent/messages
- Existing inbox endpoints for staff/parent

Run (Windows)
1) Ensure Apache/PHP app runs locally (example: http://127.0.0.1:8094)
2) In terminal:
   cd mobile-app
   npm install
   npx cap sync
   npx cap open android
3) In app, set API Base URL:
   - Android Emulator: http://10.0.2.2:8094
   - Physical Android: http://<PC-LAN-IP>:8094

Local API CORS note
- If running inside native WebView and API is reached cross-origin, set:
  API_ALLOWED_ORIGINS=http://localhost,capacitor://localhost
  in KinderLink .env.local, then restart web server.

Next planned improvements
- Parent: richer cards (photos, attachments when API is ready)
- Staff: delete message/thread and unread badges in-app
- Native push notifications (FCM/APNs) after store provisioning
