# 6. Einstellungen (nur Admin)

Systemweite Konfiguration — erfordert die Admin-Rolle. Redaktionsmitglieder ohne Admin-Rolle sehen den Menüpunkt **Einstellungen** in der Seitenleiste nicht (siehe [Kapitel 7, Rollen und Berechtigungen](07-rollen-und-berechtigungen.md)).

Der Adminbereich fasst die Konfiguration in zwei Seitenleisten-Gruppen zusammen: Alles unter **Einstellungen** ([Abschnitt 6.1](#61-einstellungen)) ist **dashboard-bezogen**, alles unter **System** ([Abschnitt 6.2](#62-system)) sind **installationsweite** Einstellungen.

> **Hinweis:** Die Bezeichnung „Sprache" taucht im Adminbereich zweimal mit unterschiedlicher Bedeutung auf: Die **Backend-Sprache** im Profil (siehe [Kapitel 1.4, Profil bearbeiten](01-erste-schritte.md)) bestimmt die Oberflächensprache des Adminbereichs selbst (Menüs, Buttons, Labels). Der **Sprachumschalter** oben auf der Seiteneinstellungen-Seite (siehe [Kapitel 1.5, Sprache umschalten](01-erste-schritte.md)) wählt dagegen, für welche Inhaltssprache (Deutsch/Englisch) die gerade bearbeiteten Felder gelten — analog zum Locale-Switcher bei Seiten- und Kachel-Inhalten.

## 6.1 Einstellungen

Die Gruppe **Einstellungen** bündelt die dashboard-bezogenen Konfigurationsseiten: **Seiteneinstellungen**, **Theme**, **Themes / Templates**, **Dashboard-Konfiguration**, **API Keys** und **Datenimport**.

### 6.1.1 Seiteneinstellungen

Die Seite **Seiteneinstellungen** bündelt die Basisdaten der Installation sowie die Header- und Footer-Navigation.

#### 6.1.1.1 Name und Mehrsprachigkeit

Im Abschnitt **Basisinformationen** stehen die grundlegenden, sprachunabhängigen Einstellungen der Installation:

| Feld | Beschreibung |
|------|-------------|
| **Seitenname** | Name der gesamten Installation. Erscheint u. a. im Browser-Tab-Titel und in System-E-Mails. Gilt für alle Dashboards (Tenants) gemeinsam. |
| **Übersetzung Englisch** | Schalter. Bei Deaktivierung wird der Sprachumschalter im Frontend ausgeblendet, und englische Routen leiten automatisch auf Deutsch um. |

<!-- Screenshot: Seiteneinstellungen: Name und Mehrsprachigkeit -->
![Seiteneinstellungen: Name und Mehrsprachigkeit](../assets/screenshots/de/06-seiteneinstellungen-allgemein.png)

> **Hinweis:** Das Favicon wird abweichend vom Feldnamen **nicht** auf dieser Seite gepflegt, sondern auf der Seite **Theme** im Logo-Bereich (siehe [Abschnitt 6.1.2, Theme / Branding](#612-theme--branding)). Diese Aufteilung weicht vom ursprünglichen Struktur-Entwurf ab, entspricht aber dem aktuellen Stand der Live-Anwendung.

#### 6.1.1.2 Header

Das Header-Menü wird auf der Seite **Seiteneinstellungen** im Abschnitt **Header → Navigations-Elemente** gepflegt. Jedes Navigations-Element besitzt:

| Feld | Beschreibung |
|------|-------------|
| **Typ** | **Seite** (verlinkt auf eine öffentliche Seite, siehe [Kapitel 2, Seiten verwalten](02-seiten-verwalten.md)) oder **Externer Link** (freie URL). |
| **Seite** | Nur bei Typ „Seite": Auswahl aus allen öffentlichen Seiten. Bezeichnung und URL werden automatisch aus der gewählten Seite übernommen. |
| **Bezeichnung** / **URL** | Nur bei Typ „Externer Link": frei editierbarer Linktext und Ziel-URL. |
| **Untermenü-Elemente** | Pro Element lässt sich eine Ebene Unterpunkte hinzufügen (gleiches Schema: Typ, Seite bzw. Bezeichnung/URL). |
| **Aktiv** | Schalter zum vorübergehenden Ausblenden eines Elements, ohne es zu löschen. |

Elemente lassen sich per Drag-Handle (**Verschieben**) neu anordnen; die Reihenfolge im Formular entspricht der Reihenfolge im Frontend-Header.

<!-- Screenshot: Seiteneinstellungen — Header-Abschnitt mit einem Navigations-Element vom Typ Externer Link -->
![Navigation: Header-Navigations-Element](../assets/screenshots/de/06-navigation.png)

**Wirkung im Frontend:** Die Navigations-Elemente erscheinen in dieser Reihenfolge im Haupt-Header des öffentlichen Dashboards; Untermenü-Elemente werden als Dropdown unter ihrem übergeordneten Element angezeigt.

#### 6.1.1.3 Footer

Ebenfalls auf der Seite **Seiteneinstellungen**, im Abschnitt **Footer**, gegliedert in drei Unterbereiche:

**Footer-Navigation** — Gleiches Feld-Schema wie beim Header (Typ „Seite"/„Externer Link", siehe [Abschnitt 6.1.1.2, Header](#6112-header)), jedoch ohne Untermenü-Ebene. Zusätzlich:

| Feld | Beschreibung |
|------|-------------|
| **Spaltenanzahl** | 1 bis 5 Spalten für die Anordnung der Footer-Navigations-Elemente im Frontend. |

<!-- Screenshot: Seiteneinstellungen — Footer-Abschnitt mit Footer-Navigations-Element, Spaltenanzahl, Social-Media- und Förderer-Bereich -->
![Footer: Navigation, Social Media und Förderer](../assets/screenshots/de/06-footer.png)

**Social Media Links**

| Feld | Beschreibung |
|------|-------------|
| **Social Media Links anzeigen** | Schalter, blendet den gesamten Social-Media-Bereich im Frontend-Footer ein oder aus. |
| **Icon** | Bild-Upload für das Plattform-Icon. |
| **Profil-URL** | Ziel-Link des Icons. |
| **Tooltip-Text** | Optionaler Text, der beim Überfahren des Icons angezeigt wird. |

**Förderer** — Liste für Sponsoren-Logos, zu der sich beliebig viele Einträge hinzufügen lassen: **Sponsor-Bild** (Upload, Pflichtfeld), **Sponsor-URL** (optional) und **Sponsor-Name** (optional, dient als Alt-Text-Ersatz in der Liste).

> **Hinweis:** Ein Feld für den Copyright-Text ist im Datenmodell vorhanden, taucht in der aktuellen Formularoberfläche jedoch nicht auf — der Copyright-Text lässt sich über dieses Formular derzeit nicht bearbeiten. Das weicht vom Struktur-Entwurf ab („Footer (Links, Social Media, **Copyright**, Layout)").

**Wirkung im Frontend:** Footer-Navigation, Social-Media-Icons und Förderer-Logos erscheinen im Footer des öffentlichen Dashboards, angeordnet in der gewählten Spaltenanzahl.

### 6.1.2 Theme / Branding

Die Seite **Theme** gliedert sich in vier Reiter: **Logo**, **Farben**, **Schrift** und **Konfiguration**.

#### 6.1.2.1 Logo

| Feld | Beschreibung |
|------|-------------|
| **Logo** | Bild-Upload, erscheint im Header des Frontends. |
| **Favicon** | Bild-Upload. Empfohlen: ICO, PNG oder SVG, max. 512 KB (siehe auch die Formatübersicht in [Kapitel 5.2, Unterstützte Formate und Größen](05-bilder-und-dateien.md)). |

<!-- Screenshot: Theme — Reiter Logo mit Logo- und Favicon-Upload-Feldern -->
![Theme: Logo](../assets/screenshots/de/06-theme-logo.png)

#### 6.1.2.2 Farben

Der Reiter **Farben** gliedert die Farbpalette in fünf Gruppen:

| Gruppe | Felder |
|--------|--------|
| **Farben** | Primärfarbe*, Sekundärfarbe*, Akzentfarbe (fällt auf Primärfarbe zurück, wenn leer) |
| **Hintergrund-Farben** | Haupt-, Karten-, Hero-Block-, Overlay-, Header- und Footer-Hintergrundfarbe |
| **Text-Farben** | Primäre, Sekundäre und Inverse Textfarbe, Link-Farbe, Link-Hover-Farbe |
| **Navigations-Farben** | Navigations-Textfarbe, Inaktive Navigations-Textfarbe (Sprach-Switcher), Navigations-Hover- & Aktiv-Farbe |
| **Rand- und Schatten-Farben** sowie **Slider-Farben** | Randfarbe, Trennlinienfarbe, Schattenfarbe; Slider-Schiene, -Griff und -Griffrand (jeweils Pflichtfeld) |

Mit `*` markierte Felder sind Pflichtfelder, alle übrigen Farbfelder sind optional und fallen auf einen sinnvollen Standardwert zurück.

<!-- Screenshot: Theme — Reiter Farben mit Farbwähler-Feldern -->
![Theme: Farben](../assets/screenshots/de/06-theme-farben.png)

**Wirkung im Frontend:** Alle Farbwerte fließen direkt in die CSS-Variablen des öffentlichen Dashboards ein und wirken sich sofort nach dem Speichern auf Hintergründe, Texte, Links, Navigation und den Bereichs-Slider im Frontend aus.

#### 6.1.2.3 Schrift / Typografie

Der Reiter **Schrift** steuert die Typografie:

| Feld | Beschreibung |
|------|-------------|
| **Schriftfamilie** | Auswahl aus vordefinierten Web-Fonts (u. a. Open Sans, Roboto, Inter, Lato, Montserrat, Poppins, Source Sans Pro). Ausgeblendet, sobald eine eigene Schriftdatei hochgeladen ist. |
| **Schriftschnitte** | Liste zur Auswahl der geladenen Schriftstärken (100–900). Ebenfalls ausgeblendet bei eigener Schriftdatei. |
| **Eigene Schriftart-Datei** | Optionaler Upload (WOFF2, WOFF, TTF, OTF, max. 5 MB, siehe [Kapitel 5.2, Unterstützte Formate und Größen](05-bilder-und-dateien.md)). Ersetzt die vordefinierte Schriftfamilien-Auswahl. |
| **Name der eigenen Schriftart** | Pflichtfeld, sobald eine eigene Schriftdatei hochgeladen wurde. |
| **Schriftgrößen** | Freitextfelder (CSS-Werte wie `1rem`) für Basis-, Klein- und Großschrift sowie die Überschriften h1–h6. |

<!-- Screenshot: Theme — Reiter Schrift mit Schriftfamilie und Schriftgrößen -->
![Theme: Schrift](../assets/screenshots/de/06-theme-schrift.png)

> **Hinweis:** Schriftfamilien-Auswahl und Schriftschnitte einerseits sowie eigene Schriftdatei andererseits schließen sich gegenseitig aus — sobald eine eigene Datei hochgeladen ist, verschwinden die vordefinierten Felder aus dem Formular.

> **Hinweis (Datenschutz):** Die vordefinierten Schriftfamilien (Open Sans, Roboto, Inter) liefert das Dashboard selbst aus. Es werden keine Schriften von Google Fonts oder anderen Drittanbietern nachgeladen.

#### 6.1.2.4 Konfiguration (Kacheln)

Der vierte Reiter **Konfiguration** ist im ursprünglichen Struktur-Entwurf nicht vorgesehen, existiert in der Live-Anwendung aber als Teil der Theme-Seite:

| Feld | Beschreibung |
|------|-------------|
| **Farbquelle für Kacheln** | Kategorie-Gruppe (siehe [Kapitel 4.1, Kategorie-Gruppen](04-kategorien.md#41-kategorie-gruppen-strukturebene)), deren Kategorie-Farben zur visuellen Einfärbung der Kacheln verwendet werden. |
| **Kategorie-Gruppe für Hintergrundseite** | Kategorie-Gruppe, deren Icons auf der Kachel-Hintergrundseite (Detail-Overlay) angezeigt werden. |

<!-- Screenshot: Theme — Reiter Konfiguration mit Kachel-Farbquelle und Kategorie-Gruppe für Hintergrundseite -->
![Theme: Konfiguration](../assets/screenshots/de/06-theme-konfiguration.png)

### 6.1.3 Themes / Templates

Ein **Theme** ist ein wiederverwendbares Bündel aus Branding- und Einstellungswerten, das mehrere Dashboards gemeinsam nutzen können: Ein Theme wird einmal gepflegt und dann beliebig vielen Dashboards zugewiesen — eine Änderung am Theme wirkt sich sofort auf alle zugewiesenen Dashboards aus. Die Seite **Themes** ist nur für Admins sichtbar und gilt installationsweit, nicht dashboard-bezogen.

Abgrenzung zu [Abschnitt 6.1.2, Theme / Branding](#612-theme--branding): Dort werden die Branding-Werte **eines einzelnen** Dashboards direkt bearbeitet. Die Seite **Themes** verwaltet dagegen wiederverwendbare **Bündel** derselben Werte, die sich über mehrere Dashboards teilen lassen.

> **Hinweis:** Ein Theme wird nicht auf der Themes-Seite selbst zugewiesen, sondern pro Dashboard in der **Dashboard-Konfiguration** (siehe [Abschnitt 6.1.4.3, Theme](#6143-theme)).

#### 6.1.3.1 Übersicht

Die Übersicht listet alle vorhandenen Themes in einer Tabelle:

| Spalte | Beschreibung |
|------|-------------|
| **Name** | Anzeigename des Themes. |
| **Slug** | Eindeutiger technischer Bezeichner (siehe [Abschnitt 6.1.3.2, Theme erstellen und bearbeiten](#6132-theme-erstellen-und-bearbeiten)). |
| **Genutzt von** | Badge mit der Anzahl der Dashboards, die dieses Theme verwenden. Grün, sobald mindestens ein Dashboard das Theme nutzt, andernfalls grau. |

Über die Kopfzeilen-Aktionen **Erstellen** und **Theme importieren** wird ein neues Theme angelegt (siehe [Abschnitt 6.1.3.2, Theme erstellen und bearbeiten](#6132-theme-erstellen-und-bearbeiten) bzw. [Abschnitt 6.1.3.3, Theme aus Bundle importieren](#6133-theme-aus-bundle-importieren)).

<!-- Screenshot: Themes — Tabellenübersicht mit Name, Slug und Genutzt-von-Badge -->
![Themes: Übersicht](../assets/screenshots/de/06-themes-uebersicht.png)

#### 6.1.3.2 Theme erstellen und bearbeiten

Über **Erstellen** oder einen Klick auf ein bestehendes Theme öffnet sich das Theme-Formular:

| Feld | Beschreibung |
|------|-------------|
| **Name** | Anzeigename des Themes. |
| **Slug** | Wird automatisch aus dem Namen erzeugt, ist aber editierbar und muss eindeutig sein. |
| **Einstellungen** | JSON-Objekt mit den Werten des Themes. Erlaubt sind die bekannten Einstellungs-Gruppen `general`, `content`, `dashboard`, `branding` und `integration`; unbekannte Gruppen werden abgewiesen. Der Wert wird gegen gültiges JSON und die bekannten Gruppen geprüft. |

Beispiel für ein **Einstellungen**-JSON:

```json
{
  "branding": {
    "primary_color": "#0d47a1"
  }
}
```

> **Hinweis:** Das **Einstellungen**-JSON ist der Weg für erfahrene Anwender. Der bequemere Weg ist der Import eines fertigen Bundles (siehe [Abschnitt 6.1.3.3, Theme aus Bundle importieren](#6133-theme-aus-bundle-importieren)).

<!-- Screenshot: Themes — Formular zum Erstellen und Bearbeiten mit Name, Slug und Einstellungen-JSON -->
![Theme erstellen und bearbeiten](../assets/screenshots/de/06-theme-erstellen.png)

#### 6.1.3.3 Theme aus Bundle importieren

Die Kopfzeilen-Aktion **Theme importieren** lädt ein Theme aus einem ZIP-Bundle hoch. Das ZIP enthält:

- eine Datei **`theme.json`** mit Name, Slug und Einstellungen,
- optional einen Ordner **`assets/`** mit Mediendateien.

Erlaubte Asset-Formate: PNG, SVG, JPG, JPEG, WEBP, WOFF2, WOFF und ICO. Die Gesamtgröße des Bundles ist auf **25 MB** begrenzt.

Der Import arbeitet **nach Slug**: Existiert bereits ein Theme mit demselben Slug, werden dessen Name, Einstellungen und Assets überschrieben; andernfalls wird ein neues Theme angelegt. Asset-Werte werden auf öffentliche URLs umgeschrieben.

> **Hinweis:** Das Bundle wird beim Import geprüft (Schutz vor Zip-Slip, Format-Whitelist, 25-MB-Grenze, Bereinigung von SVG-Dateien). Ein Bundle, das gegen diese Regeln verstößt, wird sauber abgewiesen.

<!-- Screenshot: Themes — Dialog zum Import eines Theme-Bundles (ZIP) -->
![Theme importieren](../assets/screenshots/de/06-theme-import.png)

#### 6.1.3.4 Theme einem Dashboard zuweisen

Ein Theme wird nicht auf der Themes-Seite zugewiesen, sondern pro Dashboard in der **Dashboard-Konfiguration** im Abschnitt **Theme** (siehe [Abschnitt 6.1.4.3, Theme](#6143-theme)). Zum Entfernen der Zuweisung wird das Feld dort geleert.

> **Hinweis:** Ein zugewiesenes Theme liefert die Grundwerte; einzelne Werte lassen sich auf dem Dashboard weiterhin über die normale Theme/Branding-Seite (siehe [Abschnitt 6.1.2, Theme / Branding](#612-theme--branding)) überschreiben.

#### 6.1.3.5 Theme löschen

Ein Theme lässt sich nur löschen, solange **kein** Dashboard es verwendet. Nutzt mindestens ein Dashboard das Theme, ist die Löschen-Schaltfläche deaktiviert (mit Tooltip), und der Server verhindert das Löschen zusätzlich. Der **Genutzt von**-Badge in der Übersicht (siehe [Abschnitt 6.1.3.1, Übersicht](#6131-übersicht)) dient als Frühwarnung: Solange er grün ist, kann das Theme nicht gelöscht werden.

### 6.1.4 Dashboard-Konfiguration

Zusätzlich zur installationsweiten Seiteneinstellungen-Seite besitzt **jedes** Dashboard (Tenant) eine eigene Seite **Dashboard-Konfiguration** mit dashboard-spezifischen Basisdaten.

<!-- Screenshot: Dashboard-Konfiguration mit Name, Zusatzinfo, Slug, Domain, Frontend Base URL -->
![Dashboard-Konfiguration](../assets/screenshots/de/06-dashboard-konfiguration.png)

> **Hinweis:** Diese Seite ist im ursprünglichen Struktur-Entwurf nicht als eigener Punkt vorgesehen, existiert in der Live-Anwendung aber als eigenständige Einstellungsseite. Sie wurde hier ergänzt, weil sie inhaltlich zu den Basisdaten des Dashboards gehört.

#### 6.1.4.1 Name

| Feld | Beschreibung |
|------|-------------|
| **Dashboard-Name** | Name dieses einzelnen Dashboards (z. B. „Stadt Regensburg"). Erscheint im Dashboard-Auswahl-Menü (siehe [Kapitel 1.1, Anmeldung und Oberfläche](01-erste-schritte.md)). |
| **Zusatzinfo** | Optionale Zusatzinformation (z. B. Kunde, Firma), ebenfalls im Dashboard-Auswahl-Menü sichtbar. |
| **Slug** | Schreibgeschützt. Identifiziert das Dashboard eindeutig, u. a. für Domain-Zuordnung. Das Standard-Dashboard trägt den Slug `default`. |

#### 6.1.4.2 Domain

| Feld | Beschreibung |
|------|-------------|
| **Domain** | Host/Domain, über die dieses Dashboard aufgelöst wird. Wird normalisiert (Kleinbuchstaben, `www.`-Präfix entfernt). Auflösungs-Priorität: Token vor Domain vor Standard-Dashboard. |
| **Frontend Base URL** | Vollständige URL der Frontend-Anwendung. Wird für CORS und API-Antworten verwendet. |

#### 6.1.4.3 Theme

| Feld | Beschreibung |
|------|-------------|
| **Theme** | Auswahlfeld. Weist diesem Dashboard ein Theme mit vordefinierten Branding-Werten zu. Zum Entfernen der Zuweisung wird das Feld geleert. |

Der Hilfetext des Felds lautet: „Weist diesem Dashboard ein Theme mit vordefinierten Branding-Werten zu."

Die Themes selbst werden auf der Seite **Themes** verwaltet (siehe [Abschnitt 6.1.3, Themes / Templates](#613-themes--templates)).

<!-- Screenshot: Dashboard-Konfiguration — Abschnitt Theme mit Theme-Auswahlfeld -->
![Dashboard-Konfiguration: Theme](../assets/screenshots/de/06-dashboard-konfig-theme.png)

### 6.1.5 API-Schlüssel verwalten

Die Seite **API Keys** verwaltet Zugriffstoken für die Dashboard-API dieses Tenants.

#### 6.1.5.1 API-Schlüssel Übersicht

Die Tabelle zeigt pro Token: Name, Besitzer, Berechtigungen (Badge), Aktiv-Status, Erstellungs- und letztes Nutzungsdatum sowie (ausblendbar) das letzte Aktualisierungsdatum. Filterbar nach Berechtigung und Aktiv-Status, durchsuchbar über das Suchfeld.

<!-- Screenshot: API Keys — Tabellenübersicht mit einem Beispiel-Token -->
![API Keys: Übersicht](../assets/screenshots/de/06-api-keys-uebersicht.png)

#### 6.1.5.2 Neuen Key erstellen

Über **Token erstellen** öffnet sich ein Dialog mit zwei Feldern:

| Feld | Beschreibung |
|------|-------------|
| **Token-Name** | Beschreibender Name zur späteren Identifikation (z. B. „Produktions-API"). |
| **Berechtigungen** | **Nur Lesen** (Lesezugriff auf öffentliche API-Endpunkte) oder **Lesen und Schreiben** (vollständiger CRUD-Zugriff auf Admin-Endpunkte). |

<!-- Screenshot: API Keys — Dialog "API Token erstellen" mit Token-Name und Berechtigungen -->
![API Keys: Token erstellen](../assets/screenshots/de/06-api-key-erstellen.png)

> **Hinweis:** Der erzeugte Token-Wert wird **nur einmal**, direkt nach der Erstellung, im Klartext angezeigt. Es gibt keine Möglichkeit, ihn später erneut einzusehen — bei Verlust muss ein neuer Token erstellt und der alte gelöscht werden. Den Token-Wert entsprechend sicher und außerhalb von Screenshots oder Tickets aufbewahren.

#### 6.1.5.3 Key bearbeiten

Über **Bearbeiten** lassen sich Name, Berechtigungen und Aktiv-Status eines bestehenden Tokens ändern (der Token-Wert selbst bleibt unverändert und wird nicht erneut angezeigt).

<!-- Screenshot: API-Schlüssel bearbeiten -->
![API-Schlüssel bearbeiten](../assets/screenshots/de/06-api-key-bearbeiten.png)

#### 6.1.5.4 Key löschen

Über **Löschen** (mit Sicherheitsabfrage) wird ein Token unwiderruflich entzogen — Anwendungen, die ihn verwenden, verlieren sofort den Zugriff.

<!-- Screenshot: API-Schlüssel löschen -->
![API-Schlüssel löschen](../assets/screenshots/de/06-api-key-loeschen.png)

### 6.1.6 Datenimport

Für den Bulk-Import von Kacheln, Kategorien, Kategorie-Gruppen und Kennzahlen per JSON-Bundle existiert eine eigene Seite **Datenimport**, ausführlich beschrieben in [Datenimport](../datenimport.de.md).

<!-- Screenshot: Datenimport -->
![Datenimport](../assets/screenshots/de/06-datenimport.png)

## 6.2 System

Die Gruppe **System** bündelt die installationsweiten Einstellungen, die unabhängig vom gerade gewählten Dashboard gelten.

> ### <span style="color:#d32f2f">⚠️ WIP — Work in progress</span>
>
> <span style="color:#d32f2f">**Screenshot wird noch ergänzt.**</span>

### 6.2.1 Benutzerverwaltung

Nur Admin-Accounts sehen den Menüpunkt **Benutzerverwaltung** (Gruppe „System"). Dort werden alle Benutzerkonten angelegt und verwaltet, unabhängig vom aktuell gewählten Dashboard.

#### 6.2.1.1 Benutzer Übersicht

Die Übersicht zeigt jeden Account mit E-Mail-Adresse, Name, zugewiesenen Dashboards und Aktiv-Status.

<!-- Screenshot: Benutzerverwaltung — Tabellenübersicht mit Admin- und Redaktions-Accounts -->
![Benutzerverwaltung: Übersicht](../assets/screenshots/de/07-benutzerverwaltung.png)

#### 6.2.1.2 Neuen Benutzer anlegen

Beim Anlegen eines Kontos legt das Feld **Rolle** fest, ob der Account **Admin** oder **Redaktion** ist. Bei Rolle **Redaktion** wird zusätzlich das Pflichtfeld **Dashboard-Freigaben** eingeblendet — eine Mehrfachauswahl der Dashboards, auf die dieser Account Zugriff erhält.

<!-- Screenshot: Formular "Benutzer erstellen" — Abschnitt Rolle & Dashboard-Freigaben mit Rolle Redakteur -->
![Benutzerformular: Rolle und Dashboard-Freigaben](../assets/screenshots/de/07-benutzer-rolle-formular.png)

#### 6.2.1.3 Benutzer bearbeiten

Ein Klick auf einen Account öffnet dasselbe Formular wie beim Anlegen. Dort lassen sich Rolle, Dashboard-Freigaben und der Aktiv-Schalter ändern.

> **Hinweis:** Ein Konto kann nicht sich selbst deaktivieren — der entsprechende Schalter ist im eigenen Datensatz gesperrt.

<!-- Screenshot: Benutzer bearbeiten -->
![Benutzer bearbeiten](../assets/screenshots/de/07-benutzer-bearbeiten.png)

#### 6.2.1.4 Benutzer löschen

Über die Zeilenaktion **Löschen** (mit Sicherheitsabfrage) wird ein Benutzerkonto endgültig entfernt.

<!-- Screenshot: Benutzer löschen -->
![Benutzer löschen](../assets/screenshots/de/07-benutzer-loeschen.png)
