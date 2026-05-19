# 12 — Store API & REST

WooCommerce exposes two HTTP APIs that you can use as data sources for Etch markup. Particularly useful for **Pages** (Cart, Mini-Cart, Account) where there's no automatic `{this.*}` context.

| API | Auth | Use for |
|---|---|---|
| **Store API** | None (cookie + nonce) | Customer-facing reads + writes — cart, checkout, products. The cleanest way to do AJAX cart/checkout. |
| **REST API v3** | API key or app password | Admin-level operations — orders, products, reports, customers. **Not** for public frontend code. |

## Store API

Base: `/wp-json/wc/store/v1/`

It's intentionally limited: no order history, no customer email lookup, no admin features. The endpoints exist so customer-facing frontends (the Cart/Checkout blocks, and your custom builds) can read and write cart state without exposing sensitive data.

### Useful endpoints

| Endpoint | Method | Use |
|---|---|---|
| `/products` | GET | List products with filtering |
| `/products/{id}` | GET | Single product with all data |
| `/cart` | GET | Current cart contents |
| `/cart/add-item` | POST | Add item (body: `id`, `quantity`, optional `variation`) |
| `/cart/update-item` | POST | Change quantity |
| `/cart/remove-item` | POST | Remove item |
| `/cart/apply-coupon` | POST | Apply a coupon code |
| `/cart/remove-coupon` | POST | Remove a coupon |
| `/cart/update-customer` | POST | Set shipping/billing address (recalculates shipping) |
| `/cart/select-shipping-rate` | POST | Choose a shipping method |
| `/cart/extensions` | POST | Trigger custom server-side actions you registered |
| `/checkout` | POST | Place the order |

### Nonce handling

Every write request needs the Store API nonce. WooCommerce exposes it via the `Nonce` HTTP response header from any GET to `/wp-json/wc/store/v1/cart`. Many frontends just do an initial GET to grab the nonce, then attach it on subsequent writes.

```js
// Bootstrap: fetch cart once, capture the nonce for future writes
async function bootCart() {
  const res = await fetch('/wp-json/wc/store/v1/cart');
  const cart = await res.json();
  const nonce = res.headers.get('Nonce');
  return { cart, nonce };
}

// Add an item
async function addToCart({ id, quantity, variation }, nonce) {
  return fetch('/wp-json/wc/store/v1/cart/add-item', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Nonce': nonce,
    },
    body: JSON.stringify({ id, quantity, variation }),
  }).then(r => r.json());
}
```

### Cart response shape (excerpt)

```json
{
  "items": [
    {
      "key": "abc123…",
      "id": 42,
      "quantity": 2,
      "name": "Trail Runner X",
      "short_description": "…",
      "images": [{ "src": "…", "alt": "…" }],
      "variation": [
        { "attribute": "Size", "value": "42" }
      ],
      "prices": {
        "price": "12900",
        "regular_price": "12900",
        "sale_price": "12900",
        "currency_code": "EUR",
        "currency_minor_unit": 2
      },
      "totals": {
        "line_subtotal": "25800",
        "line_total": "25800"
      }
    }
  ],
  "totals": {
    "total_items": "25800",
    "total_shipping": "499",
    "total_price": "26299",
    "currency_code": "EUR",
    "currency_minor_unit": 2
  },
  "shipping_rates": [ … ],
  "needs_shipping": true,
  "needs_payment": true
}
```

**Important:** prices are returned as **integer strings in the smallest currency unit** (`12900` = €129.00). Divide by `10 ** currency_minor_unit` for display. Use `wc_price()` server-side or format on the client.

## Extending the Store API with custom data

You can attach plugin- or theme-specific data to the cart, checkout, or product endpoints — useful if Etch needs to read custom fields you've added.

### Add data to the Cart endpoint

```php
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

add_action('woocommerce_blocks_loaded', function () {
    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => CartSchema::IDENTIFIER,
        'namespace'       => 'kr_shop',
        'data_callback'   => function () {
            return [
                'rewardPoints' => (int) WC()->session->get('reward_points', 0),
                'minOrderValue' => 25.00,
            ];
        },
        'schema_callback' => function () {
            return [
                'rewardPoints' => [
                    'description' => 'Points the customer will earn for this order',
                    'type'        => 'integer',
                    'readonly'    => true,
                ],
                'minOrderValue' => [
                    'description' => 'Minimum order value in store currency',
                    'type'        => 'number',
                    'readonly'    => true,
                ],
            ];
        },
        'schema_type'     => ARRAY_A,
    ]);
});
```

Your custom data appears under `extensions.kr_shop` in the Store API cart response:

```json
{
  "items": [ … ],
  "extensions": {
    "kr_shop": {
      "rewardPoints": 250,
      "minOrderValue": 25
    }
  }
}
```

### Add data to a product

```php
use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

add_action('woocommerce_blocks_loaded', function () {
    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => ProductSchema::IDENTIFIER,
        'namespace'       => 'kr_shop',
        'data_callback'   => function ($product) {
            return [
                'manufacturer' => $product->get_attribute('pa_hersteller'),
                'capacity'     => $product->get_attribute('pa_fuellmenge'),
            ];
        },
        'schema_callback' => function () {
            return [
                'manufacturer' => ['type' => 'string'],
                'capacity'     => ['type' => 'string'],
            ];
        },
        'schema_type'     => ARRAY_A,
    ]);
});
```

### Add per-cart-item data

```php
woocommerce_store_api_register_endpoint_data([
    'endpoint'      => CartItemSchema::IDENTIFIER,
    'namespace'     => 'kr_shop',
    'data_callback' => function ($cart_item) {
        $product = $cart_item['data'];
        return [
            'isPreOrder' => (bool) $product->get_meta('_pre_order'),
        ];
    },
    'schema_callback' => function () {
        return [ 'isPreOrder' => ['type' => 'boolean'] ];
    },
    'schema_type' => ARRAY_A,
]);
```

## A custom REST endpoint for Etch

When the Store API doesn't fit your shape (e.g. you want a single response that joins user + cart + recent orders), register your own endpoint:

```php
add_action('rest_api_init', function () {
    register_rest_route('kr-shop/v1', '/dashboard', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            $user = wp_get_current_user();
            $cart = WC()->cart;

            $recent_orders = [];
            if ($user->ID) {
                $orders = wc_get_orders([
                    'customer' => $user->ID,
                    'limit'    => 3,
                    'orderby'  => 'date',
                    'order'    => 'DESC',
                ]);
                foreach ($orders as $order) {
                    $recent_orders[] = [
                        'id'     => $order->get_id(),
                        'number' => $order->get_order_number(),
                        'date'   => $order->get_date_created()->date('Y-m-d'),
                        'total'  => wc_price($order->get_total()),
                        'status' => wc_get_order_status_name($order->get_status()),
                        'url'    => $order->get_view_order_url(),
                    ];
                }
            }

            return [
                'user' => [
                    'id'          => $user->ID,
                    'displayName' => $user->display_name,
                    'loggedIn'    => (bool) $user->ID,
                ],
                'cart' => [
                    'count'    => $cart->get_cart_contents_count(),
                    'subtotal' => wc_price($cart->get_subtotal()),
                ],
                'recentOrders' => $recent_orders,
            ];
        },
    ]);
});
```

Call from the frontend:

```js
const data = await fetch('/wp-json/kr-shop/v1/dashboard').then(r => r.json());
document.querySelector('.user-name').textContent = data.user.displayName;
```

## Authentication for the REST v3 API

The REST v3 API (`/wp-json/wc/v3/`) is for backend/admin tools — order management, product import/export, reports. It needs an API key generated in `WooCommerce → Settings → Advanced → REST API`.

```bash
# Curl example — list the latest 5 orders
curl "https://example.com/wp-json/wc/v3/orders?per_page=5" \
  -u ck_abcdef1234567890:cs_abcdef1234567890
```

Never embed REST API keys in frontend code — they grant admin-level access.

## Quick decision guide

| Need | Use |
|---|---|
| Show cart contents on the frontend | Store API `GET /cart` |
| Add to cart from custom HTML | Store API `POST /cart/add-item` |
| Custom product field readable by JS | Extend Store API product endpoint |
| Backend script that creates orders | REST v3 `POST /orders` |
| Reports / analytics dashboard | REST v3 `GET /reports/*` |
| Custom mixed payload (user + cart + …) | Your own `register_rest_route` |

## Sources

- WooCommerce — [Store API reference](https://developer.woocommerce.com/docs/apis/store-api/)
- WooCommerce — [Extending Store API](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/available-endpoints-to-extend)
- WooCommerce — [REST API v3 reference](https://woocommerce.github.io/woocommerce-rest-api-docs/)
