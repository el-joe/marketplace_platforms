# QC Route Map (doc URL -> route)

| Doc URL | Status | Actual route(s) |
|---|---|---|
| /admin/ad-packages | MISSING | see F01 gap (package model not implemented) |
| /admin/ad-packages/{id} | MISSING | see F01 gap (package model not implemented) |
| /admin/ad-packages/{id}/toggle-active | MISSING | see F01 gap (package model not implemented) |
| /admin/marketer-campaigns/{campaign}/samples/{sample} | exists (panel host/prefix differs) | PATCH marketer-campaigns/{marketerCampaign}/samples/{sample} |
| /admin/marketers/{id} | exists (panel host/prefix differs) | GET api/customer/v1/{country}/marketers/{slug}; GET api/public/v1/{country}/marketers/{slug}; GET marketers/{marketer} |
| /admin/marketers/{id}/contract/acceptances | exists (panel host/prefix differs) | GET marketers/{marketer}/contract/acceptances |
| /admin/marketers/{id}/contract/upload | exists (panel host/prefix differs) | POST marketers/{marketer}/contract/upload |
| /admin/marketers/{id}/contract/versions/{v}/download | exists (panel host/prefix differs) | GET marketers/{marketer}/contract/versions/{version}/download |
| /admin/settings/currencies/OMR/rate | MISSING | - |
| /admin/settings/currencies/OMR/symbol-image | MISSING | - |
| /api/customer/v1/{region}/marketers/{id}/contract | exists | GET|HEAD api/customer/v1/{country}/marketers/{marketer}/contract |
| /api/customer/v1/{region}/marketers/{id}/contract/accept | exists | POST api/customer/v1/{country}/marketers/{marketer}/contract/accept |
| /api/customer/v1/{region}/special-requests | exists | GET|HEAD api/customer/v1/{country}/special-requests; POST api/customer/v1/{country}/special-requests |
| /api/customer/v1/{region}/travel | exists | GET|HEAD api/customer/v1/{country}/travel |
| /api/public/active-popup | check | |
| /api/public/v1/{region}/marketers | exists | GET|HEAD api/public/v1/{country}/marketers |
| /api/public/v1/{region}/marketers/{slug} | exists | GET|HEAD api/public/v1/{country}/marketers/{slug} |
| /marketer/profile/ad-price | MISSING | see F01 gap (package model not implemented) |
| /marketer/samples/{sample}/address | exists (panel host/prefix differs) | POST samples/{sample}/address |
| /marketer/special-requests | exists (panel host/prefix differs) | GET api/customer/v1/{country}/special-requests; GET api/marketer/special-requests; GET special-requests; POST api/customer/v1/{country}/special-reques |
| /marketer/special-requests/{id} | exists (panel host/prefix differs) | GET api/customer/v1/{country}/special-requests/{id}; GET api/marketer/special-requests/{id}; GET special-requests/{id} |
| /partner/ad-subscriptions | MISSING | see F01 gap (package model not implemented) |
| /partner/ad-subscriptions/{id}/cancel | MISSING | see F01 gap (package model not implemented) |
| /partner/ad-subscriptions/packages | MISSING | see F01 gap (package model not implemented) |
| /partner/ad-subscriptions/subscribe | MISSING | see F01 gap (package model not implemented) |
| /partner-api/products/{id}/custom-attributes | exists (panel host/prefix differs) | GET api/partner/v1/products/{product}/custom-attributes; GET listings/products/{product}/custom-attributes; POST api/partner/v1/products/{product}/cus |
| /partner-api/products/{id}/custom-attributes/{attr_id} | exists (panel host/prefix differs) | DELETE api/partner/v1/products/{product}/custom-attributes/{customAttribute}; DELETE listings/products/{product}/custom-attributes/{customAttribute};  |
| /partner-api/products/{id}/toggle-custom-attributes | exists (panel host/prefix differs) | POST api/partner/v1/products/{product}/toggle-custom-attributes; POST listings/products/{product}/toggle-custom-attributes |
