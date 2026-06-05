# eSewa ePay v2 Integration — Node.js + Express

A practical, self‑contained guide to accepting payments with **eSewa ePay v2** in a
Node.js / Express application. It mirrors the same flow used elsewhere in this
project (build a signed form → redirect to eSewa → verify on return), just written
for Express.

> ePay **v2** is the current HMAC‑signed API. Ignore older v1 examples that use an
> `merchant_id`/`scd` form with no signature — they are deprecated.

---

## 1. How it works

```
┌──────────┐    1. POST signed form     ┌─────────────┐
│ Your app │ ─────────────────────────▶ │   eSewa     │
│ (Express)│                            │  hosted UI  │
└──────────┘ ◀───────────────────────── └─────────────┘
      ▲        2. redirect to            user logs in
      │           success_url?data=…        & pays
      │
      │ 3. decode `data`, verify signature,
      │    then call the Status‑Check API (server‑to‑server)
      ▼
   mark order paid ✅
```

1. You render an HTML form pre‑filled with the order details **and an HMAC‑SHA256
   signature**, and auto‑submit it to eSewa.
2. The user pays on eSewa's hosted page. eSewa redirects the browser back to your
   `success_url` (or `failure_url`) with a base64‑encoded `data` query parameter.
3. Your server decodes `data`, re‑computes the signature to confirm the payload
   wasn't tampered with, and then calls the **Status‑Check API** to be 100% sure
   the transaction is `COMPLETE` before granting value.

Never trust the browser redirect alone — always do step 3 on the server.

---

## 2. Endpoints & test credentials

| | Test (RC / sandbox) | Live (production) |
|---|---|---|
| **Payment form** | `https://rc-epay.esewa.com.np/api/epay/main/v2/form` | `https://epay.esewa.com.np/api/epay/main/v2/form` |
| **Status check** | `https://rc.esewa.com.np/api/epay/transaction/status/` | `https://epay.esewa.com.np/api/epay/transaction/status/` |

**Sandbox credentials** (provided by eSewa for testing):

- `product_code` (merchant code): **`EPAYTEST`**
- Secret key (for signing): **`8gBm/:&EnhH.1/q`**
- Test login: eSewa ID `9806800001`–`9806800005`, password `Nepal@123`, MPIN `1122`
  (token `123456`).

Get your real `product_code` and secret key from the eSewa merchant dashboard for
production.

---

## 3. Project setup

```bash
npm init -y
npm install express axios dotenv
```

`.env`:

```ini
ESEWA_PRODUCT_CODE=EPAYTEST
ESEWA_SECRET_KEY=8gBm/:&EnhH.1/q
ESEWA_MODE=test                 # "test" or "live"
APP_URL=http://localhost:3000
```

`config/esewa.js`:

```js
import 'dotenv/config'

const isLive = process.env.ESEWA_MODE === 'live'

export const esewa = {
  productCode: process.env.ESEWA_PRODUCT_CODE,
  secretKey: process.env.ESEWA_SECRET_KEY,
  formUrl: isLive
    ? 'https://epay.esewa.com.np/api/epay/main/v2/form'
    : 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
  statusUrl: isLive
    ? 'https://epay.esewa.com.np/api/epay/transaction/status/'
    : 'https://rc.esewa.com.np/api/epay/transaction/status/',
}
```

---

## 4. The signature (the part everyone gets wrong)

eSewa signs an **ordered, comma‑joined `key=value` string**. For the *request* the
signed fields are exactly `total_amount,transaction_uuid,product_code`, in that
order, with **no spaces**:

```
total_amount=100,transaction_uuid=240601-abc123,product_code=EPAYTEST
```

The signature is `Base64( HMAC_SHA256(message, secretKey) )`.

`lib/signature.js`:

```js
import crypto from 'node:crypto'

/**
 * Build the eSewa signature for an ordered list of fields.
 * @param {Record<string,string|number>} fields  values to sign
 * @param {string[]} order  field names in the exact order eSewa expects
 * @param {string} secret   your eSewa secret key
 */
export function sign(fields, order, secret) {
  const message = order.map((key) => `${key}=${fields[key]}`).join(',')
  return crypto.createHmac('sha256', secret).update(message).digest('base64')
}
```

**Gotchas**

- `total_amount` in the signed message must be **byte‑for‑byte identical** to the
  `total_amount` form field. If you send `100` in one and `100.0` in the other, the
  signature check fails. Pick one representation and reuse it.
- `total_amount = amount + tax_amount + product_service_charge +
  product_delivery_charge`. If those extras are `0`, `total_amount === amount`.
- `transaction_uuid` must be unique per attempt and contain only alphanumerics and
  hyphens.

---

## 5. Initiating a payment

eSewa expects an HTML **form POST** (not a JSON API call) to open its hosted page.
The cleanest way in Express is to return a tiny auto‑submitting form.

`routes/payment.js`:

```js
import express from 'express'
import { randomUUID } from 'node:crypto'
import { esewa } from '../config/esewa.js'
import { sign } from '../lib/signature.js'

const router = express.Router()

router.post('/pay', (req, res) => {
  // In a real app: look up the order, take the amount from your DB — never trust
  // an amount sent by the browser.
  const amount = '100'
  const taxAmount = '0'
  const totalAmount = '100' // amount + tax + charges
  const transactionUuid = `${Date.now()}-${randomUUID().slice(0, 8)}`

  // TODO: persist { transactionUuid, totalAmount, status: 'PENDING' } now.

  const fields = {
    amount,
    tax_amount: taxAmount,
    total_amount: totalAmount,
    transaction_uuid: transactionUuid,
    product_code: esewa.productCode,
    product_service_charge: '0',
    product_delivery_charge: '0',
    success_url: `${process.env.APP_URL}/payment/success`,
    failure_url: `${process.env.APP_URL}/payment/failure`,
    signed_field_names: 'total_amount,transaction_uuid,product_code',
  }

  fields.signature = sign(
    fields,
    ['total_amount', 'transaction_uuid', 'product_code'],
    esewa.secretKey,
  )

  // Auto‑submitting form → redirects the browser to eSewa.
  const inputs = Object.entries(fields)
    .map(([name, value]) => `<input type="hidden" name="${name}" value="${value}">`)
    .join('')

  res.send(`<!doctype html><html><body onload="document.forms[0].submit()">
    <form action="${esewa.formUrl}" method="POST">${inputs}</form>
    <p>Redirecting to eSewa…</p>
  </body></html>`)
})

export default router
```

> Prefer SPA/JSON? Return the `formUrl` and `fields` as JSON and let the client
> build & submit the form. eSewa still needs a real form POST, so you cannot
> replace it with `fetch()`.

---

## 6. Handling the return & verifying

On success eSewa redirects to `success_url?data=<base64>`. Decode it, confirm the
**response** signature, then call the Status‑Check API.

```js
import axios from 'axios'
import crypto from 'node:crypto'
import { esewa } from '../config/esewa.js'

router.get('/payment/success', async (req, res) => {
  const raw = req.query.data
  if (!raw) return res.redirect('/payment/failure')

  // 1. Decode the base64 JSON eSewa sent back.
  const payload = JSON.parse(Buffer.from(raw, 'base64').toString('utf8'))
  // payload = { transaction_code, status, total_amount, transaction_uuid,
  //             product_code, signed_field_names, signature }

  // 2. Verify the response signature over the fields eSewa names.
  const order = payload.signed_field_names.split(',')
  const message = order.map((k) => `${k}=${payload[k]}`).join(',')
  const expected = crypto
    .createHmac('sha256', esewa.secretKey)
    .update(message)
    .digest('base64')

  if (expected !== payload.signature || payload.status !== 'COMPLETE') {
    return res.redirect('/payment/failure')
  }

  // 3. Source of truth: server‑to‑server status check.
  const { data: status } = await axios.get(esewa.statusUrl, {
    params: {
      product_code: esewa.productCode,
      total_amount: payload.total_amount,
      transaction_uuid: payload.transaction_uuid,
    },
  })

  if (status.status !== 'COMPLETE') {
    return res.redirect('/payment/failure')
  }

  // TODO: look up the order by transaction_uuid, confirm total_amount matches
  // what you stored, then mark it paid (idempotently — guard against double hits).
  // Save status.ref_id as the eSewa reference.

  return res.redirect('/payment/done')
})

router.get('/payment/failure', (req, res) => {
  // TODO: mark the pending order as failed.
  res.send('Payment failed or was cancelled.')
})
```

### Status‑Check API response

```json
{
  "product_code": "EPAYTEST",
  "transaction_uuid": "240601-abc123",
  "total_amount": 100.0,
  "status": "COMPLETE",
  "ref_id": "0001ABC"
}
```

Possible `status` values: `COMPLETE`, `PENDING`, `FULL_REFUND`, `PARTIAL_REFUND`,
`AMBIGUOUS`, `NOT_FOUND`, `CANCELED`. **Only treat `COMPLETE` as paid.**

---

## 7. Wire it up

```js
import express from 'express'
import paymentRoutes from './routes/payment.js'

const app = express()
app.use(express.urlencoded({ extended: true }))
app.use(paymentRoutes)
app.listen(3000, () => console.log('http://localhost:3000'))
```

Test it: `POST /pay` (e.g. a form button) → pay on eSewa sandbox with the test
login → you're redirected back to `/payment/success` and the status check confirms
`COMPLETE`.

---

## 8. Security checklist

- **Re‑verify server‑side.** The browser redirect can be replayed or faked — the
  Status‑Check API call is what you trust.
- **Validate the amount.** Compare `total_amount` from the status response against
  the amount you stored for that `transaction_uuid`. Never accept an amount from
  the client.
- **Idempotency.** A user may hit `success_url` more than once. Only grant value if
  the order is still `PENDING`; flip it to `PAID` in one atomic update.
- **Keep the secret key server‑side only.** Never expose it to the browser or commit
  it — load it from `.env`.
- **Use HTTPS** for `success_url`/`failure_url` in production; eSewa must be able to
  reach your `APP_URL`.
- **Unique `transaction_uuid` per attempt**, stored before redirecting.

---

## 9. Common errors

| Symptom | Cause |
|---|---|
| "Invalid signature" on the form | Signed message order/format wrong, or `total_amount` differs between the signed string and the form field. |
| Works in test, fails live | Still pointing at `rc-epay`/`rc` URLs, or using `EPAYTEST`/sandbox secret instead of your live credentials. |
| `data` param is empty on return | You read it on the wrong route, or eSewa hit `failure_url`. |
| Status check returns `NOT_FOUND` | `transaction_uuid`/`product_code`/`total_amount` don't match the original request exactly. |
| Double‑charged value granted | Missing idempotency guard on `success_url`. |

---

## References

- eSewa Developer docs: <https://developer.esewa.com.np/>
- ePay v2 signing: HMAC‑SHA256 over
  `total_amount,transaction_uuid,product_code`, Base64‑encoded.
