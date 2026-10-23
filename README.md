# Mitfahrzentrale
CB AddOn Plugin - for Shared rides with special Bikes

## Shortcodes

Die folgenden Shortcodes werden vom Plugin bereitgestellt. Die Shortcodes werden jeweils auf einer eigenen WordPress-Seite eingefügt.

### Fahreransicht

    [mitfahrzentrale_fahrer]

Zeigt angemeldeten Benutzern die zukünftigen Mitfahr-Termine mit:

- Monatskalender zur Auswahl eines Termins
- Datum, Institution und Abholadresse
- Start- und Endzeit je Slot
- Fahrradname und Artikelbild
- Anzahl der eingetragenen Mitfahrer
- aktuell eingetragenem Fahrer
- Button zum Ein- bzw. Austragen als Fahrer

Die sichtbaren Rollen werden unter **Mitfahrzentrale → Berechtigungen → Fahreransicht** konfiguriert. Benutzer müssen zusätzlich angemeldet sein.

### Institutionsseite

    [mitfahrzentrale_institution]

Zeigt einer Institution die zukünftigen Termine ihres eigenen Standorts. Je Slot werden unter anderem Fahrer, Mitfahrer und das Mitfahrer-Auswahlfeld angezeigt. Die Mitfahrer können dort gespeichert und verwaltet werden.

Die Seite enthält ebenfalls die Monatskalender-Ansicht der Fahreransicht. Termine können über den Kalender ausgewählt werden; anschließend wird nur der gewählte Tag angezeigt.

Voraussetzungen:

- Benutzer ist angemeldet.
- Die Benutzerrolle ist unter **Mitfahrzentrale → Berechtigungen → Institutionsseite** freigeschaltet.
- Der Benutzer ist einer Institution zugeordnet oder besitzt die Berechtigung edit_users.

### Mitfahrer-Verwaltung

    [mitfahrzentrale_mitfahrer]

Stellt eine separate Verwaltungsseite für die Mitfahrer einer Institution bereit. Dort können Mitfahrer hinzugefügt und entfernt werden.

Voraussetzungen:

- Benutzer ist angemeldet.
- Die Benutzerrolle ist für die Institutionsseite freigeschaltet.
- Der Benutzer ist einer Institution zugeordnet oder besitzt die Berechtigung edit_users.

### Organisatorseite

    [mitfahrzentrale_admin]

Zeigt die Organisatoransicht mit zukünftigen Terminen und den zugehörigen Slots. Zusätzlich können vergangene Termine eingeblendet werden. Angezeigt werden unter anderem:

- eingesetzte Fahrräder
- Fahrer- und Kontaktdaten
- Mitfahrer und Auslastung
- Status der Fahrerbestätigung
- Funktion zum Drucken eines Tages
- Funktion zum Versenden bzw. erneuten Versenden der Fahrerbestätigung

Die sichtbaren Rollen werden unter **Mitfahrzentrale → Berechtigungen → Organisatorseite** konfiguriert.

## Rollenberechtigungen

Die Konfigurationsseite befindet sich im WordPress-Backend unter **Mitfahrzentrale → Berechtigungen**. Für jede Ansicht können mehrere WordPress-Rollen ausgewählt werden. Wird keine Rolle ausgewählt, bleibt die jeweilige Ansicht für normale Benutzer gesperrt.

Die Berechtigungsprüfung erfolgt zusätzlich zu den vorhandenen Funktionsberechtigungen. Beispielsweise benötigt die Institutionsseite weiterhin eine Institutionszuordnung beziehungsweise edit_users. Die Einstellung erlaubt also den Zugriff auf die Ansicht, ersetzt aber keine institutions- oder aktionsbezogenen Berechtigungen.

## Voraussetzungen und Einrichtung

1. CommonsBooking und die Artikel/Fahrräder müssen eingerichtet sein.
2. Im Backend Institutionen, Fahrer und Mitfahrer anlegen beziehungsweise zuordnen.
3. Eine Seite für jeden benötigten Shortcode erstellen.
4. Die Rollen unter **Mitfahrzentrale → Berechtigungen** konfigurieren.
5. Termine mit Zeitslots, Fahrradartikeln und Institutionen anlegen.

Die Frontend-Layoutregeln liegen in [mitfahrzentrale.css](./mitfahrzentrale.css).
