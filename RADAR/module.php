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
        $this->RegisterPropertyInteger('RadarRefreshSeconds', 600);
        $this->RegisterPropertyInteger('WeatherRefreshSeconds', 300);
        $this->RegisterPropertyInteger('Zoom', 7);
        $this->RegisterPropertyString('Theme', 'dark');
        $this->RegisterPropertyString('MapStyle', 'street');

        $this->RegisterTimer('RadarUpdate', 0, 'IPS_RequestAction($_IPS["TARGET"], "RefreshRadar", true);');
        $this->RegisterTimer('WeatherUpdate', 0, 'IPS_RequestAction($_IPS["TARGET"], "RefreshWeather", true);');

        // HTML-SDK aktivieren. 1 = individuelle Darstellung via HTML-SDK.
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $radarSeconds = max(60, $this->ReadPropertyInteger('RadarRefreshSeconds'));
        $weatherSeconds = max(30, $this->ReadPropertyInteger('WeatherRefreshSeconds'));

        $this->SetTimerInterval('RadarUpdate', $radarSeconds * 1000);
        $this->SetTimerInterval('WeatherUpdate', $weatherSeconds * 1000);

        $provider = $this->ReadPropertyString('RadarProvider');
    
        $this->UpdateWeather();
        $this->UpdateRadar();
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'RefreshWeather':
                $this->UpdateWeather();
                return;

            case 'RefreshRadar':
                $this->UpdateRadar();
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
                        ['type' => 'NumberSpinner', 'name' => 'RainbowColor', 'caption' => 'Rainbow Farbe (0-9)', 'minimum' => 0, 'maximum' => 9],
                        ['type' => 'NumberSpinner', 'name' => 'RadarRefreshSeconds', 'caption' => 'Radar aktualisieren alle Sekunden', 'minimum' => 60],
                        ['type' => 'NumberSpinner', 'name' => 'WeatherRefreshSeconds', 'caption' => 'Wetter + Forecast aktualisieren alle Sekunden', 'minimum' => 30],
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
                    'caption' => 'Wetter + Forecast jetzt aktualisieren',
                    'onClick' => 'WTR_UpdateWeather($id);',
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Radar jetzt aktualisieren',
                    'onClick' => 'WTR_UpdateRadar($id);',
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
                'config' => $this->BuildClientConfig(),
                'weather' => $this->BuildWeatherPayload(),
                'radar' => $this->BuildRadarPayload()
            ]
        ];

        $initialJson = json_encode(
            $initial,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        $html = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'module.html');
        if ($html === false) {
            return '<div>module.html fehlt</div>';
        }

        return str_replace('%%INITIAL_JSON%%', (string) $initialJson, $html);
    }

    public function UpdateWeather(): void
    {
        $this->SendVisualizationMessage('weather', $this->BuildWeatherPayload());
    }

    public function UpdateRadar(): void
    {
        $this->SendVisualizationMessage('radar', $this->BuildRadarPayload());
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
            'weatherRefreshSeconds' => max(30, $this->ReadPropertyInteger('WeatherRefreshSeconds')),
            'mapTileUrl' => $mapTileUrl,
            'mapAttribution' => $mapAttribution,
            'mapSubdomains' => $subdomains
        ];
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
