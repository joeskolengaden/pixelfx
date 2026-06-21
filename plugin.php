<?php
// Settings page for the "pixelfx" plugin. $pluginSettings is populated by FPP's
// LoadPluginSettings() from config/plugin.pixelfx. Styling is self-contained and
// scoped under #pfx so it can't affect FPP.
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
function px_set_js($k, $bool = false)
{
    $v = $bool ? 'this.checked ? 1 : 0' : 'this.value';
    $extra = $bool ? ' pfxToggle(this);' : '';
    return "SetPluginSetting('pixelfx','$k', $v, 0, 0);$extra";
}
function ctlNum($k, $d, $min = '', $max = '', $step = '')
{
    $a = ($min !== '' ? " min=\"$min\"" : '') . ($max !== '' ? " max=\"$max\"" : '') . ($step !== '' ? " step=\"$step\"" : '');
    return "<input type=\"number\" data-key=\"$k\"$a value=\"" . htmlspecialchars(px_get($k, $d)) . "\" onChange=\"" . px_set_js($k) . "\">";
}
function ctlSel($k, $opts, $d)
{
    $h = "<select data-key=\"$k\" onChange=\"" . px_set_js($k) . "\">";
    foreach ($opts as $o) {
        $sel = (px_get($k, $d) === $o) ? ' selected' : '';
        $h .= "<option value=\"$o\"$sel>$o</option>";
    }
    return $h . "</select>";
}
function ctlToggle($k, $d = '0')
{
    return "<label class=\"sw\"><input type=\"checkbox\" data-key=\"$k\"" . px_chk($k, $d) . " onChange=\"" . px_set_js($k, true) . "\"><span class=\"sl\"></span></label>";
}
function ctlRange($p)
{
    $sk = $p . '_startChannel';
    $ck = $p . '_channelCount';
    return '<div class="pfx-range">'
        . "<input type=\"number\" id=\"pfx-$p-start\" data-key=\"$sk\" min=\"1\" value=\"" . htmlspecialchars(px_get($sk, '1')) . "\" onChange=\"" . px_set_js($sk) . "\">"
        . '<span>to</span>'
        . "<input type=\"number\" id=\"pfx-$p-count\" data-key=\"$ck\" min=\"0\" value=\"" . htmlspecialchars(px_get($ck, '1500')) . "\" onChange=\"" . px_set_js($ck) . "\">"
        . "<select class=\"pfx-target\" data-prefix=\"$p\" onChange=\"pfxTarget(this)\"><option value=\"\">Target&hellip;</option><option value=\"__all__\">All outputs</option></select></div>";
}
function head($title, $sub, $enKey)
{
    return '<div class="pfx-head"><span class="pfx-title">' . $title . ' <span class="pfx-sub">' . $sub . '</span></span>' . ctlToggle($enKey) . '</div>';
}
function bodyOpen($enKey)
{
    return '<div class="pfx-body' . (px_get($enKey) == '1' ? '' : ' pfx-off') . '"><div class="pfx-grid">';
}
function row($lab, $ctl, $help = '')
{
    return '<div class="pfx-lab">' . $lab . '</div><div>' . $ctl . '</div><div class="pfx-help">' . $help . '</div>';
}
$presetsRaw = px_get('presets', '{}');
?>
<style>
#pfx{max-width:900px;margin:0 auto;color:#1f2733;font-size:14px}
#pfx .pfx-intro{color:#6b7280;font-size:13px;margin:0 0 14px}
#pfx .pfx-presets{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f6f8fa;border:1px solid #e4e7ec;border-radius:10px;padding:10px 14px;margin:0 0 16px}
#pfx .pfx-presets b{font-size:13px;color:#374151;margin-right:4px}
#pfx .pfx-presets select{min-width:160px}
#pfx .pfx-card{border:1px solid #e4e7ec;border-radius:12px;margin:0 0 14px;overflow:hidden;background:#fff}
#pfx .pfx-head{display:flex;align-items:center;gap:12px;padding:12px 18px;background:#f6f8fa;border-bottom:1px solid #eceef2}
#pfx .pfx-head .pfx-title{font-size:15px;font-weight:600;flex:1}
#pfx .pfx-head .pfx-sub{color:#6b7280;font-size:12px;font-weight:400}
#pfx .pfx-body{padding:13px 18px}
#pfx .pfx-body.pfx-off{opacity:.45;pointer-events:none}
#pfx .pfx-grid{display:grid;grid-template-columns:140px minmax(120px,300px) 1fr;gap:10px 16px;align-items:center}
#pfx .pfx-lab{font-weight:500;color:#374151}
#pfx .pfx-help{color:#6b7280;font-size:12.5px}
#pfx input[type=number],#pfx select,#pfx .pfx-presets select{padding:7px 10px;border:1px solid #cdd3dc;border-radius:7px;background:#fff;font-size:14px;color:#1f2733;box-sizing:border-box;max-width:170px}
#pfx input[type=number]{width:100%}
#pfx input:focus,#pfx select:focus{outline:none;border-color:#2f9e6f;box-shadow:0 0 0 3px rgba(47,158,111,.15)}
#pfx .pfx-range{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#pfx .pfx-range input{max-width:92px}
#pfx .pfx-range span{color:#9aa1ac;font-size:12px}
#pfx .pfx-btn,#pfx .pfx-auto{padding:6px 11px;border:1px solid #2f9e6f;background:#eafaf2;color:#1c6b4a;border-radius:7px;font-size:12.5px;cursor:pointer;white-space:nowrap}
#pfx .pfx-btn:hover,#pfx .pfx-auto:hover{background:#dcf5e9}
#pfx .pfx-btn.alt{border-color:#cdd3dc;background:#fff;color:#374151}
#pfx .pfx-btn.alt:hover{background:#f1f3f6}
#pfx .sw{position:relative;display:inline-block;width:46px;height:25px;vertical-align:middle;flex:none}
#pfx .sw input{opacity:0;width:0;height:0}
#pfx .sw .sl{position:absolute;cursor:pointer;inset:0;background:#cbd1da;border-radius:25px;transition:.18s}
#pfx .sw .sl:before{content:"";position:absolute;height:19px;width:19px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
#pfx .sw input:checked + .sl{background:#2f9e6f}
#pfx .sw input:checked + .sl:before{transform:translateX(21px)}
#pfx .pfx-genrow{display:flex;align-items:center;gap:12px;padding:5px 0}
#pfx .pfx-genrow .pfx-lab{min-width:140px}
@media(max-width:640px){#pfx .pfx-grid{grid-template-columns:1fr;gap:4px 0}#pfx .pfx-help{margin-bottom:8px}}
</style>

<div id="pfx">
  <p class="pfx-intro">Live modifier over the playing sequence. Functions run in order: mirror &rarr; hue &rarr; saturation &rarr; color order &rarr; brightness &rarr; sparkle &rarr; strobe &rarr; framerate. Changes apply within ~0.5&nbsp;s &mdash; no restart. Test patterns are never modified. Ranges default to all output channels.</p>

  <div class="pfx-presets">
    <b>Presets</b>
    <select id="pfx-preset-list"></select>
    <button type="button" class="pfx-btn" onclick="pfxPresetApply()">Apply</button>
    <button type="button" class="pfx-btn alt" onclick="pfxPresetSave()">Save current&hellip;</button>
    <button type="button" class="pfx-btn alt" onclick="pfxPresetDelete()">Delete</button>
  </div>

  <div class="pfx-card">
    <div class="pfx-head"><span class="pfx-title">General <span class="pfx-sub">&mdash; master switch</span></span>
      <label class="sw"><input type="checkbox" id="pfx-master" data-key="enabled"<?php echo px_chk('enabled'); ?> onChange="SetPluginSetting('pixelfx','enabled', this.checked?1:0,0,0); pfxToggle(this);"><span class="sl"></span></label></div>
    <div class="pfx-body">
      <div class="pfx-genrow"><span class="pfx-lab">Only while playing</span><?php echo ctlToggle('onlyWhenPlaying', '1'); ?><span class="pfx-help">Only modify during sequence playback.</span></div>
      <div class="pfx-genrow"><span class="pfx-lab">Channels / pixel</span><?php echo ctlSel('channelsPerPixel', array('3', '4'), '3'); ?><span class="pfx-help">3 = RGB, 4 = RGBW (white preserved by hue/color order).</span></div>
    </div>
  </div>

  <div class="pfx-card"><?php echo head('Mirror', '&mdash; reverse / reflect pixels', 'mr_enabled') . bodyOpen('mr_enabled');
    echo row('Range', ctlRange('mr'), 'Start channel &amp; count.');
    echo row('Mode', ctlSel('mr_mode', array('reverse', 'mirror'), 'reverse'), 'reverse = flip range; mirror = reflect first half.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Hue shift wave', '&mdash; animated hue rotation', 'hs_enabled') . bodyOpen('hs_enabled');
    echo row('Range', ctlRange('hs'), 'channels = LEDs &times; 3.');
    echo row('Wave', ctlSel('hs_hueWave', array('off', 'sine', 'triangle', 'sawtooth', 'square'), 'off'), 'Waveform driving the rotation.');
    echo row('Period', ctlNum('hs_huePeriodMs', '5000', 1), 'ms &middot; one full cycle.');
    echo row('Depth', ctlNum('hs_hueDepthDeg', '360', 0, 360), '360 = full spectrum.');
    echo row('Phase / LED', ctlNum('hs_huePhasePerChannel', '0', '', '', '0.1'), 'Per-pixel offset for a traveling rainbow.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Saturation', '&mdash; boost or wash out color', 'sa_enabled') . bodyOpen('sa_enabled');
    echo row('Range', ctlRange('sa'), 'Start channel &amp; count.');
    echo row('Saturation', ctlNum('sa_level', '100', 0, 300, 5), '% &middot; 100 = unchanged, 0 = grayscale, &gt;100 = boosted.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Color order', '&mdash; reorder R/G/B bytes', 'co_enabled') . bodyOpen('co_enabled');
    echo row('Range', ctlRange('co'), 'Start channel &amp; count.');
    echo row('Order', ctlSel('co_colorOrder', array('RGB', 'RBG', 'GRB', 'GBR', 'BRG', 'BGR'), 'RGB'), 'Output byte order per pixel.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Brightness', '&mdash; dimmer / power limit', 'br_enabled') . bodyOpen('br_enabled');
    echo row('Range', ctlRange('br'), 'Start channel &amp; count.');
    echo row('Brightness', ctlNum('br_level', '100', 0, 100), '% &middot; 100 = full.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Sparkle', '&mdash; random white twinkles', 'sp_enabled') . bodyOpen('sp_enabled');
    echo row('Range', ctlRange('sp'), 'Start channel &amp; count.');
    echo row('Density', ctlNum('sp_density', '10', 0, 100), 'How often new twinkles appear.');
    echo row('Decay', ctlNum('sp_decayMs', '400', 1, 5000, 50), 'ms &middot; how fast each twinkle fades.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Strobe', '&mdash; blink on/off', 'st_enabled') . bodyOpen('st_enabled');
    echo row('Range', ctlRange('st'), 'Start channel &amp; count.');
    echo row('Period', ctlNum('st_periodMs', '200', 10, 10000, 10), 'ms &middot; full on+off cycle.');
    echo row('On time', ctlNum('st_duty', '50', 1, 99), '% of each period the pixels are lit.');
  ?></div></div></div>

  <div class="pfx-card"><?php echo head('Framerate', '&mdash; hold frames to a target FPS', 'fr_enabled') . bodyOpen('fr_enabled');
    echo row('Range', ctlRange('fr'), 'Start channel &amp; count.');
    echo row('Frames / sec', ctlNum('fr_fps', '20', 0, 120), '0 = disabled.');
  ?></div></div></div>
</div>

<script>
var pfxPresets = {};
try { pfxPresets = JSON.parse(<?php echo json_encode($presetsRaw ?: '{}'); ?>) || {}; } catch (e) { pfxPresets = {}; }
var PFX_SKIP = { enabled: 1, presets: 1 };

function pfxToggle(cb) {
    if (cb.id === 'pfx-master') {
        document.querySelectorAll('#pfx .pfx-card .pfx-body').forEach(function (b) {
            b.style.opacity = cb.checked ? '' : '.45';
            b.style.pointerEvents = cb.checked ? '' : 'none';
        });
        return;
    }
    var head = cb.closest('.pfx-head');
    if (head && head.nextElementSibling) head.nextElementSibling.classList.toggle('pfx-off', !cb.checked);
}

function pfxRenderPresets(sel) {
    var s = document.getElementById('pfx-preset-list');
    s.innerHTML = '';
    var names = Object.keys(pfxPresets);
    if (!names.length) { var o = document.createElement('option'); o.textContent = '(none saved)'; o.disabled = true; s.appendChild(o); return; }
    names.forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = n; if (n === sel) o.selected = true; s.appendChild(o); });
}
function pfxSavePresetsSetting() { SetPluginSetting('pixelfx', 'presets', JSON.stringify(pfxPresets), 0, 0); }
function pfxPresetSave() {
    var name = prompt('Save current settings as preset named:');
    if (!name) return;
    var p = {};
    document.querySelectorAll('#pfx [data-key]').forEach(function (el) {
        var k = el.getAttribute('data-key');
        if (PFX_SKIP[k]) return;
        p[k] = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : el.value;
    });
    pfxPresets[name] = p;
    pfxSavePresetsSetting();
    pfxRenderPresets(name);
}
function pfxPresetApply() {
    var name = document.getElementById('pfx-preset-list').value;
    var p = pfxPresets[name];
    if (!p) return;
    Object.keys(p).forEach(function (k) {
        SetPluginSetting('pixelfx', k, p[k], 0, 0);
        var el = document.querySelector('#pfx [data-key="' + k + '"]');
        if (el) {
            if (el.type === 'checkbox') { el.checked = (p[k] == '1'); pfxToggle(el); }
            else el.value = p[k];
        }
    });
}
function pfxPresetDelete() {
    var name = document.getElementById('pfx-preset-list').value;
    if (!name || !pfxPresets[name]) return;
    if (!confirm('Delete preset "' + name + '"?')) return;
    delete pfxPresets[name];
    pfxSavePresetsSetting();
    pfxRenderPresets();
}

// Compute the channel span of all configured outputs (co-*.json).
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
                return fetch('api/channel/output/' + f).then(function (r) { return r.json(); }).then(function (c) { walk(c); }).catch(function () {});
            });
        }, Promise.resolve());
    }).then(function () {
        pfxOutputsCache = (maxE > 0 && minS !== Infinity) ? { start: minS, count: maxE - minS + 1 } : { start: 1, count: 0 };
        return pfxOutputsCache;
    }).catch(function () { return { start: 1, count: 0 }; });
}
function pfxSetRange(p, start, count) {
    var si = document.getElementById('pfx-' + p + '-start'), ci = document.getElementById('pfx-' + p + '-count');
    if (si) si.value = start;
    if (ci) ci.value = count;
    SetPluginSetting('pixelfx', p + '_startChannel', start, 0, 0);
    SetPluginSetting('pixelfx', p + '_channelCount', count, 0, 0);
}
function pfxAllOutputs(p) {
    return pfxComputeOutputs().then(function (r) {
        if (!r.count) { alert('Could not detect output channels from the FPP output configuration.'); return; }
        pfxSetRange(p, r.start, r.count);
    });
}

// Target dropdown: pick "All outputs" or a named model (from /api/models).
var pfxModels = [];
function pfxLoadModels() {
    fetch('api/models').then(function (r) { return r.json(); }).then(function (arr) {
        pfxModels = Array.isArray(arr) ? arr : [];
        document.querySelectorAll('#pfx .pfx-target').forEach(function (s) {
            pfxModels.forEach(function (m) {
                var o = document.createElement('option');
                o.value = 'm:' + m.Name; o.textContent = m.Name; s.appendChild(o);
            });
        });
    }).catch(function () {});
}
function pfxTarget(sel) {
    var p = sel.getAttribute('data-prefix'), v = sel.value;
    if (v === '__all__') pfxAllOutputs(p);
    else if (v.indexOf('m:') === 0) {
        var name = v.slice(2);
        var m = pfxModels.filter(function (x) { return x.Name === name; })[0];
        if (m) pfxSetRange(p, m.StartChannel, m.ChannelCount);
    }
    sel.value = '';
}

document.addEventListener('DOMContentLoaded', function () {
    pfxRenderPresets();
    pfxLoadModels();
    var prefixes = ['mr', 'hs', 'sa', 'co', 'br', 'sp', 'st', 'fr'];
    var needs = prefixes.filter(function (p) { var ci = document.getElementById('pfx-' + p + '-count'); return ci && ci.value === '1500'; });
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
