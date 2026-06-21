#pragma once
// No-op logging stub so the frame-smoothing tool can compile FPP's FSEQFile.cpp
// without pulling in FPP's logging / log4cpp.
#define VB_ALL 0
#define VB_SEQUENCE 1
inline void LogErr(int, const char*, ...) {}
inline void LogInfo(int, const char*, ...) {}
inline void LogWarn(int, const char*, ...) {}
inline void LogDebug(int, const char*, ...) {}
