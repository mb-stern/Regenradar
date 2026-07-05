<?php

class Wetterradar extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('OpenWeatherInstanceID', 0);
        $this->RegisterPropertyInteger('TemperatureID', 0);
        $this->RegisterPropertyInteger('HumidityID', 0);
        $this->RegisterPropertyInteger('WindSpeedID', 0);
        $this->RegisterPropertyInteger('Rain1hID', 0);
        $this->RegisterPropertyFloat('Latitude', 47.3769);
        $this->RegisterPropertyFloat('Longitude', 8.5417);

        $this->RegisterPropertyString('RadarProvider', 'rainviewer');
        $this->RegisterPropertyString('RainbowApiKey', '');
        $this->RegisterPropertyString('RainbowLayer', 'precip');
        $this->RegisterPropertyInteger('RainbowColor', 5);
        $this->RegisterPropertyInteger('RainviewerColorScheme', 2);
        $this->RegisterPropertyInteger('RadarRefreshSeconds', 600);
        $this->RegisterPropertyBoolean('EnableAutoplay', false);
        $this->RegisterPropertyInteger('Zoom', 7);
        $this->RegisterPropertyString('Theme', 'dark');
        $this->RegisterPropertyString('MapStyle', 'street');

        $this->RegisterTimer('RadarUpdate', 0, 'WTR_UpdateRadar($_IPS["TARGET"]);');
        // Interner Fallback: Wetter/Forecast wird primär per VM_UPDATE aktualisiert.
        // Dieser Timer bleibt bewusst nicht konfigurierbar.
        $this->RegisterTimer('WeatherUpdate', 0, 'WTR_UpdateWeather($_IPS["TARGET"]);');

        // Gemerkte Variablen, auf deren VM_UPDATE die Wetter-/Forecast-Anzeige sofort aktualisiert wird.
        $this->RegisterAttributeString('WeatherWatchIDs', '[]');

        // HTML-SDK aktivieren. 1 = individuelle Darstellung via HTML-SDK.
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $radarSeconds = max(60, $this->ReadPropertyInteger('RadarRefreshSeconds'));

        // Radar aktiv abrufen, da Rainviewer/Rainbow keine Symcon-Variable aktualisieren.
        $this->SetTimerInterval('RadarUpdate', $radarSeconds * 1000);

        // Wetter/Forecast wird sofort über VM_UPDATE der Variablen aktualisiert.
        // Der interne Fallback läuft stündlich und ist nicht in der Konfiguration sichtbar.
        $this->SetTimerInterval('WeatherUpdate', 60 * 60 * 1000);

        // Wetter- und Forecast-Variablen überwachen: bei VM_UPDATE sofort nur die Wetterdaten neu senden.
        $this->RefreshWeatherWatchRegistrations();

        // Bei jeder Konfigurationsänderung das komplette HTML neu laden,
        // damit Theme, Kartenstil, Provider, Zoom, API-Key usw. sofort übernommen werden.
        $this->ReloadHtml();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message !== VM_UPDATE) {
            return;
        }

        $watchIDs = json_decode($this->ReadAttributeString('WeatherWatchIDs'), true);
        if (!is_array($watchIDs) || !in_array((int) $SenderID, array_map('intval', $watchIDs), true)) {
            return;
        }

        // Variable wurde geändert: nur Wetter + Forecast aktualisieren, kein HTML-Reload.
        $this->UpdateWeather();
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'RefreshRadar':
                $this->UpdateRadar();
                return;

            case 'ReloadHtml':
                $this->ReloadHtml();
                return;
        }

        throw new Exception('Invalid Ident: ' . $Ident);
    }

    public function GetConfigurationForm(): string
    {
        $form = [
            'elements' => [
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Wetterdaten',
                    'items' => [
                        [
                            'type' => 'SelectObject',
                            'name' => 'OpenWeatherInstanceID',
                            'caption' => 'OpenWeatherOneCall Instanz',
                            'objectType' => 1
                        ],
                        [
                            'type' => 'SelectObject',
                            'name' => 'TemperatureID',
                            'caption' => 'Temperatur Variable (0 = OpenWeather)',
                            'objectType' => 2
                        ],
                        [
                            'type' => 'SelectObject',
                            'name' => 'HumidityID',
                            'caption' => 'Luftfeuchte Variable (0 = OpenWeather)',
                            'objectType' => 2
                        ],
                        [
                            'type' => 'SelectObject',
                            'name' => 'WindSpeedID',
                            'caption' => 'Wind Variable (0 = OpenWeather)',
                            'objectType' => 2
                        ],
                        [
                            'type' => 'SelectObject',
                            'name' => 'Rain1hID',
                            'caption' => 'Regen 1h Variable (0 = OpenWeather)',
                            'objectType' => 2
                        ],
                    ],
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Radar',
                    'items' => [
                        [
                            'type' => 'Select',
                            'name' => 'RadarProvider',
                            'caption' => 'Radar Provider',
                            'options' => [
                                ['caption' => 'Rainviewer', 'value' => 'rainviewer'],
                                ['caption' => 'Rainbow', 'value' => 'rainbow'],
                            ],
                        ],
                        [
                            'type' => 'Select',
                            'name' => 'RainviewerColorScheme',
                            'caption' => 'RainViewer Farbschema',
                            'options' => [
                                ['caption' => '0 - RainViewer 0', 'value' => 0],
                                ['caption' => '1 - RainViewer 1', 'value' => 1],
                                ['caption' => '2 - RainViewer 2', 'value' => 2],
                                ['caption' => '3 - RainViewer 3', 'value' => 3],
                                ['caption' => '4 - RainViewer 4', 'value' => 4],
                                ['caption' => '5 - RainViewer 5', 'value' => 5],
                                ['caption' => '6 - RainViewer 6', 'value' => 6],
                                ['caption' => '7 - RainViewer 7', 'value' => 7],
                                ['caption' => '8 - RainViewer 8', 'value' => 8],
                            ],
                        ],
                        ['type' => 'ValidationTextBox', 'name' => 'RainbowApiKey', 'caption' => 'Rainbow API-Key'],
                        [
                            'type' => 'Select',
                            'name' => 'RainbowLayer',
                            'caption' => 'Rainbow Layer',
                            'options' => [
                                ['caption' => 'Precip', 'value' => 'precip'],
                                ['caption' => 'Precip Global', 'value' => 'precip-global'],
                                ['caption' => 'Clouds', 'value' => 'clouds'],
                                ['caption' => 'Radars', 'value' => 'radars'],
                            ],
                        ],
                       [
                        'type' => 'Select',
                        'name' => 'RainbowColor',
                        'caption' => 'Farbpalette',
                        'options' => [
                            ['caption' => '0 - Original', 'value' => 0],
                            ['caption' => '1 - Dark Sky', 'value' => 1],
                            ['caption' => '2 - Meteored', 'value' => 2],
                            ['caption' => '3 - NEXRAD', 'value' => 3],
                            ['caption' => '4 - Rainbow Classic', 'value' => 4],
                            ['caption' => '5 - RainViewer', 'value' => 5],
                            ['caption' => '6 - SELEX-IS', 'value' => 6],
                            ['caption' => '7 - TITAN', 'value' => 7],
                            ['caption' => '8 - Universal Blue', 'value' => 8],
                            ['caption' => '9 - The Weather Channel', 'value' => 9],
                        ]
                    ],
                        ['type' => 'NumberSpinner', 'name' => 'RadarRefreshSeconds', 'caption' => 'Radar aktualisieren alle Sekunden', 'minimum' => 60],
                        ['type' => 'CheckBox', 'name' => 'EnableAutoplay', 'caption' => 'Autoplay aktivieren'],
                    ],
                ],
                [
                    'type' => 'ExpansionPanel',
                    'caption' => 'Darstellung',
                    'items' => [
                        ['type' => 'NumberSpinner', 'name' => 'Zoom', 'caption' => 'Start-Zoom', 'minimum' => 1, 'maximum' => 12],
                        [
                            'type' => 'Select',
                            'name' => 'Theme',
                            'caption' => 'Theme',
                            'options' => [
                                ['caption' => 'Dunkel', 'value' => 'dark'],
                                ['caption' => 'Hell', 'value' => 'light'],
                            ],
                        ],
                        [
                            'type' => 'Select',
                            'name' => 'MapStyle',
                            'caption' => 'Kartenstil',
                            'options' => [
                                ['caption' => 'Street', 'value' => 'street'],
                                ['caption' => 'Topo', 'value' => 'topo'],
                                ['caption' => 'NatGeo', 'value' => 'natgeo'],
                                ['caption' => 'Satellite', 'value' => 'satellite'],
                                ['caption' => 'HOT', 'value' => 'hot'],
                                ['caption' => 'OSM FR', 'value' => 'osmfr'],
                                ['caption' => 'OpenTopo', 'value' => 'opentopo'],
                            ],
                        ],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'caption' => 'Radar jetzt aktualisieren',
                    'onClick' => 'WTR_UpdateRadar($id);',
                ],
                [
                    'type' => 'Button',
                    'caption' => 'HTML neu laden',
                    'onClick' => 'IPS_RequestAction($id, "ReloadHtml", true);',
                ],
            ],
        ];

        return json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function GetVisualizationTile(): string
    {
        $initial = [
            'type' => 'init',
            'data' => [
                'config'  => $this->BuildClientConfig(),
                'weather' => $this->BuildWeatherPayload(),
                'radar'   => $this->BuildRadarPayload()
            ]
        ];

        $initialJson = json_encode(
            $initial,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        return <<<HTML
<div id="wetterradar-root" class="wr-root">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">

    <style>
        .wr-root {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: system-ui, Segoe UI, Roboto, sans-serif;
            --wr-bg: rgba(40,40,40,0.85);
            --wr-text: #fff;
            --wr-panel: #2b2b2b;
            --wr-border: #444;
            --wr-btn-bg: #333;
            --wr-btn-fg: #fff;
            --wr-btn-border: #999;
            --k: 1;
            --wr-fs: calc(12px * var(--k));
            --wr-fs-small: calc(11px * var(--k));
            --wr-fs-tiny: calc(10px * var(--k));
            --wr-pad: calc(8px * var(--k));
            --wr-gap: calc(8px * var(--k));
            --wr-radius: calc(10px * var(--k));
            --wr-shadow: 0 calc(4px * var(--k)) calc(14px * var(--k)) rgba(0,0,0,.25);
            --wr-icon: calc(20px * var(--k));
            --wr-forecast-icon: calc(40px * var(--k));
            --wr-legend-swatch-w: calc(18px * var(--k));
            --wr-legend-swatch-h: calc(12px * var(--k));
            --wr-controls-w: calc(220px * var(--k));
            --wr-range-h: calc(22px * var(--k));
            --wr-btn-pad-v: calc(8px * var(--k));
            /* Script-kompatible Aliase für Tooltip-CSS */
            --bg: var(--wr-bg);
            --text: var(--wr-text);
            --pad: var(--wr-pad);
            --radius: var(--wr-radius);
            --fs-small: var(--wr-fs-small);
        }
        .wr-root.wr-light {
            --wr-bg: rgba(255,255,255,0.90);
            --wr-text: #222;
            --wr-panel: #fff;
            --wr-border: #ccc;
            --wr-btn-bg: #fff;
            --wr-btn-fg: #000;
            --wr-btn-border: #ddd;
        }
        .wr-root #map {
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        .wr-panel {
            position: absolute;
            z-index: 1000;
            background: var(--wr-bg);
            color: var(--wr-text);
            border-radius: var(--wr-radius);
            padding: var(--wr-pad);
            box-shadow: var(--wr-shadow);
            backdrop-filter: blur(4px);
            font-size: var(--wr-fs);
        }
        #wr-current {
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: calc(var(--wr-gap) + 6px);
            align-items: center;
        }
        #wr-current .wr-value {
            display: flex;
            align-items: center;
            gap: calc(6px * var(--k));
            font-size: var(--wr-fs);
            white-space: nowrap;
        }
        #wr-current img {
            width: var(--wr-icon);
            height: var(--wr-icon);
        }
        #wr-legend {
            bottom: 10px;
            left: 10px;
            font-size: var(--wr-fs-tiny);
        }
        #wr-legend .wr-legend-entry {
            display: flex;
            align-items: center;
            gap: calc(var(--wr-gap) - 4px);
            margin-bottom: calc(4px * var(--k));
        }
        #wr-legend .wr-legend-color {
            width: var(--wr-legend-swatch-w);
            height: var(--wr-legend-swatch-h);
            border: 1px solid var(--wr-border);
        }
        #wr-forecast {
            bottom: 10px;
            right: 10px;
            display: flex;
            gap: var(--wr-gap);
            padding: calc(var(--wr-pad) - 2px) var(--wr-pad);
            border-radius: var(--wr-radius);
        }
        #wr-controls {
            top: 10px;
            right: 10px;
            width: var(--wr-controls-w);
        }
        #wr-controls .wr-row {
            display: flex;
            gap: calc(var(--wr-gap) - 2px);
            justify-content: space-between;
            margin-bottom: calc(var(--wr-gap) - 2px);
        }
        #wr-controls button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--wr-btn-pad-v) 0;
            font-size: var(--wr-fs);
            line-height: 1;
            gap: 6px;
            min-width: 0;
            border-radius: calc(var(--wr-radius) - 2px);
            background: var(--wr-btn-bg);
            color: var(--wr-btn-fg);
            border: 1px solid var(--wr-btn-border);
            -webkit-appearance: none;
            appearance: none;
            background-image: none;
            box-shadow: none;
            cursor: pointer;
        }
        #wr-controls button:hover { filter: brightness(1.08); }
        #wr-controls label {
            display: block;
            font-size: var(--wr-fs-small);
            margin: 6px 0 2px;
        }
        #wr-frame-slider {
            width: 100%;
            height: var(--wr-range-h);
        }
        #wr-frame-time {
            display: block;
            margin-top: 6px;
            font-weight: 600;
            text-align: center;
            font-size: var(--wr-fs-small);
            cursor: pointer;
        }
        .wr-ico {
            width: 14px;
            height: 14px;
            display: inline-block;
        }
        .wr-ico svg {
            width: 100%;
            height: 100%;
            display: block;
            fill: currentColor;
        }
        .wr-forecast-entry {
            text-align: center;
            cursor: default;
        }
        .wr-forecast-entry img {
            width: var(--wr-forecast-icon);
            height: var(--wr-forecast-icon);
        }
        .wr-forecast-entry .wr-day {
            font-weight: 600;
            margin-bottom: 2px;
            font-size: var(--wr-fs);
        }
        .wr-forecast-entry .wr-temp {
            font-size: var(--wr-fs-small);
            opacity: .9;
        }
        #wr-status {
            display: none;
        }
        /* Tooltip 1:1 wie im Script: gleiche Variablen, gleiche Abstände, gleicher Schatten. */
        .tooltip {
            position: fixed;
            background: var(--bg);
            color: var(--text);
            font-size: var(--fs-small);
            padding: calc(var(--pad) - 2px) var(--pad);
            border-radius: var(--radius);
            box-shadow: 0 calc(8px * var(--k)) calc(18px * var(--k)) rgba(0,0,0,.28);
            white-space: normal;
            line-height: 1.25;
            max-width: calc(220px * var(--k));
            z-index: 2000;
        }
        @media (min-width: 701px) and (max-width: 1300px) {
            #wr-current {
                display: inline-flex;
                width: max-content;
                min-width: 340px;
                max-width: 560px;
            }
            #wr-current-values { flex: 0 0 auto; }
        }

        @media (min-width: 540px) {
            #wr-controls {
                width: 130px;
            }
            #wr-controls .wr-row {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                column-gap: 2px;
                margin-bottom: 6px;
            }
            #wr-controls button {
                border-radius: 2px;
                padding: 4px 0;
                font-size: 12px;
            }
            #wr-controls label {
                font-size: 12px;
                margin: 4px 0 2px;
            }
            #wr-frame-slider {
                height: 18px;
            }
            #wr-frame-time {
                font-size: 12px;
                margin-top: 4px;
            }
            .wr-ico {
                width: 12px;
                height: 12px;
            }
        }

        @media (max-width: 700px) and (min-width: 540px) {
            #wr-current {
                top: 8px;
                left: 56px;
                right: auto;
                transform: none;
                display: inline-flex;
                width: max-content;
                min-width: 0;
                max-width: calc(100vw - 56px - 16px - (130px + 16px));
                justify-content: flex-end;
                text-align: right;
                flex-wrap: wrap;
                gap: 6px;
            }
        }

        @media (max-width: 539px) {
            #wr-current {
                top: 8px;
                left: auto;
                right: 8px;
                transform: none;
                display: inline-flex;
                width: max-content;
                min-width: 0;
                max-width: calc(100vw - 56px - 16px);
                justify-content: flex-end;
                text-align: right;
                flex-wrap: wrap;
                gap: 6px;
            }
            #wr-controls {
                left: auto;
                right: 8px;
                width: 120px;
            }
            #wr-controls .wr-row {
                gap: 4px;
                margin-bottom: 6px;
                display: flex;
            }
            #wr-controls button {
                padding: 6px 0;
                font-size: 11px;
                border-radius: 6px;
            }
            #wr-controls label {
                font-size: 10px;
                margin: 4px 0 2px;
            }
            #wr-frame-slider {
                height: 16px;
            }
            #wr-frame-time {
                font-size: 10px;
                margin-top: 4px;
            }
        }

        /* Nur ganz am Schluss / sehr kleine Handybreite: Vorhersage rechts moderat kompakter. */
        @media (max-width: 430px) {
            #wr-forecast {
                max-width: min(200px, calc(100% - 120px));
                padding: 4px 5px;
                gap: 3px;
            }

            .wr-forecast-entry {
                flex-basis: 26px;
                min-width: 26px;
            }

            .wr-forecast-entry img {
                width: 22px;
                height: 22px;
            }

            .wr-forecast-entry .wr-day {
                font-size: 8px;
            }

            .wr-forecast-entry .wr-temp {
                font-size: 8px;
            }
        }

    </style>

    <div id="map"></div>

    <div id="wr-current" class="wr-panel">
        <div class="wr-value"><img src="https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/thermometer.svg" alt=""><span id="wr-temp">–</span></div>
        <div class="wr-value"><img src="https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/humidity.svg" alt=""><span id="wr-humidity">–</span></div>
        <div class="wr-value"><img src="https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/wind.svg" alt=""><span id="wr-wind">–</span></div>
        <div class="wr-value"><img src="https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/rain.svg" alt=""><span id="wr-rain">–</span></div>
        <div class="wr-value"><img src="https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/cloudy.svg" alt=""><span id="wr-clouds">–</span></div>
    </div>

    <div id="wr-controls" class="wr-panel">
        <div class="wr-row">
            <button id="wr-btn-prev" title="Vorheriger Frame" aria-label="Vorheriger">
                <span class="wr-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M15.5 5.5 8.5 12l7 6.5-1.5 1.5L5.5 12l8.5-8.5 1.5 2z"/></svg></span>
            </button>
            <button id="wr-btn-play" title="Abspielen" aria-label="Abspielen">
                <span class="wr-ico" aria-hidden="true"></span>
            </button>
            <button id="wr-btn-next" title="Nächster Frame" aria-label="Nächster">
                <span class="wr-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m8.5 5.5 1.5-2L18.5 12l-8.5 8.5-1.5-1.5 7-6.5-7-6.5z"/></svg></span>
            </button>
        </div>
        <label for="wr-frame-slider">Zeit</label>
        <input id="wr-frame-slider" type="range" min="0" max="0" step="1" value="0">
        <span id="wr-frame-time">⏳</span>
    </div>

    <div id="wr-legend" class="wr-panel"></div>

    <div id="wr-forecast" class="wr-panel"></div>
    <div id="wr-status" class="wr-panel">Initialisierung...</div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
(function wrScaleByTile(){
    function computeK(){
        const w = document.documentElement.clientWidth || window.innerWidth || 800;
        const k = Math.max(0.75, Math.min(1.0, w / 780));
        const root = document.getElementById('wetterradar-root');
        if (root) root.style.setProperty('--k', k.toFixed(3));
        if (window.wrMapRef && window.wrMapRef.invalidateSize) setTimeout(function() { window.wrMapRef.invalidateSize(); }, 0);
    }
    computeK();
    window.addEventListener('resize', computeK);
})();

const WR_INITIAL = {$initialJson};
const WR_ICON_URL = "https://raw.githubusercontent.com/basmilius/weather-icons/dev/production/fill/svg/";
const WR_ICON_MAP = {
    "01d":"clear-day","01n":"clear-night",
    "02d":"partly-cloudy-day","02n":"partly-cloudy-night",
    "03d":"cloudy","03n":"cloudy",
    "04d":"overcast","04n":"overcast",
    "09d":"rain","09n":"rain","10d":"rain","10n":"rain",
    "11d":"thunderstorms-day","11n":"thunderstorms-night",
    "13d":"snow","13n":"snow",
    "50d":"fog","50n":"fog"
};

let wrMap = null;
let wrBaseLayer = null;
let wrRadarLayer = null;
let wrData = WR_INITIAL;
let wrRadarPayload = null;
let wrConfig = (WR_INITIAL && WR_INITIAL.data && WR_INITIAL.data.config) ? WR_INITIAL.data.config : {};
let wrFrames = [];
let wrFrameIndex = 0;
let wrAnimationTimer = null;
let wrRadarLayerCache = {};
const WR_ANIMATION_DELAY_MS = 1000;
const wrPlaySvg = '<svg viewBox="0 0 24 24"><path d="M8 5l12 7-12 7V5z"/></svg>';
const wrPauseSvg = '<svg viewBox="0 0 24 24"><path d="M8 5h3v14H8V5zm5 0h3v14h-3V5z"/></svg>';

function wrSetText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '–';
}

function wrNumber(value, digits) {
    const n = Number(value);
    if (!Number.isFinite(n)) return 0;
    return Number(n.toFixed(digits || 0));
}

function wrRemoveTooltip() {
    const root = document.getElementById('wetterradar-root');
    const t = (root || document).querySelector('.tooltip');
    if (t) t.remove();
}

function wrShowForecastTooltip(entry, f) {
    wrRemoveTooltip();

    const t = document.createElement('div');
    t.className = 'tooltip';
    t.innerHTML =
        "<b style='display:block; margin-bottom:4px;'>" + (f.description || 'Vorhersage') + "</b>" +
        "<div>💧 " + wrNumber(f.humidity, 0) + " %</div>" +
        "<div>🌡️ " + Math.round(Number(f.max || 0)) + "° / " + Math.round(Number(f.min || 0)) + "°</div>" +
        "<div>💨 " + Math.round(Number(f.wind || 0)) + " km/h</div>" +
        (Number(f.rain || 0) > 0 ? "<div>🌧️ " + Number(f.rain).toFixed(1) + " mm</div>" : "") +
        (Number(f.snow || 0) > 0 ? "<div>❄️ " + Number(f.snow).toFixed(1) + " mm</div>" : "") +
        "<div>☁️ " + Math.round(Number(f.clouds || 0)) + " %</div>";

    // Im Modul in den Root hängen, damit die Script-Variablen (--bg/--text) wirklich greifen.
    const root = document.getElementById('wetterradar-root');
    (root || document.body).appendChild(t);

    const rect = entry.getBoundingClientRect();
    const margin = 8;
    const tRect = t.getBoundingClientRect();

    let left = rect.left + rect.width / 2 - tRect.width / 2;
    if (left < margin) left = margin;

    const maxLeft = window.innerWidth - margin - tRect.width;
    if (left > maxLeft) left = maxLeft;

    t.style.left = left + "px";
    t.style.top = (rect.top - 8) + "px";
    t.style.transform = "translate(0, -100%)";
}


function wrFrameTime(frame) {
    if (!frame || !frame.time) return 'Keine Radarframes';
    return new Date(Number(frame.time) * 1000).toLocaleTimeString();
}

function wrSetPlayState(isPlaying) {
    const btn = document.getElementById('wr-btn-play');
    if (!btn) return;
    btn.title = isPlaying ? 'Pause' : 'Abspielen';
    btn.setAttribute('aria-label', isPlaying ? 'Pause' : 'Abspielen');
    const ico = btn.querySelector('.wr-ico');
    if (ico) ico.innerHTML = isPlaying ? wrPauseSvg : wrPlaySvg;
}

function wrStopAnimation() {
    if (wrAnimationTimer) {
        clearTimeout(wrAnimationTimer);
        wrAnimationTimer = null;
    }
    wrSetPlayState(false);
}

function wrWrapFrameIndex(index) {
    if (!wrFrames.length) return 0;
    while (index >= wrFrames.length) index -= wrFrames.length;
    while (index < 0) index += wrFrames.length;
    return index;
}

function wrBuildRadarLayer(frame) {
    if (!wrRadarPayload || !frame) return null;

    if (wrRadarPayload.provider === 'rainviewer') {
        const host = wrRadarPayload.host || '';
        const tileSize = window.devicePixelRatio >= 2 ? 512 : 256;
        const colorScheme = Number.isFinite(Number(wrConfig.rainviewerColorScheme)) ? Number(wrConfig.rainviewerColorScheme) : 2;
        return L.tileLayer(
            host + frame.path + '/' + tileSize + '/{z}/{x}/{y}/' + colorScheme + '/1_1.png',
            { tileSize: 256, opacity: 0, maxNativeZoom: 7, maxZoom: 7 }
        );
    }

    if (wrRadarPayload.provider === 'rainbow') {
        return L.tileLayer(
            frame.url,
            { tileSize: 256, opacity: 0, maxNativeZoom: 12, maxZoom: 12 }
        );
    }

    return null;
}

function wrRadarCacheKey(frame) {
    if (!wrRadarPayload || !frame) return '';

    // Wichtig: nicht nach Index cachen, weil sich die Timeline beim nächsten Radar-Update verschiebt.
    // Der Key enthält Provider + Zeit + Tile-Quelle. Dadurch werden unveränderte Frames wiederverwendet,
    // aber Provider-/Layer-/Farbwechsel oder neue URLs trotzdem sauber neu geladen.
    if (wrRadarPayload.provider === 'rainviewer') {
        return 'rainviewer|' + String(wrConfig.rainviewerColorScheme || 2) + '|' + String(frame.time || '') + '|' + String(frame.path || '');
    }

    if (wrRadarPayload.provider === 'rainbow') {
        return 'rainbow|' + String(frame.time || '') + '|' + String(frame.url || '');
    }

    return String(frame.time || '') + '|' + JSON.stringify(frame);
}

function wrPruneRadarLayerCache(validKeys) {
    for (const key in wrRadarLayerCache) {
        if (Object.prototype.hasOwnProperty.call(wrRadarLayerCache, key) && !validKeys[key]) {
            try { wrMap.removeLayer(wrRadarLayerCache[key]); } catch(e) {}
            if (wrRadarLayer === wrRadarLayerCache[key]) {
                wrRadarLayer = null;
            }
            delete wrRadarLayerCache[key];
        }
    }
}

function wrShowFrame(index) {
    if (!wrMap || !wrFrames.length) return;

    index = wrWrapFrameIndex(index);
    const frame = wrFrames[index];
    const cacheKey = wrRadarCacheKey(frame);
    const slider = document.getElementById('wr-frame-slider');

    if (wrRadarLayer && wrRadarLayer !== wrRadarLayerCache[cacheKey]) {
        try { wrRadarLayer.setOpacity(0); } catch(e) {}
    }

    let layer = wrRadarLayerCache[cacheKey] || null;
    if (!layer) {
        layer = wrBuildRadarLayer(frame);
        if (!layer) return;
        wrRadarLayerCache[cacheKey] = layer;
        layer.addTo(wrMap);
    }

    layer.setOpacity(0.5);
    wrRadarLayer = layer;
    wrFrameIndex = index;

    if (slider) slider.value = String(index);
    wrSetText('wr-frame-time', wrFrameTime(frame));
}

function wrPlayAnimation() {
    wrStopAnimation();
    wrSetPlayState(true);

    function step() {
        wrShowFrame(wrFrameIndex + 1);
        wrAnimationTimer = setTimeout(step, WR_ANIMATION_DELAY_MS);
    }

    wrAnimationTimer = setTimeout(step, WR_ANIMATION_DELAY_MS);
}

function wrToggleAnimation() {
    if (wrAnimationTimer) {
        wrStopAnimation();
        return;
    }
    wrPlayAnimation();
}

function wrClearRadarLayers() {
    for (const key in wrRadarLayerCache) {
        if (Object.prototype.hasOwnProperty.call(wrRadarLayerCache, key)) {
            try { wrMap.removeLayer(wrRadarLayerCache[key]); } catch(e) {}
        }
    }
    wrRadarLayerCache = {};
    wrRadarLayer = null;
}


function wrRenderLegend(config) {
    const legend = document.getElementById('wr-legend');
    if (!legend) return;

    const items = config && Array.isArray(config.legend) ? config.legend : [];
    legend.innerHTML = '';

    if (!items.length) {
        legend.style.display = 'none';
        return;
    }

    legend.style.display = 'block';
    items.forEach(function(item) {
        if (!item || !item.color || !item.label) return;
        const entry = document.createElement('div');
        entry.className = 'wr-legend-entry';

        const swatch = document.createElement('div');
        swatch.className = 'wr-legend-color';
        swatch.style.background = item.color;

        const label = document.createTextNode(' ' + item.label);
        entry.appendChild(swatch);
        entry.appendChild(label);
        legend.appendChild(entry);
    });
}

function wrSetupControls() {
    wrSetPlayState(false);

    const prev = document.getElementById('wr-btn-prev');
    const next = document.getElementById('wr-btn-next');
    const play = document.getElementById('wr-btn-play');
    const slider = document.getElementById('wr-frame-slider');

    if (prev) prev.addEventListener('click', function() { wrStopAnimation(); wrShowFrame(wrFrameIndex - 1); });
    if (next) next.addEventListener('click', function() { wrStopAnimation(); wrShowFrame(wrFrameIndex + 1); });
    if (play) play.addEventListener('click', wrToggleAnimation);
    if (slider) slider.addEventListener('input', function(e) {
        wrStopAnimation();
        wrShowFrame(parseInt(e.target.value, 10));
    });
}

function wrRenderForecast(forecast) {
    const box = document.getElementById('wr-forecast');
    if (!box) return;

    box.innerHTML = '';

    if (!Array.isArray(forecast) || forecast.length === 0) {
        box.style.display = 'none';
        return;
    }

    box.style.display = 'flex';

    forecast.forEach(function(f) {
        if (!f || !f.dt) return;

        const d = new Date(Number(f.dt) * 1000);
        const day = d.toLocaleDateString('de-CH', { weekday: 'short' });
        const mappedIcon = WR_ICON_MAP[f.icon] || 'not-available';
        const icon = WR_ICON_URL + mappedIcon + '.svg';

        const entry = document.createElement('div');
        entry.className = 'wr-forecast-entry';
        entry.innerHTML =
            "<div class='wr-day'>" + day + "</div>" +
            "<img alt='' src='" + icon + "'>" +
            "<div class='wr-temp'>" + Math.round(Number(f.max || 0)) + "°/" + Math.round(Number(f.min || 0)) + "°</div>";

        entry.addEventListener('mouseenter', function() {
            wrShowForecastTooltip(entry, f);
        });
        entry.addEventListener('mouseleave', wrRemoveTooltip);

        box.appendChild(entry);
    });
}

function wrRenderWeather(payload) {
    if (!payload || !payload.current) return;

    wrSetText('wr-temp', payload.current.temperature);
    wrSetText('wr-humidity', payload.current.humidity);
    wrSetText('wr-wind', payload.current.wind);
    wrSetText('wr-rain', payload.current.rain);
    wrSetText('wr-clouds', payload.current.clouds);
    wrRenderForecast(payload.forecast || []);
}

function wrInitMap(config) {
    if (!config || wrMap) return;

    const root = document.getElementById('wetterradar-root');
    if (root && config.theme === 'light') {
        root.classList.add('wr-light');
    }

    wrMap = L.map('map', {
        zoomControl: true,
        attributionControl: true,
        maxZoom: config.radarMaxZoom || 7
    }).setView([config.lat, config.lon], config.zoom || 7);
    window.wrMapRef = wrMap;

    const options = {
        maxZoom: config.radarMaxZoom || 7,
        attribution: config.mapAttribution || ''
    };

    if (Array.isArray(config.mapSubdomains) && config.mapSubdomains.length > 0) {
        options.subdomains = config.mapSubdomains;
    }

    wrBaseLayer = L.tileLayer(config.mapTileUrl, options).addTo(wrMap);
    L.marker([config.lat, config.lon]).addTo(wrMap).bindPopup('Standort');
}

function wrRenderRadar(payload) {
    if (!payload || !wrMap) return;

    wrStopAnimation();
    wrRadarPayload = payload;

    wrFrames = Array.isArray(payload.frames) ? payload.frames.slice() : [];

    // Bestehende Radar-TileLayer behalten, solange der Frame in der neuen Timeline noch existiert.
    // So werden unveränderte Frames beim Timer-Update nicht unnötig neu geladen.
    const validCacheKeys = {};
    wrFrames.forEach(function(frame) {
        const key = wrRadarCacheKey(frame);
        if (key) validCacheKeys[key] = true;
    });
    wrPruneRadarLayerCache(validCacheKeys);

    const slider = document.getElementById('wr-frame-slider');

    if (!wrFrames.length) {
        if (slider) {
            slider.max = '0';
            slider.value = '0';
        }
        wrSetText('wr-frame-time', payload.error || 'Keine Radardaten');
        return;
    }

    if (slider) {
        slider.max = String(wrFrames.length - 1);
        slider.value = '0';
    }

    // wie im Script: Rainviewer startet beim letzten Past-Frame; Rainbow in der Mitte der Timeline.
    if (payload.provider === 'rainviewer') {
        wrFrameIndex = wrFrames.length - 1;
    } else {
        wrFrameIndex = Math.floor(wrFrames.length / 2);
    }

    wrShowFrame(wrFrameIndex);

    if (wrConfig && wrConfig.enableAutoplay) {
        wrPlayAnimation();
    }
}

function wrHandlePayload(payload) {
    if (!payload || !payload.type) return;
    wrData = payload;
    if (payload.data && payload.data.config) wrConfig = payload.data.config;

    if (payload.type === 'reload') {
        try {
            window.location.reload();
            return;
        } catch (e) {
            if (payload.data) {
                wrInitMap(payload.data.config);
                wrRenderLegend(payload.data.config);
                wrRenderWeather(payload.data.weather);
                wrRenderRadar(payload.data.radar);
            }
            return;
        }
    }

    if (payload.type === 'init') {
        wrInitMap(payload.data.config);
        wrRenderLegend(payload.data.config);
        wrRenderWeather(payload.data.weather);
        wrRenderRadar(payload.data.radar);
        return;
    }

    if (payload.type === 'weather') {
        wrRenderWeather(payload.data);
        return;
    }

    if (payload.type === 'radar') {
        wrRenderRadar(payload.data);
        return;
    }
}

function handleMessage(message) {
    try {
        const payload = JSON.parse(message);
        wrHandlePayload(payload);
    } catch (e) {
        wrSetText('wr-status', 'Fehler: ' + e.message);
    }
}

function wrPlaceControlsOnPhone() {
    const controls = document.getElementById('wr-controls');
    const current = document.getElementById('wr-current');
    if (!controls || !current) return;

    function update() {
        const isPhone = window.matchMedia('(max-width: 539px)').matches;
        if (isPhone) {
            const root = document.getElementById('wetterradar-root');
            const rootRect = root ? root.getBoundingClientRect() : { top: 0 };
            const rect = current.getBoundingClientRect();
            const top = Math.max(8, rect.bottom - rootRect.top + 3);
            controls.style.top = top + 'px';
        } else {
            controls.style.top = '10px';
        }
        if (wrMap && wrMap.invalidateSize) {
            setTimeout(function() { wrMap.invalidateSize(); }, 0);
        }
    }

    update();
    window.addEventListener('resize', update);
    try { new ResizeObserver(update).observe(document.documentElement); } catch(e) {}
}


wrSetupControls();
wrPlaceControlsOnPhone();
wrHandlePayload(WR_INITIAL);
setTimeout(function() {
    if (wrMap) wrMap.invalidateSize();
}, 250);
</script>
HTML;
    }

    public function UpdateWeather(): void
    {
        $this->SendDebug('UpdateWeather', 'Wetter-Aktualisierung gestartet', 0);
        $this->SendVisualizationMessage('weather', $this->BuildWeatherPayload());
    }

    public function UpdateRadar(): void
    {
        $this->SendDebug('UpdateRadar', 'Radar-Aktualisierung gestartet', 0);
        $this->SendVisualizationMessage('radar', $this->BuildRadarPayload());
    }

    public function ReloadHtml(): void
    {
        $this->SendVisualizationMessage('reload', [
            'config'  => $this->BuildClientConfig(),
            'weather' => $this->BuildWeatherPayload(),
            'radar'   => $this->BuildRadarPayload()
        ]);
    }

    private function SendVisualizationMessage(string $type, array $data): void
    {
        $payload = [
            'type' => $type,
            'data' => $data
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            $this->UpdateVisualizationValue($json);
        }
    }

    private function BuildClientConfig(): array
    {
        [$lat, $lon] = $this->GetLocation();
        $provider = $this->ReadPropertyString('RadarProvider');
        $radarMaxZoom = ($provider === 'rainbow') ? 12 : 7;
        $zoom = min(max(1, $this->ReadPropertyInteger('Zoom')), $radarMaxZoom);
        [$mapTileUrl, $mapAttribution, $subdomains] = $this->GetMapStyle();

        return [
            'lat' => $lat,
            'lon' => $lon,
            'zoom' => $zoom,
            'theme' => $this->ReadPropertyString('Theme'),
            'radarMaxZoom' => $radarMaxZoom,
            'radarRefreshSeconds' => max(60, $this->ReadPropertyInteger('RadarRefreshSeconds')),
            'rainviewerColorScheme' => min(max(0, $this->ReadPropertyInteger('RainviewerColorScheme')), 8),
            'rainbowColor' => min(max(0, $this->ReadPropertyInteger('RainbowColor')), 9),
            'legend' => $this->BuildLegendPayload($provider),
            'enableAutoplay' => $this->ReadPropertyBoolean('EnableAutoplay'),
            'mapTileUrl' => $mapTileUrl,
            'mapAttribution' => $mapAttribution,
            'mapSubdomains' => $subdomains
        ];
    }

    private function RefreshWeatherWatchRegistrations(): void
    {
        $oldIDs = json_decode($this->ReadAttributeString('WeatherWatchIDs'), true);
        if (!is_array($oldIDs)) {
            $oldIDs = [];
        }

        foreach (array_unique(array_map('intval', $oldIDs)) as $id) {
            if ($id > 0) {
                $this->UnregisterMessage($id, VM_UPDATE);
            }
        }

        $newIDs = $this->CollectWeatherWatchIDs();
        foreach ($newIDs as $id) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        $this->WriteAttributeString('WeatherWatchIDs', json_encode($newIDs, JSON_UNESCAPED_SLASHES));
    }

    private function CollectWeatherWatchIDs(): array
    {
        $owmInstID = $this->ReadPropertyInteger('OpenWeatherInstanceID');
        $ids = [];

        // Aktuelle Werte: eigene Variablen oder Fallback aus OpenWeather.
        $ids[] = $this->ResolveVariableID('TemperatureID', 'Temperature', $owmInstID);
        $ids[] = $this->ResolveVariableID('HumidityID', 'Humidity', $owmInstID);
        $ids[] = $this->ResolveVariableID('WindSpeedID', 'WindSpeed', $owmInstID);
        $ids[] = $this->ResolveVariableID('Rain1hID', 'Rain_1h', $owmInstID);
        $ids[] = $this->GetObjectIDByIdentSafe('Cloudiness', $owmInstID);

        // Forecast-Werte aus OpenWeather überwachen.
        if ($owmInstID > 0 && @IPS_ObjectExists($owmInstID)) {
            $count = (int) @IPS_GetProperty($owmInstID, 'daily_forecast_count');
            if ($count <= 0) {
                $count = 5;
            }

            $forecastIdents = [
                'DailyForecastBegin',
                'DailyForecastTemperatureMin',
                'DailyForecastTemperatureMax',
                'DailyForecastWindSpeed',
                'DailyForecastRain',
                'DailyForecastSnow',
                'DailyForecastConditionIcon',
                'DailyForecastConditions',
                'DailyForecastHumidity',
                'DailyForecastCloudiness'
            ];

            for ($i = 0; $i < $count; $i++) {
                $post = '_' . sprintf('%02d', $i);
                foreach ($forecastIdents as $identPrefix) {
                    $ids[] = $this->GetObjectIDByIdentSafe($identPrefix . $post, $owmInstID);
                }
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));

        sort($ids);
        return $ids;
    }


    private function BuildLegendPayload(string $provider): array
    {
        if ($provider === 'rainbow') {
            return $this->GetRainbowLegend(min(max(0, $this->ReadPropertyInteger('RainbowColor')), 9));
        }

        return $this->GetRainviewerLegend(min(max(0, $this->ReadPropertyInteger('RainviewerColorScheme')), 8));
    }

    private function GetRainviewerLegend(int $scheme): array
    {
        // Kompakte Modul-Legende: gleiche Intensitätsstufen, Farben passend zum gewählten RainViewer-Schema.
        $palettes = [
            0 => ['#9fd3ff', '#2f9cff', '#0068ff', '#ff9900', '#cc0033'],
            1 => ['#b7e3ff', '#5bbcff', '#0094ff', '#f2c84b', '#d23b3b'],
            2 => ['#b3d9ff', '#3399ff', '#0066ff', '#cc3300', '#990099'],
            3 => ['#8fd3ff', '#00a8ff', '#00cc66', '#ffcc00', '#ff3333'],
            4 => ['#a7f3d0', '#34d399', '#22c55e', '#f59e0b', '#dc2626'],
            5 => ['#c7f9ff', '#60a5fa', '#2563eb', '#f97316', '#7e22ce'],
            6 => ['#d8f3ff', '#7dd3fc', '#38bdf8', '#fb923c', '#a855f7'],
            7 => ['#c4e0ff', '#76b7ff', '#3578ff', '#ff7a00', '#b000b5'],
            8 => ['#e0f2fe', '#7dd3fc', '#0284c7', '#facc15', '#be123c'],
        ];

        return $this->BuildLegendItems($palettes[$scheme] ?? $palettes[2]);
    }

    private function GetRainbowLegend(int $scheme): array
    {
        // Rainbow-Paletten 0-9. Die Legende folgt der gewählten Palette und bleibt bewusst kompakt.
        $palettes = [
            0 => ['#7dd3fc', '#22c55e', '#facc15', '#f97316', '#a855f7'],
            1 => ['#5eead4', '#38bdf8', '#facc15', '#fb923c', '#ef4444'],
            2 => ['#93c5fd', '#60a5fa', '#2563eb', '#f97316', '#7c3aed'],
            3 => ['#a7f3d0', '#34d399', '#22c55e', '#f59e0b', '#dc2626'],
            4 => ['#9ae6b4', '#4ade80', '#facc15', '#fb923c', '#ef4444'],
            5 => ['#b3d9ff', '#3399ff', '#0066ff', '#cc3300', '#990099'],
            6 => ['#dbeafe', '#93c5fd', '#3b82f6', '#f97316', '#a21caf'],
            7 => ['#bfdbfe', '#60a5fa', '#1d4ed8', '#f59e0b', '#991b1b'],
            8 => ['#dff6ff', '#8edbff', '#25a7e0', '#006cba', '#073b8e'],
            9 => ['#5eead4', '#38bdf8', '#facc15', '#fb923c', '#ef4444'],
        ];

        return $this->BuildLegendItems($palettes[$scheme] ?? $palettes[5]);
    }

    private function BuildLegendItems(array $colors): array
    {
        $labels = ['Sehr leicht', 'Leicht', 'Mäßig', 'Stark', 'Extrem'];
        $items = [];

        foreach ($labels as $index => $label) {
            $items[] = [
                'label' => $label,
                'color' => $colors[$index] ?? '#999999'
            ];
        }

        return $items;
    }

    private function BuildWeatherPayload(): array
    {
        $owmInstID = $this->ReadPropertyInteger('OpenWeatherInstanceID');

        $temperatureID = $this->ResolveVariableID('TemperatureID', 'Temperature', $owmInstID);
        $humidityID = $this->ResolveVariableID('HumidityID', 'Humidity', $owmInstID);
        $windID = $this->ResolveVariableID('WindSpeedID', 'WindSpeed', $owmInstID);
        $rainID = $this->ResolveVariableID('Rain1hID', 'Rain_1h', $owmInstID);
        $cloudsID = $this->GetObjectIDByIdentSafe('Cloudiness', $owmInstID);

        return [
            'current' => [
                'temperature' => $this->GetFormattedValueSafe($temperatureID),
                'humidity' => $this->GetFormattedValueSafe($humidityID),
                'wind' => $this->GetFormattedValueSafe($windID),
                'rain' => $this->GetFormattedValueSafe($rainID),
                'clouds' => $this->GetFormattedValueSafe($cloudsID)
            ],
            'forecast' => $this->BuildForecastPayload($owmInstID),
            'updatedAt' => time()
        ];
    }

    private function BuildForecastPayload(int $owmInstID): array
    {
        if ($owmInstID <= 0 || !@IPS_ObjectExists($owmInstID)) {
            return [];
        }

        $count = 0;
        $location = @IPS_GetProperty($owmInstID, 'daily_forecast_count');
        if ($location !== false && $location !== '') {
            $count = (int) $location;
        }
        if ($count <= 0) {
            $count = 5;
        }

        $forecast = [];
        for ($i = 0; $i < $count; $i++) {
            $post = '_' . sprintf('%02d', $i);
            $beginID = $this->GetObjectIDByIdentSafe('DailyForecastBegin' . $post, $owmInstID);
            if ($beginID === 0) {
                continue;
            }

            $forecast[] = [
                'dt' => $this->GetValueIntegerSafe($beginID),
                'min' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastTemperatureMin' . $post, $owmInstID)),
                'max' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastTemperatureMax' . $post, $owmInstID)),
                'wind' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastWindSpeed' . $post, $owmInstID)),
                'rain' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastRain' . $post, $owmInstID)),
                'snow' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastSnow' . $post, $owmInstID)),
                'icon' => $this->GetValueStringSafe($this->GetObjectIDByIdentSafe('DailyForecastConditionIcon' . $post, $owmInstID)),
                'description' => $this->GetValueStringSafe($this->GetObjectIDByIdentSafe('DailyForecastConditions' . $post, $owmInstID)),
                'humidity' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastHumidity' . $post, $owmInstID)),
                'clouds' => $this->GetValueFloatSafe($this->GetObjectIDByIdentSafe('DailyForecastCloudiness' . $post, $owmInstID))
            ];
        }

        return $forecast;
    }

    private function BuildRadarPayload(): array
    {
        $provider = $this->ReadPropertyString('RadarProvider');
        if ($provider === 'rainbow') {
            return $this->BuildRainbowPayload();
        }

        return $this->BuildRainviewerPayload();
    }

    private function BuildRainviewerPayload(): array
    {
        $url = 'https://api.rainviewer.com/public/weather-maps.json';
        $json = $this->HttpGet($url, [], 10);
        $data = json_decode($json, true);

        if (!is_array($data) || !isset($data['host']) || !isset($data['radar']['past']) || !is_array($data['radar']['past'])) {
            return [
                'provider' => 'rainviewer',
                'host' => '',
                'frames' => [],
                'error' => 'Rainviewer-Daten ungültig'
            ];
        }

        return [
            'provider' => 'rainviewer',
            'host' => (string) $data['host'],
            'colorScheme' => min(max(0, $this->ReadPropertyInteger('RainviewerColorScheme')), 8),
            'frames' => array_values($data['radar']['past']),
            'updatedAt' => time()
        ];
    }

    private function BuildRainbowPayload(): array
    {
        $apiKey = trim($this->ReadPropertyString('RainbowApiKey'));
        $layer = $this->ReadPropertyString('RainbowLayer');
        $color = min(max(0, $this->ReadPropertyInteger('RainbowColor')), 9);

        if ($apiKey === '') {
            return [
                'provider' => 'rainbow',
                'frames' => [],
                'error' => 'Rainbow API-Key fehlt'
            ];
        }

        $snapshotUrl = 'https://api.rainbow.ai/tiles/v1/snapshot?layer=' . rawurlencode($layer);
        $json = $this->HttpGet($snapshotUrl, ['Ocp-Apim-Subscription-Key: ' . $apiKey], 10);
        $data = json_decode($json, true);
        $snapshot = (is_array($data) && isset($data['snapshot'])) ? (int) $data['snapshot'] : 0;

        if ($snapshot <= 0) {
            return [
                'provider' => 'rainbow',
                'frames' => [],
                'error' => 'Rainbow Snapshot ungültig'
            ];
        }

        $pastSteps = [-3600, -3000, -2400, -1800, -1200, -600];
        $forecastSteps = [0, 600, 1200, 1800, 2400, 3000, 3600];
        $frames = [];

        foreach ($pastSteps as $step) {
            $frameSnapshot = $snapshot + $step;
            $frames[] = [
                'time' => $frameSnapshot,
                'url' => $this->BuildRainbowTileUrl($layer, $frameSnapshot, 0, $color, $apiKey)
            ];
        }

        foreach ($forecastSteps as $step) {
            $frames[] = [
                'time' => $snapshot + $step,
                'url' => $this->BuildRainbowTileUrl($layer, $snapshot, $step, $color, $apiKey)
            ];
        }

        return [
            'provider' => 'rainbow',
            'snapshot' => $snapshot,
            'frames' => $frames,
            'updatedAt' => time()
        ];
    }

    private function BuildRainbowTileUrl(string $layer, int $snapshot, int $forecastTime, int $color, string $apiKey): string
    {
        return 'https://api.rainbow.ai/tiles/v1/' . rawurlencode($layer) . '/' . $snapshot . '/' . $forecastTime . '/{z}/{x}/{y}?color=' . $color . '&token=' . rawurlencode($apiKey);
    }

    private function ResolveVariableID(string $propertyName, string $fallbackIdent, int $fallbackParentID): int
    {
        $configuredID = $this->ReadPropertyInteger($propertyName);
        if ($configuredID > 0 && @IPS_ObjectExists($configuredID)) {
            return $configuredID;
        }

        return $this->GetObjectIDByIdentSafe($fallbackIdent, $fallbackParentID);
    }

    private function GetObjectIDByIdentSafe(string $ident, int $parentID): int
    {
        if ($parentID <= 0 || !@IPS_ObjectExists($parentID)) {
            return 0;
        }

        $id = @IPS_GetObjectIDByIdent($ident, $parentID);
        return ($id === false) ? 0 : (int) $id;
    }

    private function GetFormattedValueSafe(int $id): string
    {
        if ($id <= 0 || !@IPS_ObjectExists($id)) {
            return '–';
        }

        $value = @GetValueFormatted($id);
        return ($value === false) ? '–' : (string) $value;
    }

    private function GetValueIntegerSafe(int $id): int
    {
        if ($id <= 0 || !@IPS_ObjectExists($id)) {
            return 0;
        }
        return (int) @GetValue($id);
    }

    private function GetValueFloatSafe(int $id): float
    {
        if ($id <= 0 || !@IPS_ObjectExists($id)) {
            return 0.0;
        }
        return (float) @GetValue($id);
    }

    private function GetValueStringSafe(int $id): string
    {
        if ($id <= 0 || !@IPS_ObjectExists($id)) {
            return '';
        }
        return (string) @GetValue($id);
    }

    private function GetLocation(): array
    {
        $owmInstID = $this->ReadPropertyInteger('OpenWeatherInstanceID');
        if ($owmInstID > 0 && @IPS_ObjectExists($owmInstID)) {
            $locationJson = @IPS_GetProperty($owmInstID, 'location');
            $location = json_decode((string) $locationJson, true);
            if (is_array($location) && isset($location['latitude'], $location['longitude'])) {
                return [(float) $location['latitude'], (float) $location['longitude']];
            }
        }

        return [$this->ReadPropertyFloat('Latitude'), $this->ReadPropertyFloat('Longitude')];
    }

    private function GetMapStyle(): array
    {
        $mapStyles = [
            'street' => [
                'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
                'attribution' => '&copy; Esri, HERE, Garmin, FAO, NOAA, USGS, OpenStreetMap contributors'
            ],
            'topo' => [
                'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
                'attribution' => '&copy; Esri, HERE, Garmin, FAO, NOAA, USGS, OpenStreetMap contributors'
            ],
            'natgeo' => [
                'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/NatGeo_World_Map/MapServer/tile/{z}/{y}/{x}',
                'attribution' => '&copy; Esri, National Geographic, Garmin, HERE, UNEP-WCMC, USGS, NASA, ESA, METI, NRCAN, GEBCO, NOAA, increment P Corp.'
            ],
            'satellite' => [
                'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                'attribution' => '&copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            ],
            'hot' => [
                'url' => 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                'attribution' => '&copy; OpenStreetMap contributors, Tiles style by Humanitarian OpenStreetMap Team hosted by OpenStreetMap France',
                'subdomains' => ['a', 'b', 'c']
            ],
            'osmfr' => [
                'url' => 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',
                'attribution' => '&copy; OpenStreetMap contributors, Tiles style by OpenStreetMap France',
                'subdomains' => ['a', 'b', 'c']
            ],
            'opentopo' => [
                'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'attribution' => '&copy; OpenStreetMap contributors, SRTM | Kartendarstellung: &copy; OpenTopoMap',
                'subdomains' => ['a', 'b', 'c']
            ]
        ];

        $style = $this->ReadPropertyString('MapStyle');
        if (!isset($mapStyles[$style])) {
            $style = 'street';
        }

        return [
            $mapStyles[$style]['url'],
            $mapStyles[$style]['attribution'],
            $mapStyles[$style]['subdomains'] ?? []
        ];
    }

    private function HttpGet(string $url, array $headers = [], int $timeout = 10): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'IP-Symcon Wetterradar HTML-SDK',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers
            ]);
            $result = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false || $httpCode < 200 || $httpCode >= 300) {
                $this->SendDebug('HttpGet', 'HTTP=' . $httpCode . ' Error=' . $error . ' URL=' . $url, 0);
                return '';
            }

            return (string) $result;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers)
            ]
        ]);
        $result = @file_get_contents($url, false, $context);
        return ($result === false) ? '' : (string) $result;
    }
}
