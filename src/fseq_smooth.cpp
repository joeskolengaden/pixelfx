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
 * Original source frames are preserved exactly; N-1 interpolated frames are
 * inserted between each, where N = round(targetFps / sourceFps). The media
 * filename and other variable headers are carried over, so audio stays in sync.
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

    int N = (srcFps > 0) ? (int)std::lround(targetFps / srcFps) : 1;
    if (N < 1) N = 1;
    int newStep = (N > 0) ? (int)std::lround((double)inStep / N) : inStep;
    if (newStep < 1) newStep = 1;
    const uint32_t newFrames = (inFrames <= 1) ? inFrames : (inFrames - 1) * N + 1;

    std::vector<std::pair<uint32_t, uint32_t>> ranges;
    ranges.push_back(std::pair<uint32_t, uint32_t>(0, 999999999));
    src->prepareRead(ranges);

    FSEQFile* dst = FSEQFile::createFSEQFile(out, 2, FSEQFile::CompressionType::zstd, -99);
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

    uint32_t outIdx = 0;
    if (inFrames == 1) {
        readF(0, A);
        dst->addFrame(outIdx++, A.data());
    } else if (inFrames > 1) {
        readF(0, A);
        readF(1, B);
        for (uint32_t a = 0; a + 1 < inFrames; ++a) {
            for (int k = 0; k < N; ++k) {
                if (k == 0) {
                    dst->addFrame(outIdx++, A.data());
                } else {
                    for (size_t i = 0; i < bufsz; ++i) {
                        int va = A[i], vb = B[i];
                        O[i] = (uint8_t)(va + (vb - va) * k / N);
                    }
                    dst->addFrame(outIdx++, O.data());
                }
            }
            A.swap(B);
            uint32_t next = (a + 2 < inFrames) ? (a + 2) : (inFrames - 1);
            readF(next, B);
            if (prog && (a % 8 == 0)) writeProgress(prog, (int)(100.0 * a / (inFrames - 1)));
        }
        readF(inFrames - 1, A);
        dst->addFrame(outIdx++, A.data());  // exact final frame
    }

    dst->finalize();
    delete dst;
    delete src;
    writeProgress(prog, 100);
    printf("OK: %u frames @ %dms (%.2f fps)  ->  %u frames @ %dms (%.2f fps), N=%d\n",
           inFrames, inStep, srcFps, outIdx, newStep, newStep > 0 ? 1000.0 / newStep : 0.0, N);
    return 0;
}
