# Thawani checkout activation — reply to Noof

**To:** Noof al Jabri, Executive Payment Gateway — Thawani
**Subject:** Nawy – Thawani checkout activation checklist

---

Dear Noof,

Thank you for the checklist. Please find our details below, with screenshots attached.

## 1. Website and SSL

- Website: https://nawyweb.com/omn-en (Arabic: https://nawyweb.com/omn-ar)
- The whole domain runs on HTTPS with a valid SSL certificate (Let's Encrypt). Screenshot 1 is attached.

## 2. Customer information in metadata

We integrate directly with the Thawani E-Commerce API; we don't use a plug-in. Every checkout session includes the customer's name, contact number and email in the metadata. A sample session request (amount in baisa):

```json
POST /api/v1/checkout/session
{
  "client_reference_id": "NAWY-20261114-ABC123",
  "mode": "payment",
  "products": [{ "name": "Order NAWY-20261114-ABC123", "quantity": 1, "unit_amount": 25500 }],
  "success_url": "https://api.nawyweb.com/api/customer/v1/omn/checkout/payment/success/NAWY-20261114-ABC123?signature=…",
  "cancel_url": "https://api.nawyweb.com/api/customer/v1/omn/checkout/payment/cancel/NAWY-20261114-ABC123?signature=…",
  "metadata": {
    "order_id": "…",
    "customer_id": "…",
    "customer_name": "Ahmed Al Balushi",
    "customer_email": "[email protected]",
    "customer_phone": "+96890000000"
  }
}
```

The customer is redirected to the Thawani checkout page using our publishable key. On their return, we confirm the payment by retrieving the session.

## 3. Card payments message

At checkout, Thawani appears under "Pay with" with the message "Debit & credit cards accepted (Visa, Mastercard)" (Arabic: "نقبل الدفع بالبطاقات البنكية والائتمانية (فيزا، ماستركارد)"). Screenshot 5 is attached.

## 4. Products

Products are fully listed and organised by category, with images, prices and product pages:

- Home: https://nawyweb.com/omn-en
- Example category: https://nawyweb.com/omn-en/beauty

Screenshots 2–4 are attached.

## 5. Delivery, refund and terms pages

These are linked in the footer of every page:

- Terms & Conditions: https://nawyweb.com/omn-en/pages/terms-and-conditions
- Refund Policy: https://nawyweb.com/omn-en/pages/refund-policy
- Return Policy: https://nawyweb.com/omn-en/pages/return-policy
- Shipping & Delivery Policy: https://nawyweb.com/omn-en/pages/shipping-policy
- Privacy Policy: https://nawyweb.com/omn-en/pages/privacy-policy
- Contact Us: https://nawyweb.com/omn-en/pages/contact-us

## Webhook

Please register this webhook URL for our account:
https://api.nawyweb.com/webhooks/payment/thawani

We verify the `thawani-signature` header (HMAC-SHA256 of the body + "-" + `thawani-timestamp`) with the webhook secret from the merchant portal.

## To complete the integration, could you please:

1. Confirm that our UAT keys (secret key, publishable key and webhook secret) are active, or share them if they haven't been issued yet.
2. Confirm that the webhook signature format above is correct.
3. Let us know whether Thawani supports partial refunds through the API, or only full refunds of a payment.
4. Advise on the next step for production activation after the UAT test.

Best regards,
Youssef
Nawy

---

## Before sending (internal — delete this section from the email)

1. Replace the placeholder text on Terms & Conditions, Refund Policy, Return Policy, Shipping Policy, Privacy Policy and Contact Us (English and Arabic) in Admin → Static pages.
2. Enter the Thawani sandbox Secret Key, Publishable Key and Webhook Secret in Admin → Payment gateways → Oman → Thawani, then press "Test connection". Thawani is hidden at checkout until the keys are saved.

### Screenshots to attach (English site, desktop)

1. Browser padlock on https://nawyweb.com/omn-en ("Connection is secure").
2. Home page https://nawyweb.com/omn-en with product rows visible.
3. Category page https://nawyweb.com/omn-en/beauty with product names, prices and images.
4. One product detail page (open any product from the home page).
5. Checkout "Pay with" box with Thawani selected, showing "Debit & credit cards accepted (Visa, Mastercard)".
6. Page footer showing the policy links.
7. Terms & Conditions, Refund Policy and Shipping Policy pages (after the real text is in).
8. Optional: Arabic checkout on https://nawyweb.com/omn-ar.
