/**
 * Layer 2b — the Store API cart layer under jsdom (issues #41, #42).
 *
 * Loads the shipped script into a jsdom window with a stubbed fetch and
 * asserts the write queue (a second interaction mid-write is queued, never
 * the classic POST; a repeat from the same control is dropped), the event
 * order (woo4etch:cart-written before the page re-fetch, cart-updated after),
 * the refresh opt-outs (preventDefault, data-w4e-refresh="none"), and the
 * checkout's queued order placement. Run: npm test (in tests/js).
 */
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(process.argv[2] || path.join(__dirname, '..', '..', 'plugin', 'woo4etch', 'assets', 'store-api.js'), 'utf8');

let failures = 0, checks = 0;
function ok(cond, label) { checks++; if (!cond) { failures++; console.log('  FAIL ' + label); } else console.log('  ok   ' + label); }
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

function page(markup) {
    return `<!doctype html><html><body>
<div class="w4e-notices"></div>
<div data-w4e-cart-region="mini" class="w4e-minicart"><span class="mini-cart-count">0</span></div>
${markup}
</body></html>`;
}

function boot(markup, opts) {
    opts = opts || {};
    const vc = new VirtualConsole();
    const jsdomErrors = [];
    vc.on('jsdomError', (e) => jsdomErrors.push(String(e.message || e)));
    const dom = new JSDOM(page(markup), { url: 'https://shop.test/search/?s=x', runScripts: 'outside-only', virtualConsole: vc });
    const w = dom.window;
    const calls = [];
    let count = 0;
    w.w4eStoreApi = { restUrl: 'https://shop.test/wp-json/wc/store/v1', nonce: 'n', checkoutGateways: ['cod'] };
    w.fetch = function (url, init) {
        const rec = { url: String(url), method: (init && init.method) || 'GET', body: init && init.body ? JSON.parse(init.body) : null, t: Date.now() };
        calls.push(rec);
        const delay = opts.delay || 30;
        const fail = opts.failUrl && rec.url.indexOf(opts.failUrl) !== -1;
        return sleep(delay).then(() => {
            rec.done = Date.now();
            if (rec.url.indexOf('/wc/store/v1/') !== -1) {
                if (fail) return { ok: false, status: 400, statusText: 'Bad', headers: { get: () => null }, json: () => Promise.resolve({ message: 'Nope' }) };
                count++;
                return { ok: true, status: 200, headers: { get: () => null }, json: () => Promise.resolve({ items_count: count, action: rec.url.split('/').pop() }) };
            }
            // page refresh
            rec.kind = 'refresh';
            return { ok: true, status: 200, headers: { get: () => null }, text: () => Promise.resolve(page(markup).replace('>0<', '>' + count + '<')) };
        });
    };
    w.eval(src);
    return { w, d: w.document, calls, jsdomErrors };
}

const cartForm = (id) => `<form class="cart" id="f${id}" action="https://shop.test/product/${id}/" method="post">
<input type="number" name="quantity" value="1"><button type="submit" name="add-to-cart" value="${id}">Add</button></form>`;

async function run() {
    console.log('#41 two cards in a row');
    {
        const { w, d, calls, jsdomErrors } = boot(cartForm(1) + cartForm(2));
        const events = [];
        d.addEventListener('woo4etch:cart-written', e => events.push('written:' + e.detail.request.id));
        d.addEventListener('woo4etch:cart-updated', e => events.push('updated:' + e.detail.cart.items_count));
        const e1 = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('f1').dispatchEvent(e1);
        await sleep(10);
        const e2 = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('f2').dispatchEvent(e2);
        ok(e1.defaultPrevented, 'first submit intercepted');
        ok(e2.defaultPrevented, 'second submit (during busy) intercepted, no classic POST');
        ok(d.getElementById('f2').getAttribute('aria-busy') === 'true', 'queued form carries aria-busy');
        await sleep(300);
        const adds = calls.filter(c => c.url.endsWith('/cart/add-item'));
        ok(adds.length === 2, 'both adds went through the Store API (' + adds.length + ')');
        const refreshes = calls.filter(c => c.kind === 'refresh');
        ok(refreshes.length === 2, 'two region refreshes (' + refreshes.length + ')');
        ok(adds[1].t >= refreshes[0].done, 'second add started after the first write+refresh settled');
        ok(events.join(',') === 'written:1,updated:1,written:2,updated:2', 'event order: ' + events.join(','));
        ok(!d.getElementById('f1').hasAttribute('aria-busy') && !d.getElementById('f2').hasAttribute('aria-busy'), 'aria-busy cleared on both forms');
        ok(!d.querySelector('[data-w4e-cart-region]').hasAttribute('aria-busy'), 'region aria-busy cleared');
        ok(d.querySelector('.mini-cart-count').textContent === '2', 'counter synced to 2');
        ok(jsdomErrors.length === 0, 'no jsdom navigation/not-implemented errors: ' + jsdomErrors.join(' | '));
    }

    console.log('#41 double click on one button');
    {
        const { w, d, calls } = boot(cartForm(1));
        const e1 = new w.Event('submit', { bubbles: true, cancelable: true });
        const e2 = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('f1').dispatchEvent(e1);
        await sleep(5);
        d.getElementById('f1').dispatchEvent(e2);
        ok(e2.defaultPrevented, 'repeat submit intercepted');
        await sleep(200);
        ok(calls.filter(c => c.url.endsWith('/cart/add-item')).length === 1, 'only one add-item (no double add)');
        ok(d.querySelectorAll('.w4e-notice--success').length === 1, 'one success notice');
    }

    console.log('#41 remove link double click + queue survives an error');
    {
        const { w, d, calls } = boot(`<div class="w4e-cart" data-w4e-cart-region="cart"><a id="rm" href="https://shop.test/cart/?remove_item=abc">x</a></div>` + cartForm(3), { failUrl: '/cart/remove-item' });
        const click = () => { const ev = new w.MouseEvent('click', { bubbles: true, cancelable: true }); d.getElementById('rm').dispatchEvent(ev); return ev; };
        const c1 = click(); await sleep(5); const c2 = click();
        ok(c1.defaultPrevented && c2.defaultPrevented, 'both clicks intercepted');
        const s = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('f3').dispatchEvent(s);
        await sleep(300);
        ok(calls.filter(c => c.url.endsWith('/cart/remove-item')).length === 1, 'one remove-item (repeat dropped)');
        ok(calls.filter(c => c.url.endsWith('/cart/add-item')).length === 1, 'add-item queued behind the failed remove still ran');
        {
            const restore = calls.find(c => c.kind === 'refresh');
            const add = calls.find(c => c.url.endsWith('/cart/add-item'));
            ok(restore && add && add.t >= restore.done, 'restore refresh after the failed write finished before the next queued write started');
        }
        ok(d.querySelectorAll('.w4e-notice--error').length === 1, 'error notice shown once');
        ok(d.querySelectorAll('.w4e-notice--success').length === 1, 'success notice for the add');
        ok(!d.querySelector('[data-w4e-cart-region]').hasAttribute('aria-busy'), 'region aria-busy cleared after the run');
    }

    console.log('#42 early event, cancel, data-w4e-refresh=none');
    {
        const { w, d, calls } = boot(cartForm(1));
        let writtenAt = 0, detail = null;
        d.addEventListener('woo4etch:cart-written', e => { writtenAt = Date.now(); detail = e.detail; });
        d.getElementById('f1').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(200);
        const refresh = calls.find(c => c.kind === 'refresh');
        ok(detail && detail.action === 'add-item' && detail.request.id === 1 && detail.request.quantity === 1 && detail.cart.items_count === 1 && detail.origin === d.getElementById('f1'), 'cart-written detail: action/request/cart/origin');
        ok(refresh && writtenAt <= refresh.t, 'cart-written fired before the page re-fetch started');
    }
    {
        const { w, d, calls } = boot(cartForm(1));
        let updated = 0;
        d.addEventListener('woo4etch:cart-written', e => e.preventDefault());
        d.addEventListener('woo4etch:cart-updated', () => updated++);
        d.getElementById('f1').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(200);
        ok(calls.filter(c => c.kind === 'refresh').length === 0, 'preventDefault on cart-written skips the refresh');
        ok(updated === 1, 'cart-updated still fires when refresh skipped');
        ok(calls.filter(c => c.url.endsWith('/cart/add-item')).length === 1, 'write itself happened');
    }
    {
        const { w, d, calls } = boot(`<div data-w4e-refresh="none">${cartForm(1)}</div>` + cartForm(2));
        let updated = 0;
        d.addEventListener('woo4etch:cart-updated', () => updated++);
        d.getElementById('f1').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(150);
        ok(calls.filter(c => c.kind === 'refresh').length === 0, 'data-w4e-refresh="none" on an ancestor skips the refresh');
        d.getElementById('f2').dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(150);
        ok(calls.filter(c => c.kind === 'refresh').length === 1, 'form outside the opt-out still refreshes');
        ok(updated === 2, 'cart-updated fired for both');
    }

    console.log('checkout: submit swallowed while busy, order POST queued behind update-customer');
    {
        const { w, d, calls } = boot(`<form data-w4e-checkout id="co"><input name="billing_postcode" value="1"><input name="billing_email" value="a@b.c"><input type="radio" name="payment_method" value="cod" checked><button>Pay</button></form>`, { delay: 30 });
        const pc = d.querySelector('[name=billing_postcode]');
        pc.value = '12345';
        pc.dispatchEvent(new w.Event('change', { bubbles: true }));
        await sleep(650); // debounce 600 → update-customer now in flight
        const s1 = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('co').dispatchEvent(s1);
        ok(s1.defaultPrevented, 'submit during in-flight write swallowed (not classic)');
        await sleep(150);
        ok(calls.filter(c => c.url.endsWith('/checkout')).length === 0, 'no order placed by the swallowed submit');
        const s2 = new w.Event('submit', { bubbles: true, cancelable: true });
        d.getElementById('co').dispatchEvent(s2);
        await sleep(150);
        const co = calls.filter(c => c.url.endsWith('/checkout'));
        ok(co.length === 1 && co[0].body.payment_method === 'cod' && co[0].body.billing_address.postcode === '12345', 'order placed once with the address');
        ok(calls.filter(c => c.url.endsWith('/cart/update-customer')).length === 1, 'one update-customer');
    }

    console.log(`\n${checks - failures}/${checks} passed`);
    process.exit(failures ? 1 : 0);
}
run().catch(e => { console.error(e); process.exit(2); });
