# tc-lib-pdf-font

> Font import, metrics, and stack management utilities for PDF generation.

[![Latest Stable Version](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/version)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)
[![Build](https://github.com/tecnickcom/tc-lib-pdf-font/actions/workflows/check.yml/badge.svg)](https://github.com/tecnickcom/tc-lib-pdf-font/actions/workflows/check.yml)
[![Coverage](https://codecov.io/gh/tecnickcom/tc-lib-pdf-font/graph/badge.svg?token=wGN6UnOAFo)](https://codecov.io/gh/tecnickcom/tc-lib-pdf-font)
[![License](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/license)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)
[![Downloads](https://poser.pugx.org/tecnickcom/tc-lib-pdf-font/downloads)](https://packagist.org/packages/tecnickcom/tc-lib-pdf-font)

[![Sponsor on GitHub](https://img.shields.io/badge/sponsor-github-EA4AAA.svg?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/tecnickcom)

If this project is useful to you, please consider [supporting development via GitHub Sponsors](https://github.com/sponsors/tecnickcom).

---

## Overview

`tc-lib-pdf-font` provides font import and runtime font-stack services used by PDF composition engines.

It bridges static font assets and runtime document composition by handling metrics, encodings, and font program references in a PDF-friendly way. This modular design lets applications evolve font workflows independently from the rest of the rendering stack.

| | |
|---|---|
| **Namespace** | `\Com\Tecnick\Pdf\Font` |
| **Author** | Nicola Asuni <info@tecnick.com> |
| **License** | [GNU LGPL v3](https://www.gnu.org/copyleft/lesser.html) - see [LICENSE](LICENSE) |
| **API docs** | <https://tcpdf.org/docs/srcdoc/tc-lib-pdf-font> |
| **Packagist** | <https://packagist.org/packages/tecnickcom/tc-lib-pdf-font> |

---

## Features

### Font Processing
- Import support for core, Type1, and TrueType sources
- Font metadata extraction and normalization
- Utilities for subset and output dictionary generation

### Runtime Font Stack
- Font stack insertion and switching
- Glyph width/bounding-box helpers
- Character replacement and fallback handling

---

## Requirements

- PHP 8.2 or later
- Extensions: `json`, `pcre`, `zlib`
- Composer

---

## Installation

```bash
composer require tecnickcom/tc-lib-pdf-font
```

---

## Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$font = new \Com\Tecnick\Pdf\Font\Import('/path/to/font.ttf');
$metrics = $font->getFontMetrics();

var_dump($font->getFontName(), $metrics['type']);
```

For larger examples, refer to `test/OutputTest.php` and the conversion tooling in this repository.

---

## Converting Existing Fonts

Use the CLI utilities in `util/` to convert existing font files into the JSON/Z format consumed by this library.

### Convert One or More Fonts

Run `util/convert.php` and pass one or more input files with `--fonts`:

```bash
php util/convert.php \
	--outpath=./target/fonts/custom/ \
	--type=TrueTypeUnicode \
	--flags=32 \
	--encoding_id=1 \
	--fonts=/path/to/MyFont-Regular.ttf,/path/to/MyFont-Bold.ttf
```

The command writes generated font definition files to `--outpath`.

Common options:

- `--type`: Explicit font type (`TrueTypeUnicode`, `TrueType`, `Type1`, `CID0JP`, `CID0KR`, `CID0CS`, `CID0CT`). Leave empty for autodetect.
- `--encoding`: Encoding table (for example `cp1252` for many non-Unicode Type1/Core cases). Omit for Unicode and symbolic fonts.
- `--flags`: PDF descriptor flags. Default is `32` (non-symbolic).
- `--platform_id` and `--encoding_id`: CMAP selection for TrueType Unicode imports (defaults: `3` and `1`).
- `--linked`: Link to system font file instead of embedding/copying it (not transportable).

To see full usage help:

```bash
php util/convert.php --help
```

### Bulk Conversion

For batch generation from the mirrored font set:

```bash
cd util
make build
```

This installs `util` dependencies and runs `bulk_convert.php`, which scans the mirror package and writes converted fonts under `target/fonts/`.

Notes:

- `bulk_convert.php` also attempts OTF conversion via FontForge (`fontforge -script otf2ttf.ff ...`) before import.
- If you run bulk conversion directly, customize destination with `php util/bulk_convert.php --outpath=/your/path/`.

---

## Development

```bash
make deps
make help
make qa
```

Font generation helpers are also available through Make targets such as `fonts`.

---

## Packaging

```bash
make rpm
make deb
```

For system packages, bootstrap with:

```php
require_once '/usr/share/php/Com/Tecnick/Pdf/Font/autoload.php';
```

---

## Contributing

Contributions are welcome. Please review [CONTRIBUTING.md](CONTRIBUTING.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), and [SECURITY.md](SECURITY.md).

