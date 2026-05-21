# 09 — Transactional Emails

Order confirmation, processing, completed, shipping notifications, password resets. WooCommerce emails are **completely separate from your frontend** — they're rendered through PHP templates with table-based markup for email-client compatibility.

> **Important:** Etch and Dynamic Keys do **not** apply here. Email markup must be inline-styled, table-based HTML — Outlook on Windows still uses Microsoft Word's rendering engine for HTML, which means no flexbox, no grid, no modern CSS. Override the PHP templates instead.

## When to use

- Customizing order confirmation, processing, completed, refunded, etc.
- Adding your brand header/footer to all WC emails.
- Replacing default content (`Hi {firstName}, your order has been received`) with custom copy.
- Adding tracking links, upsell content, or attachment notes.

## Preparation

WooCommerce stores email templates in `wp-content/plugins/woocommerce/templates/emails/`. You override them by copying the relevant file to `wp-content/themes/<your-theme>/woocommerce/emails/<filename>.php`.

**Never edit the plugin files directly** — they're overwritten on update.

Email config lives at `WooCommerce → Settings → Emails`. Each email type has its own settings: enable/disable, recipient, subject, heading, template.

## Email types (overview)

| Email | Triggered when | Template file |
|---|---|---|
| New order | Order placed | `emails/admin-new-order.php` |
| Cancelled order | Order cancelled | `emails/admin-cancelled-order.php` |
| Failed order | Payment failed | `emails/admin-failed-order.php` |
| Processing order | Payment received | `emails/customer-processing-order.php` |
| Completed order | Order completed | `emails/customer-completed-order.php` |
| Refunded order | Refund issued | `emails/customer-refunded-order.php` |
| Invoice / order details | Manual from admin | `emails/customer-invoice.php` |
| Customer note | Note added to order | `emails/customer-note.php` |
| Reset password | Password reset request | `emails/customer-reset-password.php` |
| New account | Customer registers | `emails/customer-new-account.php` |

## Template structure — what's where

Every email template uses a wrapper that's shared across all emails:

```
emails/email-header.php   ← top of every email (logo, heading, blue band)
emails/<email-name>.php   ← body content
emails/email-footer.php   ← bottom (legal text, links)
emails/email-styles.php   ← inline CSS (gets inlined into all <style> tags)
```

Inside an email template, you'll see:

```php
<?php do_action('woocommerce_email_header', $email_heading, $email); ?>

<!-- Email body content here -->

<?php do_action('woocommerce_email_footer', $email); ?>
```

That means: **header and footer are shared**. Customize them once, all emails update.

## Step 1 — Override the header

Copy `wp-content/plugins/woocommerce/templates/emails/email-header.php` to `wp-content/themes/<your-theme>/woocommerce/emails/email-header.php`.

Minimal customized header:

```php
<?php
/**
 * Email Header
 */
if (!defined('ABSPATH')) exit;
?>
<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo esc_html(get_bloginfo('name', 'display')); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0"
      topmargin="0"
      marginwidth="0"
      marginheight="0"
      style="background-color: #f7f7f7;">

  <div id="wrapper"
       dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

    <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
      <tr>
        <td align="center" valign="top">

          <div id="template_header_image">
            <p style="margin-top:0; text-align:center;">
              <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/email-logo.png'); ?>"
                     alt="<?php echo esc_attr(get_bloginfo('name', 'display')); ?>"
                     width="200" />
              </a>
            </p>
          </div>

          <table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container"
                 style="background-color:#ffffff; border:1px solid #dedede; border-radius:3px;">
            <tr>
              <td align="center" valign="top">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header"
                       style="background-color:#1a1a1a; color:#ffffff;
                              border-top-left-radius:3px; border-top-right-radius:3px;">
                  <tr>
                    <td id="header_wrapper" style="padding:36px 48px; display:block;">
                      <h1 style="color:#ffffff; font-family:Helvetica,Arial,sans-serif;
                                 font-size:30px; font-weight:300; line-height:150%;
                                 margin:0; text-align:left;">
                        <?php echo esc_html($email_heading); ?>
                      </h1>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
                  <tr>
                    <td valign="top" id="body_content" style="background-color:#ffffff;">
                      <table border="0" cellpadding="20" cellspacing="0" width="100%">
                        <tr>
                          <td valign="top" style="padding:48px 48px 32px;">
                            <div id="body_content_inner"
                                 style="color:#636363; font-family:Helvetica,Arial,sans-serif;
                                        font-size:14px; line-height:150%; text-align:left;">
```

That's the **opening half** of the wrapper — it ends in `email-footer.php`.

## Step 2 — Override the footer

Copy `wp-content/plugins/woocommerce/templates/emails/email-footer.php` likewise.

Minimal:

```php
<?php
if (!defined('ABSPATH')) exit;
?>
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table border="0" cellpadding="10" cellspacing="0" width="100%" id="template_footer">
                  <tr>
                    <td valign="top" style="padding:0; border-radius:6px;">
                      <table border="0" cellpadding="10" cellspacing="0" width="100%">
                        <tr>
                          <td colspan="2" valign="middle" id="credit"
                              style="font-family:Helvetica,Arial,sans-serif;
                                     color:#8a8a8a; font-size:12px; line-height:125%;
                                     text-align:center; padding:24px 0;">
                            <?php echo wp_kses(apply_filters('woocommerce_email_footer_text',
                                get_option('woocommerce_email_footer_text')), [
                                'a' => ['href' => true, 'target' => true],
                                'strong' => [], 'em' => [], 'br' => [],
                            ]); ?>
                            <br>
                            <a href="<?php echo esc_url(home_url('/imprint')); ?>"
                               style="color:#8a8a8a;">Imprint</a> ·
                            <a href="<?php echo esc_url(home_url('/privacy')); ?>"
                               style="color:#8a8a8a;">Privacy</a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
```

## Step 3 — Override an email body

Example: customize the processing-order email.

Copy `wp-content/plugins/woocommerce/templates/emails/customer-processing-order.php`:

```php
<?php
/**
 * Customer processing order email
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */
if (!defined('ABSPATH')) exit;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>Hi <?php echo esc_html($order->get_billing_first_name()); ?>,</p>

<p>
  Thanks for your order — we're processing it right now.
  We'll send another email when your order has shipped.
</p>

<!-- Tracking placeholder for later -->
<?php if ($tracking_number = $order->get_meta('_tracking_number')): ?>
  <p>
    <strong>Tracking number:</strong>
    <a href="<?php echo esc_url($order->get_meta('_tracking_url')); ?>">
      <?php echo esc_html($tracking_number); ?>
    </a>
  </p>
<?php endif; ?>

<?php
/* Hook: woocommerce_email_order_details */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/* Hook: woocommerce_email_order_meta */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/* Hook: woocommerce_email_customer_details */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
?>

<p>
  Thanks for shopping with us.
</p>

<?php do_action('woocommerce_email_footer', $email); ?>
```

The three `do_action(...)` calls in the middle are **important** — they render the order table, customer details, and meta. Don't remove them.

## Hooks used

### Header / footer

| Hook | Position | Use |
|---|---|---|
| `woocommerce_email_header` | Top of every email | Inserts header markup |
| `woocommerce_email_footer` | Bottom of every email | Inserts footer markup |
| `woocommerce_email_footer_text` (Filter) | — | Customize footer credit text |

### Inside the body

| Hook | Position | Use / default callback |
|---|---|---|
| `woocommerce_email_order_details` | Body slot | **Default**: renders the items table |
| `woocommerce_email_order_meta` | Body slot | **Default**: empty unless plugins add notes |
| `woocommerce_email_customer_details` | Body slot | **Default**: customer billing/shipping address |
| `woocommerce_email_before_order_table` | Before items table | Trust badges, banner |
| `woocommerce_email_after_order_table` | After items table | Cross-sells |

### Filters

| Hook | Use |
|---|---|
| `woocommerce_email_subject_customer_processing_order` | Customize subject per email type |
| `woocommerce_email_subject_customer_completed_order` | (analogous) |
| `woocommerce_email_heading_customer_processing_order` | Customize the H1 heading in the email |
| `woocommerce_email_styles` | Inject custom inline CSS |
| `woocommerce_email_classes` | Register a custom email type |

## PHP layer

### Custom subject

```php
add_filter('woocommerce_email_subject_customer_processing_order', function ($subject, $order) {
    return sprintf('Order #%s received — we\'re on it', $order->get_order_number());
}, 10, 2);
```

### Custom heading

```php
add_filter('woocommerce_email_heading_customer_processing_order', function ($heading, $order) {
    return 'Thanks for your order!';
}, 10, 2);
```

### Footer text (no template override needed)

`WooCommerce → Settings → Emails → Footer text` accepts placeholders:

- `{site_title}` — site name
- `{site_url}` — site URL
- `{site_address}` — derived from settings

Set there, no code required.

### Inject custom inline CSS

```php
add_filter('woocommerce_email_styles', function ($css) {
    return $css . "
        #body_content h1 { font-family: Georgia, serif; }
        .button { background-color: #1a1a1a; color: #fff; padding: 12px 24px; }
    ";
});
```

> CSS in emails is **inlined automatically** by WooCommerce (using `Emogrifier` / `pelago/emogrifier`). You can write a normal stylesheet, but only basic CSS works in email clients.

### Add tracking link to the email

```php
add_action('woocommerce_email_order_meta', function ($order, $sent_to_admin, $plain_text, $email) {
    if ($email->id !== 'customer_completed_order') return;
    $tracking = $order->get_meta('_tracking_number');
    $url      = $order->get_meta('_tracking_url');
    if (!$tracking || !$url) return;

    if ($plain_text) {
        echo "\nTracking number: $tracking\nTrack your shipment: $url\n";
    } else {
        echo '<p><strong>Tracking:</strong> <a href="' . esc_url($url) . '">' . esc_html($tracking) . '</a></p>';
    }
}, 10, 4);
```

### Register a custom email (e.g. "Order shipped")

```php
add_filter('woocommerce_email_classes', function ($emails) {
    require_once get_stylesheet_directory() . '/inc/class-wc-email-order-shipped.php';
    $emails['WC_Email_Order_Shipped'] = new WC_Email_Order_Shipped();
    return $emails;
});
```

The class file (`class-wc-email-order-shipped.php`) extends `WC_Email`:

```php
class WC_Email_Order_Shipped extends WC_Email {
    public function __construct() {
        $this->id             = 'order_shipped';
        $this->customer_email = true;
        $this->title          = 'Order shipped';
        $this->description    = 'Email sent when an order is marked as shipped';
        $this->template_html  = 'emails/customer-order-shipped.php';
        $this->template_plain = 'emails/plain/customer-order-shipped.php';

        // Trigger when order status changes to "shipped"
        add_action('woocommerce_order_status_shipped_notification', [$this, 'trigger']);
        add_action('woocommerce_order_status_completed_to_shipped_notification', [$this, 'trigger']);

        parent::__construct();
    }

    public function trigger($order_id) {
        if (!$order_id) return;
        $this->object    = wc_get_order($order_id);
        $this->recipient = $this->object->get_billing_email();
        if (!$this->is_enabled() || !$this->get_recipient()) return;
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }
}
```

You'll also need to register the `shipped` status itself — that's a separate `register_post_status` call.

### Preview emails in browser

Install a dev tool like **Email Log** or **WP Mail Logging** plugin during development. Both intercept outgoing emails so you can review the rendered HTML.

For a one-off preview without a plugin:

```php
add_action('admin_init', function () {
    if (!isset($_GET['preview_email'])) return;
    if (!current_user_can('manage_options')) return;
    // Admin-only is not enough — guard against CSRF with a nonce.
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'kr_preview_email')) {
        wp_die('Invalid or missing nonce.');
    }

    $order = wc_get_order((int) ($_GET['order_id'] ?? 0));
    if (!$order) wp_die('Order not found');

    $email_id    = sanitize_key($_GET['preview_email']);
    $email_class = 'WC_Email_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $email_id)));
    $email       = WC()->mailer()->emails[$email_class] ?? null;
    if (!$email) wp_die('Unknown email');

    $email->object = $order;
    echo $email->get_content_html();
    exit;
});
```

Build the preview link with a matching nonce, e.g.
`wp_nonce_url(admin_url('?preview_email=customer_processing_order&order_id=123'), 'kr_preview_email')`.

## Common mistakes

- Modern CSS (flexbox, grid, custom properties) in email styles → breaks in Outlook on Windows.
- Linked external CSS → most clients block external stylesheets. Use `style` attributes (or rely on Emogrifier-inlining).
- Background images → unsupported in Outlook. Use a solid fallback color.
- Removing `do_action('woocommerce_email_order_details', …)` → email shows no order summary.
- Forgetting `<?php do_action('woocommerce_email_header', $email_heading, $email); ?>` → no wrapper, raw text appears.
- Using `<img>` without `width` attribute → Outlook ignores image dimensions.
- Forgetting the `plain_text` branch → plain-text clients (rare but legally required for some markets) get HTML in their email.

## Test checklist

- Place a test order → confirmation email arrives within 1–2 min.
- Check email in Gmail web, Outlook desktop (Windows), Apple Mail, iOS Mail, and Android Gmail.
- Subject and heading match your customization.
- Logo loads (CDN/HTTPS URL).
- Tracking link in shipped email opens the carrier page.
- Plain-text version (mostly auto-generated) is readable.
- Email rendering tool (Litmus, Email on Acid, or Mailtrap) shows no layout breaks.
- Replying to the email goes to a real address (`WooCommerce → Settings → Emails → From address`).
