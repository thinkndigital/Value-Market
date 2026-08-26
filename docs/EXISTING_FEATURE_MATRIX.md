# Existing Feature Matrix — eShop Plus 1.0.6 vs. Target Platform

Status is based on the evidence available (schema dump + dependency manifests — see
`INITIAL_CODEBASE_AUDIT.md` for scope). **Existing** = real schema/package support found. **Partial** =
some support but materially incomplete. **Missing** = no schema/package evidence at all. Recommendation
columns are not mutually exclusive with status (e.g. an Existing feature can still need Refactor).

| Feature | Existing | Partial | Missing | Reuse | Refactor | New Build |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Multi-vendor marketplace core (stores, sellers) | ✔ | | | ✔ | | |
| Vendor storefront (branding, colors, description) | ✔ | | | ✔ | | |
| Product catalog (simple + variant) | ✔ | | | ✔ | | |
| Combo/bundle products | | ✔ | | | ✔ | |
| Categories / brands / attributes | ✔ | | | ✔ | | |
| Custom product fields | ✔ | | | ✔ | | |
| Cart / checkout | ✔ | | | ✔ | | |
| Promo codes / coupons | ✔ | | | ✔ | | |
| Order management (order + order_items) | ✔ | | | ✔ | | |
| Order tracking / courier integration (Shiprocket) | ✔ | | | ✔ | | |
| Returns | | ✔ | | | ✔ | |
| Digital product delivery | ✔ | | | ✔ | | |
| Ratings / FAQs / favorites | ✔ | | | ✔ | | |
| Wallet (customer/seller balance) | | ✔ | | | ✔ | |
| Payment gateways (Stripe/PayPal/Razorpay/Flutterwave/Paystack/bank transfer) | ✔ | | | ✔ | | |
| Support tickets | ✔ | | | ✔ | | |
| Real-time chat (Chatify) | ✔ | | | ✔ | | |
| RBAC (roles/permissions) | | ✔ | | | ✔ | |
| Multi-currency (order-level) | | ✔ | | ✔ | ✔ | |
| Multi-language / RTL (Arabic) | | ✔ | | ✔ | | |
| Push notifications (FCM) | ✔ | | | ✔ | | |
| Delivery-boy assignment & OTP delivery | ✔ | | | ✔ | | |
| Delivery cash reconciliation (`fund_transfers`) | | ✔ | | | ✔ | |
| **Delivery zones / dispatch / driver earnings (full)** | | | ✔ | | | ✔ |
| **POS (shifts, till, split payment, cash reconciliation)** | | ✔ | | | | ✔ |
| **Inventory: warehouses / branches / stock movements** | | | ✔ | | | ✔ |
| **Inventory valuation (FIFO / weighted-average)** | | | ✔ | | | ✔ |
| **Procurement (suppliers, POs, GRNs, supplier payables)** | | | ✔ | | | ✔ |
| **Affiliate / reseller link + click + conversion tracking** | | ✔ | | | | ✔ |
| **Affiliate storefronts** | | | ✔ | | | ✔ |
| **Configurable commission rule engine** | | ✔ | | | ✔ | ✔ |
| **Commission ledger (pending→payable→paid, reversible)** | | | ✔ | | | ✔ |
| **Accounting: Chart of Accounts** | | | ✔ | | | ✔ |
| **Accounting: Journal Entries / General Ledger** | | | ✔ | | | ✔ |
| **Accounts Receivable / Payable** | | | ✔ | | | ✔ |
| **Unified financial ledger** | | | ✔ | | | ✔ |
| **Partners / Shareholders / capital accounts** | | | ✔ | | | ✔ |
| **Assets & depreciation** | | | ✔ | | | ✔ |
| **Liabilities tracking** | | | ✔ | | | ✔ |
| **Expense management (structured, with accounting impact)** | | | ✔ | | | ✔ |
| **Financial reports (P&L, Balance Sheet, Cash Flow, Trial Balance, Aging)** | | | ✔ | | | ✔ |
| **Employee management (distinct from sellers, branch-scoped)** | | | ✔ | | | ✔ |
| **CRM (segments, tags, notes, CLV)** | | | ✔ | | | ✔ |
| **Multi-company / multi-branch org structure** | | | ✔ | | | ✔ |
| **Multi-warehouse-aware order fulfillment** | | | ✔ | | | ✔ |
| **Executive / unified analytics dashboard** | | | ✔ | | | ✔ |
| **Vendor / affiliate / delivery analytics** | | | ✔ | | | ✔ |
| **AI Business Intelligence layer** | | | ✔ | | | ✔ |
| **Audit log** | | | ✔ | | | ✔ |
| Tenant data isolation (enforced at query/authorization layer) | | ✔ | | | ✔ | |
| API-first architecture (Sanctum present; endpoint coverage unverified) | | ✔ | | ✔ | ? unverified | |
| Customer mobile app | ? unverified | | | ? | | |
| Vendor/seller mobile app | ? unverified | | | ? | | |
| Delivery driver mobile app | ? unverified | | | ? | | |
| Automated test suite | ? unverified | | | ? | | |

**Rows marked "? unverified"**: mobile app source and PHP source were not available in this session — see
`INITIAL_CODEBASE_AUDIT.md` §9–10. Status will move from "?" to a real value once the source is pushed and
inspected; do not treat "?" as "missing."
