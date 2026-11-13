# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.3] - 2026-11-13

### Fixed

- On PrestaShop 9.2.1 the manufacturer and responsible operator fields of products and
  brands are created. PrestaShop 9.2.1 accepts only its own validation rules in extra
  fields and refused the module's rule, so only the safety information field was
  created. Shops installed with an earlier version get the missing fields when the
  module is updated.

## [0.1.2] - 2026-11-13

### Fixed

- The back office no longer sends you to the shop's home page after this module is
  installed from its release ZIP. The index.php file in every folder of the package
  redirected and stopped the process also when PrestaShop loaded it while scanning
  `src/ApiPlatform/Resources`, so the back office could not be built.

## [0.1.1] - 2026-11-10

### Added

- Polish translation of every text the module shows, in the back office and in the
  shop. This includes the fields on the product and brand pages, the role names and
  the message about an unknown company code.

## [0.1.0] - 2026-11-10

First public release.

### Added

- Company register for manufacturers, importers, authorised representatives,
  fulfilment service providers and distributors, with roles, postal and electronic
  address, and retiring instead of deleting.
- Manufacturer and responsible operator fields on brands and products, and a safety
  text per language on products, as PrestaShop extra properties in the standard
  product and brand forms.
- Responsible operator resolved from the product, then the brand, then the
  manufacturer itself when it is based in the EU.
- Product page section with the manufacturer, the responsible operator in the EU and
  the safety text: through the Quince Engine product page when the engine runs in the
  shop, through `displayProductAdditionalInfo` otherwise.
- "Products without GPSR data" screen listing products on sale that do not show a
  responsible operator with a full address and an electronic address.
- Admin API resources for reading the register and writing companies one at a time
  or in batches of up to 500, with rows entered by hand left untouched by uploads.
