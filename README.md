# STAG Rozvrh

**STAG Rozvrh** is a WordPress plugin that loads and displays teacher schedules from the STAG API with customizable output formatting. The plugin supports display via a shortcode (`[stag_rozvrh]`) or via a classic widget. It allows you to specify either a teacher login (`staglogin`) or a teacher ID (`ucitidno`) directly.

## Features

- Loads teacher schedules from the STAG API.
- Customizable output using HTML templates and placeholders (e.g. `{predmet}`, `{cas_od}`, `{cas_do}`, etc.).
- Shortcode support: `[stag_rozvrh]`.
- Configurable cache duration and displays last update time (with timezone).
- Accepts both teacher login and teacher ID.

## Installation

1. Download zip from release.
1. Install the plugin via the WordPress admin dashboard.
1. Activate the plugin via the WordPress admin dashboard.
1. Configure the plugin settings under **Settings > STAG Rozvrh**.

## Usage

- **Shortcode:**  
  Use the shortcode in your posts or pages like this:  
  - With teacher login: `[stag_rozvrh staglogin="your_login"]`  
  - Or with teacher ID: `[stag_rozvrh ucitidno="your_teacher_id"]`

## License

This project is licensed under the [MIT License](LICENSE).