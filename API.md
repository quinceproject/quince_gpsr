# Quince GPSR API

This document is the contract for any tool that loads GPSR data into the shop: a PIM,
a CRM, an exporter of your own or a spreadsheet run through a script. **The module
assumes no particular tool on the other side.** Every shape below was checked by a
call on PrestaShop 9.2.

There is one split, and it follows from where the data lives in the shop:

| What you load | How |
|---|---|
| companies (manufacturers, importers, representatives) | the module's resources under `/quince-gpsr/organizations` |
| assignments and the safety text of a product or a brand | the standard Admin API of the product or brand, as extra properties |

---

## 1. Authentication

PrestaShop Admin API, OAuth2, `client_credentials`. The shop creates the client in
the back office (**Advanced Parameters → Admin API**) or from the console:

```
php bin/console prestashop:api-client create my-tool \
  --name="My tool" \
  --scopes=quince_gpsr_register_read,quince_gpsr_register_write,product_read,product_write
```

Token:

```http
POST /admin-api/access_token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=my-tool
&client_secret=…
&scope=quince_gpsr_register_read quince_gpsr_register_write product_read product_write
```

The scope has to be given **both when the client is created and when the token is
requested**. A token without the scope gets `403 Invalid scope` on its first request.

Scopes of the module:

| Scope | For |
|---|---|
| `quince_gpsr_register_read` | reading the company register |
| `quince_gpsr_register_write` | writing companies, one at a time and in batches |

**Multistore.** An installation with several shops needs the shop context on every
request: `?shopId=1` (or `shopGroupId`, or `allShops`). Without it the API answers
`400 Multi shop is enabled, you must specify a shop context`. The company register is
shared by the whole installation, but the parameter is still required.

---

## 2. Company register

### 2.1 Reading

```http
GET /admin-api/quince-gpsr/organizations?shopId=1&limit=100&page=1
GET /admin-api/quince-gpsr/organizations/{code}?shopId=1
```

`limit` is 100 by default, 500 at most. `retired=false` narrows the list to active
companies. Without that parameter retired ones are listed too, because a tool that
mirrors the shop's state has to see them: products may still point to them.

Response:

```json
{
  "code": "example-tools-gmbh",
  "roles": ["producer"],
  "tradeName": "Example Tools GmbH",
  "legalName": "Example Tools Central Europe GmbH",
  "street": "Musterstraße 1",
  "postalCode": "10115",
  "city": "Berlin",
  "country": "DE",
  "email": "safety@example.com",
  "contactUrl": "",
  "phone": "+49 30 0000000",
  "taxId": "",
  "retired": false,
  "source": "import"
}
```

### 2.2 Writing one company

```http
POST /admin-api/quince-gpsr/organizations?shopId=1
Content-Type: application/json
```

Companies are matched by `code`, so sending the same request twice gives the same
row. The tool does not have to remember what it has sent, nor ask the shop for its
state first.

### 2.3 Writing a batch

```http
POST /admin-api/quince-gpsr/organizations/batch?shopId=1

{
  "channel": "my-tool",
  "organizations": [ { …company… }, { …company… } ]
}
```

Up to 500 entries per request. The response carries counts, not rows:

```json
{ "importId": 1, "channel": "my-tool", "saved": 1, "skipped": 1, "rejected": 1 }
```

| Field | Meaning |
|---|---|
| `saved` | saved or updated |
| `skipped` | a row entered by hand in the shop; loading does not touch it and **this is not an error** |
| `rejected` | missing `code` or `tradeName`; the rest of the batch is saved anyway |
| `importId` | number of the import record shown in the back office |

One typo in an export must not block the other few hundred companies, so a rejected
row is counted and recorded in the import record instead of stopping the batch.

### 2.4 Company fields

| Field | Required | Notes |
|---|---|---|
| `code` | yes | stable for the whole life of the entry; upserts go by it. A tax number is fine as information, not as a key |
| `tradeName` | yes | this is what the customer sees |
| `roles` | recommended | `producer`, `importer`, `authorized_representative`, `fulfillment_service_provider`, `distributor`; unknown roles are dropped silently |
| `email` or `contactUrl` | yes, by art. 19 | a company without an electronic address is marked incomplete in the back office |
| `street`, `postalCode`, `city`, `country` | yes, by art. 19 | `country` as ISO 3166-1 alpha-2; it decides whether the company is in the EU |
| `legalName`, `phone`, `taxId` | no | |
| `retired` | no | `true` retires the entry: it stays in the register and leaves the choice lists |
| `source` | | read only, kept by the shop |

---

## 3. Assignments and safety text

These go through the **standard product API**, because in the shop they are core extra
properties. The module has no endpoint of its own for them.

```http
PATCH /admin-api/products/{id}?shopId=1
Content-Type: application/merge-patch+json

{
  "extraProperties": {
    "quince_gpsr": {
      "manufacturer": "example-tools-gmbh",
      "responsible": "example-import",
      "safety": {
        "pl-PL": "<p>Elektronarzędzie akumulatorowe…</p><ul><li>…</li></ul>",
        "en-US": "<p>Cordless power tool…</p>"
      }
    }
  }
}
```

The shape follows a real `GET /admin-api/products/1?shopId=1` response. Scopes:
`product_read`, `product_write`.

**The language key is a locale**: `pl-PL`, `en-US`, `de-DE`. A two-letter code is not
recognised.

A brand has the same two assignment fields, in its own resource and under its own
scopes:

```http
GET   /admin-api/manufacturers/{id}?shopId=1        scope manufacturer_read
PATCH /admin-api/manufacturers/{id}?shopId=1        scope manufacturer_write

{ "extraProperties": { "quince_gpsr": { "manufacturer": "example-tools-gmbh", "responsible": "" } } }
```

A brand has no safety text: `ps_manufacturer_lang` has no shop column, and the core
saving of a translatable extra property needs one. For GPSR this does not matter,
because warnings belong to the product, not to the brand.

What a change means:

| You send | The shop |
|---|---|
| a company code | assigns it |
| `null` | removes the assignment |
| no key | changes nothing |
| an empty string in `safety` for a language | clears the text in that language on purpose |

### 3.1 Code checks

A write that points to a company outside the register is refused:

```json
[{"propertyPath":"extraProperties.quince_gpsr.manufacturer",
  "message":"Company \"no-such-company\" is not in the GPSR register."}]
```

So is a write that puts in the responsible operator field a company with no role that
allows it (for example a distributor only):

```json
[{"propertyPath":"extraProperties.quince_gpsr.responsible",
  "message":"Company \"example-wholesale\" cannot be the responsible operator: none of its roles allows it."}]
```

Both come back as `422`. An empty field passes: empty means "take it from the brand".

**Load order therefore matters**: companies first, assignments second. The other way
round every assignment bounces off the check.

---

## 4. How the shop decides whom to show

```
manufacturer = code on the product → code on the brand → none
named        = code on the product → code on the brand → none
responsible  = named → the manufacturer, when it is in the EU → none
```

The last step follows art. 16(1) of the GPSR. A tool can therefore send assignments
per brand and let the shop decide, or send the resolved operator on every product. The
shop takes either, and when showing them it prefers the value on the product.

---

## 5. What the module does not take, and why

- **Batch and serial numbers.** Art. 19 does not require them. The duty in art. 9(5)
  falls on the manufacturer and concerns the physical product; the shop does not know
  which unit the customer will receive.
- **A distributor in the responsible operator field.** Selling alone does not make
  anyone that (art. 16(1)).
- **Roles outside the list in section 2.4.** A silent typo must not turn a wholesaler
  into the responsible operator.
- **Overwriting a row entered by hand in the back office.** The first import would
  otherwise wipe the shop manager's work, with no way to get it back.
