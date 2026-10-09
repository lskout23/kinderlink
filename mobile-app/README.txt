KinderLink Mobile (Capacitor local-assets app)

Goal
- Native-wrapper app that ships local UI assets (better App Store position than a remote website wrapper).
- Uses Bearer token auth against existing KinderLink API.
- Supports parent, teacher, admin login with one flow.

Current status
- Implemented screens (admin/teacher/parent, full parity with web app):
  Dashboard, Children, Groups, Children-per-Group, Activities, Users,
  Messages Create (ratings, activities bulk-apply, attendance, photos,
  email preview, send), Messages List (search + bulk delete), Free Email,
  Inbox (thread list/messages/reply/delete/new-message + unread badge),
  Financial (Income, Income Totals, Activities, Expenses), Parameters
  (incl. attendance migration), Email Template, Personal Settings.
- Android platform scaffolded: mobile-app/android

IMPORTANT: after any `npx cap add android` (fresh scaffold), you must
manually add this attribute to android/app/src/main/AndroidManifest.xml
on the <application> tag, or the app CANNOT reach any http:// API server
(Android 9+ blocks cleartext traffic by default):
    android:usesCleartextTraffic="true"
This has already been applied to the current android/ folder, but it is
NOT tracked by git (android/ is gitignored) and is NOT restored by
`npx cap sync` — only by re-adding it manually after a fresh `cap add android`.

Backend requirements (already added in this repo)
- POST /api/auth/token, /api/auth/me, /api/auth/revoke
- Full /api/* surface mirroring the web app (children, groups, activities,
  users, messages incl. photos, financial, parameters, email-template,
  personal, inbox) — see core/Router.php for the complete list.

Run (Windows)
1) Ensure Apache/PHP app runs locally (example: http://127.0.0.1:8094)
2) After editing mobile-app/www/*, always run `npx cap sync android`
   before building/running the Android app, or it will use stale assets.
3) In terminal:
   cd mobile-app
   npm install
   npx cap sync
   npx cap open android
4) In app, set API Base URL:
   - Android Emulator: http://10.0.2.2:8094
   - Physical Android: http://<PC-LAN-IP>:8094

KNOWN ISSUE on locked-down/corporate machines (e.g. VPN/EDR software):
- If the emulator shows "offline" and http://10.0.2.2:8094 fails with
  net::ERR_EMPTY_RESPONSE (checked via chrome://inspect), Windows Firewall
  is NOT the cause (confirmed even with it fully disabled). Corporate
  VPN/EDR network filtering is blocking the emulator's virtual NAT path.
- Workaround: tunnel through ADB instead of the emulator's virtual network.
    $env:Path += ";$env:LOCALAPPDATA\Android\Sdk\platform-tools"
    adb reverse tcp:8094 tcp:8094
  Then in the app, set API Base URL to http://127.0.0.1:8094 (not 10.0.2.2).
  Must be re-run every time the emulator is restarted.

Local API CORS note
- If running inside native WebView and API is reached cross-origin, set:
  API_ALLOWED_ORIGINS=http://localhost,capacitor://localhost
  in KinderLink .env.local, then restart web server.

Next planned improvements
- Photo bulk purge / ZIP download in-app (email already has ZIP link)
- Native push notifications (FCM/APNs) after store provisioning
