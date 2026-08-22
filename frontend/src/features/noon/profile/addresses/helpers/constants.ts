/**
 * The customer-facing API has no endpoint to look up real city UUIDs (see
 * the Postman collection: `city_id` is documented as "seed/look up from
 * your database", with no public cities list). Until that exists, new
 * addresses are created against this placeholder so the rest of the
 * create/update flow can be wired end-to-end — swap this for a real
 * city-picker once a cities endpoint is available.
 */
export const DEFAULT_CITY_ID = "";
