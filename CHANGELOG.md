# Changelog
Starting from v3.0.0, all notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Breaking Changes
- Namespace change: ⚠This requires configuration changes to be applied to your config.php.⚠\
  Specifically the `class` attribute needs to be changed to the full class path starting with `\OCA\UserExternal\` and ending with the name of the specific authentication backend you use (e.g. IMAP or FTP). Check the [README.md](https://github.com/nextcloud/user_external#readme) for the concrete value you have to set.

### Added
- Support for Nextcloud 23 and 24
  [#191](https://github.com/nextcloud/user_external/pull/191) @MarBie77
- New CI config (migrate to Github Workflows)
  [#192](https://github.com/nextcloud/user_external/pull/192) @skjnldsv

## Older releases
For versions before 3.0.0 please check the [github releases](https://github.com/nextcloud/user_external/releases) for release notes.
