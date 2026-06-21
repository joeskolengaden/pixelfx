# Makefile for the FPP "pixelfx" plugin.
#
#   make            build the plugin shared library + the frame-smoothing tool
#   make clean      remove build artifacts
#
# Override FPPDIR if FPP is not at /opt/fpp:  make FPPDIR=/path/to/fpp

PLUGIN  := pixelfx
FPPDIR  ?= /opt/fpp
SRCDIR  ?= $(FPPDIR)/src

UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Darwin)
  SHLIB_EXT   := .dylib
  SHLIB_FLAGS := -dynamiclib -undefined dynamic_lookup
  CXX         ?= clang++
else
  SHLIB_EXT   := .so
  SHLIB_FLAGS := -shared
  CXX         ?= g++
endif

TARGET := lib$(PLUGIN)$(SHLIB_EXT)
OBJS   := src/PixelFxPlugin.o
SMOOTH := pixelfx-smooth

# gnu++2a is recognized by GCC 8 (Debian 10 / FPP 5.4) through current GCC, so
# one flag covers every FPP from 5.4 to 9.x.
CXXFLAGS += -std=gnu++2a -fPIC -O2 -Wall -fvisibility=default -I$(SRCDIR)
CXXFLAGS += $(shell pkg-config --cflags jsoncpp 2>/dev/null)

.PHONY: all clean
all: $(TARGET) $(SMOOTH)

$(TARGET): $(OBJS)
	$(CXX) $(SHLIB_FLAGS) -o $@ $(OBJS)

src/%.o: src/%.cpp
	$(CXX) $(CXXFLAGS) -c -o $@ $<

# Frame-smoothing tool: reuses FPP's FSEQFile (built from the FPP source tree)
# for all .fseq read/write, links zstd + zlib. -DPLATFORM_UNKNOWN selects FPP's
# logging path (satisfied by the no-op stubs in src/fseqstub) and skips the
# Linux-only posix_fadvise, so it builds the same on the device and elsewhere.
$(SMOOTH): src/fseq_smooth.cpp
	$(CXX) -std=gnu++2a -O2 -DPLATFORM_UNKNOWN -Isrc/fseqstub -I$(SRCDIR) -o $@ \
	    src/fseq_smooth.cpp $(SRCDIR)/fseq/FSEQFile.cpp -lzstd -lz -lpthread

clean:
	rm -f $(OBJS) $(TARGET) $(SMOOTH)
