# pixelfx — FPP plugin

A single FPP **ChannelData** plugin that layers three independently-toggleable
modifier functions over the playing sequence, applied each frame just before
output:

1. **Hue shift** — time-driven hue rotation of lit pixels (sine/triangle/
   sawtooth/square) with optional per-pixel phase for a traveling rainbow wave.
2. **Color order** — reorder the R/G/B bytes of each pixel (RGB…BGR).
3. **Framerate** — hold frames to a target FPS for a choppy / low-FPS look.

They run in that fixed order (framerate last, so it freezes the final result).
Each function has its own enable and channel range, under one master switch.

## FPP compatibility (5.4 → 9.x)

One source compiles against any FPP from **5.4 onward** — it uses only plugin API
present in every version (the `FPPPlugin(name)` ctor, `modifyChannelData`, the
`settings` map + `reloadSettings()`, and the test/sequence state) and avoids the
9.x-only `settingChanged`/FileMonitor hooks. The plugin is compiled on each
device against that device's headers, so version-specific details resolve
automatically. Built with `-std=gnu++2a` (works on Debian 10 / GCC 8 through
current GCC).

Because 5.4 has no live settings callback, the plugin **re-reads its settings
file about twice a second**, so app/UI changes apply within ~0.5 s with no fppd
restart on every supported version.

## Modifier-layer behavior

- Acts on the live channel buffer — the `.fseq` on disk is never changed.
- **Test patterns are never modified.**
- **`onlyWhenPlaying` (default on):** only modifies while a sequence is playing.
  Turn off to also affect bridged E1.31/DDP input and idle output.

## Build

```bash
make                      # -> libpixelfx.so (or .dylib on macOS)
make FPPDIR=/path/to/fpp  # if FPP is not at /opt/fpp
```

`scripts/fpp_install.sh` runs the build on install. `callbacks.sh` tells FPP to
load the compiled library. **Restart fppd** to load it.

## Settings (`<config>/plugin.pixelfx`)

| Key | Type | Default | Meaning |
|---|---|---|---|
| `enabled` | 0/1 | `0` | Master on/off |
| `onlyWhenPlaying` | 0/1 | `1` | Only modify during sequence playback |
| `hs_enabled` | 0/1 | `0` | Hue-shift function on/off |
| `hs_startChannel` / `hs_channelCount` | int | `1` / `1500` | Hue-shift range |
| `hs_hueWave` | off/sine/triangle/sawtooth/square | `off` | Hue wave shape |
| `hs_huePeriodMs` | int (ms) | `5000` | One full cycle |
| `hs_hueDepthDeg` | int (deg) | `360` | Max hue shift |
| `hs_huePhasePerChannel` | float (deg) | `0` | Per-pixel offset (traveling wave) |
| `co_enabled` | 0/1 | `0` | Color-order function on/off |
| `co_startChannel` / `co_channelCount` | int | `1` / `1500` | Color-order range |
| `co_colorOrder` | RGB/RBG/GRB/GBR/BRG/BGR | `RGB` | Output byte order |
| `fr_enabled` | 0/1 | `0` | Framerate function on/off |
| `fr_startChannel` / `fr_channelCount` | int | `1` / `1500` | Framerate range |
| `fr_fps` | int | `20` | Effective frame rate (0 = off) |

Each function's range is in channels (1 RGB LED = 3 channels). Set ranges to
cover only your RGB pixel channels (not DMX/dumb channels).

## Install / control

- Install: see [`../INSTALL.md`](../INSTALL.md) (manual) or
  [`../DISTRIBUTE.md`](../DISTRIBUTE.md) (GitHub → FPP UI).
- App control over REST: see [`../APP_API.md`](../APP_API.md). All keys above are
  in `pluginInfo.json`'s `settingsSchema`, grouped by function, so an app can
  auto-render the three sections.
