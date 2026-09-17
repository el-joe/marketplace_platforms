/**
 * A public `GET /cities?country_id=...` lookup endpoint now exists
 * (routes/api.php), but the map-based add-address wizard
 * (`add-address-modal/`) never asks the customer to pick a country/city —
 * it only captures a map pin + street details. Wiring a real city picker
 * here means adding a new city-select step to that wizard (not just
 * swapping an options array), which is out of scope for this pass. Until
 * that step is added, new addresses are created against this placeholder
 * so the rest of the create/update flow stays wired end-to-end.
 */
export const DEFAULT_CITY_ID = "";
