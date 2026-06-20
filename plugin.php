<?php
// Settings page for the "pixelfx" plugin. $pluginSettings is populated by FPP's
// LoadPluginSettings() from config/plugin.pixelfx. Self-contained styling (FPP
// 5.x has no card/switch CSS), scoped under #pfx so it can't affect FPP.
global $pluginSettings;
if (!isset($pluginSettings) || !is_array($pluginSettings)) {
    $pluginSettings = array();
}
function px_get($k, $d = '')
{
    global $pluginSettings;
    return isset($pluginSettings[$k]) ? $pluginSettings[$k] : $d;
}
function px_chk($k, $d = '0')
{
    return px_get($k, $d) == '1' ? ' checked' : '';
}
function px_sel($k, $v, $d = '')
{
    return (px_get($k, $d) === $v) ? ' selected' : '';
}
function px_bool($k)
{
    return "SetPluginSetting('pixelfx','$k', this.checked ? 1 : 0, 0, 0); pfxToggle(this);";
}
function px_val($k)
{
    return "SetPluginSetting('pixelfx','$k', this.value, 0, 0);";
}
$colorOrders = array('RGB', 'RBG', 'GRB', 'GBR', 'BRG', 'BGR');
$waves = array('off', 'sine', 'triangle', 'sawtooth', 'square');
?>
<style>
#pfx{max-width:880px;margin:0 auto;color:#1f2733;font-size:14px}
#pfx .pfx-intro{color:#6b7280;font-size:13px;margin:0 0 18px}
#pfx .pfx-card{border:1px solid #e4e7ec;border-radius:12px;margin:0 0 16px;overflow:hidden;background:#fff}
#pfx .pfx-head{display:flex;align-items:center;gap:12px;padding:13px 18px;background:#f6f8fa;border-bottom:1px solid #eceef2}
#pfx .pfx-head .pfx-title{font-size:15px;font-weight:600;flex:1;margin:0}
#pfx .pfx-head .pfx-sub{color:#6b7280;font-size:12px;font-weight:400}
#pfx .pfx-body{padding:14px 18px}
#pfx .pfx-body.pfx-off{opacity:.45;pointer-events:none}
#pfx .pfx-grid{display:grid;grid-template-columns:150px minmax(120px,300px) 1fr;gap:11px 16px;align-items:center}
#pfx .pfx-lab{font-weight:500;color:#374151}
#pfx .pfx-help{color:#6b7280;font-size:12.5px}
#pfx input[type=number],#pfx select{width:100%;max-width:170px;padding:7px 10px;border:1px solid #cdd3dc;border-radius:7px;background:#fff;font-size:14px;color:#1f2733;box-sizing:border-box}
#pfx input[type=number]:focus,#pfx select:focus{outline:none;border-color:#2f9e6f;box-shadow:0 0 0 3px rgba(47,158,111,.15)}
#pfx .pfx-range{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#pfx .pfx-range input{max-width:92px}
#pfx .pfx-range span{color:#9aa1ac;font-size:12px}
#pfx .pfx-auto{padding:6px 11px;border:1px solid #2f9e6f;background:#eafaf2;color:#1c6b4a;border-radius:7px;font-size:12.5px;cursor:pointer;white-space:nowrap}
#pfx .pfx-auto:hover{background:#dcf5e9}
#pfx .pfx-auto:active{transform:scale(.97)}
#pfx .sw{position:relative;display:inline-block;width:46px;height:25px;vertical-align:middle;flex:none}
#pfx .sw input{opacity:0;width:0;height:0}
#pfx .sw .sl{position:absolute;cursor:pointer;inset:0;background:#cbd1da;border-radius:25px;transition:.18s}
#pfx .sw .sl:before{content:"";position:absolute;height:19px;width:19px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
#pfx .sw input:checked + .sl{background:#2f9e6f}
#pfx .sw input:checked + .sl:before{transform:translateX(21px)}
#pfx .pfx-genrow{display:flex;align-items:center;gap:12px;padding:6px 0}
#pfx .pfx-genrow .pfx-lab{min-width:150px}
@media(max-width:640px){#pfx .pfx-grid{grid-template-columns:1fr;gap:5px 0}#pfx .pfx-help{margin-bottom:8px}}
</style>

<div id="pfx">
  <p class="pfx-intro">Live modifier layer over the playing sequence. Three independent functions run in order: hue shift &rarr; color order &rarr; framerate. Changes apply within ~0.5&nbsp;s &mdash; no restart. Test patterns are never modified. The range defaults to all your configured output channels (<b>All outputs</b>).</p>

  <div class="pfx-card">
    <div class="pfx-head">
      <span class="pfx-title">General</span>
      <span class="pfx-sub">master switch</span>
      <label class="sw"><input type="checkbox" id="pfx-master"<?php echo px_chk('enabled'); ?> onChange="<?php echo px_bool('enabled'); ?>"><span class="sl"></span></label>
    </div>
    <div class="pfx-body">
      <div class="pfx-genrow">
        <span class="pfx-lab">Only while playing</span>
        <label class="sw"><input type="checkbox"<?php echo px_chk('onlyWhenPlaying', '1'); ?> onChange="SetPluginSetting('pixelfx','onlyWhenPlaying', this.checked ? 1 : 0, 0, 0);"><span class="sl"></span></label>
        <span class="pfx-help">Only modify during sequence playback (test patterns are always left alone).</span>
      </div>
    </div>
  </div>

  <div class="pfx-card">
    <div class="pfx-head">
      <span class="pfx-title">Hue shift wave <span class="pfx-sub">&mdash; animated hue rotation</span></span>
      <label class="sw"><input type="checkbox"<?php echo px_chk('hs_enabled'); ?> onChange="<?php echo px_bool('hs_enabled'); ?>"><span class="sl"></span></label>
    </div>
    <div class="pfx-body<?php echo px_get('hs_enabled') == '1' ? '' : ' pfx-off'; ?>">
      <div class="pfx-grid">
        <div class="pfx-lab">Range</div>
        <div class="pfx-range">
          <input type="number" id="pfx-hs-start" min="1" value="<?php echo htmlspecialchars(px_get('hs_startChannel', '1')); ?>" onChange="<?php echo px_val('hs_startChannel'); ?>">
          <span>to</span>
          <input type="number" id="pfx-hs-count" min="0" step="3" value="<?php echo htmlspecialchars(px_get('hs_channelCount', '1500')); ?>" onChange="<?php echo px_val('hs_channelCount'); ?>">
          <button type="button" class="pfx-auto" onclick="pfxAllOutputs('hs')">All outputs</button>
        </div>
        <div class="pfx-help">Start channel &amp; count (channels = LEDs &times; 3).</div>

        <div class="pfx-lab">Wave</div>
        <div><select onChange="<?php echo px_val('hs_hueWave'); ?>"><?php foreach ($waves as $w) echo "<option value='$w'" . px_sel('hs_hueWave', $w, 'off') . ">$w</option>"; ?></select></div>
        <div class="pfx-help">Waveform driving the rotation.</div>

        <div class="pfx-lab">Period</div>
        <div><input type="number" min="1" value="<?php echo htmlspecialchars(px_get('hs_huePeriodMs', '5000')); ?>" onChange="<?php echo px_val('hs_huePeriodMs'); ?>"></div>
        <div class="pfx-help">One full cycle, in ms.</div>

        <div class="pfx-lab">Depth</div>
        <div><input type="number" value="<?php echo htmlspecialchars(px_get('hs_hueDepthDeg', '360')); ?>" onChange="<?php echo px_val('hs_hueDepthDeg'); ?>"></div>
        <div class="pfx-help">Max shift in degrees. 360 = full spectrum.</div>

        <div class="pfx-lab">Phase / LED</div>
        <div><input type="number" step="0.1" value="<?php echo htmlspecialchars(px_get('hs_huePhasePerChannel', '0')); ?>" onChange="<?php echo px_val('hs_huePhasePerChannel'); ?>"></div>
        <div class="pfx-help">Per-pixel offset (deg) for a traveling rainbow. 0 = uniform.</div>
      </div>
    </div>
  </div>

  <div class="pfx-card">
    <div class="pfx-head">
      <span class="pfx-title">Color order <span class="pfx-sub">&mdash; reorder R/G/B bytes</span></span>
      <label class="sw"><input type="checkbox"<?php echo px_chk('co_enabled'); ?> onChange="<?php echo px_bool('co_enabled'); ?>"><span class="sl"></span></label>
    </div>
    <div class="pfx-body<?php echo px_get('co_enabled') == '1' ? '' : ' pfx-off'; ?>">
      <div class="pfx-grid">
        <div class="pfx-lab">Range</div>
        <div class="pfx-range">
          <input type="number" id="pfx-co-start" min="1" value="<?php echo htmlspecialchars(px_get('co_startChannel', '1')); ?>" onChange="<?php echo px_val('co_startChannel'); ?>">
          <span>to</span>
          <input type="number" id="pfx-co-count" min="0" step="3" value="<?php echo htmlspecialchars(px_get('co_channelCount', '1500')); ?>" onChange="<?php echo px_val('co_channelCount'); ?>">
          <button type="button" class="pfx-auto" onclick="pfxAllOutputs('co')">All outputs</button>
        </div>
        <div class="pfx-help">Start channel &amp; count.</div>

        <div class="pfx-lab">Order</div>
        <div><select onChange="<?php echo px_val('co_colorOrder'); ?>"><?php foreach ($colorOrders as $c) echo "<option value='$c'" . px_sel('co_colorOrder', $c, 'RGB') . ">$c</option>"; ?></select></div>
        <div class="pfx-help">Output byte order per pixel.</div>
      </div>
    </div>
  </div>

  <div class="pfx-card">
    <div class="pfx-head">
      <span class="pfx-title">Framerate <span class="pfx-sub">&mdash; hold frames to a target FPS</span></span>
      <label class="sw"><input type="checkbox"<?php echo px_chk('fr_enabled'); ?> onChange="<?php echo px_bool('fr_enabled'); ?>"><span class="sl"></span></label>
    </div>
    <div class="pfx-body<?php echo px_get('fr_enabled') == '1' ? '' : ' pfx-off'; ?>">
      <div class="pfx-grid">
        <div class="pfx-lab">Range</div>
        <div class="pfx-range">
          <input type="number" id="pfx-fr-start" min="1" value="<?php echo htmlspecialchars(px_get('fr_startChannel', '1')); ?>" onChange="<?php echo px_val('fr_startChannel'); ?>">
          <span>to</span>
          <input type="number" id="pfx-fr-count" min="0" value="<?php echo htmlspecialchars(px_get('fr_channelCount', '1500')); ?>" onChange="<?php echo px_val('fr_channelCount'); ?>">
          <button type="button" class="pfx-auto" onclick="pfxAllOutputs('fr')">All outputs</button>
        </div>
        <div class="pfx-help">Start channel &amp; count.</div>

        <div class="pfx-lab">Frames / sec</div>
        <div><input type="number" min="0" max="120" value="<?php echo htmlspecialchars(px_get('fr_fps', '20')); ?>" onChange="<?php echo px_val('fr_fps'); ?>"></div>
        <div class="pfx-help">Effective frame rate. 0 = disabled.</div>
      </div>
    </div>
  </div>
</div>

<script>
// Dim a function's body when its enable toggle is off.
function pfxToggle(cb) {
    if (cb.id === 'pfx-master') {
        document.querySelectorAll('#pfx .pfx-body').forEach(function (b) {
            b.style.opacity = cb.checked ? '' : '.45';
            b.style.pointerEvents = cb.checked ? '' : 'none';
        });
        return;
    }
    var head = cb.closest('.pfx-head');
    if (head && head.nextElementSibling) head.nextElementSibling.classList.toggle('pfx-off', !cb.checked);
}

// Compute the channel span of all configured outputs (co-*.json) by walking the
// output config for any node with startChannel + pixelCount/channelCount.
var pfxOutputsCache = null;
function pfxComputeOutputs() {
    if (pfxOutputsCache) return Promise.resolve(pfxOutputsCache);
    var minS = Infinity, maxE = 0;
    function walk(n) {
        if (Array.isArray(n)) { n.forEach(walk); return; }
        if (n && typeof n === 'object') {
            if (n.startChannel !== undefined) {
                var s = +n.startChannel, len = 0;
                if (n.pixelCount !== undefined) len = (+n.pixelCount) * 3;
                else if (n.channelCount !== undefined && +n.channelCount > 0) len = +n.channelCount;
                if (len > 0 && s > 0) { if (s < minS) minS = s; if (s + len - 1 > maxE) maxE = s + len - 1; }
            }
            for (var k in n) walk(n[k]);
        }
    }
    return fetch('api/configfile').then(function (r) { return r.json(); }).then(function (list) {
        var files = (list.ConfigFiles || []).filter(function (f) { return f.indexOf('co-') === 0; })
            .map(function (f) { return f.replace(/\.json$/, ''); });
        return files.reduce(function (p, f) {
            return p.then(function () {
                return fetch('api/channel/output/' + f).then(function (r) { return r.json(); })
                    .then(function (cfg) { walk(cfg); }).catch(function () {});
            });
        }, Promise.resolve());
    }).then(function () {
        pfxOutputsCache = (maxE > 0 && minS !== Infinity) ? { start: minS, count: maxE - minS + 1 } : { start: 1, count: 0 };
        return pfxOutputsCache;
    }).catch(function () { return { start: 1, count: 0 }; });
}

function pfxAllOutputs(p) {
    return pfxComputeOutputs().then(function (r) {
        if (!r.count) { alert('Could not detect output channels from the FPP output configuration.'); return; }
        document.getElementById('pfx-' + p + '-start').value = r.start;
        document.getElementById('pfx-' + p + '-count').value = r.count;
        SetPluginSetting('pixelfx', p + '_startChannel', r.start, 0, 0);
        SetPluginSetting('pixelfx', p + '_channelCount', r.count, 0, 0);
    });
}

// Auto-fill any range still at the default (1500) with the real output span.
document.addEventListener('DOMContentLoaded', function () {
    var needs = ['hs', 'co', 'fr'].filter(function (p) {
        var ci = document.getElementById('pfx-' + p + '-count');
        return ci && ci.value === '1500';
    });
    if (!needs.length) return;
    pfxComputeOutputs().then(function (r) {
        if (!r.count) return;
        needs.forEach(function (p) {
            document.getElementById('pfx-' + p + '-start').value = r.start;
            document.getElementById('pfx-' + p + '-count').value = r.count;
            SetPluginSetting('pixelfx', p + '_startChannel', r.start, 0, 0);
            SetPluginSetting('pixelfx', p + '_channelCount', r.count, 0, 0);
        });
    });
});
</script>
