# Database Gap Analysis — eShop Plus 1.0.6 Schema vs. Target Platform

Source: `eshop_plus.sql` (full structure dump, 90 tables, MariaDB 10.4 / phpMyAdmin export, no data rows
inspected — structure only). This is the one piece of evidence in this audit that was read in full.

## 1. Full Existing Table Inventory (90 tables), Grouped by Domain

**Identity / RBAC**
`users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`,
`user_permissions` (legacy custom RBAC, parallel to Spatie), `personal_access_tokens` (Sanctum),
`password_reset_tokens`, `login_attempts`, `otps`, `client_api_keys`

**Vendors / Marketplace**
`stores`, `seller_store`, `seller_data`, `seller_commissions`

**Catalog**
`categories`, `category_sliders`, `brands`, `attributes`, `attribute_values`, `products`,
`product_variants`, `product_attributes`, `custom_fields`, `product_custom_field_values`,
`product_faqs`, `product_ratings`, `combo_products`, `combo_product_attributes`,
`combo_product_attribute_values`, `combo_product_custom_field_values`, `combo_product_faqs`,
`combo_product_ratings`

**Cart / Orders / Fulfillment**
`cart`, `cart_reminders`, `orders`, `order_items`, `order_charges`, `order_trackings`,
`order_bank_transfers`, `return_requests`, `parcels`, `parcel_items`, `digital_orders_mails`

**Delivery**
`delivery_boy_notifications`, `fund_transfers` (delivery-boy cash reconciliation)

**Payments / Wallet**
`transactions`, `wallet_transactions`, `payment_requests`, `promo_codes`

**Geography / Serviceability**
`countries`, `cities`, `areas`, `zipcodes`, `zones`, `addresses`, `pickup_locations`

**Localization**
`languages`, `currencies`

**Content / Merchandising**
`sliders`, `offers`, `offer_sliders`, `sections`, `blogs`, `blog_categories`, `faqs`, `themes`,
`custom_messages`, `time_slots`

**Engagement**
`favorites`, `search_history`, `notifications`, `system_notification`, `user_fcm`,
`user_client_preferences`

**Support / Chat**
`tickets`, `ticket_messages`, `ticket_types`, `ch_messages`, `ch_favorites` (Chatify)

**Media / Infra**
`images`, `media`, `storage_types`, `failed_jobs`, `migrations`, `updates`, `settings`

## 2. Referential Integrity — Actual State

- **Declared foreign keys: 2 total**, both on `seller_store` (`seller_id → seller_data.id`,
  `user_id → users.id`, both `ON DELETE CASCADE ON UPDATE CASCADE`).
- Every other relationship in the schema — `orders.user_id`, `order_items.order_id`,
  `order_items.product_variant_id`, `products.category_id`, `cart.user_id`, `favorites.product_id`,
  `wallet_transactions.user_id`, etc. — is a bare `int` column with **no DB-level constraint**. Some have a
  plain (non-FK) index for query performance (e.g. `addresses.user_id`, `cart.user_id`,
  `order_items.order_id`); most integrity is convention-only.
- **Implication for the target platform**: tenant-isolation and financial-integrity work (Section 5/16 of
  the master prompt) cannot rely on the database to reject a cross-tenant write — it must be enforced in
  application-level query scoping (global scopes / policies) since the schema itself won't stop it. This
  is exactly the "never rely only on frontend filtering" risk the master prompt warns about, extended one
  level deeper: today it's not even reliably enforced by SQL constraints.

## 3. Engine / Transaction-Safety Gap

11 of 90 tables are `ENGINE=MyISAM` (no transactions, table-level locking, weaker crash recovery):
`orders`, `products`, `wallet_transactions`, `return_requests`, `sections`, `settings`, `sliders`,
`time_slots`, `notifications`, `favorites`, `delivery_boy_notifications`.

The remaining 79 are InnoDB. **Gap**: any ledger/accounting work must sit on InnoDB tables with real
transaction boundaries; `orders` and `wallet_transactions` being MyISAM today is disqualifying for the
"unified ledger" and "every financial record traceable to source" requirements and must be migrated
(`ALTER TABLE ... ENGINE=InnoDB`) as an early, low-risk, backward-compatible step (Phase 1).

## 4. Money Representation Gap

Every monetary column in the schema is `double` (or `float`): `products.price`-adjacent fields on
`product_variants` (`price`, `special_price`), `orders` (`total`, `delivery_charge`, `wallet_balance`,
`promo_discount`, `discount`, `total_payable`, `final_total`), `order_items` (`price`,
`discounted_price`, `tax_amount`, `discount`, `sub_total`, `admin_commission_amount`,
`seller_commission_amount`), `wallet_transactions.amount`, `transactions.amount`, `users.balance`,
`users.cash_received` (this one is already `decimal(15,2)` — inconsistent with the rest),
`seller_commissions.commission` (`double(10,2)`), `payment_requests.amount_requested`
(`decimal(10,2)` — another inconsistent exception).

**Gap**: the master prompt's money-handling rule (Section 40) requires `DECIMAL` columns and a currency
recorded with every monetary record. Currency-per-transaction already exists at the *order* level
(`orders.order_payment_currency_code`, `base_currency_code`, `order_payment_currency_conversion_rate`) but
not at the line-item or ledger-entry level, and not as `DECIMAL` anywhere except the two exceptions noted.
This is a schema-wide, backward-compatible migration: widen columns to `DECIMAL(x,y)`, backfill, do not
drop the old columns until the app layer is fully cut over.

## 5. Domain Gaps vs. Target Platform (nothing to reuse — net-new schema required)

| Target Domain | Current Schema Support | Gap |
|---|---|---|
| Chart of Accounts / GL / Journal Entries | None | Full new schema: `accounts`, `journal_entries`, `journal_lines`, with immutable-or-reversal semantics |
| Accounts Receivable / Payable | None (only flat `transactions`/`wallet_transactions` logs) | New schema, driven off orders/purchases |
| Warehouses / Branches / Multi-location stock | None (`pickup_locations` is a single address per seller; stock is one `int` per product/variant) | New: `warehouses`, `branches`, `stock_items` (location-aware), `stock_movements` |
| Inventory valuation (FIFO/weighted-avg) | None (no stock ledger at all) | New: cost-layer or moving-average valuation tables |
| Procurement (suppliers, POs, GRNs) | None | New: `suppliers`, `purchase_orders`, `purchase_order_items`, `goods_received_notes`, `supplier_payments` |
| POS (shifts, till, split payments) | Only `orders.is_pos_order` flag | New: `pos_shifts`, `pos_payments` (or generalize `transactions`), cash reconciliation |
| Affiliate/Referral engine | `users.referral_code`/`friends_code` only | New: `affiliate_links`, `link_clicks`, `referral_conversions`, `commission_ledger` with a state machine |
| Commission Engine (general, configurable) | `seller_commissions` (flat vendor-category rate) + per-order-item commission amount columns | New: rule engine tables (`commission_rules` by scope: platform/vendor/affiliate/category/product/campaign) feeding the ledger |
| Employees (distinct from sellers) | None — sellers are `users` with a role | New: `employees` with branch/department assignment, permission scoping |
| Partners / Shareholders | None | New: `partners`, `partner_capital_accounts`, `partner_transactions` |
| Assets / Liabilities | None | New: `assets`, `depreciation_schedules`, `liabilities` |
| CRM (segments, tags, notes, CLV) | Only implicit via `orders`/`users` history | New: `customer_notes`, `customer_tags`, `customer_segments`; CLV can be computed, not stored |
| Multi-company | None — platform is single-company | New: `companies` as a top-level tenant above `stores`, or confirm `stores` *is* the company unit and branches nest under it — architectural decision needed in Phase 1 |
| Audit log | None | New: `audit_logs` (actor, action, entity, entity_id, old/new value, timestamp, IP) |

## 6. What's Already a Good Foundation (do not rebuild)

- Multi-currency order capture (rate snapshot per order) — extend downward to line-item/ledger level rather
  than replace.
- Translatable JSON columns + `languages.is_rtl` — reuse for all new entities' translatable fields.
- `custom_fields`/`*_custom_field_values` pattern — reuse for vendor-defined POS/inventory attributes if
  needed.
- Geography tree (`countries`→`cities`→`areas`→`zipcodes`, plus `zones`) — reuse as the basis for
  delivery-zone and warehouse-location modeling rather than inventing a parallel geography model.

## 7. Migration Approach

Per the master prompt's rule against destructive migrations: every gap above should be closed with
**additive** migrations (new tables, new nullable/defaulted columns) plus a **parallel-write, verify, then
cut-over** approach for the money-type changes (`double`→`DECIMAL`) and the engine change
(MyISAM→InnoDB), rather than a single destructive pass. Foreign keys should be added table-by-table once
each relationship's data has been verified clean (orphan rows checked first), not applied blindly across
all 88 unconstrained relationships at once.
