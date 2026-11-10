# Quince GPSR

**Product safety and the responsible economic operator on the product page, for
PrestaShop 9.2.**

The EU General Product Safety Regulation (Regulation 2023/988, article 19) asks an
online offer to show, before the purchase, the manufacturer, the responsible economic
operator in the EU with a postal and an electronic address, and the warnings and
safety information in the buyer's language. PrestaShop has no place for most of this:
a brand holds one descriptive address, with no electronic address and no responsible
operator. Quince GPSR adds a company register, points brands and products to it, and
shows the result on the product page.

> Quince GPSR is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

> The module gives the shop places for this data and shows it. Whether a given
> product meets the regulation depends on the data the shop enters; the module does
> not check that the information is true or legally sufficient.

---

## Features

- **One company register.** Manufacturers, importers, authorised representatives,
  fulfilment service providers and distributors, each with a stable code, a name
  shown to customers, a registered name, an address, an e-mail or a contact form
  address, a phone and a tax number. A company used by products can be retired
  instead of deleted: it keeps its data where it is and leaves the choice lists.
- **Assignments on brands and products.** A brand and a product each get a
  manufacturer and a responsible operator field, chosen from the register. A product
  inherits from its brand and can override it. The fields live in the standard
  product and brand forms of the back office as PrestaShop extra properties.
- **Safety text per language.** Warnings and safety information on the product, in
  every shop language, also in the product form.
- **Responsible operator resolved for you.** Product first, then brand, then the
  manufacturer itself when it is based in the EU. A distributor can never be the
  responsible operator.
- **Product page section.** The manufacturer, the responsible operator in the EU and
  the safety text, shown only on products that have any of them.
- **What is missing.** A "Products without GPSR data" screen lists products on sale
  that do not show a responsible operator with a full address and an electronic
  address, filterable by brand and category. Products switched off are left out.
- **Data from any tool.** Companies can be loaded through the Admin API one at a time
  or in batches; assignments and safety texts go through the standard product and
  brand API. Rows entered by hand in the back office are never overwritten by an
  upload. See [API.md](API.md).
- **Multistore.** Installed into every shop of the installation, so the section shows
  in each of them.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | not required |

Quince GPSR works on its own, with any theme. When
[Quince Engine](https://github.com/quinceproject/quince_engine) runs in the shop, the
module appears in its back office menu and its product page places the section as
the **Product safety** element of the product page accordion. Without the engine the
section is shown through the standard `displayProductAdditionalInfo` hook, and the
module gets its own back office menu entry.

The module is in English only for now; the back office screens and the product page
section have no translations yet.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_gpsr.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_gpsr/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_gpsr` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince GPSR and click **Install**.

**From Quince Engine** *(coming soon)*

The module list in Quince Engine will install the module straight from its release,
without downloading anything by hand.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/gpsr` will work once the package is
published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_gpsr/releases).

Uninstalling deletes the company register, the safety texts and the assignments.
Export them first if another tool does not hold them.

## Configuration

Open **Product safety** in the back office menu (under **Quince project** when Quince
Engine is installed, under **Quince GPSR** otherwise), or click **Configure** next to
the module in the Module Manager.

1. **Company register.** Add the manufacturers and importers, with their roles and
   addresses.
2. **Brands.** In **Catalog → Brands**, set the manufacturer and the responsible
   operator for GPSR. Products of the brand inherit them.
3. **Products.** In the product form, override them where a product differs, and
   enter the safety information in each language.
4. **Products without GPSR data.** Check what is still missing.
5. **With Quince Engine:** switch on the **Product safety** element on the engine's
   product page screen.

## Architecture

- **`OrganizationRepository`** keeps the register in the module's own tables, with a
  record of every upload.
- **Core extra properties** hold the assignments and the safety text, so they sit in
  the product and brand forms and in the standard Admin API without an endpoint of
  their own.
- **`Resolver`** decides, per product and language, which manufacturer and
  responsible operator to show; **`Audit`** finds products that miss them.
- **API Platform resources** `Organization` and `OrganizationBatch` serve
  `/admin-api/quince-gpsr/organizations`, under the scopes
  `quince_gpsr_register_read` and `quince_gpsr_register_write`.
- **Symfony controller and Twig** for the back office screens, with its own copy of
  the Quince panel shell so it runs without Quince Engine. The panel loads the Manrope
  font from Google Fonts.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_gpsr.git
cd quince_gpsr
composer install
```

The folder must be called `quince_gpsr`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_gpsr/` folder **with its `vendor/`
directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
