# Marketing fees: decisions log

One line per ambiguity resolved while executing the prompts in `marketing-fees-and-marketer-payouts.md`, for owner review.

- P1: `MarketingFeeService::calculate(..., $applyFlatAmounts)` replaces the plan's `$applyFixed`; false skips BOTH flat amounts (country fixed amount and category variable amount) and keeps only the percentage parts, matching 1.3 and 4.4 (flat amounts charged once, on the first accrual).
- P1: `marketer_campaigns.marketing_fee_fixed_accrued` is a DECIMAL(19,4) (flat amount already accrued, 0 = none yet) instead of a bool, so P6 can see what was charged.
- P1: the ADD migration for `marketer_commission_country_settings` also performs the data copy (`affiliate_commission_amount > 0` -> `affiliate_commission_flat`, mode `fixed`); the legacy dump of dropped values stays in P3 with the DROP. New columns default to mode `percentage`, rate 0, flat 0, share 0.
- P1: no admin UI or seeded row exists for `marketer_campaign_allowed_fulfilment_models`; the only place it is defined is the code default, now one constant `MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS = ['fbn','fbm','cross_dock']` used by all 5 readers. A stored setting row, if an environment has one, still wins and must include `cross_dock` by hand.
- P1: `MarketingFeeType` labels are keyed by value in `lang/{en,ar}/enums/marketing_fee_type.php` (EnumHelpers convention); the Arabic labels keep the Latin codes with "FBN (تنفيذ ناوي)".
- P1: accrual table gets `updated_at` as well as `created_at` (rows change status to settled/reversed); `marketer_payout_wallet_tx_id` has no FK (wallet transaction table is polymorphic across wallet owners).
