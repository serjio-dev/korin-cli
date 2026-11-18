# korin — GEDCOM on the command line

Show, validate, convert and compare GEDCOM files (5.5, 5.5.1, 5.5.5, 7.0; 4.0 and the 5.x drafts
are read). Built on [korin/gedcom](https://github.com/serjio-dev/korin-php): lossless, every
conversion loss reported, files of any size.

> **Status: 0.1 — early development.** Until 1.0 a minor release may change commands and output.

## Installation

```bash
composer global require korin/gedcom-cli
# or in a project
composer require --dev korin/gedcom-cli && vendor/bin/korin list
```

PHP 8.2–8.6. `ext-zip` reads `.gdz` archives; `ext-intl` and `ext-mbstring` are used when present.

## Commands

### info

```console
$ korin info family.ged
File         family.ged, 1.2 MB
Version      5.5
Encoding     Windows-1252 (CHAR IBMPC), CRLF
Program      Brother's Keeper 5.2
Records      4 812: INDI 3 120, FAM 1 040, SOUR 512, NOTE 140
Diagnostics  1 warning, 1 info (-v to list)
```

A version or encoding in parentheses is what the file declares, when it is read otherwise:
the file is mistaken, or its program is known to write something else
([dialects](https://serjio-dev.github.io/korin-php/dialects/)).

### validate

```console
$ korin validate family.ged --plausibility
KV1101 warning line 8 (HEAD/CHAR): Non-standard CHAR value; encoding detected from content
KV1010 info line 8 (HEAD/CHAR): Brother's Keeper before 6.1.12 writes CHAR IBMPC for Windows-1252; read as Windows-1252
KV3006 error line 8 (HEAD/CHAR): CHAR does not permit "IBMPC"; expected one of ANSEL, UNICODE, ASCII
KV5001 warning line 912 (@I77@/BIRT/DATE): Birth is after the death on line 914
family.ged (GEDCOM 5.5): 1 error, 2 warnings, 1 info
```

`--disable KV1101` (repeatable) leaves a code out, `--fail-on warning` exits with 1 on warnings
too. The codes are listed in [diagnostics.md](https://serjio-dev.github.io/korin-php/diagnostics/).

### convert

```console
$ korin convert family-7.ged family-551.ged --to 5.5.1
KL3002 warning line 19 (@I1@/SEX), extension: SEX X has no 5.5.1 value: SEX U
Converted to GEDCOM 5.5.1 in family-551.ged: 1 loss
```

`--policy` decides what happens to data the version cannot hold: `extension` (the default: kept
as an extension tag), `drop` or `fail`. `--losses losses.json` also writes the report as JSON. The
output is written to a temporary file and renamed when the conversion succeeds; `-` writes it to
the standard output, and the report to the standard error.

### diff

```console
$ korin diff family.ged family-edited.ged --ignore CHAN
--- family.ged  5.5.1, UTF-8
+++ family-edited.ged  5.5.1, Windows-1251
~ @I1@ INDI
    - @I1@/NAME Анна /Коваль/
    + @I1@/NAME Анна /Ковальова/
+ @I3@ INDI
1 changed, 1 added
```

Records are matched by xref and compared as trees: encodings, line endings, CONC/CONT and the
order of records do not count, so a file can be compared with its copy saved by another program,
or converted. `--ignore` (repeatable) leaves out a tag with its substructures, or records of a tag.

### Common options

| Option | |
| --- | --- |
| `--format json` | The result as JSON, for scripts |
| `--encoding NAME` | Read in this encoding, whatever the file declares |
| `--no-dialects` | Do not correct documented mistakes of the program that wrote the file |
| `-v` | More detail (`info`: the diagnostics of reading) |

A `.gdz` archive is read through its `gedcom.ged`.

Exit codes: `0` success, `1` what the command looks for was found (errors, differences, a
conversion stopped at a loss with `--policy fail`), `2` a file cannot be read or an option is wrong.

## License and trademarks

Apache-2.0. GEDCOM is a trademark of Intellectual Reserve, Inc. Korin is not affiliated with
or endorsed by FamilySearch.
