=== Agent-Ready Shop Lite ===
Contributors: mckeecreative
Tags: woocommerce, mcp, ai, ai assistant, agentic commerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let AI shopping assistants search your WooCommerce products, read details and check stock over MCP. Read-only.

== Description ==

Shoppers are starting to ask AI assistants to find products for them. Agent-Ready Shop Lite gives your WooCommerce store an MCP connector (an "AI connection link") so assistants that support custom MCP connectors can:

* search your published products by keyword or category
* read product details, variations and attributes
* check stock and current prices
* read store info: currency, countries you ship to, terms and privacy links

It is read-only. Assistants send shoppers to your product pages to buy on your normal checkout.

**Safe by default**

* Access tokens are stored as hashes (HMAC), can be revoked in one click and are rate-limited per token.
* Draft, private, password-protected and hidden products are never shown.
* No card or customer data passes through the plugin. Nothing is sent to any outside server.
* Compatible with WooCommerce high-performance order storage. Uninstalling removes its settings.

**MCP endpoint:** `/wp-json/arsl/v1/mcp` (MCP Streamable HTTP, JSON responses, bearer token).

**Want assistants to create orders too?** The paid Agent-Ready Shop plugin adds agent ordering (a normal pending WooCommerce order plus your store's own payment link), signed automatic updates and email support: https://mckeecreative.store/agent-ready-shop/ . This free plugin is complete on its own and has no locked features.

== Installation ==

1. Install and activate WooCommerce.
2. Plugins > Add New > search "Agent-Ready Shop Lite" (or upload the zip) > Activate.
3. Go to WooCommerce > Agent Shop Lite and create an access token.
4. Copy your MCP address and the token. Paste them where your AI assistant asks for a custom connector (for example Claude's connector settings).

Requires HTTPS for most AI assistants.

== Frequently Asked Questions ==

= Which AI assistants can use it? =

Any assistant that lets you add a custom MCP connector. Which assistants support connectors is up to each AI company.

= Can assistants place orders? =

No. This plugin is read-only. Assistants send shoppers to your product pages.

= Does it send data anywhere? =

No. It only answers requests that carry one of your tokens. It makes no outgoing requests.

== Changelog ==

= 1.0.1 =
* Security: the MCP endpoint checks the access token in its REST permission_callback.
* Hardening: malformed stock-check items return a clear error.

= 1.0.0 =
* First release: MCP endpoint with search_products, get_product, check_stock and get_store_info; hashed, revocable, rate-limited read-only tokens; self-check; clean uninstall.
