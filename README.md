# Rowing Regatta Management System

A comprehensive web application for managing rowing regatta events, races, participants, and results.

## Features

- **Multi-Year Data Management**: Store and view regatta data across multiple years
- **Upcoming Races**: Display information about upcoming races including participants, start times, and distances
- **Race Results**: Record and display results for all races
- **Historical Data**: Browse through archives of past regattas
- **Admin Panel**: Complete management interface for all aspects of the system

## Technical Information

- Built with PHP and MySQL for robust data storage
- Bootstrap 5 for responsive, mobile-friendly design
- Modern JavaScript for enhanced user experience

## Installation

1. Clone this repository to your web server directory
2. Make sure you have PHP 7.4+ and MySQL 5.7+ installed
3. Create a MySQL database and update the connection details in `config/database.php`
4. Access the application through your web browser
5. Use the admin panel to add data

## Database Structure

The system uses the following tables:
- `years` - Store regatta years
- `events` - Store events for each year
- `races` - Store races for each event
- `teams` - Store team information
- `participants` - Store participant information
- `race_participants` - Connect races with participating teams

## Usage

### Adding Data
1. First add years through the admin panel
2. Add events for each year
3. Add races for each event
4. Add teams and participants
5. Assign teams to races
6. Update race results

### Viewing Data
- Use the main navigation to browse upcoming races, results, and historical data
- Filter results by year
- View detailed information about specific races

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

[Your Name] - Initial work 