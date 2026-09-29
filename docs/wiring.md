# Wiring this in

## 1. Route

Add inside your existing admin route group (wherever `feed-sources` and `jobs` are registered):

```php
use App\Http\Controllers\Admin\WebhookRequestController;

Route::get('webhook-requests', [WebhookRequestController::class, 'index'])
    ->name('webhook-requests.index');
```

The admin group already adds the `admin.` name prefix and the `admin/` URL
prefix, so don't repeat them here. The full name ends up as
`admin.webhook-requests.index`, matching the other admin routes. Confirm with:

```
php artisan route:list --path=webhook
```

## 2. Nav link

Link to `route('admin.webhook-requests.index')` wherever the admin nav lives
(same place the Feed Sources / Jobs / Docs links are).

## 3. JS

`feed-sources.js` gets loaded somehow, likely imported in `resources/js/app.js`.
Add the same import for `webhook-requests.js`:

```js
import './webhook-requests';
```

(or whatever the existing import line for `feed-sources` looks like, mirror it)

## 4. Model check

This assumes `App\Models\WebhookRequest` already casts `payload_in` and
`payload_out` to `array` (your `model:show` output said "text / array", so
this should already be true). The Blade view passes the whole row to the
frontend via `@js($requests)`, and `formatPayload()` in the JS expects
`payload_in`/`payload_out` to already be plain JS objects/arrays, not
JSON strings.

## 5. Tests

`tests/Feature/Admin/WebhookRequestIndexTest.php` covers the viewer. It uses
`route('admin.webhook-requests.index')`, so it depends on step 1 producing
that exact name.
