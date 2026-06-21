/*
 * pixelfx-smooth  -  FSEQ frame-rate upsampler / interpolator (Method A)
 *
 * Reads a low-fps .fseq and writes a higher-fps .fseq, inserting linearly
 * interpolated (crossfaded) frames between each pair of source frames. FPP then
 * plays the smooth file natively at the higher rate. Reuses FPP's own FSEQFile
 * class so all the v2 format / zstd-zlib / sparse / variable-header handling is
 * the battle-tested implementation.
 *
 *   pixelfx-smooth <in.fseq> <out.fseq> <targetFps> [progressFile]
 *
 * Time-based resample: the output is sampled at the new (integer-ms) step time
 * along the source's real timeline, so total duration is preserved (audio stays
 * in sync) for any target rate, not just integer multiples. The media filename
 * and other variable headers are carried over.
 */

#include <cmath>
#include <cstdint>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <string>
#include <utility>
#include <vector>

#include "fseq/FSEQFile.h"

static void writeProgress(const char* f, int pct) {
    if (!f) return;
    FILE* fp = fopen(f, "w");
    if (fp) { fprintf(fp, "%d", pct); fclose(fp); }
}

int main(int argc, char** argv) {
    if (argc < 4) {
        fprintf(stderr, "usage: %s <in.fseq> <out.fseq> <targetFps> [progressFile]\n", argv[0]);
        return 2;
    }
    std::string in = argv[1], out = argv[2];
    double targetFps = atof(argv[3]);
    const char* prog = (argc > 4) ? argv[4] : nullptr;
    if (targetFps <= 0) { fprintf(stderr, "bad targetFps\n"); return 2; }

    writeProgress(prog, 0);

    FSEQFile* src = FSEQFile::openFSEQFile(in);
    if (!src) { fprintf(stderr, "cannot open %s\n", in.c_str()); writeProgress(prog, -1); return 1; }

    const int inStep = src->getStepTime();
    const uint32_t inFrames = src->getNumFrames();
    const uint32_t ch = src->getChannelCount();
    const double srcFps = inStep > 0 ? 1000.0 / inStep : 0.0;

    // New step time (integer ms) from the target rate. FSEQ step time is one
    // byte, so clamp to [1,255]. Output frame count keeps total duration.
    int newStep = (int)std::lround(1000.0 / targetFps);
    if (newStep < 1) newStep = 1;
    if (newStep > 255) newStep = 255;
    const double srcDurMs = (double)inFrames * inStep;
    uint32_t newFrames = (uint32_t)std::llround(srcDurMs / newStep);
    if (newFrames < 1) newFrames = 1;

    std::vector<std::pair<uint32_t, uint32_t>> ranges;
    ranges.push_back(std::pair<uint32_t, uint32_t>(0, 999999999));
    src->prepareRead(ranges);

    // Use zlib, not zstd: FPP's V2 zstd writer drops the final frame for very
    // short files (<= ~10 frames) in some versions; zlib is correct at all
    // sizes. Decompression cost is negligible at these channel counts.
    FSEQFile* dst = FSEQFile::createFSEQFile(out, 2, FSEQFile::CompressionType::zlib, -99);
    if (!dst) { fprintf(stderr, "cannot create %s\n", out.c_str()); delete src; writeProgress(prog, -1); return 1; }
    dst->initializeFromFSEQ(*src);
    dst->setStepTime(newStep);
    dst->setNumFrames(newFrames);
    dst->writeHeader();

    const size_t bufsz = (ch > 0) ? ch : (size_t)(8024 * 1024);
    std::vector<uint8_t> A(bufsz), B(bufsz), O(bufsz);
    auto readF = [&](uint32_t idx, std::vector<uint8_t>& buf) {
        FSEQFile::FrameData* fd = src->getFrame(idx);
        if (fd) { fd->readFrame(buf.data(), (uint32_t)bufsz); delete fd; }
    };

    // Resample by time. For each output frame j, sample the source timeline at
    // t = j*newStep ms and linearly blend the two bracketing source frames.
    // a (the lower source frame index) is non-decreasing, so stream two buffers.
    uint32_t curA = 0;
    readF(0, A);
    if (inFrames > 1) readF(1, B); else B = A;

    for (uint32_t j = 0; j < newFrames; ++j) {
        double pos = (inStep > 0) ? ((double)j * newStep / inStep) : 0.0;
        uint32_t a = (uint32_t)pos;
        if (a > inFrames - 1) a = inFrames - 1;
        while (curA < a) {
            A.swap(B);
            ++curA;
            uint32_t nb = (curA + 1 < inFrames) ? (curA + 1) : (inFrames - 1);
            readF(nb, B);
        }
        double frac = pos - (double)a;
        if (a >= inFrames - 1) frac = 0.0;
        if (frac <= 0.0) {
            dst->addFrame(j, A.data());
        } else {
            for (size_t i = 0; i < bufsz; ++i) {
                int va = A[i], vb = B[i];
                int v = (int)std::lround(va + (vb - va) * frac);
                O[i] = (uint8_t)(v < 0 ? 0 : (v > 255 ? 255 : v));
            }
            dst->addFrame(j, O.data());
        }
        if (prog && (j % 32 == 0)) writeProgress(prog, (int)(100.0 * j / newFrames));
    }

    dst->finalize();
    delete dst;
    delete src;
    writeProgress(prog, 100);
    printf("OK: %u frames @ %dms (%.2f fps, %.0fms)  ->  %u frames @ %dms (%.2f fps, %.0fms)\n",
           inFrames, inStep, srcFps, srcDurMs,
           newFrames, newStep, 1000.0 / newStep, (double)newFrames * newStep);
    return 0;
}
