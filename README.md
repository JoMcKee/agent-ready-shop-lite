# Agent-Ready Shop Lite

Free, read-only WordPress plugin that lets AI shopping assistants search your WooCommerce products, read details and check stock over MCP.

- MCP endpoint: `/wp-json/arsl/v1/mcp` (Streamable HTTP, JSON responses, bearer token)
- Tools: `search_products`, `get_product`, `check_stock`, `get_store_info`
- Tokens stored as HMAC hashes, revocable, rate-limited. Draft, private, password-protected and hidden products are never exposed. No outgoing requests.
- Requires WordPress 6.4+, WooCommerce 8.0+, PHP 7.4+.

Install: copy `agent-ready-shop-lite/` into `wp-content/plugins/`, activate, then WooCommerce > Agent Shop Lite > Create token.

Want assistants to create orders paid on your own checkout, plus signed updates and support? See the paid [Agent-Ready Shop](https://mckeecreative.store/agent-ready-shop/).

Licence: GPL-2.0-or-later. Made by McKee Creative.
