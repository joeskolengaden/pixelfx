/*
 * FPP "pixelfx" plugin
 *
 * A single ChannelData plugin combining three independently-toggleable modifier
 * functions, applied to the live channel buffer each frame, immediately before
 * output (FPPPlugins::ChannelDataPlugin::modifyChannelData):
 *
 *   1. Hue shift  - time-driven hue rotation of lit pixels (sine/triangle/
 *                   sawtooth/square) with optional per-pixel phase (rainbow wave)
 *   2. Color order- reorder the R/G/B bytes of each pixel (RGB..BGR)
 *   3. Framerate  - hold frames to a target FPS for a choppy / low-FPS look
 *
 * They run in that fixed order (framerate last, so it freezes the final result).
 * Each function has its own enable + channel range. A master "enabled" gates the
 * whole plugin. As a modifier layer it never alters test patterns, and
 * "onlyWhenPlaying" (default on) limits it to sequence playback.
 *
 * Settings live in <config>/plugin.pixelfx (key=value), hot-reloaded by FPP's
 * FileMonitor which invokes settingChanged().
 */

#include <algorithm>
#include <array>
#include <atomic>
#include <climits>
#include <cmath>
#include <cstdint>
#include <cstdlib>
#include <cstring>
#include <string>
#include <vector>

#include "Plugin.h"
#include "Sequence.h"
#include "channeltester/ChannelTester.h"

namespace {

void rgb2hsv(uint8_t R, uint8_t G, uint8_t B, double& h, double& s, double& v) {
    const double r = R / 255.0, g = G / 255.0, b = B / 255.0;
    const double mx = std::max({r, g, b});
    const double mn = std::min({r, g, b});
    const double d = mx - mn;
    v = mx;
    s = (mx <= 0.0) ? 0.0 : d / mx;
    if (d <= 0.0) {
        h = 0.0;
        return;
    }
    if (mx == r) {
        h = 60.0 * std::fmod((g - b) / d, 6.0);
    } else if (mx == g) {
        h = 60.0 * (((b - r) / d) + 2.0);
    } else {
        h = 60.0 * (((r - g) / d) + 4.0);
    }
    if (h < 0.0) {
        h += 360.0;
    }
}

void hsv2rgb(double h, double s, double v, uint8_t& R, uint8_t& G, uint8_t& B) {
    h = std::fmod(h, 360.0);
    if (h < 0.0) {
        h += 360.0;
    }
    const double c = v * s;
    const double x = c * (1.0 - std::fabs(std::fmod(h / 60.0, 2.0) - 1.0));
    const double m = v - c;
    double r = 0, g = 0, b = 0;
    if (h < 60.0) {
        r = c; g = x; b = 0;
    } else if (h < 120.0) {
        r = x; g = c; b = 0;
    } else if (h < 180.0) {
        r = 0; g = c; b = x;
    } else if (h < 240.0) {
        r = 0; g = x; b = c;
    } else if (h < 300.0) {
        r = x; g = 0; b = c;
    } else {
        r = c; g = 0; b = x;
    }
    R = static_cast<uint8_t>(std::lround(std::clamp((r + m) * 255.0, 0.0, 255.0)));
    G = static_cast<uint8_t>(std::lround(std::clamp((g + m) * 255.0, 0.0, 255.0)));
    B = static_cast<uint8_t>(std::lround(std::clamp((b + m) * 255.0, 0.0, 255.0)));
}

constexpr std::array<std::array<int, 3>, 6> ORDER = {{
    {{0, 1, 2}},  // RGB
    {{0, 2, 1}},  // RBG
    {{1, 0, 2}},  // GRB
    {{1, 2, 0}},  // GBR
    {{2, 0, 1}},  // BRG
    {{2, 1, 0}},  // BGR
}};

int parseColorOrder(const std::string& v) {
    static const char* names[6] = {"RGB", "RBG", "GRB", "GBR", "BRG", "BGR"};
    for (int i = 0; i < 6; ++i) {
        if (v == names[i]) {
            return i;
        }
    }
    return 0;
}

int parseWave(const std::string& v) {
    if (v == "sine") return 1;
    if (v == "triangle") return 2;
    if (v == "sawtooth") return 3;
    if (v == "square") return 4;
    return 0;
}

double wavePhase(int wave, double t, double period) {
    if (period < 1.0) {
        period = 1.0;
    }
    double p = std::fmod(t, period) / period;
    if (p < 0.0) {
        p += 1.0;
    }
    switch (wave) {
        case 1: return 0.5 * (1.0 - std::cos(2.0 * M_PI * p));
        case 2: return (p < 0.5) ? (2.0 * p) : (2.0 * (1.0 - p));
        case 3: return p;
        case 4: return (p < 0.5) ? 0.0 : 1.0;
        default: return 0.0;
    }
}

long toLong(const std::string& v, long def) {
    if (v.empty()) return def;
    char* end = nullptr;
    long r = std::strtol(v.c_str(), &end, 10);
    return (end == v.c_str()) ? def : r;
}
double toDouble(const std::string& v, double def) {
    if (v.empty()) return def;
    char* end = nullptr;
    double r = std::strtod(v.c_str(), &end);
    return (end == v.c_str()) ? def : r;
}

}  // namespace

class PixelFxPlugin : public FPPPlugin {
public:
    PixelFxPlugin() :
        FPPPlugin("pixelfx", /*monitorSettings=*/true) {
        for (const auto& kv : settings) {
            settingChanged(kv.first, kv.second);
        }
    }

    ~PixelFxPlugin() override = default;

    void settingChanged(const std::string& key, const std::string& value) override {
        // general
        if (key == "enabled") {
            mEnabled.store(toLong(value, 0) != 0);
        } else if (key == "onlyWhenPlaying") {
            mOnlyWhenPlaying.store(toLong(value, 1) != 0);

        // hue shift
        } else if (key == "hs_enabled") {
            mHsEnabled.store(toLong(value, 0) != 0);
        } else if (key == "hs_startChannel") {
            mHsStart.store(std::max<long>(1, toLong(value, 1)));
        } else if (key == "hs_channelCount") {
            mHsCount.store(std::max<long>(0, toLong(value, 0)));
        } else if (key == "hs_hueWave") {
            mHsWave.store(parseWave(value));
        } else if (key == "hs_huePeriodMs") {
            mHsPeriodMs.store(std::max(1.0, toDouble(value, 5000.0)));
        } else if (key == "hs_hueDepthDeg") {
            mHsDepth.store(toDouble(value, 360.0));
        } else if (key == "hs_huePhasePerChannel") {
            mHsPhase.store(toDouble(value, 0.0));

        // color order
        } else if (key == "co_enabled") {
            mCoEnabled.store(toLong(value, 0) != 0);
        } else if (key == "co_startChannel") {
            mCoStart.store(std::max<long>(1, toLong(value, 1)));
        } else if (key == "co_channelCount") {
            mCoCount.store(std::max<long>(0, toLong(value, 0)));
        } else if (key == "co_colorOrder") {
            mCoOrder.store(parseColorOrder(value));

        // framerate
        } else if (key == "fr_enabled") {
            mFrEnabled.store(toLong(value, 0) != 0);
        } else if (key == "fr_startChannel") {
            mFrStart.store(std::max<long>(1, toLong(value, 1)));
        } else if (key == "fr_channelCount") {
            mFrCount.store(std::max<long>(0, toLong(value, 0)));
        } else if (key == "fr_fps") {
            mFrFps.store(std::max(0.0, toDouble(value, 0.0)));
        }
    }

    void modifyChannelData(int ms, uint8_t* seqData) override {
        if (!mEnabled.load() || seqData == nullptr) {
            return;
        }
        if (!shouldModify()) {
            mFrBucket = LLONG_MIN;  // forget held frame so stale data isn't repeated
            return;
        }
        applyHueShift(ms, seqData);
        applyColorOrder(seqData);
        applyFramerate(ms, seqData);  // last: freezes the final result
    }

private:
    // Modifier layer: never touch test patterns, and (optionally) only while a
    // sequence is playing.
    bool shouldModify() const {
        if (ChannelTester::INSTANCE.Testing()) {
            return false;
        }
        if (mOnlyWhenPlaying.load() && (sequence == nullptr || !sequence->IsSequenceRunning())) {
            return false;
        }
        return true;
    }

    void applyHueShift(int ms, uint8_t* seqData) {
        if (!mHsEnabled.load()) {
            return;
        }
        const int wave = mHsWave.load();
        const double depth = mHsDepth.load();
        if (wave == 0 || depth == 0.0) {
            return;
        }
        const long start = std::max<long>(1, mHsStart.load());
        const long count = mHsCount.load();
        if (count < 3) {
            return;
        }
        const long startIdx = start - 1;
        const long pixels = count / 3;
        const double baseHue = depth * wavePhase(wave, ms, mHsPeriodMs.load());
        const double phasePer = mHsPhase.load();
        for (long i = 0; i < pixels; ++i) {
            const long idx = startIdx + i * 3;
            uint8_t r = seqData[idx], g = seqData[idx + 1], b = seqData[idx + 2];
            double h, s, v;
            rgb2hsv(r, g, b, h, s, v);
            h += baseHue + static_cast<double>(i) * phasePer;
            hsv2rgb(h, s, v, r, g, b);
            seqData[idx] = r;
            seqData[idx + 1] = g;
            seqData[idx + 2] = b;
        }
    }

    void applyColorOrder(uint8_t* seqData) {
        if (!mCoEnabled.load()) {
            return;
        }
        const int order = mCoOrder.load();
        if (order == 0) {
            return;
        }
        const long start = std::max<long>(1, mCoStart.load());
        const long count = mCoCount.load();
        if (count < 3) {
            return;
        }
        const long startIdx = start - 1;
        const long pixels = count / 3;
        const auto& ord = ORDER[order];
        for (long i = 0; i < pixels; ++i) {
            const long idx = startIdx + i * 3;
            const uint8_t src[3] = {seqData[idx], seqData[idx + 1], seqData[idx + 2]};
            seqData[idx] = src[ord[0]];
            seqData[idx + 1] = src[ord[1]];
            seqData[idx + 2] = src[ord[2]];
        }
    }

    void applyFramerate(int ms, uint8_t* seqData) {
        if (!mFrEnabled.load()) {
            return;
        }
        const double fps = mFrFps.load();
        if (fps <= 0.0) {
            return;
        }
        const long start = std::max<long>(1, mFrStart.load());
        const long count = mFrCount.load();
        if (count < 1) {
            return;
        }
        const long startIdx = start - 1;
        if (static_cast<long>(mHeld.size()) != count) {
            mHeld.assign(count, 0);
            mFrBucket = LLONG_MIN;
        }
        const double frameMs = 1000.0 / fps;
        const long long bucket = static_cast<long long>(std::floor(ms / frameMs));
        if (bucket == mFrBucket) {
            std::memcpy(seqData + startIdx, mHeld.data(), count);
        } else {
            std::memcpy(mHeld.data(), seqData + startIdx, count);
            mFrBucket = bucket;
        }
    }

    // general
    std::atomic<bool> mEnabled{false};
    std::atomic<bool> mOnlyWhenPlaying{true};

    // hue shift
    std::atomic<bool> mHsEnabled{false};
    std::atomic<long> mHsStart{1};
    std::atomic<long> mHsCount{1500};
    std::atomic<int> mHsWave{0};
    std::atomic<double> mHsPeriodMs{5000.0};
    std::atomic<double> mHsDepth{360.0};
    std::atomic<double> mHsPhase{0.0};

    // color order
    std::atomic<bool> mCoEnabled{false};
    std::atomic<long> mCoStart{1};
    std::atomic<long> mCoCount{1500};
    std::atomic<int> mCoOrder{0};

    // framerate
    std::atomic<bool> mFrEnabled{false};
    std::atomic<long> mFrStart{1};
    std::atomic<long> mFrCount{1500};
    std::atomic<double> mFrFps{20.0};
    std::vector<uint8_t> mHeld;     // output-thread only
    long long mFrBucket{LLONG_MIN}; // output-thread only
};

extern "C" {
FPPPlugin* createPlugin() {
    return new PixelFxPlugin();
}
}
