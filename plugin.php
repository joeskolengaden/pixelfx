<?php
// Settings page for the "pixelfx" plugin. $pluginSettings is populated by FPP's
// LoadPluginSettings() from config/plugin.pixelfx.
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
function px_bool($plugin, $k)
{
    return "SetPluginSetting('$plugin','$k', this.checked ? 1 : 0, 0, 0, this.checked);";
}
function px_val($plugin, $k)
{
    return "SetPluginSetting('$plugin','$k', this.value, 0, 0, null);";
}
$P = 'pixelfx';
$colorOrders = array('RGB', 'RBG', 'GRB', 'GBR', 'BRG', 'BGR');
$waves = array('off', 'sine', 'triangle', 'sawtooth', 'square');
?>
<div id="global" class="settings">
    <fieldset class="fs">
        <legend>Pixel FX &mdash; general</legend>
        <table class="settingsTable">
            <tr>
                <td><b>Plugin Enabled</b></td>
                <td><input type="checkbox"<?php echo px_chk('enabled'); ?> onChange="<?php echo px_bool($P, 'enabled'); ?>"></td>
                <td>Master on/off for all functions. Applies live (no fppd restart).</td>
            </tr>
            <tr>
                <td>Only While Playing</td>
                <td><input type="checkbox"<?php echo px_chk('onlyWhenPlaying', '1'); ?> onChange="<?php echo px_bool($P, 'onlyWhenPlaying'); ?>"></td>
                <td>Only modify during sequence playback. Test patterns are never modified.</td>
            </tr>
        </table>
    </fieldset>

    <fieldset class="fs">
        <legend>Hue shift wave</legend>
        <table class="settingsTable">
            <tr><td><b>Enabled</b></td><td><input type="checkbox"<?php echo px_chk('hs_enabled'); ?> onChange="<?php echo px_bool($P, 'hs_enabled'); ?>"></td><td>Continuous hue rotation of lit pixels.</td></tr>
            <tr><td>Start Channel</td><td><input type="number" min="1" value="<?php echo htmlspecialchars(px_get('hs_startChannel', '1')); ?>" onChange="<?php echo px_val($P, 'hs_startChannel'); ?>"></td><td>1-based first channel.</td></tr>
            <tr><td>Channel Count</td><td><input type="number" min="0" step="3" value="<?php echo htmlspecialchars(px_get('hs_channelCount', '1500')); ?>" onChange="<?php echo px_val($P, 'hs_channelCount'); ?>"></td><td>Channels (LEDs &times; 3).</td></tr>
            <tr><td>Hue Wave</td><td><select onChange="<?php echo px_val($P, 'hs_hueWave'); ?>"><?php foreach ($waves as $w) { echo "<option value='$w'" . px_sel('hs_hueWave', $w, 'off') . ">$w</option>"; } ?></select></td><td>Waveform.</td></tr>
            <tr><td>Hue Period (ms)</td><td><input type="number" min="1" value="<?php echo htmlspecialchars(px_get('hs_huePeriodMs', '5000')); ?>" onChange="<?php echo px_val($P, 'hs_huePeriodMs'); ?>"></td><td>One full cycle.</td></tr>
            <tr><td>Hue Depth (deg)</td><td><input type="number" value="<?php echo htmlspecialchars(px_get('hs_hueDepthDeg', '360')); ?>" onChange="<?php echo px_val($P, 'hs_hueDepthDeg'); ?>"></td><td>360 = full spectrum.</td></tr>
            <tr><td>Hue Phase / LED (deg)</td><td><input type="number" step="0.1" value="<?php echo htmlspecialchars(px_get('hs_huePhasePerChannel', '0')); ?>" onChange="<?php echo px_val($P, 'hs_huePhasePerChannel'); ?>"></td><td>Per-pixel offset (traveling wave).</td></tr>
        </table>
    </fieldset>

    <fieldset class="fs">
        <legend>Color order</legend>
        <table class="settingsTable">
            <tr><td><b>Enabled</b></td><td><input type="checkbox"<?php echo px_chk('co_enabled'); ?> onChange="<?php echo px_bool($P, 'co_enabled'); ?>"></td><td>Reorder R/G/B bytes per pixel.</td></tr>
            <tr><td>Start Channel</td><td><input type="number" min="1" value="<?php echo htmlspecialchars(px_get('co_startChannel', '1')); ?>" onChange="<?php echo px_val($P, 'co_startChannel'); ?>"></td><td>1-based first channel.</td></tr>
            <tr><td>Channel Count</td><td><input type="number" min="0" step="3" value="<?php echo htmlspecialchars(px_get('co_channelCount', '1500')); ?>" onChange="<?php echo px_val($P, 'co_channelCount'); ?>"></td><td>Channels (LEDs &times; 3).</td></tr>
            <tr><td>Color Order</td><td><select onChange="<?php echo px_val($P, 'co_colorOrder'); ?>"><?php foreach ($colorOrders as $c) { echo "<option value='$c'" . px_sel('co_colorOrder', $c, 'RGB') . ">$c</option>"; } ?></select></td><td>Output byte order.</td></tr>
        </table>
    </fieldset>

    <fieldset class="fs">
        <legend>Framerate</legend>
        <table class="settingsTable">
            <tr><td><b>Enabled</b></td><td><input type="checkbox"<?php echo px_chk('fr_enabled'); ?> onChange="<?php echo px_bool($P, 'fr_enabled'); ?>"></td><td>Hold frames to a target FPS.</td></tr>
            <tr><td>Start Channel</td><td><input type="number" min="1" value="<?php echo htmlspecialchars(px_get('fr_startChannel', '1')); ?>" onChange="<?php echo px_val($P, 'fr_startChannel'); ?>"></td><td>1-based first channel.</td></tr>
            <tr><td>Channel Count</td><td><input type="number" min="0" value="<?php echo htmlspecialchars(px_get('fr_channelCount', '1500')); ?>" onChange="<?php echo px_val($P, 'fr_channelCount'); ?>"></td><td>Channels to hold.</td></tr>
            <tr><td>Framerate (fps)</td><td><input type="number" min="0" value="<?php echo htmlspecialchars(px_get('fr_fps', '20')); ?>" onChange="<?php echo px_val($P, 'fr_fps'); ?>"></td><td>Effective frame rate. 0 = disabled.</td></tr>
        </table>
    </fieldset>
</div>
