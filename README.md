# Flight Status Web App

A real-time flight status web application built with Laravel and Vue.js, powered by the Aviationstack API.

## Features

- Real-time flight information
- Search for airports, airlines, and more
- Responsive design for all devices
- Laravel backend with API proxy
- Vue.js frontend with TypeScript

## Requirements

- PHP 8.1 or higher
- Composer
- Node.js and npm
- Aviationstack API key

## Installation

1. Clone the repository:

    ```
    git clone <repository-url>
    cd flight-status
    ```

2. Install PHP dependencies:

    ```
    composer install
    ```

3. Install JavaScript dependencies:

    ```
    npm install
    ```

4. Create a `.env` file from the example:

    ```
    cp .env.example .env
    ```

5. Generate the application key:

    ```
    php artisan key:generate
    ```

6. Create the database:

    ```
    touch database/database.sqlite
    php artisan migrate
    ```

7. Configure your Aviationstack API key:
   Edit your `.env` file and add the following:

    ```
    AVIATIONSTACK_API_KEY=your_api_key_here
    AVIATIONSTACK_API_URL=http://api.aviationstack.com/v1
    AVIATIONSTACK_CACHE_TIME=15
    ```

    Replace `your_api_key_here` with your actual Aviationstack API key.

8. Build the frontend assets:

    ```
    npm run build
    ```

9. Start the development server:
    ```
    php artisan serve
    ```

Visit `http://localhost:8000` in your browser to access the application.

## Getting an Aviationstack API Key

1. Visit [aviationstack.com](https://aviationstack.com/) and sign up for an account
2. Choose a plan based on your needs (there is a free plan with limited requests)
3. After signing up, you'll receive an API key
4. Add this key to your `.env` file as described in the installation steps

## Usage

- Use the search box to find information about flights, airports, airlines, etc.
- Select the data type you want to search for using the buttons
- Enter your search query following the guidelines for each data type
- View the results in real-time

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
