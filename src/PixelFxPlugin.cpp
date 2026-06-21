/*
 * FPP "pixelfx" plugin  -  compatible with FPP 5.4 through 9.x
 *
 * A single ChannelData plugin combining several independently-toggleable
 * modifier functions, applied to the live channel buffer each frame just
 * before output (FPPPlugin::modifyChannelData). Functions run in a fixed order:
 *
 *   mirror -> hue shift -> saturation -> color order -> brightness ->
 *   sparkle -> strobe -> framerate (held last)
 *
 * Each function has its own enable + channel range. A master "enabled" gates the
 * whole plugin. As a modifier layer it never alters test patterns, and
 * "onlyWhenPlaying" (default on) limits it to sequence playback.
 *
 * Cross-version: uses only API present in every FPP from 5.4 on (one-arg
 * FPPPlugin ctor, modifyChannelData, the protected `settings` map +
 * reloadSettings, sequence/ChannelTester state). Settings are re-read ~twice a
 * second so app/UI changes apply live without the 9.x settingChanged hook.
 * Hue is a luminance-preserving rotation matrix (cos/sin LUT) - far cheaper than
 * per-pixel HSV, so it scales to large ranges on a single-core BBB.
 */

#include <algorithm>
#include <array>
#include <chrono>
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

inline uint8_t clamp8(int v) { return v < 0 ? 0 : (v > 255 ? 255 : (uint8_t)v); }

// Integer luma approximation (Rec.601): (77R + 150G + 29B) >> 8.
inline int luma(uint8_t r, uint8_t g, uint8_t b) { return (77 * r + 150 * g + 29 * b) >> 8; }

constexpr std::array<std::array<int, 3>, 6> ORDER = {{
    {{0, 1, 2}}, {{0, 2, 1}}, {{1, 0, 2}}, {{1, 2, 0}}, {{2, 0, 1}}, {{2, 1, 0}},
}};

int parseColorOrder(const std::string& v) {
    static const char* n[6] = {"RGB", "RBG", "GRB", "GBR", "BRG", "BGR"};
    for (int i = 0; i < 6; ++i)
        if (v == n[i]) return i;
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
    if (period < 1.0) period = 1.0;
    double p = std::fmod(t, period) / period;
    if (p < 0.0) p += 1.0;
    switch (wave) {
        case 1: return 0.5 * (1.0 - std::cos(2.0 * M_PI * p));
        case 2: return (p < 0.5) ? (2.0 * p) : (2.0 * (1.0 - p));
        case 3: return p;
        case 4: return (p < 0.5) ? 0.0 : 1.0;
        default: return 0.0;
    }
}
long toLong(const std::string& v, long d) {
    if (v.empty()) return d;
    char* e = nullptr;
    long r = std::strtol(v.c_str(), &e, 10);
    return e == v.c_str() ? d : r;
}
double toDouble(const std::string& v, double d) {
    if (v.empty()) return d;
    char* e = nullptr;
    double r = std::strtod(v.c_str(), &e);
    return e == v.c_str() ? d : r;
}

// Degree cos/sin lookup, initialized once.
struct Trig {
    float c[360], s[360];
    Trig() {
        for (int i = 0; i < 360; ++i) {
            c[i] = (float)std::cos(i * M_PI / 180.0);
            s[i] = (float)std::sin(i * M_PI / 180.0);
        }
    }
};
const Trig TRIG;

// Build a luminance-preserving hue-rotation matrix for angle (degrees).
void hueMatrix(double angleDeg, float m[9]) {
    int idx = ((int)std::lround(angleDeg) % 360 + 360) % 360;
    float a = TRIG.c[idx], b = TRIG.s[idx];
    m[0] = 0.213f + 0.787f * a - 0.213f * b;
    m[1] = 0.715f - 0.715f * a - 0.715f * b;
    m[2] = 0.072f - 0.072f * a + 0.928f * b;
    m[3] = 0.213f - 0.213f * a + 0.143f * b;
    m[4] = 0.715f + 0.285f * a + 0.140f * b;
    m[5] = 0.072f - 0.072f * a - 0.283f * b;
    m[6] = 0.213f - 0.213f * a - 0.787f * b;
    m[7] = 0.715f - 0.715f * a + 0.715f * b;
    m[8] = 0.072f + 0.928f * a + 0.072f * b;
}
inline void applyMatrix(const float m[9], uint8_t& r, uint8_t& g, uint8_t& b) {
    float fr = r, fg = g, fb = b;
    int nr = (int)std::lround(fr * m[0] + fg * m[1] + fb * m[2]);
    int ng = (int)std::lround(fr * m[3] + fg * m[4] + fb * m[5]);
    int nb = (int)std::lround(fr * m[6] + fg * m[7] + fb * m[8]);
    r = clamp8(nr); g = clamp8(ng); b = clamp8(nb);
}

}  // namespace

class PixelFxPlugin : public FPPPlugin {
public:
    PixelFxPlugin() : FPPPlugin("pixelfx") {
        mLastReload = std::chrono::steady_clock::now();
        applySettings();
    }
    ~PixelFxPlugin() override = default;

    void modifyChannelData(int ms, uint8_t* seqData) override {
        maybeReload();
        if (!mEnabled || seqData == nullptr) return;
        if (!shouldModify()) { mFrBucket = LLONG_MIN; mLastMs = ms; return; }

        int dt = ms - mLastMs;
        if (dt < 0 || dt > 1000) dt = 20;
        mLastMs = ms;

        applyMirror(seqData);
        applyHueShift(ms, seqData);
        applySaturation(seqData);
        applyColorOrder(seqData);
        applyBrightness(seqData);
        applySparkle(dt, seqData);
        applyStrobe(ms, seqData);
        applyFramerate(ms, seqData);  // last: freezes the final result
    }

private:
    std::string cfg(const std::string& k) const {
        auto it = settings.find(k);
        return it == settings.end() ? std::string() : it->second;
    }
    void maybeReload() {
        auto now = std::chrono::steady_clock::now();
        if (now - mLastReload >= std::chrono::milliseconds(500)) {
            mLastReload = now;
            reloadSettings();
            applySettings();
        }
    }
    // start/count for a function -> (startIdx, byteCount). Returns false if empty.
    bool range(long start, long count, long& startIdx, long& bytes) const {
        if (count < 1) return false;
        startIdx = std::max<long>(1, start) - 1;
        bytes = count;
        return true;
    }

    void applySettings() {
        mEnabled = toLong(cfg("enabled"), 0) != 0;
        mOnlyWhenPlaying = toLong(cfg("onlyWhenPlaying"), 1) != 0;
        mChPerPix = std::max<long>(1, toLong(cfg("channelsPerPixel"), 3));

        mMrEnabled = toLong(cfg("mr_enabled"), 0) != 0;
        mMrStart = toLong(cfg("mr_startChannel"), 1);
        mMrCount = toLong(cfg("mr_channelCount"), 1500);
        mMrMirror = cfg("mr_mode") == "mirror";

        mHsEnabled = toLong(cfg("hs_enabled"), 0) != 0;
        mHsStart = toLong(cfg("hs_startChannel"), 1);
        mHsCount = toLong(cfg("hs_channelCount"), 1500);
        mHsWave = parseWave(cfg("hs_hueWave"));
        mHsPeriodMs = std::max(1.0, toDouble(cfg("hs_huePeriodMs"), 5000.0));
        mHsDepth = toDouble(cfg("hs_hueDepthDeg"), 360.0);
        mHsPhase = toDouble(cfg("hs_huePhasePerChannel"), 0.0);

        mSaEnabled = toLong(cfg("sa_enabled"), 0) != 0;
        mSaStart = toLong(cfg("sa_startChannel"), 1);
        mSaCount = toLong(cfg("sa_channelCount"), 1500);
        mSaLevel = std::max(0L, toLong(cfg("sa_level"), 100));

        mCoEnabled = toLong(cfg("co_enabled"), 0) != 0;
        mCoStart = toLong(cfg("co_startChannel"), 1);
        mCoCount = toLong(cfg("co_channelCount"), 1500);
        mCoOrder = parseColorOrder(cfg("co_colorOrder"));

        mBrEnabled = toLong(cfg("br_enabled"), 0) != 0;
        mBrStart = toLong(cfg("br_startChannel"), 1);
        mBrCount = toLong(cfg("br_channelCount"), 1500);
        mBrLevel = std::min(100L, std::max(0L, toLong(cfg("br_level"), 100)));

        mSpEnabled = toLong(cfg("sp_enabled"), 0) != 0;
        mSpStart = toLong(cfg("sp_startChannel"), 1);
        mSpCount = toLong(cfg("sp_channelCount"), 1500);
        mSpDensity = std::min(100L, std::max(0L, toLong(cfg("sp_density"), 10)));
        mSpDecayMs = std::max(1.0, toDouble(cfg("sp_decayMs"), 400.0));

        mStEnabled = toLong(cfg("st_enabled"), 0) != 0;
        mStStart = toLong(cfg("st_startChannel"), 1);
        mStCount = toLong(cfg("st_channelCount"), 1500);
        mStPeriodMs = std::max(1.0, toDouble(cfg("st_periodMs"), 200.0));
        mStDuty = std::min(100L, std::max(0L, toLong(cfg("st_duty"), 50)));

        mFrEnabled = toLong(cfg("fr_enabled"), 0) != 0;
        mFrStart = toLong(cfg("fr_startChannel"), 1);
        mFrCount = toLong(cfg("fr_channelCount"), 1500);
        mFrFps = std::max(0.0, toDouble(cfg("fr_fps"), 20.0));
    }

    bool shouldModify() const {
        if (ChannelTester::INSTANCE.Testing()) return false;
        if (mOnlyWhenPlaying && (sequence == nullptr || !sequence->IsSequenceRunning())) return false;
        return true;
    }

    void applyMirror(uint8_t* d) {
        if (!mMrEnabled) return;
        long si, by;
        if (!range(mMrStart, mMrCount, si, by)) return;
        long px = by / mChPerPix;
        long cpp = mChPerPix;
        if (mMrMirror) {  // reflect first half onto second
            for (long p = 0; p < px / 2; ++p)
                std::memcpy(d + si + (px - 1 - p) * cpp, d + si + p * cpp, cpp);
        } else {  // reverse whole range
            uint8_t tmp[8];
            for (long p = 0; p < px / 2; ++p) {
                uint8_t* a = d + si + p * cpp;
                uint8_t* b = d + si + (px - 1 - p) * cpp;
                std::memcpy(tmp, a, cpp); std::memcpy(a, b, cpp); std::memcpy(b, tmp, cpp);
            }
        }
    }

    void applyHueShift(int ms, uint8_t* d) {
        if (!mHsEnabled || mHsWave == 0 || mHsDepth == 0.0) return;
        long si, by;
        if (!range(mHsStart, mHsCount, si, by)) return;
        long px = by / mChPerPix, cpp = mChPerPix;
        double base = mHsDepth * wavePhase(mHsWave, ms, mHsPeriodMs);
        if (mHsPhase == 0.0) {  // fast path: one matrix for the whole range
            float m[9]; hueMatrix(base, m);
            for (long p = 0; p < px; ++p) {
                long i = si + p * cpp;
                applyMatrix(m, d[i], d[i + 1], d[i + 2]);
            }
        } else {  // per-pixel hue (traveling rainbow)
            for (long p = 0; p < px; ++p) {
                float m[9]; hueMatrix(base + p * mHsPhase, m);
                long i = si + p * cpp;
                applyMatrix(m, d[i], d[i + 1], d[i + 2]);
            }
        }
    }

    void applySaturation(uint8_t* d) {
        if (!mSaEnabled || mSaLevel == 100) return;
        long si, by;
        if (!range(mSaStart, mSaCount, si, by)) return;
        long px = by / mChPerPix, cpp = mChPerPix;
        long s = mSaLevel;  // percent
        for (long p = 0; p < px; ++p) {
            long i = si + p * cpp;
            int L = luma(d[i], d[i + 1], d[i + 2]);
            d[i] = clamp8(L + (d[i] - L) * s / 100);
            d[i + 1] = clamp8(L + (d[i + 1] - L) * s / 100);
            d[i + 2] = clamp8(L + (d[i + 2] - L) * s / 100);
        }
    }

    void applyColorOrder(uint8_t* d) {
        if (!mCoEnabled || mCoOrder == 0) return;
        long si, by;
        if (!range(mCoStart, mCoCount, si, by)) return;
        long px = by / mChPerPix, cpp = mChPerPix;
        const auto& o = ORDER[mCoOrder];
        for (long p = 0; p < px; ++p) {
            long i = si + p * cpp;
            uint8_t s[3] = {d[i], d[i + 1], d[i + 2]};
            d[i] = s[o[0]]; d[i + 1] = s[o[1]]; d[i + 2] = s[o[2]];
        }
    }

    void applyBrightness(uint8_t* d) {
        if (!mBrEnabled || mBrLevel == 100) return;
        long si, by;
        if (!range(mBrStart, mBrCount, si, by)) return;
        long lvl = mBrLevel;
        for (long i = si; i < si + by; ++i)
            d[i] = (uint8_t)((int)d[i] * lvl / 100);
    }

    void applySparkle(int dt, uint8_t* d) {
        if (!mSpEnabled) return;
        long si, by;
        if (!range(mSpStart, mSpCount, si, by)) return;
        long px = by / mChPerPix, cpp = mChPerPix;
        if ((long)mSparkle.size() != px) mSparkle.assign(px, 0);
        int decay = (int)std::lround(255.0 * dt / mSpDecayMs);
        if (decay < 1) decay = 1;
        // spawn probability per pixel per frame, scaled by density and frame time
        uint32_t thresh = (uint32_t)(mSpDensity * dt);  // density(0-100) * ms
        for (long p = 0; p < px; ++p) {
            int lv = mSparkle[p] - decay;
            if (lv < 0) lv = 0;
            if ((mRng = mRng * 1664525u + 1013904223u) % 20000u < thresh) lv = 255;
            mSparkle[p] = (uint8_t)lv;
            if (lv > 0) {
                long i = si + p * cpp;
                d[i] = std::max<uint8_t>(d[i], (uint8_t)lv);
                d[i + 1] = std::max<uint8_t>(d[i + 1], (uint8_t)lv);
                d[i + 2] = std::max<uint8_t>(d[i + 2], (uint8_t)lv);
            }
        }
    }

    void applyStrobe(int ms, uint8_t* d) {
        if (!mStEnabled) return;
        long si, by;
        if (!range(mStStart, mStCount, si, by)) return;
        double ph = std::fmod((double)ms, mStPeriodMs) / mStPeriodMs;
        if (ph * 100.0 >= (double)mStDuty)  // off phase -> blank
            std::memset(d + si, 0, by);
    }

    void applyFramerate(int ms, uint8_t* d) {
        if (!mFrEnabled || mFrFps <= 0.0) return;
        long si, by;
        if (!range(mFrStart, mFrCount, si, by)) return;
        if ((long)mHeld.size() != by) { mHeld.assign(by, 0); mFrBucket = LLONG_MIN; }
        double frameMs = 1000.0 / mFrFps;
        long long bucket = (long long)std::floor(ms / frameMs);
        if (bucket == mFrBucket) std::memcpy(d + si, mHeld.data(), by);
        else { std::memcpy(mHeld.data(), d + si, by); mFrBucket = bucket; }
    }

    std::chrono::steady_clock::time_point mLastReload;
    int mLastMs = 0;
    uint32_t mRng = 2463534242u;

    bool mEnabled = false, mOnlyWhenPlaying = true;
    long mChPerPix = 3;

    bool mMrEnabled = false, mMrMirror = false;
    long mMrStart = 1, mMrCount = 1500;

    bool mHsEnabled = false;
    long mHsStart = 1, mHsCount = 1500;
    int mHsWave = 0;
    double mHsPeriodMs = 5000.0, mHsDepth = 360.0, mHsPhase = 0.0;

    bool mSaEnabled = false;
    long mSaStart = 1, mSaCount = 1500, mSaLevel = 100;

    bool mCoEnabled = false;
    long mCoStart = 1, mCoCount = 1500;
    int mCoOrder = 0;

    bool mBrEnabled = false;
    long mBrStart = 1, mBrCount = 1500, mBrLevel = 100;

    bool mSpEnabled = false;
    long mSpStart = 1, mSpCount = 1500, mSpDensity = 10;
    double mSpDecayMs = 400.0;
    std::vector<uint8_t> mSparkle;

    bool mStEnabled = false;
    long mStStart = 1, mStCount = 1500, mStDuty = 50;
    double mStPeriodMs = 200.0;

    bool mFrEnabled = false;
    long mFrStart = 1, mFrCount = 1500;
    double mFrFps = 20.0;
    std::vector<uint8_t> mHeld;
    long long mFrBucket = LLONG_MIN;
};

extern "C" {
FPPPlugin* createPlugin() { return new PixelFxPlugin(); }
}
