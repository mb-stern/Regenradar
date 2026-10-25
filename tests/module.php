<?php
// Run with PHP CLI; these host doubles do not replace an IP-Symcon integration test.
class IPSModuleStrict
{
    public int $InstanceID = 12345;
    public array $properties = [];
    public array $attributes = [];
    public array $calls = [];
    public array $messages = [];
    public function Create(): void { $this->calls[] = 'parent::Create'; }
    public function ApplyChanges(): void { $this->calls[] = 'parent::ApplyChanges'; }
    protected function RegisterPropertyInteger($name, $value) { $this->properties[$name] = $value; }
    protected function RegisterPropertyString($name, $value) { $this->properties[$name] = $value; }
    protected function RegisterPropertyBoolean($name, $value) { $this->properties[$name] = $value; }
    protected function RegisterAttributeString($name, $value) { $this->attributes[$name] = $value; }
    protected function ReadPropertyInteger($name) { return $this->properties[$name]; }
    protected function ReadPropertyString($name) { return $this->properties[$name]; }
    protected function ReadPropertyBoolean($name) { return $this->properties[$name]; }
    protected function ReadAttributeString($name) { return $this->attributes[$name]; }
    protected function WriteAttributeString($name, $value) { $this->attributes[$name] = $value; }
    protected function SetVisualizationType($value) { $this->calls[] = ['visualization', $value]; }
    protected function UpdateVisualizationValue($value) { $this->messages[] = json_decode($value, true, 512, JSON_THROW_ON_ERROR); }
    protected function SendDebug(...$args) {}
}
function IPS_GetInstanceListByModuleID($id) { return []; }
function IPS_SetProperty($id, $name, $value) {
    global $module;
    check($id === $module->InstanceID, 'Wrong target instance');
    $module->properties[$name] = $value;
}
function IPS_ApplyChanges($id) {
    global $module;
    check($id === $module->InstanceID, 'Wrong target instance');
    $module->ApplyChanges();
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
if (($argv[1] ?? '') === 'fullscreen') define('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN', 2);
require __DIR__ . '/../RGR/module.php';
$module = new Regenradar();
$module->Create();
$type = defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') ? 2 : 1;
check(array_slice($module->calls, 0, 2) === ['parent::Create', ['visualization', $type]], 'Create must enable supported visualization after parent');
$module->properties['RadarProvider'] = 'rainbow'; // Exercise missing-key handling without remote calls.
for ($i = 0; $i < 2; $i++) {
    $module->calls = [];
    $module->ApplyChanges();
    check(array_slice($module->calls, 0, 2) === ['parent::ApplyChanges', ['visualization', $type]], 'ApplyChanges must restore visualization after parent');
    $last = end($module->messages);
    check($last['type'] === 'reload' && $last['data']['radar']['error'] === 'Rainbow API-Key fehlt', 'ApplyChanges reload/error payload');
}
$form = json_decode($module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
check(count($form['elements']) > 0, 'Configuration form missing');
try { $module->RequestAction('SetRadarProvider', 'invalid'); throw new RuntimeException('Invalid provider accepted'); }
catch (Exception $e) { check(str_contains($e->getMessage(), 'Ungültiger Radar-Provider'), 'Unexpected provider error'); }
$method = new ReflectionMethod(Regenradar::class, 'BuildRainbowTileUrl');
$url = $method->invoke($module, 'precip', 1791889800, 14400, 8, 'test key&');
check($url === 'https://api.rainbow.ai/tiles/v1/precip/1791889800/14400/{z}/{x}/{y}?color=8&token=test%20key%26', 'Rainbow URL/credential encoding');
$legendMethod = new ReflectionMethod(Regenradar::class, 'BuildRadarLegendPayload');
$referenceEntries = null;
foreach (['rainviewer', 'rainbow', 'meteoswiss'] as $provider) {
    $module->properties['RadarProvider'] = $provider;
    $legend = $legendMethod->invoke($module);
    if ($referenceEntries === null) $referenceEntries = $legend['entries'];
    check($legend['entries'] === $referenceEntries, 'Provider legends must be identical');
    check(!isset($legend['note']), 'No explanatory footer in radar legend');
    check(str_starts_with($legend['entries'][0]['label'], '≈ '), 'Estimated mm/h remain marked as approximate');
    $configMethod = new ReflectionMethod(Regenradar::class, 'BuildClientConfig');
    $module->properties['Zoom'] = 18;
    $config = $configMethod->invoke($module);
    check($config['zoom'] === 18 && $config['radarMaxZoom'] === 18, 'Overzoom to 18');
}
$module->properties['RadarProvider'] = 'rainbow';
$html = $module->GetVisualizationTile();
check(str_contains($html, 'Rainbow API-Key fehlt'), 'Missing-key visualization');
preg_match_all('~<script\b[^>]*>(.*?)</script>~s', $html, $scripts);
$output = $argv[2] ?? (sys_get_temp_dir() . '/regenradar-test.js');
file_put_contents($output, implode("\n", $scripts[1]));
file_put_contents($output . ".html", $html);
echo "PASS lifecycle, configuration, provider validation, URL encoding and rendering (visualization=$type)\n";

$module->properties['RadarProvider'] = 'rainviewer';
$module->RequestAction('SetRadarProvider', ' RAINBOW ');
check($module->properties['RadarProvider'] === 'rainbow', 'Provider action must update configuration');
$last = end($module->messages);
check($last['type'] === 'reload' && $last['data']['config']['radarProvider'] === 'rainbow', 'Provider switch must send the new configuration');
echo "PASS provider switch action and updated visualization payload\n";

$configMethod = new ReflectionMethod(Regenradar::class, 'BuildClientConfig');
foreach (['rainviewer' => 600, 'rainbow' => 600, 'meteoswiss' => 300] as $provider => $cap) {
    $module->properties['RadarProvider'] = $provider;
    foreach ([0 => 60, 60 => 60, 120 => 120, 600 => $cap, 3600 => $cap] as $requested => $expected) {
        $module->properties['RadarRefreshSeconds'] = $requested;
        $config = $configMethod->invoke($module);
        check($config['radarRefreshSeconds'] === $expected, 'Refresh interval must respect provider cadence and minimum');
    }
}
echo "PASS provider refresh cadence and configured faster intervals\n";
