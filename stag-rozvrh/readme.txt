=== STAG Rozvrh ===
Contributors: boubik
Tags: STAG, rozvrh, učitel, API, widget, shortcode
Requires at least: 5.0
Tested up to: 6.7.2
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

== Description ==
STAG Rozvrh je plugin pro WordPress, který načítá a zobrazuje rozvrh učitelů ze STAG API s možností vlastního formátování výpisu. Plugin poskytuje flexibilní zobrazení pomocí shortcode [stag_rozvrh] nebo widgetu. Navíc podporuje zadání učitelského loginu (staglogin) nebo přímo ID učitele (ucitidno).

== Installation ==
1. Stáhni si plugin jako ZIP archiv.
2. V administraci WordPressu přejdi do sekce **Pluginy > Instalace pluginů** a klikni na tlačítko **Nahrát plugin**.
3. Vyber stažený ZIP soubor a klikni na **Instalovat nyní**.
4. Po úspěšné instalaci plugin aktivuj.
5. Nastavení pluginu najdeš v **Nastavení > STAG Rozvrh**.

== Frequently Asked Questions ==
= Jak mohu změnit formát výpisu? =
V nastavení pluginu můžeš definovat vlastní formát řádku a hlavičku výpisu pomocí HTML a dostupných placeholderů (např. {predmet}, {cas_od}, {cas_do} apod.).

= Jak se zobrazuje čas poslední aktualizace? =
Plugin vypisuje datum a čas poslední aktualizace dat z API. Pokud cache nebyla aktualizována, zobrazí se zpráva „momentálně není aktualizovaný“.

== Screenshots ==
1. Nastavení pluginu v administraci.
2. Výpis rozvrhu pomocí shortcode.
3. Zobrazení widgetu na front-endu.

== Changelog ==
= 1.0 =
* První verze pluginu.
* Načítání rozvrhu učitelů ze STAG API.
* Podpora vlastního formátování výpisu a widgetu.
* Možnost zadání učitelského loginu (staglogin) nebo ID učitele (ucitidno).

== Upgrade Notice ==
Aktualizace na verzi 1.0 obsahuje nové možnosti zadání učitelského ID přímo. Pro více informací se podívejte do dokumentace pluginu.

== License ==
This program is free software; you can redistribute it and/or modify it under the terms of the MIT License.


=== STAG Rozvrh ===
Contributors: boubik
Tags: STAG, schedule, teacher, API, widget, shortcode
Requires at least: 5.0
Tested up to: 6.7.2
Stable tag: 1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

== Description ==
STAG Rozvrh is a WordPress plugin that loads and displays teacher schedules from the STAG API with customizable output formatting. The plugin provides flexible display via the shortcode [stag_rozvrh] or a widget. In addition, it supports entering either a teacher login (staglogin) or a teacher ID (ucitidno) directly.

== Installation ==
1. Download the plugin as a ZIP archive.
2. In your WordPress admin, go to **Plugins > Add New** and click **Upload Plugin**.
3. Select the downloaded ZIP file and click **Install Now**.
4. After successful installation, activate the plugin.
5. Plugin settings can be found under **Settings > STAG Rozvrh**.

== Frequently Asked Questions ==
= How can I change the output format? =
In the plugin settings, you can define a custom row format and header using HTML and available placeholders (e.g. {predmet}, {cas_od}, {cas_do}, etc.).

= How is the last update time displayed? =
The plugin displays the date and time of the last update from the API. If the cache has not been updated, the message "currently not updated" is shown.

== Screenshots ==
1. Plugin settings in the WordPress admin.
2. Schedule display using shortcode.
3. Widget display on the front-end.

== Changelog ==
= 1.0 =
* Initial release.
* Loads teacher schedules from the STAG API.
* Customizable output formatting and widget support.
* Supports both teacher login (staglogin) and teacher ID (ucitidno).

== Upgrade Notice ==
Upgrading to version 1.0 introduces direct teacher ID input. For more details, please refer to the plugin documentation.

== License ==
This program is free software; you can redistribute it and/or modify it under the terms of the MIT License.
