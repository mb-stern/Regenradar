# Regenradar für IP-Symcon

Ein modernes HTML-Visualisierungsmodul für **IP-Symcon**, das Wetterdaten aus **OpenWeather OneCall** und der eigenen Wetterstation mit einem animierten Regenradar kombiniert.

Voraussetzung ist ein installiertes **OpenWeather OneCall** Modul.

Das Modul wurde für Desktop, Tablet und Smartphone optimiert und legt besonderen Wert auf eine schnelle Darstellung sowie möglichst wenig unnötigen Netzwerkverkehr.

---

## Funktionen

* 🌧️ Animiertes Regenradar

  * RainViewer
  * Rainbow API
* 🗺️ Mehrere Kartenstile

  * OpenStreetMap
  * Topo
  * OpenTopo
  * HOT
  * NatGeo
  * Satellite
  * OSM France
* 🌡️ Aktuelle Wetterdaten

  * Temperatur
  * Luftfeuchtigkeit
  * Wind
  * Niederschlag
  * Bewölkung
* 📅 Mehrtages-Wettervorhersage
* ▶️ Radaranimation mit Play/Pause
* ⏮️ Vor-/Zurückblättern der Radarframes
* 🎚️ Zeitschieberegler
* 🌙 Hell- und Dunkelmodus
* 📱 Optimierte Darstellung für Smartphone, Tablet und Desktop
* 🔍 Optionales Tile-Debug für Entwickler

---

## Unterstützte Radarprovider

### RainViewer

Kostenlos nutzbar.

### Rainbow API

Unterstützt verschiedene Layer und Farbpaletten.

---

## Aktualisierung

Das Modul erzeugt **keinen permanenten Hintergrundverkehr**.

Das Radar wird

* beim Öffnen der Visualisierung sofort aktualisiert,
* anschließend im konfigurierten Aktualisierungsintervall,
* ausschließlich solange die Visualisierung geöffnet bzw. sichtbar ist.

Dadurch entstehen keine regelmäßigen Radarabrufe, wenn die Visualisierung nicht verwendet wird.

---

## Voraussetzungen

* IP-Symcon 8.x oder neuer
* OpenWeather OneCall Modul
* Internetverbindung für Radar- und Kartendaten

---

## Konfiguration

### Wetter

Es können entweder

* die Variablen des OpenWeather-Moduls verwendet werden

oder

* eigene Variablen (Temperatur, Luftfeuchtigkeit, Wind und Niederschlag) ausgewählt werden.

### Radar

Einstellbar sind

* Radarprovider
* Aktualisierungsintervall
* Autoplay
* Tile-Debug
* Rainbow-Layer
* Rainbow-Farbpalette

### Darstellung

* Kartenstil
* Startzoom
* Hell/Dunkel-Theme

---

## Bedienung

Die Visualisierung bietet

* Play/Pause
* Vorheriger Frame
* Nächster Frame
* Zeitschieberegler
* Wettervorhersage mit Detailinformationen
* automatische Aktualisierung der Radarbilder

---

## Performance

Das Modul verwendet mehrere Optimierungen:

* Wiederverwendung bereits geladener Radar-Tiles
* Aktualisierung nur bei sichtbarer Visualisierung
* automatische Größenanpassung für Desktop und Mobilgeräte
* minimale Netzwerklast

---

## Entwicklung

Das Modul wurde speziell für den Einsatz in IP-Symcon entwickelt.

Feedback, Verbesserungsvorschläge und Pull Requests sind jederzeit willkommen.

---

## Versionen

**Version 1.0 (11.07.2026)**
* Inititale Version.

---

## Lizenz

MIT License

---

## Unterstützung

Falls dir das Modul gefällt und du die Weiterentwicklung unterstützen möchtest:

**PayPal:** https://paypal.me/mbstern
