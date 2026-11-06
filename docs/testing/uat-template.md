# Abnahmetest (UAT) – Vorlage

Vorlage für den Abnahmetest einer CiviDash-Installation durch die Kommune. Kopieren Sie
die Datei für jeden Testlauf und füllen Sie die Felder aus.

## Testlauf

| Feld | Eintrag |
| --- | --- |
| Kommune / Dashboard | |
| Umgebung (URL) | |
| CiviDash-Version (Release) | |
| Datum | |
| Prüfende Personen | |
| Browser und Version | |
| Betriebssystem / Gerät | |
| Hilfsmittel (z. B. Screenreader, Zoom) | |

**Ergebnis-Werte:** OK · Mangel · nicht geprüft (n. g.)

**Mangelklassen:**

- **A – abnahmeverhindernd:** Kernfunktion fehlt oder Barriere ohne Umgehung.
- **B – wesentlich:** Funktion eingeschränkt, Umgehung möglich.
- **C – gering:** Schönheitsfehler, Text- oder Darstellungsfehler.

## 1. Öffentliches Dashboard

| Nr. | Prüfschritt | Erwartetes Ergebnis | Ergebnis | Klasse | Bemerkung |
| --- | --- | --- | --- | --- | --- |
| 1.1 | Startseite aufrufen | Seite lädt ohne Fehlermeldung, Logo und Navigation sichtbar | | | |
| 1.2 | Kachelübersicht aufrufen | Alle veröffentlichten Kacheln erscheinen | | | |
| 1.3 | Filter nach Kategorie anwenden | Nur passende Kacheln bleiben sichtbar | | | |
| 1.4 | Suche nach einem Kacheltitel | Treffer erscheinen, Leertreffer zeigt Hinweis | | | |
| 1.5 | Kachel öffnen | Detailansicht mit Diagramm, Werten und Quelle | | | |
| 1.6 | Daten einer Kachel herunterladen (CSV/JSON) | Datei lädt herunter und enthält die angezeigten Werte | | | |
| 1.7 | Sprache auf Englisch wechseln | Oberfläche und Inhalte erscheinen auf Englisch, URL beginnt mit `/en` | | | |
| 1.8 | Inhaltsseiten (Impressum, Datenschutz, Barrierefreiheit) | Seiten sind erreichbar und aktuell | | | |
| 1.9 | Darstellung auf dem Smartphone | Kein horizontales Scrollen, Navigation bedienbar | | | |
| 1.10 | Branding prüfen | Farben, Logo und Schrift entsprechen den Vorgaben der Kommune | | | |

## 2. Barrierefreiheit (WCAG 2.1 AA / BITV 2.0)

| Nr. | Prüfschritt | Erwartetes Ergebnis | Ergebnis | Klasse | Bemerkung |
| --- | --- | --- | --- | --- | --- |
| 2.1 | Gesamte Startseite nur mit Tastatur bedienen (Tab, Shift+Tab, Enter, Esc) | Alle Elemente erreichbar, keine Tastaturfalle | | | |
| 2.2 | Fokus sichtbar | Fokussiertes Element ist jederzeit deutlich erkennbar | | | |
| 2.3 | Farbkontrast von Text und Schaltflächen | Mindestens 4,5:1 (große Schrift 3:1), z. B. mit dem Colour Contrast Analyser | | | |
| 2.4 | Kontrast im Admin-Login, hell und dunkel | Schaltfläche „Anmelden“ mindestens 4,5:1 | | | |
| 2.5 | Zoom auf 200 % | Inhalte bleiben lesbar und bedienbar | | | |
| 2.6 | Screenreader (NVDA oder VoiceOver) | Überschriften, Landmarken und Linktexte sind verständlich | | | |
| 2.7 | Bilder und Diagramme | Alternativtext oder Textalternative vorhanden | | | |
| 2.8 | Automatischer Test mit axe DevTools | Keine Verstöße der Stufe „serious“ oder „critical“ | | | |

## 3. Redaktionssystem (Admin)

| Nr. | Prüfschritt | Erwartetes Ergebnis | Ergebnis | Klasse | Bemerkung |
| --- | --- | --- | --- | --- | --- |
| 3.1 | Anmeldung unter `/admin` | Anmeldung mit gültigen Daten möglich, falsche Daten werden abgewiesen | | | |
| 3.2 | Anmeldung per SSO (falls aktiviert) | Weiterleitung und Rückkehr funktionieren | | | |
| 3.3 | Kachel anlegen, bearbeiten, veröffentlichen | Änderung erscheint im öffentlichen Dashboard | | | |
| 3.4 | Kachel depublizieren | Kachel verschwindet aus dem öffentlichen Dashboard | | | |
| 3.5 | Kategorien und Gruppen pflegen | Änderung wirkt im Filter | | | |
| 3.6 | Inhaltsseite mit dem Seiten-Baukasten anlegen | Seite ist unter ihrem Slug erreichbar | | | |
| 3.7 | Branding ändern | Neue Farben erscheinen im Dashboard | | | |
| 3.8 | Datenimport (CSV/JSON) | Werte werden übernommen, Fehler verständlich gemeldet | | | |
| 3.9 | Benutzer und Rollen | Rolle ohne Rechte sieht die geschützten Bereiche nicht | | | |
| 3.10 | API-Schlüssel anlegen und widerrufen | Widerrufener Schlüssel wird abgewiesen | | | |

## 4. Mandantentrennung (bei mehreren Dashboards)

| Nr. | Prüfschritt | Erwartetes Ergebnis | Ergebnis | Klasse | Bemerkung |
| --- | --- | --- | --- | --- | --- |
| 4.1 | Dashboard über die eigene Domain aufrufen | Richtiges Dashboard erscheint | | | |
| 4.2 | Redakteur eines Dashboards öffnet den Admin-Bereich eines fremden Dashboards | Zugriff wird verweigert | | | |
| 4.3 | Daten eines Dashboards in einem anderen suchen | Keine fremden Daten sichtbar | | | |

## 5. Betrieb und Datenschutz

| Nr. | Prüfschritt | Erwartetes Ergebnis | Ergebnis | Klasse | Bemerkung |
| --- | --- | --- | --- | --- | --- |
| 5.1 | Netzwerkanalyse im Browser (Entwicklerwerkzeuge) | Keine Anfragen an Drittanbieter (Schriften, CDNs, Avatare) | | | |
| 5.2 | HTTPS und Sicherheits-Header | Gültiges Zertifikat, Header laut Betriebsdokumentation | | | |
| 5.3 | API-Dokumentation unter `/docs` | Dokumentation ist erreichbar | | | |

## Offene Mängel

| Nr. | Prüfschritt | Beschreibung | Klasse | Zuständig | Frist |
| --- | --- | --- | --- | --- | --- |
| | | | | | |

## Abnahmeerklärung

- [ ] Abnahme ohne Mängel
- [ ] Abnahme mit Mängeln der Klassen B/C; Behebung bis: ____________
- [ ] Keine Abnahme (Mängel der Klasse A)

| Rolle | Name | Datum | Unterschrift |
| --- | --- | --- | --- |
| Kommune | | | |
| Auftragnehmer | | | |
