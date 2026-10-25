> Aktueller Stand: Rainbow verwendet wieder reguläre Universal-Blue-PNG-Tiles (Palette 8). Die frühere Rohdaten-Umfärbung wurde wegen der gemeldeten Anzeigeausfälle entfernt. Details und Grenzen stehen im letzten Abschnitt.

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

Die vollständige OpenAPI bestätigt ausdrücklich die vorhandenen `/tiles/v1/snapshot`- und `/tiles/v1/precip/{snapshot}/{forecast_time}/{zoom}/{tile_x}/{tile_y}`-Pfade. Der alternative Pfad im Einstiegsbeispiel ist kein Grund, die funktionierenden Tile-Pfade zu ersetzen. Der verwendete Header `Ocp-Apim-Subscription-Key` und der Tile-Queryparameter `token` sind dokumentiert. Für die einheitliche Darstellung wird jetzt `dbz_u8` dekodiert und mit der offiziellen Universal-Blue-Tabelle eingefärbt (Details unten).

Die Prognosen bis 14400 Sekunden in 600-Sekunden-Schritten und nativer Radarzoom 12 liegen im dokumentierten Bereich. Die Historie verwendet jetzt die erlaubten zwei Stunden. Der Dienst antwortet ohne Schlüssel mit HTTP 401; authentifizierte Snapshot-/Tile-Inhalte wurden mangels konfiguriertem Schlüssel nicht geprüft.

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


## Ergänzung: einheitliche Farbskala und vollständige Reichweite

Die frühere ungeprüfte, grobe mm/h-Legende und die abweichende MeteoSchweiz-Palette wurden ersetzt. Maßgeblich ist jetzt `RGR/radar-palette.json`: die 128 Regen-Farbwerte der offiziellen [Universal-Blue-Tabelle](https://www.rainviewer.com/files/rainviewer_api_colors_table.csv) für −32 bis 95 dBZ. Die zweite Hälfte der CSV ist die Schneepalette und wird absichtlich nicht als zusätzliche Regen-Farbstufen eingelesen.

- RainViewer verwendet weiterhin Schema 2, nun ohne Glättung und ohne separate Schneefarben (`0_0`). Damit bleibt dieselbe Intensitätsfarbe für Regen und Schnee erhalten.
- Rainbow nutzt statt Palette 8 jetzt dokumentierte [Rohdaten](https://doc.rainbow.ai/tile_colors/#raw-dbz-values) (`color=dbz_u8`). Der Browser dekodiert `(R & 127) - 32`, behandelt Regen/Schnee mit derselben Palette und macht fehlende Abdeckung transparent. Vor der Dekodierung wird das Quellbild nicht skaliert. Es gibt keine zusätzlichen API-Abfragen für die Umfärbung.
- MeteoSchweiz-mm/h werden mit der expliziten Näherung `Z = 200 R^1,6` in dBZ umgerechnet und derselben Tabelle zugeordnet. Null/NaN bleiben transparent. Das ist eine Darstellungsvereinheitlichung; unterschiedliche Messverfahren und providerseitige Kalibrierungen werden dadurch nicht identisch.
- Legende und Renderer beziehen ihre Farben aus derselben Datei. Die Legende nennt dBZ-Referenzpunkte und nur **ungefähre** mm/h-Werte. Gleiche Farbtöne bedeuten damit dieselbe Skalenstufe, aber keine garantierte Gleichheit der Messungen. Die alte Aussage zu Palette 8 beschreibt den vorherigen Stand und ist durch diese Ergänzung ersetzt.

| Anbieter | Historie im Modul | Vorschau | Native Radardetails | Kartenzoom |
|---|---|---|---|---|
| RainViewer | alle gelieferten Frames, ca. 2 h | keine, seit 2026 eingestellt | bis Zoom 7 | jetzt bis 18, darüber Vergrößerung |
| Rainbow | jetzt volle 2 h statt 1 h | 1–4 h einstellbar; Standard 1 h, API-Maximum 4 h | bis Zoom 12 | jetzt bis 18, darüber Vergrößerung |
| MeteoSchweiz RZC | weiterhin letzte 12 Bilder, ca. 55 min | keine Zukunftsbilder in diesem RZC-Produkt | 1-km-Raster, keine XYZ-Zoomstufen | jetzt bis 18, ohne höhere Radarauflösung |

Mehr Kartenzoom ist bewusst von Radardetails getrennt; das Modul fordert keine nicht unterstützten Zoomstufen bei den Radar-Anbietern an. Basiskarten werden oberhalb Zoom 17 ebenfalls vergrößert. Die visuelle Größe einer Kachel ist keine zusätzliche Messgenauigkeit.

Nicht ausgeschöpft sind längere MeteoSchweiz-Archive (die Tagesitems enthalten deutlich mehr als zwölf RZC-Dateien), Rainbow-Coverage-/Wolken-/globale Layer und punktbezogene Prognosen sowie RainViewer-Coverage. Diese sind keine weiteren verfügbaren RZC-Vorschauframes und würden eigene Bedienelemente bzw. eine Speicher-/Ladebegrenzung benötigen. Mehr Bilder erhöhen Ladezeit, Browser-Speicher und ggf. Rainbow-Tile-Verbrauch; vier Stunden Vorschau sind daher wählbar, nicht ungefragt als Standard aktiviert.

Zusätzliche Tests prüfen offizielle Farbreferenzpunkte, die physikalische Näherung, identische Providerlegenden, NoData/Schnee-Dekodierung und Zoom 18. Ein echter Chromium-/Leaflet-Test hat die Canvas-Dekodierung mit synthetischen Rohdaten einschließlich transparenter Pixel erfolgreich geprüft:

```sh
# Chromium muss lokal mit einem CDP-Port laufen; Leaflet liegt als lokale Datei vor.
node tests/browser.cjs /tmp/regenradar-test.js /tmp/leaflet.js
```

Ein authentifizierter Rainbow-Rohdaten-/CORS-Livetest ist weiterhin mangels Schlüssel offen; der Browsertest ersetzt ihn nicht. Ebenso bleibt die Bedienprüfung in einer echten IP-Symcon-Instanz offen.


## Providerwechsel direkt in der Kachel

Das Auswahlfeld oberhalb der Farbskala bietet RainViewer, Rainbow und MeteoSchweiz an. Der vorhandene Symcon-Aufruf `SetRadarProvider` speichert die Auswahl in der Instanz und löst `ApplyChanges()` aus. Der neue Payload aktualisiert die Kachel jetzt direkt, statt das Symcon-Iframe über `window.location.reload()` neu zu laden. Karte, Legende, Radarframes und Timer werden erneuert; beim Providerwechsel bleiben Kartenausschnitt und Zoom erhalten. Beim Themewechsel wird die Hell-/Dunkelklasse korrekt umgeschaltet. Ein unmittelbar fehlgeschlagener RequestAction-Aufruf gibt das Auswahlfeld wieder frei und zeigt einen Fehler.

Der PHP-Test prüft die Aktion einschließlich aktualisiertem Konfigurationspayload. Der Chromium-Test betätigt das echte HTML-Auswahlfeld für alle drei Provider und prüft Aktionsparameter, erneutes Freigeben, Kartenposition, Aktualisierung und synchrone Fehlerbehandlung. Der Host-Payload wird dabei simuliert; ein Live-Wechsel in IP-Symcon ist weiterhin offen. Rainbow kann ausgewählt werden, benötigt für Daten aber einen hinterlegten API-Schlüssel. Die Auswahl gilt wie bisher für die gesamte Symcon-Instanz, nicht nur für einen einzelnen Browser.

## Bildalter und Abrufintervalle

Das Modul begrenzt den konfigurierten Metadaten-Abrufabstand jetzt auf das Providerintervall: MeteoSchweiz maximal 300 Sekunden, Rainbow/RainViewer maximal 600 Sekunden. Kürzere konfigurierte Abstände bleiben erhalten (Minimum 60 Sekunden). Damit kann das bisherige Standardintervall von zehn Minuten nicht mehr jeden zweiten MeteoSchweiz-Abruf auslassen. Die Abrufe erfolgen weiterhin nur bei sichtbarer Kachel; ihre Phase ist nicht an den Veröffentlichungszeitpunkt des Anbieters gekoppelt.

Rainbow-Snapshot-Abfragen senden zusätzlich `Cache-Control: no-cache` und `Pragma: no-cache`, damit ein HTTP-Cache den Snapshot vor Verwendung revalidieren soll. Das ist keine Garantie, dass der Anbieter bereits neue Daten veröffentlicht hat. Authentifizierte Rainbow-Aktualität bleibt mangels Schlüssel ungetestet.

Live-Beobachtung am 25.10.2026 um 19:44 UTC: Das neueste MeteoSchweiz-RZC-Asset trug 19:40 UTC im Dateinamen und wurde laut STAC-Metadaten erst um 19:43:57 UTC erstellt. Fünf Minuten Bildabstand bedeuten daher nicht fünf Minuten Vorschau oder verzögerungsfreie Verfügbarkeit. Die Anzeige verwendet weiterhin die echten Provider-Zeitstempel und erfindet keinen Zukunftsversatz. Rainbow startet weiterhin beim neuesten gelieferten Snapshot; Zukunftsframes sind separat per Zeitschieber wählbar.

Regressionstests decken die Providergrenzen sowie kürzere konfigurierte Intervalle ab.


## Rainbow-Anzeige (dynamische Layoutüberarbeitung zurückgesetzt)

Nach wiederholt gemeldeten leeren Rainbow-Bildern wurde die zusätzliche CORS-/Canvas-Abhängigkeit aus der Rainbow-Anzeige entfernt: Die API liefert wieder fertig eingefärbte PNGs mit `color=8`, Leaflet zeigt sie als normale Bilder an. Die Regenpalette bleibt Universal Blue. Eine identische Schneefärbung mit MeteoSchweiz kann bei den Anbieter-PNGs nicht garantiert werden. Die präzise Ursache des gemeldeten Ausfalls wurde ohne authentifizierten Live-Zugriff nicht nachgewiesen; die Rohdaten-Umfärbung ist ein beseitigter möglicher Fehlerpfad, kein bestätigter API-Defekt.

Schlagen alle Tiles einer abgeschlossenen Ladegruppe fehl, zeigt die Zeitzeile nun einen Ladefehler, statt eine leere Karte nur mit gültigem Framezeitstempel zu präsentieren. Erfolgreiches Nachladen setzt den Fehler zurück. Die neuen Tests prüfen beide Zustände. Datenzeitpunkte und Providerintervalle werden nicht künstlich verschoben. Normale Bewölkung wird weiterhin bei keinem Provider hinzugefügt.


## Wiederkehrend leere Rainbow-Regenzellen: Cache und Live-Diagnose

Ein nachweisbarer Modulfehler wurde korrigiert: Radar-Layer mit vollständig fehlgeschlagenem Tile-Ladevorgang wurden bisher auch bei einem erneuten Metadatenabruf wiederverwendet, solange der Frame noch gültig war. Solche fehlerhaften Layer werden jetzt beim nächsten Radar-Payload verworfen und neu geladen. Erfolgreiche Layer bleiben gecacht. Der Regressionstest simuliert einen unveränderten Snapshot und prüft genau diese Unterscheidung. Das belegt einen korrigierten Wiederherstellungsfehler, nicht die Ursache des ersten Tile-Ausfalls.

In der Modulkonfiguration gibt es jetzt **„Rainbow-Verbindung prüfen“** (`RGR_CheckRainbowConnection`). Der manuell ausgelöste Test verwendet den bereits konfigurierten Schlüssel und führt maximal zwei Abrufe aus:

1. Snapshot mit der dokumentierten Header-Authentifizierung.
2. Ein Universal-Blue-Niederschlags-Tile am konfigurierten Standort, Zoom 7, Forecast-Offset 0, mit derselben Token-Query-Authentifizierung wie der Browser.

Die Ausgabe enthält ausschließlich Status und Einordnung, den Datenzeitpunkt und das Ergebnis einer PNG-Prüfung. Schlüssel, URLs mit Tokens und Antworttexte werden nicht ausgegeben. Auch reguläre HTTP-Debug-Meldungen enthalten nun keine URL-Queryparameter mehr. 401 (Schlüssel), 403 (Berechtigung/Tarif), 404 (Frame/Tile), 429 (Limit), Anbieterfehler und Transportfehler sind unterscheidbar. Ein gültiges PNG beweist Erreichbarkeit, aber weder Regen am Standort noch einen erfolgreichen Browserabruf.

Der native PHP-HTTP-Transport wurde gegen einen lokalen HTTP-Testserver mit 200/401 geprüft; das Diagnoseverhalten mit isolierten Antworten für Erfolg und Fehler. Authentifizierte Rainbow-Liveantworten sind hier weiterhin nicht verfügbar. Für die weitere Eingrenzung in der betroffenen Symcon-Instanz den neuen Knopf betätigen und dessen schlüsselfreie Textausgabe verwenden. Es werden weiterhin ausschließlich Niederschlagsbilder dargestellt.


## Rücknahme der letzten dynamischen Layoutänderung

Auf Nutzerwunsch wurden CSS, Anordnung, Legende, Vorhersage und Größenpositionierung auf den Stand vor der letzten Layoutüberarbeitung (`44b3c6a`) zurückgesetzt. Die neu eingeführten aufklappbaren Bereiche und der dazugehörige Layouttest wurden entfernt. Die ursprüngliche Größenanpassung bleibt bestehen. Die regulären Rainbow-PNGs, Wiederholung fehlgeschlagener Layer, Fehlermeldungen, API-Diagnose, HTML-SDK-Vollbildunterstützung und Providerwechsel bleiben erhalten.


## Vollständiger Rückgang der Dynamik auf den Chat-Ausgangsstand

Auf erneuten Nutzerwunsch wurden CSS, HTML-Anordnung, initiale Skalierung, Legenden-/Vorhersageaufbau, Größenpositionierung und der HTML-Reload-Handler direkt gegen den ursprünglichen Commit `7128469` abgeglichen und exakt daraus wiederhergestellt. Es gibt keine kompakte Grid-Ansicht und keine „Skala“-/„Vorhersage“-Aufklappschalter mehr. Die später hinzugefügte Aktualisierung ohne Seitenreload wurde ebenfalls entfernt: Ein Reload fordert jetzt wieder ein Neuladen des eingebetteten Dokuments an. Die vorigen Hinweise auf Erhalt des Kartenausschnitts bei Providerwechsel beschreiben damit nicht mehr den aktuellen Stand. Rainbow-PNGs, Cache-Wiederholung, Diagnose und sonstige API-Korrekturen sind unverändert erhalten.

Ein noch angezeigter „Skala“-Knopf kennzeichnet die zwischenzeitliche HTML-Version und kommt im aktuellen Quelltext nicht vor. Nach dem Modulupdate die gesamte Visualisierung im Browser vollständig neu laden; eine bereits laufende alte Kachel kann ihren bisherigen JavaScript-Code weiter ausführen. Die Rücknahme wurde per direktem Vergleich aller genannten Abschnitte mit `7128469` sowie den PHP-/JavaScript-Regressionstests geprüft.
