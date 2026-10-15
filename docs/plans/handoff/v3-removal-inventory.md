# v3 removal inventory (R0)

Generated 2026-10-15 from `main` + the R0-R2 additive work. Source of truth: plan section 4.2 of `docs/plans/marketer-types-commissions-refactor.md` with the owner's answers applied.
All paths are relative to `backend/` unless prefixed (`frontend/`). Format: `file`: line numbers of every match. Migrations under `database/migrations/` are historical and are NOT listed (they stay; the removal is a new migration).
Nothing here is removed yet. The last phase (R9) deletes these using this list and then re-runs the same greps to prove zero hits (see "Verification" at the end).

## Owner decisions that shape the removal

| Q | Decision | Effect on this inventory |
|---|---|---|
| Q13 | Keep `marketer_sale_settlements` | Section N is informational only. `SettleMarketerSalesJob` stays because it belongs to it (listed in G with a KEEP flag). |
| Q14 | Remove promotion targets, fines, `promotion:*` commands | Section M is fully removable (tables `promotion_category_targets`, `marketer_promotion_fines`, `marketer_promotion_reminders`, profile columns `promotion_category`, `monthly_promotion_target`). |
| Q15 | Remove only the flash-sale commission bonus | Section K: drop bonus columns / code only; keep `flash_sale_marketer_invitations` and `FlashSaleMarketerInvitation`. |
| Q9 | Remove the TravelAgencyCampaignOffer layer | Section L entirely (tables, models, controllers, views, routes, permissions, notification). |
| Q12 | Remove the influencer fee payer setting | Section J (`marketer_influencer_fee_payer`). |
| Q10 | Remove tiered rules / flash bonus / last-click flat types | Sections B, C, K. |

## Tables and columns to drop (data-removal migration, R9)

| Object | Section |
|---|---|
| `marketer_category_commissions`, `open_market_category_commissions` | A |
| `marketer_campaign_tiered_rules`, `marketer_campaign_category_rules`; `marketer_campaign_conversions.tiered_rule_id`, `sale_number_in_campaign`; `marketer_campaigns.product_category_selection_mode`, `classified_category_selection_mode` | B |
| `marketer_campaigns.commission_type` (collapse), `marketer_commission_amount`, `platform_commission_amount` (decide after the usage list in C) | C |
| `marketer_campaign_invitations`: `marketer_fee_share_pct`, `marketer_payout_amount`, `marketer_credited_at`, `marketer_payout_wallet_tx_id`, `platform_fee_refunded_at/_amount/_wallet_tx_id/_reason`, `platform_fee_voided_at`, `marketer_credit_reversed_at`, `marketer_credit_reversal_wallet_tx_id`, `marketer_clawback_amount`, status `refunded`; `platform_fee_marketing_amount` only if not reused as the campaign-fee carrier | D |
| `marketer_campaign_fee_accruals`, `marketer_campaign_fee_settlements`, `payout_items.affiliate_fee_settlement_id`, `payout_items.marketing_fee_amount` (re-check), `marketer_campaigns.affiliate_fee_accrued/_settled_at`, `marketing_fee_fixed_accrued` | E |
| `marketer_profiles`: `platform_cut_percentage`, `commission_discount_type/_flat/_percentage/_notes`; `marketer_campaign_conversions`: `platform_cut_*`, `platform_cut_vat_*` snapshot columns (superseded by `commission_fee_amount` / `net_commission_amount`) | H |
| `marketer_commission_country_settings` (whole table; migrate rows into `marketer_default_commissions` first) | I |
| `marketer_influencer_fee_country_settings`; settings key `marketer_influencer_fee_payer` | J |
| `flash_sale_marketer_invitations`: bonus columns (`fixed_bonus`, `extra_commission_*`); `marketer_campaign_conversions.flash_sale_id`, `flash_sale_bonus_amount` | K |
| `travel_agency_campaign_offers`, `travel_agency_campaign_offer_packages`, `travel_agency_campaign_invitations` | L |
| `promotion_category_targets`, `marketer_promotion_fines`, `marketer_promotion_reminders`; `marketer_profiles.promotion_category`, `monthly_promotion_target` | M |

Migrations that must NOT be edited or deleted (history): everything in `database/migrations/` created before `2026_10_16_*`.

Do not drop in R9 without a replacement in place: `marketer_campaign_conversions.net_commission_amount`, `gross_commission_amount`, `reversed_at`, `travel_booking_id`, `classified_inquiry_id`, `conversion_type` (reused by v3).

Naming note: `Marketer*` hits that merely contain the word "tiered" or "commission_type" in unrelated modules (vendor commission tiers, `CommissionType` enum, `category_commission_tiers`) are out of scope and were filtered where possible; confirm each hit in C before deleting.

---

## A. Legacy mirrors: marketer_category_commissions / open_market_category_commissions

Pattern: `marketer_category_commissions|MarketerCategoryCommission|open_market_category_commissions|OpenMarketCategoryCommission|openMarketCategoryCommission`

32 hits in 10 files.

**App code**

- `app/Http/Controllers/Admin/MarketerController.php`: 15, 21, 392, 397, 404, 424, 427, 436
- `app/Services/MarketerCampaignService.php`: 20, 24, 782, 888, 928, 929
- `app/Models/MarketerCommissionCountrySetting.php`: 47
- `app/Models/MarketerCategoryCommission.php`: 10
- `app/Models/OpenMarketCategoryCommission.php`: 9
- `app/Models/Marketer.php`: 106

**Lang**

- `lang/ar/commissions.php`: 70
- `lang/en/commissions.php`: 70

**Tests**

- `tests/Feature/Admin/MarketerCategoryCommissionStoreTest.php`: 8, 10, 17, 41, 49, 51, 72, 86, 87, 101, 112
- `tests/Feature/Commission/CommissionRuleResolverTest.php`: 83

## B. Tiered rules and campaign category rules

Pattern: `marketer_campaign_tiered_rules|MarketerCampaignTieredRule|tieredRules|tiered_rule_id|tiered_rules|marketer_campaign_category_rules|MarketerCampaignCategoryRule|categoryRules\b|category_selection_mode`

94 hits in 21 files.

**App code**

- `app/Http/Controllers/Marketer/CampaignController.php`: 45
- `app/Http/Controllers/Admin/MarketerCampaignController.php`: 12, 78, 82, 83, 319, 322, 337, 338, 342, 343, 372, 386, 393
- `app/Http/Controllers/Partner/MarketerCampaignController.php`: 129, 192, 223, 248, 271, 292
- `app/Http/Controllers/Partner/ListingController.php`: 622, 910, 911, 912, 1083, 1198, 1199, 1200, 1256
- `app/Services/LastClickAttributionService.php`: 87, 129, 168, 230
- `app/Services/MarketerCampaignService.php`: 19, 64, 263, 264, 265, 538, 561, 683, 685, 686, 817
- `app/Models/MarketerCampaignCategoryRule.php`: 11, 12, 15
- `app/Models/MarketerCampaign.php`: 22, 141, 143, 148, 150, 152
- `app/Models/MarketerCampaignTieredRule.php`: 10
- `app/Models/MarketerCampaignConversion.php`: 17, 86

**Views**

- `resources/js/partner/listing-create-campaign.js`: 39, 96, 100, 104, 116, 295
- `resources/views/partner/marketer_campaigns/show.blade.php`: 188, 190, 200
- `resources/views/partner/marketer_campaigns/edit.blade.php`: 5, 17
- `resources/views/partner/listings/edit.blade.php`: 401, 403, 405, 409, 413, 420, 461
- `resources/views/partner/listings/create.blade.php`: 468
- `resources/views/admin/marketer_campaigns/show.blade.php`: 210, 216, 329, 339, 349
- `resources/views/admin/marketer_campaigns/create.blade.php`: 86, 94

**Lang**

- `lang/ar/admin.php`: 1631
- `lang/ar/partner.php`: 521, 564, 2595, 2712, 2784
- `lang/en/admin.php`: 1626
- `lang/en/partner.php`: 546, 589, 2595, 2712, 2784

## C. marketer_campaigns.commission_type variants and redundant amount columns

Pattern: `commission_type|flat_per_order|flat_per_click|'last_click'|\"last_click\"|marketer_commission_amount|sale_number_in_campaign`

176 hits in 52 files.

**App code**

- `app/Http/Controllers/TravelAgencyPortal/CampaignController.php`: 134, 175
- `app/Http/Controllers/Admin/MarketerCampaignController.php`: 315, 417, 423, 502, 511
- `app/Http/Controllers/Partner/MarketerCampaignController.php`: 129, 188, 291, 468, 477
- `app/Services/Marketer/InvitationWhatsAppMessage.php`: 35, 36, 37, 38, 39, 41, 42
- `app/Http/Controllers/Api/TravelAgencyPortal/CampaignController.php`: 66, 113
- `app/Services/MarketerCampaignService.php`: 62, 209, 233, 236, 253, 257, 263, 418, 424, 441, 487, 514, 520, 535, 544, 547, 555, 561, 677, 684, 780, 800, 814, 841, 880, 890, 920
- `app/Notifications/Vendor/CampaignApprovedNotification.php`: 28
- `app/Enums/MarketplaceShippingRuleCommissionType.php`: 8, 10
- `app/Enums/CommissionType.php`: 12, 13
- `app/Enums/AdminInvitationCommissionType.php`: 13, 16, 17
- `app/Models/MarketerCampaign.php`: 23, 24, 50, 258, 259
- `app/Models/TravelAgencyCampaignOffer.php`: 28, 59
- `app/Models/MarketerCampaignConversion.php`: 17

**Views**

- `resources/js/travel_agency/campaigns.js`: 110
- `resources/js/partner/campaign-offers.js`: 108
- `resources/views/travel-agency/campaigns/show.blade.php`: 27, 58, 59, 60, 144
- `resources/views/travel-agency/campaigns/index.blade.php`: 132
- `resources/views/travel-agency/campaigns/create.blade.php`: 20, 168, 220, 224, 225, 226, 229, 230
- `resources/views/partner/marketer_campaigns/show.blade.php`: 133, 136, 188
- `resources/views/partner/marketer_campaigns/edit.blade.php`: 17
- `resources/views/partner/marketer_campaigns/index.blade.php`: 77, 95
- `resources/views/partner/listings/partials/marketer-campaign-tab.blade.php`: 112
- `resources/views/admin/marketer_campaigns/show.blade.php`: 172, 175, 233, 234, 329
- `resources/views/admin/marketer_campaigns/index.blade.php`: 89, 119
- `resources/views/admin/marketer_campaigns/create.blade.php`: 58, 60, 61, 62, 63
- `resources/views/admin/docs/features/marketer-campaigns.blade.php`: 13, 15, 16, 17
- `resources/views/admin/marketers/show.blade.php`: 613, 652, 655
- `resources/views/marketer/campaigns/show.blade.php`: 24
- `resources/views/marketer/campaigns/active.blade.php`: 41
- `resources/views/marketer/invitations/index.blade.php`: 39, 40, 41, 42, 43, 46

**Lang**

- `lang/ar/docs/features/marketer-campaigns.php`: 11, 15, 20
- `lang/ar/enums/admin_invitation_commission_type.php`: 6
- `lang/ar/enums/commission_type.php`: 5, 6
- `lang/ar/marketer.php`: 349, 350, 351, 352
- `lang/ar/commissions.php`: 82, 154, 204
- `lang/en/docs/features/marketer-campaigns.php`: 11, 15, 20
- `lang/en/enums/admin_invitation_commission_type.php`: 6
- `lang/en/enums/commission_type.php`: 5, 6
- `lang/en/marketer.php`: 349, 350, 351, 352
- `lang/en/commissions.php`: 82, 154, 204

**Tests**

- `tests/Feature/Marketer/MarketerLifecycleTest.php`: 99, 101, 213, 215
- `tests/Feature/MarketerAttributionConversionTest.php`: 187
- `tests/Feature/Commission/CommissionReconciliationTest.php`: 147
- `tests/Feature/Commission/LegacyMarketerCommissionAmountsMigrationTest.php`: 29, 70
- `tests/Feature/Campaigns/PeerPromotionServiceTest.php`: 60, 193, 197
- `tests/Feature/Campaigns/MarketerPerSaleCommissionTest.php`: 38, 125, 134, 151
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 350
- `tests/Feature/Campaigns/MarketerCampaignDefaultCommissionTest.php`: 47, 56, 65, 74, 86, 92
- `tests/Feature/Campaigns/MarketingFeeQuoteSnapshotTest.php`: 199
- `tests/Feature/Campaigns/CampaignSamplesPartnerTest.php`: 103
- `tests/Feature/MarketerCampaignSourcesTest.php`: 46, 48, 115, 117, 163, 175, 205, 207, 237, 239
- `tests/Support/CampaignSampleFixtures.php`: 85, 87

## D. Invitation fee-share / payout / refund / clawback columns

Pattern: `marketer_fee_share_pct|marketer_payout_amount|marketer_credited_at|marketer_payout_wallet_tx_id|platform_fee_refund|platform_fee_voided_at|marketer_credit_reversed_at|marketer_credit_reversal_wallet_tx_id|marketer_clawback_amount|platform_fee_marketing_amount`

180 hits in 24 files.

**App code**

- `app/Http/Controllers/Marketer/FinanceController.php`: 117, 118
- `app/Http/Controllers/Partner/FinanceController.php`: 178, 182, 197, 198, 201
- `app/Services/MarketerInvitationRefundService.php`: 13, 22, 27, 64, 65, 72, 73, 100, 101, 102, 103, 115, 132, 133, 137, 141, 142, 161, 162, 169, 170, 171, 197, 208, 209, 212, 214
- `app/Services/MarketingFeeReportService.php`: 21, 58, 66, 67, 74, 78, 82, 155, 156, 243, 252, 253, 255, 290, 298, 299, 300, 301, 306, 322, 333, 335, 336, 337, 409, 410
- `app/Services/PayoutCalculationService.php`: 291
- `app/Services/MarketerFeePayoutService.php`: 10, 13, 14, 32, 33, 34, 36, 55, 58, 62, 63
- `app/Services/MarketerCampaignService.php`: 1223, 1224, 1225, 1279
- `app/Services/Vendor/TransactionFeedService.php`: 169, 174, 180, 181, 184, 191
- `app/Jobs/RetryMarketerFeeCreditsJob.php`: 26, 27, 28
- `app/Jobs/GenerateVendorPayoutsJob.php`: 123
- `app/Models/MarketerCampaignInvitation.php`: 28, 29, 30, 31, 60, 61, 62, 63, 64, 65, 66, 67, 68, 118, 150, 155, 161

**Views**

- `resources/views/partner/marketer_campaigns/show.blade.php`: 260, 314, 328, 329
- `resources/views/admin/marketer_campaigns/financials.blade.php`: 96
- `resources/views/admin/marketer_campaigns/show.blade.php`: 451, 452, 454, 455, 456, 457, 459, 460, 462, 463, 464, 494
- `resources/views/marketer/campaigns/show.blade.php`: 30, 33, 34, 35, 37, 38, 40, 41
- `resources/views/marketer/finance/wallet.blade.php`: 34

**Tests**

- `tests/Feature/MarketingFeeServiceTest.php`: 148
- `tests/Feature/Reports/MarketingFeePanelReportsTest.php`: 191, 344, 348
- `tests/Feature/Campaigns/CancellationRefundSymmetryTest.php`: 164, 165, 166, 167, 171, 172, 173, 211, 212, 227, 228, 257, 258, 260, 301, 313, 314
- `tests/Feature/Campaigns/InfluencerChargeAndMarketerCreditTest.php`: 140, 141, 147, 155, 156, 157, 158, 172, 175, 189, 216, 217, 218, 235, 236, 247, 250, 274, 286
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 231, 232, 233, 239, 240, 241
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 364, 366
- `tests/Feature/Campaigns/CampaignInfluencerFeeServiceTest.php`: 181
- `tests/Support/MarketingFeeReportCase.php`: 201, 204

## E. Affiliate per-order fee accruals / settlements

Pattern: `marketer_campaign_fee_accruals|MarketerCampaignFeeAccrual|marketer_campaign_fee_settlements|MarketerCampaignFeeSettlement|affiliate_fee_settlement_id|feeSettlements|feeAccruals|affiliate_fee_accrued|affiliate_fee_settled_at|marketing_fee_fixed_accrued|affiliate_marketing_fee`

191 hits in 28 files.

**App code**

- `app/Http/Controllers/Admin/FinancialReportController.php`: 262
- `app/Http/Controllers/Partner/PayoutController.php`: 131
- `app/Http/Controllers/Partner/FinanceController.php`: 6, 217, 226
- `app/Services/AffiliateFeeAccrualService.php`: 8, 15, 37, 52, 53, 60, 67, 70, 81, 83, 84, 92, 93, 106, 119, 120, 122, 124, 125, 127, 145, 147
- `app/Services/MarketingFeeReportService.php`: 8, 9, 36, 88, 89, 95, 100, 184, 191, 200, 206, 207, 213, 230, 259, 261, 268, 272, 353, 354, 355, 357, 358, 514
- `app/Services/AffiliateFeeSettlementService.php`: 7, 8, 21, 25, 27, 44, 57, 65, 67, 81, 82, 89, 94, 96, 103, 104, 109, 126, 130, 131, 145, 165, 169, 170, 184, 209, 210, 219
- `app/Services/PayoutCalculationService.php`: 12, 300, 303, 311, 314, 315
- `app/Services/Vendor/TransactionFeedService.php`: 5, 55, 207, 222
- `app/Jobs/GenerateVendorPayoutsJob.php`: 11, 130, 132, 275, 276, 285
- `app/Models/MarketerCampaignFeeSettlement.php`: 11, 48, 53
- `app/Models/MarketerCampaignFeeAccrual.php`: 10, 56
- `app/Models/PayoutItem.php`: 22, 66
- `app/Models/MarketerCampaign.php`: 33, 57, 58, 59, 160, 162, 165, 167

**Views**

- `resources/views/partner/marketer_campaigns/create.blade.php`: 241
- `resources/views/partner/payouts/show.blade.php`: 23
- `resources/views/partner/finance/transactions.blade.php`: 166, 168
- `resources/views/partner/finance/_marketing_totals.blade.php`: 1, 2, 21
- `resources/views/admin/reports/financial.blade.php`: 399
- `resources/views/admin/payouts/show.blade.php`: 162
- `resources/views/components/affiliate-fee-history.blade.php`: 4, 8, 26, 28, 39, 40

**Lang**

- `lang/ar/marketing_fees.php`: 63
- `lang/en/marketing_fees.php`: 63

**Tests**

- `tests/Feature/MarketingFeeServiceTest.php`: 145, 157
- `tests/Feature/Reports/MarketingFeeFinancialReportTest.php`: 42, 56, 89
- `tests/Feature/Reports/MarketingFeePanelReportsTest.php`: 300, 304, 316, 353, 363, 373, 383
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 16, 17, 322, 402, 411, 416, 446, 465, 475, 483, 484, 486, 489, 501, 508, 511, 513, 519, 520
- `tests/Feature/Campaigns/AffiliateFeeAccrualAndSettlementTest.php`: 15, 16, 140, 146, 160, 170, 171, 172, 185, 204, 218, 219, 226, 227, 248, 252, 268, 299, 302, 304, 334, 376, 378, 379, 449, 455, 456, 469, 472, 473, 483, 488
- `tests/Feature/Campaigns/MarketingFeeQuoteSnapshotTest.php`: 252

## F. Superseded services

Pattern: `CommissionRuleResolver|AffiliateCommissionResolver|MarketerCommissionRateService|MarketerCommissionStatementService|MarketerFeeShareResolver|MarketerFeePayoutService|AffiliateFeeAccrualService|AffiliateFeeSettlementService|MarketerInvitationRefundService|MarketingFeeReportService|LastClickAttributionService`

188 hits in 51 files.

**App code**

- `app/Http/Controllers/Marketer/ReportController.php`: 9, 72
- `app/Http/Controllers/Marketer/FinanceController.php`: 15, 16, 72, 121
- `app/Http/Controllers/Admin/MarketerCampaignController.php`: 18, 23, 219, 404
- `app/Http/Controllers/Admin/MarketerController.php`: 25, 26, 163, 178
- `app/Http/Controllers/Admin/MarketingFeeController.php`: 11, 95
- `app/Http/Controllers/Admin/WalletController.php`: 21, 224
- `app/Http/Controllers/Customer/CheckoutController.php`: 71, 107, 1119
- `app/Http/Controllers/Partner/MarketerCampaignController.php`: 16, 230
- `app/Http/Controllers/Partner/PayoutController.php`: 8, 133
- `app/Http/Controllers/Partner/ListingController.php`: 37, 43, 397, 415
- `app/Http/Controllers/Partner/FinanceController.php`: 13, 257
- `app/Http/Controllers/Api/Marketer/ReportController.php`: 8, 24
- `app/Http/Controllers/Api/Marketer/FinanceController.php`: 11, 53, 93
- `app/Http/Controllers/Api/ReferralTrackingController.php`: 7, 15
- `app/Services/MarketerCommissionStatementService.php`: 30
- `app/Services/MarketerFeeShareResolver.php`: 11
- `app/Services/AffiliateFeeAccrualService.php`: 17, 19
- `app/Services/LastClickAttributionService.php`: 40, 48, 181, 196, 266, 271
- `app/Services/MarketerInvitationRefundService.php`: 25
- `app/Services/MarketingFeeReportService.php`: 24
- `app/Services/AffiliateFeeSettlementService.php`: 23
- `app/Services/MarketerConversionReversalService.php`: 60, 75, 93, 136, 143
- `app/Services/CommissionRuleResolver.php`: 8
- `app/Services/AffiliateCommissionResolver.php`: 12, 19, 27
- `app/Services/MarketerCampaignQuoteService.php`: 73, 80, 151
- `app/Services/MarketerFeePayoutService.php`: 16
- `app/Services/FinancialReportService.php`: 448, 460
- `app/Services/MarketerCampaignService.php`: 429, 435, 610, 611, 628, 631, 856, 861, 863, 900, 903, 1140, 1209, 1539
- `app/Services/Vendor/FinanceService.php`: 10, 44
- `app/Services/MarketerCommissionRateService.php`: 16, 26
- `app/Jobs/RetryMarketerFeeCreditsJob.php`: 6, 21
- `app/Jobs/AffiliateFeeSettlementJob.php`: 5, 21
- `app/Jobs/RetryInvitationFeeRefundsJob.php`: 5, 20
- `app/Jobs/ReleaseMarketerPendingCommissionJob.php`: 8, 61, 69
- `app/Jobs/ApproveMarketerConversionsJob.php`: 7, 8, 10, 72, 89, 126, 134
- `app/Jobs/GenerateVendorPayoutsJob.php`: 22, 302
- `app/Models/MarketerProfile.php`: 122

**Tests**

- `tests/Feature/MarketerFeeShareResolverTest.php`: 8, 12, 30, 43
- `tests/Feature/MarketerNetCommissionStatementTest.php`: 17, 68
- `tests/Feature/Commission/CommissionReconciliationTest.php`: 9, 171
- `tests/Feature/Commission/CommissionRuleResolverTest.php`: 10, 16, 49, 72, 104
- `tests/Feature/Reports/MarketingFeeFinancialReportTest.php`: 11, 142
- `tests/Feature/Reports/MarketingFeePanelReportsTest.php`: 14, 296, 390
- `tests/Feature/Campaigns/CancellationRefundSymmetryTest.php`: 29, 30, 131, 137, 186, 187, 188, 218, 223, 224, 267, 318, 319, 337
- `tests/Feature/Campaigns/InfluencerChargeAndMarketerCreditTest.php`: 29, 239, 243, 244, 259, 327
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 26, 30, 464, 480, 481, 509, 510
- `tests/Feature/Campaigns/ConversionStatementLedgerTest.php`: 17, 84, 104, 145
- `tests/Feature/Campaigns/AffiliateFeeAccrualAndSettlementTest.php`: 28, 29, 178, 202, 238, 239, 240, 275, 289, 290, 291, 339, 340, 346, 431, 435, 436, 468, 486
- `tests/Feature/Campaigns/AffiliateCommissionResolverTest.php`: 8, 16, 22, 28
- `tests/Feature/Campaigns/ReferralCodeAttributionTest.php`: 7, 205, 206
- `tests/Support/MarketingFeeReportCase.php`: 25, 28, 29, 30, 164, 165, 168, 192, 216, 253

## G. Jobs to remove

Pattern: `SettleMarketerSalesJob|CollectPendingInfluencerFeesJob|RetryMarketerFeeCreditsJob|RetryInvitationFeeRefundsJob|AffiliateFeeSettlementJob`

64 hits in 17 files.

**App code**

- `app/Services/MarketerCampaignService.php`: 1234
- `app/Jobs/RetryMarketerFeeCreditsJob.php`: 17
- `app/Jobs/CollectPendingInfluencerFeesJob.php`: 17
- `app/Jobs/AffiliateFeeSettlementJob.php`: 17
- `app/Jobs/RetryInvitationFeeRefundsJob.php`: 16
- `app/Jobs/GenerateVendorPayoutsJob.php`: 300
- `app/Jobs/SettleMarketerSalesJob.php`: 16

**Routes / config**

- `routes/console.php`: 5, 11, 27, 28, 29, 65, 182, 183, 185, 187

**Tests**

- `tests/Feature/MarketerPromotionFlowTest.php`: 7, 215
- `tests/Feature/Campaigns/CancellationRefundSymmetryTest.php`: 8, 10, 11, 187, 188, 218, 223, 224, 263, 318, 319, 337
- `tests/Feature/Campaigns/InfluencerChargeAndMarketerCreditTest.php`: 8, 10, 193, 194, 239, 243, 244, 327
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 8, 481, 509
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 10, 285
- `tests/Feature/Campaigns/MarketerSaleSettlementTest.php`: 7, 99
- `tests/Feature/Campaigns/CampaignInfluencerFeeServiceTest.php`: 6, 107, 111, 118
- `tests/Feature/Campaigns/CampaignInfluencerFeePayoutTest.php`: 7, 141, 156, 210
- `tests/Feature/Campaigns/AffiliateFeeAccrualAndSettlementTest.php`: 8, 240, 291, 339, 340, 431, 435, 436, 468, 486

## H. marketer_profiles platform cut / commission discount

Pattern: `platform_cut_percentage|platform_cut_base_amount|platform_cut_discount_amount|platform_cut_amount|platform_cut_vat|commission_discount_type|commission_discount_flat|commission_discount_percentage|commission_discount_notes|MarketerCommissionDiscountType`

111 hits in 13 files.

**App code**

- `app/Http/Requests/Admin/UpdateVendorRequest.php`: 31, 32, 33, 34
- `app/Http/Controllers/Marketer/FinanceController.php`: 59
- `app/Http/Controllers/Admin/MarketerController.php`: 5, 256, 257, 258, 259, 260, 272, 273, 274, 275, 302, 303, 304, 309, 314
- `app/Services/MarketerCommissionStatementService.php`: 5, 20, 43, 44, 45, 46, 47, 80, 92, 93, 94, 95, 96, 97, 112, 113, 114, 115, 116, 117, 166, 167
- `app/Models/Vendor.php`: 81, 82, 83, 84, 108, 109, 110, 131, 132, 134, 135
- `app/Enums/MarketerCommissionDiscountType.php`: 7
- `app/Models/MarketerProfile.php`: 6, 63, 64, 65, 66, 67, 89, 90, 91, 92, 131, 132, 133
- `app/Models/MarketerCampaignConversion.php`: 21, 22, 44, 45, 46, 47

**Views**

- `resources/views/admin/marketers/show.blade.php`: 187, 188, 191

**Tests**

- `tests/Feature/MarketerNetCommissionStatementTest.php`: 64, 65, 66, 90, 143, 144, 146, 158, 200, 203, 204, 238, 239, 242, 243, 245, 254, 256, 257, 265, 275
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 134, 319, 320, 573
- `tests/Feature/Campaigns/ConversionStatementLedgerTest.php`: 48, 92, 110
- `tests/Unit/CommissionDiscountTest.php`: 5, 16, 17, 18, 26, 27, 28

## I. marketer_commission_country_settings and influencer fee-share / affiliate default

Pattern: `marketer_commission_country_settings|MarketerCommissionCountrySetting|influencer_fee_share_pct|affiliate_commission_mode|affiliate_commission_rate|affiliate_commission_flat|marketer-commission\.update|marketerCommission`

213 hits in 33 files.

**App code**

- `app/Http/Controllers/Admin/MarketerCampaignController.php`: 15, 91
- `app/Http/Controllers/Admin/CategoryController.php`: 15, 195, 211, 504, 505, 506, 507, 511, 514, 517, 518, 519, 520
- `app/Http/Controllers/Customer/CheckoutController.php`: 1208, 1209, 1335, 1336
- `app/Http/Controllers/Partner/ListingController.php`: 19, 379, 380, 381, 382, 393, 414, 415, 416, 417, 418
- `app/Services/MarketerFeeShareResolver.php`: 5, 16, 18
- `app/Services/Checkout/CheckoutPricingEngine.php`: 518, 520, 529, 535, 543, 563, 595, 596, 608, 622, 644, 664, 1059, 1060, 1066, 1067, 1094, 1095
- `app/Services/MarketerCampaignService.php`: 783, 863, 866, 875, 880, 903, 906, 915, 920
- `app/Services/LedgerService.php`: 111, 123, 132, 143, 197
- `app/Services/AffiliateCommissionResolver.php`: 7, 14, 58, 64, 65, 66, 77
- `app/Models/MarketerCommissionCountrySetting.php`: 10, 16, 21, 22, 23, 52, 53, 54

**Routes / config**

- `routes/admin.php`: 329

**Views**

- `resources/js/partner/listing-create-campaign.js`: 213, 215, 216, 217
- `resources/views/admin/marketer_campaigns/show.blade.php`: 246, 252, 254, 256
- `resources/views/admin/categories/_form.blade.php`: 475, 477, 493, 522, 523, 524, 533, 542, 543, 548, 549

**Lang**

- `lang/ar/admin.php`: 2319
- `lang/en/admin.php`: 2314

**Tests**

- `tests/Feature/MarketerFeeShareResolverTest.php`: 7, 18, 20, 57, 61, 62, 63
- `tests/Feature/MarketingFeeServiceTest.php`: 151, 152, 155
- `tests/Feature/Checkout/CheckoutMoneySplitTest.php`: 59, 66, 83
- `tests/Feature/Admin/CategoryMarketersTabTest.php`: 8, 50, 55, 57, 62, 64, 71, 74, 86, 87, 91, 92, 93, 94, 99, 101, 102, 103, 105, 106, 108, 114, 115, 116, 117, 118, 119, 120, 121, 129, 132, 138, 139, 143, 144, 145, 151
- `tests/Feature/Commission/VendorCampaignPricingTest.php`: 6, 65, 67, 71, 73, 74, 75, 84, 85
- `tests/Feature/Commission/LegacyMarketerCommissionAmountsMigrationTest.php`: 22, 57, 69, 81, 99, 100, 102, 103, 110
- `tests/Feature/Reports/MarketingFeeFinancialReportTest.php`: 7, 132
- `tests/Feature/Campaigns/CancellationRefundSymmetryTest.php`: 17, 82, 84
- `tests/Feature/Campaigns/PeerPromotionServiceTest.php`: 8, 183, 185
- `tests/Feature/Campaigns/InfluencerChargeAndMarketerCreditTest.php`: 16, 81, 83, 270
- `tests/Feature/Campaigns/MarketerPerSaleCommissionTest.php`: 8, 43, 45, 46
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 158, 159, 160
- `tests/Feature/Campaigns/MarketerCampaignDefaultCommissionTest.php`: 6, 34, 36, 37
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 22, 92, 94
- `tests/Feature/Campaigns/MarketerCampaignOpenToAffiliatesTest.php`: 9, 153, 155, 187, 189, 204, 206, 220, 222
- `tests/Feature/Campaigns/AffiliateCommissionResolverTest.php`: 6, 31, 33, 35, 142
- `tests/Support/MarketingFeeReportCase.php`: 16, 96, 98

## J. marketer_influencer_fee_country_settings and settings keys

Pattern: `marketer_influencer_fee_country_settings|MarketerInfluencerFeeCountrySetting|marketer_influencer_fee_payer|platform_fee_payer`

74 hits in 28 files.

**App code**

- `app/Http/Controllers/Admin/MarketerSettingsController.php`: 8, 28, 54
- `app/Http/Controllers/Partner/ListingController.php`: 20, 391, 453
- `app/Http/Controllers/Partner/FinanceController.php`: 166
- `app/Services/Marketer/InvitationWhatsAppMessage.php`: 6, 60, 67
- `app/Services/MarketerInvitationRefundService.php`: 41, 63, 195
- `app/Services/MarketingFeeReportService.php`: 151, 235, 289, 405
- `app/Services/PayoutCalculationService.php`: 271
- `app/Services/MarketerCampaignQuoteService.php`: 9, 47, 203
- `app/Services/MarketerFeePayoutService.php`: 31
- `app/Services/MarketerCampaignService.php`: 22, 1102, 1192, 1227
- `app/Services/Vendor/TransactionFeedService.php`: 149
- `app/Jobs/RetryMarketerFeeCreditsJob.php`: 25
- `app/Jobs/CollectPendingInfluencerFeesJob.php`: 26
- `app/Models/MarketerCampaignInvitation.php`: 27
- `app/Models/MarketerInfluencerFeeCountrySetting.php`: 10

**Tests**

- `tests/Feature/MarketerPromotionFlowTest.php`: 12, 189, 202, 211, 223, 233
- `tests/Feature/Commission/VendorCampaignPricingTest.php`: 8, 56
- `tests/Feature/Reports/MarketingFeeFinancialReportTest.php`: 8, 133
- `tests/Feature/Campaigns/CancellationRefundSymmetryTest.php`: 18, 71, 73
- `tests/Feature/Campaigns/InfluencerChargeAndMarketerCreditTest.php`: 17, 70, 72, 286
- `tests/Feature/Campaigns/MarketingFeesEndToEndTest.php`: 80, 236
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 23, 87, 96
- `tests/Feature/Campaigns/CampaignInfluencerFeeServiceTest.php`: 9, 43, 49, 151, 155, 179
- `tests/Feature/Campaigns/CampaignInfluencerFeePayoutTest.php`: 12, 61, 62
- `tests/Feature/Campaigns/MarketerCampaignOpenToAffiliatesTest.php`: 11, 102, 182
- `tests/Feature/Campaigns/CampaignInfluencerFeePanelsTest.php`: 9, 43, 44
- `tests/Feature/Campaigns/MarketingFeeQuoteSnapshotTest.php`: 9, 63
- `tests/Support/MarketingFeeReportCase.php`: 17, 86, 88, 203

## K. Flash-sale marketer commission bonus (Q15: bonus only; keep the invitation)

Pattern: `extra_commission|fixed_bonus|flash_sale_bonus_amount|flash_sale_bonus|extra_commission_flat_amount|flashSaleBonus`

74 hits in 24 files.

**App code**

- `app/Http/Controllers/Marketer/FlashSaleController.php`: 48
- `app/Http/Controllers/Admin/FlashSaleController.php`: 655, 656, 659, 660, 676, 677, 678, 710, 711, 712, 713
- `app/Services/LastClickAttributionService.php`: 201, 210, 216, 232
- `app/Services/MarketerConversionReversalService.php`: 57
- `app/Notifications/Marketer/FlashSaleInvitationNotification.php`: 28, 29, 30, 31
- `app/Models/MarketerCampaignConversion.php`: 18, 40, 61
- `app/Models/FlashSaleMarketerInvitation.php`: 19, 20, 21, 28, 29, 36, 38, 39, 41, 42, 50, 58, 59, 60, 62, 63

**Views**

- `resources/js/admin/flash-sale-detail.js`: 676, 677
- `resources/views/admin/flash-sales/show.blade.php`: 777, 781, 783
- `resources/views/marketer/flash-sales/index.blade.php`: 23, 54
- `resources/views/marketer/finance/commissions.blade.php`: 105, 122, 123

**Lang**

- `lang/ar/admin.php`: 5125, 5126, 5127
- `lang/ar/marketer.php`: 293, 320, 326
- `lang/ar/commissions.php`: 146
- `lang/en/admin.php`: 5116, 5117, 5118
- `lang/en/marketer.php`: 293, 320, 326
- `lang/en/commissions.php`: 146

**Tests**

- `tests/Feature/Marketer/MarketerLifecycleTest.php`: 313
- `tests/Feature/MarketerNetCommissionStatementTest.php`: 119
- `tests/Feature/MarketerAttributionConversionTest.php`: 133
- `tests/Feature/Commission/FlashSaleBonusModeTest.php`: 17, 24, 31, 36
- `tests/Feature/Campaigns/ConversionStatementLedgerTest.php`: 78
- `tests/Feature/Campaigns/AffiliateFeeAccrualAndSettlementTest.php`: 133
- `tests/Support/MarketingFeeReportCase.php`: 244

## L. Travel agency campaign offer layer (Q9)

Pattern: `TravelAgencyCampaignOffer|TravelAgencyCampaignInvitation|travel_agency_campaign_offer|travel_agency_campaign_invitation|campaign-offers|campaign_offers|VendorCampaignOfferStatus|VendorCampaignInvitationStatus`

111 hits in 19 files.

**App code**

- `app/Http/Controllers/TravelAgencyPortal/CampaignController.php`: 7, 8, 14, 15, 16, 18, 19, 38, 64, 69, 70, 73, 74, 75, 99, 168, 185, 189, 190, 202, 214, 215, 221, 225, 229, 231, 233, 236, 240, 244, 247, 251, 254, 258, 262, 265, 269, 272, 276, 285, 289, 308, 309, 311, 321, 322, 334, 338, 342
- `app/Http/Controllers/Api/TravelAgencyPortal/CampaignController.php`: 5, 6, 8, 24, 27, 38, 41, 45, 46, 47, 52, 53, 85, 97, 99, 100, 101
- `app/Notifications/Admin/TravelAgencyCampaignOfferSubmitted.php`: 5, 9, 11, 15
- `app/Enums/VendorCampaignOfferStatus.php`: 7
- `app/Enums/VendorCampaignInvitationStatus.php`: 7
- `app/Models/TravelAgencyCampaignOfferPackage.php`: 9, 14, 27
- `app/Models/TravelAgencyCampaignOffer.php`: 8, 17, 60, 81, 86, 93, 94, 103, 108, 113
- `app/Models/TravelAgencyCampaignInvitation.php`: 5, 10, 15, 28, 33, 48

**Views**

- `resources/js/travel_agency/campaigns.js`: 5
- `resources/js/admin/vendor-campaign-offers.js`: 2, 56, 79
- `resources/views/travel-agency/campaigns/show.blade.php`: 91, 101, 107, 229, 370, 374

**Lang**

- `lang/ar/js.php`: 918
- `lang/ar/docs/panels/admin.php`: 71
- `lang/ar/enums/notification_type.php`: 81, 82
- `lang/ar/partner.php`: 685
- `lang/en/js.php`: 933
- `lang/en/docs/panels/admin.php`: 71
- `lang/en/enums/notification_type.php`: 84, 85
- `lang/en/partner.php`: 683

## M. Promotion targets / fines / promotion:* commands (Q14 = remove)

Pattern: `promotion:|PromotionCategoryTarget|promotion_category_targets|MarketerPromotionFine|marketer_promotion_fines|MarketerPromotionReminder|marketer_promotion_reminders|monthly_promotion_target|promotion_category|PromotionCategory\b|ApplyPromotionPenalties|CheckPromotionMinimums|ClosePromotionMonth|ExpirePromotionItems|SendPromotionQuotaReminders`

276 hits in 41 files.

**App code**

- `app/Console/Commands/ExpirePromotionItems.php`: 9, 11, 17, 41
- `app/Console/Commands/SendPromotionQuotaReminders.php`: 10, 13, 25, 37, 39
- `app/Console/Commands/CheckPromotionMinimums.php`: 9, 11, 17, 41
- `app/Console/Commands/ClosePromotionMonth.php`: 9, 11
- `app/Console/Commands/ApplyPromotionPenalties.php`: 11, 13, 19, 61, 72
- `app/Http/Controllers/Marketer/DashboardController.php`: 71
- `app/Http/Controllers/Admin/MarketerController.php`: 6, 254, 255, 278, 279
- `app/Http/Controllers/Admin/MarketerSettingsController.php`: 5, 9, 32, 33, 106, 112, 113, 120
- `app/Http/Controllers/Admin/MarketerPromotionFineController.php`: 5, 7, 14, 19, 21, 24, 27, 31, 37
- `app/Http/Controllers/Admin/PromotionCategoryProductController.php`: 5, 22, 26, 33, 40, 50, 53, 55, 61, 62, 70, 73, 75, 77
- `app/Http/Controllers/Admin/AdminListingController.php`: 6, 225, 804
- `app/Services/Marketer/PromotionQuotaService.php`: 5, 8, 21, 23, 29, 44, 50, 51, 69, 76, 77, 80, 85, 92
- `app/Services/Marketer/PromotionPenaltyService.php`: 7, 33, 46, 47, 54, 69, 87, 88, 95, 97, 112, 116, 130, 133, 135, 139
- `app/Services/Checkout/CheckoutPricingEngine.php`: 1064
- `app/Enums/PromotionCategory.php`: 5, 14
- `app/Models/PromotionCategoryTarget.php`: 6, 10, 12, 15
- `app/Models/MarketerPromotionReminder.php`: 9, 13
- `app/Models/AdminListing.php`: 39, 80
- `app/Models/MarketerProfile.php`: 7, 61, 62, 87, 88
- `app/Models/MarketerPromotionFine.php`: 6, 12, 23, 28

**Routes / config**

- `routes/admin.php`: 71, 1170, 1171
- `routes/console.php`: 143, 149, 155, 190

**Views**

- `resources/js/travel_agency/campaigns.js`: 292, 293
- `resources/js/partner/campaign-offers.js`: 298
- `resources/js/admin/marketer-quotas.js`: 37, 52, 57, 90, 127
- `resources/js/admin/secret-promotion-detail.js`: 4
- `resources/views/admin/marketer_fines/index.blade.php`: 33, 53
- `resources/views/admin/marketers/show.blade.php`: 354, 355, 356, 357, 358, 365, 366, 367, 369
- `resources/views/admin/promotion_products/index.blade.php`: 11, 41, 46, 47
- `resources/views/admin/marketer_settings/index.blade.php`: 115, 116, 117, 145, 155
- `resources/views/admin/admin-listings/_form.blade.php`: 408, 409, 410, 411, 412, 415, 416
- `resources/views/marketer/dashboard.blade.php`: 71

**Lang**

- `lang/ar/admin.php`: 2457, 2458, 2459, 2460, 10384, 10385, 10386
- `lang/en/admin.php`: 2452, 2453, 2454, 2455, 10376, 10377, 10378, 11293

**Tests**

- `tests/Feature/Marketer/MarketerPromotionQuotaPanelTest.php`: 5, 7, 38, 39, 45, 51, 67, 78, 89, 97
- `tests/Feature/MarketerPromotionFlowTest.php`: 6, 15, 154, 155, 156, 162, 164, 182
- `tests/Feature/Admin/PromotionCategoryAdminTest.php`: 5, 7, 8, 44, 46, 50, 51, 57, 59, 68, 74, 75, 83, 84, 92, 94, 96, 98, 101, 102, 104, 106, 121, 123, 129, 131, 134, 135, 138, 150, 152, 162
- `tests/Feature/Campaigns/PeerPromotionServiceTest.php`: 5, 169, 172, 176, 177
- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 7, 26, 27, 609, 629, 630, 634, 635, 636, 637, 638
- `tests/Feature/Campaigns/PromotionQuotaP12Test.php`: 6, 7, 8, 39, 40, 41, 42, 53, 58, 68, 69, 74, 87, 88, 89, 96, 97, 98, 100, 107, 111, 117, 122, 123, 133, 139, 145, 148, 163, 164, 173, 183, 184, 192, 193
- `tests/Support/PromotionQuotaFixtures.php`: 6, 10, 41, 45, 49, 50

## N. marketer_sale_settlements (Q13 = KEEP; listed for awareness, do NOT remove)

Pattern: `marketer_sale_settlements|MarketerSaleSettlement`

49 hits in 10 files.

**App code**

- `app/Http/Controllers/Marketer/FinanceController.php`: 11, 13, 61, 63, 112
- `app/Http/Controllers/Admin/MarketerController.php`: 20, 24, 173, 175
- `app/Http/Controllers/Admin/WalletController.php`: 19, 225
- `app/Services/Marketer/MarketerSaleSettlementService.php`: 7, 30, 38, 57, 75, 84, 91, 118, 123, 201, 211
- `app/Services/LedgerService.php`: 119
- `app/Jobs/SettleMarketerSalesJob.php`: 5, 20
- `app/Jobs/ReleaseMarketerPendingCommissionJob.php`: 7, 45
- `app/Models/MarketerSaleSettlement.php`: 12, 14

**Tests**

- `tests/Feature/Campaigns/MarketerCampaignLifecycleTest.php`: 28, 39, 560, 563
- `tests/Feature/Campaigns/MarketerSaleSettlementTest.php`: 13, 19, 35, 99, 101, 126, 128, 141, 156, 158, 176, 177, 218, 228, 259, 275

---

## Verification (run at the end of R9)

```bash
cd backend
rg -n "MarketerCategoryCommission|OpenMarketCategoryCommission|MarketerCampaignTieredRule|MarketerCampaignCategoryRule|MarketerCampaignFeeAccrual|MarketerCampaignFeeSettlement|MarketerCommissionCountrySetting|MarketerInfluencerFeeCountrySetting|TravelAgencyCampaignOffer|TravelAgencyCampaignInvitation|PromotionCategoryTarget|MarketerPromotionFine|MarketerPromotionReminder|CommissionRuleResolver|AffiliateCommissionResolver|MarketerCommissionRateService|MarketerCommissionStatementService|MarketerFeeShareResolver|MarketerFeePayoutService|AffiliateFee(Accrual|Settlement)Service|MarketerInvitationRefundService" app resources routes lang config database/seeders database/factories tests
rg -n "promotion:|marketer_influencer_fee_payer|influencer_fee_share_pct|platform_cut_percentage|commission_discount_" app resources routes lang config database/seeders tests
```
Both must return nothing (apart from `database/migrations`, which is excluded on purpose).
