# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html); until 1.0 a minor release may change
commands and output.

## [Unreleased]

### Changed

- CI as korin/gedcom: PHP 8.6 is a required version (Windows and macOS on 8.2 and 8.6), a
  non-blocking `php-nightly` workflow, `composer-require-checker` and `composer-unused`,
  Dependabot for Composer and Actions, and a `release` workflow that publishes a tag's GitHub
  Release with its CHANGELOG notes and an attested package archive.
- korin/gedcom `^1.0@RC` (was `^0.5`), with no change to the commands or their output. The next
  release waits for korin/gedcom 1.0.0 and requires `^1.0`: Composer reads a stability flag only
  in the root package, so a release on `@RC` would not install for others.

## [0.1.0] - 2026-11-18

The first release, on korin/gedcom 0.5.

### Added

- `info`, `validate`, `convert` and `diff` commands, on korin/gedcom 0.5: text and JSON output,
  `.gdz` input, files of any size.

[Unreleased]: https://github.com/serjio-dev/korin-cli/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/serjio-dev/korin-cli/releases/tag/v0.1.0
