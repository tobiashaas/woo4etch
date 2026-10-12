# JavaScript checks — `tests/js/`

Behaviour checks for the Store API cart layer
(`plugin/woo4etch/assets/store-api.js`) under [jsdom](https://github.com/jsdom/jsdom).
No WordPress, no browser, no network: the shipped script is loaded into a
jsdom window with a stubbed `fetch`, and the test drives it with real DOM
events.

```bash
cd tests/js
npm ci
npm test
```

What is asserted (issues [#41](https://github.com/tobiashaas/woo4etch/issues/41)
and [#42](https://github.com/tobiashaas/woo4etch/issues/42)):

| Area | Checks |
|---|---|
| Write queue (#41) | a second `form.cart` submit while a write is in flight is intercepted and queued — never the classic POST; both adds reach the Store API in order, each after the previous write's region refresh; `aria-busy` is set on the queued form and cleared on all regions/forms at the end |
| Repeat guard (#41) | a double click on one add button or one remove link yields one write; the queue keeps going after a rejected write, with one error notice |
| Early event (#42) | `woo4etch:cart-written` fires before the page re-fetch starts, with `detail.cart`, `detail.action`, `detail.request` and `detail.origin`; `woo4etch:cart-updated` follows the swap |
| Refresh opt-out (#42) | `preventDefault()` on `woo4etch:cart-written` and `data-w4e-refresh="none"` on an ancestor both skip the re-fetch for that write, while `cart-updated` still fires; a form outside the opt-out still refreshes |
| Checkout | a submit while a write is in flight is swallowed (no classic POST, no order); the next submit places the order once, queued behind the pending `update-customer` |

The suite runs in CI (`Store API script (jsdom)` job in
`.github/workflows/test.yml`). It is deliberately dependency-light — one
devDependency, plain Node asserts — so it stays as cheap as the PHP fast
checks in [`tests/php/`](../php/README.md).
