<?php
class PwaController extends Controller {

    public function manifest(): void {
        $baseUrl = rtrim(APP_URL, '/');
        $manifest = [
            'name'             => defined('APP_NAME_GR') ? APP_NAME_GR : 'KinderLink',
            'short_name'       => 'KinderLink',
            'description'      => 'Ημερήσια επικοινωνία σχολείου-γονέων',
            'start_url'        => BASE_URL . '/',
            'scope'            => BASE_URL . '/',
            'display'          => 'standalone',
            'background_color' => '#fff8ef',
            'theme_color'      => '#087f83',
            'orientation'      => 'portrait-primary',
            'lang'             => 'el',
            'icons'            => [
                [
                    'src'     => $baseUrl . '/public/icons/icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => $baseUrl . '/public/icons/icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public function serviceWorker(): void {
        $baseUrl = BASE_URL;
      // Caches are origin-wide: never delete another application/install's caches.
      $cachePrefix = 'kinderlink-' . substr(hash('sha256', $baseUrl), 0, 12) . '-';
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . $baseUrl . '/');
        header('Cache-Control: no-store');
        echo <<<JS
const CACHE_PREFIX = '{$cachePrefix}';
const CACHE_NAME = CACHE_PREFIX + 'static-v1';
const STATIC_ASSETS = [
  '{$baseUrl}/public/kinderlink-mark.svg',
  '{$baseUrl}/public/icons/icon-192.png',
  '{$baseUrl}/public/icons/icon-512.png',
];

self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(STATIC_ASSETS.filter(Boolean));
    }).catch(function(){})
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.filter(function(k){ return k.startsWith(CACHE_PREFIX) && k !== CACHE_NAME; })
            .map(function(k){ return caches.delete(k); })
      );
    })
  );
  self.clients.claim();
});

// Only same-origin public assets are cached. Private records/photos/API never are.
self.addEventListener('fetch', function(e) {
  var url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== self.location.origin ||
      !url.pathname.startsWith('{$baseUrl}/public/')) return;
  // Network-first also refreshes assets whose URL has not changed across upgrades.
  e.respondWith(
    caches.open(CACHE_NAME).then(function(cache) {
      return fetch(e.request).then(function(resp) {
        if (resp.ok && resp.type === 'basic') {
          var copy = resp.clone();
          e.waitUntil(cache.put(e.request, copy));
        }
        return resp;
      }).catch(function() {
        return cache.match(e.request).then(function(cached) { return cached || Response.error(); });
      });
    })
  );
});
JS;
        exit;
    }
}
