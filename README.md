# BanItem

Item ban plugin for Axolotl-PM API 5.

[![CI](https://github.com/NhanAZ-Plugins/BanItem/actions/workflows/build.yml/badge.svg)](https://github.com/NhanAZ-Plugins/BanItem/actions/workflows/build.yml)
[![Latest release](https://img.shields.io/github/v/release/NhanAZ-Plugins/BanItem)](https://github.com/NhanAZ-Plugins/BanItem/releases/latest)
[![Discord](https://img.shields.io/discord/986553214889517088?label=Discord&logo=discord&logoColor=white&color=5865F2)](https://discord.gg/j2X83ujT6c)


## Compatibility and verification

- Minimum Axolotl-PM API: `5.0.0`.
- CI runs PHPStan at maximum level and builds against pinned API 5.0.0 and 5.51.1 source revisions.
- CI smoke-tests the built PHAR on the official Axolotl-PM 5.51.1 server release. See the [workflow runs](https://github.com/NhanAZ-Plugins/BanItem/actions).
- The checked source revisions do not prove compatibility with every API 5.x release or substitute for a connected-player gameplay test.

## Commands

- `/banitem ban <item>` adds an item. Use a name such as `minecraft:diamond_sword`; legacy numeric identifiers are accepted when the server's compatibility parser can resolve them.
- `/banitem unban <item>` removes the matching item state.
- `/banitem list` shows the stored identifiers.

The plugin stores item names supplied by the operator and uses the parsed Axolotl-PM item state for event checks. Existing numeric identifiers and `ID:meta` entries are retained when loaded. Entries unsupported by the current server are kept in `items.bin` and reported in the server log instead of being silently discarded. Back up `plugin_data/BanItem/items.bin` before upgrading.

## Attribution and license

The historical manifest credits NhanAZ. This repository does not contain a license file; this update does not add or change a license.

## Build

The GitHub Actions workflow uses [DevTools](https://github.com/AxolotlBucketLabs/DevTools) to run PHPStan, build the plugin PHAR and validate the artifact.


## Credits

Authors: NhanAZ.
