# API- und Fehlerprüfung (25.10.2026)

Änderungen erfolgen im Branch `development`.

## Umgesetzte Korrekturen

- `SetVisualizationType()` wird in `Create()` und `ApplyChanges()` direkt nach dem Elternaufruf gesetzt. Ab Symcon 9.1 wird HTML-Vollbild verwendet; ohne die Konstante bleibt HTML-SDK Typ 1 für ältere Versionen erhalten.
- Sichtbarer, verlinkter Radar-Quellennachweis in der Leaflet-Karte, einschließlich RainViewer. Beim Providerwechsel wird der Nachweis ausgetauscht.
- MeteoSchweiz: Bildzeilen werden in Web Mercator statt linear im Breitengrad abgetastet. Das korrigiert den Versatz des Niederschlags gegenüber der Leaflet-Karte. Das native RZC-Raster bleibt LV95.
- Eine unsichtbare Visualisierung startet keine zusätzliche initiale Radaraktualisierung und kein Autoplay nach eintreffenden Daten. Serverseitige Erzeugung des initialen Payloads bleibt unverändert.

## Abgleich mit den Anbietern

### Symcon

[llms.txt](https://www.symcon.de/de/llms.txt), [Funktionsindex](https://www.symcon.de/de/llms/function-index.md), [SetVisualizationType](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module/setvisualizationtype.md).

Die Dokumentation bestätigt HTML-SDK seit 7.1 sowie `INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN = 2` seit 9.1. Der Fallback auf 1 erhält die deklarierte Modulkompatibilität ab 8.2.

### RainViewer

[Weather Maps](https://www.rainviewer.com/api/weather-maps-api.html), [Änderungen 2025/2026](https://www.rainviewer.com/api/transition-faq.html).

Live-Metadaten wurden ohne Schlüssel erfolgreich abgerufen: `host` und `radar.past` sind mit dem Modul kompatibel (13 Frames beim Test). Tile-URL aus `host` und `path`, Bildgröße 256/512, Farbe 2 (Universal Blue) und maximal Zoom 7 entsprechen der Dokumentation.

Seit Januar 2026 gibt es im öffentlichen Angebot keine Nowcast- oder Satelliten-IR-Daten mehr, nur Universal Blue und maximal Zoom 7; Limit 100 Anfragen/IP/Minute. Das Modul berücksichtigt Zoom/Farbe und verwendet keine entfernten Forecast-Felder. Autoplay und mehrere Clients können dennoch das IP-Limit überschreiten: Browser-Layercache ist kein gemeinsamer IP-weiser Request-Limiter. Der erforderliche sichtbare RainViewer-Quellenlink wurde ergänzt.

### Rainbow

[Dokumentation](https://doc.rainbow.ai/), [OpenAPI](https://doc.rainbow.ai/api-ref/api.fixed.json), [historische Frames](https://doc.rainbow.ai/faq/tiles/).

Die vollständige OpenAPI bestätigt ausdrücklich die vorhandenen `/tiles/v1/snapshot`- und `/tiles/v1/precip/{snapshot}/{forecast_time}/{zoom}/{tile_x}/{tile_y}`-Pfade. Der alternative Pfad im Einstiegsbeispiel ist kein Grund, die funktionierenden Tile-Pfade zu ersetzen. Der verwendete Header `Ocp-Apim-Subscription-Key` und der Tile-Queryparameter `token` sind dokumentiert. Palette 8 ist RainViewer Universal Blue.

Die vorhandenen Prognosen bis 14400 Sekunden in 600-Sekunden-Schritten und Zoom 12 liegen im dokumentierten Bereich. Die verwendete Stunde Historie liegt innerhalb der erlaubten zwei Stunden. Der Dienst antwortet ohne Schlüssel mit HTTP 401; authentifizierte Snapshot-/Tile-Inhalte wurden mangels konfiguriertem Schlüssel nicht geprüft.

### MeteoSchweiz / STAC

[STAC-Software](https://www.opensource.admin.ch/en/softwares/5owwhy/), [API-Dokumentation](https://data.geo.admin.ch/api/stac/static/spec/v1/api.html), [OpenAPI](https://data.geo.admin.ch/api/stac/static/spec/v1/openapi.yaml), [Radar-Collection](https://data.geo.admin.ch/api/stac/v1/collections/ch.meteoschweiz.ogd-radar-precip).

API-Einstieg, Collection, Item-Liste, aktuelles Tagesitem und eine echte Radar-HDF5-Datei wurden erfolgreich geladen. Tagesitem-IDs `YYYYMMDD-ch` und `rzc*.h5`-Assets passen zum Modul. Eine echte RZC-Datei bestätigt 710 × 640 Pixel, 1 km Auflösung, LV95, `RATE`, mm/h, Gain 1, Offset 0, NaN als fehlenden Wert. Die Collection nennt CC-BY. Die vorhandene Farbzuordnung macht NaN und Null transparent; eine Dekodierung als Byte-Index wäre für diese Datei falsch.

Das Modul verarbeitet weiterhin gezielt das aktuelle RZC-Format. Andere ODIM-Produkte mit abweichender Skalierung/Projektion sind damit nicht automatisch unterstützt.

## Noch nicht genutzte Möglichkeiten

Die folgenden Funktionen sind aktuell dokumentiert; wann sie eingeführt wurden, wurde nicht untersucht. Sie wurden nicht automatisch als neue Produktfunktionen eingebaut:

- Rainbow: bis zwei Stunden Historie (aktuell eine Stunde), `precip-global`, Wolken (`clouds`), Radar-Layer (`radars`), Coverage-Maske und Niederschlagsarten beim Radar-Layer. Wolken/Radar verwenden andere URL-Schemata und maximal Zoom 7.
- Rainbow: punktbezogene Nowcast- und Wetterendpunkte, etwa als zusätzliche Prognosequelle. Dafür sind eigene Datenmodelle und eine Prüfung des API-Tarifs erforderlich.
- RainViewer: Coverage-Maske zum Unterscheiden von trockenem Wetter und fehlender Radarabdeckung. Nowcast ist dort keine verfügbare Erweiterung mehr.
- STAC: dokumentierte Suche, räumliche Filter und Sortierung können weitere Datenquellen erschließen. Eine STAC-Forecast-Erweiterung bedeutet jedoch nicht, dass die RZC-Collection selbst Zukunftsbilder anbietet.

Sinnvolle nächste Verbesserungen sind ein gemeinsamer Metadaten-Cache, eine IP-bezogene Begrenzung von Tile-Anfragen und eine Messung des HDF5-Renderaufwands auf Mobilgeräten. Die eigene mm/h-Farblegende ist keine Validierung der vollständigen Farbstufen sämtlicher Anbieter.

## Wiederholbare Prüfungen

Im Repository mit PHP und Node.js:

```sh
php -l RGR/module.php
php tests/module.php fallback /tmp/regenradar-test.js
php tests/module.php fullscreen /tmp/regenradar-test.js
node --check /tmp/regenradar-test.js
node tests/visualization.cjs /tmp/regenradar-test.js
```

In der vorbereiteten Cloud-Umgebung zuerst `export PATH="/workspace/.regenradar-tools/bin:$PATH"` setzen.

Die Regressionstests prüfen beide Symcon-Konstantenvarianten, wiederholtes ApplyChanges, Konfigurations-/Fehlerpayloads, ungültige Provider, URL-Encoding, Mercator-Abtastung, eine Schweizer Projektionsreferenz, NoData-Farben, Attribution, Frameauswahl und Sichtbarkeit. Die Tests verwenden minimale Symcon-/Browser-Doubles und sind kein echter Integrationstest.

Zusätzlich wurden die tatsächlichen PHP-Payload-Builder für RainViewer und MeteoSchweiz gegen die Live-APIs ausgeführt. Es gibt hier keine laufende IP-Symcon-Instanz und keinen Rainbow-Schlüssel. Darstellung, Vollbildwechsel, Aktionen und Browser-Tiles in einer echten Symcon-Installation bleiben deshalb als Integrationstest offen.
