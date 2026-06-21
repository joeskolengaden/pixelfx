<?php
// "Frame Generator" page for the pixelfx plugin (Method A: offline FSEQ
// frame-rate upsampling / interpolation). Self-contained styling scoped to
// #pfxgen. Backend work is done by gen.php / progress.php (called via AJAX).
?>
<style>
#pfxgen{max-width:760px;margin:0 auto;color:#1f2733;font-size:14px}
#pfxgen .intro{color:#6b7280;font-size:13px;margin:0 0 18px}
#pfxgen .card{border:1px solid #e4e7ec;border-radius:12px;background:#fff;padding:18px 20px;margin:0 0 16px}
#pfxgen .row{display:grid;grid-template-columns:150px 1fr;gap:14px;align-items:center;margin:0 0 14px}
#pfxgen .lab{font-weight:500;color:#374151}
#pfxgen select,#pfxgen input[type=text]{width:100%;max-width:340px;padding:8px 11px;border:1px solid #cdd3dc;border-radius:8px;font-size:14px;background:#fff;box-sizing:border-box}
#pfxgen select:focus,#pfxgen input:focus{outline:none;border-color:#2f9e6f;box-shadow:0 0 0 3px rgba(47,158,111,.15)}
#pfxgen .slrow{display:flex;align-items:center;gap:12px;max-width:340px}
#pfxgen input[type=range]{flex:1;accent-color:#2f9e6f}
#pfxgen .sval{min-width:64px;text-align:right;font-weight:600;font-variant-numeric:tabular-nums}
#pfxgen .info{background:#f6f8fa;border:1px solid #eceef2;border-radius:8px;padding:10px 14px;font-size:13px;color:#374151;margin:0 0 14px}
#pfxgen .info b{color:#1c6b4a}
#pfxgen .gen{padding:11px 20px;border:none;border-radius:9px;background:#2f9e6f;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
#pfxgen .gen:hover{background:#268a60}#pfxgen .gen:disabled{background:#9bd0bb;cursor:default}
#pfxgen .bar{height:10px;background:#e4e7ec;border-radius:6px;overflow:hidden;margin:14px 0 6px;display:none}
#pfxgen .bar > div{height:100%;width:0;background:#2f9e6f;transition:width .3s}
#pfxgen .status{font-size:13px;color:#6b7280}
#pfxgen .ok{color:#1c6b4a;font-weight:500}#pfxgen .err{color:#b42318;font-weight:500}
#pfxgen .note{font-size:12.5px;color:#6b7280;margin-top:6px}
</style>

<div id="pfxgen">
  <p class="intro">Generate a smoother, higher-frame-rate copy of a sequence. Frames are linearly interpolated between the originals (a crossfade), so slow color washes, fades and hue sweeps play silky-smooth. The new <code>.fseq</code> appears in your sequence list; play it as normal. To dial the effective rate live during playback, use the <b>Framerate</b> slider on the Pixel FX settings page.</p>

  <div class="card">
    <div class="row">
      <span class="lab">Source sequence</span>
      <select id="src" onchange="pfxgSrcChanged()"><option value="">Loading&hellip;</option></select>
    </div>
    <div class="info" id="srcinfo">Pick a sequence to see its current frame rate.</div>
    <div class="row">
      <span class="lab">Target frame rate</span>
      <div class="slrow"><input type="range" id="fps" min="10" max="60" value="40" step="1" oninput="pfxgFps()"><span class="sval" id="fpsv">40 fps</span></div>
    </div>
    <div class="row">
      <span class="lab">Output name</span>
      <input type="text" id="out" placeholder="name_40fps">
    </div>
    <button class="gen" id="genbtn" onclick="pfxgGenerate()">Generate smoothed sequence</button>
    <div class="bar" id="bar"><div id="barfill"></div></div>
    <div class="status" id="status"></div>
    <div class="note">Tip: FSEQ frame rate maxes around your hardware ceiling (~30 fps on 1020-pixel ports). Interpolation is a crossfade &mdash; great for fades/washes, softens hard motion rather than truly moving it.</div>
  </div>
</div>

<script>
var pfxgMeta = {};
function pfxgApi(page, qs) { return 'plugin.php?plugin=pixelfx&page=' + page + '&nopage=1' + (qs ? '&' + qs : ''); }

function pfxgLoad() {
    fetch('api/sequence').then(function (r) { return r.json(); }).then(function (list) {
        var s = document.getElementById('src');
        s.innerHTML = '<option value="">— choose a sequence —</option>';
        (list || []).forEach(function (n) {
            var o = document.createElement('option'); o.value = n; o.textContent = n; s.appendChild(o);
        });
    }).catch(function () { document.getElementById('src').innerHTML = '<option>(could not load sequences)</option>'; });
}
function pfxgSrcChanged() {
    var name = document.getElementById('src').value;
    var info = document.getElementById('srcinfo');
    if (!name) { info.textContent = 'Pick a sequence to see its current frame rate.'; return; }
    info.textContent = 'Reading…';
    fetch('api/sequence/' + encodeURIComponent(name) + '/meta').then(function (r) { return r.json(); }).then(function (m) {
        pfxgMeta = m;
        var fps = m.StepTime > 0 ? (1000 / m.StepTime) : 0;
        info.innerHTML = 'Source: <b>' + fps.toFixed(1) + ' fps</b> &middot; ' + m.NumFrames + ' frames &middot; ' + m.ChannelCount + ' channels';
        var slider = document.getElementById('fps');
        slider.min = Math.max(10, Math.ceil(fps) + 1);
        if (+slider.value <= fps) slider.value = Math.min(60, Math.round(fps * 2));
        pfxgFps();
        var base = name.replace(/\.fseq$/i, '');
        document.getElementById('out').value = base + '_' + slider.value + 'fps';
    }).catch(function () { info.textContent = 'Could not read sequence metadata.'; });
}
function pfxgFps() {
    var v = document.getElementById('fps').value;
    document.getElementById('fpsv').textContent = v + ' fps';
    var src = document.getElementById('src').value;
    if (src) document.getElementById('out').value = src.replace(/\.fseq$/i, '') + '_' + v + 'fps';
}
function pfxgGenerate() {
    var src = document.getElementById('src').value;
    var fps = document.getElementById('fps').value;
    var out = (document.getElementById('out').value || '').replace(/\.fseq$/i, '');
    var status = document.getElementById('status');
    if (!src) { status.innerHTML = '<span class="err">Pick a source sequence first.</span>'; return; }
    if (!out) { status.innerHTML = '<span class="err">Enter an output name.</span>'; return; }
    var btn = document.getElementById('genbtn'); btn.disabled = true;
    document.getElementById('bar').style.display = 'block';
    document.getElementById('barfill').style.width = '0%';
    status.textContent = 'Starting…';
    fetch(pfxgApi('gen.php', 'src=' + encodeURIComponent(src) + '&out=' + encodeURIComponent(out) + '&fps=' + fps))
        .then(function (r) { return r.json(); }).then(function (j) {
            if (j.status !== 'ok') { status.innerHTML = '<span class="err">' + (j.msg || 'Failed to start.') + '</span>'; btn.disabled = false; return; }
            pfxgPoll(j.job, btn, out, fps);
        }).catch(function () { status.innerHTML = '<span class="err">Backend error.</span>'; btn.disabled = false; });
}
function pfxgPoll(job, btn, out, fps) {
    var status = document.getElementById('status');
    var last = -2, stall = 0;
    var t = setInterval(function () {
        fetch(pfxgApi('progress.php', 'job=' + encodeURIComponent(job))).then(function (r) { return r.json(); }).then(function (p) {
            var v = parseInt(p.progress);
            if (v === -1) { clearInterval(t); status.innerHTML = '<span class="err">Generation failed. See /tmp/' + job + '.log on the device.</span>'; btn.disabled = false; return; }
            // stall detection: no progress change for ~30s -> assume the job died
            if (v === last) { if (++stall > 50) { clearInterval(t); status.innerHTML = '<span class="err">Job stalled (no progress for 30s). Check /tmp/' + job + '.log.</span>'; btn.disabled = false; return; } }
            else { stall = 0; last = v; }
            document.getElementById('barfill').style.width = Math.max(2, v) + '%';
            status.textContent = 'Generating… ' + v + '%';
            if (v >= 100) {
                clearInterval(t);
                document.getElementById('barfill').style.width = '100%';
                status.innerHTML = '<span class="ok">Done — created ' + out + '.fseq at ' + fps + ' fps.</span> Add it to a playlist to use it.';
                btn.disabled = false;
            }
        }).catch(function () { clearInterval(t); status.innerHTML = '<span class="err">Lost contact with the job.</span>'; btn.disabled = false; });
    }, 600);
}
document.addEventListener('DOMContentLoaded', pfxgLoad);
</script>
