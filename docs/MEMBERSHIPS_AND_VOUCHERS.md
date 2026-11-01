# Memberships and Gift Vouchers

How the spa's physical membership cards and gift vouchers map to system rules. Admins can change every value below from the UI.

## Membership cards

Seeded by `2026_11_01_010000_create_memberships` from the printed cards:

| Benefit | Gold ★★★ | Platinum ★★★★★ |
|---|---|---|
| Service discount | 10% | 15% |
| Add-on discount | — | 20% off Ventosa, Hot Compress, Hot Stone |
| VIP Room | Regular price | PHP 100 instead of PHP 200 |
| Birthday treat | Free Back Massage (`back-massage-30` add-on) | Full Body Massage (worth up to the GAIA TOUCH service price) |
| Referral reward | PHP 100 off the next service for both members | Same |
| Card number | `CPBWS-0001`, … | Same |
| Validity | 12 months | 12 months |

Data model:
- `membership_tiers` holds each tier's benefits and rank.
- `customer_memberships` holds issued cards: card number, issued date, valid-until date, status, issuer and referrer.
- `membership_referral_credits` holds one credit per customer per referral.
- `membership_perk_usages` records each birthday treat used.

Rules:
- A card is active from its issue date through its valid-until date (Asia/Manila), unless it is cancelled. A customer can hold one active card.
- Admins and receptionists issue and renew cards from **Customers → Memberships**. Only admins cancel cards or edit tiers (**Membership tiers**).
- A renewal extends the card by the tier's validity. It counts from the current valid-until date, or from today if the card has already lapsed.
- Issuing a card with a referrer creates two referral credits, one for the new member and one for the referrer.
- The birthday treat applies once per calendar year, during the member's birth month, when the treat item is on the visit.

## Pricing at payment (`PaymentPricing`)

Staff enter the **amount before member benefits**. The amount defaults to the service price plus the paid add-ons. When a linked transaction is first saved as **paid**, benefits are applied in this order:

1. VIP Room member price.
2. Birthday treat (the treat line becomes free).
3. Add-on percentage on the listed add-ons.
4. Service percentage on what remains of the service price.
5. The oldest unused referral credit, capped at the remaining amount.

`transactions.amount` stays the **net service revenue**, so commissions, reports and RFM monetary values all use the discounted amount. The breakdown is stored in:
- `transactions.gross_amount`
- `transactions.discount_amount`
- `transactions.customer_membership_id`
- `transaction_adjustments` (one row per benefit)

Later edits keep the stored benefits and only re-derive the net from the edited gross amount. Refunds do not restore used perks or credits.

Payment forms show a live preview of benefits from the role-scoped `admin.transactions.quote` and `reception.transactions.quote` JSON support endpoints. The server always re-prices on save.

## Gift vouchers

The printed terms map to system rules as follows:

| Printed term | System rule |
|---|---|
| Valid until a date | `gift_vouchers.expires_at`. The default comes from the voucher settings. After that date (Asia/Manila) the code is refused. |
| Non-refundable, non-convertible | The voucher is a prepaid tender with no cash-out path. Refunding the payment does not reopen the voucher. |
| Not combinable with other discounts or promos | When combining is off (the default), a payment that uses a voucher skips member benefits and referral credits. A booking that carries an RFM add-on reward is refused. An admin can allow combining. |
| One-time use | The voucher becomes `redeemed` on the first paid transaction and is locked against reuse. |
| Excess paid by another method | `transactions.amount_due` = net − voucher applied. |
| Unused balance forfeited | `gift_vouchers.forfeited_amount` = value − applied. |

How vouchers work in the system:
- Admins and receptionists record sold vouchers at **Payments → Gift vouchers**.
  - The value is one of the denominations (default ₱300, ₱500, ₱1,000) or a custom amount.
  - The code is the scanned or typed barcode number from the printed voucher, or a generated `CPGV-XXXXXX` code.
- Only admins void vouchers (a reason is required) and change the voucher rules: denominations, default validity, and the combine switch.
- The voucher code is entered on the payment or "finish service" form.
- The voucher's sale is its own record and is not a service transaction. Service revenue is counted when the visit is paid, so voucher sales are not double-counted as service income.
