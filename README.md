# Berufsschulprojekt - Survey Portal 🏠📊

## Projektübersicht
Dieses Portal ermöglicht es der „Even Smarter Home“ (Hersteller & Online-Händler), direktes Feedback von Kunden zu Produkten und Dienstleistungen einzuholen. Das System bietet Administratoren Werkzeuge zur Umfrageverwaltung und motiviert Teilnehmer durch ein integriertes Belohnungssystem.

## Kern-Features
* **Rollenbasiertes Zugriffssystem:** Differenzierte Funktionen für Administratoren, registrierte Kunden und Gäste.
* **Umfrage-Management:** Erstellung von Umfragen mit spezifischen Laufzeiten, Kategorien (Produkt/Service) und automatischer Archivierung.
* **Interaktive Teilnahme:** Intuitive Abstimmungsoberfläche für Kunden mit Validierung (One-Vote-Policy).
* **Visualisierung:** Grafische Aufbereitung der Ergebnisse mittels Balkendiagrammen direkt im Frontend.
* **Belohnungssystem:** Automatisches Tracking von Abstimmungen und Vergabe von Badges für aktive Nutzer.
* **Suche & Filter:** Volltextsuche und Filterung nach Produkt- oder Servicekategorien.

## Tech-Stack
* **Framework:** Symfony 6.x / 7.x 
* **Templating:** Twig
* **ORM:** Doctrine (MySQL/MariaDB) 
* **Schnittstellen:** JSON-basierter Import von Produktkategorien aus dem eCommerce-System
* **Sicherheit:** Prepared Statements, CSRF-Schutz, Passwort-Hashes (BCrypt/Argon2) und Captcha-Integration.

